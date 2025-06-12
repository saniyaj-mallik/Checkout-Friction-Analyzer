(function($) {
    'use strict';

    // Initialize charts when document is ready
    $(document).ready(function() {
        initializeAbandonmentChart();
        initializeFrictionPointsChart();
        initializeCheckoutTimeChart();
    });

    // Abandonment rate chart
    function initializeAbandonmentChart() {
        const ctx = document.getElementById('abandonmentChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: cfaData.chartLabels,
                datasets: [{
                    label: 'Abandonment Rate',
                    data: cfaData.abandonmentData,
                    borderColor: '#dc3545',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }

    // Friction points chart
    function initializeFrictionPointsChart() {
        const ctx = document.getElementById('frictionPointsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: cfaData.frictionLabels,
                datasets: [{
                    label: 'Occurrences',
                    data: cfaData.frictionData,
                    backgroundColor: '#007bff'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Checkout time chart
    function initializeCheckoutTimeChart() {
        const ctx = document.getElementById('checkoutTimeChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: cfaData.chartLabels,
                datasets: [{
                    label: 'Average Checkout Time',
                    data: cfaData.checkoutTimeData,
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
                            callback: function(value) {
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
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cfa_refresh_dashboard',
                nonce: cfaData.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateDashboardStats(response.data);
                }
            }
        });
    }

    // Update dashboard statistics
    function updateDashboardStats(data) {
        $('.cfa-stat-value').each(function() {
            const statType = $(this).data('stat');
            if (data[statType]) {
                $(this).text(data[statType]);
            }
        });
    }

    // Auto-refresh dashboard every 5 minutes
    setInterval(refreshDashboard, 300000);

    // Handle settings form submission
    $('#cfa-settings-form').on('submit', function(e) {
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
            success: function(response) {
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
        
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

})(jQuery); 