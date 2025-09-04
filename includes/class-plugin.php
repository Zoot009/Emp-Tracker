<?php
/**
 * Main Plugin Class - COMPLETE VERSION
 * File: includes/class-plugin.php
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
        try {
            $this->database = new ETT_Database();
            $this->security = new ETT_Security();
            $this->ajax = new ETT_Ajax($this->database, $this->security);
            $this->admin = new ETT_Admin($this->database, $this->security);
            $this->hooks = new ETT_Hooks($this->database, $this->security);
            
            // Log successful initialization
            error_log('ETT Plugin: All dependencies initialized successfully');
        } catch (Exception $e) {
            error_log('ETT Plugin Error: Failed to initialize dependencies - ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        try {
            // Core hooks
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
            
            // Plugin update hooks
            add_action('upgrader_process_complete', array($this, 'on_plugin_update'), 10, 2);
            
            // Health check
            add_action('wp_loaded', array($this, 'health_check'));
            
            error_log('ETT Plugin: All hooks initialized successfully');
        } catch (Exception $e) {
            error_log('ETT Plugin Error: Failed to initialize hooks - ' . $e->getMessage());
        }
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        try {
            // Validate WordPress version
            if (version_compare(get_bloginfo('version'), '5.0', '<')) {
                deactivate_plugins(plugin_basename(ETT_PLUGIN_FILE));
                wp_die('Employee Tag Tracker requires WordPress 5.0 or higher.');
            }
            
            // Validate PHP version
            if (version_compare(PHP_VERSION, '7.4', '<')) {
                deactivate_plugins(plugin_basename(ETT_PLUGIN_FILE));
                wp_die('Employee Tag Tracker requires PHP 7.4 or higher.');
            }
            
            $database = new ETT_Database();
            
            if (!$database->validate_connection()) {
                wp_die('Database connection failed: ' . $database->get_last_error());
            }
            
            if ($database->create_tables()) {
                update_option('ett_db_version', ETT_DB_VERSION);
                update_option('ett_plugin_activated', time());
                
                // Clear any cached data
                wp_cache_flush();
                
                error_log('ETT Plugin: Activated successfully');
            } else {
                wp_die('Failed to create database tables: ' . $database->get_last_error());
            }
        } catch (Exception $e) {
            error_log('ETT Plugin Activation Error: ' . $e->getMessage());
            wp_die('Plugin activation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        try {
            // Clear scheduled events
            wp_clear_scheduled_hook('ett_daily_cleanup');
            wp_clear_scheduled_hook('ett_weekly_reports');
            
            // Clear cached data
            wp_cache_flush();
            
            // Clear plugin options if needed
            delete_option('ett_plugin_activated');
            
            error_log('ETT Plugin: Deactivated successfully');
        } catch (Exception $e) {
            error_log('ETT Plugin Deactivation Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        try {
            $database = new ETT_Database();
            
            // Ask user for confirmation before data deletion
            if (get_option('ett_delete_data_on_uninstall', false)) {
                if ($database->drop_tables()) {
                    error_log('ETT Plugin: All tables dropped successfully');
                } else {
                    error_log('ETT Plugin Warning: Failed to drop some tables');
                }
                
                // Delete all plugin options
                delete_option('ett_db_version');
                delete_option('ett_plugin_activated');
                delete_option('ett_delete_data_on_uninstall');
                
                // Clear cached data
                wp_cache_flush();
            }
            
            error_log('ETT Plugin: Uninstalled successfully');
        } catch (Exception $e) {
            error_log('ETT Plugin Uninstall Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle plugin updates
     */
    public function on_plugin_update($upgrader_object, $options) {
        $our_plugin = plugin_basename(ETT_PLUGIN_FILE);
        
        if ($options['action'] == 'update' && $options['type'] == 'plugin') {
            if (isset($options['plugins'])) {
                foreach ($options['plugins'] as $plugin) {
                    if ($plugin == $our_plugin) {
                        // Plugin was updated, run upgrade routine
                        $this->upgrade_routine();
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Run upgrade routine
     */
    private function upgrade_routine() {
        try {
            $current_version = get_option('ett_db_version', '0');
            
            if (version_compare($current_version, ETT_DB_VERSION, '<')) {
                // Run database upgrades
                if ($this->database->create_tables()) {
                    update_option('ett_db_version', ETT_DB_VERSION);
                    error_log('ETT Plugin: Database upgraded to version ' . ETT_DB_VERSION);
                } else {
                    error_log('ETT Plugin Error: Database upgrade failed');
                }
            }
        } catch (Exception $e) {
            error_log('ETT Plugin Upgrade Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Health check
     */
    public function health_check() {
        // Only run health check in admin area
        if (!is_admin()) {
            return;
        }
        
        try {
            // Check database connection
            if (!$this->database->is_connected()) {
                add_action('admin_notices', array($this, 'database_error_notice'));
                return;
            }
            
            // Check required tables exist
            if (!$this->check_required_tables()) {
                add_action('admin_notices', array($this, 'missing_tables_notice'));
                return;
            }
            
            // Check version compatibility
            $db_version = get_option('ett_db_version', '0');
            if (version_compare($db_version, ETT_DB_VERSION, '<')) {
                add_action('admin_notices', array($this, 'upgrade_needed_notice'));
                return;
            }
            
        } catch (Exception $e) {
            error_log('ETT Plugin Health Check Error: ' . $e->getMessage());
            add_action('admin_notices', array($this, 'general_error_notice'));
        }
    }
    
    /**
     * Check if all required tables exist
     */
    private function check_required_tables() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'ett_employees',
            $wpdb->prefix . 'ett_tags',
            $wpdb->prefix . 'ett_assignments',
            $wpdb->prefix . 'ett_logs',
            $wpdb->prefix . 'ett_warnings',
            $wpdb->prefix . 'ett_submission_status',
            $wpdb->prefix . 'ett_breaks',
            $wpdb->prefix . 'ett_issues'
        );
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Admin notices
     */
    public function database_error_notice() {
        echo '<div class="notice notice-error"><p><strong>Employee Tag Tracker:</strong> Database connection error. Please check your database configuration.</p></div>';
    }
    
    public function missing_tables_notice() {
        $url = admin_url('plugins.php');
        echo '<div class="notice notice-warning"><p><strong>Employee Tag Tracker:</strong> Some database tables are missing. Please <a href="' . $url . '">deactivate and reactivate</a> the plugin to recreate them.</p></div>';
    }
    
    public function upgrade_needed_notice() {
        echo '<div class="notice notice-info"><p><strong>Employee Tag Tracker:</strong> Database upgrade in progress. Please wait...</p></div>';
    }
    
    public function general_error_notice() {
        echo '<div class="notice notice-error"><p><strong>Employee Tag Tracker:</strong> Plugin error detected. Please check error logs.</p></div>';
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
    
    /**
     * Get admin instance
     */
    public function get_admin() {
        return $this->admin;
    }
    
    /**
     * Get ajax instance
     */
    public function get_ajax() {
        return $this->ajax;
    }
    
    /**
     * Get hooks instance
     */
    public function get_hooks() {
        return $this->hooks;
    }
}