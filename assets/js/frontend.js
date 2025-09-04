/**
 * Frontend JavaScript functionality
 */
(function($) {
    'use strict';
    
    var ETTFrontend = {
        
        currentEmployeeId: 0,
        breakTimer: null,
        clockTimer: null,
        
        init: function() {
            this.initClock();
            this.bindEvents();
            this.initWorkLog();
            this.initBreakTimer();
        },
        
        initClock: function() {
            this.updateDateTime();
            this.clockTimer = setInterval(this.updateDateTime, 1000);
        },
        
        updateDateTime: function() {
            var now = new Date();
            var options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                timeZone: 'Asia/Kolkata',
                timeZoneName: 'short'
            };
            $('#current-datetime').text(now.toLocaleString('en-IN', options));
        },
        
        bindEvents: function() {
            // Login form
            $('#ett-login-form').on('submit', this.handleLogin);
            
            // Logout button
            $('#ett-logout-btn').on('click', this.handleLogout);
            
            // Work log form
            $('#ett-work-log-form').on('submit', this.handleWorkLogSubmit);
            
            // Count input changes
            $('.ett-count-input').on('input', this.calculateTotals);
            
            // Date selection
            $('#date-selection-form').on('submit', this.handleDateChange);
            
            // Break controls
            $('#break-in-btn').on('click', this.handleBreakIn);
            $('#break-out-btn').on('click', this.handleBreakOut);
            
            // Issue form
            $('#ett-issue-form').on('submit', this.handleIssueSubmit);
            
            // Warning dismissal
            $('.dismiss-warning-btn').on('click', this.dismissWarning);
        },
        
        initWorkLog: function() {
            this.calculateTotals();
            this.loadExistingData();
        },
        
        initBreakTimer: function() {
            if ($('#break-duration').length > 0) {
                this.updateBreakDuration();
                this.breakTimer = setInterval(this.updateBreakDuration, 1000);
            }
        },
        
        handleLogin: function(e) {
            e.preventDefault();
            var employeeCode = $('#employee_code').val();
            
            if (!employeeCode) {
                ETTFrontend.showMessage('Please enter employee code', 'error');
                return;
            }
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_employee_login',
                    employee_code: employeeCode,
                    nonce: ettFrontend.nonces.login
                },
                beforeSend: function() {
                    $('#ett-login-form button').prop('disabled', true).text('Logging in...');
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        ETTFrontend.showMessage(response.data, 'error', '#ett-login-message');
                    }
                },
                complete: function() {
                    $('#ett-login-form button').prop('disabled', false).text('Login');
                }
            });
        },
        
        handleLogout: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to logout?')) {
                return;
            }
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_employee_logout',
                    nonce: ettFrontend.nonces.logout
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        },
        
        handleWorkLogSubmit: function(e) {
            e.preventDefault();
            
            if (!confirm('Once submitted, this data will be locked. Continue?')) {
                return;
            }
            
            var logs = [];
            var missingMandatory = false;
            
            $('.ett-count-input').each(function() {
                var count = parseInt($(this).val()) || 0;
                var tagId = $(this).data('tag-id');
                var isMandatory = $(this).data('mandatory') == '1';
                
                if (isMandatory && count === 0) {
                    missingMandatory = true;
                }
                
                logs.push({
                    tag_id: tagId,
                    count: count
                });
            });
            
            if (missingMandatory) {
                if (!confirm('Warning: You have missed some mandatory tags. Continue?')) {
                    return;
                }
            }
            
            var employeeId = $('#employee_id').val();
            var logDate = $('#selected_log_date').val();
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_save_log',
                    employee_id: employeeId,
                    logs: logs,
                    log_date: logDate,
                    nonce: ettFrontend.nonces.save_log
                },
                beforeSend: function() {
                    $('#ett-work-log-form button').prop('disabled', true).text('Submitting...');
                },
                success: function(response) {
                    if (response.success) {
                        ETTFrontend.showMessage('✅ ' + response.data, 'success', '#ett-message');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        ETTFrontend.showMessage('❌ ' + response.data, 'error', '#ett-message');
                    }
                },
                complete: function() {
                    $('#ett-work-log-form button').prop('disabled', false).text('Submit & Lock Work Log');
                }
            });
        },
        
        calculateTotals: function() {
            var grandTotal = 0;
            
            $('.ett-count-input').each(function() {
                var count = parseInt($(this).val()) || 0;
                var time = parseInt($(this).data('time')) || 0;
                var total = count * time;
                var tagId = $(this).data('tag-id');
                
                $('.ett-total-time[data-tag-id="' + tagId + '"]').text(total + ' min');
                grandTotal += total;
            });
            
            var hours = Math.floor(grandTotal / 60);
            var minutes = grandTotal % 60;
            $('#ett-grand-total').text(hours + ' hours ' + minutes + ' minutes');
        },
        
        loadExistingData: function() {
            var employeeId = $('#employee_id').val();
            var logDate = $('#selected_log_date').val();
            
            if (!employeeId || !logDate) return;
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_get_logs_by_date',
                    employee_id: employeeId,
                    log_date: logDate,
                    nonce: ettFrontend.nonces.get_logs
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $.each(response.data, function(tag_id, count) {
                            $('.ett-count-input[data-tag-id="' + tag_id + '"]').val(count);
                        });
                        ETTFrontend.calculateTotals();
                    }
                }
            });
        },
        
        handleDateChange: function(e) {
            // Form will submit naturally, just add loading state
            $(this).find('button').prop('disabled', true).text('Loading...');
        },
        
        handleBreakIn: function(e) {
            e.preventDefault();
            var employeeId = $('#employee_id').val();
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_break_in',
                    employee_id: employeeId,
                    nonce: ettFrontend.nonces.break
                },
                beforeSend: function() {
                    $('#break-in-btn').prop('disabled', true).text('Starting...');
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        ETTFrontend.showMessage(response.data, 'error');
                    }
                },
                complete: function() {
                    $('#break-in-btn').prop('disabled', false).text('Break In');
                }
            });
        },
        
        handleBreakOut: function(e) {
            e.preventDefault();
            var employeeId = $('#employee_id').val();
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_break_out',
                    employee_id: employeeId,
                    nonce: ettFrontend.nonces.break
                },
                beforeSend: function() {
                    $('#break-out-btn').prop('disabled', true).text('Ending...');
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        ETTFrontend.showMessage(response.data, 'error');
                    }
                },
                complete: function() {
                    $('#break-out-btn').prop('disabled', false).text('Break Out');
                }
            });
        },
        
        updateBreakDuration: function() {
            // This would be implemented with actual break start time
            // Placeholder for break duration calculation
        },
        
        handleIssueSubmit: function(e) {
            e.preventDefault();
            
            var category = $('#issue-category').val();
            var description = $('#issue-description').val();
            var employeeId = $('#employee_id').val();
            
            if (!category || !description) {
                ETTFrontend.showMessage('Please fill all fields', 'error');
                return;
            }
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_raise_issue',
                    employee_id: employeeId,
                    category: category,
                    description: description,
                    nonce: ettFrontend.nonces.raise_issue
                },
                beforeSend: function() {
                    $('#ett-issue-form button').prop('disabled', true).text('Submitting...');
                },
                success: function(response) {
                    if (response.success) {
                        ETTFrontend.showMessage('Issue submitted successfully', 'success');
                        $('#issue-category').val('');
                        $('#issue-description').val('');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        ETTFrontend.showMessage('Failed to submit issue', 'error');
                    }
                },
                complete: function() {
                    $('#ett-issue-form button').prop('disabled', false).text('Submit Issue');
                }
            });
        },
        
        dismissWarning: function(e) {
            e.preventDefault();
            var warningId = $(this).data('warning-id');
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_dismiss_warning',
                    warning_id: warningId,
                    nonce: ettFrontend.nonces.dismiss_warning
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        },
        
        showMessage: function(message, type, target) {
            target = target || '#ett-message';
            var className = type === 'success' ? 'ett-success' : 'ett-error';
            $(target).html('<p class="' + className + '">' + message + '</p>');
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $(target).html('');
            }, 5000);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        ETTFrontend.init();
    });
    
})(jQuery);