<?php
/**
 * Complete AJAX Handler Class - FIXED VERSION
 * File: includes/class-ajax.php
 */

class ETT_Ajax {
    
    private $database;
    private $security;
    
    public function __construct($database, $security) {
        $this->database = $database;
        $this->security = $security;
    }
    
    /**
     * Initialize all AJAX handlers
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
    }
    
    /**
     * Employee login with improved validation
     */
    public function ett_employee_login() {
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
        
        // Check database connection
        if (!$this->database->is_connected()) {
            wp_send_json_error('Database connection error');
            return;
        }
        
        // Rate limiting
        if (!$this->security->check_rate_limit('login', 0, 5, 300)) {
            wp_send_json_error('Too many login attempts. Please try again later.');
            return;
        }
        
        // Get employee
        $employee = $this->database->get_employee_by_code($employee_code);
        
        if ($employee) {
            if ($this->security->set_employee_login($employee->id)) {
                wp_send_json_success('Login successful');
            } else {
                wp_send_json_error('Failed to set login session');
            }
        } else {
            wp_send_json_error('Invalid employee code');
        }
    }
    
    /**
     * Employee logout
     */
    public function ett_employee_logout() {
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_employee_logout')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if ($this->security->destroy_session()) {
            wp_send_json_success('Logged out successfully');
        } else {
            wp_send_json_error('Logout failed');
        }
    }
    
    /**
     * Save work log with comprehensive validation
     */
    public function ett_save_log() {
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
        
        // Check database connection
        if (!$this->database->is_connected()) {
            wp_send_json_error('Database connection error');
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
        
        // Check if data already submitted for this date
        global $wpdb;
        $existing_submission = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ett_submission_status 
            WHERE employee_id = %d AND submission_date = %s AND is_locked = 1
        ", $employee_id, $log_date));
        
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
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($logs as $log) {
                if (!isset($log['tag_id']) || !isset($log['count'])) {
                    throw new Exception('Invalid log data format');
                }
                
                $tag_id = $this->security->sanitize_int($log['tag_id']);
                $count = $this->security->sanitize_int($log['count'], 0, 9999);
                
                if ($tag_id <= 0) {
                    throw new Exception('Invalid tag ID');
                }
                
                // Check if mandatory tag is missing
                if (in_array($tag_id, $mandatory_tags) && $count == 0) {
                    $missing_mandatory = true;
                }
                
                if (!$this->database->save_log($employee_id, $tag_id, $count, $log_date)) {
                    throw new Exception('Failed to save log: ' . $this->database->get_last_error());
                }
                
                // Calculate total minutes
                $tag = $wpdb->get_row($wpdb->prepare(
                    "SELECT time_minutes FROM {$wpdb->prefix}ett_tags WHERE id = %d",
                    $tag_id
                ));
                
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
                throw new Exception('Failed to record submission status');
            }
            
            // Create warning if mandatory tags are missing
            if ($missing_mandatory) {
                $this->database->create_warning(
                    $employee_id,
                    'Mandatory tags were not filled for ' . $log_date,
                    $log_date
                );
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Refresh session
            $this->security->refresh_session();
            
            wp_send_json_success('Work log submitted and locked successfully');
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Failed to save work log: ' . $e->getMessage());
        }
    }
    
    /**
     * Get logs by date
     */
    public function ett_get_logs_by_date() {
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
        
        $logs = $this->database->get_logs_by_date($employee_id, $log_date);
        
        $data = array();
        foreach ($logs as $log) {
            $data[$log->tag_id] = $log->count;
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Break in
     */
    public function ett_break_in() {
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
        
        $result = $this->database->start_break($employee_id);
        
        if ($result) {
            wp_send_json_success('Break started successfully');
        } else {
            wp_send_json_error($this->database->get_last_error());
        }
    }
    
    /**
     * Break out
     */
    public function ett_break_out() {
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
        
        $result = $this->database->end_break($employee_id);
        
        if ($result) {
            wp_send_json_success('Break ended successfully');
        } else {
            wp_send_json_error($this->database->get_last_error());
        }
    }
    
    /**
     * Raise issue
     */
    public function ett_raise_issue() {
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
        
        $result = $this->database->create_issue($employee_id, $category, $description);
        
        if ($result) {
            wp_send_json_success('Issue raised successfully');
        } else {
            wp_send_json_error($this->database->get_last_error());
        }
    }
    
    /**
     * DELETE TAG - ADMIN ONLY
     */
    public function ett_delete_tag() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        if (!isset($_POST['nonce']) || !$this->security->verify_nonce($_POST['nonce'], 'ett_delete_tag')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!$this->database->is_connected()) {
            wp_send_json_error('Database connection error');
            return;
        }
        
        global $wpdb;
        
        $tag_id = $this->security->sanitize_int($_POST['tag_id'] ?? 0);
        
        if ($tag_id <= 0) {
            wp_send_json_error('Invalid tag ID');
            return;
        }
        
        // Check if tag is in use
        $assignments_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ett_assignments WHERE tag_id = %d",
            $tag_id
        ));
        
        $logs_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ett_logs WHERE tag_id = %d",
            $tag_id
        ));
        
        if ($assignments_count > 0 || $logs_count > 0) {
            wp_send_json_error('Cannot delete tag. It is assigned to employees or has logged data.');
            return;
        }
        
        if ($this->database->delete_tag($tag_id)) {
            wp_send_json_success('Tag deleted successfully');
        } else {
            wp_send_json_error($this->database->get_last_error());
        }
    }
    
    /**
     * Delete employee - ADMIN ONLY
     */
    public function ett_delete_employee() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
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
        
        if ($this->database->delete_employee($employee_id)) {
            wp_send_json_success('Employee deleted successfully');
        } else {
            wp_send_json_error($this->database->get_last_error());
        }
    }
    
    /**
     * Delete assignment - ADMIN ONLY
     */
    public function ett_delete_assignment() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
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
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'ett_assignments',
            array('id' => $assignment_id),
            array('%d')
        );
        
        if ($result !== false && $result > 0) {
            wp_send_json_success('Assignment deleted successfully');
        } else {
            wp_send_json_error('Failed to delete assignment');
        }
    }
    
    /**
     * Dismiss warning
     */
    public function ett_dismiss_warning() {
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
            wp_send_json_error('Failed to dismiss warning');
        }
    }
    
    /**
     * Update log - ADMIN ONLY
     */
    public function ett_update_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
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
        
        // Get log with tag information
        $log = $wpdb->get_row($wpdb->prepare("
            SELECT l.*, t.time_minutes 
            FROM {$wpdb->prefix}ett_logs l
            LEFT JOIN {$wpdb->prefix}ett_tags t ON l.tag_id = t.id
            WHERE l.id = %d
        ", $log_id));
        
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
            wp_send_json_error('Failed to update log');
        }
    }
    
    /**
     * Update issue status - ADMIN ONLY
     */
    public function ett_update_issue_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
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
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ett_issues',
            $update_data,
            array('id' => $issue_id)
        );
        
        if ($result !== false) {
            wp_send_json_success('Issue updated successfully');
        } else {
            wp_send_json_error('Failed to update issue');
        }
    }
    
    /**
     * Send break warning - ADMIN ONLY
     */
    public function ett_send_break_warning() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
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
        
        // Mark warning as sent for the break
        $wpdb->update(
            $wpdb->prefix . 'ett_breaks',
            array('warning_sent' => 1),
            array('id' => $break_id),
            array('%d'),
            array('%d')
        );
        
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
    }
    
    /**
     * Send missing data warning - ADMIN ONLY
     */
    public function ett_send_missing_data_warning() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
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
    }
}