<?php
require_once 'config/config.php';
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
require_once 'config/database.php';
$db   = Database::getInstance();
i18n_init($db);
$user = $db->fetch("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);
$lang = currentLang(); $dir = langDir();
?><!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('bmi_title') ?> - <?= t('app_name') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?= jsTranslations() ?>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <h1 class="page-title">💪 حساب BMI والسعرات</h1>

    <div class="grid-2">
        <!-- Form -->
        <div class="card">
            <div class="card-header">📏 بياناتك الجسمانية</div>
            <form id="bmi-form" onsubmit="calcBmi(event)">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">الطول (سم)</label>
                        <input type="number" class="form-control" name="height" id="height"
                               value="<?= $user['height'] ?? '' ?>" placeholder="170" min="100" max="250" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوزن (كجم)</label>
                        <input type="number" class="form-control" name="weight" id="weight"
                               value="<?= $user['weight'] ?? '' ?>" placeholder="70" min="20" max="300" step=".1" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">العمر</label>
                        <input type="number" class="form-control" name="age" id="age"
                               value="<?= $user['age'] ?? '' ?>" placeholder="25" min="10" max="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الجنس</label>
                        <select class="form-control" name="gender">
                            <option value="male"   <?= ($user['gender']??'')==='male'?'selected':'' ?>>ذكر</option>
                            <option value="female" <?= ($user['gender']??'')==='female'?'selected':'' ?>>أنثى</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">مستوى النشاط</label>
                    <select class="form-control" name="activity_level">
                        <option value="sedentary"   <?= ($user['activity_level']??'')==='sedentary'?'selected':'' ?>>خامل (مكتبي بدون رياضة)</option>
                        <option value="light"       <?= ($user['activity_level']??'')==='light'?'selected':'' ?>>خفيف (1-3 أيام/أسبوع)</option>
                        <option value="moderate"    <?= ($user['activity_level']??'moderate')==='moderate'?'selected':'' ?>>معتدل (3-5 أيام/أسبوع)</option>
                        <option value="active"      <?= ($user['activity_level']??'')==='active'?'selected':'' ?>>نشيط (6-7 أيام/أسبوع)</option>
                        <option value="very_active" <?= ($user['activity_level']??'')==='very_active'?'selected':'' ?>>رياضي محترف</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="calc-btn">
                    احسب BMI والسعرات
                </button>
            </form>
        </div>

        <!-- Results -->
        <div id="results-card" class="card" style="display:none">
            <div class="card-header">📊 النتائج</div>
            <div class="bmi-gauge" id="bmi-gauge"></div>
            <div class="grid-2 mt-16">
                <div class="stat-card">
                    <div class="stat-value" id="bmr-val">-</div>
                    <div class="stat-label">BMR (سعرة أساسية)</div>
                </div>
                <div class="stat-card" style="border-color:#2196F3">
                    <div class="stat-value" style="color:#2196F3" id="tdee-val">-</div>
                    <div class="stat-label">TDEE (سعرات يومية)</div>
                </div>
            </div>
            <div id="bmi-advice" class="alert alert-info mt-16"></div>
            <a href="goals.php" class="btn btn-accent btn-block mt-16">🎯 حدد هدفك</a>
        </div>

        <!-- Show previous result if exists -->
        <?php
        $lastBmi = $db->fetch("SELECT * FROM bmi_records WHERE user_id=? ORDER BY recorded_at DESC LIMIT 1", [$_SESSION['user_id']]);
        if ($lastBmi):
        ?>
        <div id="results-card-static" class="card">
            <div class="card-header">📊 آخر قياس</div>
            <div class="bmi-gauge">
                <?php
                $cat = $lastBmi['category'];
                $catClass = match($cat) {
                    'Underweight' => 'cat-underweight',
                    'Normal weight' => 'cat-normal',
                    'Overweight' => 'cat-overweight',
                    default => 'cat-obese'
                };
                $catAr = match($cat) {
                    'Underweight' => 'نقص في الوزن',
                    'Normal weight' => 'وزن طبيعي',
                    'Overweight' => 'زيادة في الوزن',
                    default => 'سمنة'
                };
                ?>
                <div class="bmi-value"><?= $lastBmi['bmi'] ?></div>
                <div class="bmi-category <?= $catClass ?>"><?= $catAr ?></div>
                <p class="text-muted mt-16" style="font-size:.85rem">
                    <?= $lastBmi['height'] ?>سم • <?= $lastBmi['weight'] ?>كجم<br>
                    <?= date('d/m/Y', strtotime($lastBmi['recorded_at'])) ?>
                </p>
            </div>
            <a href="goals.php" class="btn btn-accent btn-block mt-16">🎯 حدد هدفك</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- History -->
    <div class="card">
        <div class="card-header">📈 سجل القياسات</div>
        <div id="history-table">
            <?php
            $history = $db->fetchAll("SELECT * FROM bmi_records WHERE user_id=? ORDER BY recorded_at DESC LIMIT 8", [$_SESSION['user_id']]);
            if ($history):
            ?>
            <table class="table">
                <thead>
                    <tr><th>التاريخ</th><th>الوزن</th><th>الطول</th><th>BMI</th><th>التصنيف</th></tr>
                </thead>
                <tbody>
                <?php foreach ($history as $r):
                    $catAr2 = match($r['category']) {
                        'Underweight' => 'نقص', 'Normal weight' => 'طبيعي', 'Overweight' => 'زيادة', default => 'سمنة'
                    };
                    $badgeClass = match($r['category']) {
                        'Normal weight' => 'badge-success', 'Underweight' => 'badge-info', 'Overweight' => 'badge-warning', default => 'badge-danger'
                    };
                ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($r['recorded_at'])) ?></td>
                        <td><?= $r['weight'] ?> كجم</td>
                        <td><?= $r['height'] ?> سم</td>
                        <td><strong><?= $r['bmi'] ?></strong></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $catAr2 ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-center text-muted">لا يوجد قياسات سابقة</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script>
