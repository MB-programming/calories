<?php
require_once 'config/config.php';
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
require_once 'config/database.php';
$db    = Database::getInstance();
i18n_init($db);
$prefs = $db->fetch("SELECT * FROM user_preferences WHERE user_id=?", [$_SESSION['user_id']]);
$lang  = currentLang(); $dir = langDir();
?><!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('tracker_title') ?> - <?= t('app_name') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?= jsTranslations() ?>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <!-- Header + Date Picker -->
    <div class="flex-between" style="margin-bottom:20px">
        <h1 class="page-title mb-0">🍽️ تتبع السعرات</h1>
        <div style="display:flex;align-items:center;gap:10px">
            <button class="btn btn-outline btn-sm" onclick="changeDate(-1)">◄</button>
            <input type="date" id="log-date" class="form-control" style="width:auto"
                   value="<?= date('Y-m-d') ?>" onchange="loadDay()">
            <button class="btn btn-outline btn-sm" onclick="changeDate(1)">►</button>
        </div>
    </div>

    <div class="grid-2">
        <!-- Left: Calorie Ring + Macros -->
        <div>
            <div class="card">
                <div class="card-header">🔥 ملخص اليوم</div>
                <div class="calorie-ring-wrap">
                    <div class="calorie-ring">
                        <svg width="140" height="140" viewBox="0 0 140 140">
                            <circle cx="70" cy="70" r="58" fill="none" stroke="#e0e0e0" stroke-width="14"/>
                            <circle id="ring-circle" cx="70" cy="70" r="58" fill="none"
                                    stroke="#4CAF50" stroke-width="14"
                                    stroke-dasharray="0 364.4" stroke-linecap="round"/>
                        </svg>
                        <div class="ring-text">
                            <div class="ring-calories" id="ring-calories">0</div>
                            <div class="ring-label">سعرة</div>
                        </div>
                    </div>
                    <div id="ring-remaining" style="font-size:.9rem;color:#666">متبقي 2000</div>
                    <div id="target-display" style="font-size:.8rem;color:#999">الهدف: 2000 سعرة</div>
                </div>

                <div id="macros-section" style="margin-top:16px"></div>
            </div>

            <!-- AI Chat -->
            <div class="card">
                <div class="card-header">🤖 اسأل AI</div>
                <div class="chat-box" id="chat-box">
                    <div class="chat-msg ai">مرحباً! اسألني أي سؤال عن الأكل والتغذية 💪</div>
                </div>
                <div style="display:flex;gap:8px;margin-top:10px">
                    <input type="text" class="form-control" id="chat-input"
                           placeholder="اسأل عن أكل أو تمرين..." onkeydown="if(e.key==='Enter')sendChat()">
                    <button class="btn btn-secondary" onclick="sendChat()" id="chat-btn">إرسال</button>
                </div>
            </div>
        </div>

        <!-- Right: Add Food + Log -->
        <div>
            <!-- Add Food Card -->
            <div class="card">
                <div class="card-header">➕ أضف أكل</div>

                <!-- Meal Type Tabs -->
                <div class="meal-tabs">
                    <div class="meal-tab active" onclick="setMeal('breakfast', this)"><?= t('meal_breakfast') ?></div>
                    <div class="meal-tab" onclick="setMeal('lunch', this)"><?= t('meal_lunch') ?></div>
                    <div class="meal-tab" onclick="setMeal('dinner', this)"><?= t('meal_dinner') ?></div>
                    <div class="meal-tab" onclick="setMeal('snack', this)"><?= t('meal_snack') ?></div>
                </div>
                <div id="meal-time-hint" style="font-size:.82rem;color:var(--primary);margin-bottom:10px"></div>
                <input type="hidden" id="meal-type" value="breakfast">

                <!-- Photo Upload -->
                <div class="photo-upload-area" onclick="document.getElementById('food-photo').click()" id="upload-area">
                    <div class="upload-icon">📷</div>
                    <div><strong>صور الأكلة</strong></div>
                    <div class="text-muted" style="font-size:.85rem">AI هيعرف الأكلة وسعراتها تلقائياً</div>
                    <input type="file" id="food-photo" accept="image/*" capture="environment" onchange="onPhotoSelect()">
                </div>
                <img id="preview-img" alt="preview">

                <div id="ai-detect-status" class="hidden"></div>

                <!-- Food Form -->
                <form id="food-form" onsubmit="addFood(event)" style="margin-top:16px">
                    <div class="form-group">
                        <label class="form-label">اسم الأكلة <span style="color:red">*</span></label>
                        <input type="text" class="form-control" id="food-name" placeholder="مثال: أرز بالدجاج" required>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">السعرات <span style="color:red">*</span></label>
                            <input type="number" class="form-control" id="food-cal" placeholder="350" min="0" step=".1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الحجم</label>
                            <input type="text" class="form-control" id="food-portion" placeholder="100جم / 1 طبق">
                        </div>
                    </div>
                    <div class="grid-3" style="grid-template-columns:1fr 1fr 1fr">
                        <div class="form-group">
                            <label class="form-label">بروتين (ج)</label>
                            <input type="number" class="form-control" id="food-protein" placeholder="0" min="0" step=".1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">كارب (ج)</label>
                            <input type="number" class="form-control" id="food-carbs" placeholder="0" min="0" step=".1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">دهون (ج)</label>
                            <input type="number" class="form-control" id="food-fat" placeholder="0" min="0" step=".1">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" id="add-btn">
                        ➕ إضافة للسجل
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Food Log -->
    <div class="card">
        <div class="card-header flex-between">
            <span>📋 سجل اليوم</span>
            <span id="log-date-display" style="font-size:.85rem;color:#666"></span>
        </div>
        <div id="food-log"></div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">✏️ تعديل الوجبة</span>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <form id="edit-form" onsubmit="saveEdit(event)">
            <input type="hidden" id="edit-id">
            <div class="form-group">
                <label class="form-label">نوع الوجبة</label>
                <select class="form-control" id="edit-meal">
                    <option value="breakfast">فطار</option>
                    <option value="lunch">غدا</option>
                    <option value="dinner">عشا</option>
                    <option value="snack">سناك</option>
                    <option value="other">أخرى</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">اسم الأكلة</label>
                <input type="text" class="form-control" id="edit-name" required>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">السعرات</label>
                    <input type="number" class="form-control" id="edit-cal" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الحجم</label>
                    <input type="text" class="form-control" id="edit-portion">
                </div>
            </div>
            <div class="grid-3" style="grid-template-columns:1fr 1fr 1fr">
                <div class="form-group">
                    <label class="form-label">بروتين</label>
                    <input type="number" class="form-control" id="edit-protein" step=".1">
                </div>
                <div class="form-group">
                    <label class="form-label">كارب</label>
                    <input type="number" class="form-control" id="edit-carbs" step=".1">
                </div>
                <div class="form-group">
                    <label class="form-label">دهون</label>
                    <input type="number" class="form-control" id="edit-fat" step=".1">
                </div>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary" style="flex:1">حفظ</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()" style="flex:1">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script>
