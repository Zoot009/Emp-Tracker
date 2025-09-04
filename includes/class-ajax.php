<?php
/**
 * ENHANCED AJAX CLASS WITH DATABASE CONNECTION FIXES
 * File: includes/class-ajax.php (UPDATED VERSION)
 */

class ETT_Ajax {
    
    private $database;
    private $security;
    
    public function __construct($database, $security) {
        $this->database = $database;
        $this->security = $security;
    }
    
    /**
     * Validate database connection before any AJAX operation
     */
    private function validate_database_connection() {
        if (!$this->database || !is_object($this->database)) {
            wp_send_json_error('Database object not available');
            return false;
        }
        
        if (!$this->database->is_connected()) {
            // Attempt reconnection
            if (method_exists($this->database, 'reconnect')) {
                if (!$this->database->reconnect()) {
                    wp_send_json_error('Database connection failed: ' . $this->database->get_last_error());
                    return false;
                }
            } else {
                wp_send_json_error('Database not connected: ' . $this->database->get_last_error());
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Initialize all AJAX handlers with connection validation
     */
    public function init_ajax_handlers() {
        // Frontend AJAX handlers (accessible to all users)
        add_action('wp_ajax_ett_save_log', array($this, 'ett_save_log'));
        add_action('wp_ajax_nopriv_ett_save_log', array($this, 'ett_save_log'));
        add_action('wp_ajax_ett_employee_login', array($this, 'ett_employee_login'));
        add_action('wp_ajax_nopriv_ett_employee_login', array($this, 'ett_employee_login'));
        add_action('wp_ajax_ett_employee_logout', array($this, 'ett_employee_logout'));
        add_action('wp_ajax_nopriv_ett_employee_logout', array($this, 'ett_employee_logout'));
        add_action('wp_ajax_ett_get_logs_by_date', array($this, 'ett_get_logs_by_date'));
        add_action('wp_ajax_nopriv_ett_get_logs_by_date', array($this, 'ett_get_logs_by_date'));
        add_action('wp_ajax_ett_dismiss_warning', array($this, 'ett_dismiss_warning'));
        add_action('wp_ajax_nopriv_ett_dismiss_warning', array($this, 'ett_dismiss_warning'));
        add_action('wp_ajax_ett_break_in', array($this, 'ett_break_in'));
        add_action('wp_ajax_nopriv_ett_break_in', array($this, 'ett_break_in'));
        add_action('wp_ajax_ett_break_out', array($this, 'ett_break_out'));
        add_action('wp_ajax_nopriv_ett_break_out', array($this, 'ett_break_out'));
        add_action('wp_ajax_ett_raise_issue', array($this, 'ett_raise_issue'));
        add_action('wp_ajax_nopriv_ett_raise_issue', array($this, 'ett_raise_issue'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_ett_delete_tag', array($this, 'ett_delete_tag'));
        add_action('wp_ajax_ett_delete_employee', array($this, 'ett_delete_employee'));
        add_action('wp_ajax_ett_delete_assignment', array($this, 'ett_delete_assignment'));
        add_action('wp_ajax_ett_update_log', array($this, 'ett_update_log'));
        add_action('wp_ajax_ett_update_issue_status', array($this, 'ett_update_issue_status'));
        add_action('wp_ajax_ett_send_break_warning', array($this, 'ett_send_break_warning'));
        add_action('wp_ajax_ett_send_missing_data_warning', array($this, 'ett_send_missing_data_warning'));
        
        // Database health check AJAX
        add_action('wp_ajax_ett_check_database_health', array($this, 'ett_check_database_health'));
    }
    
    /**
     * Database health check AJAX endpoint
     */
    public function ett_check_database_health() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        try {
            if ($this->validate_database_connection()) {
                // Test basic operations
                $employees = $this->database->get_all_employees();
                $tags = $this->database->get_all_tags();
                
                wp_send_json_success(array(
                    'status' => 'healthy',
                    'employee_count' => is_array($employees) ? count($employees) : 0,
                    'tag_count' => is_array($tags) ? count($tags) : 0,
                    'message' => 'Database connection is healthy'
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error('Database health check failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Employee login with enhanced database validation
     */
    public function ett_employee_login() {
        // Validate database connection first
        if (!$this->validate_database_connection()) {
            return; // Error already sent
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_employee_login')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Validate input
        if (!isset($_POST['employee_code']) || empty($_POST['employee_code'])) {
            wp_send_json_error('Employee code is required');
            return;
        }
        
        $employee_code = $this->security->sanitize_employee_code($_POST['employee_code']);
        
        if (empty($employee_code)) {
            wp_send_json_error('Invalid employee code format');
            return;
        }
        
        // Rate limiting
        if (!$this->security->check_rate_limit('login', 0, 5, 300)) {
            wp_send_json_error('Too many login attempts. Please try again later.');
            return;
        }
        
        try {
            // Get employee with database error handling
            $employee = $this->database->get_employee_by_code($employee_code);
            
            if ($employee === false) {
                $error = $this->database->get_last_error();
                if (!empty($error)) {
                    wp_send_json_error('Database error: ' . $error);
                } else {
                    wp_send_json_error('Invalid employee code');
                }
                return;
            }
            
            if ($employee) {
                if ($this->security->set_employee_login($employee->id)) {
                    wp_send_json_success('Login successful');
                } else {
                    wp_send_json_error('Failed to set login session');
                }
            } else {
                wp_send_json_error('Invalid employee code');
            }
            
        } catch (Exception $e) {
            error_log('ETT Login Exception: ' . $e->getMessage());
            wp_send_json_error('Login failed due to system error');
        }
    }
    
    /**
     * Employee logout with validation
     */
    public function ett_employee_logout() {
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_employee_logout')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        try {
            if ($this->security->destroy_session()) {
                wp_send_json_success('Logged out successfully');
            } else {
                wp_send_json_error('Logout failed');
            }
        } catch (Exception $e) {
            error_log('ETT Logout Exception: ' . $e->getMessage());
            wp_send_json_error('Logout failed due to system error');
        }
    }
    
    /**
     * Save work log with comprehensive database validation
     */
    public function ett_save_log() {
        // Validate database connection first
        if (!$this->validate_database_connection()) {
            return; // Error already sent
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_save_log')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check if user is logged in
        if (!$this->security->is_employee_logged_in()) {
            wp_send_json_error('Please login first');
            return;
        }
        
        // Validate session
        if (!$this->security->is_session_valid()) {
            wp_send_json_error('Session expired. Please login again.');
            return;
        }
        
        // Validate input data
        $employee_id = $this->security->sanitize_int($_POST['employee_id'] ?? 0);
        $logs = $_POST['logs'] ?? array();
        $log_date = $this->security->sanitize_date($_POST['log_date'] ?? '');
        
        if ($employee_id <= 0) {
            wp_send_json_error('Invalid employee ID');
            return;
        }
        
        if (!is_array($logs) || empty($logs)) {
            wp_send_json_error('No log data provided');
            return;
        }
        
        // Verify employee owns this session
        if ($employee_id !== $this->security->get_logged_in_employee_id()) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        global $wpdb;
        
        try {
            // Check if data already submitted for this date
            $existing_submission = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ett_submission_status 
                WHERE employee_id = %d AND submission_date = %s AND is_locked = 1
            ", $employee_id, $log_date));
            
            if ($wpdb->last_error) {
                wp_send_json_error('Database error checking submission status: ' . $wpdb->last_error);
                return;
            }
            
            if ($existing_submission) {
                wp_send_json_error('Data already submitted and locked for this date');
                return;
            }
            
            // Rate limiting for submissions
            if (!$this->security->check_rate_limit('submit_log', $employee_id, 3, 300)) {
                wp_send_json_error('Too many submission attempts. Please wait before trying again.');
                return;
            }
            
            // Process logs
            $total_minutes = 0;
            $missing_mandatory = false;
            
            // Get mandatory tags for this employee
            $mandatory_tags = $wpdb->get_col($wpdb->prepare("
                SELECT tag_id FROM {$wpdb->prefix}ett_assignments 
                WHERE employee_id = %d AND is_mandatory = 1
            ", $employee_id));
            
            if ($wpdb->last_error) {
                wp_send_json_error('Database error getting mandatory tags: ' . $wpdb->last_error);
                return;
            }
            
            // Start transaction
            $wpdb->query('START TRANSACTION');
            
            foreach ($logs as $log) {
                if (!isset($log['tag_id']) || !isset($log['count'])) {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error('Invalid log data format');
                    return;
                }
                
                $tag_id = $this->security->sanitize_int($log['tag_id']);
                $count = $this->security->sanitize_int($log['count'], 0, 9999);
                
                if ($tag_id <= 0) {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error('Invalid tag ID');
                    return;
                }
                
                // Check if mandatory tag is missing
                if (in_array($tag_id, $mandatory_tags) && $count == 0) {
                    $missing_mandatory = true;
                }
                
                if (!$this->database->save_log($employee_id, $tag_id, $count, $log_date)) {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error('Failed to save log: ' . $this->database->get_last_error());
                    return;
                }
                
                // Calculate total minutes
                $tag = $wpdb->get_row($wpdb->prepare(
                    "SELECT time_minutes FROM {$wpdb->prefix}ett_tags WHERE id = %d",
                    $tag_id
                ));
                
                if ($wpdb->last_error) {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error('Database error getting tag info: ' . $wpdb->last_error);
                    return;
                }
                
                if ($tag) {
                    $total_minutes += $count * $tag->time_minutes;
                }
            }
            
            // Record submission status
            $status_message = $missing_mandatory ? 'Submitted with missing mandatory tags' : 'Data submitted successfully';
            
            $submission_result = $wpdb->replace(
                $wpdb->prefix . 'ett_submission_status',
                array(
                    'employee_id' => $employee_id,
                    'submission_date' => $log_date,
                    'submission_time' => current_time('mysql'),
                    'is_locked' => 1,
                    'total_minutes' => $total_minutes,
                    'status_message' => $status_message
                ),
                array('%d', '%s', '%s', '%d', '%d', '%s')
            );
            
            if ($submission_result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to record submission status: ' . $wpdb->last_error);
                return;
            }
            
            // Create warning if mandatory tags are missing
            if ($missing_mandatory) {
                if (!$this->database->create_warning(
                    $employee_id,
                    'Mandatory tags were not filled for ' . $log_date,
                    $log_date
                )) {
                    error_log('ETT Warning Creation Failed: ' . $this->database->get_last_error());
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Refresh session
            $this->security->refresh_session();
            
            wp_send_json_success('Work log submitted and locked successfully');
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            error_log('ETT Save Log Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to save work log due to system error');
        }
    }
    
    /**
     * Get logs by date with database validation
     */
    public function ett_get_logs_by_date() {
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_get_logs_by_date')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!$this->security->is_employee_logged_in()) {
            wp_send_json_error('Please login first');
            return;
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id'] ?? 0);
        $log_date = $this->security->sanitize_date($_POST['log_date'] ?? '');
        
        // Verify employee owns this session
        if ($employee_id !== $this->security->get_logged_in_employee_id()) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        try {
            $logs = $this->database->get_logs_by_date($employee_id, $log_date);
            
            if ($logs === false) {
                wp_send_json_error('Database error getting logs: ' . $this->database->get_last_error());
                return;
            }
            
            $data = array();
            foreach ($logs as $log) {
                $data[$log->tag_id] = $log->count;
            }
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            error_log('ETT Get Logs Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to get logs due to system error');
        }
    }
    
    /**
     * Break in with database validation
     */
    public function ett_break_in() {
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_break')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!$this->security->is_employee_logged_in()) {
            wp_send_json_error('Please login first');
            return;
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id'] ?? 0);
        
        // Verify employee owns this session
        if ($employee_id !== $this->security->get_logged_in_employee_id()) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        // Rate limiting for break actions
        if (!$this->security->check_rate_limit('break_action', $employee_id, 10, 300)) {
            wp_send_json_error('Too many break actions. Please wait.');
            return;
        }
        
        try {
            $result = $this->database->start_break($employee_id);
            
            if ($result) {
                wp_send_json_success('Break started successfully');
            } else {
                wp_send_json_error($this->database->get_last_error());
            }
        } catch (Exception $e) {
            error_log('ETT Break In Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to start break due to system error');
        }
    }
    
    /**
     * Break out with database validation
     */
    public function ett_break_out() {
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_break')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!$this->security->is_employee_logged_in()) {
            wp_send_json_error('Please login first');
            return;
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id'] ?? 0);
        
        // Verify employee owns this session
        if ($employee_id !== $this->security->get_logged_in_employee_id()) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        // Rate limiting for break actions
        if (!$this->security->check_rate_limit('break_action', $employee_id, 10, 300)) {
            wp_send_json_error('Too many break actions. Please wait.');
            return;
        }
        
        try {
            $result = $this->database->end_break($employee_id);
            
            if ($result) {
                wp_send_json_success('Break ended successfully');
            } else {
                wp_send_json_error($this->database->get_last_error());
            }
        } catch (Exception $e) {
            error_log('ETT Break Out Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to end break due to system error');
        }
    }
    
    /**
     * Raise issue with database validation
     */
    public function ett_raise_issue() {
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_raise_issue')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!$this->security->is_employee_logged_in()) {
            wp_send_json_error('Please login first');
            return;
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id'] ?? 0);
        $category = $this->security->sanitize_text($_POST['category'] ?? '');
        $description = $this->security->sanitize_textarea($_POST['description'] ?? '');
        
        // Verify employee owns this session
        if ($employee_id !== $this->security->get_logged_in_employee_id()) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (empty($category) || empty($description)) {
            wp_send_json_error('Category and description are required');
            return;
        }
        
        // Rate limiting for issue creation
        if (!$this->security->check_rate_limit('raise_issue', $employee_id, 5, 3600)) {
            wp_send_json_error('Too many issues raised recently. Please wait.');
            return;
        }
        
        try {
            $result = $this->database->create_issue($employee_id, $category, $description);
            
            if ($result) {
                wp_send_json_success('Issue raised successfully');
            } else {
                wp_send_json_error($this->database->get_last_error());
            }
        } catch (Exception $e) {
            error_log('ETT Raise Issue Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to raise issue due to system error');
        }
    }
    
    /**
     * DELETE TAG - ADMIN ONLY with database validation
     */
    public function ett_delete_tag() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_delete_tag')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        global $wpdb;
        
        $tag_id = $this->security->sanitize_int($_POST['tag_id'] ?? 0);
        
        if ($tag_id <= 0) {
            wp_send_json_error('Invalid tag ID');
            return;
        }
        
        try {
            // Check if tag is in use
            $assignments_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ett_assignments WHERE tag_id = %d",
                $tag_id
            ));
            
            if ($wpdb->last_error) {
                wp_send_json_error('Database error checking assignments: ' . $wpdb->last_error);
                return;
            }
            
            $logs_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ett_logs WHERE tag_id = %d",
                $tag_id
            ));
            
            if ($wpdb->last_error) {
                wp_send_json_error('Database error checking logs: ' . $wpdb->last_error);
                return;
            }
            
            if ($assignments_count > 0 || $logs_count > 0) {
                wp_send_json_error('Cannot delete tag. It is assigned to employees or has logged data.');
                return;
            }
            
            if ($this->database->delete_tag($tag_id)) {
                wp_send_json_success('Tag deleted successfully');
            } else {
                wp_send_json_error($this->database->get_last_error());
            }
            
        } catch (Exception $e) {
            error_log('ETT Delete Tag Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to delete tag due to system error');
        }
    }
    
    /**
     * Delete employee - ADMIN ONLY with database validation
     */
    public function ett_delete_employee() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_delete_employee')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id'] ?? 0);
        
        if ($employee_id <= 0) {
            wp_send_json_error('Invalid employee ID');
            return;
        }
        
        try {
            if ($this->database->delete_employee($employee_id)) {
                wp_send_json_success('Employee deleted successfully');
            } else {
                wp_send_json_error($this->database->get_last_error());
            }
        } catch (Exception $e) {
            error_log('ETT Delete Employee Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to delete employee due to system error');
        }
    }
    
    /**
     * Delete assignment - ADMIN ONLY with database validation
     */
    public function ett_delete_assignment() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_delete_assignment')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        global $wpdb;
        
        $assignment_id = $this->security->sanitize_int($_POST['assignment_id'] ?? 0);
        
        if ($assignment_id <= 0) {
            wp_send_json_error('Invalid assignment ID');
            return;
        }
        
        try {
            $result = $wpdb->delete(
                $wpdb->prefix . 'ett_assignments',
                array('id' => $assignment_id),
                array('%d')
            );
            
            if ($result !== false && $result > 0) {
                wp_send_json_success('Assignment deleted successfully');
            } else {
                wp_send_json_error('Failed to delete assignment: ' . $wpdb->last_error);
            }
        } catch (Exception $e) {
            error_log('ETT Delete Assignment Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to delete assignment due to system error');
        }
    }
    
    /**
     * Dismiss warning with database validation
     */
    public function ett_dismiss_warning() {
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_dismiss_warning')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        global $wpdb;
        
        $warning_id = $this->security->sanitize_int($_POST['warning_id'] ?? 0);
        
        if ($warning_id <= 0) {
            wp_send_json_error('Invalid warning ID');
            return;
        }
        
        try {
            $result = $wpdb->update(
                $wpdb->prefix . 'ett_warnings',
                array('is_active' => 0),
                array('id' => $warning_id),
                array('%d'),
                array('%d')
            );
            
            if ($result !== false) {
                wp_send_json_success('Warning dismissed successfully');
            } else {
                wp_send_json_error('Failed to dismiss warning: ' . $wpdb->last_error);
            }
        } catch (Exception $e) {
            error_log('ETT Dismiss Warning Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to dismiss warning due to system error');
        }
    }
    
    /**
     * Update log - ADMIN ONLY with database validation
     */
    public function ett_update_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_update_log')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        global $wpdb;
        
        $log_id = $this->security->sanitize_int($_POST['log_id'] ?? 0);
        $count = $this->security->sanitize_int($_POST['count'] ?? 0, 0, 9999);
        
        if ($log_id <= 0) {
            wp_send_json_error('Invalid log ID');
            return;
        }
        
        try {
            // Get log with tag information
            $log = $wpdb->get_row($wpdb->prepare("
                SELECT l.*, t.time_minutes 
                FROM {$wpdb->prefix}ett_logs l
                LEFT JOIN {$wpdb->prefix}ett_tags t ON l.tag_id = t.id
                WHERE l.id = %d
            ", $log_id));
            
            if ($wpdb->last_error) {
                wp_send_json_error('Database error getting log: ' . $wpdb->last_error);
                return;
            }
            
            if (!$log) {
                wp_send_json_error('Log not found');
                return;
            }
            
            $total_minutes = $count * $log->time_minutes;
            
            $result = $wpdb->update(
                $wpdb->prefix . 'ett_logs',
                array(
                    'count' => $count,
                    'total_minutes' => $total_minutes
                ),
                array('id' => $log_id),
                array('%d', '%d'),
                array('%d')
            );
            
            if ($result !== false) {
                wp_send_json_success('Log updated successfully');
            } else {
                wp_send_json_error('Failed to update log: ' . $wpdb->last_error);
            }
        } catch (Exception $e) {
            error_log('ETT Update Log Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to update log due to system error');
        }
    }
    
    /**
     * Update issue status - ADMIN ONLY with database validation
     */
    public function ett_update_issue_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_update_issue')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        global $wpdb;
        
        $issue_id = $this->security->sanitize_int($_POST['issue_id'] ?? 0);
        
        if ($issue_id <= 0) {
            wp_send_json_error('Invalid issue ID');
            return;
        }
        
        $update_data = array();
        
        if (isset($_POST['status']) && !empty($_POST['status'])) {
            $status = $this->security->sanitize_text($_POST['status']);
            $allowed_statuses = array('pending', 'in_progress', 'resolved');
            
            if (in_array($status, $allowed_statuses)) {
                $update_data['issue_status'] = $status;
                
                if ($status === 'resolved') {
                    $update_data['resolved_date'] = current_time('mysql');
                }
            }
        }
        
        if (isset($_POST['admin_response'])) {
            $update_data['admin_response'] = $this->security->sanitize_textarea($_POST['admin_response']);
        }
        
        if (empty($update_data)) {
            wp_send_json_error('No valid data to update');
            return;
        }
        
        try {
            $result = $wpdb->update(
                $wpdb->prefix . 'ett_issues',
                $update_data,
                array('id' => $issue_id)
            );
            
            if ($result !== false) {
                wp_send_json_success('Issue updated successfully');
            } else {
                wp_send_json_error('Failed to update issue: ' . $wpdb->last_error);
            }
        } catch (Exception $e) {
            error_log('ETT Update Issue Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to update issue due to system error');
        }
    }
    
    /**
     * Send break warning - ADMIN ONLY with database validation
     */
    public function ett_send_break_warning() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_send_break_warning')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        global $wpdb;
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id'] ?? 0);
        $break_id = $this->security->sanitize_int($_POST['break_id'] ?? 0);
        
        if ($employee_id <= 0 || $break_id <= 0) {
            wp_send_json_error('Invalid employee or break ID');
            return;
        }
        
        try {
            // Mark warning as sent for the break
            $wpdb->update(
                $wpdb->prefix . 'ett_breaks',
                array('warning_sent' => 1),
                array('id' => $break_id),
                array('%d'),
                array('%d')
            );
            
            if ($wpdb->last_error) {
                wp_send_json_error('Database error updating break: ' . $wpdb->last_error);
                return;
            }
            
            // Create warning record
            $result = $this->database->create_warning(
                $employee_id, 
                'Break time exceeded 20 minutes limit',
                date('Y-m-d')
            );
            
            if ($result) {
                wp_send_json_success('Warning sent successfully');
            } else {
                wp_send_json_error($this->database->get_last_error());
            }
        } catch (Exception $e) {
            error_log('ETT Send Break Warning Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to send warning due to system error');
        }
    }
    
    /**
     * Send missing data warning - ADMIN ONLY with database validation
     */
    public function ett_send_missing_data_warning() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!$this->validate_database_connection()) {
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_send_warning')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id'] ?? 0);
        $missing_dates = $this->security->sanitize_text($_POST['missing_dates'] ?? '');
        
        if ($employee_id <= 0) {
            wp_send_json_error('Invalid employee ID');
            return;
        }
        
        if (empty($missing_dates)) {
            wp_send_json_error('Missing dates information required');
            return;
        }
        
        try {
            $result = $this->database->create_warning(
                $employee_id,
                'Missing data submissions for dates: ' . $missing_dates,
                date('Y-m-d')
            );
            
            if ($result) {
                wp_send_json_success('Warning sent successfully');
            } else {
                wp_send_json_error($this->database->get_last_error());
            }
        } catch (Exception $e) {
            error_log('ETT Send Missing Data Warning Exception: ' . $e->getMessage());
            wp_send_json_error('Failed to send warning due to system error');
        }
    }
}