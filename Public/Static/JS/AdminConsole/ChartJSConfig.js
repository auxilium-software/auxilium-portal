
(function() {
    'use strict';

    const chartInstances = {};

    const AdminChartColours = {
        // primary colours
        primary: '#1941a5',
        primaryLight: '#3168d5',
        primaryLighter: '#4a7ae2',

        // status colours
        success: '#3d8b3d',
        successLight: '#5cb85c',
        warning: '#c87f0a',
        warningLight: '#f0ad4e',
        danger: '#ac2925',
        dangerLight: '#d9534f',
        info: '#28a4c9',
        infoLight: '#5bc0de',

        // neutral colours
        gray: '#808080',
        grayLight: '#d4d0c8',
        grayLighter: '#ece9d8',
        text: '#000000',
        textMuted: '#666666',
        background: '#ffffff',

        // chart-specific colours
        gridLines: '#d0d0d0',
        gridLinesDark: '#808080',

        // dataset colours (for multi-series charts)
        datasets: [
            '#1941a5', // Primary blue
            '#3d8b3d', // Green
            '#c87f0a', // Orange
            '#ac2925', // Red
            '#28a4c9', // Cyan
            '#6f42c1', // Purple
            '#fd7e14', // Bright orange
            '#20c997', // Teal
        ],

        // dataset colours (for multi-series charts) - with transparency values
        datasetsAlpha: [
            'rgba(25, 65, 165, 0.7)',
            'rgba(61, 139, 61, 0.7)',
            'rgba(200, 127, 10, 0.7)',
            'rgba(172, 41, 37, 0.7)',
            'rgba(40, 164, 201, 0.7)',
            'rgba(111, 66, 193, 0.7)',
            'rgba(253, 126, 20, 0.7)',
            'rgba(32, 201, 151, 0.7)',
        ],
    };

    const AdminChartDefaults = {
        // shared defaults for all chart types
        global: {
            responsive: true,
            maintainAspectRatio: true,

            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        font: {
                            family: 'Tahoma, "Segoe UI", Verdana, sans-serif',
                            size: 10,
                        },
                        color: AdminChartColours.text,
                        boxWidth: 12,
                        boxHeight: 12,
                        padding: 12,
                        usePointStyle: false,
                    },
                },

                tooltip: {
                    enabled: true,
                    backgroundColor: '#ffffcc',
                    titleColor: '#003399',
                    bodyColor: '#000000',
                    borderColor: '#808080',
                    borderWidth: 1,
                    titleFont: {
                        family: 'Tahoma, "Segoe UI", Verdana, sans-serif',
                        size: 11,
                        weight: 'bold',
                    },
                    bodyFont: {
                        family: 'Tahoma, "Segoe UI", Verdana, sans-serif',
                        size: 10,
                    },
                    padding: 8,
                    cornerRadius: 0,
                    displayColors: true,
                    boxWidth: 8,
                    boxHeight: 8,
                    boxPadding: 4,
                },

                title: {
                    display: false, // we're using `.chart-container__header` instead
                    font: {
                        family: 'Tahoma, "Segoe UI", Verdana, sans-serif',
                        size: 12,
                        weight: 'bold',
                    },
                    color: '#003399',
                },
            },

            animation: {
                duration: 500,
                easing: 'easeOutQuart',
            },
        },

        // line chart specifics
        line: {
            tension: 0.3, // slight curve
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#ffffff',
            pointBorderWidth: 2,
            fill: true,

            scales: {
                x: {
                    grid: {
                        color: AdminChartColours.gridLines,
                        lineWidth: 1,
                        drawTicks: true,
                        tickLength: 4,
                    },
                    ticks: {
                        font: {
                            family: 'Tahoma, "Segoe UI", Verdana, sans-serif',
                            size: 9,
                        },
                        color: AdminChartColours.textMuted,
                    },
                    border: {
                        color: AdminChartColours.gridLinesDark,
                        width: 2,
                    },
                },
                y: {
                    grid: {
                        color: AdminChartColours.gridLines,
                        lineWidth: 1,
                    },
                    ticks: {
                        font: {
                            family: 'Tahoma, "Segoe UI", Verdana, sans-serif',
                            size: 9,
                        },
                        color: AdminChartColours.textMuted,
                    },
                    border: {
                        color: AdminChartColours.gridLinesDark,
                        width: 2,
                    },
                    beginAtZero: true,
                },
            },
        },

        // bar chart specifics
        bar: {
            borderWidth: 1,
            borderRadius: 0,

            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        font: {
                            family: 'Tahoma, "Segoe UI", Verdana, sans-serif',
                            size: 9,
                        },
                        color: AdminChartColours.textMuted,
                    },
                    border: {
                        color: AdminChartColours.gridLinesDark,
                        width: 2,
                    },
                },
                y: {
                    grid: {
                        color: AdminChartColours.gridLines,
                        lineWidth: 1,
                    },
                    ticks: {
                        font: {
                            family: 'Tahoma, "Segoe UI", Verdana, sans-serif',
                            size: 9,
                        },
                        color: AdminChartColours.textMuted,
                    },
                    border: {
                        color: AdminChartColours.gridLinesDark,
                        width: 2,
                    },
                    beginAtZero: true,
                },
            },
        },

        // pie/doughnut chart specifics
        pie: {
            borderWidth: 1,
            borderColor: '#ffffff',
            hoverOffset: 8,
        },

        doughnut: {
            borderWidth: 1,
            borderColor: '#ffffff',
            hoverOffset: 8,
            cutout: '50%',
        },

        // radar chart specifics
        radar: {
            borderWidth: 2,
            pointRadius: 4,
            pointBackgroundColor: '#ffffff',
            pointBorderWidth: 2,

            scales: {
                r: {
                    grid: {
                        color: AdminChartColours.gridLines,
                    },
                    angleLines: {
                        color: AdminChartColours.gridLines,
                    },
                    pointLabels: {
                        font: {
                            family: 'Tahoma, "Segoe UI", Verdana, sans-serif',
                            size: 9,
                        },
                        color: AdminChartColours.textMuted,
                    },
                    ticks: {
                        font: {
                            family: 'Tahoma, "Segoe UI", Verdana, sans-serif',
                            size: 8,
                        },
                        color: AdminChartColours.textMuted,
                        backdropColor: 'transparent',
                    },
                },
            },
        },

        // polar area chart specifics
        polarArea: {
            borderWidth: 1,
            borderColor: '#ffffff',
        },
    };

    /**
     * Get a colour from the palette.
     * @param {number} index - Index of the color.
     * @param {boolean} withAlpha - Return with transparency.
     * @return {string} The colour's hex code.
     */
    function getColour(index, withAlpha = false)
    {
        const colours = withAlpha
            ? AdminChartColours.datasetsAlpha
            : AdminChartColours.datasets;
        return colours[index % colours.length];
    }

    /**
     * Generate gradient for area/line fills.
     * @param {CanvasRenderingContext2D} ctx - Canvas context.
     * @param {string} colour - Base colour.
     */
    function createGradient(ctx, colour)
    {
        const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
        gradient.addColorStop(0, colour.replace(')', ', 0.5)').replace('rgb', 'rgba'));
        gradient.addColorStop(1, colour.replace(')', ', 0.0)').replace('rgb', 'rgba'));
        return gradient;
    }

    /**
     * Merge user options with admin defaults.
     * @param {string} chartType - Type of chart (line, bar, pie, etc...).
     * @param {object} userOptions - User-provided options.
     */
    function mergeOptions(chartType, userOptions = {})
    {
        const defaults = {
            ...AdminChartDefaults.global,
            ...AdminChartDefaults[chartType],
        };

        return deepMerge(defaults, userOptions);
    }

    /**
     * Deep merge utility
     */
    function deepMerge(target, source)
    {
        const result = { ...target };

        for (const key in source)
        {
            if (source[key] instanceof Object && key in target)
            {
                result[key] = deepMerge(target[key], source[key]);
            }
            else
            {
                result[key] = source[key];
            }
        }

        return result;
    }

    function destroyChart(canvas)
    {
        const id = typeof canvas === 'string' ? canvas : canvas.id;
        if (id && chartInstances[id]) {
            chartInstances[id].destroy();
            delete chartInstances[id];
        }
    }
    function createChart(canvas, type, data, options = {})
    {
        destroyChart(canvas);

        const ctx = typeof canvas === 'string'
            ? document.getElementById(canvas).getContext('2d')
            : canvas.getContext('2d');

        // auto-apply colours if not specified
        if (data.datasets)
        {
            data.datasets.forEach((dataset, i) => {
                if (!dataset.backgroundColor) {
                    if (type === 'line')
                    {
                        dataset.backgroundColor = getColour(i, true);
                        dataset.borderColor = getColour(i);
                        dataset.pointBorderColor = getColour(i);
                    }
                    else if (type === 'bar')
                    {
                        dataset.backgroundColor = getColour(i, true);
                        dataset.borderColor = getColour(i);
                    }
                    else if (type === 'pie' || type === 'doughnut' || type === 'polarArea')
                    {
                        dataset.backgroundColor = AdminChartColours.datasetsAlpha;
                        dataset.borderColor = AdminChartColours.datasets;
                    }
                    else if (type === 'radar')
                    {
                        dataset.backgroundColor = getColour(i, true);
                        dataset.borderColor = getColour(i);
                        dataset.pointBorderColor = getColour(i);
                    }
                }
            });
        }

        const mergedOptions = mergeOptions(type, options);

        const chart = new Chart(ctx, {
            type: type,
            data: data,
            options: mergedOptions,
        });

        // track the instance
        const id = typeof canvas === 'string' ? canvas : canvas.id;
        if (id) chartInstances[id] = chart;

        return chart;
    }

    // ============================================
    // shorthand chart creators
    // ============================================
    function createLineChart(canvas, labels, datasets, options = {})
    {
        return createChart(canvas, 'line', { labels, datasets }, options);
    }

    function createBarChart(canvas, labels, datasets, options = {})
    {
        return createChart(canvas, 'bar', { labels, datasets }, options);
    }

    function createPieChart(canvas, labels, data, options = {})
    {
        return createChart(canvas, 'pie', {
            labels,
            datasets: [{ data }],
        }, options);
    }

    function createDoughnutChart(canvas, labels, data, options = {})
    {
        return createChart(canvas, 'doughnut', {
            labels,
            datasets: [{ data }],
        }, options);
    }

    function createRadarChart(canvas, labels, datasets, options = {})
    {
        return createChart(canvas, 'radar', { labels, datasets }, options);
    }

    // ============================================
    // apply global Chart.js defaults
    // ============================================
    if (typeof Chart !== 'undefined')
    {
        // set global font
        Chart.defaults.font.family = 'Tahoma, "Segoe UI", Verdana, sans-serif';
        Chart.defaults.font.size = 10;
        Chart.defaults.color = AdminChartColours.text;

        // disable default legend in favor of our custom HTML legends
        // Chart.defaults.plugins.legend.display = false;
    }

    // ============================================
    // export to global scope
    // ============================================
    window.AdminChart = {
        colors: AdminChartColours,
        defaults: AdminChartDefaults,

        // core functions
        create: createChart,
        getColor: getColour,
        createGradient: createGradient,
        mergeOptions: mergeOptions,

        // shorthand creators
        line: createLineChart,
        bar: createBarChart,
        pie: createPieChart,
        doughnut: createDoughnutChart,
        radar: createRadarChart,
    };

})();
