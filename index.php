<?php
require_once 'config/config.php';
if (!empty($_SESSION['user_id'])) {
    $redirect = ($_SESSION['user_role'] ?? '') === 'admin' ? 'admin/index.php' : 'dashboard.php';
    header('Location: ' . $redirect);
    exit;
}
// Init i18n without DB (not logged in yet)
i18n_init();
$lang = currentLang(); $dir = langDir();
?><!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('app_name') ?> - <?= t('app_tagline') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?= jsTranslations() ?>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Language switcher on login page -->
        <div style="text-align:<?= isRtl()?'left':'right' ?>;margin-bottom:12px">
            <a href="?lang=ar" style="text-decoration:none;padding:4px 8px;border-radius:8px;font-size:.82rem;<?= $lang==='ar'?'background:var(--primary);color:#fff':'color:#666' ?>">🇸🇦</a>
            <a href="?lang=en" style="text-decoration:none;padding:4px 8px;border-radius:8px;font-size:.82rem;<?= $lang==='en'?'background:var(--primary);color:#fff':'color:#666' ?>">🇬🇧</a>
            <a href="?lang=de" style="text-decoration:none;padding:4px 8px;border-radius:8px;font-size:.82rem;<?= $lang==='de'?'background:var(--primary);color:#fff':'color:#666' ?>">🇩🇪</a>
        </div>
        <div class="auth-logo">
            <div class="logo-icon">🥗</div>
            <h1><?= t('app_name') ?></h1>
            <p><?= t('app_tagline') ?></p>
        </div>

        <div id="alert-box"></div>

        <!-- Tabs -->
        <div class="auth-tabs">
            <div class="auth-tab active" onclick="switchTab('login')"><?= t('login') ?></div>
            <div class="auth-tab" onclick="switchTab('register')"><?= t('new_account') ?></div>
        </div>

        <!-- Login Form -->
        <form id="login-form" onsubmit="doLogin(event)">
            <div class="form-group">
                <label class="form-label">البريد الإلكتروني</label>
                <input type="email" class="form-control" name="email" placeholder="example@email.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور</label>
                <input type="password" class="form-control" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">
                <span id="login-text">دخول</span>
            </button>
            <p class="text-center mt-16" style="font-size:.85rem;color:#666">
                admin@fittrack.com / admin123
            </p>
        </form>

        <!-- Register Form -->
        <form id="register-form" class="hidden" onsubmit="doRegister(event)">
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">الاسم</label>
                    <input type="text" class="form-control" name="name" placeholder="اسمك" required>
                </div>
                <div class="form-group">
                    <label class="form-label">السن</label>
                    <input type="number" class="form-control" name="age" placeholder="25" min="10" max="100">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">البريد الإلكتروني</label>
                <input type="email" class="form-control" name="email" placeholder="example@email.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور (6 أحرف على الأقل)</label>
                <input type="password" class="form-control" name="password" placeholder="••••••••" required minlength="6">
            </div>
            <div class="form-group">
                <label class="form-label">الجنس</label>
                <select class="form-control" name="gender">
                    <option value="">اختر</option>
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">إنشاء الحساب</button>
        </form>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script>
function switchTab(tab) {
    document.querySelectorAll('.auth-tab').forEach((t,i) => t.classList.toggle('active', (i===0&&tab==='login')||(i===1&&tab==='register')));
    document.getElementById('login-form').classList.toggle('hidden', tab !== 'login');
    document.getElementById('register-form').classList.toggle('hidden', tab !== 'register');
    document.getElementById('alert-box').innerHTML = '';
}

async function doLogin(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';
    const fd = new FormData(e.target);
    fd.append('action', 'login');
    const res = await api('api/auth.php', fd, true);
    btn.disabled = false;
    btn.innerHTML = 'دخول';
    if (res.success) {
        window.location.href = res.redirect;
    } else {
        document.getElementById('alert-box').innerHTML = `<div class="alert alert-error">${res.message}</div>`;
    }
}

async function doRegister(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';
    const fd = new FormData(e.target);
    fd.append('action', 'register');
    const res = await api('api/auth.php', fd, true);
    btn.disabled = false;
    btn.innerHTML = 'إنشاء الحساب';
    if (res.success) {
        window.location.href = res.redirect;
    } else {
        document.getElementById('alert-box').innerHTML = `<div class="alert alert-error">${res.message}</div>`;
    }
}
</script>
</body>
</html>
