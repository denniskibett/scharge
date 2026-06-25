import ApexCharts from "apexcharts";

let isInitialized = false;
let chartInstances = {};

const accountantCharts = () => {
    // Prevent multiple initializations
    if (isInitialized) {
        console.log('Charts already initialized, skipping...');
        return;
    }
    
    console.log('Initializing accountant charts...');
    
    // Get dynamic colors from CSS variables
    const primaryColor = getComputedStyle(document.documentElement)
        .getPropertyValue('--primary-color').trim() || '#465fff';
    const secondaryColor = getComputedStyle(document.documentElement)
        .getPropertyValue('--secondary-color').trim() || '#10B981';
    const warningColor = getComputedStyle(document.documentElement)
        .getPropertyValue('--warning-color').trim() || '#F59E0B';
    const errorColor = getComputedStyle(document.documentElement)
        .getPropertyValue('--error-color').trim() || '#EF4444';
    const successColor = getComputedStyle(document.documentElement)
        .getPropertyValue('--success-color').trim() || '#10B981';

    // Log raw data for debugging
    console.log('Chart Data Debug:');
    const revenueElement = document.querySelector("#revenueBarChart");
    if (revenueElement) {
        console.log('Revenue dates:', revenueElement.dataset.dates);
        console.log('Revenue counts:', revenueElement.dataset.counts);
    }

    // Initialize all charts
    try {
        initRevenueBarChart(primaryColor);
        initPaymentDoughnutChart(primaryColor, secondaryColor, warningColor, errorColor);
        initRevenueExpenseLineChart(primaryColor, secondaryColor, errorColor);
        initInvoiceStatusPieChart(successColor, errorColor, warningColor);
        initCollectionRateRadialChart(successColor);
        initPerformanceRadarChart(primaryColor);
        initAgingReportChart(warningColor);

        isInitialized = true;
        console.log('All charts initialized successfully!');
    } catch (error) {
        console.error('Error initializing charts:', error);
    }
    
    // Handle period filter changes
    document.querySelectorAll('.chart-period-filter').forEach(select => {
        select.addEventListener('change', async function(e) {
            const interval = e.target.value;
            const chartId = this.dataset.chart;
            const chartElement = document.getElementById(chartId);
            
            if (chartInstances[chartId]) {
                chartElement.style.opacity = '0.5';
            }
            
            try {
                const response = await fetch(`/dashboard/chart-data?type=${chartId}&interval=${interval}`);
                const data = await response.json();
                
                if (data.success && data.chartData) {
                    const chart = chartInstances[chartId];
                    if (chart && chart.updateChartData) {
                        chart.updateChartData(
                            data.chartData.dates || data.chartData.labels,
                            data.chartData.counts || data.chartData.values
                        );
                        // Update data attributes
                        Object.keys(data.chartData).forEach(key => {
                            chartElement.setAttribute(`data-${key}`, JSON.stringify(data.chartData[key]));
                        });
                    }
                }
            } catch (error) {
                console.error('Error fetching chart data:', error);
            } finally {
                if (chartInstances[chartId]) {
                    setTimeout(() => {
                        chartElement.style.opacity = '1';
                    }, 200);
                }
            }
        });
    });
};

// Helper function to calculate Y-axis max
const calculateYAxisMax = (max) => {
    if (max === 0) return 5;
    
    const magnitude = Math.pow(10, Math.floor(Math.log10(max)));
    const normalized = max / magnitude;
    
    let step;
    if (normalized <= 1) step = 1;
    else if (normalized <= 2) step = 2;
    else if (normalized <= 5) step = 5;
    else step = 10;
    
    return Math.ceil(max / (step * magnitude)) * (step * magnitude);
};

// Helper to safely parse data
const safeParseData = (data) => {
    if (!data || data === '[]' || data === '' || data === 'null' || data === 'undefined') return [];
    try {
        const parsed = JSON.parse(data);
        // Convert string numbers to actual numbers
        if (Array.isArray(parsed)) {
            return parsed.map(item => {
                if (typeof item === 'string' && !isNaN(item)) {
                    return parseFloat(item);
                }
                return item;
            });
        }
        return parsed;
    } catch (e) {
        console.warn('Error parsing data:', data, e);
        return [];
    }
};

