class ServiceMetricsDashboard
{
    constructor()
    {
        this.api = new SystemMetricController();
        this._charts = {};
    }

    async load(keys)
    {
        const results = await Promise.all(keys.map(k => this.api.GetMetricSeries(k)));
        const out = {};
        keys.forEach((k, i) => { out[k] = results[i]?.metrics ?? []; });
        return out;
    }

    latest(metrics)
    {
        return metrics?.[0]?.value ?? null;
    }
    prev(metrics)
    {
        return metrics?.[1]?.value ?? null;
    }
    peak(metrics)
    {
        return (metrics && metrics.length)
            ? Math.max(...metrics.map(m => m.value))
            : null;
    }
    lastAt(metrics)
    {
        return metrics?.[0]?.createdAt ?? null;
    }

    statValue(id, value, fmt = MetricFmt.count)
    {
        const el = document.getElementById(id);
        if (el)
        {
            el.textContent = fmt(value);
        }
    }

    statChange(id, metrics, fmt = MetricFmt.count, sinceText = 'since last reading', noChangeText = 'No change')
    {
        const el = document.getElementById(id);
        if (!el)
        {
            return;
        }
        const cur = this.latest(metrics), prev = this.prev(metrics);
        if (cur === null || prev === null)
        {
            el.className = 'stat-card__change';
            el.innerHTML = '&nbsp;';
            return;
        }
        const delta = cur - prev;
        if (delta > 0)
        {
            el.className = 'stat-card__change stat-card__change--up';
            el.textContent = '▲ ' + fmt(delta) + ' ' + sinceText;
        }
        else if (delta < 0)
        {
            el.className = 'stat-card__change stat-card__change--down';
            el.textContent = '▼ ' + fmt(Math.abs(delta)) + ' ' + sinceText;
        }
        else
        {
            el.className = 'stat-card__change';
            el.textContent = noChangeText;
        }
    }

    statStatus(id, value, upText, downText)
    {
        const el = document.getElementById(id);
        if (!el)
        {
            return;
        }
        const up = value === 1 || value === true;
        const unknown = value === null || value === undefined;
        el.textContent = unknown ? '-' : (up ? upText : downText);
        el.style.color = unknown ? '#666' : (up ? '#006600' : '#cc0000');
    }

    statSub(id, text)
    {
        const el = document.getElementById(id);
        if (el)
        {
            el.className = 'stat-card__change';
            el.textContent = text;
        }
    }

    lineChart(canvasId, metrics, opts = {})
    {
        const ordered = [...metrics].reverse(); // chronological
        if (this._charts[canvasId])
        {
            this._charts[canvasId].destroy();
        }

        const fmt = opts.fmt ?? (v => v);
        this._charts[canvasId] = AdminChart.line(
            canvasId,
            ordered.map(m => MetricFmt.date(m.createdAt)),
            [{
                label: opts.label ?? '',
                data: ordered.map(m => m.value),
                fill: opts.fill ?? true,
                tension: 0.3,
                pointRadius: 2,
                pointHoverRadius: 5,
                ...(opts.color ? { borderColor: opts.color, backgroundColor: opts.color } : {}),
            }],
            {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + fmt(ctx.parsed.y)
                        }
                    },
                },
                scales: {
                    y: opts.y
                        ?? {
                        beginAtZero: false
                    }
                },
            }
        );
        return this._charts[canvasId];
    }

    multiLineChart(canvasId, seriesList, opts = {})
    {
        const tsSet = new Set();
        seriesList.forEach(s => s.metrics.forEach(m => tsSet.add(m.createdAt)));
        const ts = [...tsSet].sort();

        const datasets = seriesList.map((s, i) => {
            const byTs = new Map(s.metrics.map(m => [m.createdAt, m.value]));
            const colour = s.color ?? METRIC_PALETTE[i % METRIC_PALETTE.length];
            return {
                label: s.label,
                data: ts.map(t => (byTs.has(t) ? byTs.get(t) : null)),
                spanGaps: true,
                fill: false,
                tension: 0.3,
                pointRadius: 2,
                pointHoverRadius: 5,
                borderColor: colour,
                backgroundColor: colour,
            };
        });

        const fmt = opts.fmt ?? (v => v);
        if (this._charts[canvasId]) this._charts[canvasId].destroy();
        this._charts[canvasId] = AdminChart.line(
            canvasId,
            ts.map(t => MetricFmt.date(t)),
            datasets,
            {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            font: {
                                size: 10
                            },
                            boxWidth: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: ${fmt(ctx.parsed.y)}`
                        }
                    },
                },
                scales: {
                    y: opts.y
                        ?? {
                        beginAtZero: true
                    }
                },
            }
        );
        return this._charts[canvasId];
    }

    groupByLabel(metrics)
    {
        const groups = {};
        for (const m of metrics)
        {
            const k = m.label ?? '(unlabelled)';
            (groups[k] ??= []).push(m);
        }
        return Object.entries(groups).map(([label, ms]) => ({ label, metrics: ms }));
    }

    footer(id, metrics, readingsText = 'readings', lastUpdatedText = 'Last updated')
    {
        const el = document.getElementById(id);
        if (!el)
        {
            return;
        }
        el.innerHTML =
            '<span>' + (metrics?.length ?? 0) + ' ' + readingsText + '</span>'
            + '<span>' + lastUpdatedText + ': ' + MetricFmt.date(this.lastAt(metrics)) + '</span>';
    }

    historyTable(tbodyId, seriesMap, columns, emptyText = 'No data yet')
    {
        const tbody = document.getElementById(tbodyId);
        if (!tbody)
        {
            return;
        }

        const rows = new Map(); // createdAt -> { createdAt, [key]: value }
        for (const [key, metrics] of Object.entries(seriesMap))
        {
            for (const m of metrics)
            {
                let row = rows.get(m.createdAt);
                if (!row) { row = { createdAt: m.createdAt }; rows.set(m.createdAt, row); }
                row[key] = m.value;
            }
        }

        const ordered = [...rows.values()].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
        if (!ordered.length)
        {
            tbody.innerHTML = `<tr><td colspan="${columns.length + 1}" style="text-align:center;color:#666;padding:20px;">${emptyText}</td></tr>`;
            return;
        }

        tbody.innerHTML = ordered.map(row =>
            '<tr><td>' + MetricFmt.date(row.createdAt) + '</td>'
            + columns.map(c => '<td>' + c.fmt(row[c.key] ?? null) + '</td>').join('')
            + '</tr>'
        ).join('');
    }

    async fail(err, contextLabel)
    {
        console.error('Failed to load ' + contextLabel + ':', err);
        new ToastNotification(
            await Localisation.translate('Failed to load ' + contextLabel),
            'warning-circle',
            'error'
        );
    }
}
