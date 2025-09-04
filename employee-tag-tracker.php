<?php
/**
 * UPDATED PLUGIN ACTIVATION WITH DATABASE CONNECTION FIXES
 * File: employee-tag-tracker.php (UPDATED SECTIONS)
 */

/**
 * Enhanced database creation on activation with comprehensive validation
 */
register_activation_hook(__FILE__, 'ett_create_database_tables_enhanced');

function ett_create_database_tables_enhanced() {
    global $wpdb;
    
    // Step 1: Validate WordPress environment
    if (!defined('ABSPATH')) {
        wp_die('Invalid WordPress environment');
    }
    
    // Step 2: Check WordPress version
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Employee Tag Tracker requires WordPress 5.0 or higher.');
    }
    
    // Step 3: Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Employee Tag Tracker requires PHP 7.4 or higher.');
    }
    
    // Step 4: Validate database connection at multiple levels
    if (!$wpdb || !is_object($wpdb)) {
        wp_die('WordPress database connection not available');
    }
    
    // Test basic database connectivity
    $test_query = $wpdb->get_var("SELECT 1");
    if ($wpdb->last_error || $test_query !== '1') {
        wp_die('Database connection test failed: ' . $wpdb->last_error);
    }
    
    // Test database write permissions
    $write_test = $wpdb->query("SELECT 1");
    if ($write_test === false) {
        wp_die('Database write test failed: ' . $wpdb->last_error);
    }
    
    // Step 5: Create database instance with enhanced error handling
    try {
        $database = new ETT_Database();
        
        if (!$database->validate_connection()) {
            error_log('ETT Activation Error: Database validation failed - ' . $database->get_last_error());
            wp_die('Database validation failed: ' . $database->get_last_error());
        }
        
        // Step 6: Create tables with verification
        if ($database->create_tables()) {
            // Verify all tables were actually created
            if (!$database->verify_tables_exist()) {
                error_log('ETT Activation Error: Table verification failed - ' . $database->get_last_error());
                wp_die('Table creation verification failed: ' . $database->get_last_error());
            }
            
            // Step 7: Insert sample data safely
            ett_insert_sample_data_enhanced();
            
            // Step 8: Update options
            update_option('ett_db_version', ETT_DB_VERSION);
            update_option('ett_plugin_activated', time());
            update_option('ett_activation_check', array(
                'time' => time(),
                'version' => ETT_PLUGIN_VERSION,
                'db_version' => ETT_DB_VERSION,
                'tables_verified' => true
            ));
            
            // Clear any cached data
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            error_log('ETT Plugin: Activated successfully with all database validations passed');
            
        } else {
            error_log('ETT Activation Error: Table creation failed - ' . $database->get_last_error());
            wp_die('Failed to create database tables: ' . $database->get_last_error());
        }
        
    } catch (Exception $e) {
        error_log('ETT Activation Exception: ' . $e->getMessage());
        wp_die('Plugin activation failed with exception: ' . $e->getMessage());
    }
}

/**
 * Enhanced sample data insertion with better error handling
 */
