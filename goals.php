<?php
require_once 'config/config.php';
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
require_once 'config/database.php';
$db   = Database::getInstance();
$user = $db->fetch("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);
$lastBmi = $db->fetch("SELECT * FROM bmi_records WHERE user_id=? ORDER BY recorded_at DESC LIMIT 1", [$_SESSION['user_id']]);
$activeGoal = $db->fetch("SELECT * FROM goals WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 1", [$_SESSION['user_id']]);
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الهدف - FitTrack AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <a class="navbar-brand" href="dashboard.php">🥗 <span>FitTrack AI</span></a>
    <ul class="nav-links">
        <li><a href="dashboard.php">الرئيسية</a></li>
        <li><a href="bmi.php">BMI</a></li>
        <li><a href="goals.php" class="active">الهدف</a></li>
        <li><a href="tracker.php">التتبع</a></li>
        <li><a href="#" onclick="logout()">خروج</a></li>
    </ul>
</nav>

<div class="container">
    <h1 class="page-title">🎯 تحديد الهدف</h1>

    <div class="grid-2">
        <!-- Goal Form -->
        <div class="card">
            <div class="card-header">🎯 هدفك الجديد</div>

            <?php if (!$lastBmi): ?>
            <div class="alert alert-warning">
                ⚠️ لازم تحسب BMI الأول!
                <a href="bmi.php" class="btn btn-sm btn-accent mt-16">احسب BMI</a>
            </div>
            <?php endif; ?>

            <form id="goal-form" onsubmit="saveGoal(event)">
                <div class="form-group">
                    <label class="form-label">نوع الهدف</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:8px">
                        <label class="goal-option" data-val="lose">
                            <input type="radio" name="goal_type" value="lose" <?= ($activeGoal['goal_type']??'')==='lose'?'checked':'' ?>>
                            <div class="goal-box">
                                <span style="font-size:1.5rem">📉</span><br>
                                <strong>تنزيل وزن</strong>
                            </div>
                        </label>
                        <label class="goal-option" data-val="maintain">
                            <input type="radio" name="goal_type" value="maintain" <?= ($activeGoal['goal_type']??'maintain')==='maintain'?'checked':'' ?>>
                            <div class="goal-box">
                                <span style="font-size:1.5rem">⚖️</span><br>
                                <strong>ثبات الوزن</strong>
                            </div>
                        </label>
                        <label class="goal-option" data-val="gain">
                            <input type="radio" name="goal_type" value="gain" <?= ($activeGoal['goal_type']??'')==='gain'?'checked':'' ?>>
                            <div class="goal-box">
                                <span style="font-size:1.5rem">📈</span><br>
                                <strong>زيادة وزن</strong>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">الوزن المستهدف (كجم)</label>
                        <input type="number" class="form-control" name="target_weight"
                               value="<?= $activeGoal['target_weight'] ?? '' ?>"
                               placeholder="70" step=".1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">السعرات اليومية المستهدفة</label>
                        <input type="number" class="form-control" name="target_calories" id="target-cal"
                               value="<?= $activeGoal['target_calories'] ?? '2000' ?>"
                               placeholder="2000">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">التاريخ المستهدف</label>
                    <input type="date" class="form-control" name="target_date"
                           value="<?= $activeGoal['target_date'] ?? '' ?>"
                           min="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">ملاحظات (اختياري)</label>
                    <textarea class="form-control" name="notes" rows="2" placeholder="أهداف إضافية..."><?= $activeGoal['notes'] ?? '' ?></textarea>
                </div>

                <button type="button" class="btn btn-secondary btn-block" onclick="getAiPlan()" id="ai-btn">
                    🤖 احصل على خطة AI مجانية
                </button>
                <div id="ai-plan-box" class="hidden mt-16">
                    <div class="ai-response" id="ai-plan-text"></div>
                    <input type="hidden" name="ai_plan" id="ai-plan-hidden">
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg mt-16" id="save-btn">
                    💾 حفظ الهدف
                </button>
            </form>
        </div>

        <!-- Current Goal + Tips -->
        <div>
            <?php if ($activeGoal): ?>
            <div class="card">
                <div class="card-header">✅ هدفك الحالي</div>
                <?php
                $goalIcons = ['lose' => '📉', 'maintain' => '⚖️', 'gain' => '📈'];
                $goalAr    = ['lose' => 'إنقاص الوزن', 'maintain' => 'ثبات الوزن', 'gain' => 'زيادة الوزن'];
                ?>
                <div style="text-align:center;padding:16px 0">
                    <div style="font-size:3rem"><?= $goalIcons[$activeGoal['goal_type']] ?? '🎯' ?></div>
                    <div style="font-size:1.3rem;font-weight:700;margin:8px 0"><?= $goalAr[$activeGoal['goal_type']] ?? '' ?></div>
                    <?php if ($activeGoal['target_weight']): ?>
                    <div class="text-muted">الوزن المستهدف: <strong><?= $activeGoal['target_weight'] ?> كجم</strong></div>
                    <?php endif; ?>
                    <?php if ($activeGoal['target_calories']): ?>
                    <div class="text-muted">السعرات اليومية: <strong><?= $activeGoal['target_calories'] ?> سعرة</strong></div>
                    <?php endif; ?>
                    <?php if ($activeGoal['target_date']): ?>
                    <div class="text-muted">الهدف في: <strong><?= date('d/m/Y', strtotime($activeGoal['target_date'])) ?></strong></div>
                    <?php endif; ?>
                </div>

                <?php if ($activeGoal['ai_plan']): ?>
                <details>
                    <summary style="cursor:pointer;padding:8px;background:#f5f5f5;border-radius:8px;font-weight:600">🤖 خطة AI المحفوظة</summary>
                    <div class="ai-response mt-16"><?= nl2br(htmlspecialchars($activeGoal['ai_plan'])) ?></div>
                </details>
                <?php endif; ?>

                <a href="tracker.php" class="btn btn-primary btn-block mt-16">📊 ابدأ التتبع</a>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">💡 نصائح السعرات</div>
                <div id="cal-suggestions" style="line-height:2;color:#555;font-size:.9rem">
                    <?php if ($lastBmi): ?>
                    جاري حساب التوصيات...
                    <script>
                    window.userWeight = <?= (float)($user['weight'] ?? 70) ?>;
                    window.userHeight = <?= (float)($user['height'] ?? 170) ?>;
                    window.userAge    = <?= (int)($user['age'] ?? 25) ?>;
                    window.userGender = '<?= $user['gender'] ?? 'male' ?>';
                    window.userActivity = '<?= $user['activity_level'] ?? 'moderate' ?>';
                    </script>
                    <?php else: ?>
                    <a href="bmi.php">احسب BMI أولاً للحصول على توصيات مخصصة</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.goal-option input { display:none; }
.goal-box {
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 12px 8px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: #fff;
}
.goal-option input:checked + .goal-box {
    border-color: var(--primary);
    background: #e8f5e9;
}
textarea.form-control { resize: vertical; font-family: inherit; }
details summary { list-style: none; }
</style>

<script src="assets/js/app.js"></script>
<script>
// Calculate calorie suggestion based on goal type
document.querySelectorAll('input[name="goal_type"]').forEach(r => {
    r.addEventListener('change', updateCalSuggestion);
});

function updateCalSuggestion() {
    if (typeof window.userWeight === 'undefined') return;
    const h = window.userHeight, w = window.userWeight, a = window.userAge, g = window.userGender;
    const bmr = g === 'male' ? (10*w + 6.25*h - 5*a + 5) : (10*w + 6.25*h - 5*a - 161);
    const actMap = {sedentary:1.2, light:1.375, moderate:1.55, active:1.725, very_active:1.9};
    const tdee = Math.round(bmr * (actMap[window.userActivity] || 1.55));
    const goalType = document.querySelector('input[name="goal_type"]:checked')?.value || 'maintain';

    const suggestedCal = goalType === 'lose' ? tdee - 500 : goalType === 'gain' ? tdee + 300 : tdee;
    document.getElementById('target-cal').value = suggestedCal;

    const html = `
        <div>🔥 <strong>TDEE:</strong> ${tdee} سعرة/يوم</div>
        <div>${goalType === 'lose' ? '📉 لإنقاص الوزن: TDEE - 500 =' : goalType === 'gain' ? '📈 لزيادة الوزن: TDEE + 300 =' : '⚖️ للثبات: TDEE ='} <strong>${suggestedCal} سعرة</strong></div>
        <div class="text-muted" style="font-size:.8rem;margin-top:8px">يمكنك تعديل الرقم حسب راحتك</div>
    `;
    document.getElementById('cal-suggestions').innerHTML = html;
}

window.addEventListener('load', updateCalSuggestion);

async function getAiPlan() {
    const btn = document.getElementById('ai-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> الذكاء الاصطناعي بيفكر...';

    const goalType = document.querySelector('input[name="goal_type"]:checked')?.value || 'maintain';
    const targetW  = document.querySelector('[name="target_weight"]').value;

    const res = await api('api/ai.php', { action: 'plan', goal_type: goalType, target_weight: targetW });

    btn.disabled = false;
    btn.innerHTML = '🤖 احصل على خطة AI مجانية';

    if (res.success) {
        document.getElementById('ai-plan-text').textContent = res.plan;
        document.getElementById('ai-plan-hidden').value = res.plan;
        document.getElementById('ai-plan-box').classList.remove('hidden');
    } else {
        toast(res.message || 'خطأ في AI', 'error');
    }
}

async function saveGoal(e) {
    e.preventDefault();
    const btn = document.getElementById('save-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';

    const fd = new FormData(e.target);
    fd.append('action', 'save');
    const res = await api('api/goals.php', fd, true);

    btn.disabled = false;
    btn.innerHTML = '💾 حفظ الهدف';

    if (res.success) {
        toast('تم حفظ الهدف ✅', 'success');
        setTimeout(() => window.location.href = 'tracker.php', 1200);
    } else {
        toast(res.message, 'error');
    }
}

function logout() {
    api('api/auth.php', {action:'logout'}).then(r => { if(r.success) window.location.href = r.redirect; });
}
</script>
</body>
</html>
