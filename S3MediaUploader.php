<?php
/*
Plugin Name: S3媒体上传 (已优化)
Description: 适用于 WordPress 的 S3 插件，通过S3协议将上传的媒体文件保存到云端存储中。支持自动转WebP及缩略图同步。
Version: 1.1
Author: Ayfl
Author URI: https://www.ayfl.cn/
License: GPLv3 or later
Text Domain: s3-media-upload
*/

defined('ABSPATH') || exit;

// 1. 加载 AWS SDK
if (file_exists(plugin_dir_path(__FILE__) . 'vendor/autoloader.php')) {
    require_once plugin_dir_path(__FILE__) . 'vendor/autoloader.php';
} else {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>S3媒体上传插件：找不到AWS SDK，请确保已正确安装依赖。</p></div>';
    });
    return;
}

// 2. 加载插件核心类
require_once plugin_dir_path(__FILE__) . 'includes/class-s3-service.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-media-handler.php';

// 3. 初始化插件
function init_s3_media_uploader() {
    \S3_Media_Uploader\S3_Service::get_instance();
    \S3_Media_Uploader\Admin_Settings::get_instance();
    \S3_Media_Uploader\Media_Handler::get_instance();
}
add_action('plugins_loaded', 'init_s3_media_uploader');
