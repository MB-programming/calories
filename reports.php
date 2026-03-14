<?php
require_once 'config/config.php';
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
require_once 'config/database.php';
$db   = Database::getInstance();
i18n_init($db);
$user = $db->fetch("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);
$lang = currentLang();
$dir  = langDir();
?><!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('reports_title') ?> - <?= t('app_name') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?= jsTranslations() ?>
    <style>
    .report-card { background:#fff; border-radius:16px; padding:24px; box-shadow:var(--shadow); margin-bottom:20px; }
    .report-card .card-header { font-size:1.1rem; font-weight:600; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; }
    .big-stat { text-align:center; padding:16px 8px; }
    .big-stat .val { font-size:2.2rem; font-weight:800; color:var(--primary); }
    .big-stat .lbl { font-size:.82rem; color:var(--text-muted); margin-top:4px; }
    .food-rank { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--border); }
    .food-rank:last-child { border:none; }
    .food-rank .rank-num { width:24px; height:24px; border-radius:50%; background:var(--primary); color:#fff; font-size:.75rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    @media print {
        .navbar, .date-controls, .no-print { display:none !important; }
        body { background:#fff; }
        .report-card { box-shadow:none; border:1px solid #ddd; }
    }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <div class="flex-between" style="margin-bottom:20px;flex-wrap:wrap;gap:12px">
        <h1 class="page-title mb-0">📊 <?= t('reports_title') ?></h1>
        <div style="display:flex;gap:8px;flex-wrap:wrap" class="no-print">
            <button onclick="printReport()" class="btn btn-outline btn-sm">🖨️ <?= t('print_report') ?></button>
            <button onclick="exportCsv()" class="btn btn-outline btn-sm">📥 <?= t('export_csv') ?></button>
        </div>
    </div>

    <!-- Date Range Controls -->
    <div class="report-card date-controls no-print">
        <div class="card-header"><?= t('date_range') ?></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div>
                <label class="form-label"><?= t('from_date') ?></label>
                <input type="date" class="form-control" id="from-date" style="width:auto"
                       value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
            </div>
            <div>
                <label class="form-label"><?= t('to_date') ?></label>
                <input type="date" class="form-control" id="to-date" style="width:auto"
                       value="<?= date('Y-m-d') ?>">
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="btn btn-sm btn-outline" onclick="setRange(7)"><?= t('weekly') ?></button>
                <button class="btn btn-sm btn-outline" onclick="setRange(30)"><?= t('monthly') ?></button>
                <button class="btn btn-sm btn-primary" onclick="loadReport()">📊 <?= t('calculate') ?></button>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div id="stats-grid" class="grid-4">
        <div class="report-card big-stat">
            <div class="val" id="stat-avg-cal">—</div>
            <div class="lbl">⚡ <?= t('avg_calories') ?></div>
        </div>
        <div class="report-card big-stat">
            <div class="val" id="stat-goal-rate" style="color:#2196F3">—%</div>
            <div class="lbl">🎯 <?= t('goal_rate') ?></div>
        </div>
        <div class="report-card big-stat">
            <div class="val" id="stat-streak" style="color:#FF9800">—</div>
            <div class="lbl">🔥 <?= t('streak') ?></div>
        </div>
        <div class="report-card big-stat">
            <div class="val" id="stat-days" style="color:#9C27B0">—</div>
            <div class="lbl">📅 <?= t('total_logs') ?></div>
        </div>
    </div>

    <div class="grid-2">
        <!-- Calorie Trend Chart -->
        <div class="report-card">
            <div class="card-header">📈 <?= t('calorie_trend') ?></div>
            <canvas id="calorie-chart" height="200"></canvas>
        </div>

        <!-- Macro Breakdown -->
        <div class="report-card">
            <div class="card-header">🥗 <?= t('macro_breakdown') ?></div>
            <canvas id="macro-chart" height="200"></canvas>
        </div>
    </div>

    <div class="grid-2">
        <!-- Weight Trend -->
        <div class="report-card">
            <div class="card-header">⚖️ <?= t('weight_trend') ?></div>
            <canvas id="weight-chart" height="200"></canvas>
            <p id="no-weight-data" class="text-center text-muted" style="display:none"><?= t('no_data') ?></p>
        </div>

        <!-- Top Foods -->
        <div class="report-card">
            <div class="card-header">🏆 أكثر الأكلات تكراراً</div>
            <div id="top-foods">
                <p class="text-center text-muted"><?= t('loading') ?></p>
            </div>
        </div>
    </div>

    <!-- Meal Type Distribution -->
    <div class="report-card">
        <div class="card-header">🍽️ توزيع الوجبات</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;height:120px" id="meal-bars">
            <p class="text-muted"><?= t('loading') ?></p>
        </div>
    </div>

    <!-- AI Recommendations Section -->
    <div class="report-card no-print">
        <div class="card-header">
            🤖 <?= t('ai_rec_title') ?>
            <span id="ai-provider-badge" class="badge badge-info" style="font-size:.75rem"></span>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
            <button class="btn btn-primary" onclick="generatePlan('full_plan')" id="btn-full">
                🎯 <?= $lang === 'en' ? 'Full Plan (Diet + Training)' : ($lang === 'de' ? 'Vollständiger Plan' : 'خطة كاملة (غذاء + تدريب)') ?>
            </button>
            <button class="btn btn-secondary" onclick="generatePlan('diet_plan')" id="btn-diet">
                🍽️ <?= t('diet_plan') ?>
            </button>
            <button class="btn btn-accent" onclick="generatePlan('training_plan')" id="btn-training">
                💪 <?= t('training_plan') ?>
            </button>
        </div>
        <div id="ai-plan-result" style="display:none">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <span id="ai-plan-type" class="badge badge-success"></span>
                <button onclick="copyPlan()" class="btn btn-sm btn-outline no-print">📋 نسخ</button>
            </div>
            <div class="ai-response" id="ai-plan-text"></div>
        </div>
        <div id="saved-recs" style="margin-top:16px"></div>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script>
let calChart, macroChart, weightChart;

async function loadReport() {
    const from = document.getElementById('from-date').value;
    const to   = document.getElementById('to-date').value;
    showLoading(window.i18n?.loading || 'Loading...');

    const res = await api(`api/reports.php?action=range&from=${from}&to=${to}`);
    hideLoading();
    if (!res.success) { toast(res.message || 'Error', 'error'); return; }

    // Stats
    document.getElementById('stat-avg-cal').textContent = res.stats.avg_calories || 0;
    document.getElementById('stat-goal-rate').textContent = (res.goal_rate || 0) + '%';
    document.getElementById('stat-streak').textContent = res.streak || 0;
    document.getElementById('stat-days').textContent = res.stats.days_logged || 0;

    // Color goal rate
    const gr = res.goal_rate || 0;
    document.getElementById('stat-goal-rate').style.color = gr >= 70 ? '#4CAF50' : gr >= 40 ? '#FF9800' : '#f44336';

    // Calorie Trend Chart
    const dates    = res.daily.map(d => d.summary_date);
    const cals     = res.daily.map(d => parseFloat(d.total_calories) || 0);
    const targets  = res.daily.map(d => parseFloat(d.target_calories) || 2000);

    if (calChart) calChart.destroy();
    calChart = new Chart(document.getElementById('calorie-chart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                {
                    label: '<?= t('calories') ?>',
                    data: cals,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76,175,80,.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                },
                {
                    label: '<?= t('target') ?>',
                    data: targets,
                    borderColor: '#FF9800',
                    borderDash: [6, 3],
                    fill: false,
                    pointRadius: 0,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: false } }
        }
    });

    // Macro Breakdown (average)
    const avgP = parseFloat(res.stats.avg_protein) || 0;
    const avgC = parseFloat(res.stats.avg_carbs)   || 0;
    const avgF = parseFloat(res.stats.avg_fat)     || 0;

    if (macroChart) macroChart.destroy();
    macroChart = new Chart(document.getElementById('macro-chart'), {
        type: 'doughnut',
        data: {
            labels: ['<?= t('protein') ?>', '<?= t('carbs') ?>', '<?= t('fat') ?>'],
            datasets: [{
                data: [avgP * 4, avgC * 4, avgF * 9],
                backgroundColor: ['#E91E63', '#FF9800', '#9C27B0'],
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct   = total > 0 ? Math.round((ctx.raw / total) * 100) : 0;
                            return ` ${ctx.label}: ${pct}% (${Math.round(ctx.raw)} kcal)`;
                        }
                    }
                }
            }
        }
    });

    // Weight Trend
    const wData = res.weight_trend || [];
    const canvas = document.getElementById('weight-chart');
    const noData = document.getElementById('no-weight-data');
    if (wData.length > 1) {
        canvas.style.display = 'block';
        noData.style.display = 'none';
        if (weightChart) weightChart.destroy();
        weightChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: wData.map(d => d.date),
                datasets: [{
                    label: '<?= t('weight') ?> (kg)',
                    data: wData.map(d => parseFloat(d.weight)),
                    borderColor: '#2196F3',
                    backgroundColor: 'rgba(33,150,243,.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 5,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: false } }
            }
        });
    } else {
        canvas.style.display = 'none';
        noData.style.display = 'block';
    }

    // Top Foods
    const tfEl = document.getElementById('top-foods');
    if (res.top_foods && res.top_foods.length) {
        tfEl.innerHTML = res.top_foods.map((f, i) => `
            <div class="food-rank">
                <div class="rank-num">${i + 1}</div>
                <div style="flex:1">
                    <div style="font-weight:600">${f.food_name}</div>
                    <div style="font-size:.8rem;color:#666">≈${f.avg_cal} kcal</div>
                </div>
                <div style="font-weight:700;color:var(--primary)">${f.freq}x</div>
            </div>`).join('');
    } else {
        tfEl.innerHTML = '<p class="text-center text-muted"><?= t('no_data') ?></p>';
    }

    // Meal Type Distribution
    const mealEl   = document.getElementById('meal-bars');
    const mealIcons = {breakfast:'🌅',lunch:'☀️',dinner:'🌙',snack:'🍎',other:'🍴'};
    const mealNames = {
        breakfast: '<?= t('meal_breakfast') ?>',
        lunch:     '<?= t('meal_lunch') ?>',
        dinner:    '<?= t('meal_dinner') ?>',
        snack:     '<?= t('meal_snack') ?>',
        other:     '<?= t('meal_other') ?>',
    };
    if (res.meal_breakdown && res.meal_breakdown.length) {
        const maxCal = Math.max(...res.meal_breakdown.map(m => parseFloat(m.total_cal)));
        mealEl.innerHTML = res.meal_breakdown.map(m => {
            const h = Math.max(8, Math.round((m.total_cal / maxCal) * 100));
            return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                <div style="font-size:.75rem;color:#666">${Math.round(m.total_cal)}</div>
                <div style="width:100%;height:${h}px;background:var(--primary);border-radius:4px 4px 0 0"></div>
                <div style="font-size:.8rem">${mealIcons[m.meal_type]||'🍴'}</div>
                <div style="font-size:.75rem;color:#666">${mealNames[m.meal_type]||m.meal_type}</div>
            </div>`;
        }).join('');
    } else {
        mealEl.innerHTML = '<p class="text-muted"><?= t('no_data') ?></p>';
    }
}

function setRange(days) {
    const to   = new Date();
    const from = new Date();
    from.setDate(from.getDate() - (days - 1));
    document.getElementById('from-date').value = from.toISOString().split('T')[0];
    document.getElementById('to-date').value   = to.toISOString().split('T')[0];
    loadReport();
}

function printReport() { window.print(); }

function exportCsv() {
    const from = document.getElementById('from-date').value;
    const to   = document.getElementById('to-date').value;
    window.open(`api/reports.php?action=export_csv&from=${from}&to=${to}`, '_blank');
}

// AI Plan generation
async function generatePlan(type) {
    const btns = ['btn-full','btn-diet','btn-training'];
    btns.forEach(id => { document.getElementById(id).disabled = true; });

    const labels = {
        full_plan:     '<?= $lang==='en' ? 'Full Plan' : ($lang==='de' ? 'Vollplan' : 'خطة كاملة') ?>',
        diet_plan:     '<?= t('diet_plan') ?>',
        training_plan: '<?= t('training_plan') ?>',
    };

    document.getElementById('ai-plan-result').style.display = 'none';
    showLoading(window.i18n?.ai_thinking || 'AI is thinking...');

    const res = await api('api/ai.php', { action: type });
    hideLoading();
    btns.forEach(id => { document.getElementById(id).disabled = false; });

    if (res.success) {
        document.getElementById('ai-plan-type').textContent = labels[type] || type;
        document.getElementById('ai-plan-text').textContent = res.plan;
        document.getElementById('ai-plan-result').style.display = 'block';
        document.getElementById('ai-provider-badge').textContent = res.provider || '';
        document.getElementById('ai-plan-result').scrollIntoView({ behavior: 'smooth' });
    } else {
        toast(res.message || window.i18n?.ai_unavailable, 'error');
    }
}

function copyPlan() {
    const text = document.getElementById('ai-plan-text').textContent;
    navigator.clipboard.writeText(text).then(() => toast('تم النسخ ✅', 'success'));
}

// Load AI provider badge
(async () => {
    const res = await api('api/reports.php?action=range&from=' + document.getElementById('from-date').value + '&to=' + document.getElementById('to-date').value);
    // also load provider info
})();

// Init
loadReport();
</script>
</body>
</html>
