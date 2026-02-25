// admin.js
jQuery(document).ready(function($) {
    // 仅在 S3 配置页面提供连接测试功能
    if ($('#s3-connection-test-btn').length) {
        $('#s3-connection-test-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $status = $('#s3-connection-status-msg');
            
            $btn.prop('disabled', true).text('正在测试...');
            $status.text('').removeClass('success error');

            $.ajax({
                url: s3_media.ajax_url,
                type: 'POST',
                data: {
                    action: 'check_s3_connection',
                    nonce: s3_media.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.text('✓ ' + response.data).addClass('success');
                        $('#wp-admin-bar-s3-status .ab-icon').css('color', '#46b450');
                    } else {
                        $status.text('✗ ' + response.data).addClass('error');
                        $('#wp-admin-bar-s3-status .ab-icon').css('color', '#dc3232');
                    }
                },
                error: function() {
                    $status.text('✗ 网络错误，请稍后再试').addClass('error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('测试 S3 连接');
                }
            });
        });
    }

    // 初始化设置页面功能
    if ($('.wrap h1').text().indexOf('S3') !== -1) {
    }

});
