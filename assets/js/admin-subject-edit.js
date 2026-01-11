jQuery(document).ready(function($) {
    var restUrl = wpn_subject_edit.rest_url;
    var subjectId = wpn_subject_edit.subject_id;
    var nonce = wpn_subject_edit.nonce;
    
    // Store initial values for all fields that have a revert button
    $('input, textarea').each(function() {
        var input = $(this);
        if (input.next('.revert-btn').length) {
            input.data('original-value', input.val());
        }
    });

    // Monitor manual input changes
    $(document).on('input propertychange', 'input, textarea', function() {
        var input = $(this);
        var originalValue = input.data('original-value');
        var revertBtn = input.next('.revert-btn');
        
        if (revertBtn.length && originalValue !== undefined) {
            if (input.val() != originalValue) {
                input.addClass('previewing');
                revertBtn.show();
            } else {
                input.removeClass('previewing');
                revertBtn.hide();
            }
        }
    });

    // Helper to show refresh status (Snackbar)
    function showStatus(message, type) {
        var snackbar = $('#wpn-snackbar');
        snackbar.removeClass('success error show')
                .addClass(type)
                .html(message);
        
        // Force reflow
        snackbar[0].offsetHeight;
        
        snackbar.addClass('show');
        
        // Clear existing timeout if any
        if (window.statusTimeout) clearTimeout(window.statusTimeout);
        
        // Hide after 5 seconds
        window.statusTimeout = setTimeout(function() {
            snackbar.removeClass('show');
        }, 5000);
    }

    // Click handler for refresh buttons
    $('.source-refresh-btn').on('click', function() {
        var btn = $(this);
        var source = btn.data('source');
        var originalText = btn.text(); // Store original text
        
        // Show loading
        btn.prop('disabled', true).text('加载中...');
        $('#wpn-snackbar').removeClass('show'); // Hide previous status
        
        // AJAX call
        $.ajax({
            url: restUrl,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            data: {
                subject_id: subjectId,
                source: source,
                action: wpn_subject_edit.action
            },
            success: function(response) {
                console.log('API Response:', response); // Debug
                if (response.success) {
                    fillFormWithPreview(response.data, response.source);
                } else {
                    showStatus('<span class="dashicons dashicons-warning"></span> 获取数据失败: ' + (response.message || '未知错误'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr.responseText); // Debug
                showStatus('<span class="dashicons dashicons-warning"></span> 请求失败: ' + error, 'error');
            },
            complete: function() {
                // Restore original button text
                btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Fill form with preview data
    function fillFormWithPreview(data, source) {
        var updated = 0;
        console.log('Filling form with data:', data); // Debug
        $.each(data, function(field, value) {
            var input = $('[name="' + field + '"]');
            if (input.length && value !== undefined && value !== null) {
                var originalValue = input.data('original-value');
                
                // Only update if value is different from current page-load value
                if (value != input.val()) {
                    // Set new value
                    input.val(value);
                    // Trigger input event to update UI (show revert button, etc.)
                    input.trigger('input');
                    updated++;
                    console.log('Updated field:', field); // Debug
                }
            }
        });
        
        console.log('Total updated:', updated); // Debug
        if (updated > 0) {
            showStatus('<span class="dashicons dashicons-yes-alt"></span> 已加载来自 ' + source + ' 的数据（更新了 ' + updated + ' 个字段）', 'success');
        } else {
            showStatus('<span class="dashicons dashicons-info"></span> 数据已是最新，无需更新', 'success');
        }
    }
    
    // Revert button handler
    $('.revert-btn').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        var input = btn.prev('input, textarea');
        var originalValue = input.data('original-value');
        
        if (originalValue !== undefined) {
            input.val(originalValue);
            input.trigger('input'); // Trigger input event to hide button and remove class
        }
    });
});
