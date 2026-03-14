<?php
require_once 'config/config.php';
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
require_once 'config/database.php';
$db   = Database::getInstance();
$user = $db->fetch("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);
$lastBmi = $db->fetch("SELECT * FROM bmi_records WHERE user_id=? ORDER BY recorded_at DESC LIMIT 1", [$_SESSION['user_id']]);
$activeGoal = $db->fetch("SELECT * FROM goals WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 1", [$_SESSION['user_id']]);
$todaySummary = $db->fetch("SELECT * FROM daily_summaries WHERE user_id=? AND summary_date=?", [$_SESSION['user_id'], date('Y-m-d')]);
$weekData = $db->fetchAll(
    "SELECT summary_date, total_calories, target_calories FROM daily_summaries
     WHERE user_id=? AND summary_date >= date('now','-6 days') ORDER BY summary_date",
    [$_SESSION['user_id']]
);
$goalAr = ['lose' => 'إنقاص الوزن', 'maintain' => 'ثبات الوزن', 'gain' => 'زيادة الوزن'];
$todayCal   = (float)($todaySummary['total_calories'] ?? 0);
$targetCal  = (int)($activeGoal['target_calories'] ?? 2000);
$pct        = $targetCal > 0 ? min(round(($todayCal / $targetCal) * 100), 100) : 0;
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - FitTrack AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <a class="navbar-brand" href="dashboard.php">🥗 <span>FitTrack AI</span></a>
    <ul class="nav-links">
        <li><a href="dashboard.php" class="active">الرئيسية</a></li>
        <li><a href="bmi.php">BMI</a></li>
        <li><a href="goals.php">الهدف</a></li>
        <li><a href="tracker.php">التتبع</a></li>
        <li><a href="#" onclick="logout()">خروج</a></li>
    </ul>
</nav>

<div class="container">
    <!-- Greeting -->
    <div class="card" style="background:linear-gradient(135deg,#4CAF50,#2196F3);color:#fff;border-radius:var(--radius)">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
            <div>
                <h2 style="margin-bottom:4px">مرحباً، <?= htmlspecialchars($user['name']) ?>! 👋</h2>
                <p style="opacity:.9;font-size:.95rem"><?= date('l، d F Y') ?></p>
                <?php if ($activeGoal): ?>
                <p style="opacity:.85;font-size:.85rem;margin-top:4px">
                    🎯 هدفك: <?= $goalAr[$activeGoal['goal_type']] ?? '' ?>
                    <?= $activeGoal['target_weight'] ? " | الوزن المستهدف: {$activeGoal['target_weight']}كجم" : '' ?>
                </p>
                <?php endif; ?>
            </div>
            <a href="tracker.php" class="btn" style="background:rgba(255,255,255,.25);color:#fff;border:2px solid rgba(255,255,255,.5)">
                ➕ سجّل وجبة
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid-4">
        <div class="stat-card">
            <div class="stat-value"><?= $lastBmi ? $lastBmi['bmi'] : '—' ?></div>
            <div class="stat-label">BMI الحالي</div>
        </div>
        <div class="stat-card" style="border-color:#2196F3">
            <div class="stat-value" style="color:#2196F3"><?= $user['weight'] ? $user['weight'].'كجم' : '—' ?></div>
            <div class="stat-label">الوزن</div>
        </div>
        <div class="stat-card" style="border-color:#FF9800">
            <div class="stat-value" style="color:#FF9800"><?= round($todayCal) ?></div>
            <div class="stat-label">سعرات اليوم</div>
        </div>
        <div class="stat-card" style="border-color:#9C27B0">
            <div class="stat-value" style="color:#9C27B0"><?= $targetCal ?></div>
            <div class="stat-label">الهدف اليومي</div>
        </div>
    </div>

    <div class="grid-2">
        <!-- Today Progress -->
        <div class="card">
            <div class="card-header">🔥 تقدم اليوم</div>
            <div style="display:flex;align-items:center;gap:20px">
                <div class="calorie-ring" style="flex-shrink:0">
                    <svg width="120" height="120" viewBox="0 0 140 140">
                        <circle cx="70" cy="70" r="58" fill="none" stroke="#e0e0e0" stroke-width="14"/>
                        <circle cx="70" cy="70" r="58" fill="none" stroke="<?= $todayCal > $targetCal ? '#f44336' : '#4CAF50' ?>"
                                stroke-width="14" stroke-dasharray="<?= ($pct/100)*364.4 ?> 364.4"
                                stroke-linecap="round" transform="rotate(-90 70 70)"/>
                    </svg>
                    <div class="ring-text">
                        <div class="ring-calories" style="font-size:1.1rem"><?= round($todayCal) ?></div>
                        <div class="ring-label">سعرة</div>
                    </div>
                </div>
                <div style="flex:1">
                    <div style="font-size:1.3rem;font-weight:700;color:<?= $todayCal > $targetCal ? '#f44336' : '#4CAF50' ?>">
                        <?= $pct ?>%
                    </div>
                    <div class="text-muted" style="font-size:.85rem;margin:4px 0">من هدف <?= $targetCal ?> سعرة</div>
                    <?php
                    $rem = $targetCal - $todayCal;
                    if ($rem >= 0):
                    ?>
                    <div style="color:#4CAF50;font-size:.9rem">✅ متبقي <?= round($rem) ?> سعرة</div>
                    <?php else: ?>
                    <div style="color:#f44336;font-size:.9rem">⚠️ تجاوزت الهدف بـ <?= round(-$rem) ?> سعرة</div>
                    <?php endif; ?>

                    <div style="margin-top:12px;font-size:.85rem">
                        <div>🥩 بروتين: <?= round($todaySummary['total_protein'] ?? 0) ?>ج</div>
                        <div>🍞 كارب: <?= round($todaySummary['total_carbs'] ?? 0) ?>ج</div>
                        <div>🥑 دهون: <?= round($todaySummary['total_fat'] ?? 0) ?>ج</div>
                    </div>
                </div>
            </div>
            <a href="tracker.php" class="btn btn-primary btn-block mt-16">📊 تفاصيل اليوم</a>
        </div>

        <!-- Weekly Chart -->
        <div class="card">
            <div class="card-header">📈 أسبوع أخير</div>
            <div style="display:flex;align-items:flex-end;gap:6px;height:140px;padding-bottom:20px;position:relative">
                <?php
                // Build 7-day map
                $dayMap = [];
                foreach ($weekData as $d) $dayMap[$d['summary_date']] = $d;
                $maxCal = 0;
                for ($i = 6; $i >= 0; $i--) {
                    $dt = date('Y-m-d', strtotime("-$i days"));
                    $cal = (float)($dayMap[$dt]['total_calories'] ?? 0);
                    if ($cal > $maxCal) $maxCal = $cal;
                }
                if ($maxCal < 1) $maxCal = 2000;
                for ($i = 6; $i >= 0; $i--):
                    $dt    = date('Y-m-d', strtotime("-$i days"));
                    $cal   = (float)($dayMap[$dt]['total_calories'] ?? 0);
                    $tCal  = (int)($dayMap[$dt]['target_calories'] ?? $targetCal);
                    $h     = $maxCal > 0 ? round(($cal / $maxCal) * 120) : 0;
                    $color = ($cal > $tCal && $cal > 0) ? '#f44336' : '#4CAF50';
                    $day   = date('D', strtotime($dt));
                    $dayAr = ['Sun'=>'أح','Mon'=>'إث','Tue'=>'ث','Wed'=>'أر','Thu'=>'خ','Fri'=>'ج','Sat'=>'س'];
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                    <div style="font-size:.65rem;color:#999"><?= $cal ? round($cal) : '' ?></div>
                    <div style="width:100%;height:<?= $h ?>px;background:<?= $color ?>;border-radius:4px 4px 0 0;min-height:<?= $cal ? '4' : '0' ?>px;transition:height .4s"></div>
                    <div style="font-size:.7rem;color:#666"><?= $dayAr[$day] ?? $day ?></div>
                </div>
                <?php endfor; ?>
                <!-- Target line -->
                <?php if ($targetCal && $maxCal > 0): $lineH = round(($targetCal / $maxCal) * 120); ?>
                <div style="position:absolute;right:0;left:0;bottom:<?= $lineH + 20 ?>px;height:1px;background:#FF9800;border-top:2px dashed #FF9800;opacity:.7"></div>
                <?php endif; ?>
            </div>
            <div style="text-align:center;font-size:.75rem;color:#999">
                <span style="color:#4CAF50">■</span> في الهدف
                <span style="color:#f44336;margin-right:10px">■</span> تجاوز الهدف
                <span style="color:#FF9800;margin-right:10px">- -</span> الهدف
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid-3">
        <a href="bmi.php" class="card" style="text-decoration:none;text-align:center;cursor:pointer;transition:transform .2s" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
            <div style="font-size:2.5rem">💪</div>
            <div style="font-weight:700;margin:8px 0">حساب BMI</div>
            <div class="text-muted" style="font-size:.85rem">راجع وزنك ومؤشر كتلة الجسم</div>
        </a>
        <a href="goals.php" class="card" style="text-decoration:none;text-align:center;cursor:pointer;transition:transform .2s" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
            <div style="font-size:2.5rem">🎯</div>
            <div style="font-weight:700;margin:8px 0">تحديد الهدف</div>
            <div class="text-muted" style="font-size:.85rem">اختار هدفك واحصل على خطة AI</div>
        </a>
        <a href="tracker.php" class="card" style="text-decoration:none;text-align:center;cursor:pointer;transition:transform .2s" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
            <div style="font-size:2.5rem">📷</div>
            <div style="font-weight:700;margin:8px 0">تتبع السعرات</div>
            <div class="text-muted" style="font-size:.85rem">صور أكلتك وسجل سعراتها</div>
        </a>
    </div>

    <?php if (!$lastBmi): ?>
    <div class="alert alert-warning">
        👋 أهلاً! ابدأ بـ <a href="bmi.php" style="color:inherit;font-weight:700">حساب BMI</a> عشان نحدد نقطة البداية ونديلك خطة مناسبة.
    </div>
    <?php endif; ?>
</div>

<script src="assets/js/app.js"></script>
<script>
function logout() {
    api('api/auth.php', {action:'logout'}).then(r => { if(r.success) window.location.href = r.redirect; });
}
</script>
</body>
</html>
