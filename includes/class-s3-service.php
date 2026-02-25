<?php
namespace S3_Media_Uploader;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

defined('ABSPATH') || exit;

class S3_Service {
    private static $instance = null;
    private $s3_client = null;
    private $options = null;

    private function __construct() {
        $this->options = get_option('s3_media_options');
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_options() {
        return $this->options;
    }

    public function check_connection($force_refresh = false) {
        static $connected = null;
        if ($connected !== null && !$force_refresh) return $connected;

        if (empty($this->options['s3_access_key']) || empty($this->options['s3_secret_key']) || 
            empty($this->options['s3_bucket'])) {
            $connected = false;
            return false;
        }

        $transient_key = 's3_connection_status_' . md5($this->options['s3_access_key'] . $this->options['s3_bucket']);
        
        if (!$force_refresh) {
            $cached_status = get_transient($transient_key);
            if (false !== $cached_status) {
                $connected = (bool) $cached_status;
                return $connected;
            }
        }

        try {
            $s3Config = [
                'version' => 'latest',
                'region' => !empty($this->options['s3_region']) ? $this->options['s3_region'] : 'us-east-1',
                'credentials' => [
                    'key' => $this->options['s3_access_key'],
                    'secret' => $this->options['s3_secret_key'],
                ],
            ];
            
            if (!empty($this->options['s3_api_endpoint'])) {
                $s3Config['endpoint'] = $this->options['s3_api_endpoint'];
                $s3Config['use_path_style_endpoint'] = true;
            }
            
            $this->s3_client = new S3Client($s3Config);
            $this->s3_client->headBucket(['Bucket' => $this->options['s3_bucket']]);
            
            $connected = true;
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
        } catch (AwsException $e) {
            error_log('S3连接失败: ' . $e->getMessage());
            $connected = false;
            set_transient($transient_key, 0, HOUR_IN_SECONDS);
        }

        return $connected;
    }

    private function get_client() {
        if ($this->s3_client === null) {
            $s3Config = [
                'version' => 'latest',
                'region' => !empty($this->options['s3_region']) ? $this->options['s3_region'] : 'us-east-1',
                'credentials' => [
                    'key' => $this->options['s3_access_key'],
                    'secret' => $this->options['s3_secret_key'],
                ],
            ];
            if (!empty($this->options['s3_api_endpoint'])) {
                $s3Config['endpoint'] = $this->options['s3_api_endpoint'];
                $s3Config['use_path_style_endpoint'] = true;
            }
            $this->s3_client = new S3Client($s3Config);
        }
        return $this->s3_client;
    }

    public function upload_file($local_path, $remote_path) {
        if (!$this->check_connection()) return false;
        
        try {
            $this->get_client()->upload(
                $this->options['s3_bucket'],
                ltrim($remote_path, '/'),
                fopen($local_path, 'r'),
                'public-read',
                [
                    'params' => [
                        'ACL' => 'public-read',
                        'ContentType' => mime_content_type($local_path) ?: 'binary/octet-stream'
                    ]
                ]
            );
            return true;
        } catch (AwsException $e) {
            error_log('S3上传失败: ' . $e->getMessage());
            return false;
        }
    }

    public function delete_file($remote_path) {
        if (!$this->check_connection()) return false;
        
        try {
            $this->get_client()->deleteObject([
                'Bucket' => $this->options['s3_bucket'],
                'Key' => ltrim($remote_path, '/')
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('S3删除失败: ' . $e->getMessage());
            return false;
        }
    }
}