function initRevenueBarChart(primaryColor) {
    const chartElement = document.querySelector("#revenueBarChart");
    if (!chartElement) {
        console.warn('revenueBarChart element not found');
        return;
    }

    // Safely parse data
    let dates = safeParseData(chartElement.dataset.dates);
    let counts = safeParseData(chartElement.dataset.counts);

    // Ensure counts are numbers
    if (!Array.isArray(counts)) {
        counts = [];
    }
    counts = counts.map(val => typeof val === 'string' ? parseFloat(val) || 0 : val || 0);

    console.log('Revenue chart data after parse:', { dates, counts });

    // Check if we have valid data
    const hasValidData = dates.length > 0 && counts.length > 0 && counts.some(v => v > 0);
    
    if (!hasValidData) {
        chartElement.innerHTML = '<div class="text-center text-gray-500 py-10">No revenue data available</div>';
        return;
    }

    // If only one data point, add a dummy point to make the bar visible
    if (dates.length === 1) {
        dates = [dates[0], dates[0]];
        counts = [counts[0], counts[0]];
    }

    const maxCount = Math.max(...counts, 1);
    const yAxisMax = calculateYAxisMax(maxCount);

    // Set full width and responsive height
    chartElement.style.width = '100%';
    chartElement.style.height = '320px';

    const options = {
        series: [{ name: "Revenue", data: counts }],
        colors: [primaryColor],
        chart: {
            fontFamily: "Outfit, sans-serif",
            type: "bar",
            height: 320,
            width: '100%',
            toolbar: { show: false },
            animations: { enabled: true, speed: 800 },
            background: 'transparent'
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: "39%",
                borderRadius: 5,
                borderRadiusApplication: "end",
                dataLabels: {
                    position: 'top',
                },
            }
        },
        dataLabels: {
            enabled: true,
            offsetY: -20,
            style: {
                fontSize: '12px',
                fontFamily: 'Outfit, sans-serif',
                colors: ['#333'],
                fontWeight: '600',
            },
            formatter: function(val) {
                return 'KES ' + Number(val).toLocaleString();
            }
        },
        xaxis: {
            categories: dates,
            axisBorder: { show: true, color: '#e5e7eb' },
            axisTicks: { show: true, color: '#e5e7eb' },
            labels: {
                style: {
                    fontSize: "12px",
                    fontFamily: 'Outfit, sans-serif',
                    colors: '#64748b',
                },
                rotate: -15,
                trim: true,
                hideOverlappingLabels: true,
            }
        },
        yaxis: {
            min: 0,
            max: yAxisMax,
            tickAmount: 5,
            forceNiceScale: true,
            decimalsInFloat: 0,
            title: {
                text: "Revenue (KES)",
                style: {
                    fontSize: "13px",
                    fontFamily: 'Outfit, sans-serif',
                    fontWeight: 500,
                    color: '#64748b'
                }
            },
            labels: {
                formatter: (value) => {
                    return Math.round(value).toString();
                },
                style: {
                    fontSize: "12px",
                    fontFamily: 'Outfit, sans-serif',
                    colors: '#64748b',
                },
                align: 'right',
                padding: 5
            },
            axisBorder: { show: true, color: '#e5e7eb' },
            axisTicks: { show: true, color: '#e5e7eb' },
            crosshairs: {
                show: true,
                position: 'back',
                stroke: {
                    color: '#b6b6b6',
                    width: 1,
                    dashArray: 3,
                }
            }
        },
        grid: {
            show: true,
            borderColor: "#e5e7eb",
            strokeDashArray: 5,
            position: "back",
            xaxis: {
                lines: {
                    show: false,
                },
            },
            yaxis: {
                lines: {
                    show: true,
                },
            },
            padding: {
                left: 10,
                right: 10,
            }
        },
        legend: {
            show: true,
            position: "top",
            horizontalAlign: "left",
            fontFamily: "Outfit, sans-serif",
        },
        tooltip: {
            y: {
                formatter: (val) => 'KES ' + Number(val).toLocaleString(),
            },
        },
        states: {
            hover: {
                filter: {
                    type: 'lighten',
                    value: 0.1,
                }
            }
        },
        responsive: [{
            breakpoint: 768,
            options: {
                chart: {
                    height: 280,
                },
                plotOptions: {
                    bar: {
                        columnWidth: "50%",
                    }
                },
                dataLabels: {
                    style: {
                        fontSize: '10px',
                    }
                }
            }
        }]
    };

    if (chartInstances.revenue) chartInstances.revenue.destroy();
    chartInstances.revenue = new ApexCharts(chartElement, options);
    chartInstances.revenue.render();
    console.log('Revenue chart rendered!');

    chartInstances.revenue.updateChartData = (newDates, newCounts) => {
        let updatedDates = [...newDates];
        let updatedCounts = Array.isArray(newCounts) ? newCounts.map(v => typeof v === 'string' ? parseFloat(v) || 0 : v || 0) : [];
        
        if (updatedDates.length === 1) {
            updatedDates = [updatedDates[0], updatedDates[0]];
            updatedCounts = [updatedCounts[0], updatedCounts[0]];
        }
        const newMaxCount = Math.max(...updatedCounts, 1);
        const newYAxisMax = calculateYAxisMax(newMaxCount);
        chartInstances.revenue.updateOptions({
            xaxis: {
                categories: updatedDates,
            },
            yaxis: {
                max: newYAxisMax,
                labels: {
                    formatter: (value) => {
                        return Math.round(value).toString();
                    }
                }
            }
        });
        chartInstances.revenue.updateSeries([{ name: "Revenue", data: updatedCounts }]);
    };
}

