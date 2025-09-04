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
        $this->security->start_session();
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
            )
        ));
    }
    
    /**
     * Employee panel shortcode
     */
    public function employee_panel_shortcode() {
        ob_start();
        include ETT_PLUGIN_PATH . 'templates/frontend/employee-panel.php';
        return ob_get_clean();
    }
    
    /**
     * Warning chart shortcode
     */
    public function warning_chart_shortcode() {
        ob_start();
        include ETT_PLUGIN_PATH . 'templates/frontend/warning-chart.php';
        return ob_get_clean();
    }
    
    /**
     * All employee tags graph shortcode - COMPLETE IMPLEMENTATION
     */
    public function all_employee_tags_graph_shortcode() {
        ob_start();
        include ETT_PLUGIN_PATH . 'templates/frontend/employee-tags-graph.php';
        return ob_get_clean();
    }
    
    /**
     * Break tracker shortcode
     */
    public function break_tracker_shortcode() {
        ob_start();
        include ETT_PLUGIN_PATH . 'templates/frontend/break-tracker.php';
        return ob_get_clean();
    }
    
    /**
     * Issue tracker shortcode
     */
    public function issue_tracker_shortcode() {
        ob_start();
        include ETT_PLUGIN_PATH . 'templates/frontend/issue-tracker.php';
        return ob_get_clean();
    }
}