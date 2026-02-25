<?php
namespace S3_Media_Uploader;

defined('ABSPATH') || exit;

class Admin_Settings {
    private static $instance = null;
    private $s3_service;

    private function __construct() {
        $this->s3_service = S3_Service::get_instance();
        
        add_action('admin_menu', [$this, 'add_settings_pages']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_bar_menu', [$this, 'add_admin_bar_indicator'], 100);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_check_s3_connection', [$this, 'ajax_check_connection']);
        add_filter('plugin_action_links_' . plugin_basename(dirname(__DIR__) . '/S3MediaUploader.php'), [$this, 'add_plugin_action_links']);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add_settings_pages() {
        add_menu_page('S3', 'S3', 'manage_options', 's3-settings', [$this, 'render_s3_settings_page'], 'dashicons-networking', 100);
        add_submenu_page('s3-settings', 'S3配置', 'S3配置', 'manage_options', 's3-settings', [$this, 'render_s3_settings_page']);
        add_submenu_page('s3-settings', '其它设置', '其它设置', 'manage_options', 's3-other-settings', [$this, 'render_other_settings_page']);
    }

    public function register_settings() {
        register_setting('s3_settings', 's3_media_options', [$this, 'sanitize_s3_options']);
        register_setting('s3_other_settings', 's3_image_conversion_options', [$this, 'sanitize_other_options']);

        add_settings_section('s3_section', '连接设置', [$this, 's3_section_callback'], 's3-settings');
        add_settings_field('s3_mode', '存储模式', [$this, 'mode_field_callback'], 's3-settings', 's3_section');
        add_settings_field('s3_access_key', 'Access Key', [$this, 'access_key_field_callback'], 's3-settings', 's3_section');
        add_settings_field('s3_secret_key', 'Secret Key', [$this, 'secret_key_field_callback'], 's3-settings', 's3_section');
        add_settings_field('s3_region', 'Region', [$this, 'region_field_callback'], 's3-settings', 's3_section');
        add_settings_field('s3_bucket', 'Bucket', [$this, 'bucket_field_callback'], 's3-settings', 's3_section');
        add_settings_field('s3_api_endpoint', 'API端点URL', [$this, 'api_endpoint_field_callback'], 's3-settings', 's3_section');
        add_settings_field('s3_public_url', '公共访问URL', [$this, 'public_url_field_callback'], 's3-settings', 's3_section');
        add_settings_field('s3_folder', '目标文件夹', [$this, 'folder_field_callback'], 's3-settings', 's3_section');

        add_settings_section('s3_other_section', '其它设置', [$this, 'other_section_callback'], 's3-other-settings');
        add_settings_field('convert_to_webp', '转换为WebP格式', [$this, 'convert_to_webp_field_callback'], 's3-other-settings', 's3_other_section');
    }

    public function sanitize_s3_options($input) {
        $input['s3_public_url'] = esc_url_raw($input['s3_public_url'], ['http', 'https']);
        $input['s3_api_endpoint'] = esc_url_raw($input['s3_api_endpoint'], ['http', 'https']);
        $input['s3_access_key'] = sanitize_text_field($input['s3_access_key']);
        $input['s3_secret_key'] = sanitize_text_field($input['s3_secret_key']);
        $input['s3_region'] = sanitize_text_field($input['s3_region']);
        $input['s3_bucket'] = sanitize_text_field($input['s3_bucket']);
        $input['s3_folder'] = sanitize_text_field($input['s3_folder']);
        
        $transient_key = 's3_connection_status_' . md5($input['s3_access_key'] . $input['s3_bucket']);
        delete_transient($transient_key);
        
        return $input;
    }

    public function sanitize_other_options($input) {
        $input['convert_to_webp'] = isset($input['convert_to_webp']) ? (bool) $input['convert_to_webp'] : false;
        return $input;
    }

    // Callbacks and Rendering (omitted for brevity in this thought, but will be included in full file)
    public function s3_section_callback() { echo '<p>配置您的S3连接信息。</p>'; }
    public function other_section_callback() { echo '<p>配置其它相关选项。</p>'; }

    public function mode_field_callback() {
        $options = $this->s3_service->get_options();
        $mode = $options['s3_mode'] ?? 'local';
        ?>
        <select name="s3_media_options[s3_mode]">
            <option value="local" <?php selected($mode, 'local'); ?>>本地模式</option>
            <option value="s3" <?php selected($mode, 's3'); ?>>云端模式</option>
            <option value="sync" <?php selected($mode, 'sync'); ?>>同步模式</option>
        </select>
        <p class="description">选择文件存储模式。</p>
        <?php
    }

    public function access_key_field_callback() {
        $options = $this->s3_service->get_options();
        echo '<input type="text" name="s3_media_options[s3_access_key]" value="'.esc_attr($options['s3_access_key'] ?? '').'" class="regular-text">';
    }

    public function secret_key_field_callback() {
        $options = $this->s3_service->get_options();
        echo '<input type="password" name="s3_media_options[s3_secret_key]" value="'.esc_attr($options['s3_secret_key'] ?? '').'" class="regular-text">';
    }

    public function region_field_callback() {
        $options = $this->s3_service->get_options();
        echo '<input type="text" name="s3_media_options[s3_region]" value="'.esc_attr($options['s3_region'] ?? '').'" class="regular-text" placeholder="us-east-1">';
    }

    public function bucket_field_callback() {
        $options = $this->s3_service->get_options();
        echo '<input type="text" name="s3_media_options[s3_bucket]" value="'.esc_attr($options['s3_bucket'] ?? '').'" class="regular-text">';
    }

    public function api_endpoint_field_callback() {
        $options = $this->s3_service->get_options();
        echo '<input type="url" name="s3_media_options[s3_api_endpoint]" value="'.esc_attr($options['s3_api_endpoint'] ?? '').'" class="regular-text" placeholder="https://s3.amazonaws.com">';
        echo '<p class="description">S3 API端点URL（用于服务连接）</p>';
    }

    public function public_url_field_callback() {
        $options = $this->s3_service->get_options();
        echo '<input type="url" name="s3_media_options[s3_public_url]" value="'.esc_attr($options['s3_public_url'] ?? '').'" class="regular-text" placeholder="https://your-bucket.s3.amazonaws.com/">';
        echo '<p class="description">公共访问URL（用于前端显示）</p>';
    }

    public function folder_field_callback() {
        $options = $this->s3_service->get_options();
        echo '<input type="text" name="s3_media_options[s3_folder]" value="'.esc_attr($options['s3_folder'] ?? '').'" class="regular-text">';
        echo '<p class="description">文件存储路径（支持日期变量：{year}/{month}）</p>';
    }

    public function convert_to_webp_field_callback() {
        $options = get_option('s3_image_conversion_options');
        $convert = !empty($options['convert_to_webp']);
        ?>
        <label>
            <input type="checkbox" name="s3_image_conversion_options[convert_to_webp]" value="1" <?php checked($convert); ?>>
            启用上传时将图片转换为WebP格式
        </label>
        <?php
    }

    public function render_s3_settings_page() {
        ?>
        <div class="wrap">
            <h1>S3配置</h1>
            <form method="post" action="options.php">
                <?php settings_fields('s3_settings'); do_settings_sections('s3-settings'); submit_button(); ?>
            </form>
            <hr>
            <h2>连接测试</h2>
            <p>
                <button type="button" id="s3-connection-test-btn" class="button">测试 S3 连接</button>
                <span id="s3-connection-status-msg" style="margin-left: 10px; font-weight: bold;"></span>
            </p>
        </div>
        <?php
    }

    public function render_other_settings_page() {
        ?>
        <div class="wrap">
            <h1>其它设置</h1>
            <form method="post" action="options.php">
                <?php settings_fields('s3_other_settings'); do_settings_sections('s3-other-settings'); submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function add_admin_bar_indicator($admin_bar) {
        if (!current_user_can('manage_options')) return;
        $status = $this->s3_service->check_connection();
        $color = $status ? '#46b450' : '#dc3232';
        $admin_bar->add_node([
            'id'    => 's3-status',
            'title' => '<span class="ab-icon dashicons dashicons-networking" style="color:'.esc_attr($color).';"></span>',
            'href'  => admin_url('admin.php?page=s3-settings'),
            'meta'  => ['title' => $status ? 'S3服务连接正常' : 'S3服务连接断开']
        ]);
    }

    public function enqueue_scripts() {
        // 使用更稳健的方式获取插件根目录 URL
        $plugin_root_url = plugins_url('/', dirname(__DIR__) . '/S3MediaUploader.php');
        
        wp_enqueue_script('s3-admin-js', $plugin_root_url . 'admin.js', ['jquery'], '1.5', true);
        wp_localize_script('s3-admin-js', 's3_media', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('s3_media_nonce')
        ]);
        wp_enqueue_style('s3-admin-css', $plugin_root_url . 'admin.css', [], '1.5');
    }

    public function ajax_check_connection() {
        check_ajax_referer('s3_media_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('权限不足');
        
        // 强制刷新连接状态，不使用缓存
        if ($this->s3_service->check_connection(true)) {
            wp_send_json_success('S3 连接正常');
        } else {
            wp_send_json_error('S3 连接失败，请检查配置或网络');
        }
    }

    public function add_plugin_action_links($links) {
        array_unshift($links, '<a href="' . admin_url('admin.php?page=s3-settings') . '">设置</a>');
        return $links;
    }
}
