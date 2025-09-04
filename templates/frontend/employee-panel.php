<?php
/**
 * Employee Panel Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$security = $plugin->get_security();
$database = $plugin->get_database();

// Start session if not already started
$security->start_session();
?>

<div class="ett-panel-container">
    <?php if ($security->is_employee_logged_in()): ?>
        <?php 
        $employee_id = $security->get_logged_in_employee_id();
        include ETT_PLUGIN_PATH . 'templates/frontend/partials/employee-dashboard.php';
        ?>
    <?php else: ?>
        <?php include ETT_PLUGIN_PATH . 'templates/frontend/partials/login-form.php'; ?>
    <?php endif; ?>
</div>