function initPaymentDoughnutChart(primaryColor, secondaryColor, warningColor, errorColor) {
    const element = document.getElementById('paymentDoughnutChart');
    if (!element) return;

    let labels = safeParseData(element.dataset.labels);
    let values = safeParseData(element.dataset.values);

    // Ensure values are numbers
    if (!Array.isArray(values)) {
        values = [];
    }
    values = values.map(v => typeof v === 'string' ? parseFloat(v) || 0 : v || 0);

    console.log('Payment chart data:', { labels, values });

    if (!labels.length || !values.length || !values.some(v => v > 0)) {
        element.innerHTML = '<div class="text-center text-gray-500 py-10">No payment data available</div>';
        return;
    }

    // Use dynamic colors for payment methods
    const palette = [primaryColor, secondaryColor, warningColor, errorColor, '#8B5CF6', '#EC4899', '#14B8A6', '#F97316'];

    // Ensure the chart container takes full width
    element.style.width = '100%';
    element.style.height = '250px';

    const options = {
        series: values,
        colors: palette.slice(0, labels.length),
        chart: { 
            type: 'donut', 
            height: 250,
            width: '100%',
            toolbar: { show: false },
            fontFamily: "Outfit, sans-serif",
            background: 'transparent'
        },
        labels: labels,
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            fontSize: '11px',
            fontFamily: 'Outfit, sans-serif',
            labels: { colors: '#64748b' },
            itemMargin: {
                horizontal: 5,
                vertical: 2
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function(val, opts) {
                return 'KES ' + Number(opts.w.globals.series[opts.seriesIndex]).toLocaleString();
            },
            style: {
                fontSize: '11px',
                fontFamily: 'Outfit, sans-serif',
                fontWeight: '600'
            }
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total',
                            fontSize: '13px',
                            fontFamily: 'Outfit, sans-serif',
                            fontWeight: 600,
                            color: '#64748b',
                            formatter: function(w) {
                                return 'KES ' + Number(w.globals.seriesTotals.reduce((a, b) => a + b, 0)).toLocaleString();
                            }
                        }
                    }
                }
            }
        },
        tooltip: { 
            y: { 
                formatter: function(val) { 
                    return 'KES ' + Number(val).toLocaleString(); 
                } 
            },
            style: {
                fontSize: '12px',
                fontFamily: 'Outfit, sans-serif'
            }
        },
        responsive: [{
            breakpoint: 768,
            options: {
                chart: {
                    height: 220,
                },
                legend: {
                    fontSize: '10px',
                },
                dataLabels: {
                    style: {
                        fontSize: '10px',
                    }
                }
            }
        }]
    };

    if (chartInstances.payment) chartInstances.payment.destroy();
    chartInstances.payment = new ApexCharts(element, options);
    chartInstances.payment.render();
    console.log('Payment doughnut chart rendered!');
}

