(function ($) {
    'use strict';

    // Initialize charts when document is ready
    $(document).ready(function () {
        console.log('Initializing charts with data:', cfaData);

        // Initialize charts with data from PHP
        if (cfaData) {
            console.log('CFA: Initializing charts with data:', cfaData);

            // Initialize abandonment rate chart
            initializeAbandonmentChart(
                cfaData.chartLabels,
                cfaData.abandonmentData
            );

            // Initialize friction points chart
            initializeFrictionPointsChart(
                cfaData.frictionLabels,
                cfaData.frictionData
            );

            // Initialize checkout time chart
            initializeCheckoutTimeChart(
                cfaData.chartLabels,
                cfaData.checkoutTimeData
            );
        }
    });

    // Abandonment rate chart
    function initializeAbandonmentChart(labels, data) {
        var chartElem = document.getElementById('abandonmentChart');
        if (!chartElem) {
            console.error('Abandonment chart element not found');
            return;
        }
        console.log('Initializing abandonment chart with data:', {
            labels: labels,
            data: data
        });
        var ctx = chartElem.getContext('2d');
        window.abandonmentChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Abandonment Rate',
                    data: data,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#dc3545',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function (value) {
                                return value + '%';
                            },
                            font: {
                                size: 12
                            },
                            color: '#646970'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            color: '#646970'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 14,
                                weight: '600'
                            },
                            color: '#1d2327',
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 14,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 12,
                        cornerRadius: 4,
                        callbacks: {
                            label: function (context) {
                                return 'Abandonment Rate: ' + context.raw + '%';
                            },
                            title: function (context) {
                                return 'Date: ' + context[0].label;
                            }
                        }
                    }
                }
            }
        });
    }

    // Friction points chart
    function initializeFrictionPointsChart(labels, data) {
        var chartElem = document.getElementById('frictionPointsChart');
        if (!chartElem) {
            return;
        }
        var ctx = chartElem.getContext('2d');
        // Color palette for bars
        var colors = [
            '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#17a2b8', '#fd7e14', '#20c997', '#6610f2', '#e83e8c'
        ];
        window.frictionPointsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Occurrences',
                    data: data,
                    backgroundColor: colors.slice(0, labels.length),
                    borderRadius: 8,
                    maxBarThickness: 48,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.85)',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + context.parsed.y;
                            }
                        }
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: '#222',
                        font: { weight: 'bold', size: 13 },
                        formatter: function (value) { return value; }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: { size: 13, weight: 'bold' },
                            color: '#333'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f0f0f0',
                            borderDash: [4, 4]
                        },
                        ticks: {
                            stepSize: 1,
                            font: { size: 12 },
                            color: '#888'
                        }
                    }
                },
                layout: {
                    padding: { top: 20, right: 20, left: 10, bottom: 10 }
                },
                animation: {
                    duration: 700,
                    easing: 'easeOutQuart'
                }
            },
            plugins: [window.ChartDataLabels || {}]
        });
    }

    // Checkout time chart
    function initializeCheckoutTimeChart(labels, data) {
        var chartElem = document.getElementById('checkoutTimeChart');
        if (!chartElem) {
            return;
        }
        var ctx = chartElem.getContext('2d');
        window.checkoutTimeChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Average Checkout Time',
                    data: data,
                    borderColor: '#28a745',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return value + 's';
                            }
                        }
                    }
                }
            }
        });
    }

    // Refresh dashboard data
    function refreshDashboard() {
        console.log('Refreshing dashboard data...');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cfa_refresh_dashboard',
                nonce: cfaData.nonce
            },
            success: function (response) {
                console.log('Dashboard refresh response:', response);
                if (response.success) {
                    // Update chart data
                    cfaData = response.data;
                    console.log('Updated chart data:', cfaData);

                    // Destroy existing charts
                    if (window.abandonmentChart) {
                        window.abandonmentChart.destroy();
                    }
                    if (window.frictionPointsChart) {
                        window.frictionPointsChart.destroy();
                    }
                    if (window.checkoutTimeChart) {
                        window.checkoutTimeChart.destroy();
                    }

                    // Reinitialize charts with new data
                    initializeAbandonmentChart(
                        cfaData.chartLabels,
                        cfaData.abandonmentData
                    );
                    initializeFrictionPointsChart(
                        cfaData.frictionLabels,
                        cfaData.frictionData
                    );
                    initializeCheckoutTimeChart(
                        cfaData.chartLabels,
                        cfaData.checkoutTimeData
                    );

                    // Update dashboard stats
                    updateDashboardStats(response.data);
                } else {
                    console.error('Failed to refresh dashboard:', response);
                }
            },
            error: function (xhr, status, error) {
                console.error('Dashboard refresh error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
            }
        });
    }

    // Update dashboard statistics
    function updateDashboardStats(data) {
        $('.cfa-stat-value').each(function () {
            const statType = $(this).data('stat');
            if (data[statType]) {
                $(this).text(data[statType]);
            }
        });
    }

    // Auto-refresh dashboard every 5 minutes
    setInterval(refreshDashboard, 300000);

    // Handle settings form submission
    $('#cfa-settings-form').on('submit', function (e) {
        e.preventDefault();

        const formData = $(this).serialize();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cfa_save_settings',
                nonce: cfaData.nonce,
                settings: formData
            },
            success: function (response) {
                if (response.success) {
                    showNotice('success', 'Settings saved successfully.');
                } else {
                    showNotice('error', 'Failed to save settings.');
                }
            }
        });
    });

    // Show notice message
    function showNotice(type, message) {
        const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);

        setTimeout(function () {
            notice.fadeOut(function () {
                $(this).remove();
            });
        }, 3000);
    }

})(jQuery); 