<?php
/**
 * Plugin Name: Employee Tag & Time Tracker
 * Plugin URI: https://example.com/
 * Description: Track employee work time based on tags with mandatory tag warnings, break management, and issue tracking
 * Version: 1.4.1
 * Author: Your Company
 * License: GPL v2 or later
 * Text Domain: employee-tag-tracker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ETT_PLUGIN_FILE', __FILE__);
define('ETT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ETT_PLUGIN_VERSION', '1.4.1');
define('ETT_DB_VERSION', '1.4.1');

// Include autoloader
require_once ETT_PLUGIN_PATH . 'includes/class-autoloader.php';

// Initialize autoloader
ETT_Autoloader::init();

/**
 * Fixed database creation on activation
 */
register_activation_hook(__FILE__, 'ett_create_database_tables_fixed');

function ett_create_database_tables_fixed() {
    global $wpdb;
    
    // Validate database connection first
    if (!$wpdb || $wpdb->last_error) {
        error_log('ETT Plugin Error: Database connection failed - ' . $wpdb->last_error);
        return false;
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Create database instance for proper table creation
    $database = new ETT_Database();
    
    if (!$database->validate_connection()) {
        error_log('ETT Plugin Error: Database validation failed - ' . $database->get_last_error());
        return false;
    }
    
    // Create tables using the database class
    if ($database->create_tables()) {
        // Insert sample data only if tables are empty
        ett_insert_sample_data_safe();
        
        // Update database version
        update_option('ett_db_version', ETT_DB_VERSION);
        
        error_log('ETT Plugin: Database tables created successfully with version ' . ETT_DB_VERSION);
        return true;
    } else {
        error_log('ETT Plugin Error: Failed to create database tables - ' . $database->get_last_error());
        return false;
    }
}

/**
 * Safe sample data insertion
 */
function ett_insert_sample_data_safe() {
    global $wpdb;
    
    try {
        // Check if employees table is empty
        $employee_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_employees");
        
        if ($employee_count == 0) {
            // Insert sample employees
            $wpdb->insert(
                $wpdb->prefix . 'ett_employees',
                array(
                    'name' => 'John Doe',
                    'email' => 'john.doe@company.com',
                    'employee_code' => 'EMP001',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s')
            );
            
            $wpdb->insert(
                $wpdb->prefix . 'ett_employees',
                array(
                    'name' => 'Jane Smith',
                    'email' => 'jane.smith@company.com',
                    'employee_code' => 'EMP002',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s')
            );
            
            // Insert sample tags
            $wpdb->insert(
                $wpdb->prefix . 'ett_tags',
                array(
                    'tag_name' => 'Email Processing',
                    'time_minutes' => 5,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s')
            );
            
            $wpdb->insert(
                $wpdb->prefix . 'ett_tags',
                array(
                    'tag_name' => 'Data Entry',
                    'time_minutes' => 10,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s')
            );
            
            $wpdb->insert(
                $wpdb->prefix . 'ett_tags',
                array(
                    'tag_name' => 'Customer Support',
                    'time_minutes' => 15,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s')
            );
            
            error_log('ETT Plugin: Sample data inserted successfully');
        }
    } catch (Exception $e) {
        error_log('ETT Plugin Error: Failed to insert sample data - ' . $e->getMessage());
    }
}

/**
 * Database version check and upgrade
 */
function ett_check_database_version() {
    $installed_version = get_option('ett_db_version', '0');
    
    if (version_compare($installed_version, ETT_DB_VERSION, '<')) {
        ett_create_database_tables_fixed();
    }
}

// Check database version on admin init
add_action('admin_init', 'ett_check_database_version');

// Plugin lifecycle hooks
register_deactivation_hook(__FILE__, array('ETT_Plugin', 'deactivate'));
register_uninstall_hook(__FILE__, array('ETT_Plugin', 'uninstall'));

// Initialize plugin AFTER WordPress is fully loaded
add_action('plugins_loaded', array('ETT_Plugin', 'get_instance'));

/**
 * Network activation support
 */
function ett_activate_network($network_wide) {
    if (is_multisite() && $network_wide) {
        global $wpdb;
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            ett_create_database_tables_fixed();
            restore_current_blog();
        }
    } else {
        ett_create_database_tables_fixed();
    }
}

register_activation_hook(__FILE__, 'ett_activate_network');