function initRevenueExpenseLineChart(primaryColor, secondaryColor, errorColor) {
    const element = document.getElementById('revenueExpenseLineChart');
    if (!element) return;

    let dates = safeParseData(element.dataset.dates);
    let revenueData = safeParseData(element.dataset.revenue);
    let expenseData = safeParseData(element.dataset.expenses);

    // Ensure values are numbers
    if (!Array.isArray(revenueData)) {
        revenueData = [];
    }
    if (!Array.isArray(expenseData)) {
        expenseData = [];
    }
    revenueData = revenueData.map(v => typeof v === 'string' ? parseFloat(v) || 0 : v || 0);
    expenseData = expenseData.map(v => typeof v === 'string' ? parseFloat(v) || 0 : v || 0);

    console.log('Revenue vs Expense data:', { dates, revenueData, expenseData });

    if (!dates.length || !revenueData.length) {
        element.innerHTML = '<div class="text-center text-gray-500 py-10">No revenue/expense data available</div>';
        return;
    }

    // Ensure the chart container takes full width
    element.style.width = '100%';
    element.style.height = '300px';

    const options = {
        series: [
            {
                name: 'Revenue',
                data: revenueData,
                color: secondaryColor
            },
            {
                name: 'Expenses',
                data: expenseData,
                color: errorColor
            }
        ],
        chart: {
            type: 'area',
            height: 300,
            width: '100%',
            toolbar: { 
                show: false,
                offsetX: 0,
                offsetY: 0,
            },
            animations: { 
                enabled: true, 
                speed: 800 
            },
            fontFamily: "Outfit, sans-serif",
            background: 'transparent',
            sparkline: {
                enabled: false
            }
        },
        stroke: { 
            curve: 'smooth', 
            width: 2.5
        },
        fill: {
            type: 'gradient',
            gradient: {
                enabled: true,
                opacityFrom: 0.55,
                opacityTo: 0,
            },
        },
        markers: { 
            size: 4, 
            colors: ['#fff'], 
            strokeColors: [secondaryColor, errorColor], 
            strokeWidth: 2,
            hover: { 
                size: 7,
                strokeWidth: 3
            },
            discrete: []
        },
        xaxis: {
            categories: dates,
            labels: {
                style: {
                    fontSize: '11px',
                    fontFamily: 'Outfit, sans-serif',
                    colors: '#64748b',
                    fontWeight: 400
                },
                rotate: -15,
                trim: true,
                hideOverlappingLabels: true,
                maxHeight: 40,
            },
            axisBorder: { 
                show: true, 
                color: '#e5e7eb' 
            },
            axisTicks: { 
                show: true, 
                color: '#e5e7eb' 
            },
            title: {
                text: "Date",
                style: {
                    fontSize: "12px",
                    fontFamily: 'Outfit, sans-serif',
                    fontWeight: 500,
                    color: '#64748b'
                }
            }
        },
        yaxis: {
            title: {
                text: "Amount (KES)",
                style: {
                    fontSize: "12px",
                    fontFamily: 'Outfit, sans-serif',
                    fontWeight: 500,
                    color: '#64748b'
                }
            },
            labels: {
                formatter: function(value) { 
                    if (value >= 1000000) {
                        return 'KES ' + (value / 1000000).toFixed(1) + 'M';
                    } else if (value >= 1000) {
                        return 'KES ' + (value / 1000).toFixed(1) + 'K';
                    }
                    return 'KES ' + Number(value).toLocaleString(); 
                },
                style: {
                    fontSize: '11px',
                    fontFamily: 'Outfit, sans-serif',
                    colors: '#64748b',
                },
                offsetX: -5,
                maxWidth: 80,
            },
            axisBorder: { 
                show: true, 
                color: '#e5e7eb' 
            },
            axisTicks: { 
                show: true, 
                color: '#e5e7eb' 
            },
            min: 0,
            tickAmount: 5,
            forceNiceScale: true,
            decimalsInFloat: 0,
        },
        grid: { 
            borderColor: '#e5e7eb', 
            strokeDashArray: 5,
            xaxis: {
                lines: {
                    show: false,
                },
            },
            yaxis: {
                lines: {
                    show: true,
                },
            },
            padding: {
                left: 0,
                right: 10,
            }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'left',
            fontSize: '12px',
            fontFamily: 'Outfit, sans-serif',
            labels: { 
                colors: '#64748b',
                useSeriesColors: false
            },
            markers: {
                width: 12,
                height: 12,
                radius: 2,
                strokeWidth: 0,
            },
            itemMargin: {
                horizontal: 10,
                vertical: 0
            }
        },
        tooltip: { 
            shared: true,
            intersect: false,
            y: { 
                formatter: function(val) { 
                    return 'KES ' + Number(val).toLocaleString(); 
                } 
            },
            style: {
                fontSize: '12px',
                fontFamily: 'Outfit, sans-serif'
            }
        },
        responsive: [{
            breakpoint: 768,
            options: {
                chart: {
                    height: 250,
                },
                yaxis: {
                    labels: {
                        style: {
                            fontSize: '10px',
                        }
                    }
                },
                xaxis: {
                    labels: {
                        style: {
                            fontSize: '10px',
                        },
                        rotate: -30,
                    }
                }
            }
        }]
    };

    // Destroy existing chart if it exists
    if (chartInstances.revenueExpense) {
        chartInstances.revenueExpense.destroy();
    }

    chartInstances.revenueExpense = new ApexCharts(element, options);
    chartInstances.revenueExpense.render();
    console.log('Revenue vs Expense chart rendered!');
}

