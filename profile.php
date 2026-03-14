<?php
require_once 'config/config.php';
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
require_once 'config/database.php';
$db    = Database::getInstance();
i18n_init($db);
$user  = $db->fetch("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);
$prefs = $db->fetch("SELECT * FROM user_preferences WHERE user_id=?", [$_SESSION['user_id']]);
$gymDays = json_decode($prefs['gym_days'] ?? '[]', true) ?? [];
$dir   = langDir();
$lang  = currentLang();
?><!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('profile_title') ?> - <?= t('app_name') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?= jsTranslations() ?>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <h1 class="page-title">⚙️ <?= t('profile_title') ?></h1>

    <div id="alert-box"></div>

    <form id="profile-form" onsubmit="saveProfile(event)">
    <div class="grid-2">
        <!-- Personal Info -->
        <div class="card">
            <div class="card-header">👤 <?= t('personal_info') ?></div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label"><?= t('name') ?></label>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= t('age') ?></label>
                    <input type="number" class="form-control" name="age" value="<?= $user['age'] ?? '' ?>" min="10" max="100">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label"><?= t('height') ?></label>
                    <input type="number" class="form-control" name="height" value="<?= $user['height'] ?? '' ?>" step=".1">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= t('weight') ?></label>
                    <input type="number" class="form-control" name="weight" value="<?= $user['weight'] ?? '' ?>" step=".1">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label"><?= t('gender') ?></label>
                    <select class="form-control" name="gender">
                        <option value="male"   <?= ($user['gender']??'')==='male'  ?'selected':'' ?>><?= t('male') ?></option>
                        <option value="female" <?= ($user['gender']??'')==='female'?'selected':'' ?>><?= t('female') ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= t('activity_level') ?></label>
                    <select class="form-control" name="activity_level">
                        <?php foreach(['sedentary','light','moderate','active','very_active'] as $a): ?>
                        <option value="<?= $a ?>" <?= ($user['activity_level']??'moderate')===$a?'selected':'' ?>><?= t($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= t('fitness_level') ?></label>
                <select class="form-control" name="fitness_level">
                    <?php foreach(['beginner','intermediate','advanced'] as $f): ?>
                    <option value="<?= $f ?>" <?= ($prefs['fitness_level']??'beginner')===$f?'selected':'' ?>><?= t($f) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= t('dietary_notes') ?> <small class="text-muted">(<?= t('optional') ?>)</small></label>
                <textarea class="form-control" name="dietary_notes" rows="2"
                          placeholder="<?= t('dietary_hint') ?>"><?= htmlspecialchars($prefs['dietary_notes'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Right column: Language + Meal Times -->
        <div>
            <!-- Language -->
            <div class="card">
                <div class="card-header">🌐 <?= t('language_pref') ?></div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                    <?php foreach(['ar'=>'🇸🇦','en'=>'🇬🇧','de'=>'🇩🇪'] as $code => $flag): ?>
                    <label style="cursor:pointer">
                        <input type="radio" name="language" value="<?= $code ?>"
                               <?= ($lang===$code)?'checked':'' ?> style="display:none" class="lang-radio">
                        <div class="lang-card <?= $lang===$code?'active':'' ?>" data-lang="<?= $code ?>">
                            <div style="font-size:1.8rem"><?= $flag ?></div>
                            <div style="font-weight:600;font-size:.9rem"><?= t('lang_'.$code) ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Meal Times -->
            <div class="card">
                <div class="card-header">🍽️ <?= t('meal_times') ?></div>
                <div class="grid-2">
                    <?php
                    $meals = [
                        'breakfast_time' => ['🌅', 'meal_breakfast', '08:00'],
                        'lunch_time'     => ['☀️', 'meal_lunch',     '13:00'],
                        'snack_time'     => ['🍎', 'meal_snack',     '16:00'],
                        'dinner_time'    => ['🌙', 'meal_dinner',    '19:00'],
                    ];
                    foreach ($meals as $field => [$icon, $label, $default]): ?>
                    <div class="form-group">
                        <label class="form-label"><?= $icon ?> <?= t($label) ?></label>
                        <input type="time" class="form-control" name="<?= $field ?>"
                               value="<?= $prefs[$field] ?? $default ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Gym Schedule - Full Width -->
    <div class="card">
        <div class="card-header">
            🏋️ <?= t('gym_schedule') ?>
            <small class="text-muted" style="font-size:.8rem">(<?= t('optional') ?>)</small>
        </div>

        <div style="margin-bottom:16px">
            <label class="form-label"><?= t('gym_days') ?></label>
            <div class="gym-days-grid">
                <?php
                $dayKeys = ['mon','tue','wed','thu','fri','sat','sun'];
                foreach ($dayKeys as $day):
                ?>
                <label class="gym-day-label">
                    <input type="checkbox" name="gym_days[]" value="<?= $day ?>"
                           <?= in_array($day, $gymDays) ? 'checked' : '' ?>>
                    <div class="gym-day-box">
                        <span><?= t('day_'.$day) ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid-3" style="grid-template-columns:1fr 1fr 1fr">
            <div class="form-group">
                <label class="form-label">⏰ <?= t('gym_time') ?></label>
                <input type="time" class="form-control" name="gym_time"
                       value="<?= $prefs['gym_time'] ?? '18:00' ?>">
            </div>
            <div class="form-group">
                <label class="form-label">⏱️ <?= t('gym_duration') ?></label>
                <input type="number" class="form-control" name="gym_duration"
                       value="<?= $prefs['gym_duration'] ?? 60 ?>" min="15" max="180" step="15">
            </div>
            <div style="display:flex;align-items:flex-end;padding-bottom:16px">
                <div id="gym-preview" class="alert alert-info mb-0" style="width:100%;font-size:.85rem">
                    <?php if (!empty($gymDays)): ?>
                    💪 <?= implode(', ', array_map(fn($d) => t('day_'.$d), $gymDays)) ?>
                    <?php else: ?>
                    <?= t('no_gym') ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap">
        <button type="submit" class="btn btn-primary btn-lg" id="save-btn">
            💾 <?= t('save') ?>
        </button>
        <a href="dashboard.php" class="btn btn-outline btn-lg"><?= t('cancel') ?></a>
    </div>
    </form>

    <!-- Change Password -->
    <div class="card" style="margin-top:20px">
        <div class="card-header">🔒 تغيير كلمة المرور</div>
        <form id="pw-form" onsubmit="changePassword(event)" style="max-width:400px">
            <div class="form-group">
                <label class="form-label">كلمة المرور الحالية</label>
                <input type="password" class="form-control" name="current_password" required>
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور الجديدة</label>
                <input type="password" class="form-control" name="new_password" required minlength="6">
            </div>
            <button type="submit" class="btn btn-secondary">تغيير</button>
        </form>
    </div>
</div>

<style>
.lang-card {
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 14px 8px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
}
.lang-card:hover, .lang-card.active {
    border-color: var(--primary);
    background: #e8f5e9;
}
.gym-days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
}
.gym-day-label input { display: none; }
.gym-day-box {
    border: 2px solid var(--border);
    border-radius: 10px;
    padding: 10px 4px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    font-size: .85rem;
}
.gym-day-label input:checked + .gym-day-box {
    border-color: var(--primary);
    background: var(--primary);
    color: #fff;
    font-weight: 700;
}
@media(max-width:600px) {
    .gym-days-grid { grid-template-columns: repeat(4, 1fr); }
}
textarea.form-control { resize: vertical; font-family: inherit; }
</style>

<script src="assets/js/app.js"></script>
<script>
// Language card selection
document.querySelectorAll('.lang-radio').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.lang-card').forEach(c => c.classList.remove('active'));
        r.nextElementSibling.classList.add('active');
    });
});

