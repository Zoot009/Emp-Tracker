<?php
/**
 * Plugin Name: Employee Tag & Time Tracker
 * Plugin URI: https://example.com/
 * Description: Track employee work time based on tags with mandatory tag warnings, break management, and issue tracking
 * Version: 1.4.0
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
define('ETT_PLUGIN_VERSION', '1.4.0');
define('ETT_DB_VERSION', '1.4.0');

// Include autoloader
require_once ETT_PLUGIN_PATH . 'includes/class-autoloader.php';

// Initialize autoloader
ETT_Autoloader::init();

// CRITICAL: Direct database creation on activation
register_activation_hook(__FILE__, 'ett_create_database_tables');

function ett_create_database_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Employees table
    $table_employees = $wpdb->prefix . 'ett_employees';
    $sql_employees = "CREATE TABLE IF NOT EXISTS $table_employees (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        employee_code VARCHAR(50) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY email (email),
        UNIQUE KEY employee_code (employee_code)
    ) $charset_collate;";
    
    // Tags table
    $table_tags = $wpdb->prefix . 'ett_tags';
    $sql_tags = "CREATE TABLE IF NOT EXISTS $table_tags (
        id INT(11) NOT NULL AUTO_INCREMENT,
        tag_name VARCHAR(255) NOT NULL,
        time_minutes INT(11) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Assignments table
    $table_assignments = $wpdb->prefix . 'ett_assignments';
    $sql_assignments = "CREATE TABLE IF NOT EXISTS $table_assignments (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        tag_id INT(11) NOT NULL,
        is_mandatory TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY employee_tag (employee_id, tag_id)
    ) $charset_collate;";
    
    // Logs table
    $table_logs = $wpdb->prefix . 'ett_logs';
    $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        tag_id INT(11) NOT NULL,
        count INT(11) NOT NULL DEFAULT 0,
        total_minutes INT(11) NOT NULL DEFAULT 0,
        log_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY employee_tag_date (employee_id, tag_id, log_date)
    ) $charset_collate;";
    
    // Warnings table
    $table_warnings = $wpdb->prefix . 'ett_warnings';
    $sql_warnings = "CREATE TABLE IF NOT EXISTS $table_warnings (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        warning_date DATE NOT NULL,
        warning_message TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Submission status table
    $table_submissions = $wpdb->prefix . 'ett_submission_status';
    $sql_submissions = "CREATE TABLE IF NOT EXISTS $table_submissions (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        submission_date DATE NOT NULL,
        submission_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_locked TINYINT(1) DEFAULT 1,
        total_minutes INT(11) DEFAULT 0,
        status_message VARCHAR(255) DEFAULT 'Data submitted successfully',
        PRIMARY KEY (id),
        UNIQUE KEY employee_date (employee_id, submission_date)
    ) $charset_collate;";
    
    // Breaks table
    $table_breaks = $wpdb->prefix . 'ett_breaks';
    $sql_breaks = "CREATE TABLE IF NOT EXISTS $table_breaks (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        break_date DATE NOT NULL,
        break_in_time DATETIME NULL,
        break_out_time DATETIME NULL,
        break_duration INT(11) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 0,
        warning_sent TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY employee_date (employee_id, break_date)
    ) $charset_collate;";
    
    // Issues table
    $table_issues = $wpdb->prefix . 'ett_issues';
    $sql_issues = "CREATE TABLE IF NOT EXISTS $table_issues (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        issue_category VARCHAR(100) NOT NULL,
        issue_description TEXT NOT NULL,
        issue_status VARCHAR(50) DEFAULT 'pending',
        raised_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        resolved_date DATETIME NULL,
        admin_response TEXT NULL,
        days_elapsed INT(11) DEFAULT 0,
        PRIMARY KEY (id),
        KEY employee_status (employee_id, issue_status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_employees);
    dbDelta($sql_tags);
    dbDelta($sql_assignments);
    dbDelta($sql_logs);
    dbDelta($sql_warnings);
    dbDelta($sql_submissions);
    dbDelta($sql_breaks);
    dbDelta($sql_issues);
    
    update_option('ett_db_version', '1.4.0');
    
    // Log successful creation
    error_log('ETT Plugin: Database tables created successfully');
}

// Plugin lifecycle hooks
register_deactivation_hook(__FILE__, array('ETT_Plugin', 'deactivate'));
register_uninstall_hook(__FILE__, array('ETT_Plugin', 'uninstall'));

// Initialize plugin AFTER WordPress is fully loaded
add_action('plugins_loaded', array('ETT_Plugin', 'get_instance'));