function initInvoiceStatusPieChart(successColor, errorColor, warningColor) {
    const element = document.getElementById('invoiceStatusPieChart');
    if (!element) return;

    let labels = safeParseData(element.dataset.labels);
    let values = safeParseData(element.dataset.values);

    // Ensure values are numbers
    if (!Array.isArray(values)) {
        values = [];
    }
    values = values.map(v => typeof v === 'string' ? parseFloat(v) || 0 : v || 0);

    console.log('Invoice status data:', { labels, values });

    if (!labels.length || !values.length || !values.some(v => v > 0)) {
        element.innerHTML = '<div class="text-center text-gray-500 py-10">No invoice data available</div>';
        return;
    }

    // Use dynamic colors for invoice status
    const colorsMap = {
        'paid': successColor,
        'unpaid': errorColor,
        'partial': warningColor,
        'draft': '#94A3B8'
    };

    element.style.width = '100%';
    element.style.height = '250px';

    const options = {
        series: values,
        colors: labels.map(label => colorsMap[label.toLowerCase()] || '#94A3B8'),
        chart: { 
            type: 'pie', 
            height: 250,
            width: '100%',
            toolbar: { show: false },
            fontFamily: "Outfit, sans-serif",
            background: 'transparent'
        },
        labels: labels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            fontSize: '11px',
            fontFamily: 'Outfit, sans-serif',
            labels: { colors: '#64748b' },
            itemMargin: {
                horizontal: 5,
                vertical: 2
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function(val, opts) {
                return opts.w.globals.series[opts.seriesIndex];
            },
            style: {
                fontSize: '11px',
                fontFamily: 'Outfit, sans-serif',
                fontWeight: '600'
            }
        },
        tooltip: { 
            y: { 
                formatter: function(val) { 
                    return val + ' invoices'; 
                } 
            },
            style: {
                fontSize: '12px',
                fontFamily: 'Outfit, sans-serif'
            }
        },
        responsive: [{
            breakpoint: 768,
            options: {
                chart: {
                    height: 220,
                },
                legend: {
                    fontSize: '10px',
                },
                dataLabels: {
                    style: {
                        fontSize: '10px',
                    }
                }
            }
        }]
    };

    if (chartInstances.invoiceStatus) chartInstances.invoiceStatus.destroy();
    chartInstances.invoiceStatus = new ApexCharts(element, options);
    chartInstances.invoiceStatus.render();
    console.log('Invoice status chart rendered!');
}

