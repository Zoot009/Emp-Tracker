<?php
/**
 * Complete Hooks Handler Class
 * File: includes/class-hooks.php
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
        // Start session for employee login
        $this->security->start_session();
        
        // Add scheduled tasks
        $this->schedule_tasks();
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
        
        wp_enqueue_style(
            'ett-components-styles', 
            ETT_PLUGIN_URL . 'assets/css/components.css', 
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
        
        // Enqueue Chart.js for graphs
        wp_enqueue_script(
            'chart-js', 
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', 
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
                'get_logs' => wp_create_nonce('ett_get_logs_by_date'),
                'break' => wp_create_nonce('ett_break'),
                'raise_issue' => wp_create_nonce('ett_raise_issue'),
                'dismiss_warning' => wp_create_nonce('ett_dismiss_warning')
            ),
            'strings' => array(
                'loading' => __('Loading...', 'employee-tag-tracker'),
                'error' => __('An error occurred', 'employee-tag-tracker'),
                'success' => __('Success', 'employee-tag-tracker'),
                'confirm' => __('Are you sure?', 'employee-tag-tracker')
            )
        ));
    }
    
    /**
     * Employee panel shortcode
     */
    public function employee_panel_shortcode($atts = array()) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'class' => '',
            'style' => 'default'
        ), $atts);
        
        ob_start();
        
        // Add wrapper class if provided
        if (!empty($atts['class'])) {
            echo '<div class="' . esc_attr($atts['class']) . '">';
        }
        
        include ETT_PLUGIN_PATH . 'templates/frontend/employee-panel.php';
        
        if (!empty($atts['class'])) {
            echo '</div>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Warning chart shortcode
     */
    public function warning_chart_shortcode($atts = array()) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'limit' => 20,
            'status' => 'active',
            'class' => ''
        ), $atts);
        
        ob_start();
        
        if (!empty($atts['class'])) {
            echo '<div class="' . esc_attr($atts['class']) . '">';
        }
        
        include ETT_PLUGIN_PATH . 'templates/frontend/warning-chart.php';
        
        if (!empty($atts['class'])) {
            echo '</div>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * All employee tags graph shortcode
     */
    public function all_employee_tags_graph_shortcode($atts = array()) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'date' => date('Y-m-d'),
            'type' => 'bar',
            'class' => ''
        ), $atts);
        
        ob_start();
        
        if (!empty($atts['class'])) {
            echo '<div class="' . esc_attr($atts['class']) . '">';
        }
        
        include ETT_PLUGIN_PATH . 'templates/frontend/employee-tags-graph.php';
        
        if (!empty($atts['class'])) {
            echo '</div>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Break tracker shortcode
     */
    public function break_tracker_shortcode($atts = array()) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'date' => date('Y-m-d'),
            'show_history' => 'true',
            'class' => ''
        ), $atts);
        
        ob_start();
        
        if (!empty($atts['class'])) {
            echo '<div class="' . esc_attr($atts['class']) . '">';
        }
        
        include ETT_PLUGIN_PATH . 'templates/frontend/break-tracker.php';
        
        if (!empty($atts['class'])) {
            echo '</div>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Issue tracker shortcode
     */
    public function issue_tracker_shortcode($atts = array()) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'limit' => 50,
            'status' => 'all',
            'class' => ''
        ), $atts);
        
        ob_start();
        
        if (!empty($atts['class'])) {
            echo '<div class="' . esc_attr($atts['class']) . '">';
        }
        
        include ETT_PLUGIN_PATH . 'templates/frontend/issue-tracker.php';
        
        if (!empty($atts['class'])) {
            echo '</div>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Schedule recurring tasks
     */
    private function schedule_tasks() {
        // Daily cleanup task
        if (!wp_next_scheduled('ett_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ett_daily_cleanup');
        }
        
        // Weekly reports task
        if (!wp_next_scheduled('ett_weekly_reports')) {
            wp_schedule_event(time(), 'weekly', 'ett_weekly_reports');
        }
        
        // Add action hooks for scheduled tasks
        add_action('ett_daily_cleanup', array($this, 'daily_cleanup_task'));
        add_action('ett_weekly_reports', array($this, 'weekly_reports_task'));
    }
    
    /**
     * Daily cleanup task
     */
    public function daily_cleanup_task() {
        global $wpdb;
        
        try {
            // Clean up old sessions (older than 7 days)
            $this->cleanup_old_sessions();
            
            // Update issue days elapsed
            $wpdb->query("
                UPDATE {$wpdb->prefix}ett_issues 
                SET days_elapsed = DATEDIFF(NOW(), raised_date) 
                WHERE issue_status != 'resolved'
            ");
            
            // Auto-dismiss very old warnings (older than 30 days)
            $wpdb->query("
                UPDATE {$wpdb->prefix}ett_warnings 
                SET is_active = 0 
                WHERE is_active = 1 
                AND warning_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            error_log('ETT Plugin: Daily cleanup completed');
        } catch (Exception $e) {
            error_log('ETT Plugin Daily Cleanup Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Weekly reports task
     */
    public function weekly_reports_task() {
        try {
            // Generate weekly statistics
            $this->generate_weekly_stats();
            
            // Send notifications if needed
            $this->send_weekly_notifications();
            
            error_log('ETT Plugin: Weekly reports generated');
        } catch (Exception $e) {
            error_log('ETT Plugin Weekly Reports Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Cleanup old sessions
     */
    private function cleanup_old_sessions() {
        // This would clean up any session-related data if stored in database
        // For now, PHP sessions are handled automatically
        
        // Clean up rate limiting data older than 24 hours
        if (session_status() === PHP_SESSION_ACTIVE) {
            foreach ($_SESSION as $key => $value) {
                if (strpos($key, 'ett_rate_limit_') === 0) {
                    $current_time = time();
                    $_SESSION[$key] = array_filter($value, function($timestamp) use ($current_time) {
                        return ($current_time - $timestamp) < 86400; // 24 hours
                    });
                    
                    if (empty($_SESSION[$key])) {
                        unset($_SESSION[$key]);
                    }
                }
            }
        }
    }
    
    /**
     * Generate weekly statistics
     */
    private function generate_weekly_stats() {
        global $wpdb;
        
        $week_start = date('Y-m-d', strtotime('monday last week'));
        $week_end = date('Y-m-d', strtotime('sunday last week'));
        
        // Get submission statistics
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_submissions,
                SUM(total_minutes) as total_minutes,
                AVG(total_minutes) as avg_minutes,
                COUNT(DISTINCT employee_id) as active_employees
            FROM {$wpdb->prefix}ett_submission_status 
            WHERE submission_date BETWEEN %s AND %s
        ", $week_start, $week_end));
        
        // Store stats for later use
        update_option('ett_last_week_stats', array(
            'week_start' => $week_start,
            'week_end' => $week_end,
            'stats' => $stats,
            'generated_at' => current_time('mysql')
        ));
    }
    
    /**
     * Send weekly notifications
     */
    private function send_weekly_notifications() {
        // Get admin email
        $admin_email = get_option('admin_email');
        
        if (empty($admin_email)) {
            return;
        }
        
        // Get weekly stats
        $weekly_data = get_option('ett_last_week_stats');
        
        if (empty($weekly_data)) {
            return;
        }
        
        $stats = $weekly_data['stats'];
        
        // Prepare email content
        $subject = 'Employee Tag Tracker - Weekly Report';
        $message = sprintf(
            "Weekly Report for %s to %s\n\n" .
            "Total Submissions: %d\n" .
            "Total Hours Logged: %s\n" .
            "Average Hours per Employee: %s\n" .
            "Active Employees: %d\n\n" .
            "View detailed reports in your admin dashboard.",
            $weekly_data['week_start'],
            $weekly_data['week_end'],
            $stats->total_submissions,
            $this->minutes_to_hours_format($stats->total_minutes),
            $this->minutes_to_hours_format($stats->avg_minutes),
            $stats->active_employees
        );
        
        // Send email
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Convert minutes to hours format
     */
    private function minutes_to_hours_format($minutes) {
        if (empty($minutes)) {
            return '0h 0m';
        }
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%dh %dm', $hours, $mins);
    }
    
    /**
     * Add custom body classes for frontend
     */
    public function add_body_classes($classes) {
        if (is_page() && has_shortcode(get_post()->post_content, 'employee_panel')) {
            $classes[] = 'ett-employee-panel-page';
        }
        
        return $classes;
    }
    
    /**
     * Add custom CSS for specific pages
     */
    public function add_custom_css() {
        if (is_page() && has_shortcode(get_post()->post_content, 'employee_panel')) {
            echo '<style>
                body.ett-employee-panel-page {
                    background: #f8fafc;
                }
                .ett-employee-panel-page .site-header,
                .ett-employee-panel-page .site-footer {
                    display: none;
                }
            </style>';
        }
    }
}