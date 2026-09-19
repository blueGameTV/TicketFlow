(() => {
    const canvas = document.getElementById('ticketTrendChart');
    const source = document.getElementById('ticketTrendData');
    if (!canvas || !source) return;

    let data;
    try { data = JSON.parse(source.textContent); } catch (_) { return; }

    const ctx = canvas.getContext('2d');
    const labels = Array.isArray(data.labels) ? data.labels : [];
    const created = Array.isArray(data.created) ? data.created : [];
    const resolved = Array.isArray(data.resolved) ? data.resolved : [];

    function draw() {
        const ratio = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        const width = Math.max(320, Math.floor(rect.width));
        const height = 300;
        canvas.width = Math.floor(width * ratio);
        canvas.height = Math.floor(height * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.clearRect(0, 0, width, height);

        const pad = { left: 42, right: 18, top: 18, bottom: 42 };
        const plotW = width - pad.left - pad.right;
        const plotH = height - pad.top - pad.bottom;
        const maxValue = Math.max(1, ...created, ...resolved);
        const gridSteps = 4;

        ctx.font = '12px system-ui, sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        ctx.strokeStyle = '#e8edf4';
        ctx.fillStyle = '#7c8698';
        ctx.lineWidth = 1;

        for (let i = 0; i <= gridSteps; i++) {
            const y = pad.top + (plotH * i / gridSteps);
            const value = Math.round(maxValue * (1 - i / gridSteps));
            ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(width - pad.right, y); ctx.stroke();
            ctx.fillText(String(value), pad.left - 8, y);
        }

        if (labels.length === 0) return;
        const xFor = (i) => labels.length === 1 ? pad.left + plotW / 2 : pad.left + (plotW * i / (labels.length - 1));
        const yFor = (v) => pad.top + plotH - (Math.max(0, Number(v) || 0) / maxValue) * plotH;

        const css = getComputedStyle(document.documentElement);
        const createdColor = css.getPropertyValue('--chart-created').trim() || '#3157d5';
        const resolvedColor = css.getPropertyValue('--chart-resolved').trim() || '#2f7d55';

        function series(values, color) {
            ctx.strokeStyle = color;
            ctx.lineWidth = 2.5;
            ctx.lineJoin = 'round';
            ctx.lineCap = 'round';
            ctx.beginPath();
            values.forEach((value, i) => {
                const x = xFor(i), y = yFor(value);
                if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            });
            ctx.stroke();
        }

        series(created, createdColor);
        series(resolved, resolvedColor);

        const desiredTicks = width < 600 ? 4 : 7;
        const tickStep = Math.max(1, Math.ceil(labels.length / desiredTicks));
        ctx.fillStyle = '#7c8698';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        labels.forEach((label, i) => {
            if (i % tickStep !== 0 && i !== labels.length - 1) return;
            const parts = String(label).split('-');
            const text = parts.length === 3 ? `${parts[2]}/${parts[1]}` : label;
            ctx.fillText(text, xFor(i), height - pad.bottom + 12);
        });
    }

    let timer;
    window.addEventListener('resize', () => {
        clearTimeout(timer);
        timer = setTimeout(draw, 100);
    });
    draw();
})();
