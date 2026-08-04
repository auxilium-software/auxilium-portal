
const MetricFmt = {
    bytes(v)
    {
        if (v === null || v === undefined) return '-';
        if (v >= 1_073_741_824) return (v / 1_073_741_824).toFixed(2) + ' GB';
        if (v >= 1_048_576)     return (v / 1_048_576).toFixed(2) + ' MB';
        if (v >= 1_024)         return (v / 1_024).toFixed(2) + ' KB';
        return Math.round(v) + ' B';
    },
    percent(v) { return (v === null || v === undefined) ? '-' : v.toFixed(1) + '%'; },
    ms(v)      { return (v === null || v === undefined) ? '-' : v.toFixed(1) + ' ms'; },
    count(v)   { return (v === null || v === undefined) ? '-' : Math.round(v).toLocaleString('en-GB'); },
    decimal(v) { return (v === null || v === undefined) ? '-' : v.toFixed(2); },
    duration(seconds)
    {
        if (seconds === null || seconds === undefined) return '-';
        const s = Math.floor(seconds);
        const d = Math.floor(s / 86400);
        const h = Math.floor((s % 86400) / 3600);
        const m = Math.floor((s % 3600) / 60);
        if (d > 0) return `${d}d ${h}h ${m}m`;
        if (h > 0) return `${h}h ${m}m`;
        if (m > 0) return `${m}m`;
        return `${s}s`;
    },
    date(iso)
    {
        if (!iso) return '-';
        const dt = new Date(iso);
        return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
            + ' ' + dt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    },
};

const METRIC_PALETTE = [
    '#3168d5',
    '#d9534f',
    '#5cb85c',
    '#f0ad4e',
    '#8e44ad',
    '#16a085',
];


const MetricAxis = {
    percent: {
        beginAtZero: true,
        suggestedMax: 100,
        ticks: {
            callback: v => v + '%'
        }
    },
    bytes: {
        beginAtZero: false,
        ticks: {
            callback: v => MetricFmt.bytes(v)
        }
    },
    ms: {
        beginAtZero: true,
        ticks: {
            callback: v => v + ' ms'
        }
    },
    count: {
        beginAtZero: true,
        ticks: {
            precision: 0
        }
    },
};