let currentMeal   = 'breakfast';
let uploadedImage = null;
let uploadedMime  = null;
let targetCal     = 2000;

function setMeal(type, el) {
    currentMeal = type;
    document.getElementById('meal-type').value = type;
    document.querySelectorAll('.meal-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    if (typeof updateMealTimeHint === 'function') updateMealTimeHint(type);
}

function changeDate(days) {
    const d = new Date(document.getElementById('log-date').value);
    d.setDate(d.getDate() + days);
    document.getElementById('log-date').value = d.toISOString().split('T')[0];
    loadDay();
}

function onPhotoSelect() {
    const file = document.getElementById('food-photo').files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async e => {
        const img = document.getElementById('preview-img');
        img.src = e.target.result;
        img.style.display = 'block';
        document.getElementById('upload-area').style.display = 'none';

        // Store for upload
        uploadedImage = e.target.result;
        uploadedMime  = file.type;

        // AI recognition
        await recognizeFood(uploadedImage, uploadedMime);
    };
    reader.readAsDataURL(file);
}

async function recognizeFood(imageData, mimeType) {
    const status = document.getElementById('ai-detect-status');
    status.className = 'alert alert-info';
    status.innerHTML = '<span class="spinner" style="border-color:rgba(21,101,192,.3);border-top-color:#1565C0;width:16px;height:16px;border-width:2px"></span> 🤖 AI بيحلل الصورة...';

    const b64 = imageData.split('base64,')[1] || imageData;
    const res = await api('api/ai.php', { action: 'recognize_food', image_data: b64, mime_type: mimeType });

    if (res.success && res.food) {
        const f = res.food;
        document.getElementById('food-name').value    = f.food_name || '';
        document.getElementById('food-cal').value     = f.calories  || 0;
        document.getElementById('food-protein').value = f.protein   || 0;
        document.getElementById('food-carbs').value   = f.carbs     || 0;
        document.getElementById('food-fat').value     = f.fat       || 0;
        document.getElementById('food-portion').value = f.portion   || '';
        status.className = 'alert alert-success';
        status.innerHTML = `✅ AI اكتشف: <strong>${f.food_name}</strong> ≈ ${f.calories} سعرة (${f.confidence} confidence) — عدّل لو محتاج`;
    } else if (res.fallback) {
        status.className = 'alert alert-warning';
        status.innerHTML = '⚠️ AI مش متاح. أضف التفاصيل يدوياً.';
    } else {
        status.className = 'alert alert-warning';
        status.innerHTML = '⚠️ مش قادر أحدد الأكلة. أضف البيانات يدوياً.';
    }
}

async function addFood(e) {
    e.preventDefault();
    const btn = document.getElementById('add-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';

    const fd = new FormData();
    fd.append('action',      'add');
    fd.append('log_date',    document.getElementById('log-date').value);
    fd.append('meal_type',   currentMeal);
    fd.append('food_name',   document.getElementById('food-name').value);
    fd.append('calories',    document.getElementById('food-cal').value);
    fd.append('protein',     document.getElementById('food-protein').value || 0);
    fd.append('carbs',       document.getElementById('food-carbs').value   || 0);
    fd.append('fat',         document.getElementById('food-fat').value     || 0);
    fd.append('portion',     document.getElementById('food-portion').value);
    fd.append('ai_detected', uploadedImage ? 1 : 0);

    // Attach image if selected
    const photoInput = document.getElementById('food-photo');
    if (photoInput.files[0]) fd.append('food_image', photoInput.files[0]);

    const res = await api('api/food.php', fd, true);
    btn.disabled = false;
    btn.innerHTML = '➕ إضافة للسجل';

    if (res.success) {
        toast('تمت الإضافة ✅', 'success');
        resetForm();
        loadDay();
    } else {
        toast(res.message, 'error');
    }
}

function resetForm() {
    ['food-name','food-cal','food-portion','food-protein','food-carbs','food-fat'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('preview-img').style.display = 'none';
    document.getElementById('upload-area').style.display = 'block';
    document.getElementById('food-photo').value = '';
    document.getElementById('ai-detect-status').className = 'hidden';
    uploadedImage = null; uploadedMime = null;
}

async function loadDay() {
    const date = document.getElementById('log-date').value;
    document.getElementById('log-date-display').textContent = new Date(date + 'T12:00:00').toLocaleDateString('ar-EG', {weekday:'long', year:'numeric', month:'long', day:'numeric'});

    const res = await api(`api/food.php?action=list&date=${date}`);
    if (!res.success) return;

    targetCal = res.target_calories || 2000;
    document.getElementById('target-display').textContent = `الهدف: ${targetCal} سعرة`;

    const s = res.summary || {};
    const totalCal  = parseFloat(s.total_calories || 0);
    const totalProt = parseFloat(s.total_protein  || 0);
    const totalCarb = parseFloat(s.total_carbs    || 0);
    const totalFat  = parseFloat(s.total_fat      || 0);

    updateRing(totalCal, targetCal);

    document.getElementById('macros-section').innerHTML =
        macroBar('🥩 بروتين', totalProt, 150, '#E91E63') +
        macroBar('🍞 كارب',   totalCarb, 250, '#FF9800') +
        macroBar('🥑 دهون',   totalFat,  70,  '#9C27B0');

    // Render food log
    const mealIcons = {breakfast:'🌅',lunch:'☀️',dinner:'🌙',snack:'🍎',other:'🍴'};
    const mealNames = {breakfast:'فطار',lunch:'غدا',dinner:'عشا',snack:'سناك',other:'أخرى'};
    const meals     = {};
    res.logs.forEach(log => {
        if (!meals[log.meal_type]) meals[log.meal_type] = [];
        meals[log.meal_type].push(log);
    });

    let html = '';
    if (!res.logs.length) {
        html = '<div class="text-center text-muted" style="padding:40px">لم تسجل أي وجبات اليوم 🍽️</div>';
    } else {
        ['breakfast','lunch','dinner','snack','other'].forEach(mt => {
            if (!meals[mt]) return;
            const mCal = meals[mt].reduce((s, f) => s + parseFloat(f.calories), 0);
            html += `<div style="margin-bottom:16px">
                <div style="font-weight:700;margin-bottom:8px;display:flex;justify-content:space-between">
                    <span>${mealIcons[mt]} ${mealNames[mt]}</span>
                    <span style="color:#4CAF50">${Math.round(mCal)} سعرة</span>
                </div>`;
            meals[mt].forEach(f => {
                html += `<div class="food-item">
                    ${f.image_path ? `<img src="${f.image_path}" alt="${f.food_name}">` : `<div style="width:60px;height:60px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.5rem">🍽️</div>`}
                    <div class="food-info">
                        <div class="food-name">${f.food_name} ${f.ai_detected ? '<span style="font-size:.7rem;background:#e8f5e9;color:#2e7d32;padding:2px 6px;border-radius:8px">AI</span>' : ''}</div>
                        <div class="food-meta">${f.portion || ''} ${f.protein ? `• 🥩${f.protein}ج` : ''} ${f.carbs ? `• 🍞${f.carbs}ج` : ''} ${f.fat ? `• 🥑${f.fat}ج` : ''}</div>
                    </div>
                    <div class="food-cal">${Math.round(f.calories)}</div>
                    <div class="food-actions">
                        <button class="btn btn-sm btn-outline" onclick='editFood(${JSON.stringify(f).replace(/'/g,"&#39;")})'>✏️</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteFood(${f.id})">🗑️</button>
                    </div>
                </div>`;
            });
            html += '</div>';
        });
    }
    document.getElementById('food-log').innerHTML = html;
}

function editFood(f) {
    document.getElementById('edit-id').value      = f.id;
    document.getElementById('edit-meal').value    = f.meal_type;
    document.getElementById('edit-name').value    = f.food_name;
    document.getElementById('edit-cal').value     = f.calories;
    document.getElementById('edit-portion').value = f.portion || '';
    document.getElementById('edit-protein').value = f.protein || 0;
    document.getElementById('edit-carbs').value   = f.carbs   || 0;
    document.getElementById('edit-fat').value     = f.fat     || 0;
    document.getElementById('edit-modal').classList.add('open');
}
function closeModal() { document.getElementById('edit-modal').classList.remove('open'); }

async function saveEdit(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action',    'update');
    fd.append('id',        document.getElementById('edit-id').value);
    fd.append('meal_type', document.getElementById('edit-meal').value);
    fd.append('food_name', document.getElementById('edit-name').value);
    fd.append('calories',  document.getElementById('edit-cal').value);
    fd.append('portion',   document.getElementById('edit-portion').value);
    fd.append('protein',   document.getElementById('edit-protein').value);
    fd.append('carbs',     document.getElementById('edit-carbs').value);
    fd.append('fat',       document.getElementById('edit-fat').value);
    const res = await api('api/food.php', fd, true);
    if (res.success) { closeModal(); toast('تم التعديل ✅', 'success'); loadDay(); }
    else toast(res.message, 'error');
}

async function deleteFood(id) {
    if (!confirm('مسح الوجبة دي؟')) return;
    const res = await api('api/food.php', { action: 'delete', id });
    if (res.success) { toast('تم المسح', 'info'); loadDay(); }
    else toast(res.message, 'error');
}

// AI Chat
async function sendChat() {
    const input = document.getElementById('chat-input');
    const msg   = input.value.trim();
    if (!msg) return;

    const box = document.getElementById('chat-box');
    box.innerHTML += `<div class="chat-msg user">${msg}</div>`;
    input.value = '';
    box.scrollTop = box.scrollHeight;

    const btn = document.getElementById('chat-btn');
    btn.disabled = true;
    btn.innerHTML = '...';

    const res = await api('api/ai.php', { action: 'chat', message: msg });
    btn.disabled = false;
    btn.innerHTML = 'إرسال';

    box.innerHTML += `<div class="chat-msg ai">${(res.response || 'خطأ في الاتصال').replace(/\n/g,'<br>')}</div>`;
    box.scrollTop = box.scrollHeight;
}

document.getElementById('chat-input').addEventListener('keydown', e => { if (e.key === 'Enter') sendChat(); });

// Show meal time hints from user preferences
const mealTimes = {
    breakfast: '<?= $prefs['breakfast_time'] ?? '08:00' ?>',
    lunch:     '<?= $prefs['lunch_time']     ?? '13:00' ?>',
    snack:     '<?= $prefs['snack_time']     ?? '16:00' ?>',
    dinner:    '<?= $prefs['dinner_time']    ?? '19:00' ?>',
};
function updateMealTimeHint(meal) {
    const t = mealTimes[meal];
    const hint = document.getElementById('meal-time-hint');
    if (hint && t) hint.textContent = '⏰ ' + t;
}
// Auto-select current meal based on time
(function() {
    const h = new Date().getHours();
    let meal = 'breakfast';
    if (h >= 11 && h < 15) meal = 'lunch';
    else if (h >= 15 && h < 18) meal = 'snack';
    else if (h >= 18) meal = 'dinner';
    document.querySelectorAll('.meal-tab').forEach(t => t.classList.remove('active'));
    const tabs = {'breakfast':0,'lunch':1,'dinner':2,'snack':3};
    document.querySelectorAll('.meal-tab')[tabs[meal]]?.classList.add('active');
    currentMeal = meal;
    updateMealTimeHint(meal);
})();

// Init
loadDay();
</script>
</body>
</html>
