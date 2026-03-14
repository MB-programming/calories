<?php
/**
 * Shared Navigation Bar
 * Requires: $db, session started, i18n_init() called
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$isAdmin     = ($_SESSION['user_role'] ?? '') === 'admin';
$lang        = currentLang();
$dir         = langDir();

// Gym schedule check for today notification
$gymToday = false;
if (!empty($_SESSION['user_id'])) {
    $prefs = $db->fetch("SELECT gym_days FROM user_preferences WHERE user_id=?", [$_SESSION['user_id']]);
    if ($prefs && $prefs['gym_days']) {
        $gymDays  = json_decode($prefs['gym_days'], true) ?? [];
        $todayKey = strtolower(date('D')); // mon, tue, ...
        $gymToday = in_array($todayKey, $gymDays);
    }
}
?>
<nav class="navbar" id="main-navbar">
    <a class="navbar-brand" href="<?= $isAdmin ? '../dashboard.php' : 'dashboard.php' ?>">
        🥗 <span><?= t('app_name') ?></span>
    </a>

    <ul class="nav-links">
        <li><a href="<?= $isAdmin ? '../' : '' ?>dashboard.php"
               class="<?= $currentPage==='dashboard.php'?'active':'' ?>"><?= t('nav_home') ?></a></li>
        <li><a href="<?= $isAdmin ? '../' : '' ?>bmi.php"
               class="<?= $currentPage==='bmi.php'?'active':'' ?>"><?= t('nav_bmi') ?></a></li>
        <li><a href="<?= $isAdmin ? '../' : '' ?>goals.php"
               class="<?= $currentPage==='goals.php'?'active':'' ?>"><?= t('nav_goals') ?></a></li>
        <li><a href="<?= $isAdmin ? '../' : '' ?>tracker.php"
               class="<?= $currentPage==='tracker.php'?'active':'' ?>"><?= t('nav_tracker') ?></a></li>
        <li><a href="<?= $isAdmin ? '../' : '' ?>reports.php"
               class="<?= $currentPage==='reports.php'?'active':'' ?>"><?= t('nav_reports') ?></a></li>
        <li>
            <a href="<?= $isAdmin ? '../' : '' ?>profile.php"
               class="<?= $currentPage==='profile.php'?'active':'' ?>">
                <?= t('nav_profile') ?>
                <?php if ($gymToday): ?><span class="gym-dot" title="<?= t('gym_today') ?>">💪</span><?php endif; ?>
            </a>
        </li>
        <?php if ($isAdmin): ?>
        <li><a href="<?= $isAdmin ? '' : '' ?>admin/index.php" class="active"><?= t('nav_admin') ?></a></li>
        <?php elseif (($_SESSION['user_role'] ?? '') === 'admin'): ?>
        <li><a href="admin/index.php"><?= t('nav_admin') ?></a></li>
        <?php endif; ?>

        <!-- Language Switcher -->
        <li class="lang-switcher">
            <div class="lang-btn" onclick="toggleLangMenu()">
                <?= strtoupper($lang) ?> <span>▾</span>
            </div>
            <div class="lang-menu" id="lang-menu">
                <a href="?lang=ar" class="<?= $lang==='ar'?'active':'' ?>">🇸🇦 العربية</a>
                <a href="?lang=en" class="<?= $lang==='en'?'active':'' ?>">🇬🇧 English</a>
                <a href="?lang=de" class="<?= $lang==='de'?'active':'' ?>">🇩🇪 Deutsch</a>
            </div>
        </li>

        <li>
            <a href="#" onclick="doLogout()" style="color:#f44336">
                <?= t('nav_logout') ?>
            </a>
        </li>
    </ul>
</nav>

<style>
.lang-switcher { position: relative; }
.lang-btn {
    padding: 8px 14px;
    border-radius: 20px;
    border: 1.5px solid var(--primary);
    color: var(--primary);
    cursor: pointer;
    font-weight: 600;
    font-size: .85rem;
    user-select: none;
}
.lang-menu {
    display: none;
    position: absolute;
    top: 110%;
    <?= isRtl() ? 'left' : 'right' ?>: 0;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    overflow: hidden;
    z-index: 200;
    min-width: 140px;
}
.lang-menu.open { display: block; }
.lang-menu a {
    display: block;
    padding: 10px 16px;
    color: var(--text);
    text-decoration: none;
    font-size: .9rem;
    transition: background .15s;
}
.lang-menu a:hover { background: #f0f0f0; }
.lang-menu a.active { background: var(--primary); color: #fff; }
.gym-dot { font-size: .8rem; }
</style>

<script>
function toggleLangMenu() {
    document.getElementById('lang-menu').classList.toggle('open');
}
document.addEventListener('click', e => {
    if (!e.target.closest('.lang-switcher')) {
        document.getElementById('lang-menu')?.classList.remove('open');
    }
});
function doLogout() {
    fetch('<?= $isAdmin ? '../' : '' ?>api/auth.php', {
        method:'POST',
        body: new URLSearchParams({action:'logout'})
    }).then(r=>r.json()).then(r=>{ if(r.success) window.location.href=r.redirect; });
}
</script>
