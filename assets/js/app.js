// ── API helper ──
async function api(url, data = null, isForm = false) {
    const opts = { method: data ? 'POST' : 'GET' };
    if (data) {
        if (isForm) {
            opts.body = data; // FormData
        } else {
            opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
            opts.body = new URLSearchParams(data).toString();
        }
    }
    const res = await fetch(url, opts);
    return res.json();
}

// ── Toast notifications ──
function toast(msg, type = 'info') {
    const colors = { success: '#4CAF50', error: '#f44336', info: '#2196F3', warning: '#FF9800' };
    const el = document.createElement('div');
    el.style.cssText = `
        position:fixed; bottom:24px; left:50%; transform:translateX(-50%);
        background:${colors[type]}; color:#fff; padding:12px 24px;
        border-radius:24px; z-index:99999; font-size:.95rem; font-weight:600;
        box-shadow:0 4px 20px rgba(0,0,0,.2); animation: fadeInUp .3s ease;
        max-width:90vw; text-align:center;
    `;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

// ── Loading overlay ──
function showLoading(text = 'جاري التحميل...') {
    let ov = document.getElementById('loading-overlay');
    if (!ov) {
        ov = document.createElement('div');
        ov.id = 'loading-overlay';
        ov.className = 'loading-overlay show';
        ov.innerHTML = `<div class="loading-box"><div class="spinner"></div><div>${text}</div></div>`;
        document.body.appendChild(ov);
    } else {
        ov.querySelector('div > div:last-child').textContent = text;
        ov.classList.add('show');
    }
}
function hideLoading() {
    const ov = document.getElementById('loading-overlay');
    if (ov) ov.remove();
}

// ── Calorie ring ──
function updateRing(consumed, target) {
    const pct = Math.min((consumed / target) * 100, 100);
    const r = 58, circ = 2 * Math.PI * r;
    const dashArr = `${(pct / 100) * circ} ${circ}`;
    const circle = document.getElementById('ring-circle');
    if (circle) {
        circle.setAttribute('stroke-dasharray', dashArr);
        circle.setAttribute('stroke', consumed > target ? '#f44336' : '#4CAF50');
    }
    const calEl = document.getElementById('ring-calories');
    if (calEl) calEl.textContent = Math.round(consumed);
    const remEl = document.getElementById('ring-remaining');
    if (remEl) {
        const rem = target - consumed;
        remEl.textContent = rem >= 0 ? `متبقي ${Math.round(rem)}` : `زيادة ${Math.round(-rem)}`;
        remEl.style.color = rem < 0 ? '#f44336' : '#666';
    }
}

// ── Format macros ──
function macroBar(label, val, max, color) {
    const pct = Math.min((val / max) * 100, 100);
    return `<div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:4px">
            <span>${label}</span><span style="color:${color};font-weight:600">${Math.round(val)}g</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width:${pct}%;background:${color}"></div>
        </div>
    </div>`;
}

// ── Confirm dialog ──
function confirm(msg, callback) {
    if (window.confirm(msg)) callback();
}

// Add CSS animation
const style = document.createElement('style');
style.textContent = '@keyframes fadeInUp{from{opacity:0;transform:translateX(-50%) translateY(20px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}';
document.head.appendChild(style);
