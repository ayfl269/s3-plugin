<?php
namespace S3_Media_Uploader;

defined('ABSPATH') || exit;

class Media_Handler {
    private static $instance = null;
    private $s3_service;

    private function __construct() {
        $this->s3_service = S3_Service::get_instance();
        
        add_filter('upload_dir', [$this, 'custom_upload_dir']);
        add_filter('wp_handle_upload_prefilter', [$this, 'pre_upload_handler']);
        add_filter('wp_handle_upload', [$this, 'handle_original_upload']);
        add_filter('wp_update_attachment_metadata', [$this, 'handle_metadata_update'], 10, 2);
        add_action('delete_attachment', [$this, 'delete_s3_files']);
        add_filter('manage_media_columns', [$this, 'add_media_columns']);
        add_action('manage_media_custom_column', [$this, 'render_media_columns'], 10, 2);
        add_action('delete_local_file_hook', [$this, 'delete_local_file_callback']);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function custom_upload_dir($uploads) {
        $options = $this->s3_service->get_options();
        if (($options['s3_mode'] ?? 'local') !== 'local' && !empty($options['s3_public_url'])) {
            $uploads['baseurl'] = rtrim($options['s3_public_url'], '/');
        }
        return $uploads;
    }

    public function pre_upload_handler($file) {
        $options = $this->s3_service->get_options();
        if (($options['s3_mode'] ?? 'local') !== 'local') {
            if (empty($options['s3_public_url']) || !$this->s3_service->check_connection()) {
                error_log('S3未配置或连接失败，文件将保存到本地。');
            }
        }
        return $file;
    }

    public function handle_original_upload($upload) {
        $options = get_option('s3_image_conversion_options');
        if (!empty($options['convert_to_webp']) && $this->is_image($upload['file'])) {
            $old_file = $upload['file'];
            $webp_path = $this->convert_to_webp($old_file);
            if ($webp_path) {
                $upload['file'] = $webp_path;
                $upload['url'] = str_replace(basename($old_file), basename($webp_path), $upload['url']);
                $upload['type'] = 'image/webp';
            }
        }
        return $upload;
    }

    public function handle_metadata_update($metadata, $attachment_id) {
        $options = $this->s3_service->get_options();
        $mode = $options['s3_mode'] ?? 'local';
        if ($mode === 'local') return $metadata;

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) return $metadata;

        $folder = !empty($options['s3_folder']) ? rtrim($options['s3_folder'], '/') : '/wp-uploads';
        $folder = str_replace(['{year}', '{month}'], [date('Y'), date('m')], $folder);
        $folder = ltrim($folder, '/');

        $files_to_upload = [];
        $base_dir = dirname($file_path);
        
        // 主文件
        $files_to_upload[basename($file_path)] = $file_path;
        
        // 缩略图
        if (!empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size) {
                $files_to_upload[$size['file']] = $base_dir . '/' . $size['file'];
            }
        }

        $uploaded_count = 0;
        foreach ($files_to_upload as $remote_name => $local_path) {
            $remote_path = $folder . '/' . $remote_name;
            if ($this->s3_service->upload_file($local_path, $remote_path)) {
                $uploaded_count++;
                if ($mode === 's3') {
                    wp_schedule_single_event(time() + 10, 'delete_local_file_hook', [$local_path]);
                }
            }
        }

        if ($uploaded_count > 0) {
            // 更新数据库中的相对路径
            update_post_meta($attachment_id, '_wp_attached_file', $folder . '/' . basename($file_path));
            if (!empty($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $key => $size) {
                    $metadata['sizes'][$key]['file'] = basename($size['file']);
                }
            }
        }

        return $metadata;
    }

    public function delete_s3_files($attachment_id) {
        $options = $this->s3_service->get_options();
        if (($options['s3_mode'] ?? 'local') === 'local') return;

        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata || !isset($metadata['file'])) return;

        $folder = dirname($metadata['file']);
        $files_to_delete = [$metadata['file']];

        if (!empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size) {
                $files_to_delete[] = $folder . '/' . $size['file'];
            }
        }

        foreach ($files_to_delete as $file) {
            $this->s3_service->delete_file($file);
        }
    }

    public function add_media_columns($columns) {
        $columns['storage_location'] = '存储位置';
        return $columns;
    }

    public function render_media_columns($column_name, $id) {
        if ('storage_location' !== $column_name) return;
        
        $options = $this->s3_service->get_options();
        $s3_public_url = !empty($options['s3_public_url']) ? rtrim($options['s3_public_url'], '/') : '';
        
        // 改进判断逻辑：直接检查附件 URL 是否包含 S3 公共域名
        $attachment_url = wp_get_attachment_url($id);
        $is_s3 = false;
        
        if (!empty($s3_public_url)) {
            // 提取域名进行比对，避免协议(http/https)差异影响
            $s3_host = parse_url($s3_public_url, PHP_URL_HOST);
            $file_host = parse_url($attachment_url, PHP_URL_HOST);
            
            if ($s3_host && $file_host && $s3_host === $file_host) {
                $is_s3 = true;
            }
        }
        
        if ($is_s3) {
            echo '<span class="dashicons dashicons-networking" style="color:#46b450;"></span> S3存储';
        } else {
            echo '<span class="dashicons dashicons-desktop" style="color:#72777c;"></span> 本地存储';
        }
    }

    public function delete_local_file_callback($file_path) {
        if (file_exists($file_path)) @unlink($file_path);
    }

    private function is_image($path) {
        return in_array(wp_check_filetype($path)['type'], ['image/jpeg', 'image/png', 'image/webp']);
    }

    private function convert_to_webp($file_path) {
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
        $editor = wp_get_image_editor($file_path);
        if (!is_wp_error($editor)) {
            $result = $editor->save($webp_path, 'image/webp');
            if (!is_wp_error($result)) {
                @unlink($file_path);
                return $webp_path;
            }
        }
        return false;
    }
}
