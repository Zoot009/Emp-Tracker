<?php
/**
 * Complete AJAX Handler Implementation - Missing Functions
 */

// Add these methods to the ETT_Ajax class

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
 * Check lock status
 */
public function ett_check_lock_status() {
    $this->security->start_session();
    
    $employee_id = $this->security->sanitize_int($_POST['employee_id']);
    $log_date = $this->security->sanitize_text($_POST['log_date']);
    
    global $wpdb;
    $submission = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ett_submission_status 
        WHERE employee_id = %d AND submission_date = %s AND is_locked = 1
    ", $employee_id, $log_date));
    
    wp_send_json_success(array('is_locked' => !empty($submission)));
}

/**
 * Get break status
 */
public function ett_get_break_status() {
    global $wpdb;
    
    $employee_id = $this->security->sanitize_int($_POST['employee_id']);
    
    $active_break = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ett_breaks 
        WHERE employee_id = %d AND is_active = 1
    ", $employee_id));
    
    if ($active_break) {
        wp_send_json_success(array('on_break' => true, 'break_data' => $active_break));
    } else {
        wp_send_json_success(array('on_break' => false));
    }
}

/**
 * Get employee issues
 */
public function ett_get_employee_issues() {
    global $wpdb;
    
    $employee_id = $this->security->sanitize_int($_POST['employee_id']);
    
    $issues = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ett_issues 
        WHERE employee_id = %d 
        ORDER BY raised_date DESC
        LIMIT 10
    ", $employee_id));
    
    wp_send_json_success($issues);
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

/**
 * Update log (for Edit Logs page)
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
 * Send general warning
 */
public function ett_send_warning() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    if (!$this->security->verify_nonce($_POST['nonce'], 'ett_send_warning')) {
        wp_send_json_error('Invalid nonce');
    }
    
    $employee_id = $this->security->sanitize_int($_POST['employee_id']);
    $message = $this->security->sanitize_text($_POST['message']);
    $warning_date = isset($_POST['warning_date']) ? 
        $this->security->sanitize_text($_POST['warning_date']) : 
        date('Y-m-d');
    
    $result = $this->database->create_warning($employee_id, $message, $warning_date);
    
    if ($result) {
        wp_send_json_success('Warning sent successfully');
    } else {
        wp_send_json_error('Failed to send warning');
    }
}

/**
 * Remove warning
 */
public function ett_remove_warning() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    if (!$this->security->verify_nonce($_POST['nonce'], 'ett_remove_warning')) {
        wp_send_json_error('Invalid nonce');
    }
    
    global $wpdb;
    
    $warning_id = $this->security->sanitize_int($_POST['warning_id']);
    
    $result = $wpdb->delete(
        $wpdb->prefix . 'ett_warnings',
        array('id' => $warning_id),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success('Warning removed successfully');
    } else {
        wp_send_json_error('Failed to remove warning');
    }
}

/**
 * Update employee (placeholder for future use)
 */
public function ett_update_employee() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    if (!$this->security->verify_nonce($_POST['nonce'], 'ett_update_employee')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Implementation for updating employee details
    global $wpdb;
    
    $employee_id = $this->security->sanitize_int($_POST['employee_id']);
    $name = $this->security->sanitize_text($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $employee_code = $this->security->sanitize_text($_POST['employee_code']);
    
    $result = $wpdb->update(
        $wpdb->prefix . 'ett_employees',
        array(
            'name' => $name,
            'email' => $email,
            'employee_code' => $employee_code
        ),
        array('id' => $employee_id),
        array('%s', '%s', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success('Employee updated successfully');
    } else {
        wp_send_json_error('Failed to update employee');
    }
}

/**
 * Update tag
 */
public function ett_update_tag() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    if (!$this->security->verify_nonce($_POST['nonce'], 'ett_update_tag')) {
        wp_send_json_error('Invalid nonce');
    }
    
    global $wpdb;
    
    $tag_id = $this->security->sanitize_int($_POST['tag_id']);
    $tag_name = $this->security->sanitize_text($_POST['tag_name']);
    $time_minutes = $this->security->sanitize_int($_POST['time_minutes'], 1);
    
    $result = $wpdb->update(
        $wpdb->prefix . 'ett_tags',
        array(
            'tag_name' => $tag_name,
            'time_minutes' => $time_minutes
        ),
        array('id' => $tag_id),
        array('%s', '%d'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success('Tag updated successfully');
    } else {
        wp_send_json_error('Failed to update tag');
    }
}

/**
 * Update assignment
 */
public function ett_update_assignment() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    if (!$this->security->verify_nonce($_POST['nonce'], 'ett_update_assignment')) {
        wp_send_json_error('Invalid nonce');
    }
    
    global $wpdb;
    
    $assignment_id = $this->security->sanitize_int($_POST['assignment_id']);
    $is_mandatory = $this->security->sanitize_int($_POST['is_mandatory']);
    
    $result = $wpdb->update(
        $wpdb->prefix . 'ett_assignments',
        array('is_mandatory' => $is_mandatory),
        array('id' => $assignment_id),
        array('%d'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success('Assignment updated successfully');
    } else {
        wp_send_json_error('Failed to update assignment');
    }
}

/**
 * Edit log (generic log editing)
 */
public function ett_edit_log() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    if (!$this->security->verify_nonce($_POST['nonce'], 'ett_edit_log')) {
        wp_send_json_error('Invalid nonce');
    }
    
    global $wpdb;
    
    $log_id = $this->security->sanitize_int($_POST['log_id']);
    $field = $this->security->sanitize_text($_POST['field']);
    $value = $this->security->sanitize_text($_POST['value']);
    
    // Validate field
    $allowed_fields = array('count', 'total_minutes');
    if (!in_array($field, $allowed_fields)) {
        wp_send_json_error('Invalid field');
    }
    
    $result = $wpdb->update(
        $wpdb->prefix . 'ett_logs',
        array($field => $value),
        array('id' => $log_id),
        array('%s'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success('Log edited successfully');
    } else {
        wp_send_json_error('Failed to edit log');
    }
}