<?php
/**
 * Complete Admin Class Implementation
 * File: includes/class-admin.php - REPLACE EXISTING
 */

class ETT_Admin {
    
    private $database;
    private $security;
    
    public function __construct($database, $security) {
        $this->database = $database;
        $this->security = $security;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Employee Tag Tracker',
            'Employee Tag Tracker',
            'manage_options',
            'ett-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-groups',
            30
        );
        
        $submenu_pages = array(
            'ett-daily-chart' => array('Daily Chart', 'Daily Chart', 'daily_chart_page'),
            'ett-employees' => array('Employees', 'Employees', 'employees_page'),
            'ett-tags' => array('Tags', 'Tags', 'tags_page'),
            'ett-assignments' => array('Assignments', 'Assignments', 'assignments_page'),
            'ett-edit-logs' => array('Edit Logs', 'Edit Logs', 'edit_logs_page'),
            'ett-missing-data' => array('Missing Data', 'Missing Data', 'missing_data_page'),
            'ett-warnings' => array('Warning Chart', 'Warning Chart', 'warnings_page'),
            'ett-breaks' => array('Break Management', 'Break Management', 'breaks_management_page'),
            'ett-issues' => array('Issue Management', 'Issue Management', 'issues_management_page')
        );
        
        foreach ($submenu_pages as $slug => $page_data) {
            add_submenu_page(
                'ett-dashboard',
                $page_data[0],
                $page_data[1],
                'manage_options',
                $slug,
                array($this, $page_data[2])
            );
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'ett-') === false) {
            return;
        }
        
        wp_enqueue_style(
            'ett-admin-styles', 
            ETT_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            ETT_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'ett-admin-scripts',
            ETT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ETT_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('ett-admin-scripts', 'ettAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'delete_tag' => wp_create_nonce('ett_delete_tag'),
                'delete_employee' => wp_create_nonce('ett_delete_employee'),
                'delete_assignment' => wp_create_nonce('ett_delete_assignment'),
                'update_log' => wp_create_nonce('ett_update_log'),
                'send_warning' => wp_create_nonce('ett_send_warning'),
                'dismiss_warning' => wp_create_nonce('ett_dismiss_warning'),
                'update_issue' => wp_create_nonce('ett_update_issue')
            )
        ));
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        include ETT_PLUGIN_PATH . 'templates/admin/dashboard.php';
    }
    
    /**
     * Daily chart page - COMPLETE IMPLEMENTATION
     */
    public function daily_chart_page() {
        include ETT_PLUGIN_PATH . 'templates/admin/daily-chart.php';
    }
    
    /**
     * Employees page
     */
    public function employees_page() {
        include ETT_PLUGIN_PATH . 'templates/admin/employees.php';
    }
    
    /**
     * Tags page
     */
    public function tags_page() {
        include ETT_PLUGIN_PATH . 'templates/admin/tags.php';
    }
    
    /**
     * Assignments page
     */
    public function assignments_page() {
        include ETT_PLUGIN_PATH . 'templates/admin/assignments.php';
    }
    
    /**
     * Edit logs page - COMPLETE IMPLEMENTATION
     */
    public function edit_logs_page() {
        include ETT_PLUGIN_PATH . 'templates/admin/edit-logs.php';
    }
    
    /**
     * Missing data page - COMPLETE IMPLEMENTATION
     */
    public function missing_data_page() {
        include ETT_PLUGIN_PATH . 'templates/admin/missing-data.php';
    }
    
    /**
     * Warnings page - COMPLETE IMPLEMENTATION
     */
    public function warnings_page() {
        include ETT_PLUGIN_PATH . 'templates/admin/warnings.php';
    }
    
    /**
     * Breaks management page - COMPLETE IMPLEMENTATION
     */
    public function breaks_management_page() {
        include ETT_PLUGIN_PATH . 'templates/admin/breaks.php';
    }
    
    /**
     * Issues management page - COMPLETE IMPLEMENTATION
     */
    public function issues_management_page() {
        include ETT_PLUGIN_PATH . 'templates/admin/issues.php';
    }
}