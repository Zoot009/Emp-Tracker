<?php
/**
 * Employee Tags Performance Graph
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$current_date = date('Y-m-d');

$data = $wpdb->get_results($wpdb->prepare("
    SELECT e.name as employee_name, e.employee_code, t.tag_name, l.count, l.total_minutes
    FROM {$wpdb->prefix}ett_logs l
    LEFT JOIN {$wpdb->prefix}ett_employees e ON l.employee_id = e.id
    LEFT JOIN {$wpdb->prefix}ett_tags t ON l.tag_id = t.id
    WHERE l.log_date = %s AND l.count > 0
    ORDER BY e.name, t.tag_name
", $current_date));

// Get unique employees and tags
$employees = array();
$tags = array();
$chart_data = array();

foreach ($data as $row) {
    if (!in_array($row->employee_name, $employees)) {
        $employees[] = $row->employee_name;
    }
    if (!in_array($row->tag_name, $tags)) {
        $tags[] = $row->tag_name;
    }
    
    $chart_data[] = array(
        'employee' => $row->employee_name,
        'tag' => $row->tag_name,
        'minutes' => intval($row->total_minutes),
        'count' => intval($row->count)
    );
}
?>

<div class="ett-graph-container ett-card">
    <div class="ett-card-header">
        <h2 class="ett-card-title">Employee Performance Chart - <?php echo date('F j, Y'); ?></h2>
        <p>Daily work distribution by tags</p>
    </div>
    
    <?php if (!empty($data)): ?>
        <div class="chart-controls" style="margin-bottom: 20px;">
            <label>
                <input type="radio" name="chart-type" value="minutes" checked> Time (Minutes)
            </label>
            <label style="margin-left: 15px;">
                <input type="radio" name="chart-type" value="count"> Count
            </label>
        </div>
        
        <div class="chart-container">
            <canvas id="ett-performance-chart" style="max-height:400px;"></canvas>
        </div>
        
        <div class="chart-legend" style="margin-top: 20px;">
            <h4>Today's Summary</h4>
            <div class="summary-grid">
                <?php 
                $employee_totals = array();
                foreach ($data as $row) {
                    if (!isset($employee_totals[$row->employee_name])) {
                        $employee_totals[$row->employee_name] = 0;
                    }
                    $employee_totals[$row->employee_name] += $row->total_minutes;
                }
                
                foreach ($employee_totals as $emp_name => $total_mins): ?>
                    <div class="summary-item">
                        <strong><?php echo esc_html($emp_name); ?>:</strong>
                        <?php echo ETT_Utils::minutes_to_hours_format($total_mins); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') {
                document.getElementById('ett-performance-chart').style.display = 'none';
                document.querySelector('.chart-container').innerHTML = '<p style="text-align:center;color:#999;">Chart.js library not loaded. Please refresh the page.</p>';
                return;
            }
            
            var ctx = document.getElementById('ett-performance-chart');
            if (!ctx) return;
            
            ctx = ctx.getContext('2d');
            var employees = <?php echo json_encode($employees); ?>;
            var tags = <?php echo json_encode($tags); ?>;
            var chartData = <?php echo json_encode($chart_data); ?>;
            
            var colors = [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', 
                '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
            ];
            
            function createChart(dataType) {
                if (window.ettChart) {
                    window.ettChart.destroy();
                }
                
                var datasets = [];
                
                tags.forEach(function(tag, index) {
                    var tagData = [];
                    employees.forEach(function(emp) {
                        var found = chartData.find(function(d) {
                            return d.employee === emp && d.tag === tag;
                        });
                        var value = found ? (dataType === 'minutes' ? found.minutes : found.count) : 0;
                        tagData.push(value);
                    });
                    
                    datasets.push({
                        label: tag,
                        data: tagData,
                        backgroundColor: colors[index % colors.length],
                        borderColor: colors[index % colors.length],
                        borderWidth: 1
                    });
                });
                
                window.ettChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: employees,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: dataType === 'minutes' ? 'Minutes' : 'Count'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Employees'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.parsed.y;
                                        label += dataType === 'minutes' ? ' minutes' : ' items';
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Initial chart
            createChart('minutes');
            
            // Chart type toggle
            document.querySelectorAll('input[name="chart-type"]').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    createChart(this.value);
                });
            });
        });
        </script>
    <?php else: ?>
        <div class="ett-alert ett-alert-info">
            <p>No work data available for today. Data will appear here once employees start logging their work.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.chart-controls {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.chart-controls label {
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.chart-controls label:hover {
    background: #e9ecef;
}

.chart-container {
    position: relative;
    height: 400px;
    margin: 20px 0;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.summary-item {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    text-align: center;
    border-left: 3px solid #007cba;
}
</style>