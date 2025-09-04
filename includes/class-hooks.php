<?php
/**
 * Complete Employee Tags Graph Shortcode Implementation
 * Add this to the ETT_Hooks class to replace the placeholder
 */

public function all_employee_tags_graph_shortcode() {
    global $wpdb;
    
    ob_start();
    
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
    <div class="ett-graph-container">
        <h2>Employee Tag Performance - <?php echo date('F j, Y'); ?></h2>
        
        <?php if (!empty($data)): ?>
            <div class="chart-controls" style="margin-bottom: 20px; text-align: center; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                <label style="margin-right: 15px;">
                    <input type="radio" name="chart-type" value="minutes" checked> Time (Minutes)
                </label>
                <label>
                    <input type="radio" name="chart-type" value="count"> Count
                </label>
            </div>
            
            <div class="chart-container" style="position: relative; height: 400px; margin: 20px 0;">
                <canvas id="ett-performance-chart"></canvas>
            </div>
            
            <div class="chart-legend" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                <h4>Today's Summary</h4>
                <div class="summary-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
                    <?php 
                    $employee_totals = array();
                    foreach ($data as $row) {
                        if (!isset($employee_totals[$row->employee_name])) {
                            $employee_totals[$row->employee_name] = 0;
                        }
                        $employee_totals[$row->employee_name] += $row->total_minutes;
                    }
                    
                    foreach ($employee_totals as $emp_name => $total_mins): ?>
                        <div class="summary-item" style="padding: 10px; background: white; border-radius: 4px; text-align: center; border-left: 3px solid #007cba;">
                            <strong><?php echo esc_html($emp_name); ?>:</strong><br>
                            <?php 
                            $hours = floor($total_mins / 60);
                            $minutes = $total_mins % 60;
                            echo sprintf('%dh %dm', $hours, $minutes);
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof Chart === 'undefined') {
                    console.error('Chart.js not loaded');
                    document.getElementById('ett-performance-chart').style.display = 'none';
                    document.querySelector('.chart-container').innerHTML = '<p style="text-align:center;color:#999;padding:40px;">Chart.js library not available. Please refresh the page.</p>';
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
                    '#FF9F40', '#8E5EA2', '#3cba9f', '#e8c3b9', '#c45850'
                ];
                
                var ettChart;
                
                function createChart(dataType) {
                    if (ettChart) {
                        ettChart.destroy();
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
                            backgroundColor: colors[index % colors.length] + '80', // Add transparency
                            borderColor: colors[index % colors.length],
                            borderWidth: 2
                        });
                    });
                    
                    ettChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: employees,
                            datasets: datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: dataType === 'minutes' ? 'Minutes' : 'Count'
                                    },
                                    grid: {
                                        color: '#e0e0e0'
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Employees'
                                    },
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    titleColor: '#fff',
                                    bodyColor: '#fff',
                                    borderColor: '#ddd',
                                    borderWidth: 1,
                                    callbacks: {
                                        label: function(context) {
                                            var label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            label += context.parsed.y;
                                            label += dataType === 'minutes' ? ' minutes' : ' items';
                                            return label;
                                        },
                                        footer: function(tooltipItems) {
                                            var total = 0;
                                            tooltipItems.forEach(function(item) {
                                                total += item.parsed.y;
                                            });
                                            return 'Total: ' + total + (dataType === 'minutes' ? ' minutes' : ' items');
                                        }
                                    }
                                }
                            },
                            animation: {
                                duration: 1000,
                                easing: 'easeInOutQuart'
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
            <div style="padding: 40px; text-align: center; background: #f8f9fa; border-radius: 8px; border: 1px solid #e0e0e0;">
                <h3 style="color: #666; margin-bottom: 15px;">No Data Available</h3>
                <p style="color: #999;">No work data has been logged for today yet. Data will appear here once employees start submitting their work logs.</p>
                <p style="color: #999; font-size: 14px; margin-top: 10px;">Check back later or refresh the page to see updated charts.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
    .ett-graph-container {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid #e0e0e0;
    }
    
    .ett-graph-container h2 {
        text-align: center;
        color: #333;
        margin-bottom: 20px;
        font-size: 24px;
    }
    
    .chart-controls {
        text-align: center;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .chart-controls label {
        cursor: pointer;
        padding: 8px 15px;
        margin: 0 5px;
        border-radius: 4px;
        transition: background-color 0.3s;
        display: inline-block;
    }
    
    .chart-controls label:hover {
        background: #e9ecef;
    }
    
    .chart-controls input[type="radio"] {
        margin-right: 5px;
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
        padding: 15px;
        background: white;
        border-radius: 4px;
        text-align: center;
        border-left: 3px solid #007cba;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    @media (max-width: 768px) {
        .ett-graph-container {
            margin: 10px 0;
            padding: 15px;
        }
        
        .ett-graph-container h2 {
            font-size: 20px;
        }
        
        .chart-container {
            height: 300px;
        }
        
        .summary-grid {
            grid-template-columns: 1fr;
        }
        
        .chart-controls {
            padding: 10px;
        }
        
        .chart-controls label {
            display: block;
            margin: 5px 0;
        }
    }
    </style>
    <?php
    
    return ob_get_clean();
}