function ett_insert_sample_data_enhanced() {
    global $wpdb;
    
    try {
        // Only insert if tables are empty
        $employee_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_employees");
        
        if ($wpdb->last_error) {
            error_log('ETT Sample Data Error: Could not check employee count - ' . $wpdb->last_error);
            return false;
        }
        
        if ($employee_count == 0) {
            // Insert sample employees with validation
            $sample_employees = array(
                array(
                    'name' => 'John Doe',
                    'email' => 'john.doe@company.com',
                    'employee_code' => 'EMP001'
                ),
                array(
                    'name' => 'Jane Smith', 
                    'email' => 'jane.smith@company.com',
                    'employee_code' => 'EMP002'
                )
            );
            
            foreach ($sample_employees as $employee) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'ett_employees',
                    array(
                        'name' => $employee['name'],
                        'email' => $employee['email'],
                        'employee_code' => $employee['employee_code'],
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s')
                );
                
                if ($result === false) {
                    error_log('ETT Sample Data Error: Failed to insert employee ' . $employee['name'] . ' - ' . $wpdb->last_error);
                    return false;
                }
            }
            
            // Insert sample tags with validation
            $sample_tags = array(
                array('tag_name' => 'Email Processing', 'time_minutes' => 5),
                array('tag_name' => 'Data Entry', 'time_minutes' => 10),
                array('tag_name' => 'Customer Support', 'time_minutes' => 15)
            );
            
            foreach ($sample_tags as $tag) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'ett_tags',
                    array(
                        'tag_name' => $tag['tag_name'],
                        'time_minutes' => $tag['time_minutes'],
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%d', '%s')
                );
                
                if ($result === false) {
                    error_log('ETT Sample Data Error: Failed to insert tag ' . $tag['tag_name'] . ' - ' . $wpdb->last_error);
                    return false;
                }
            }
            
            error_log('ETT Plugin: Sample data inserted successfully');
            return true;
        }
        
        return true; // No need to insert, data already exists
        
    } catch (Exception $e) {
        error_log('ETT Sample Data Exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced database version check with connection validation
 */
function ett_check_database_version_enhanced() {
    // Only run in admin area
    if (!is_admin()) {
        return;
    }
    
    $installed_version = get_option('ett_db_version', '0');
    
    if (version_compare($installed_version, ETT_DB_VERSION, '<')) {
        // Need to upgrade database
        try {
            $database = new ETT_Database();
            
            if (!$database->validate_connection()) {
                // Connection failed, add admin notice
                add_action('admin_notices', function() use ($database) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>Employee Tag Tracker:</strong> Database connection error - ';
                    echo esc_html($database->get_last_error());
                    echo '</p></div>';
                });
                return;
            }
            
            // Perform upgrade
            if ($database->create_tables()) {
                if ($database->verify_tables_exist()) {
                    update_option('ett_db_version', ETT_DB_VERSION);
                    error_log('ETT Plugin: Database upgraded successfully to version ' . ETT_DB_VERSION);
                    
                    // Add success notice
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>';
                        echo '<strong>Employee Tag Tracker:</strong> Database updated successfully.';
                        echo '</p></div>';
                    });
                } else {
                    // Verification failed
                    add_action('admin_notices', function() use ($database) {
                        echo '<div class="notice notice-error"><p>';
                        echo '<strong>Employee Tag Tracker:</strong> Database upgrade verification failed - ';
                        echo esc_html($database->get_last_error());
                        echo '</p></div>';
                    });
                }
            } else {
                // Upgrade failed
                add_action('admin_notices', function() use ($database) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>Employee Tag Tracker:</strong> Database upgrade failed - ';
                    echo esc_html($database->get_last_error());
                    echo '</p></div>';
                });
            }
            
        } catch (Exception $e) {
            error_log('ETT Database Version Check Exception: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>Employee Tag Tracker:</strong> Database check failed - ';
                echo esc_html($e->getMessage());
                echo '</p></div>';
            });
        }
    }
}

// Replace the existing database version check
remove_action('admin_init', 'ett_check_database_version');
add_action('admin_init', 'ett_check_database_version_enhanced');

/**
 * Enhanced health check function
 */
function ett_perform_health_check() {
    // Only run for admin users
    if (!current_user_can('manage_options')) {
        return;
    }
    
    try {
        $database = new ETT_Database();
        
        // Check 1: Database connection
        if (!$database->validate_connection()) {
            add_action('admin_notices', function() use ($database) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>Employee Tag Tracker:</strong> Database connection error - ';
                echo esc_html($database->get_last_error());
                echo ' <a href="' . admin_url('admin.php?page=ett-dashboard') . '">Check Dashboard</a>';
                echo '</p></div>';
            });
            return;
        }
        
        // Check 2: Required tables
        if (!$database->verify_tables_exist()) {
            add_action('admin_notices', function() use ($database) {
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>Employee Tag Tracker:</strong> Missing database tables - ';
                echo esc_html($database->get_last_error());
                echo ' <a href="' . admin_url('plugins.php') . '">Reactivate plugin</a>';
                echo '</p></div>';
            });
            return;
        }
        
        // Check 3: Version compatibility
        $db_version = get_option('ett_db_version', '0');
        if (version_compare($db_version, ETT_DB_VERSION, '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-info"><p>';
                echo '<strong>Employee Tag Tracker:</strong> Database update available. ';
                echo 'Please wait while we update your database tables.';
                echo '</p></div>';
            });
        }
        
        // Check 4: Basic functionality test
        $employees = $database->get_all_employees();
        if ($employees === false && $database->get_last_error()) {
            add_action('admin_notices', function() use ($database) {
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>Employee Tag Tracker:</strong> Database query test failed - ';
                echo esc_html($database->get_last_error());
                echo '</p></div>';
            });
        }
        
    } catch (Exception $e) {
        error_log('ETT Health Check Exception: ' . $e->getMessage());
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Employee Tag Tracker:</strong> Health check failed - ';
            echo esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
}

