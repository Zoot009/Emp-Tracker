/**
 * AJAX interaction handlers
 */
(function($) {
    'use strict';
    
    var ETTAjax = {
        
        // Common AJAX wrapper with error handling
        request: function(action, data, callbacks) {
            var defaultCallbacks = {
                beforeSend: function() {},
                success: function() {},
                error: function() {},
                complete: function() {}
            };
            
            callbacks = $.extend(defaultCallbacks, callbacks);
            
            var ajaxData = $.extend({
                action: action,
                nonce: this.getNonce(action)
            }, data);
            
            $.ajax({
                url: ettFrontend.ajaxurl,
                type: 'POST',
                data: ajaxData,
                beforeSend: callbacks.beforeSend,
                success: function(response) {
                    if (response.success) {
                        callbacks.success(response.data);
                    } else {
                        callbacks.error(response.data || 'Unknown error occurred');
                    }
                },
                error: function(xhr, status, error) {
                    callbacks.error('Network error: ' + error);
                },
                complete: callbacks.complete
            });
        },
        
        // Get appropriate nonce for action
        getNonce: function(action) {
            var nonceMap = {
                'ett_employee_login': 'login',
                'ett_employee_logout': 'logout',
                'ett_save_log': 'save_log',
                'ett_break_in': 'break',
                'ett_break_out': 'break',
                'ett_raise_issue': 'raise_issue',
                'ett_get_logs_by_date': 'get_logs',
                'ett_dismiss_warning': 'dismiss_warning'
            };
            
            var nonceKey = nonceMap[action];
            return nonceKey ? ettFrontend.nonces[nonceKey] : '';
        },
        
        // Specific AJAX methods
        employeeLogin: function(employeeCode, callbacks) {
            this.request('ett_employee_login', {
                employee_code: employeeCode
            }, callbacks);
        },
        
        employeeLogout: function(callbacks) {
            this.request('ett_employee_logout', {}, callbacks);
        },
        
        saveWorkLog: function(employeeId, logs, logDate, callbacks) {
            this.request('ett_save_log', {
                employee_id: employeeId,
                logs: logs,
                log_date: logDate
            }, callbacks);
        },
        
        breakIn: function(employeeId, callbacks) {
            this.request('ett_break_in', {
                employee_id: employeeId
            }, callbacks);
        },
        
        breakOut: function(employeeId, callbacks) {
            this.request('ett_break_out', {
                employee_id: employeeId
            }, callbacks);
        },
        
        raiseIssue: function(employeeId, category, description, callbacks) {
            this.request('ett_raise_issue', {
                employee_id: employeeId,
                category: category,
                description: description
            }, callbacks);
        },
        
        getLogsByDate: function(employeeId, logDate, callbacks) {
            this.request('ett_get_logs_by_date', {
                employee_id: employeeId,
                log_date: logDate
            }, callbacks);
        },
        
        dismissWarning: function(warningId, callbacks) {
            this.request('ett_dismiss_warning', {
                warning_id: warningId
            }, callbacks);
        }
    };
    
    // Make available globally
    window.ETTAjax = ETTAjax;
    
})(jQuery);