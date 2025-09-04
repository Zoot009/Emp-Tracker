<?php
/**
 * Main Plugin Class - COMPLETE VERSION
 */

class ETT_Plugin {
    
    private static $instance = null;
    private $database;
    private $admin;
    private $ajax;
    private $hooks;
    private $security;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Initialize dependencies
     */
    private function init_dependencies() {
        $this->database = new ETT_Database();
        $this->security = new ETT_Security();
        $this->ajax = new ETT_Ajax($this->database, $this->security);
        $this->admin = new ETT_Admin($this->database, $this->security);
        $this->hooks = new ETT_Hooks($this->database, $this->security);
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this->hooks, 'init'));
        add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this->hooks, 'enqueue_frontend_assets'));
        
        // Register shortcodes
        add_shortcode('employee_panel', array($this->hooks, 'employee_panel_shortcode'));
        add_shortcode('warning_chart', array($this->hooks, 'warning_chart_shortcode'));
        add_shortcode('All_Employee_tags_pragh', array($this->hooks, 'all_employee_tags_graph_shortcode'));
        add_shortcode('break_tracker', array($this->hooks, 'break_tracker_shortcode'));
        add_shortcode('issue_tracker', array($this->hooks, 'issue_tracker_shortcode'));
        
        // Initialize AJAX handlers
        $this->ajax->init_ajax_handlers();
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        $database = new ETT_Database();
        $database->create_tables();
        update_option('ett_db_version', ETT_DB_VERSION);
        
        // Log activation
        error_log('ETT Plugin: Activated successfully');
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Cleanup tasks if needed
        error_log('ETT Plugin: Deactivated');
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        if (defined('WP_UNINSTALL_PLUGIN')) {
            $database = new ETT_Database();
            $database->drop_tables();
            delete_option('ett_db_version');
            error_log('ETT Plugin: Uninstalled - all data removed');
        }
    }
    
    /**
     * Get database instance
     */
    public function get_database() {
        return $this->database;
    }
    
    /**
     * Get security instance
     */
    public function get_security() {
        return $this->security;
    }
}