async function calcBmi(e) {
    e.preventDefault();
    const btn = document.getElementById('calc-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> جاري الحساب...';

    const fd = new FormData(e.target);
    fd.append('action', 'calculate');
    const res = await api('api/bmi.php', fd, true);

    btn.disabled = false;
    btn.innerHTML = 'احسب BMI والسعرات';

    if (!res.success) { toast(res.message, 'error'); return; }

    const catMap = {
        'Underweight': {ar: 'نقص في الوزن', cls: 'cat-underweight'},
        'Normal weight': {ar: 'وزن طبيعي', cls: 'cat-normal'},
        'Overweight': {ar: 'زيادة في الوزن', cls: 'cat-overweight'},
        'Obese': {ar: 'سمنة', cls: 'cat-obese'},
    };
    const cat = catMap[res.category] || {ar: res.category, cls: 'cat-normal'};

    const adviceMap = {
        'Underweight': '⚠️ وزنك أقل من الطبيعي. ركز على زيادة السعرات والبروتين.',
        'Normal weight': '✅ وزنك مثالي! حافظ على نمط حياتك الصحي.',
        'Overweight': '⚠️ وزنك زيادة شوية. خفف السعرات وزود الحركة.',
        'Obese': '🚨 يستحسن تراجع دكتور وتبدأ رحلة إنقاص الوزن بجدية.',
    };

    document.getElementById('bmi-gauge').innerHTML = `
        <div class="bmi-value">${res.bmi}</div>
        <div class="bmi-category ${cat.cls}">${cat.ar}</div>
        <p class="text-muted mt-16" style="font-size:.85rem">${res.height}سم • ${res.weight}كجم</p>
    `;
    document.getElementById('bmr-val').textContent = res.bmr;
    document.getElementById('tdee-val').textContent = res.tdee;
    document.getElementById('bmi-advice').textContent = adviceMap[res.category] || '';
    document.getElementById('results-card').style.display = 'block';

    const staticCard = document.getElementById('results-card-static');
    if (staticCard) staticCard.style.display = 'none';

    toast('تم الحساب بنجاح', 'success');
}

</script>
</body>
</html>
