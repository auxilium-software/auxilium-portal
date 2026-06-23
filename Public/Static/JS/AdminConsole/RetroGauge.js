class RetroGauge
{
    #cx = 120;
    #cy = 122;
    #needle = null;
    #lcd = null;
    #lcdValue = null;
    #cfg;
    #current = -180;

    constructor(host, cfg)
    {
        this.#cfg = Object.assign({min: 0, unit: '', minorPerMajor: 2, caption: ''}, cfg);
        host.innerHTML =
            `<div class="analogue_gauge__title">${this.#cfg.title}</div>${this.#buildSvg()}`
            + `<div class="analogue_gauge__lcd"><span class="analogue_gauge__lcd-value">--</span>`
            + (this.#cfg.unit ? `<span class="analogue_gauge__lcd-unit">${this.#cfg.unit}</span>` : '')
            + `</div>`;
        this.#needle = host.querySelector('.rg-needle');
        this.#lcd = host.querySelector('.analogue_gauge__lcd');
        this.#lcdValue = host.querySelector('.analogue_gauge__lcd-value');
    }

    static #polar(cx, cy, r, deg)
    {
        const a = deg * Math.PI / 180;
        return [cx + r * Math.cos(a), cy - r * Math.sin(a)];
    }

    static #arc(cx, cy, r, d0, d1)
    {
        const s = RetroGauge.#polar(cx, cy, r, d0), e = RetroGauge.#polar(cx, cy, r, d1);
        const lg = Math.abs(d0 - d1) > 180 ? 1 : 0;
        return `M${s[0].toFixed(2)} ${s[1].toFixed(2)} A${r} ${r} 0 ${lg} 1 ${e[0].toFixed(2)} ${e[1].toFixed(2)}`;
    }

    static #degFor(f)
    {
        return 180 - 180 * f;
    }

    static #easeOutBack(x)
    {
        const c1 = 1.4, c3 = c1 + 1;
        return 1 + c3 * Math.pow(x - 1, 3) + c1 * Math.pow(x - 1, 2);
    }

    #buildSvg()
    {
        const c = this.#cfg, cx = this.#cx, cy = this.#cy;
        const zR = 80, zW = 7, tOut = 94, tMajI = 84, tMinI = 89, lblR = 66, nLen = 88, nTail = 16;

        let zones = '';
        c.zones.forEach(z => {
            zones += `<path d="${RetroGauge.#arc(cx, cy, zR, RetroGauge.#degFor(z[0]), RetroGauge.#degFor(z[1]))}" fill="none" stroke="${z[2]}" stroke-width="${zW}"/>`;
        });

        let ticks = '', labels = '';
        const steps = c.majorCount * c.minorPerMajor;
        for (let k = 0; k <= steps; k++)
        {
            const f = k / steps, deg = RetroGauge.#degFor(f), maj = (k % c.minorPerMajor === 0);
            const o = RetroGauge.#polar(cx, cy, tOut, deg), i = RetroGauge.#polar(cx, cy, maj ? tMajI : tMinI, deg);
            ticks += `<line x1="${o[0].toFixed(2)}" y1="${o[1].toFixed(2)}" x2="${i[0].toFixed(2)}" y2="${i[1].toFixed(2)}" stroke="#333" stroke-width="${maj ? 1.6 : 0.8}"/>`;
            if (maj)
            {
                const val = c.min + f * (c.max - c.min), l = RetroGauge.#polar(cx, cy, lblR, deg);
                labels += `<text x="${l[0].toFixed(2)}" y="${(l[1] + 3).toFixed(2)}" font-size="9" font-family="Tahoma,sans-serif" fill="#222" text-anchor="middle">${c.tickLabel(val)}</text>`;
            }
        }

        const needle = `<g class="rg-needle" transform="rotate(-180 ${cx} ${cy})"><polygon points="${cx + nLen},${cy} ${cx},${cy - 3.5} ${cx - nTail},${cy} ${cx},${cy + 3.5}" fill="#222"/></g>`;
        const hub = `<circle cx="${cx}" cy="${cy}" r="7" fill="url(#rgHub)" stroke="#555" stroke-width="0.8"/><circle cx="${cx}" cy="${cy}" r="2.6" fill="#7a0000"/>`;

        return `<svg viewBox="0 0 240 150" width="100%">`
            + `<defs><linearGradient id="rgHub" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#888"/></linearGradient></defs>`
            + `<rect x="6" y="6" width="228" height="132" rx="3" fill="#f4efe2" stroke="#808080"/>`
            + `<rect x="7.5" y="7.5" width="225" height="129" rx="2" fill="none" stroke="#ffffff" opacity="0.45"/>`
            + zones + ticks + labels
            + `<ellipse cx="120" cy="58" rx="100" ry="40" fill="#ffffff" opacity="0.07"/>`
            + `<text x="120" y="116" font-size="9" font-family="Tahoma,sans-serif" fill="#777" text-anchor="middle">${c.caption}</text>`
            + needle + hub + `</svg>`;
    }

    setValue(v)
    {
        const c = this.#cfg;
        const f = Math.max(0, Math.min(1, (v - c.min) / (c.max - c.min)));
        const target = 180 * f - 180;
        this.#lcd.classList.toggle('is-over', v > c.max);
        this.#lcdValue.textContent = c.lcd(v);

        const from = this.#current, t0 = performance.now(), dur = 950;
        const tick = now => {
            const p = Math.min(1, (now - t0) / dur);
            const d = from + (target - from) * RetroGauge.#easeOutBack(p);
            this.#needle.setAttribute('transform', `rotate(${d.toFixed(2)} ${this.#cx} ${this.#cy})`);
            if (p < 1) requestAnimationFrame(tick); else this.#current = target;
        };
        requestAnimationFrame(tick);
    }
}
