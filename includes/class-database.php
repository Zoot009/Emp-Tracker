<?php
/**
 * COMPLETE DATABASE CONNECTION FIXES
 * File: includes/class-database.php (UPDATED VERSION)
 */

class ETT_Database {
    
    private $wpdb;
    private $last_error = '';
    private $is_connected = false;
    private $connection_validated = false;
    
    public function __construct() {
        global $wpdb;
        
        // Ensure we have a valid wpdb instance
        if (!$wpdb || !is_object($wpdb)) {
            $this->last_error = 'WordPress database object not available';
            $this->is_connected = false;
            return;
        }
        
        $this->wpdb = $wpdb;
        
        // Validate connection with multiple checks
        $this->is_connected = $this->validate_connection();
        
        if ($this->is_connected) {
            // Set timezone for IST
            $this->set_timezone();
        }
    }
    
    /**
     * Enhanced connection validation with multiple checks
     */
    public function validate_connection() {
        if ($this->connection_validated) {
            return $this->is_connected;
        }
        
        try {
            // Check 1: Ensure wpdb exists and is object
            if (!$this->wpdb || !is_object($this->wpdb)) {
                $this->last_error = 'WordPress database object not available';
                return false;
            }
            
            // Check 2: Test basic query
            $result = $this->wpdb->get_var("SELECT 1");
            if ($this->wpdb->last_error) {
                $this->last_error = 'Database connection test failed: ' . $this->wpdb->last_error;
                return false;
            }
            
            if ($result !== '1') {
                $this->last_error = 'Database connection test returned unexpected result';
                return false;
            }
            
            // Check 3: Test database writing capability
            $test_result = $this->wpdb->query("SELECT 1");
            if ($test_result === false) {
                $this->last_error = 'Database write test failed';
                return false;
            }
            
            // Check 4: Verify we can access the specific database
            $db_name = defined('DB_NAME') ? DB_NAME : '';
            if (empty($db_name)) {
                $this->last_error = 'Database name not configured';
                return false;
            }
            
            // Check 5: Test table creation capability (dry run)
            $charset_collate = $this->wpdb->get_charset_collate();
            if (empty($charset_collate)) {
                error_log('ETT Database Warning: Could not get charset collate, using default');
            }
            
            $this->connection_validated = true;
            return true;
            
        } catch (Exception $e) {
            $this->last_error = 'Database validation exception: ' . $e->getMessage();
            error_log('ETT Database Connection Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Force reconnection attempt
     */
    public function reconnect() {
        global $wpdb;
        
        // Reset validation flag
        $this->connection_validated = false;
        
        // Try to get fresh wpdb instance
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        $this->wpdb = $wpdb;
        $this->is_connected = $this->validate_connection();
        
        return $this->is_connected;
    }
    
    /**
     * Set MySQL timezone to IST with error handling
     */
    private function set_timezone() {
        try {
            // Try to set timezone, but don't fail if it doesn't work
            $result = $this->wpdb->query("SET time_zone = '+05:30'");
            if ($result === false && $this->wpdb->last_error) {
                error_log('ETT Database Warning: Could not set timezone - ' . $this->wpdb->last_error);
            }
        } catch (Exception $e) {
            error_log('ETT Database Warning: Timezone setting failed - ' . $e->getMessage());
        }
    }
    
    /**
     * Safe query execution with connection validation
     */
    private function safe_query($query, $method = 'get_results') {
        if (!$this->is_connected()) {
            $this->last_error = 'Database not connected';
            return false;
        }
        
        try {
            switch ($method) {
                case 'get_results':
                    $result = $this->wpdb->get_results($query);
                    break;
                case 'get_row':
                    $result = $this->wpdb->get_row($query);
                    break;
                case 'get_var':
                    $result = $this->wpdb->get_var($query);
                    break;
                case 'query':
                    $result = $this->wpdb->query($query);
                    break;
                default:
                    $result = $this->wpdb->get_results($query);
            }
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                error_log('ETT Database Query Error: ' . $this->wpdb->last_error . ' | Query: ' . $query);
                return false;
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->last_error = 'Query execution failed: ' . $e->getMessage();
            error_log('ETT Database Exception: ' . $e->getMessage() . ' | Query: ' . $query);
            return false;
        }
    }
    
    /**
     * Enhanced table creation with better error handling
     */
    public function create_tables() {
        if (!$this->validate_connection()) {
            return false;
        }
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Ensure we have dbDelta function
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        if (!function_exists('dbDelta')) {
            $this->last_error = 'WordPress dbDelta function not available';
            return false;
        }
        
        // Table creation order is important due to relationships
        $tables = array(
            'employees' => $this->get_employees_table_sql($charset_collate),
            'tags' => $this->get_tags_table_sql($charset_collate),
            'assignments' => $this->get_assignments_table_sql($charset_collate),
            'logs' => $this->get_logs_table_sql($charset_collate),
            'warnings' => $this->get_warnings_table_sql($charset_collate),
            'submission_status' => $this->get_submission_status_table_sql($charset_collate),
            'breaks' => $this->get_breaks_table_sql($charset_collate),
            'issues' => $this->get_issues_table_sql($charset_collate)
        );
        
        $success = true;
        $created_tables = array();
        
        foreach ($tables as $table_name => $sql) {
            try {
                error_log("ETT Database: Creating table {$table_name}");
                
                $result = dbDelta($sql);
                
                // Check if table was actually created
                $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->wpdb->prefix}ett_{$table_name}'");
                
                if ($table_exists) {
                    $created_tables[] = $table_name;
                    error_log("ETT Database: Successfully created/updated {$table_name} table");
                } else {
                    $this->last_error = "Failed to create table: {$table_name}";
                    $success = false;
                    error_log("ETT Database Error: Table {$table_name} was not created");
                }
                
                if ($this->wpdb->last_error) {
                    error_log("ETT Database Warning for {$table_name}: " . $this->wpdb->last_error);
                }
                
            } catch (Exception $e) {
                $this->last_error = "Exception creating table {$table_name}: " . $e->getMessage();
                $success = false;
                error_log("ETT Database Exception: {$table_name} - " . $e->getMessage());
            }
        }
        
        // Verify all tables were created
        $required_tables = array_keys($tables);
        $missing_tables = array_diff($required_tables, $created_tables);
        
        if (!empty($missing_tables)) {
            $this->last_error = "Failed to create tables: " . implode(', ', $missing_tables);
            $success = false;
        }
        
        if ($success) {
            error_log("ETT Database: All tables created successfully");
        }
        
        return $success;
    }
    
    /**
     * Check if all required tables exist
     */
    public function verify_tables_exist() {
        if (!$this->is_connected()) {
            return false;
        }
        
        $required_tables = array(
            'ett_employees',
            'ett_tags', 
            'ett_assignments',
            'ett_logs',
            'ett_warnings',
            'ett_submission_status',
            'ett_breaks',
            'ett_issues'
        );
        
        foreach ($required_tables as $table) {
            $table_name = $this->wpdb->prefix . $table;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($exists != $table_name) {
                $this->last_error = "Missing required table: $table_name";
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Enhanced employee operations with better error handling
     */
    public function get_employee_by_code($employee_code) {
        if (!$this->is_connected()) {
            return false;
        }
        
        if (empty($employee_code)) {
            $this->last_error = 'Employee code cannot be empty';
            return false;
        }
        
        try {
            $result = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}ett_employees WHERE employee_code = %s",
                sanitize_text_field($employee_code)
            ));
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return false;
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception getting employee: ' . $e->getMessage();
            return false;
        }
    }
    
    public function get_employee_by_id($employee_id) {
        if (!$this->is_connected()) {
            return false;
        }
        
        if (!is_numeric($employee_id) || $employee_id <= 0) {
            $this->last_error = 'Invalid employee ID';
            return false;
        }
        
        try {
            $result = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}ett_employees WHERE id = %d",
                intval($employee_id)
            ));
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return false;
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception getting employee by ID: ' . $e->getMessage();
            return false;
        }
    }
    
    public function get_all_employees() {
        if (!$this->is_connected()) {
            return array();
        }
        
        try {
            $result = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}ett_employees ORDER BY name");
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return array();
            }
            
            return $result ?: array();
            
        } catch (Exception $e) {
            $this->last_error = 'Exception getting all employees: ' . $e->getMessage();
            return array();
        }
    }
    
    public function get_all_tags() {
        if (!$this->is_connected()) {
            return array();
        }
        
        try {
            $result = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}ett_tags ORDER BY tag_name");
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return array();
            }
            
            return $result ?: array();
            
        } catch (Exception $e) {
            $this->last_error = 'Exception getting all tags: ' . $e->getMessage();
            return array();
        }
    }
    
    /**
     * Enhanced CRUD operations with connection validation
     */
    public function create_employee($name, $email, $employee_code) {
        if (!$this->is_connected()) {
            $this->last_error = 'Database not connected';
            return false;
        }
        
        if (empty($name) || empty($email) || empty($employee_code)) {
            $this->last_error = 'All fields are required';
            return false;
        }
        
        try {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_employees',
                array(
                    'name' => sanitize_text_field($name),
                    'email' => sanitize_email($email),
                    'employee_code' => sanitize_text_field($employee_code),
                    'created_at' => $this->get_current_ist_time()
                ),
                array('%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create employee: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $this->wpdb->insert_id;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception creating employee: ' . $e->getMessage();
            return false;
        }
    }
    
    public function create_tag($tag_name, $time_minutes) {
        if (!$this->is_connected()) {
            $this->last_error = 'Database not connected';
            return false;
        }
        
        if (empty($tag_name) || !is_numeric($time_minutes) || $time_minutes <= 0) {
            $this->last_error = 'Invalid tag data';
            return false;
        }
        
        try {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_tags',
                array(
                    'tag_name' => sanitize_text_field($tag_name),
                    'time_minutes' => intval($time_minutes),
                    'created_at' => $this->get_current_ist_time()
                ),
                array('%s', '%d', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create tag: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $this->wpdb->insert_id;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception creating tag: ' . $e->getMessage();
            return false;
        }
    }
    
    public function get_employee_assignments($employee_id) {
        if (!$this->is_connected()) {
            return array();
        }
        
        if (!is_numeric($employee_id) || $employee_id <= 0) {
            $this->last_error = 'Invalid employee ID';
            return array();
        }
        
        try {
            $result = $this->wpdb->get_results($this->wpdb->prepare("
                SELECT a.*, t.tag_name, t.time_minutes
                FROM {$this->wpdb->prefix}ett_assignments a
                LEFT JOIN {$this->wpdb->prefix}ett_tags t ON a.tag_id = t.id
                WHERE a.employee_id = %d
                ORDER BY a.is_mandatory DESC, t.tag_name
            ", intval($employee_id)));
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return array();
            }
            
            return $result ?: array();
            
        } catch (Exception $e) {
            $this->last_error = 'Exception getting employee assignments: ' . $e->getMessage();
            return array();
        }
    }
    
    /**
     * Enhanced assignment creation
     */
    public function create_assignment($employee_id, $tag_id, $is_mandatory = 0) {
        if (!$this->is_connected()) {
            return false;
        }
        
        if (!is_numeric($employee_id) || $employee_id <= 0 || !is_numeric($tag_id) || $tag_id <= 0) {
            $this->last_error = 'Invalid employee or tag ID';
            return false;
        }
        
        try {
            $result = $this->wpdb->replace(
                $this->wpdb->prefix . 'ett_assignments',
                array(
                    'employee_id' => intval($employee_id),
                    'tag_id' => intval($tag_id),
                    'is_mandatory' => intval($is_mandatory),
                    'created_at' => $this->get_current_ist_time()
                ),
                array('%d', '%d', '%d', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create assignment: ' . $this->wpdb->last_error;
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception creating assignment: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Get current IST time with fallback
     */
    public function get_current_ist_time() {
        try {
            $datetime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
            return $datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            error_log('ETT Database: Failed to get IST time - ' . $e->getMessage());
            return current_time('mysql');
        }
    }
    
    /**
     * Enhanced log operations with transaction support
     */
    public function save_log($employee_id, $tag_id, $count, $log_date) {
        if (!$this->is_connected()) {
            return false;
        }
        
        if (!is_numeric($employee_id) || $employee_id <= 0 || !is_numeric($tag_id) || $tag_id <= 0) {
            $this->last_error = 'Invalid employee or tag ID';
            return false;
        }
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Get tag time
            $tag = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT time_minutes FROM {$this->wpdb->prefix}ett_tags WHERE id = %d",
                intval($tag_id)
            ));
            
            if (!$tag) {
                throw new Exception('Tag not found');
            }
            
            $total_minutes = intval($count) * intval($tag->time_minutes);
            
            $result = $this->wpdb->replace(
                $this->wpdb->prefix . 'ett_logs',
                array(
                    'employee_id' => intval($employee_id),
                    'tag_id' => intval($tag_id),
                    'count' => intval($count),
                    'total_minutes' => $total_minutes,
                    'log_date' => sanitize_text_field($log_date),
                    'created_at' => $this->get_current_ist_time()
                ),
                array('%d', '%d', '%d', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                throw new Exception('Failed to save log: ' . $this->wpdb->last_error);
            }
            
            // Commit transaction
            $this->wpdb->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->wpdb->query('ROLLBACK');
            $this->last_error = $e->getMessage();
            return false;
        }
    }
    
    public function get_logs_by_date($employee_id, $log_date) {
        if (!$this->is_connected()) {
            return array();
        }
        
        if (!is_numeric($employee_id) || $employee_id <= 0) {
            return array();
        }
        
        try {
            $result = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}ett_logs 
                 WHERE employee_id = %d AND log_date = %s",
                intval($employee_id),
                sanitize_text_field($log_date)
            ));
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return array();
            }
            
            return $result ?: array();
            
        } catch (Exception $e) {
            $this->last_error = 'Exception getting logs by date: ' . $e->getMessage();
            return array();
        }
    }
    
    /**
     * Enhanced break operations
     */
    public function start_break($employee_id) {
        if (!$this->is_connected()) {
            return false;
        }
        
        if (!is_numeric($employee_id) || $employee_id <= 0) {
            $this->last_error = 'Invalid employee ID';
            return false;
        }
        
        $current_date = date('Y-m-d');
        $current_time = $this->get_current_ist_time();
        
        try {
            // Check if already on break
            $active_break = $this->wpdb->get_row($this->wpdb->prepare("
                SELECT * FROM {$this->wpdb->prefix}ett_breaks 
                WHERE employee_id = %d AND is_active = 1
            ", intval($employee_id)));
            
            if ($active_break) {
                $this->last_error = 'Employee is already on break';
                return false;
            }
            
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_breaks',
                array(
                    'employee_id' => intval($employee_id),
                    'break_date' => $current_date,
                    'break_in_time' => $current_time,
                    'is_active' => 1,
                    'created_at' => $current_time
                ),
                array('%d', '%s', '%s', '%d', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to start break: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $this->wpdb->insert_id;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception starting break: ' . $e->getMessage();
            return false;
        }
    }
    
    public function end_break($employee_id) {
        if (!$this->is_connected()) {
            return false;
        }
        
        if (!is_numeric($employee_id) || $employee_id <= 0) {
            $this->last_error = 'Invalid employee ID';
            return false;
        }
        
        try {
            $active_break = $this->wpdb->get_row($this->wpdb->prepare("
                SELECT * FROM {$this->wpdb->prefix}ett_breaks 
                WHERE employee_id = %d AND is_active = 1
            ", intval($employee_id)));
            
            if (!$active_break) {
                $this->last_error = 'No active break found';
                return false;
            }
            
            $break_out_time = $this->get_current_ist_time();
            
            try {
                $break_in = new DateTime($active_break->break_in_time);
                $break_out = new DateTime($break_out_time);
                $interval = $break_out->diff($break_in);
                $duration = ($interval->h * 60) + $interval->i;
            } catch (Exception $e) {
                $this->last_error = 'Failed to calculate break duration';
                return false;
            }
            
            $result = $this->wpdb->update(
                $this->wpdb->prefix . 'ett_breaks',
                array(
                    'break_out_time' => $break_out_time,
                    'break_duration' => $duration,
                    'is_active' => 0
                ),
                array('id' => $active_break->id),
                array('%s', '%d', '%d'),
                array('%d')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to end break: ' . $this->wpdb->last_error;
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception ending break: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Enhanced issue creation
     */
    public function create_issue($employee_id, $category, $description) {
        if (!$this->is_connected()) {
            return false;
        }
        
        if (!is_numeric($employee_id) || $employee_id <= 0 || empty($category) || empty($description)) {
            $this->last_error = 'Invalid issue data';
            return false;
        }
        
        try {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_issues',
                array(
                    'employee_id' => intval($employee_id),
                    'issue_category' => sanitize_text_field($category),
                    'issue_description' => sanitize_textarea_field($description),
                    'issue_status' => 'pending',
                    'raised_date' => $this->get_current_ist_time()
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create issue: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $this->wpdb->insert_id;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception creating issue: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Enhanced warning creation
     */
    public function create_warning($employee_id, $message, $warning_date = null) {
        if (!$this->is_connected()) {
            return false;
        }
        
        if (!is_numeric($employee_id) || $employee_id <= 0 || empty($message)) {
            $this->last_error = 'Invalid warning data';
            return false;
        }
        
        if (!$warning_date) {
            $warning_date = date('Y-m-d');
        }
        
        try {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_warnings',
                array(
                    'employee_id' => intval($employee_id),
                    'warning_date' => sanitize_text_field($warning_date),
                    'warning_message' => sanitize_text_field($message),
                    'is_active' => 1,
                    'created_at' => $this->get_current_ist_time()
                ),
                array('%d', '%s', '%s', '%d', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create warning: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $this->wpdb->insert_id;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception creating warning: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Enhanced delete operations
     */
    public function delete_employee($id) {
        if (!$this->is_connected()) {
            return false;
        }
        
        if (!is_numeric($id) || $id <= 0) {
            $this->last_error = 'Invalid employee ID';
            return false;
        }
        
        try {
            $result = $this->wpdb->delete(
                $this->wpdb->prefix . 'ett_employees',
                array('id' => intval($id)),
                array('%d')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to delete employee: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $result > 0;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception deleting employee: ' . $e->getMessage();
            return false;
        }
    }
    
    public function delete_tag($id) {
        if (!$this->is_connected()) {
            return false;
        }
        
        if (!is_numeric($id) || $id <= 0) {
            $this->last_error = 'Invalid tag ID';
            return false;
        }
        
        try {
            $result = $this->wpdb->delete(
                $this->wpdb->prefix . 'ett_tags',
                array('id' => intval($id)),
                array('%d')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to delete tag: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $result > 0;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception deleting tag: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Getter methods
     */
    public function get_last_error() {
        if ($this->last_error) {
            return $this->last_error;
        }
        
        return $this->wpdb->last_error ?: 'Unknown database error';
    }
    
    public function is_connected() {
        // Re-validate connection if needed
        if (!$this->connection_validated) {
            $this->is_connected = $this->validate_connection();
        }
        
        return $this->is_connected;
    }
    
    /**
     * Table SQL methods remain the same...
     */
    private function get_employees_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_employees';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            employee_code varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            UNIQUE KEY employee_code (employee_code),
            KEY idx_employee_code (employee_code),
            KEY idx_email (email)
        ) $charset_collate;";
    }
    
    private function get_tags_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_tags';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            tag_name varchar(255) NOT NULL,
            time_minutes int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tag_name (tag_name)
        ) $charset_collate;";
    }
    
    private function get_assignments_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_assignments';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            tag_id int(11) NOT NULL,
            is_mandatory tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY employee_tag (employee_id, tag_id),
            KEY idx_employee_id (employee_id),
            KEY idx_tag_id (tag_id),
            KEY idx_mandatory (is_mandatory)
        ) $charset_collate;";
    }
    
    private function get_logs_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_logs';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            tag_id int(11) NOT NULL,
            count int(11) NOT NULL DEFAULT 0,
            total_minutes int(11) NOT NULL DEFAULT 0,
            log_date date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY employee_tag_date (employee_id, tag_id, log_date),
            KEY idx_employee_date (employee_id, log_date),
            KEY idx_log_date (log_date)
        ) $charset_collate;";
    }
    
    private function get_warnings_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_warnings';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            warning_date date NOT NULL,
            warning_message text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_employee_active (employee_id, is_active),
            KEY idx_warning_date (warning_date)
        ) $charset_collate;";
    }
    
    private function get_submission_status_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_submission_status';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            submission_date date NOT NULL,
            submission_time datetime DEFAULT CURRENT_TIMESTAMP,
            is_locked tinyint(1) DEFAULT 1,
            total_minutes int(11) DEFAULT 0,
            status_message varchar(255) DEFAULT 'Data submitted successfully',
            PRIMARY KEY (id),
            UNIQUE KEY employee_date (employee_id, submission_date),
            KEY idx_submission_date (submission_date)
        ) $charset_collate;";
    }
    
    private function get_breaks_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_breaks';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            break_date date NOT NULL,
            break_in_time datetime NULL,
            break_out_time datetime NULL,
            break_duration int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 0,
            warning_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_employee_date (employee_id, break_date),
            KEY idx_active_breaks (is_active, employee_id)
        ) $charset_collate;";
    }
    
    private function get_issues_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_issues';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            issue_category varchar(100) NOT NULL,
            issue_description text NOT NULL,
            issue_status varchar(50) DEFAULT 'pending',
            raised_date datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_date datetime NULL,
            admin_response text NULL,
            days_elapsed int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_employee_status (employee_id, issue_status),
            KEY idx_status (issue_status),
            KEY idx_raised_date (raised_date)
        ) $charset_collate;";
    }
    
    /**
     * Drop all plugin tables safely
     */
    public function drop_tables() {
        if (!$this->validate_connection()) {
            return false;
        }
        
        // Drop in reverse order to handle dependencies
        $tables = array(
            'ett_issues',
            'ett_breaks', 
            'ett_submission_status',
            'ett_warnings',
            'ett_logs',
            'ett_assignments',
            'ett_tags',
            'ett_employees'
        );
        
        $success = true;
        
        foreach ($tables as $table) {
            try {
                $result = $this->wpdb->query("DROP TABLE IF EXISTS {$this->wpdb->prefix}{$table}");
                if ($result === false) {
                    $this->last_error = "Failed to drop table: {$table}";
                    $success = false;
                    error_log("ETT Database Error: Failed to drop {$table}");
                }
            } catch (Exception $e) {
                $this->last_error = "Exception dropping table {$table}: " . $e->getMessage();
                $success = false;
                error_log("ETT Database Exception: Drop {$table} - " . $e->getMessage());
            }
        }
        
        return $success;
    }
}