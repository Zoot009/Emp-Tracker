/**
 * Updated Admin JavaScript with Delete Functionality
 * File: assets/js/admin.js
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
            // Delete functions
            $('.delete-tag-btn').on('click', this.deleteTag);
            $('.delete-employee-btn').on('click', this.deleteEmployee);
            $('.delete-assignment-btn').on('click', this.deleteAssignment);
            
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
        
        deleteTag: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var tagId = $btn.data('tag-id');
            var tagName = $btn.data('tag-name');
            
            if (!confirm('Are you sure you want to delete the tag "' + tagName + '"? This action cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_delete_tag',
                    tag_id: tagId,
                    nonce: $('#ett_delete_tag_nonce').val()
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Deleting...');
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                        ETTAdmin.showNotice('Tag deleted successfully', 'success');
                    } else {
                        ETTAdmin.showNotice('Failed to delete tag: ' + response.data, 'error');
                        $btn.prop('disabled', false).text('Delete');
                    }
                },
                error: function() {
                    ETTAdmin.showNotice('Network error occurred', 'error');
                    $btn.prop('disabled', false).text('Delete');
                }
            });
        },
        
        deleteEmployee: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var employeeId = $btn.data('employee-id');
            var employeeName = $btn.data('employee-name');
            
            if (!confirm('Are you sure you want to delete "' + employeeName + '"? This will also delete all their logs and assignments.')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_delete_employee',
                    employee_id: employeeId,
                    nonce: $('#ett_delete_employee_nonce').val()
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Deleting...');
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                        ETTAdmin.showNotice('Employee deleted successfully', 'success');
                    } else {
                        ETTAdmin.showNotice('Failed to delete employee: ' + response.data, 'error');
                        $btn.prop('disabled', false).text('Delete');
                    }
                }
            });
        },
        
        deleteAssignment: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var assignmentId = $btn.data('assignment-id');
            
            if (!confirm('Are you sure you want to delete this assignment?')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_delete_assignment',
                    assignment_id: assignmentId,
                    nonce: $('#ett_delete_assignment_nonce').val()
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Deleting...');
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                        ETTAdmin.showNotice('Assignment deleted successfully', 'success');
                    } else {
                        ETTAdmin.showNotice('Failed to delete assignment: ' + response.data, 'error');
                        $btn.prop('disabled', false).text('Delete');
                    }
                }
            });
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
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_dismiss_warning',
                    warning_id: warningId,
                    nonce: $('#ett_dismiss_warning_nonce').val()
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
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_send_missing_data_warning',
                    employee_id: employeeId,
                    missing_dates: missingDates,
                    nonce: $('#ett_send_warning_nonce').val()
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Sending...');
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Warning Sent').addClass('button-primary');
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
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_send_break_warning',
                    employee_id: employeeId,
                    break_id: breakId,
                    nonce: $('#ett_send_break_warning_nonce').val()
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Sending...');
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Warning Sent').css('color', '#28a745');
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
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_update_issue_status',
                    issue_id: issueId,
                    status: status,
                    nonce: $('#ett_update_issue_nonce').val()
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
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_update_issue_status',
                    issue_id: issueId,
                    admin_response: response,
                    nonce: $('#ett_update_issue_nonce').val()
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
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_update_log',
                    log_id: logId,
                    count: count,
                    nonce: $('#ett_update_log_nonce').val()
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