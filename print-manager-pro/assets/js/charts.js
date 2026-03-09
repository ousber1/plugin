/**
 * Print Manager Pro - Charts & Statistics
 * Uses Chart.js for admin analytics dashboards.
 */
(function($) {
    'use strict';

    var PMP_Charts = {
        charts: {},
        monthLabels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],

        init: function() {
            this.bindEvents();
            this.loadData();
        },

        bindEvents: function() {
            var self = this;

            $('#pmp-chart-year, #pmp-stats-year').on('change', function() {
                self.loadData();
            });

            $('#pmp-stats-refresh').on('click', function() {
                self.loadData();
            });
        },

        loadData: function() {
            var self = this;
            var year = $('#pmp-chart-year').val() || $('#pmp-stats-year').val() || new Date().getFullYear();

            $.post(pmp_admin.ajax_url, {
                action: 'pmp_get_dashboard_data',
                nonce: pmp_admin.nonce,
                year: year
            }, function(response) {
                if (response.success) {
                    self.renderCharts(response.data);
                }
            });
        },

        renderCharts: function(data) {
            // Dashboard revenue chart
            this.renderRevenueChart('pmp-revenue-chart', data);

            // Dashboard profit chart
            this.renderProfitChart('pmp-profit-chart', data);

            // Statistics page charts
            this.renderRevenueChart('pmp-stats-revenue-chart', data);
            this.renderProfitChart('pmp-stats-profit-chart', data);
            this.renderExpensesPie('pmp-stats-expenses-pie', data);
            this.renderOrdersChart('pmp-stats-orders-chart', data);
        },

        renderRevenueChart: function(canvasId, data) {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return;

            if (this.charts[canvasId]) {
                this.charts[canvasId].destroy();
            }

            this.charts[canvasId] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: this.monthLabels,
                    datasets: [
                        {
                            label: 'Revenus',
                            data: data.monthly_revenue,
                            backgroundColor: 'rgba(34, 113, 177, 0.7)',
                            borderColor: '#2271b1',
                            borderWidth: 1
                        },
                        {
                            label: 'Dépenses',
                            data: data.monthly_expenses,
                            backgroundColor: 'rgba(214, 54, 56, 0.7)',
                            borderColor: '#d63638',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('fr-FR') + ' €';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ' : ' + parseFloat(context.parsed.y).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' €';
                                }
                            }
                        }
                    }
                }
            });
        },

        renderProfitChart: function(canvasId, data) {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return;

            if (this.charts[canvasId]) {
                this.charts[canvasId].destroy();
            }

            var profitData = data.monthly_revenue.map(function(rev, i) {
                return rev - data.monthly_expenses[i];
            });

            var bgColors = profitData.map(function(val) {
                return val >= 0 ? 'rgba(0, 163, 42, 0.7)' : 'rgba(214, 54, 56, 0.7)';
            });

            this.charts[canvasId] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: this.monthLabels,
                    datasets: [{
                        label: 'Bénéfice',
                        data: profitData,
                        backgroundColor: bgColors,
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('fr-FR') + ' €';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Bénéfice : ' + parseFloat(context.parsed.y).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' €';
                                }
                            }
                        }
                    }
                }
            });
        },

        renderExpensesPie: function(canvasId, data) {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return;

            if (this.charts[canvasId]) {
                this.charts[canvasId].destroy();
            }

            // Aggregate monthly expenses into categories (simplified view)
            var total = data.monthly_expenses.reduce(function(a, b) { return a + b; }, 0);

            this.charts[canvasId] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: this.monthLabels,
                    datasets: [{
                        data: data.monthly_expenses,
                        backgroundColor: [
                            '#2271b1', '#d63638', '#00a32a', '#dba617',
                            '#8c8f94', '#3582c4', '#e65054', '#36b37e',
                            '#f0b849', '#a7aaad', '#1e8cbe', '#cc1818'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var val = parseFloat(context.parsed).toLocaleString('fr-FR', {minimumFractionDigits: 2});
                                    var pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return context.label + ' : ' + val + ' € (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },

        renderOrdersChart: function(canvasId, data) {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return;

            if (this.charts[canvasId]) {
                this.charts[canvasId].destroy();
            }

            // Derive orders per month from revenue (approximation)
            var avgOrderValue = data.total_orders > 0 ? data.total_revenue / data.total_orders : 50;
            var ordersPerMonth = data.monthly_revenue.map(function(rev) {
                return avgOrderValue > 0 ? Math.round(rev / avgOrderValue) : 0;
            });

            this.charts[canvasId] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.monthLabels,
                    datasets: [{
                        label: 'Commandes',
                        data: ordersPerMonth,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#2271b1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    };

    $(document).ready(function() {
        if ($('.pmp-admin-wrap').length) {
            PMP_Charts.init();
        }
    });

})(jQuery);