// Gym day preview
document.querySelectorAll('input[name="gym_days[]"]').forEach(cb => {
    cb.addEventListener('change', updateGymPreview);
});
function updateGymPreview() {
    const checked = [...document.querySelectorAll('input[name="gym_days[]"]:checked')].map(c => c.value);
    const dayNames = {
        mon: '<?= t('day_mon') ?>',
        tue: '<?= t('day_tue') ?>',
        wed: '<?= t('day_wed') ?>',
        thu: '<?= t('day_thu') ?>',
        fri: '<?= t('day_fri') ?>',
        sat: '<?= t('day_sat') ?>',
        sun: '<?= t('day_sun') ?>',
    };
    const preview = document.getElementById('gym-preview');
    if (checked.length) {
        preview.innerHTML = '💪 ' + checked.map(d => dayNames[d]).join(' • ');
        preview.className = 'alert alert-success mb-0';
    } else {
        preview.textContent = '<?= t('no_gym') ?>';
        preview.className = 'alert alert-info mb-0';
    }
}

async function saveProfile(e) {
    e.preventDefault();
    const btn = document.getElementById('save-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';

    const fd = new FormData(e.target);
    fd.append('action', 'save_profile');

    const res = await api('api/settings.php', fd, true);
    btn.disabled = false;
    btn.innerHTML = '💾 <?= t('save') ?>';

    if (res.success) {
        const lang = document.querySelector('input[name="language"]:checked')?.value || 'ar';
        toast('<?= t('saved') ?> ✅', 'success');
        // Reload to apply language change
        setTimeout(() => window.location.href = 'profile.php?lang=' + lang, 800);
    } else {
        document.getElementById('alert-box').innerHTML = `<div class="alert alert-error">${res.message}</div>`;
    }
}

async function changePassword(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('action', 'change_password');
    const res = await api('api/settings.php', fd, true);
    if (res.success) {
        toast('تم تغيير كلمة المرور ✅', 'success');
        e.target.reset();
    } else {
        toast(res.message || '<?= t('error') ?>', 'error');
    }
}
</script>
</body>
</html>
