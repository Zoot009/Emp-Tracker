/**
 * Frontend JavaScript functionality - FIXED VERSION
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
            this.validateFormOnLoad();
        },
        
        /**
         * Initialize real-time clock
         */
        initClock: function() {
            if ($('#current-datetime').length > 0) {
                this.updateDateTime();
                this.clockTimer = setInterval(this.updateDateTime, 1000);
            }
        },
        
        /**
         * Update date and time display
         */
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
            
            try {
                var formattedTime = now.toLocaleString('en-IN', options);
                $('#current-datetime').text(formattedTime);
            } catch (e) {
                // Fallback for older browsers
                $('#current-datetime').text(now.toLocaleString());
            }
        },
        
        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            // Login form
            $('#ett-login-form').on('submit', this.handleLogin.bind(this));
            
            // Logout button
            $('#ett-logout-btn').on('click', this.handleLogout.bind(this));
            
            // Work log form
            $('#ett-work-log-form').on('submit', this.handleWorkLogSubmit.bind(this));
            
            // Count input changes
            $(document).on('input', '.ett-count-input', this.calculateTotals.bind(this));
            
            // Date selection
            $('#date-selection-form').on('submit', this.handleDateChange.bind(this));
            
            // Break controls
            $('#break-in-btn').on('click', this.handleBreakIn.bind(this));
            $('#break-out-btn').on('click', this.handleBreakOut.bind(this));
            
            // Issue form
            $('#ett-issue-form').on('submit', this.handleIssueSubmit.bind(this));
            
            // Warning dismissal
            $('.dismiss-warning-btn').on('click', this.dismissWarning.bind(this));
        },
        
        /**
         * Validate forms on page load
         */
        validateFormOnLoad: function() {
            // Check if jQuery and required functions are available
            if (typeof $ === 'undefined') {
                console.error('ETT: jQuery is not loaded');
                return;
            }
            
            // Validate AJAX URL
            if (typeof ettFrontend === 'undefined' || !ettFrontend.ajaxurl) {
                console.error('ETT: AJAX configuration missing');
                return;
            }
            
            console.log('ETT Frontend initialized successfully');
        },
        
        /**
         * Initialize work log functionality
         */
        initWorkLog: function() {
            if ($('.ett-count-input').length > 0) {
                this.calculateTotals();
                this.loadExistingData();
            }
        },
        
        /**
         * Initialize break timer
         */
        initBreakTimer: function() {
            if ($('#break-duration').length > 0) {
                this.updateBreakDuration();
                this.breakTimer = setInterval(this.updateBreakDuration.bind(this), 1000);
            }
        },
        
        /**
         * Handle employee login
         */
        handleLogin: function(e) {
            e.preventDefault();
            
            var employeeCode = $('#employee_code').val().trim();
            
            if (!employeeCode) {
                this.showMessage('Please enter employee code', 'error', '#ett-login-message');
                return;
            }
            
            var $submitBtn = $('#ett-login-form button');
            var originalText = $submitBtn.text();
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_employee_login',
                    employee_code: employeeCode,
                    nonce: ettFrontend.nonces.login
                },
                beforeSend: function() {
                    $submitBtn.prop('disabled', true).text('Logging in...');
                },
                success: function(response) {
                    if (response.success) {
                        ETTFrontend.showMessage('Login successful! Redirecting...', 'success', '#ett-login-message');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        ETTFrontend.showMessage(response.data || 'Login failed', 'error', '#ett-login-message');
                    }
                },
                error: function(xhr, status, error) {
                    ETTFrontend.showMessage('Network error: ' + error, 'error', '#ett-login-message');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Handle employee logout
         */
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
                    } else {
                        alert('Logout failed: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Network error during logout');
                }
            });
        },
        
        /**
         * Handle work log submission
         */
        handleWorkLogSubmit: function(e) {
            e.preventDefault();
            
            if (!confirm('Once submitted, this data will be locked and cannot be edited. Continue?')) {
                return;
            }
            
            var logs = [];
            var missingMandatory = false;
            var hasAnyData = false;
            
            $('.ett-count-input').each(function() {
                var count = parseInt($(this).val()) || 0;
                var tagId = $(this).data('tag-id');
                var isMandatory = $(this).data('mandatory') == '1';
                
                if (count > 0) {
                    hasAnyData = true;
                }
                
                if (isMandatory && count === 0) {
                    missingMandatory = true;
                }
                
                logs.push({
                    tag_id: tagId,
                    count: count
                });
            });
            
            // Check if any data is entered
            if (!hasAnyData) {
                alert('Please enter data for at least one tag before submitting.');
                return;
            }
            
            if (missingMandatory) {
                if (!confirm('Warning: You have missed some mandatory tags. This will create a warning. Continue?')) {
                    return;
                }
            }
            
            var employeeId = $('#employee_id').val();
            var logDate = $('#selected_log_date').val();
            
            if (!employeeId || !logDate) {
                alert('Missing employee or date information');
                return;
            }
            
            var $submitBtn = $('#ett-work-log-form button');
            var originalText = $submitBtn.text();
            
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
                    $submitBtn.prop('disabled', true).text('Submitting...');
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
                error: function(xhr, status, error) {
                    ETTFrontend.showMessage('❌ Network error: ' + error, 'error', '#ett-message');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Calculate totals dynamically
         */
        calculateTotals: function() {
            var grandTotal = 0;
            
            $('.ett-count-input').each(function() {
                var count = parseInt($(this).val()) || 0;
                var time = parseInt($(this).data('time')) || 0;
                var total = count * time;
                var tagId = $(this).data('tag-id');
                
                // Update individual total
                $('.ett-total-time[data-tag-id="' + tagId + '"]').text(total + ' min');
                grandTotal += total;
                
                // Visual feedback for mandatory fields
                var isMandatory = $(this).data('mandatory') == '1';
                if (isMandatory) {
                    if (count === 0) {
                        $(this).addClass('missing-mandatory');
                    } else {
                        $(this).removeClass('missing-mandatory');
                    }
                }
            });
            
            // Update grand total
            var hours = Math.floor(grandTotal / 60);
            var minutes = grandTotal % 60;
            $('#ett-grand-total').text(hours + ' hours ' + minutes + ' minutes');
            
            // Color coding for total time
            var $grandTotal = $('#ett-grand-total');
            if (grandTotal >= 480) { // 8 hours
                $grandTotal.css('color', '#28a745'); // Green
            } else if (grandTotal >= 360) { // 6 hours
                $grandTotal.css('color', '#ffc107'); // Yellow
            } else {
                $grandTotal.css('color', '#dc3545'); // Red
            }
        },
        
        /**
         * Load existing data for selected date
         */
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
                },
                error: function() {
                    console.log('ETT: Failed to load existing data');
                }
            });
        },
        
        /**
         * Handle date change
         */
        handleDateChange: function(e) {
            var $btn = $(this).find('button');
            $btn.prop('disabled', true).text('Loading...');
            // Form will submit naturally
        },
        
        /**
         * Handle break in
         */
        handleBreakIn: function(e) {
            e.preventDefault();
            
            var employeeId = $('#employee_id').val();
            
            if (!employeeId) {
                alert('Employee ID not found');
                return;
            }
            
            var $btn = $('#break-in-btn');
            var originalText = $btn.text();
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_break_in',
                    employee_id: employeeId,
                    nonce: ettFrontend.nonces.break
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Starting...');
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to start break: ' + response.data);
                    }
                },
                error: function() {
                    alert('Network error occurred');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Handle break out
         */
        handleBreakOut: function(e) {
            e.preventDefault();
            
            var employeeId = $('#employee_id').val();
            
            if (!employeeId) {
                alert('Employee ID not found');
                return;
            }
            
            var $btn = $('#break-out-btn');
            var originalText = $btn.text();
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_break_out',
                    employee_id: employeeId,
                    nonce: ettFrontend.nonces.break
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Ending...');
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to end break: ' + response.data);
                    }
                },
                error: function() {
                    alert('Network error occurred');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Update break duration (for active breaks)
         */
        updateBreakDuration: function() {
            // This would be implemented with server-side break start time
            // For now, this is a placeholder
        },
        
        /**
         * Handle issue submission
         */
        handleIssueSubmit: function(e) {
            e.preventDefault();
            
            var category = $('#issue-category').val();
            var description = $('#issue-description').val().trim();
            var employeeId = $('#employee_id').val();
            
            if (!category || !description) {
                this.showMessage('Please fill all fields', 'error');
                return;
            }
            
            if (description.length < 10) {
                this.showMessage('Please provide a more detailed description (at least 10 characters)', 'error');
                return;
            }
            
            var $submitBtn = $('#ett-issue-form button');
            var originalText = $submitBtn.text();
            
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
                    $submitBtn.prop('disabled', true).text('Submitting...');
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
                        ETTFrontend.showMessage('Failed to submit issue: ' + response.data, 'error');
                    }
                },
                error: function() {
                    ETTFrontend.showMessage('Network error occurred', 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Dismiss warning
         */
        dismissWarning: function(e) {
            e.preventDefault();
            
            var warningId = $(this).data('warning-id');
            
            if (!warningId) {
                alert('Warning ID not found');
                return;
            }
            
            if (!confirm('Are you sure you want to dismiss this warning?')) {
                return;
            }
            
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
                    } else {
                        alert('Failed to dismiss warning: ' + response.data);
                    }
                },
                error: function() {
                    alert('Network error occurred');
                }
            });
        },
        
        /**
         * Show message to user
         */
        showMessage: function(message, type, target) {
            target = target || '#ett-message';
            var className = type === 'success' ? 'ett-success' : 'ett-error';
            $(target).html('<p class="' + className + '">' + message + '</p>');
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $(target).html('');
            }, 5000);
        },
        
        /**
         * Cleanup function
         */
        destroy: function() {
            if (this.clockTimer) {
                clearInterval(this.clockTimer);
                this.clockTimer = null;
            }
            
            if (this.breakTimer) {
                clearInterval(this.breakTimer);
                this.breakTimer = null;
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        ETTFrontend.init();
    });
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        ETTFrontend.destroy();
    });
    
    // Make available globally for debugging
    window.ETTFrontend = ETTFrontend;
    
})(jQuery);