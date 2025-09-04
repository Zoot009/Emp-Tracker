<?php
/**
 * Complete AJAX Handler Class
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
        add_action('wp_ajax_ett_check_lock_status', array($this, 'ett_check_lock_status'));
        add_action('wp_ajax_nopriv_ett_check_lock_status', array($this, 'ett_check_lock_status'));
        add_action('wp_ajax_ett_break_in', array($this, 'ett_break_in'));
        add_action('wp_ajax_nopriv_ett_break_in', array($this, 'ett_break_in'));
        add_action('wp_ajax_ett_break_out', array($this, 'ett_break_out'));
        add_action('wp_ajax_nopriv_ett_break_out', array($this, 'ett_break_out'));
        add_action('wp_ajax_ett_get_break_status', array($this, 'ett_get_break_status'));
        add_action('wp_ajax_nopriv_ett_get_break_status', array($this, 'ett_get_break_status'));
        add_action('wp_ajax_ett_raise_issue', array($this, 'ett_raise_issue'));
        add_action('wp_ajax_nopriv_ett_raise_issue', array($this, 'ett_raise_issue'));
        add_action('wp_ajax_ett_get_employee_issues', array($this, 'ett_get_employee_issues'));
        add_action('wp_ajax_nopriv_ett_get_employee_issues', array($this, 'ett_get_employee_issues'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_ett_update_employee', array($this, 'ett_update_employee'));
        add_action('wp_ajax_ett_update_tag', array($this, 'ett_update_tag'));
        add_action('wp_ajax_ett_delete_tag', array($this, 'ett_delete_tag'));
        add_action('wp_ajax_ett_delete_employee', array($this, 'ett_delete_employee'));
        add_action('wp_ajax_ett_delete_assignment', array($this, 'ett_delete_assignment'));
        add_action('wp_ajax_ett_update_assignment', array($this, 'ett_update_assignment'));
        add_action('wp_ajax_ett_edit_log', array($this, 'ett_edit_log'));
        add_action('wp_ajax_ett_send_warning', array($this, 'ett_send_warning'));
        add_action('wp_ajax_ett_remove_warning', array($this, 'ett_remove_warning'));
        add_action('wp_ajax_ett_update_issue_status', array($this, 'ett_update_issue_status'));
        add_action('wp_ajax_ett_send_break_warning', array($this, 'ett_send_break_warning'));
        add_action('wp_ajax_ett_send_missing_data_warning', array($this, 'ett_send_missing_data_warning'));
        add_action('wp_ajax_ett_update_log', array($this, 'ett_update_log'));
    }
    
    /**
     * Employee login
     */
    public function ett_employee_login() {
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_employee_login')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $employee_code = $this->security->sanitize_text($_POST['employee_code']);
        $employee = $this->database->get_employee_by_code($employee_code);
        
        if ($employee) {
            $this->security->set_employee_login($employee->id);
            wp_send_json_success('Login successful');
        } else {
            wp_send_json_error('Invalid employee code');
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
     * Save work log
     */
    public function ett_save_log() {
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_save_log')) {
            wp_send_json_error('Invalid nonce');
        }
        
        global $wpdb;
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id']);
        $logs = $_POST['logs'];
        $log_date = $this->security->sanitize_text($_POST['log_date']);
        
        $total_minutes = 0;
        $missing_mandatory = false;
        
        // Get mandatory tags
        $mandatory_tags = $wpdb->get_col($wpdb->prepare("
            SELECT tag_id FROM {$wpdb->prefix}ett_assignments 
            WHERE employee_id = %d AND is_mandatory = 1
        ", $employee_id));
        
        foreach ($logs as $log) {
            $tag_id = $this->security->sanitize_int($log['tag_id']);
            $count = $this->security->sanitize_int($log['count']);
            
            // Check if mandatory tag is missing
            if (in_array($tag_id, $mandatory_tags) && $count == 0) {
                $missing_mandatory = true;
            }
            
            if ($this->database->save_log($employee_id, $tag_id, $count, $log_date)) {
                // Get tag time
                $tag = $wpdb->get_row($wpdb->prepare(
                    "SELECT time_minutes FROM {$wpdb->prefix}ett_tags WHERE id = %d",
                    $tag_id
                ));
                
                if ($tag) {
                    $total_minutes += $count * $tag->time_minutes;
                }
            }
        }
        
        // Record submission status
        $wpdb->replace(
            $wpdb->prefix . 'ett_submission_status',
            array(
                'employee_id' => $employee_id,
                'submission_date' => $log_date,
                'submission_time' => current_time('mysql'),
                'is_locked' => 1,
                'total_minutes' => $total_minutes,
                'status_message' => $missing_mandatory ? 'Submitted with missing mandatory tags' : 'Data submitted successfully'
            )
        );
        
        // Create warning if mandatory tags are missing
        if ($missing_mandatory) {
            $this->database->create_warning(
                $employee_id,
                'Mandatory tags were not filled',
                $log_date
            );
        }
        
        wp_send_json_success('Work log submitted and locked successfully');
    }
    
    /**
     * Get logs by date
     */
    public function ett_get_logs_by_date() {
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_get_logs_by_date')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id']);
        $log_date = $this->security->sanitize_text($_POST['log_date']);
        
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
            wp_send_json_success('Break ended successfully');
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
    
    /**
     * DELETE TAG - FIXED
     */
    public function ett_delete_tag() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_delete_tag')) {
            wp_send_json_error('Invalid nonce');
        }
        
        global $wpdb;
        
        $tag_id = $this->security->sanitize_int($_POST['tag_id']);
        
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
        }
        
        if ($this->database->delete_tag($tag_id)) {
            wp_send_json_success('Tag deleted successfully');
        } else {
            wp_send_json_error('Failed to delete tag');
        }
    }
    
    /**
     * Delete employee
     */
    public function ett_delete_employee() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_delete_employee')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id']);
        
        if ($this->database->delete_employee($employee_id)) {
            wp_send_json_success('Employee deleted successfully');
        } else {
            wp_send_json_error('Failed to delete employee');
        }
    }
    
    /**
     * Delete assignment
     */
    public function ett_delete_assignment() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_delete_assignment')) {
            wp_send_json_error('Invalid nonce');
        }
        
        global $wpdb;
        
        $assignment_id = $this->security->sanitize_int($_POST['assignment_id']);
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'ett_assignments',
            array('id' => $assignment_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Assignment deleted successfully');
        } else {
            wp_send_json_error('Failed to delete assignment');
        }
    }
    
    /**
     * Dismiss warning
     */
    public function ett_dismiss_warning() {
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_dismiss_warning')) {
            wp_send_json_error('Invalid nonce');
        }
        
        global $wpdb;
        
        $warning_id = $this->security->sanitize_int($_POST['warning_id']);
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ett_warnings',
            array('is_active' => 0),
            array('id' => $warning_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Warning dismissed');
        } else {
            wp_send_json_error('Failed to dismiss warning');
        }
    }
    
    /**
     * Update log
     */
    public function ett_update_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_update_log')) {
            wp_send_json_error('Invalid nonce');
        }
        
        global $wpdb;
        
        $log_id = $this->security->sanitize_int($_POST['log_id']);
        $count = $this->security->sanitize_int($_POST['count']);
        
        // Get tag time for calculation
        $log = $wpdb->get_row($wpdb->prepare("
            SELECT l.*, t.time_minutes 
            FROM {$wpdb->prefix}ett_logs l
            LEFT JOIN {$wpdb->prefix}ett_tags t ON l.tag_id = t.id
            WHERE l.id = %d
        ", $log_id));
        
        if (!$log) {
            wp_send_json_error('Log not found');
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
     * Update issue status
     */
    public function ett_update_issue_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_update_issue')) {
            wp_send_json_error('Invalid nonce');
        }
        
        global $wpdb;
        
        $issue_id = $this->security->sanitize_int($_POST['issue_id']);
        $update_data = array();
        
        if (isset($_POST['status'])) {
            $update_data['issue_status'] = $this->security->sanitize_text($_POST['status']);
            
            if ($_POST['status'] == 'resolved') {
                $update_data['resolved_date'] = current_time('mysql');
            }
        }
        
        if (isset($_POST['admin_response'])) {
            $update_data['admin_response'] = $this->security->sanitize_textarea($_POST['admin_response']);
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
     * Send break warning
     */
    public function ett_send_break_warning() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_send_break_warning')) {
            wp_send_json_error('Invalid nonce');
        }
        
        global $wpdb;
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id']);
        $break_id = $this->security->sanitize_int($_POST['break_id']);
        
        // Mark warning as sent
        $wpdb->update(
            $wpdb->prefix . 'ett_breaks',
            array('warning_sent' => 1),
            array('id' => $break_id),
            array('%d'),
            array('%d')
        );
        
        // Add warning record
        $result = $this->database->create_warning(
            $employee_id, 
            'Break time exceeded 20 minutes limit',
            date('Y-m-d')
        );
        
        if ($result) {
            wp_send_json_success('Warning sent successfully');
        } else {
            wp_send_json_error('Failed to send warning');
        }
    }
    
    /**
     * Send missing data warning
     */
    public function ett_send_missing_data_warning() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!$this->security->verify_nonce($_POST['nonce'], 'ett_send_warning')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $employee_id = $this->security->sanitize_int($_POST['employee_id']);
        $missing_dates = $this->security->sanitize_text($_POST['missing_dates']);
        
        $result = $this->database->create_warning(
            $employee_id,
            'Missing data for dates: ' . $missing_dates,
            date('Y-m-d')
        );
        
        if ($result) {
            wp_send_json_success('Warning sent successfully');
        } else {
            wp_send_json_error('Failed to send warning');
        }
    }
    
    // Placeholder methods for future implementation
    public function ett_check_lock_status() {
        wp_send_json_success(array('is_locked' => false));
    }
    
    public function ett_get_break_status() {
        wp_send_json_success(array('on_break' => false));
    }
    
    public function ett_get_employee_issues() {
        wp_send_json_success(array());
    }
    
    public function ett_update_employee() {
        wp_send_json_error('Feature not implemented');
    }
    
    public function ett_update_tag() {
        wp_send_json_error('Feature not implemented');
    }
    
    public function ett_update_assignment() {
        wp_send_json_error('Feature not implemented');
    }
    
    public function ett_edit_log() {
        wp_send_json_error('Feature not implemented');
    }
    
    public function ett_send_warning() {
        wp_send_json_error('Feature not implemented');
    }
    
    public function ett_remove_warning() {
        wp_send_json_error('Feature not implemented');
    }
}