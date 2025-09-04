<?php
/**
 * Admin Header Partial
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ett-admin-header">
    <div class="ett-admin-nav">
        <nav class="ett-nav-menu">
            <a href="<?php echo admin_url('admin.php?page=ett-dashboard'); ?>" 
               class="ett-nav-link <?php echo (isset($_GET['page']) && $_GET['page'] === 'ett-dashboard') ? 'active' : ''; ?>">
                Dashboard
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-daily-chart'); ?>" 
               class="ett-nav-link <?php echo (isset($_GET['page']) && $_GET['page'] === 'ett-daily-chart') ? 'active' : ''; ?>">
                Daily Chart
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-employees'); ?>" 
               class="ett-nav-link <?php echo (isset($_GET['page']) && $_GET['page'] === 'ett-employees') ? 'active' : ''; ?>">
                Employees
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-tags'); ?>" 
               class="ett-nav-link <?php echo (isset($_GET['page']) && $_GET['page'] === 'ett-tags') ? 'active' : ''; ?>">
                Tags
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-assignments'); ?>" 
               class="ett-nav-link <?php echo (isset($_GET['page']) && $_GET['page'] === 'ett-assignments') ? 'active' : ''; ?>">
                Assignments
            </a>
        </nav>
    </div>
</div>

<style>
.ett-admin-header {
    background: white;
    border-bottom: 1px solid #ddd;
    margin: -10px -20px 20px -20px;
    padding: 0 20px;
}

.ett-nav-menu {
    display: flex;
    gap: 0;
}

.ett-nav-link {
    padding: 15px 20px;
    text-decoration: none;
    color: #555;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
}

.ett-nav-link:hover,
.ett-nav-link.active {
    color: #007cba;
    border-bottom-color: #007cba;
    background: #f8f9fa;
}
</style>