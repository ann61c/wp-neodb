jQuery(document).ready(function($) {
    // Handle delete button click
    $('.wpn-delete-subject').on('click', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var subjectId = $link.data('subject-id');
        var subjectName = $link.data('subject-name');
        var nonce = $link.data('nonce');
        var fallbackUrl = $link.data('fallback-url');
        var $row = $link.closest('tr');
        
        // Show confirmation dialog
        if (!confirm('确定要删除 "' + subjectName + '" 吗？\n\n此操作无法撤销。')) {
            return;
        }
        
        // Disable the link to prevent double-clicks
        $link.css('pointer-events', 'none').css('opacity', '0.5');
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl, // WordPress automatically provides this variable
            type: 'POST',
            data: {
                action: 'wpn_delete_subject',
                subject_id: subjectId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Add fade-out animation
                    $row.addClass('wpn-deleting');
                    
                    // Show success notice
                    showNotice(response.data.message || '删除成功', 'success');
                    
                    // Remove the row after animation completes
                    setTimeout(function() {
                        $row.fadeOut(400, function() {
                            $(this).remove();
                        });
                    }, 300);
                } else {
                    // Show error notice
                    showNotice(response.data.message || '删除失败，请重试', 'error');
                    // Re-enable the link
                    $link.css('pointer-events', '').css('opacity', '');
                }
            },
            error: function(xhr, status, error) {
                // If AJAX fails, try the fallback URL
                console.error('AJAX error:', error);
                showNotice('AJAX请求失败，使用传统方式删除...', 'warning');
                setTimeout(function() {
                    window.location.href = fallbackUrl;
                }, 1000);
            }
        });
    });
    
    // Show WordPress-style notice
    function showNotice(message, type) {
        type = type || 'success';
        var noticeClass = 'notice-' + type;
        
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible wpn-delete-notice"><p>' + message + '</p></div>');
        
        // Insert after the page heading or at the top of .wrap
        var $target = $('.wrap > h1, .wrap > h2').first();
        if ($target.length) {
            $notice.insertAfter($target);
        } else {
            $('.wrap').prepend($notice);
        }
        
        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            $notice.fadeOut(400, function() {
                $(this).remove();
            });
        }, 3000);
    }
});
