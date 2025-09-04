/**
 * Admin JavaScript functionality
 */
(function($) {
    'use strict';
    
    var ETTAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initModals();
            this.initFilters();
        },
        
        bindEvents: function() {
            // Warning dismissal
            $('.ett-dismiss-warning').on('click', this.dismissWarning);
            
            // Send warnings
            $('.send-warning-btn').on('click', this.sendWarning);
            
            // Break warnings
            $('.ett-send-break-warning').on('click', this.sendBreakWarning);
            
            // Issue status updates
            $('.issue-status').on('change', this.updateIssueStatus);
            
            // View issue details
            $('.view-issue').on('click', this.viewIssue);
            
            // Log updates
            $('.update-log-btn').on('click', this.updateLog);
            
            // Form submissions
            $('.ett-admin-form').on('submit', this.handleFormSubmit);
        },
        
        initModals: function() {
            // Close modals
            $('.ett-close').on('click', function() {
                $('.ett-modal').hide();
            });
            
            // Close modal on outside click
            $('.ett-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });
        },
        
        initFilters: function() {
            // Auto-submit filter forms on change
            $('.ett-filter-form select').on('change', function() {
                $(this).closest('form').submit();
            });
        },
        
        dismissWarning: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var warningId = $btn.data('warning-id');
            
            $.ajax({
                url: ettAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_dismiss_warning',
                    warning_id: warningId,
                    nonce: ettAdmin.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Dismissing...');
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('tr').fadeOut();
                        ETTAdmin.showNotice('Warning dismissed successfully', 'success');
                    } else {
                        ETTAdmin.showNotice('Failed to dismiss warning', 'error');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Dismiss');
                }
            });
        },
        
        sendWarning: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var employeeId = $btn.data('employee-id');
            var missingDates = $btn.data('missing-dates');
            
            if (!confirm('Send warning for missing data?')) {
                return;
            }
            
            $.ajax({
                url: ettAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_send_missing_data_warning',
                    employee_id: employeeId,
                    missing_dates: missingDates,
                    nonce: ettAdmin.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Sending...');
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Warning Sent').addClass('ett-btn-success');
                        ETTAdmin.showNotice('Warning sent successfully', 'success');
                    } else {
                        ETTAdmin.showNotice('Failed to send warning', 'error');
                        $btn.prop('disabled', false).text('Send Warning');
                    }
                }
            });
        },
        
        sendBreakWarning: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var employeeId = $btn.data('employee-id');
            var breakId = $btn.data('break-id');
            
            $.ajax({
                url: ettAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_send_break_warning',
                    employee_id: employeeId,
                    break_id: breakId,
                    nonce: ettAdmin.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Sending...');
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Warning Sent').addClass('ett-btn-success');
                        ETTAdmin.showNotice('Break warning sent successfully', 'success');
                    } else {
                        ETTAdmin.showNotice('Failed to send warning', 'error');
                        $btn.prop('disabled', false).text('Send Warning');
                    }
                }
            });
        },
        
        updateIssueStatus: function() {
            var $select = $(this);
            var issueId = $select.data('issue-id');
            var status = $select.val();
            
            $.ajax({
                url: ettAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_update_issue_status',
                    issue_id: issueId,
                    status: status,
                    nonce: ettAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        ETTAdmin.showNotice('Issue status updated successfully', 'success');
                    } else {
                        ETTAdmin.showNotice('Failed to update issue status', 'error');
                    }
                }
            });
        },
        
        viewIssue: function(e) {
            e.preventDefault();
            var issue = $(this).data('issue');
            
            var html = '<div class="ett-issue-details">';
            html += '<p><strong>Employee:</strong> ' + issue.employee_name + '</p>';
            html += '<p><strong>Category:</strong> ' + issue.issue_category + '</p>';
            html += '<p><strong>Description:</strong><br>' + issue.issue_description + '</p>';
            html += '<p><strong>Status:</strong> ' + issue.issue_status + '</p>';
            html += '<p><strong>Raised Date:</strong> ' + issue.raised_date + '</p>';
            
            if (issue.admin_response) {
                html += '<p><strong>Admin Response:</strong><br>' + issue.admin_response + '</p>';
            }
            
            html += '<div class="issue-response">';
            html += '<h3>Admin Response</h3>';
            html += '<textarea id="admin-response" rows="4" style="width:100%;">' + (issue.admin_response || '') + '</textarea>';
            html += '<br><br><button class="button button-primary" id="save-response" data-issue-id="' + issue.id + '">Save Response</button>';
            html += '</div>';
            html += '</div>';
            
            $('#issue-details').html(html);
            $('#issue-modal').show();
            
            // Bind save response
            $('#save-response').on('click', this.saveIssueResponse);
        },
        
        saveIssueResponse: function() {
            var issueId = $(this).data('issue-id');
            var response = $('#admin-response').val();
            
            $.ajax({
                url: ettAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_update_issue_status',
                    issue_id: issueId,
                    admin_response: response,
                    nonce: ettAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        ETTAdmin.showNotice('Response saved successfully', 'success');
                        $('#issue-modal').hide();
                    } else {
                        ETTAdmin.showNotice('Failed to save response', 'error');
                    }
                }
            });
        },
        
        updateLog: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var logId = $btn.data('log-id');
            var timePerUnit = $btn.data('time');
            var count = $('#count-' + logId).val();
            var totalMinutes = count * timePerUnit;
            
            $.ajax({
                url: ettAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_update_log',
                    log_id: logId,
                    count: count,
                    nonce: ettAdmin.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Updating...');
                },
                success: function(response) {
                    if (response.success) {
                        $('#total-' + logId).text(totalMinutes + ' min');
                        $btn.text('Updated!').css('background', 'green').css('color', 'white');
                        setTimeout(function() {
                            $btn.text('Update').css('background', '').css('color', '');
                        }, 2000);
                    } else {
                        ETTAdmin.showNotice('Failed to update log', 'error');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },
        
        handleFormSubmit: function(e) {
            var $form = $(this);
            var $submitBtn = $form.find('input[type="submit"], button[type="submit"]');
            
            // Add loading state
            $submitBtn.prop('disabled', true);
            if ($submitBtn.is('input')) {
                $submitBtn.data('original-value', $submitBtn.val()).val('Processing...');
            } else {
                $submitBtn.data('original-text', $submitBtn.text()).text('Processing...');
            }
            
            // Re-enable after 3 seconds (fallback)
            setTimeout(function() {
                $submitBtn.prop('disabled', false);
                if ($submitBtn.is('input')) {
                    $submitBtn.val($submitBtn.data('original-value'));
                } else {
                    $submitBtn.text($submitBtn.data('original-text'));
                }
            }, 3000);
        },
        
        showNotice: function(message, type) {
            var noticeClass = 'notice-' + type;
            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        ETTAdmin.init();
    });
    
})(jQuery);