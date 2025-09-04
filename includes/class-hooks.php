<?php
/**
 * WordPress hooks and shortcodes handler - FIXED VERSION
 */

class ETT_Hooks {
    
    private $database;
    private $security;
    
    public function __construct($database, $security) {
        $this->database = $database;
        $this->security = $security;
    }
    
    /**
     * Initialize hooks
     */
    public function init() {
        $this->security->start_session();
        
        // Load text domain for translations
        load_plugin_textdomain('employee-tag-tracker', false, dirname(plugin_basename(ETT_PLUGIN_FILE)) . '/languages');
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'ett-frontend-styles', 
            ETT_PLUGIN_URL . 'assets/css/frontend.css', 
            array(), 
            ETT_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'ett-frontend-scripts',
            ETT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            ETT_PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_script(
            'chart-js', 
            'https://cdn.jsdelivr.net/npm/chart.js', 
            array(), 
            '3.9.1', 
            true
        );
        
        wp_localize_script('ett-frontend-scripts', 'ettFrontend', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'login' => wp_create_nonce('ett_employee_login'),
                'logout' => wp_create_nonce('ett_employee_logout'),
                'save_log' => wp_create_nonce('ett_save_log'),
                'break' => wp_create_nonce('ett_break'),
                'raise_issue' => wp_create_nonce('ett_raise_issue'),
                'get_logs' => wp_create_nonce('ett_get_logs_by_date')
            )
        ));
    }
    
    /**
     * Employee panel shortcode
     */
    public function employee_panel_shortcode() {
        ob_start();
        ?>
        <div class="ett-panel-container">
            <?php if ($this->security->is_employee_logged_in()): ?>
                <?php $this->display_employee_panel(); ?>
            <?php else: ?>
                <?php $this->display_login_form(); ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Display login form
     */
    private function display_login_form() {
        ?>
        <div class="ett-login-container">
            <h2>Employee Login</h2>
            <form id="ett-login-form">
                <div class="ett-form-group">
                    <label for="employee_code">Employee Code:</label>
                    <input type="text" id="employee_code" name="employee_code" required>
                </div>
                <button type="submit" class="ett-button ett-button-primary">Login</button>
                <div id="ett-login-message"></div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#ett-login-form').on('submit', function(e) {
                e.preventDefault();
                var employee_code = $('#employee_code').val();
                
                if (!employee_code) {
                    $('#ett-login-message').html('<p style="color:red;">Please enter employee code</p>');
                    return;
                }
                
                $.ajax({
                    url: ettFrontend.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ett_employee_login',
                        employee_code: employee_code,
                        nonce: ettFrontend.nonces.login
                    },
                    beforeSend: function() {
                        $('#ett-login-form button').prop('disabled', true).text('Logging in...');
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#ett-login-message').html('<p style="color:green;">Login successful! Redirecting...</p>');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            $('#ett-login-message').html('<p style="color:red;">' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        $('#ett-login-message').html('<p style="color:red;">Network error. Please try again.</p>');
                    },
                    complete: function() {
                        $('#ett-login-form button').prop('disabled', false).text('Login');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Display employee panel
     */
    private function display_employee_panel() {
        $employee_id = $this->security->get_logged_in_employee_id();
        
        global $wpdb;
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ett_employees WHERE id = %d",
            $employee_id
        ));
        
        if (!$employee) {
            echo '<p>Invalid session. Please login again.</p>';
            return;
        }
        
        $selected_date = isset($_POST['log_date']) ? sanitize_text_field($_POST['log_date']) : date('Y-m-d');
        $assigned_tags = $this->database->get_employee_assignments($employee_id);
        
        // Check if data already submitted for selected date
        $selected_submission = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ett_submission_status 
            WHERE employee_id = %d AND submission_date = %s AND is_locked = 1
        ", $employee_id, $selected_date));
        ?>
        <div class="ett-employee-panel">
            <div class="ett-panel-header">
                <h2>Welcome, <?php echo esc_html($employee->name); ?>!</h2>
                <button id="ett-logout-btn" class="ett-logout-btn">Logout</button>
            </div>
            
            <div class="ett-current-datetime">
                <p><strong>Current Date & Time:</strong> <span id="current-datetime"></span></p>
            </div>
            
            <div class="ett-date-selection">
                <h3>Select Date for Work Log</h3>
                <form method="post" id="date-selection-form">
                    <input type="date" name="log_date" id="log_date" value="<?php echo esc_attr($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>" />
                    <button type="submit" class="ett-button">Load Data</button>
                </form>
            </div>
            
            <?php if ($selected_submission): ?>
                <div class="ett-locked-notice">
                    <h3>🔒 Data Already Submitted for <?php echo date('F j, Y', strtotime($selected_date)); ?></h3>
                    <p>This data has been submitted and cannot be edited.</p>
                    <p>If you need corrections, please contact your supervisor.</p>
                </div>
            <?php else: ?>
                <div id="ett-work-form-container">
                    <h3>Log Your Work - <?php echo date('F j, Y', strtotime($selected_date)); ?></h3>
                    
                    <form id="ett-work-log-form">
                        <input type="hidden" id="employee_id" value="<?php echo esc_attr($employee_id); ?>" />
                        <input type="hidden" id="selected_log_date" value="<?php echo esc_attr($selected_date); ?>" />
                        
                        <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                            <thead>
                                <tr style="background:#f5f5f5;">
                                    <th style="border:1px solid #ddd;padding:12px;text-align:left;">Tag</th>
                                    <th style="border:1px solid #ddd;padding:12px;text-align:left;">Type</th>
                                    <th style="border:1px solid #ddd;padding:12px;text-align:left;">Time/Unit</th>
                                    <th style="border:1px solid #ddd;padding:12px;text-align:left;">Count</th>
                                    <th style="border:1px solid #ddd;padding:12px;text-align:left;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($assigned_tags)): ?>
                                    <?php foreach ($assigned_tags as $tag): ?>
                                    <tr>
                                        <td style="border:1px solid #ddd;padding:12px;">
                                            <strong><?php echo esc_html($tag->tag_name); ?></strong>
                                        </td>
                                        <td style="border:1px solid #ddd;padding:12px;">
                                            <?php if ($tag->is_mandatory): ?>
                                                <span style="color:#dc3545;font-weight:bold;">⚠️ Mandatory</span>
                                            <?php else: ?>
                                                <span style="color:#28a745;">Optional</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="border:1px solid #ddd;padding:12px;"><?php echo esc_html($tag->time_minutes); ?> min</td>
                                        <td style="border:1px solid #ddd;padding:12px;">
                                            <input type="number" 
                                                   class="ett-count-input" 
                                                   data-tag-id="<?php echo esc_attr($tag->tag_id); ?>"
                                                   data-time="<?php echo esc_attr($tag->time_minutes); ?>"
                                                   data-mandatory="<?php echo esc_attr($tag->is_mandatory); ?>"
                                                   value="0"
                                                   min="0"
                                                   style="width:80px;text-align:center;padding:5px;" />
                                        </td>
                                        <td style="border:1px solid #ddd;padding:12px;" class="ett-total-time" data-tag-id="<?php echo esc_attr($tag->tag_id); ?>">0 min</td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="border:1px solid #ddd;padding:12px;text-align:center;color:#666;">
                                            No tags assigned to you yet. Please contact your supervisor.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:#f8f9fa;">
                                    <td colspan="4" style="border:1px solid #ddd;padding:12px;"><strong>Total Time:</strong></td>
                                    <td style="border:1px solid #ddd;padding:12px;"><strong id="ett-grand-total">0 hours 0 minutes</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <?php if (!empty($assigned_tags)): ?>
                        <div style="margin-top:20px;">
                            <div style="background:#fff3cd;padding:15px;margin:10px 0;border-radius:4px;border:1px solid #ffeaa7;">
                                <strong>⚠️ Important:</strong> Once submitted, this data will be locked and cannot be edited.
                            </div>
                            <button type="submit" class="ett-button ett-button-primary" style="font-size:16px;padding:12px 24px;">
                                Submit & Lock Work Log
                            </button>
                            <div id="ett-message" style="margin-top:10px;"></div>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Update current datetime
            function updateDateTime() {
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
            }
            setInterval(updateDateTime, 1000);
            updateDateTime();
            
            // Calculate totals
            function calculateTotals() {
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
            }
            
            $('.ett-count-input').on('input', calculateTotals);
            
            // Load existing data for selected date
            function loadExistingData() {
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
                            calculateTotals();
                        }
                    }
                });
            }
            loadExistingData();
            
            // Submit work log
            $('#ett-work-log-form').on('submit', function(e) {
                e.preventDefault();
                
                if (!confirm('Once submitted, this data will be locked and cannot be edited. Continue?')) {
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
                    if (!confirm('Warning: You have missed some mandatory tags. This will result in a warning. Continue?')) {
                        return;
                    }
                }
                
                $.ajax({
                    url: ettFrontend.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ett_save_log',
                        employee_id: $('#employee_id').val(),
                        logs: logs,
                        log_date: $('#selected_log_date').val(),
                        nonce: ettFrontend.nonces.save_log
                    },
                    beforeSend: function() {
                        $('#ett-work-log-form button[type="submit"]').prop('disabled', true).text('Submitting...');
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#ett-message').html('<div style="background:#d4edda;color:#155724;padding:12px;border-radius:4px;border:1px solid #c3e6cb;">✅ ' + response.data + '</div>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $('#ett-message').html('<div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:4px;border:1px solid #f5c6cb;">❌ ' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        $('#ett-message').html('<div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:4px;border:1px solid #f5c6cb;">❌ Network error. Please try again.</div>');
                    },
                    complete: function() {
                        $('#ett-work-log-form button[type="submit"]').prop('disabled', false).text('Submit & Lock Work Log');
                    }
                });
            });
            
            // Logout
            $('#ett-logout-btn').click(function() {
                if(confirm('Are you sure you want to logout?')) {
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
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Warning chart shortcode
     */
    public function warning_chart_shortcode() {
        global $wpdb;
        
        $warnings = $wpdb->get_results("
            SELECT w.*, e.name as employee_name
            FROM {$wpdb->prefix}ett_warnings w
            LEFT JOIN {$wpdb->prefix}ett_employees e ON w.employee_id = e.id
            WHERE w.is_active = 1
            ORDER BY w.created_at DESC
            LIMIT 20
        ");
        
        ob_start();
        ?>
        <div class="ett-warning-chart">
            <h3>Active Warnings</h3>
            <?php if (!empty($warnings)): ?>
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f5f5f5;">
                            <th style="border:1px solid #ddd;padding:10px;text-align:left;">Employee</th>
                            <th style="border:1px solid #ddd;padding:10px;text-align:left;">Date</th>
                            <th style="border:1px solid #ddd;padding:10px;text-align:left;">Warning</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warnings as $warning): ?>
                        <tr>
                            <td style="border:1px solid #ddd;padding:10px;"><?php echo esc_html($warning->employee_name); ?></td>
                            <td style="border:1px solid #ddd;padding:10px;"><?php echo esc_html($warning->warning_date); ?></td>
                            <td style="border:1px solid #ddd;padding:10px;"><?php echo esc_html($warning->warning_message); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No active warnings at this time.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * All employee tags graph shortcode
     */
    public function all_employee_tags_graph_shortcode() {
        return '<div><h3>Employee Performance Chart</h3><p>Feature under development - Chart.js integration needed</p></div>';
    }
    
    /**
     * Break tracker shortcode
     */
    public function break_tracker_shortcode() {
        return '<div><h3>Break Tracker</h3><p>Feature under development</p></div>';
    }
    
    /**
     * Issue tracker shortcode
     */
    public function issue_tracker_shortcode() {
        return '<div><h3>Issue Tracker</h3><p>Feature under development</p></div>';
    }
}