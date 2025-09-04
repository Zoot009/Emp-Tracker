<?php
/**
 * Chart Display Container
 */

if (!defined('ABSPATH')) {
    exit;
}

$chart_id = isset($chart_id) ? $chart_id : 'ett-chart-' . uniqid();
$chart_title = isset($chart_title) ? $chart_title : 'Chart';
$chart_type = isset($chart_type) ? $chart_type : 'bar';
$chart_data = isset($chart_data) ? $chart_data : array();
?>

<div class="ett-chart-container ett-card">
    <div class="ett-card-header">
        <h3 class="ett-card-title"><?php echo esc_html($chart_title); ?></h3>
        <?php if (isset($chart_subtitle)): ?>
            <p><?php echo esc_html($chart_subtitle); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="chart-wrapper">
        <canvas id="<?php echo esc_attr($chart_id); ?>" style="max-height: 400px;"></canvas>
    </div>
    
    <?php if (!empty($chart_data)): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart === 'undefined') {
            document.getElementById('<?php echo esc_js($chart_id); ?>').style.display = 'none';
            document.querySelector('#<?php echo esc_js($chart_id); ?>').parentNode.innerHTML = '<p style="text-align:center;color:#999;padding:40px;">Chart.js library not available</p>';
            return;
        }
        
        var ctx = document.getElementById('<?php echo esc_js($chart_id); ?>').getContext('2d');
        var chartData = <?php echo json_encode($chart_data); ?>;
        
        new Chart(ctx, {
            type: '<?php echo esc_js($chart_type); ?>',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '<?php echo isset($y_axis_label) ? esc_js($y_axis_label) : "Values"; ?>'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: '<?php echo isset($x_axis_label) ? esc_js($x_axis_label) : "Categories"; ?>'
                        }
                    }
                }
            }
        });
    });
    </script>
    <?php else: ?>
    <div class="ett-alert ett-alert-info">
        <p>No data available for chart display.</p>
    </div>
    <?php endif; ?>
</div>

<style>
.chart-wrapper {
    position: relative;
    height: 400px;
    margin: 20px 0;
}

.ett-chart-container canvas {
    max-width: 100%;
    height: auto;
}
</style>