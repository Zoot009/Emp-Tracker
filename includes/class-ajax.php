<?php
/**
 * AJAX request handler - FIXED VERSION
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
        // Ensure session is started for login
        add_action('wp_ajax_ett_employee_login', array($this, 'ensure_session_and_handle'));
        add_action('wp_ajax_nopriv_ett_employee_login', array($this, 'ensure_session_and_handle'));
        
        // Frontend actions (available to all users)
        $frontend_actions = array(
            'ett_save_log', 'ett_employee_logout', 'ett_get_logs_by_date',
            'ett_dismiss_warning', 'ett_check_lock_status', 'ett_break_in',
            'ett_break_out', 'ett_get_break_status', 'ett_raise_issue',
            'ett_get_employee_issues'
        );
        
        foreach ($frontend_actions as $action) {
            add_action('wp_ajax_' . $action, array($this, $action));
            add_action('wp_ajax_nopriv_' . $action, array($this, $action));
        }
        
        // Admin-only actions
        $admin_actions = array(
            'ett_update_employee', 'ett_update_tag', 'ett_update_assignment',
            'ett_edit_log', 'ett_send_warning', 'ett_remove_warning',
            'ett_update_issue_status', 'ett_send_break_warning',
            'ett_send_missing_data_warning', 'ett_update_log'
        );
        
        foreach ($admin_actions as $action) {
            add_action('wp_ajax_' . $action, array($this, $action));
        }
    }
    
    /**
     * Ensure session is started before handling login
     */
    public function ensure_session_and_handle() {
        $this->security->start_session();
        $this->ett_employee_login();
    }
    
    /**
     * Employee login - FIXED
     */
    public function ett_employee_login() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_employee_login')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Get and validate input
        $employee_code = $this->security->sanitize_employee_code($_POST['employee_code']);
        
        if (empty($employee_code)) {
            wp_send_json_error('Employee code is required');
        }
        
        // Get employee from database
        $employee = $this->database->get_employee_by_code($employee_code);
        
        if (!$employee) {
            wp_send_json_error('Invalid employee code. Please check and try again.');
        }
        
        // Set login session
        if ($this->security->set_employee_login($employee->id)) {
            wp_send_json_success(array(
                'message' => 'Login successful',
                'employee_name' => $employee->name,
                'employee_id' => $employee->id
            ));
        } else {
            wp_send_json_error('Failed to establish login session');
        }
    }
    
    /**
     * Employee logout
     */
    public function ett_employee_logout() {
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_employee_logout')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $this->security->destroy_session();
        wp_send_json_success('Logged out successfully');
    }
    
    /**
     * Save work log - FIXED
     */
    public function ett_save_log() {
        // Ensure session
        $this->security->start_session();
        
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_save_log')) {
            wp_send_json_error('Invalid security token');
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id']);
        $logs = isset($_POST['logs']) ? $_POST['logs'] : array();
        $log_date = $this->security->sanitize_text($_POST['log_date']);
        
        if (!$employee_id || empty($logs) || !$log_date) {
            wp_send_json_error('Missing required data');
        }
        
        // Validate employee session
        if (!$this->security->is_employee_logged_in() || 
            $this->security->get_logged_in_employee_id() !== $employee_id) {
            wp_send_json_error('Invalid employee session');
        }
        
        $total_minutes = 0;
        $missing_mandatory = false;
        $successful_saves = 0;
        
        // Get mandatory tags for this employee
        global $wpdb;
        $mandatory_tags = $wpdb->get_col($wpdb->prepare("
            SELECT tag_id FROM {$wpdb->prefix}ett_assignments 
            WHERE employee_id = %d AND is_mandatory = 1
        ", $employee_id));
        
        foreach ($logs as $log) {
            $tag_id = intval($log['tag_id']);
            $count = intval($log['count']);
            
            // Check if mandatory tag is missing
            if (in_array($tag_id, $mandatory_tags) && $count == 0) {
                $missing_mandatory = true;
            }
            
            // Save log
            if ($this->database->save_log($employee_id, $tag_id, $count, $log_date)) {
                $successful_saves++;
                
                // Calculate total minutes
                $tag = $wpdb->get_row($wpdb->prepare(
                    "SELECT time_minutes FROM {$wpdb->prefix}ett_tags WHERE id = %d",
                    $tag_id
                ));
                
                if ($tag) {
                    $total_minutes += $count * $tag->time_minutes;
                }
            }
        }
        
        if ($successful_saves === 0) {
            wp_send_json_error('Failed to save any work logs');
        }
        
        // Record submission status
        $status_message = $missing_mandatory ? 
            'Submitted with missing mandatory tags' : 
            'Data submitted successfully';
            
        $wpdb->replace(
            $wpdb->prefix . 'ett_submission_status',
            array(
                'employee_id' => $employee_id,
                'submission_date' => $log_date,
                'submission_time' => current_time('mysql'),
                'is_locked' => 1,
                'total_minutes' => $total_minutes,
                'status_message' => $status_message
            )
        );
        
        // Create warning if mandatory tags are missing
        if ($missing_mandatory) {
            $this->database->create_warning($employee_id, 'Mandatory tags were not filled', $log_date);
        }
        
        wp_send_json_success($status_message . ' (' . $successful_saves . ' items saved)');
    }
    
    /**
     * Get logs by date - FIXED
     */
    public function ett_get_logs_by_date() {
        $this->security->start_session();
        
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_get_logs_by_date')) {
            wp_send_json_error('Invalid security token');
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id']);
        $log_date = $this->security->sanitize_text($_POST['log_date']);
        
        if (!$employee_id || !$log_date) {
            wp_send_json_error('Missing required parameters');
        }
        
        // Validate employee session
        if (!$this->security->is_employee_logged_in() || 
            $this->security->get_logged_in_employee_id() !== $employee_id) {
            wp_send_json_error('Invalid employee session');
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
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_break')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id']);
        
        if ($this->database->start_break($employee_id)) {
            wp_send_json_success('Break started');
        } else {
            wp_send_json_error('Already on break or failed to start break');
        }
    }
    
    /**
     * Break out
     */
    public function ett_break_out() {
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_break')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id']);
        
        if ($this->database->end_break($employee_id)) {
            wp_send_json_success('Break ended');
        } else {
            wp_send_json_error('No active break found or failed to end break');
        }
    }
    
    /**
     * Raise issue
     */
    public function ett_raise_issue() {
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_raise_issue')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id']);
        $category = $this->security->sanitize_text($_POST['category']);
        $description = $this->security->sanitize_textarea($_POST['description']);
        
        if ($this->database->create_issue($employee_id, $category, $description)) {
            wp_send_json_success('Issue raised successfully');
        } else {
            wp_send_json_error('Failed to raise issue');
        }
    }
    
    // Placeholder methods for remaining AJAX handlers
    public function ett_dismiss_warning() { wp_send_json_error('Feature under development'); }
    public function ett_check_lock_status() { wp_send_json_error('Feature under development'); }
    public function ett_get_break_status() { wp_send_json_error('Feature under development'); }
    public function ett_get_employee_issues() { wp_send_json_error('Feature under development'); }
    public function ett_update_employee() { wp_send_json_error('Feature under development'); }
    public function ett_update_tag() { wp_send_json_error('Feature under development'); }
    public function ett_update_assignment() { wp_send_json_error('Feature under development'); }
    public function ett_edit_log() { wp_send_json_error('Feature under development'); }
    public function ett_send_warning() { wp_send_json_error('Feature under development'); }
    public function ett_remove_warning() { wp_send_json_error('Feature under development'); }
    public function ett_update_issue_status() { wp_send_json_error('Feature under development'); }
    public function ett_send_break_warning() { wp_send_json_error('Feature under development'); }
    public function ett_send_missing_data_warning() { wp_send_json_error('Feature under development'); }
    public function ett_update_log() { wp_send_json_error('Feature under development'); }
}