// Add health check on admin pages
add_action('admin_init', 'ett_perform_health_check', 20);

/**
 * Connection recovery function
 */
function ett_attempt_connection_recovery() {
    try {
        $database = new ETT_Database();
        
        // Force reconnection
        if ($database->reconnect()) {
            error_log('ETT Plugin: Database connection recovered successfully');
            return true;
        } else {
            error_log('ETT Plugin: Database connection recovery failed - ' . $database->get_last_error());
            return false;
        }
        
    } catch (Exception $e) {
        error_log('ETT Plugin: Connection recovery exception - ' . $e->getMessage());
        return false;
    }
}

/**
 * Add recovery action for admin
 */
add_action('wp_ajax_ett_recover_connection', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    if (ett_attempt_connection_recovery()) {
        wp_send_json_success('Connection recovered');
    } else {
        wp_send_json_error('Recovery failed');
    }
});

/**
 * Enhanced admin notice for connection issues
 */
function ett_show_connection_recovery_notice() {
    $database = new ETT_Database();
    
    if (!$database->is_connected()) {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>Employee Tag Tracker:</strong> Database connection error. 
                <button id="ett-recover-connection" class="button button-small">Attempt Recovery</button>
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#ett-recover-connection').click(function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Recovering...');
                
                $.post(ajaxurl, {
                    action: 'ett_recover_connection'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Recovery failed: ' + response.data);
                        $btn.prop('disabled', false).text('Attempt Recovery');
                    }
                }).fail(function() {
                    alert('Network error during recovery');
                    $btn.prop('disabled', false).text('Attempt Recovery');
                });
            });
        });
        </script>
        <?php
    }
}

// Show recovery notice when needed
add_action('admin_notices', 'ett_show_connection_recovery_notice');

/**
 * Enhanced plugin update handler
 */
function ett_handle_plugin_update($upgrader_object, $options) {
    $our_plugin = plugin_basename(ETT_PLUGIN_FILE);
    
    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        if (isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if ($plugin == $our_plugin) {
                    // Plugin was updated, run enhanced upgrade routine
                    ett_run_enhanced_upgrade();
                    break;
                }
            }
        }
    }
}

function ett_run_enhanced_upgrade() {
    try {
        $database = new ETT_Database();
        
        if (!$database->validate_connection()) {
            error_log('ETT Upgrade Error: Database not connected - ' . $database->get_last_error());
            return false;
        }
        
        $current_version = get_option('ett_db_version', '0');
        
        if (version_compare($current_version, ETT_DB_VERSION, '<')) {
            if ($database->create_tables() && $database->verify_tables_exist()) {
                update_option('ett_db_version', ETT_DB_VERSION);
                update_option('ett_last_upgrade', time());
                error_log('ETT Plugin: Enhanced upgrade completed successfully to version ' . ETT_DB_VERSION);
                return true;
            } else {
                error_log('ETT Upgrade Error: Failed to upgrade database - ' . $database->get_last_error());
                return false;
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log('ETT Enhanced Upgrade Exception: ' . $e->getMessage());
        return false;
    }
}

// Hook the enhanced upgrade handler
add_action('upgrader_process_complete', 'ett_handle_plugin_update', 10, 2);