function initCollectionRateRadialChart(successColor) {
    const element = document.getElementById('collectionRateRadialChart');
    if (!element) return;

    const value = parseFloat(element.dataset.value || '85');

    console.log('Collection rate value:', value);

    element.style.width = '100%';
    element.style.height = '250px';

    const options = {
        series: [value],
        colors: [successColor],
        chart: {
            type: 'radialBar',
            height: 250,
            width: '100%',
            toolbar: { show: false },
            offsetY: -10,
            fontFamily: "Outfit, sans-serif",
            background: 'transparent'
        },
        plotOptions: {
            radialBar: {
                startAngle: -135,
                endAngle: 135,
                hollow: {
                    margin: 0,
                    size: '70%',
                    background: 'transparent'
                },
                track: {
                    strokeWidth: '100%',
                    margin: 0,
                    background: '#e5e7eb'
                },
                dataLabels: {
                    name: {
                        show: true,
                        fontSize: '14px',
                        fontFamily: 'Outfit, sans-serif',
                        fontWeight: 500,
                        color: '#64748b',
                        offsetY: 10
                    },
                    value: { show: false }
                }
            }
        },
        grid: { padding: { top: 0, right: 0, bottom: 0, left: 0 } },
        labels: ['Collection Rate'],
        states: {
            hover: {
                filter: {
                    type: 'lighten',
                    value: 0.15,
                }
            }
        }
    };

    if (chartInstances.collectionRate) chartInstances.collectionRate.destroy();
    chartInstances.collectionRate = new ApexCharts(element, options);
    chartInstances.collectionRate.render();
    console.log('Collection rate chart rendered!');
}

