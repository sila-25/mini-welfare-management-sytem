/**
 * Charts Configuration for Careway Welfare Management System
 */

// Chart color palette
const chartColors = {
    primary: '#667eea',
    secondary: '#764ba2',
    success: '#48bb78',
    danger: '#f56565',
    warning: '#ed8936',
    info: '#4299e1',
    dark: '#1a202c',
    light: '#e2e8f0',
    
    gradient: {
        start: '#667eea',
        end: '#764ba2'
    }
};

// Common chart options
const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                boxWidth: 10,
                font: {
                    size: 12
                }
            }
        },
        tooltip: {
            backgroundColor: '#1a202c',
            titleColor: '#ffffff',
            bodyColor: '#e2e8f0',
            borderColor: chartColors.primary,
            borderWidth: 1,
            callbacks: {
                label: function(context) {
                    let label = context.dataset.label || '';
                    if (label) {
                        label += ': ';
                    }
                    if (context.parsed.y !== undefined) {
                        label += 'KES ' + context.parsed.y.toLocaleString();
                    }
                    return label;
                }
            }
        }
    }
};

// Create line chart
function createLineChart(ctx, labels, data, label, color = chartColors.primary) {
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                borderColor: color,
                backgroundColor: color + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: color,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'KES ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Create bar chart
function createBarChart(ctx, labels, datasets) {
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            ...commonOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'KES ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Create pie chart
function createPieChart(ctx, labels, data, colors = null) {
    const defaultColors = [
        chartColors.primary,
        chartColors.success,
        chartColors.warning,
        chartColors.danger,
        chartColors.info,
        chartColors.secondary
    ];
    
    return new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors || defaultColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: KES ${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Create doughnut chart
function createDoughnutChart(ctx, labels, data, colors = null) {
    const defaultColors = [
        chartColors.success,
        chartColors.danger,
        chartColors.warning
    ];
    
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors || defaultColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: KES ${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// ApexCharts configurations
class ApexChartsManager {
    constructor() {
        this.charts = {};
    }
    
    // Create area chart
    createAreaChart(elementId, options) {
        const defaultOptions = {
            chart: {
                type: 'area',
                height: 350,
                toolbar: {
                    show: false
                },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.3
                }
            },
            xaxis: {
                categories: options.categories || [],
                labels: {
                    rotate: -45,
                    style: {
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    formatter: function(value) {
                        return 'KES ' + value.toLocaleString();
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function(value) {
                        return 'KES ' + value.toLocaleString();
                    }
                }
            },
            colors: [chartColors.primary]
        };
        
        const config = { ...defaultOptions, ...options };
        this.charts[elementId] = new ApexCharts(document.querySelector('#' + elementId), config);
        this.charts[elementId].render();
        
        return this.charts[elementId];
    }
    
    // Create bar chart (ApexCharts)
    createBarChart(elementId, options) {
        const defaultOptions = {
            chart: {
                type: 'bar',
                height: 350,
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    borderRadius: 10,
                    dataLabels: {
                        position: 'top'
                    }
                }
            },
            dataLabels: {
                enabled: true,
                offsetY: -20,
                formatter: function(value) {
                    return 'KES ' + value.toLocaleString();
                }
            },
            xaxis: {
                categories: options.categories || [],
                labels: {
                    rotate: -45,
                    style: {
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    formatter: function(value) {
                        return 'KES ' + value.toLocaleString();
                    }
                }
            },
            colors: [chartColors.primary, chartColors.success, chartColors.warning],
            grid: {
                borderColor: '#e2e8f0',
                strokeDashArray: 5
            }
        };
        
        const config = { ...defaultOptions, ...options };
        this.charts[elementId] = new ApexCharts(document.querySelector('#' + elementId), config);
        this.charts[elementId].render();
        
        return this.charts[elementId];
    }
    
    // Update chart data
    updateChart(elementId, data) {
        if (this.charts[elementId]) {
            this.charts[elementId].updateSeries(data);
        }
    }
    
    // Destroy chart
    destroyChart(elementId) {
        if (this.charts[elementId]) {
            this.charts[elementId].destroy();
            delete this.charts[elementId];
        }
    }
}

// Initialize ApexCharts manager
const apexCharts = new ApexChartsManager();

// Contribution trends chart
function createContributionTrendsChart(ctx, data) {
    return createLineChart(ctx, data.labels, data.values, 'Contributions', chartColors.success);
}

// Loan repayment chart
function createLoanRepaymentChart(ctx, data) {
    return createBarChart(ctx, data.labels, [
        {
            label: 'Principal Paid',
            data: data.principal,
            backgroundColor: chartColors.primary,
            borderRadius: 10
        },
        {
            label: 'Interest Paid',
            data: data.interest,
            backgroundColor: chartColors.warning,
            borderRadius: 10
        }
    ]);
}

// Portfolio distribution chart
function createPortfolioChart(ctx, data) {
    return createPieChart(ctx, data.labels, data.values);
}

// Attendance chart
function createAttendanceChart(ctx, data) {
    return createDoughnutChart(ctx, ['Present', 'Absent', 'Excused'], [data.present, data.absent, data.excused]);
}

// Monthly summary chart
function createMonthlySummaryChart(ctx, data) {
    return createBarChart(ctx, data.months, [
        {
            label: 'Contributions',
            data: data.contributions,
            backgroundColor: chartColors.success,
            borderRadius: 10
        },
        {
            label: 'Loan Disbursements',
            data: data.disbursements,
            backgroundColor: chartColors.danger,
            borderRadius: 10
        },
        {
            label: 'Loan Repayments',
            data: data.repayments,
            backgroundColor: chartColors.info,
            borderRadius: 10
        }
    ]);
}

// Initialize all charts on page
function initializeCharts() {
    // Check for contribution trends chart
    if ($('#contributionTrendsChart').length) {
        const ctx = document.getElementById('contributionTrendsChart').getContext('2d');
        // Data would be loaded via AJAX
    }
    
    // Check for loan repayment chart
    if ($('#loanRepaymentChart').length) {
        const ctx = document.getElementById('loanRepaymentChart').getContext('2d');
        // Data would be loaded via AJAX
    }
    
    // Check for portfolio chart
    if ($('#portfolioChart').length) {
        const ctx = document.getElementById('portfolioChart').getContext('2d');
        // Data would be loaded via AJAX
    }
}