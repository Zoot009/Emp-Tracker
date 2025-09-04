<?php
/**
 * Admin Footer Partial
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ett-admin-footer">
    <div class="ett-footer-content">
        <div class="ett-footer-info">
            <p>
                <strong>Employee Tag Tracker</strong> v<?php echo ETT_PLUGIN_VERSION; ?> | 
                <a href="<?php echo admin_url('admin.php?page=ett-dashboard'); ?>">Dashboard</a> | 
                <a href="https://wordpress.org/support/" target="_blank">Support</a>
            </p>
        </div>
        <div class="ett-footer-stats">
            <?php
            global $wpdb;
            $total_employees = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_employees");
            $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_logs");
            $active_warnings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_warnings WHERE is_active = 1");
            ?>
            <small>
                <?php echo $total_employees; ?> employees | 
                <?php echo $total_logs; ?> total logs | 
                <?php echo $active_warnings; ?> active warnings
            </small>
        </div>
    </div>
</div>

<style>
.ett-admin-footer {
    margin-top: 40px;
    padding: 20px 0;
    border-top: 1px solid #ddd;
    background: #f8f9fa;
    margin-left: -20px;
    margin-right: -20px;
    padding-left: 20px;
    padding-right: 20px;
}

.ett-footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.ett-footer-info p {
    margin: 0;
    color: #666;
}

.ett-footer-info a {
    color: #007cba;
    text-decoration: none;
}

.ett-footer-info a:hover {
    text-decoration: underline;
}

.ett-footer-stats {
    color: #999;
}

@media (max-width: 768px) {
    .ett-footer-content {
        flex-direction: column;
        text-align: center;
    }
}
</style>