function initPerformanceRadarChart(primaryColor) {
    const element = document.getElementById('performanceRadarChart');
    if (!element) return;

    let labels = safeParseData(element.dataset.labels);
    let values = safeParseData(element.dataset.values);

    // Ensure values are numbers
    if (!Array.isArray(values)) {
        values = [];
    }
    values = values.map(v => typeof v === 'string' ? parseFloat(v) || 0 : v || 0);

    console.log('Performance data:', { labels, values });

    if (!labels.length || !values.length || !values.some(v => v > 0)) {
        element.innerHTML = '<div class="text-center text-gray-500 py-10">No performance data available</div>';
        return;
    }

    element.style.width = '100%';
    element.style.height = '300px';

    const options = {
        series: [{ name: 'Performance', data: values }],
        colors: [primaryColor],
        chart: {
            type: 'radar',
            height: 300,
            width: '100%',
            toolbar: { show: false },
            dropShadow: { 
                enabled: true, 
                blur: 3, 
                left: 0, 
                top: 1,
                opacity: 0.2
            },
            fontFamily: "Outfit, sans-serif",
            background: 'transparent'
        },
        plotOptions: {
            radar: {
                size: 120,
                polygons: {
                    strokeColors: '#e5e7eb',
                    connectorColors: '#e5e7eb',
                    fill: { 
                        colors: [
                            'rgba(var(--primary-rgb, 70, 95, 255), 0.05)', 
                            'rgba(var(--primary-rgb, 70, 95, 255), 0.02)'
                        ] 
                    }
                }
            }
        },
        xaxis: {
            categories: labels.map(label => label.replace(/_/g, ' ').toUpperCase()),
            labels: { 
                style: { 
                    fontSize: '11px',
                    fontFamily: 'Outfit, sans-serif', 
                    colors: '#64748b',
                    fontWeight: 500
                },
                offsetY: 5
            }
        },
        yaxis: {
            min: 0,
            max: 100,
            tickAmount: 4,
            labels: {
                formatter: function(val) { 
                    return val + '%'; 
                },
                style: { 
                    fontSize: '10px',
                    fontFamily: 'Outfit, sans-serif', 
                    colors: '#94a3b8' 
                }
            }
        },
        markers: { 
            size: 5, 
            colors: ['#fff'], 
            strokeColor: primaryColor, 
            strokeWidth: 2,
            hover: {
                size: 7
            }
        },
        stroke: {
            width: 2,
            colors: [primaryColor]
        },
        fill: {
            opacity: 0.15,
            colors: [primaryColor]
        },
        tooltip: { 
            y: { 
                formatter: function(val) { 
                    return val + '%'; 
                } 
            },
            style: {
                fontSize: '12px',
                fontFamily: 'Outfit, sans-serif'
            }
        },
        legend: {
            show: false
        },
        responsive: [{
            breakpoint: 768,
            options: {
                chart: {
                    height: 250,
                },
                plotOptions: {
                    radar: {
                        size: 100,
                    }
                }
            }
        }]
    };

    if (chartInstances.performance) chartInstances.performance.destroy();
    chartInstances.performance = new ApexCharts(element, options);
    chartInstances.performance.render();
    console.log('Performance radar chart rendered!');
}

function initAgingReportChart(warningColor) {
    const element = document.getElementById('agingReportChart');
    if (!element) return;

    let labels = safeParseData(element.dataset.labels);
    let values = safeParseData(element.dataset.values);

    // Ensure values are numbers
    if (!Array.isArray(values)) {
        values = [];
    }
    values = values.map(v => typeof v === 'string' ? parseFloat(v) || 0 : v || 0);

    console.log('Aging report data:', { labels, values });

    if (!labels.length || !values.length || !values.some(v => v > 0)) {
        element.innerHTML = '<div class="text-center text-gray-500 py-10">No aging data available</div>';
        return;
    }

    const maxValue = Math.max(...values, 1);
    const yAxisMax = calculateYAxisMax(maxValue);

    // Set full width and responsive height
    element.style.width = '100%';
    element.style.height = '280px';

    const options = {
        series: [{ name: 'Outstanding Amount', data: values }],
        colors: [warningColor],
        chart: {
            type: 'bar',
            height: 280,
            width: '100%',
            toolbar: { show: false },
            animations: { enabled: true, speed: 800 },
            fontFamily: "Outfit, sans-serif",
            background: 'transparent'
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '39%',
                borderRadius: 5,
                borderRadiusApplication: 'end',
                dataLabels: {
                    position: 'top',
                },
            }
        },
        dataLabels: {
            enabled: true,
            offsetY: -20,
            style: {
                fontSize: '12px',
                fontFamily: 'Outfit, sans-serif',
                colors: ['#333'],
                fontWeight: '600',
            },
            formatter: function(val) {
                return 'KES ' + Number(val).toLocaleString();
            }
        },
        xaxis: {
            categories: labels,
            labels: {
                style: {
                    fontSize: '12px',
                    fontFamily: 'Outfit, sans-serif',
                    colors: '#64748b',
                },
                rotate: 0,
                trim: true
            },
            axisBorder: { show: true, color: '#e5e7eb' },
            axisTicks: { show: true, color: '#e5e7eb' },
        },
        yaxis: {
            min: 0,
            max: yAxisMax,
            tickAmount: 5,
            forceNiceScale: true,
            decimalsInFloat: 0,
            title: {
                text: "Amount (KES)",
                style: {
                    fontSize: "13px",
                    fontFamily: 'Outfit, sans-serif',
                    fontWeight: 500,
                    color: '#64748b'
                }
            },
            labels: {
                formatter: (value) => {
                    return Math.round(value).toString();
                },
                style: {
                    fontSize: "12px",
                    fontFamily: 'Outfit, sans-serif',
                    colors: '#64748b',
                },
                align: 'right',
                padding: 5
            },
            axisBorder: { show: true, color: '#e5e7eb' },
            axisTicks: { show: true, color: '#e5e7eb' },
            crosshairs: {
                show: true,
                position: 'back',
                stroke: {
                    color: '#b6b6b6',
                    width: 1,
                    dashArray: 3,
                }
            }
        },
        grid: {
            show: true,
            borderColor: "#e5e7eb",
            strokeDashArray: 5,
            position: "back",
            xaxis: {
                lines: {
                    show: false,
                },
            },
            yaxis: {
                lines: {
                    show: true,
                },
            },
            padding: {
                left: 10,
                right: 10,
            }
        },
        tooltip: { 
            y: { 
                formatter: function(val) { 
                    return 'KES ' + Number(val).toLocaleString(); 
                } 
            },
            style: {
                fontSize: '12px',
                fontFamily: 'Outfit, sans-serif'
            }
        },
        states: {
            hover: {
                filter: {
                    type: 'lighten',
                    value: 0.1,
                }
            }
        },
        responsive: [{
            breakpoint: 768,
            options: {
                chart: {
                    height: 250,
                },
                plotOptions: {
                    bar: {
                        columnWidth: "50%",
                    }
                },
                dataLabels: {
                    style: {
                        fontSize: '10px',
                    }
                }
            }
        }]
    };

    if (chartInstances.aging) chartInstances.aging.destroy();
    chartInstances.aging = new ApexCharts(element, options);
    chartInstances.aging.render();
    console.log('Aging report chart rendered!');
}

