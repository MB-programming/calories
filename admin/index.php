<?php
require_once '../config/config.php';
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}
require_once '../config/database.php';
$db = Database::getInstance();

// Stats
$totalUsers   = $db->fetch("SELECT COUNT(*) as c FROM users WHERE role='user'")['c'];
$todayLogs    = $db->fetch("SELECT COUNT(*) as c FROM food_logs WHERE log_date=?", [date('Y-m-d')])['c'];
$totalLogs    = $db->fetch("SELECT COUNT(*) as c FROM food_logs")['c'];
$activeGoals  = $db->fetch("SELECT COUNT(*) as c FROM goals WHERE status='active'")['c'];
$bmiRecords   = $db->fetch("SELECT COUNT(*) as c FROM bmi_records")['c'];
$avgBmi       = $db->fetch("SELECT ROUND(AVG(bmi),1) as a FROM bmi_records")['a'];

$users = $db->fetchAll("
    SELECT u.*,
        (SELECT bmi FROM bmi_records WHERE user_id=u.id ORDER BY recorded_at DESC LIMIT 1) as last_bmi,
        (SELECT goal_type FROM goals WHERE user_id=u.id AND status='active' ORDER BY created_at DESC LIMIT 1) as active_goal,
        (SELECT COUNT(*) FROM food_logs WHERE user_id=u.id AND log_date=?) as logs_today,
        (SELECT COUNT(*) FROM food_logs WHERE user_id=u.id) as total_logs
    FROM users u WHERE u.role='user' ORDER BY u.created_at DESC
", [date('Y-m-d')]);

$recentLogs = $db->fetchAll("
    SELECT fl.*, u.name as user_name
    FROM food_logs fl
    JOIN users u ON u.id = fl.user_id
    ORDER BY fl.created_at DESC LIMIT 20
");
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الإدارة - FitTrack AI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    .admin-layout { display: flex; min-height: 100vh; }
    .sidebar {
        width: 240px; flex-shrink: 0;
        background: #1a1a2e;
        color: #fff;
        padding: 0;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
    }
    .sidebar-logo {
        padding: 24px 20px;
        font-size: 1.3rem;
        font-weight: 800;
        color: #4CAF50;
        border-bottom: 1px solid rgba(255,255,255,.1);
    }
    .sidebar-menu { padding: 16px 0; }
    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 20px;
        color: rgba(255,255,255,.7);
        text-decoration: none;
        font-size: .9rem;
        transition: all .2s;
    }
    .sidebar-menu a:hover, .sidebar-menu a.active {
        background: rgba(76,175,80,.2);
        color: #4CAF50;
        border-right: 3px solid #4CAF50;
    }
    .admin-content { flex: 1; padding: 24px; background: var(--bg); overflow-x: hidden; }
    .page-header { margin-bottom: 24px; }
    .page-header h1 { font-size: 1.6rem; font-weight: 700; }
    .page-header p { color: var(--text-muted); font-size: .9rem; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    </style>
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">🥗 FitTrack AI</div>
        <div class="sidebar-menu">
            <a href="#" class="active" onclick="showTab('overview', this)">📊 نظرة عامة</a>
            <a href="#" onclick="showTab('users', this)">👥 المستخدمين</a>
            <a href="#" onclick="showTab('logs', this)">🍽️ السجلات</a>
            <a href="#" onclick="showTab('settings', this)">⚙️ الإعدادات</a>
            <hr style="border-color:rgba(255,255,255,.1);margin:12px 0">
            <a href="../dashboard.php">🏠 الموقع الرئيسي</a>
            <a href="#" onclick="logout()">🚪 خروج</a>
        </div>
    </div>

    <!-- Content -->
    <div class="admin-content">
        <div class="page-header">
            <h1>لوحة الإدارة</h1>
            <p>مرحباً، <?= htmlspecialchars($_SESSION['user_name']) ?> | <?= date('l، d F Y') ?></p>
        </div>

        <!-- ── Overview Tab ── -->
        <div class="tab-content active" id="tab-overview">
            <div class="grid-3" style="margin-bottom:20px">
                <div class="stat-card">
                    <div class="stat-value"><?= $totalUsers ?></div>
                    <div class="stat-label">إجمالي المستخدمين</div>
                </div>
                <div class="stat-card" style="border-color:#2196F3">
                    <div class="stat-value" style="color:#2196F3"><?= $todayLogs ?></div>
                    <div class="stat-label">سجلات اليوم</div>
                </div>
                <div class="stat-card" style="border-color:#FF9800">
                    <div class="stat-value" style="color:#FF9800"><?= $totalLogs ?></div>
                    <div class="stat-label">إجمالي سجلات الطعام</div>
                </div>
                <div class="stat-card" style="border-color:#9C27B0">
                    <div class="stat-value" style="color:#9C27B0"><?= $activeGoals ?></div>
                    <div class="stat-label">أهداف نشطة</div>
                </div>
                <div class="stat-card" style="border-color:#E91E63">
                    <div class="stat-value" style="color:#E91E63"><?= $bmiRecords ?></div>
                    <div class="stat-label">قياسات BMI</div>
                </div>
                <div class="stat-card" style="border-color:#009688">
                    <div class="stat-value" style="color:#009688"><?= $avgBmi ?? '—' ?></div>
                    <div class="stat-label">متوسط BMI</div>
                </div>
            </div>

            <!-- BMI Distribution -->
            <div class="card">
                <div class="card-header">📊 توزيع BMI بين المستخدمين</div>
                <?php
                $bmiDist = $db->fetchAll("
                    SELECT category, COUNT(*) as cnt
                    FROM (SELECT DISTINCT user_id, category FROM bmi_records GROUP BY user_id HAVING recorded_at=MAX(recorded_at))
                    GROUP BY category
                ");
                $catColors = ['Underweight'=>'#2196F3','Normal weight'=>'#4CAF50','Overweight'=>'#FF9800','Obese'=>'#f44336'];
                $catAr     = ['Underweight'=>'نقص وزن','Normal weight'=>'وزن طبيعي','Overweight'=>'زيادة وزن','Obese'=>'سمنة'];
                $total = array_sum(array_column($bmiDist, 'cnt'));
                if ($bmiDist && $total > 0):
                ?>
                <div style="display:flex;gap:8px;align-items:flex-end;height:100px;margin-bottom:12px">
                    <?php foreach ($bmiDist as $b):
                        $h = round(($b['cnt'] / $total) * 100);
                        $color = $catColors[$b['category']] ?? '#999';
                    ?>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                        <div style="font-size:.7rem;color:#666"><?= $b['cnt'] ?></div>
                        <div style="width:100%;height:<?= $h ?>px;background:<?= $color ?>;border-radius:4px 4px 0 0;min-height:4px"></div>
                        <div style="font-size:.7rem;color:#666;text-align:center"><?= $catAr[$b['category']] ?? $b['category'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-center text-muted">لا يوجد بيانات كافية</p>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">⚡ آخر النشاطات</div>
                <?php if ($recentLogs): ?>
                <table class="table">
                    <thead>
                        <tr><th>المستخدم</th><th>الأكلة</th><th>السعرات</th><th>التاريخ</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($recentLogs, 0, 10) as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['user_name']) ?></td>
                            <td><?= htmlspecialchars($log['food_name']) ?>
                                <?= $log['ai_detected'] ? '<span class="badge badge-success" style="font-size:.7rem">AI</span>' : '' ?>
                            </td>
                            <td><?= round($log['calories']) ?></td>
                            <td style="font-size:.8rem;color:#666"><?= date('d/m H:i', strtotime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-center text-muted">لا يوجد نشاط بعد</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Users Tab ── -->
        <div class="tab-content" id="tab-users">
            <div class="card">
                <div class="card-header flex-between">
                    <span>👥 المستخدمين (<?= count($users) ?>)</span>
                    <input type="text" class="form-control" style="width:200px" placeholder="بحث..." id="user-search" oninput="filterUsers()">
                </div>
                <table class="table" id="users-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>الإيميل</th>
                            <th>BMI</th>
                            <th>الهدف</th>
                            <th>سجلات اليوم</th>
                            <th>إجمالي السجلات</th>
                            <th>تاريخ التسجيل</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $goalIcons = ['lose' => '📉', 'maintain' => '⚖️', 'gain' => '📈'];
                        $bmiCat = null;
                        if ($u['last_bmi']) {
                            $bmiCat = match(true) {
                                $u['last_bmi'] < 18.5 => ['badge-info','نقص'],
                                $u['last_bmi'] < 25   => ['badge-success','طبيعي'],
                                $u['last_bmi'] < 30   => ['badge-warning','زيادة'],
                                default               => ['badge-danger','سمنة'],
                            };
                        }
                    ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                            <td style="font-size:.85rem"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php if ($u['last_bmi'] && $bmiCat): ?>
                                <span class="badge <?= $bmiCat[0] ?>"><?= $u['last_bmi'] ?> (<?= $bmiCat[1] ?>)</span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= isset($goalIcons[$u['active_goal']]) ? $goalIcons[$u['active_goal']] : '—' ?></td>
                            <td>
                                <span class="badge <?= $u['logs_today'] > 0 ? 'badge-success' : 'badge-warning' ?>">
                                    <?= $u['logs_today'] ?>
                                </span>
                            </td>
                            <td><?= $u['total_logs'] ?></td>
                            <td style="font-size:.8rem;color:#666"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline" onclick="viewUser(<?= $u['id'] ?>)">عرض</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name']) ?>')">حذف</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Logs Tab ── -->
        <div class="tab-content" id="tab-logs">
            <div class="card">
                <div class="card-header flex-between">
                    <span>🍽️ آخر سجلات الطعام</span>
                    <input type="date" class="form-control" style="width:auto" id="log-filter-date"
                           value="<?= date('Y-m-d') ?>" onchange="filterLogs()">
                </div>
                <div id="logs-table-wrap">
                    <?php include 'partials/logs_table.php'; ?>
                </div>
            </div>
        </div>

        <!-- ── Settings Tab ── -->
        <div class="tab-content" id="tab-settings">
            <div class="card">
                <div class="card-header">⚙️ إعدادات النظام</div>
                <div class="alert alert-info">
                    <strong>🔑 Gemini AI API Key</strong><br>
                    لتفعيل الذكاء الاصطناعي، احصل على مفتاح مجاني من:
                    <a href="https://aistudio.google.com/app/apikey" target="_blank">https://aistudio.google.com/app/apikey</a>
                    <br><br>
                    ثم عدّل الملف: <code>config/config.php</code><br>
                    <code>define('GEMINI_API_KEY', 'YOUR_KEY_HERE');</code>
                    <br><br>
                    أو ضع المتغير في بيئة التشغيل:<br>
                    <code>export GEMINI_API_KEY=your_key_here</code>
                </div>
                <div style="margin-top:16px">
                    <div class="card-header">📦 معلومات النظام</div>
                    <table class="table">
                        <tr><td>PHP Version</td><td><?= PHP_VERSION ?></td></tr>
                        <tr><td>SQLite Version</td><td><?= SQLite3::version()['versionString'] ?></td></tr>
                        <tr><td>حجم قاعدة البيانات</td><td><?= round(filesize(BASE_PATH.'/database/fittrack.db')/1024, 1) ?> KB</td></tr>
                        <tr><td>Gemini AI</td><td><?= GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE' ? '<span class="badge badge-warning">غير مفعّل</span>' : '<span class="badge badge-success">مفعّل</span>' ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- User Detail Modal -->
<div class="modal-overlay" id="user-modal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <span class="modal-title">👤 تفاصيل المستخدم</span>
            <button class="modal-close" onclick="document.getElementById('user-modal').classList.remove('open')">✕</button>
        </div>
        <div id="user-detail-content">جاري التحميل...</div>
    </div>
</div>

<script src="../assets/js/app.js"></script>
<script>
function showTab(name, el) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    el.classList.add('active');
}

function filterUsers() {
    const q = document.getElementById('user-search').value.toLowerCase();
    document.querySelectorAll('#users-table tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

async function viewUser(id) {
    document.getElementById('user-modal').classList.add('open');
    document.getElementById('user-detail-content').innerHTML = 'جاري التحميل...';
    const res = await api('../api/admin.php?action=user_detail&id=' + id);
    if (res.success) {
        const u = res.user;
        const g = res.goal;
        const bmi = res.last_bmi;
        document.getElementById('user-detail-content').innerHTML = `
            <div class="grid-2">
                <div><strong>الاسم:</strong> ${u.name}</div>
                <div><strong>الإيميل:</strong> ${u.email}</div>
                <div><strong>العمر:</strong> ${u.age || '—'}</div>
                <div><strong>الجنس:</strong> ${u.gender === 'male' ? 'ذكر' : u.gender === 'female' ? 'أنثى' : '—'}</div>
                <div><strong>الطول:</strong> ${u.height ? u.height+'سم' : '—'}</div>
                <div><strong>الوزن:</strong> ${u.weight ? u.weight+'كجم' : '—'}</div>
            </div>
            ${bmi ? `<div class="alert alert-info mt-16">📊 آخر BMI: <strong>${bmi.bmi}</strong> (${bmi.category}) | ${bmi.height}سم / ${bmi.weight}كجم</div>` : ''}
            ${g ? `<div class="alert alert-success mt-16">🎯 الهدف النشط: <strong>${g.goal_type}</strong> | ${g.target_weight ? 'هدف وزن: '+g.target_weight+'كجم' : ''} | ${g.target_calories ? 'هدف سعرات: '+g.target_calories : ''}</div>` : ''}
            <div class="mt-16">
                <strong>إجمالي سجلات الطعام:</strong> ${res.food_count}
            </div>
        `;
    } else {
        document.getElementById('user-detail-content').innerHTML = '<div class="alert alert-error">خطأ في جلب البيانات</div>';
    }
}

async function deleteUser(id, name) {
    if (!confirm(`هل تريد حذف المستخدم "${name}"؟ سيتم حذف جميع بياناته.`)) return;
    const res = await api('../api/admin.php', { action: 'delete_user', id });
    if (res.success) { toast('تم الحذف', 'success'); setTimeout(() => location.reload(), 1000); }
    else toast(res.message, 'error');
}

async function filterLogs() {
    const date = document.getElementById('log-filter-date').value;
    const res  = await api(`../api/admin.php?action=logs_by_date&date=${date}`);
    if (res.success) {
        let html = '<table class="table"><thead><tr><th>المستخدم</th><th>الأكلة</th><th>السعرات</th><th>الوجبة</th><th>الوقت</th></tr></thead><tbody>';
        if (res.logs.length) {
            const mealAr = {breakfast:'فطار',lunch:'غدا',dinner:'عشا',snack:'سناك',other:'أخرى'};
            res.logs.forEach(l => {
                html += `<tr><td>${l.user_name}</td><td>${l.food_name} ${l.ai_detected?'<span class="badge badge-success" style="font-size:.7rem">AI</span>':''}</td><td>${Math.round(l.calories)}</td><td>${mealAr[l.meal_type]||l.meal_type}</td><td>${l.created_at.split(' ')[1]?.substring(0,5)}</td></tr>`;
            });
        } else {
            html += '<tr><td colspan="5" class="text-center text-muted">لا يوجد سجلات في هذا اليوم</td></tr>';
        }
        html += '</tbody></table>';
        document.getElementById('logs-table-wrap').innerHTML = html;
    }
}

function logout() {
    api('../api/auth.php', {action:'logout'}).then(r => { if(r.success) window.location.href = r.redirect; });
}
</script>
</body>
</html>
