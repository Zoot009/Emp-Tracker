<?php
/**
 * Admin Navigation Partial
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_page = isset($_GET['page']) ? $_GET['page'] : '';

$menu_items = array(
    'ett-dashboard' => array('title' => 'Dashboard', 'icon' => 'dashicons-dashboard'),
    'ett-daily-chart' => array('title' => 'Daily Chart', 'icon' => 'dashicons-chart-bar'),
    'ett-employees' => array('title' => 'Employees', 'icon' => 'dashicons-groups'),
    'ett-tags' => array('title' => 'Tags', 'icon' => 'dashicons-tag'),
    'ett-assignments' => array('title' => 'Assignments', 'icon' => 'dashicons-clipboard'),
    'ett-edit-logs' => array('title' => 'Edit Logs', 'icon' => 'dashicons-edit'),
    'ett-missing-data' => array('title' => 'Missing Data', 'icon' => 'dashicons-warning'),
    'ett-warnings' => array('title' => 'Warnings', 'icon' => 'dashicons-flag'),
    'ett-breaks' => array('title' => 'Breaks', 'icon' => 'dashicons-coffee'),
    'ett-issues' => array('title' => 'Issues', 'icon' => 'dashicons-sos')
);
?>

<div class="ett-admin-navigation">
    <ul class="ett-nav-list">
        <?php foreach ($menu_items as $page_slug => $item): ?>
        <li class="ett-nav-item">
            <a href="<?php echo admin_url('admin.php?page=' . $page_slug); ?>" 
               class="ett-nav-link <?php echo ($current_page === $page_slug) ? 'current' : ''; ?>">
                <span class="dashicons <?php echo $item['icon']; ?>"></span>
                <?php echo $item['title']; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<style>
.ett-admin-navigation {
    background: #f1f1f1;
    border-radius: 8px;
    margin-bottom: 20px;
    padding: 10px;
}

.ett-nav-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.ett-nav-item {
    margin: 0;
}

.ett-nav-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    text-decoration: none;
    color: #555;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.ett-nav-link:hover {
    background: #007cba;
    color: white;
}

.ett-nav-link.current {
    background: #007cba;
    color: white;
    font-weight: 600;
}

.ett-nav-link .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

@media (max-width: 768px) {
    .ett-nav-list {
        flex-direction: column;
    }
    
    .ett-nav-link {
        justify-content: flex-start;
    }
}
</style>