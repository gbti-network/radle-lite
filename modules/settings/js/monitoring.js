var $ = jQuery.noConflict();

// Initialize debugger for this file
const RadleMonitoring = {
    chartContainer: null,
    chart: null,
    currentPeriod: 'last-hour',
    debug: new RadleDebugger('monitoring.js', false),  

    init: function() {
        this.debug.log('Initializing RadleMonitoring module');
        this.chartContainer = document.getElementById('radle-rate-limit-chart');
        if (!this.chartContainer) {
            this.debug.warn('Chart container not found');
            return;
        }
        this.debug.log('Chart container found, binding events');
        this.bindEvents();
    },

    bindEvents: function() {
        this.debug.log('Binding monitoring events...');
        
        const eventBindings = {
            'radle-graph-last-hour': 'last-hour',
            'radle-graph-24h': '24h',
            'radle-graph-7d': '7d',
            'radle-graph-30d': '30d'
        };

        for (const [elementId, period] of Object.entries(eventBindings)) {
            const element = document.getElementById(elementId);
            if (element) {
                this.debug.log(`Adding click listener for ${period} period`);
                element.addEventListener('click', () => this.fetchData(period));
            } else {
                this.debug.warn(`Element not found: ${elementId}`);
            }
        }

        const refreshButton = document.getElementById('radle-graph-refresh');
        if (refreshButton) {
            this.debug.log('Adding refresh button listener');
            refreshButton.addEventListener('click', () => {
                this.debug.log('Refreshing data for current period:', this.currentPeriod);
                this.fetchData(this.currentPeriod);
            });
        }

        const deleteButton = document.getElementById('radle-graph-delete-data');
        if (deleteButton) {
            this.debug.log('Adding delete data button listener');
            deleteButton.addEventListener('click', () => this.deleteAllData());
        }

        // Listen for tab changes
        jQuery('.nav-tab-wrapper .nav-tab').on('click', (e) => {
            if (e.target.getAttribute('href').includes('monitoring')) {
                this.debug.log('Monitoring tab selected');
                setTimeout(() => {
                    this.initializeChart();
                    this.toggleSaveButton(false);
                }, 100);
            } else {
                this.debug.log('Different tab selected, showing save button');
                this.toggleSaveButton(true);
            }
        });

        if (window.location.href.includes('monitoring')) {
            this.debug.log('Monitoring page loaded directly, hiding save button');
            this.toggleSaveButton(false);
        }
    },
    toggleSaveButton: function(show) {
        const submitButton = document.getElementById('submit');
        if (submitButton) {
            submitButton.style.display = show ? 'block' : 'none';
        }
    },

    initializeChart: function() {
        if (this.chart) {
            this.chart.destroy();
        }

        if (this.chartContainer.offsetParent === null) {
            // Chart is not visible, defer initialization with a timeout
            setTimeout(() => {
                this.initializeChart();
            }, 250);  // Check again after 250ms
            return;
        }

        // Fetch data first, then initialize chart
        this.fetchData(this.currentPeriod);
    },

    fetchData: function(period) {
        this.debug.log(`Fetching data for period: ${period}`);
        this.currentPeriod = period;
        this.showLoader();

        fetch(`${radleMonitoring.root}radle/v1/rate-limit-data?period=${period}`, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': radleMonitoring.nonce
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data && data.length > 0) {
                    this.debug.log(`Received ${data.length} data points for ${period}`);
                    this.prepareAndCreateChart(data);
                } else {
                    this.debug.warn(`No data available for period: ${period}`);
                    this.displayErrorMessage('No data available for chart.');
                }
                this.hideLoader();
            })
            .catch(error => {
                this.debug.error('Error fetching rate limit data:', error);
                this.displayErrorMessage(error.message);
                this.hideLoader();
            });
    },
    prepareAndCreateChart: function(data) {
        // Ensure the chart is destroyed if it exists
        if (this.chart) {
            this.chart.destroy();
        }

        // Process chart data and options before chart creation
        const chartData = this.processChartData(data);
        const chartOptions = this.getChartOptions(chartData); // Pass chartData to ensure options are prepared

        // Create the chart only after data and options are fully prepared
        const ctx = this.chartContainer.getContext('2d');
        this.chart = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: chartOptions
        });

        this.updateButtonStyles();
    },
    showLoader: function() {
        this.debug.log('Showing loader');
        const loader = document.createElement('div');
        loader.id = 'radle-chart-loader';
        loader.innerHTML = '<span class="dashicons dashicons-update-alt spin"></span>';
        this.chartContainer.parentNode.insertBefore(loader, this.chartContainer);
    },
    hideLoader: function() {
        this.debug.log('Hiding loader');
        const loader = document.getElementById('radle-chart-loader');
        if (loader) {
            loader.remove();
        }
    },
    processChartData: function(data) {
        const labels = this.getLabels();
        const maxCalls = Math.max(...data.map(item => item.calls));
        const breachScale = 1;  // No scaling down breaches since we've already adjusted the axis

        const datasets = [
            {
                label: radleMonitoring.i18n.numberOfApiCalls,
                data: data.map(item => item.calls),
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                yAxisID: 'y-axis-calls'
            },
            {
                label: radleMonitoring.i18n.breachesOf90CallsPerMin,
                data: data.map(item => item.breaches),
                backgroundColor: 'rgba(255, 206, 86, 0.6)',
                yAxisID: 'y-axis-breaches'
            },
            {
                label: radleMonitoring.i18n.failedCallsDueToRateLimits,
                data: data.map(item => item.failures),
                backgroundColor: 'rgba(255, 99, 132, 0.6)',
                yAxisID: 'y-axis-breaches'
            }
        ];

        return { labels, datasets };
    },

    getLabels: function() {
        let labels = [];
        switch (this.currentPeriod) {
            case 'last-hour':
                labels = Array.from({ length: 60 }, (_, i) => `Minute ${i + 1}`);
                break;
            case '24h':
                labels = Array.from({ length: 24 }, (_, i) => `Hour ${i + 1}`);
                break;
            case '7d':
                labels = Array.from({ length: 7 }, (_, i) => `Day ${i + 1}`);
                break;
            case '30d':
                labels = Array.from({ length: 30 }, (_, i) => `Day ${i + 1}`);
                break;
        }
        return labels;
    },


    getChartOptions: function(chartData) {
        // Prepare options based on chartData
        const maxCalls = Math.max(...chartData.datasets[0].data);

        return {
            responsive: true,
            scales: {
                x: {
                    // Existing x-axis configuration
                },
                'y-axis-calls': {
                    type: 'linear',
                    position: 'left',
                    title: {
                        display: true,
                        text: radleMonitoring.i18n.numberOfCalls
                    },
                    ticks: {
                        beginAtZero: true
                    }
                },
                'y-axis-breaches': {
                    type: 'linear',
                    position: 'right',
                    title: {
                        display: true,
                        text: radleMonitoring.i18n.breachesAndFailures
                    },
                    ticks: {
                        beginAtZero: true,
                        max: maxCalls, // Set equal to the number of calls
                        stepSize: maxCalls / 5, // Granularity based on calls
                        callback: function(value) {
                            return value.toFixed(0); // Display as integer
                        }
                    },
                    grid: {
                        drawOnChartArea: false // Keep breaches and failures separate from the number of calls grid
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += context.parsed.y.toFixed(0); // Show as integer
                            }
                            return label;
                        }
                    }
                }
            }
        };
    },

    updateButtonStyles: function() {
        const buttons = ['last-hour', '24h', '7d', '30d'];
        buttons.forEach(period => {
            const button = document.getElementById(`radle-graph-${period}`);
            if (button) {
                button.classList.toggle('button-primary', period === this.currentPeriod);
            }
        });
    },

    deleteAllData: function() {
        if (confirm(radleMonitoring.i18n.confirmDeleteData)) {
            fetch(`${radleMonitoring.root}radle/v1/rate-limit-data`, {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': radleMonitoring.nonce
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(radleMonitoring.i18n.dataDeleted);
                        this.fetchData(this.currentPeriod);
                    } else {
                        alert(radleMonitoring.i18n.errorDeletingData);
                    }
                })
                .catch(error => {
                    this.debug.error('Error deleting rate limit data:', error);
                    alert(radleMonitoring.i18n.errorDeletingData);
                });
        }
    },

    displayErrorMessage: function(message) {
        this.debug.warn(`Displaying error message: ${message}`);
        const errorDiv = document.createElement('div');
        errorDiv.className = 'notice notice-error';
        errorDiv.innerHTML = `<p>${message}</p>`;
        this.chartContainer.parentNode.insertBefore(errorDiv, this.chartContainer);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    RadleMonitoring.init();
});