<?php
require_once '../config/config.php';
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../index.php'); exit;
}
require_once '../config/database.php';
i18n_init();
$db = Database::getInstance();

// Stats
$totalUsers  = $db->fetch("SELECT COUNT(*) as c FROM users WHERE role='user'")['c'];
$todayLogs   = $db->fetch("SELECT COUNT(*) as c FROM food_logs WHERE log_date=?", [date('Y-m-d')])['c'];
$totalLogs   = $db->fetch("SELECT COUNT(*) as c FROM food_logs")['c'];
$activeGoals = $db->fetch("SELECT COUNT(*) as c FROM goals WHERE status='active'")['c'];
$bmiRecords  = $db->fetch("SELECT COUNT(*) as c FROM bmi_records")['c'];
$avgBmi      = $db->fetch("SELECT ROUND(AVG(bmi),1) as a FROM bmi_records")['a'];

$users = $db->fetchAll("
    SELECT u.*,
        (SELECT bmi FROM bmi_records WHERE user_id=u.id ORDER BY recorded_at DESC LIMIT 1) as last_bmi,
        (SELECT goal_type FROM goals WHERE user_id=u.id AND status='active' ORDER BY created_at DESC LIMIT 1) as active_goal,
        (SELECT COUNT(*) FROM food_logs WHERE user_id=u.id AND log_date=?) as logs_today,
        (SELECT COUNT(*) FROM food_logs WHERE user_id=u.id) as total_logs,
        (SELECT language FROM user_preferences WHERE user_id=u.id) as lang
    FROM users u WHERE u.role='user' ORDER BY u.created_at DESC
", [date('Y-m-d')]);

// AI Settings
$providers    = AIProvider::getProviders();
$activeProvider = $db->getSetting('ai_provider', 'gemini');
$activeModel    = $db->getSetting('ai_model',    'gemini-2.0-flash');

$lang = currentLang();
$dir  = langDir();
?><!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin_title') ?> - <?= t('app_name') ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    .admin-layout { display:flex; min-height:100vh; }
    .sidebar {
        width:240px; flex-shrink:0; background:#1a1a2e; color:#fff;
        position:sticky; top:0; height:100vh; overflow-y:auto;
    }
    .sidebar-logo { padding:24px 20px; font-size:1.3rem; font-weight:800; color:#4CAF50; border-bottom:1px solid rgba(255,255,255,.1); }
    .sidebar-menu { padding:16px 0; }
    .sidebar-menu a {
        display:flex; align-items:center; gap:10px; padding:12px 20px;
        color:rgba(255,255,255,.7); text-decoration:none; font-size:.9rem; transition:all .2s;
    }
    .sidebar-menu a:hover, .sidebar-menu a.active {
        background:rgba(76,175,80,.2); color:#4CAF50;
        border-<?= isRtl() ? 'right' : 'left' ?>:3px solid #4CAF50;
    }
    .admin-content { flex:1; padding:24px; background:var(--bg); overflow-x:hidden; }
    .tab-content { display:none; }
    .tab-content.active { display:block; }

    /* AI Provider Cards */
    .provider-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:20px; }
    .provider-card {
        border:2px solid var(--border); border-radius:14px; padding:16px;
        cursor:pointer; transition:all .2s; background:#fff; text-align:center;
    }
    .provider-card:hover { border-color:var(--primary); }
    .provider-card.active { border-color:var(--primary); background:#e8f5e9; }
    .provider-card.paid { border-color:#FF9800; }
    .provider-card.paid.active { border-color:#FF9800; background:#FFF8E1; }
    .provider-icon { font-size:2rem; margin-bottom:6px; }
    .provider-name { font-weight:700; font-size:.95rem; }
    .provider-badge {
        display:inline-block; padding:2px 10px; border-radius:20px;
        font-size:.72rem; font-weight:600; margin-top:4px;
    }
    .badge-free { background:#E8F5E9; color:#2E7D32; }
    .badge-paid { background:#FFF8E1; color:#E65100; }
    @media(max-width:768px) { .provider-grid { grid-template-columns:1fr 1fr; } }
    </style>
</head>
<body>
<div class="admin-layout">
    <div class="sidebar">
        <div class="sidebar-logo">🥗 <?= t('app_name') ?></div>
        <div class="sidebar-menu">
            <a href="#" class="active" onclick="showTab('overview',this)">📊 <?= t('overview') ?></a>
            <a href="#" onclick="showTab('users',this)">👥 <?= t('users') ?></a>
            <a href="#" onclick="showTab('logs',this)">🍽️ <?= t('logs') ?></a>
            <a href="#" onclick="showTab('ai',this)">🤖 <?= t('ai_settings') ?></a>
            <a href="#" onclick="showTab('system',this)">⚙️ <?= t('settings') ?></a>
            <hr style="border-color:rgba(255,255,255,.1);margin:12px 0">
            <a href="../dashboard.php">🏠 <?= $lang==='en'?'Main Site':($lang==='de'?'Hauptseite':'الموقع الرئيسي') ?></a>
            <a href="#" onclick="doLogout()" style="color:#ff7070">🚪 <?= t('nav_logout') ?></a>
        </div>
    </div>

    <div class="admin-content">
        <div style="margin-bottom:24px">
            <h1 style="font-size:1.6rem;font-weight:700"><?= t('admin_title') ?></h1>
            <p class="text-muted"><?= date('l, d F Y') ?> | AI: <strong><?= $providers[$activeProvider]['name'] ?? $activeProvider ?></strong> / <?= $activeModel ?></p>
        </div>

        <!-- ── OVERVIEW ── -->
        <div class="tab-content active" id="tab-overview">
            <div class="grid-3" style="margin-bottom:20px">
                <div class="stat-card">
                    <div class="stat-value"><?= $totalUsers ?></div>
                    <div class="stat-label"><?= t('total_users') ?></div>
                </div>
                <div class="stat-card" style="border-color:#2196F3">
                    <div class="stat-value" style="color:#2196F3"><?= $todayLogs ?></div>
                    <div class="stat-label"><?= t('today_logs') ?></div>
                </div>
                <div class="stat-card" style="border-color:#FF9800">
                    <div class="stat-value" style="color:#FF9800"><?= $totalLogs ?></div>
                    <div class="stat-label"><?= $lang==='en'?'Total Food Logs':($lang==='de'?'Gesamtprotokolle':'إجمالي السجلات') ?></div>
                </div>
                <div class="stat-card" style="border-color:#9C27B0">
                    <div class="stat-value" style="color:#9C27B0"><?= $activeGoals ?></div>
                    <div class="stat-label"><?= t('active_goals') ?></div>
                </div>
                <div class="stat-card" style="border-color:#E91E63">
                    <div class="stat-value" style="color:#E91E63"><?= $bmiRecords ?></div>
                    <div class="stat-label"><?= $lang==='en'?'BMI Records':($lang==='de'?'BMI-Messungen':'قياسات BMI') ?></div>
                </div>
                <div class="stat-card" style="border-color:#009688">
                    <div class="stat-value" style="color:#009688"><?= $avgBmi ?? '—' ?></div>
                    <div class="stat-label"><?= $lang==='en'?'Avg BMI':($lang==='de'?'Ø BMI':'متوسط BMI') ?></div>
                </div>
            </div>

            <!-- BMI Distribution -->
            <div class="card">
                <div class="card-header"><?= $lang==='en'?'BMI Distribution':($lang==='de'?'BMI-Verteilung':'توزيع BMI') ?></div>
                <?php
                $bmiDist = $db->fetchAll("
                    SELECT b.category, COUNT(*) as cnt FROM bmi_records b
                    INNER JOIN (SELECT user_id, MAX(recorded_at) as mx FROM bmi_records GROUP BY user_id) m
                        ON b.user_id=m.user_id AND b.recorded_at=m.mx
                    GROUP BY b.category
                ");
                $catColors = ['Underweight'=>'#2196F3','Normal weight'=>'#4CAF50','Overweight'=>'#FF9800','Obese'=>'#f44336'];
                $catLabels  = ['Underweight'=>t('underweight'),'Normal weight'=>t('normal'),'Overweight'=>t('overweight'),'Obese'=>t('obese')];
                $total = array_sum(array_column($bmiDist, 'cnt'));
                ?>
                <?php if ($bmiDist && $total > 0): ?>
                <div style="display:flex;gap:10px;align-items:flex-end;height:110px;margin-bottom:12px">
                    <?php foreach ($bmiDist as $b):
                        $h = round(($b['cnt'] / $total) * 100);
                    ?>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                        <div style="font-size:.72rem;color:#666"><?= $b['cnt'] ?></div>
                        <div style="width:100%;height:<?= $h ?>px;background:<?= $catColors[$b['category']]??'#999' ?>;border-radius:4px 4px 0 0;min-height:4px"></div>
                        <div style="font-size:.72rem;color:#666;text-align:center"><?= $catLabels[$b['category']] ?? $b['category'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-center text-muted"><?= t('no_data') ?></p>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">⚡ <?= $lang==='en'?'Recent Activity':($lang==='de'?'Letzte Aktivitäten':'آخر النشاطات') ?></div>
                <?php
                $recent = $db->fetchAll("SELECT fl.*, u.name as un FROM food_logs fl JOIN users u ON u.id=fl.user_id ORDER BY fl.created_at DESC LIMIT 10");
                ?>
                <table class="table">
                    <thead><tr><th><?= t('name') ?></th><th><?= t('food_name') ?></th><th><?= t('calories') ?></th><th>AI</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['un']) ?></td>
                        <td><?= htmlspecialchars($r['food_name']) ?></td>
                        <td><?= round($r['calories']) ?></td>
                        <td><?= $r['ai_detected'] ? '✅' : '' ?></td>
                        <td style="font-size:.78rem;color:#999"><?= date('d/m H:i', strtotime($r['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── USERS ── -->
        <div class="tab-content" id="tab-users">
            <div class="card">
                <div class="card-header flex-between">
                    <span>👥 <?= t('users') ?> (<?= count($users) ?>)</span>
                    <input type="text" class="form-control" style="width:200px" placeholder="<?= t('search') ?>..."
                           id="user-search" oninput="filterUsers()">
                </div>
                <div style="overflow-x:auto">
                <table class="table" id="users-table">
                    <thead>
                        <tr>
                            <th>#</th><th><?= t('name') ?></th><th><?= t('email') ?></th>
                            <th>BMI</th><th><?= t('goals_title') ?></th>
                            <th>🌐</th><th><?= t('today_logs') ?></th>
                            <th><?= t('total_logs') ?></th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $gicons = ['lose'=>'📉','maintain'=>'⚖️','gain'=>'📈'];
                        $bmiClass = '';
                        if ($u['last_bmi']) {
                            $bmiClass = match(true) {
                                $u['last_bmi'] < 18.5 => 'badge-info',
                                $u['last_bmi'] < 25   => 'badge-success',
                                $u['last_bmi'] < 30   => 'badge-warning',
                                default               => 'badge-danger',
                            };
                        }
                    ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                        <td style="font-size:.8rem"><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= $u['last_bmi'] ? "<span class='badge $bmiClass'>{$u['last_bmi']}</span>" : '—' ?></td>
                        <td><?= isset($gicons[$u['active_goal']]) ? $gicons[$u['active_goal']] : '—' ?></td>
                        <td><?= $u['lang'] ? strtoupper($u['lang']) : '—' ?></td>
                        <td><span class="badge <?= $u['logs_today']>0?'badge-success':'badge-warning' ?>"><?= $u['logs_today'] ?></span></td>
                        <td><?= $u['total_logs'] ?></td>
                        <td style="white-space:nowrap">
                            <button class="btn btn-sm btn-outline" onclick="viewUser(<?= $u['id'] ?>)"><?= t('edit') ?></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>')"><?= t('delete') ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- ── LOGS ── -->
        <div class="tab-content" id="tab-logs">
            <div class="card">
                <div class="card-header flex-between">
                    <span>🍽️ <?= t('logs') ?></span>
                    <input type="date" class="form-control" style="width:auto" id="log-filter-date"
                           value="<?= date('Y-m-d') ?>" onchange="filterLogs()">
                </div>
                <div id="logs-table-wrap">
                    <?php include 'partials/logs_table.php'; ?>
                </div>
            </div>
        </div>

        <!-- ── AI SETTINGS ── -->
        <div class="tab-content" id="tab-ai">
            <div id="ai-alert"></div>

            <!-- Free Providers -->
            <div class="card">
                <div class="card-header">✨ <?= t('free_providers') ?></div>
                <div class="provider-grid">
                    <?php foreach ($providers as $key => $p):
                        if (!$p['free']) continue;
                    ?>
                    <div class="provider-card <?= $activeProvider===$key?'active':'' ?>"
                         onclick="selectProvider('<?= $key ?>')" id="pcard-<?= $key ?>">
                        <div class="provider-icon"><?= $p['icon'] ?></div>
                        <div class="provider-name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="provider-badge badge-free"><?= $p['badge'] ?></div>
                        <?php if ($activeProvider === $key): ?><div style="color:#4CAF50;margin-top:6px;font-size:.8rem">✅ نشط</div><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Paid Providers -->
            <div class="card">
                <div class="card-header">💎 <?= t('paid_providers') ?></div>
                <div class="provider-grid">
                    <?php foreach ($providers as $key => $p):
                        if ($p['free']) continue;
                    ?>
                    <div class="provider-card paid <?= $activeProvider===$key?'active':'' ?>"
                         onclick="selectProvider('<?= $key ?>')" id="pcard-<?= $key ?>">
                        <div class="provider-icon"><?= $p['icon'] ?></div>
                        <div class="provider-name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="provider-badge badge-paid"><?= $p['badge'] ?></div>
                        <?php if ($activeProvider === $key): ?><div style="color:#FF9800;margin-top:6px;font-size:.8rem">✅ نشط</div><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Config Form -->
            <div class="card">
                <div class="card-header">🔧 إعدادات المزود المختار: <span id="selected-provider-name" style="color:var(--primary)"><?= $providers[$activeProvider]['name'] ?? $activeProvider ?></span></div>

                <form id="ai-form" onsubmit="saveAiSettings(event)">
                    <input type="hidden" id="ai-provider-input" name="ai_provider" value="<?= $activeProvider ?>">

                    <div class="form-group">
                        <label class="form-label"><?= t('ai_model') ?></label>
                        <select class="form-control" name="ai_model" id="model-select">
                            <?php foreach ($providers[$activeProvider]['models'] ?? [] as $mKey => $mName): ?>
                            <option value="<?= $mKey ?>" <?= $activeModel===$mKey?'selected':'' ?>><?= htmlspecialchars($mName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php foreach ($providers as $pKey => $p):
                        if (!$p['key_field']) continue;
                        $keyVal = $db->getSetting($p['key_field'], '');
                    ?>
                    <div class="form-group provider-key-field" data-provider="<?= $pKey ?>"
                         style="<?= in_array($activeProvider, ['gemini','gemini_paid']) && $p['key_field']==='gemini_key' ? '' : ($activeProvider===$pKey ? '' : 'display:none') ?>">
                        <label class="form-label">
                            <?= $p['icon'] ?> <?= htmlspecialchars($p['name']) ?> - API Key
                            <small class="text-muted">(<?= $p['note'] ?>)</small>
                        </label>
                        <div style="display:flex;gap:8px">
                            <input type="password" class="form-control" name="<?= $p['key_field'] ?>"
                                   value="<?= htmlspecialchars($keyVal) ?>"
                                   placeholder="<?= strlen($keyVal) > 0 ? '••••••••••••' : 'sk-...' ?>">
                            <button type="button" class="btn btn-outline btn-sm"
                                    onclick="toggleKeyVisibility(this)">👁️</button>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Ollama URL -->
                    <div class="form-group provider-key-field" data-provider="ollama"
                         style="<?= $activeProvider==='ollama'?'':'display:none' ?>">
                        <label class="form-label">🖥️ Ollama Server URL</label>
                        <input type="text" class="form-control" name="ollama_url"
                               value="<?= $db->getSetting('ollama_url', 'http://localhost:11434') ?>"
                               placeholder="http://localhost:11434">
                    </div>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px">
                        <button type="submit" class="btn btn-primary" id="save-ai-btn">
                            💾 <?= t('save_settings') ?>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="testAi()" id="test-ai-btn">
                            🔌 <?= t('test_ai') ?>
                        </button>
                    </div>
                </form>

                <div id="test-result" style="margin-top:12px;display:none"></div>
            </div>

            <!-- Provider Details -->
            <div class="card">
                <div class="card-header">📚 مقارنة المزودين</div>
                <div style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr><th>المزود</th><th>النوع</th><th>رؤية الصور</th><th>السرعة</th><th>الجودة</th><th>الحد المجاني</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>✨ Gemini Flash</td><td><span class="badge badge-success">مجاني</span></td><td>✅</td><td>⚡⚡⚡</td><td>⭐⭐⭐⭐</td><td>15 req/min</td></tr>
                        <tr><td>⚡ Groq</td><td><span class="badge badge-success">مجاني</span></td><td>❌</td><td>⚡⚡⚡⚡</td><td>⭐⭐⭐⭐</td><td>30 req/min</td></tr>
                        <tr><td>🖥️ Ollama</td><td><span class="badge badge-success">مجاني تماماً</span></td><td>✅ (llava)</td><td>⚡ (محلي)</td><td>⭐⭐⭐</td><td>غير محدود</td></tr>
                        <tr><td>🤖 OpenAI GPT-4o</td><td><span class="badge badge-warning">مدفوع</span></td><td>✅</td><td>⚡⚡</td><td>⭐⭐⭐⭐⭐</td><td>—</td></tr>
                        <tr><td>🧠 Claude 3.5</td><td><span class="badge badge-warning">مدفوع</span></td><td>✅</td><td>⚡⚡</td><td>⭐⭐⭐⭐⭐</td><td>—</td></tr>
                        <tr><td>💎 Gemini Pro</td><td><span class="badge badge-warning">مدفوع</span></td><td>✅</td><td>⚡⚡</td><td>⭐⭐⭐⭐⭐</td><td>—</td></tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- ── SYSTEM SETTINGS ── -->
        <div class="tab-content" id="tab-system">
            <div class="card">
                <div class="card-header">⚙️ <?= t('settings') ?></div>
                <table class="table">
                    <tr><td>PHP Version</td><td><?= PHP_VERSION ?></td></tr>
                    <tr><td>SQLite Version</td><td><?= SQLite3::version()['versionString'] ?></td></tr>
                    <tr><td><?= $lang==='en'?'Database Size':($lang==='de'?'Datenbankgröße':'حجم قاعدة البيانات') ?></td>
                        <td><?= round(filesize(BASE_PATH.'/database/fittrack.db')/1024, 1) ?> KB</td></tr>
                    <tr><td>Active AI Provider</td><td><strong><?= $providers[$activeProvider]['name'] ?? $activeProvider ?></strong> - <?= $activeModel ?></td></tr>
                    <tr><td>App Version</td><td><?= APP_VERSION ?></td></tr>
                </table>
            </div>

            <div class="card">
                <div class="card-header">🌐 Language Distribution</div>
                <?php
                $langDist = $db->fetchAll("SELECT language, COUNT(*) as cnt FROM user_preferences GROUP BY language");
                foreach ($langDist as $l): ?>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                    <div style="width:40px"><?= strtoupper($l['language']) ?></div>
                    <div style="flex:1;background:#f0f0f0;border-radius:8px;height:20px;overflow:hidden">
                        <div style="width:<?= round(($l['cnt']/$totalUsers)*100) ?>%;background:var(--primary);height:100%;border-radius:8px"></div>
                    </div>
                    <div style="width:30px;text-align:right"><?= $l['cnt'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal-overlay" id="user-modal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <span class="modal-title">👤 User Details</span>
            <button class="modal-close" onclick="document.getElementById('user-modal').classList.remove('open')">✕</button>
        </div>
        <div id="user-detail-content">Loading...</div>
    </div>
</div>

<script src="../assets/js/app.js"></script>
<script>
// Provider config (for JS)
const PROVIDERS = <?= json_encode($providers, JSON_UNESCAPED_UNICODE) ?>;

function showTab(name, el) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    el.classList.add('active');
}

function selectProvider(key) {
    // Update card UI
    document.querySelectorAll('.provider-card').forEach(c => c.classList.remove('active'));
    document.getElementById('pcard-' + key)?.classList.add('active');
    document.getElementById('ai-provider-input').value = key;

    const p = PROVIDERS[key];
    document.getElementById('selected-provider-name').textContent = p?.name || key;

    // Update model dropdown
    const sel = document.getElementById('model-select');
    sel.innerHTML = '';
    Object.entries(p?.models || {}).forEach(([v, l]) => {
        sel.innerHTML += `<option value="${v}">${l}</option>`;
    });

    // Show/hide key fields
    document.querySelectorAll('.provider-key-field').forEach(f => {
        const providers = f.dataset.provider?.split(',') || [];
        // Gemini and gemini_paid share same key
        const show = providers.includes(key) ||
                     (key === 'gemini_paid' && providers.includes('gemini')) ||
                     (key === 'gemini' && providers.includes('gemini'));
        f.style.display = providers.includes(key) || (key === 'gemini_paid' && f.dataset.provider === 'gemini') ? '' : 'none';
    });
    document.querySelector('.provider-key-field[data-provider="ollama"]').style.display = key === 'ollama' ? '' : 'none';
}

async function saveAiSettings(e) {
    e.preventDefault();
    const btn = document.getElementById('save-ai-btn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner"></span>';

    const fd = new FormData(e.target);
    fd.append('action', 'save_ai_settings');
    const res = await api('../api/admin.php', fd, true);
    btn.disabled = false; btn.innerHTML = '💾 <?= t('save_settings') ?>';

    const alertEl = document.getElementById('ai-alert');
    if (res.success) {
        alertEl.innerHTML = '<div class="alert alert-success">✅ تم حفظ إعدادات AI</div>';
        setTimeout(() => location.reload(), 1200);
    } else {
        alertEl.innerHTML = `<div class="alert alert-error">${res.message}</div>`;
    }
}

async function testAi() {
    const btn = document.getElementById('test-ai-btn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner"></span>';
    const res = await api('../api/admin.php?action=test_ai');
    btn.disabled = false; btn.innerHTML = '🔌 <?= t('test_ai') ?>';

    const r = document.getElementById('test-result');
    r.style.display = 'block';
    if (res.success && res.ok) {
        r.innerHTML = `<div class="alert alert-success">✅ <?= t('ai_test_ok') ?> — Provider: ${res.provider} / ${res.model}<br>Response: "${res.response}"</div>`;
    } else {
        r.innerHTML = `<div class="alert alert-error">❌ <?= t('ai_test_fail') ?><br>${res.error || 'Check API key and provider settings'}</div>`;
    }
}

function toggleKeyVisibility(btn) {
    const input = btn.previousElementSibling;
    input.type = input.type === 'password' ? 'text' : 'password';
}

function filterUsers() {
    const q = document.getElementById('user-search').value.toLowerCase();
    document.querySelectorAll('#users-table tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

async function viewUser(id) {
    document.getElementById('user-modal').classList.add('open');
    document.getElementById('user-detail-content').innerHTML = 'Loading...';
    const res = await api('../api/admin.php?action=user_detail&id=' + id);
    if (!res.success) { document.getElementById('user-detail-content').innerHTML = '<div class="alert alert-error">Error</div>'; return; }
    const u = res.user, g = res.goal, b = res.last_bmi, pr = res.prefs;
    const gymDays = pr?.gym_days ? JSON.parse(pr.gym_days).join(', ') : '—';
    document.getElementById('user-detail-content').innerHTML = `
        <div class="grid-2" style="gap:10px;margin-bottom:12px">
            <div><strong><?= t('name') ?>:</strong> ${u.name}</div>
            <div><strong><?= t('email') ?>:</strong> ${u.email}</div>
            <div><strong><?= t('age') ?>:</strong> ${u.age||'—'}</div>
            <div><strong><?= t('gender') ?>:</strong> ${u.gender||'—'}</div>
            <div><strong><?= t('height') ?>:</strong> ${u.height||'—'} cm</div>
            <div><strong><?= t('weight') ?>:</strong> ${u.weight||'—'} kg</div>
            <div><strong>Language:</strong> ${(pr?.language||'—').toUpperCase()}</div>
            <div><strong><?= t('fitness_level') ?>:</strong> ${pr?.fitness_level||'—'}</div>
        </div>
        ${b ? `<div class="alert alert-info">BMI: <strong>${b.bmi}</strong> (${b.category}) | ${b.height}cm / ${b.weight}kg</div>` : ''}
        ${g ? `<div class="alert alert-success">Goal: <strong>${g.goal_type}</strong>${g.target_weight?' | Target: '+g.target_weight+'kg':''}</div>` : ''}
        ${pr ? `<div style="margin-top:8px;font-size:.85rem">
            <div>🏋️ Gym: ${gymDays} @ ${pr.gym_time||'—'} (${pr.gym_duration||0} min)</div>
            <div>🍽️ Meals: B${pr.breakfast_time} L${pr.lunch_time} S${pr.snack_time} D${pr.dinner_time}</div>
            ${pr.dietary_notes ? `<div>📋 Diet notes: ${pr.dietary_notes}</div>` : ''}
        </div>` : ''}
        <div style="margin-top:8px"><strong>Food logs:</strong> ${res.food_count}</div>`;
}

async function deleteUser(id, name) {
    if (!confirm(`Delete user "${name}"?`)) return;
    const res = await api('../api/admin.php', { action: 'delete_user', id });
    if (res.success) { toast('Deleted', 'success'); setTimeout(() => location.reload(), 800); }
    else toast(res.message, 'error');
}

async function filterLogs() {
    const date = document.getElementById('log-filter-date').value;
    const res = await api(`../api/admin.php?action=logs_by_date&date=${date}`);
    if (res.success) {
        const mealNames = {breakfast:'<?= t('meal_breakfast') ?>',lunch:'<?= t('meal_lunch') ?>',dinner:'<?= t('meal_dinner') ?>',snack:'<?= t('meal_snack') ?>',other:'<?= t('meal_other') ?>'};
        let html = '<table class="table"><thead><tr><th><?= t('name') ?></th><th><?= t('food_name') ?></th><th><?= t('calories') ?></th><th></th><th></th></tr></thead><tbody>';
        if (res.logs.length) {
            res.logs.forEach(l => { html += `<tr><td>${l.user_name}</td><td>${l.food_name}${l.ai_detected?'<span class="badge badge-success" style="font-size:.7rem;margin-right:4px">AI</span>':''}</td><td>${Math.round(l.calories)}</td><td>${mealNames[l.meal_type]||l.meal_type}</td><td style="font-size:.78rem;color:#999">${(l.created_at||'').substring(11,16)}</td></tr>`; });
        } else { html += '<tr><td colspan="5" class="text-center text-muted"><?= t('no_data') ?></td></tr>'; }
        html += '</tbody></table>';
        document.getElementById('logs-table-wrap').innerHTML = html;
    }
}

function doLogout() {
    fetch('../api/auth.php', { method:'POST', body: new URLSearchParams({action:'logout'}) })
        .then(r=>r.json()).then(r=>{ if(r.success) window.location.href=r.redirect; });
}
</script>
</body>
</html>