// Export function - Global
window.exportChart = function(chartId, type) {
    const chartMap = {
        'revenueBarChart': chartInstances.revenue,
        'paymentDoughnutChart': chartInstances.payment,
        'revenueExpenseLineChart': chartInstances.revenueExpense,
        'invoiceStatusPieChart': chartInstances.invoiceStatus,
        'collectionRateRadialChart': chartInstances.collectionRate,
        'performanceRadarChart': chartInstances.performance,
        'agingReportChart': chartInstances.aging
    };
    
    const chart = chartMap[chartId];
    if (!chart) {
        console.error('Chart not found:', chartId);
        return;
    }

    if (type === 'png' || type === 'svg') {
        chart.dataURI().then(({ imgURI, svgURI }) => {
            const a = document.createElement('a');
            if (type === 'png') {
                a.href = imgURI;
                a.download = `${chartId}.png`;
            } else {
                a.href = svgURI;
                a.download = `${chartId}.svg`;
            }
            a.click();
        }).catch(error => {
            console.error('Error exporting chart:', error);
        });
    }

    if (type === 'csv') {
        try {
            const series = chart.w.globals.series;
            const categories = chart.w.globals.labels;
            let csv = '';

            if (chart.w.config.chart.type === 'bar' || chart.w.config.chart.type === 'line') {
                csv = 'Category,' + chart.w.config.series.map(s => s.name).join(',') + '\n';
                categories.forEach((cat, i) => {
                    const row = [cat, ...series.map(s => s[i] || 0)].join(',');
                    csv += row + '\n';
                });
            } else {
                csv = 'Label,Value\n';
                categories.forEach((label, i) => {
                    csv += `${label},${series[i]}\n`;
                });
            }

            const blob = new Blob([csv], { type: 'text/csv' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `${chartId}.csv`;
            a.click();
            URL.revokeObjectURL(a.href);
        } catch (e) {
            console.error('Error exporting CSV:', e);
        }
    }
};

export default accountantCharts;