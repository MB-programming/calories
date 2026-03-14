<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$db     = Database::getInstance();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? 'plan';
$lang   = currentLang();

// Helper: language instruction for AI
function langInstruction(string $lang): string {
    return match($lang) {
        'en' => 'Respond in English.',
        'de' => 'Antworten Sie auf Deutsch.',
        default => 'أجب باللغة العربية (اللهجة المصرية مع المصطلحات الإنجليزية للتمرينات).',
    };
}

function getUserContext(Database $db, int $userId): array {
    $user    = $db->fetch("SELECT * FROM users WHERE id=?", [$userId]);
    $bmi     = $db->fetch("SELECT bmi, category FROM bmi_records WHERE user_id=? ORDER BY recorded_at DESC LIMIT 1", [$userId]);
    $goal    = $db->fetch("SELECT * FROM goals WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 1", [$userId]);
    $prefs   = $db->fetch("SELECT * FROM user_preferences WHERE user_id=?", [$userId]);
    $goalAr  = ['lose'=>'إنقاص الوزن','maintain'=>'ثبات الوزن','gain'=>'زيادة الوزن'];

    return [
        'name'        => $user['name']   ?? 'User',
        'age'         => $user['age']    ?? 'N/A',
        'gender'      => $user['gender'] ?? 'N/A',
        'height'      => $user['height'] ?? 'N/A',
        'weight'      => $user['weight'] ?? 'N/A',
        'activity'    => $user['activity_level'] ?? 'moderate',
        'bmi'         => $bmi['bmi']     ?? 'N/A',
        'bmi_cat'     => $bmi['category'] ?? 'N/A',
        'goal_type'   => $goal['goal_type'] ?? 'maintain',
        'target_w'    => $goal['target_weight'] ?? 'N/A',
        'target_cal'  => $goal['target_calories'] ?? '2000',
        'gym_days'    => $prefs ? (json_decode($prefs['gym_days'] ?? '[]', true) ?? []) : [],
        'gym_time'    => $prefs['gym_time']     ?? '18:00',
        'gym_dur'     => $prefs['gym_duration'] ?? 60,
        'fitness'     => $prefs['fitness_level'] ?? 'beginner',
        'diet_notes'  => $prefs['dietary_notes'] ?? '',
        'bf_time'     => $prefs['breakfast_time'] ?? '08:00',
        'lunch_time'  => $prefs['lunch_time']     ?? '13:00',
        'snack_time'  => $prefs['snack_time']     ?? '16:00',
        'dinner_time' => $prefs['dinner_time']    ?? '19:00',
    ];
}

switch ($action) {
    // ── Full AI Plan (Diet + Training) ────────────────────────────────
    case 'plan':
    case 'full_plan':
        $ctx  = getUserContext($db, $userId);
        $days = implode(', ', $ctx['gym_days']) ?: 'not specified';
        $langInstr = langInstruction($lang);

        $prompt = "You are an elite fitness coach and certified nutritionist. {$langInstr}

Create a complete personalized plan for:
- Name: {$ctx['name']} | Age: {$ctx['age']} | Gender: {$ctx['gender']}
- Height: {$ctx['height']}cm | Weight: {$ctx['weight']}kg | BMI: {$ctx['bmi']} ({$ctx['bmi_cat']})
- Goal: {$ctx['goal_type']} | Target Weight: {$ctx['target_w']}kg
- Daily Calorie Target: {$ctx['target_cal']} kcal
- Activity Level: {$ctx['activity']} | Fitness Level: {$ctx['fitness']}
- Gym Days: {$days} | Gym Time: {$ctx['gym_time']} | Duration: {$ctx['gym_dur']} min
- Meal Schedule: Breakfast {$ctx['bf_time']}, Lunch {$ctx['lunch_time']}, Snack {$ctx['snack_time']}, Dinner {$ctx['dinner_time']}
- Dietary Notes: {$ctx['diet_notes']}

Provide in this exact format:

## 🎯 Summary
[2-3 lines about the approach]

## 🍽️ Daily Meal Plan (with times)
[Breakfast at {$ctx['bf_time']}, Lunch at {$ctx['lunch_time']}, Snack at {$ctx['snack_time']}, Dinner at {$ctx['dinner_time']}]
[Include estimated calories for each meal]

## 💪 Weekly Workout Plan
[Based on gym days: {$days}]
[Include exercise name, sets, reps, and rest time]

## 📊 Weekly Macros
- Calories: X kcal/day
- Protein: Xg | Carbs: Xg | Fat: Xg

## ⏱️ Expected Timeline
[Realistic estimate]

## 💡 Key Tips (5 tips)
[Numbered tips]";

        $response = AIProvider::call($prompt, null, null, $db);

        if (!$response) {
            $response = buildFallbackPlan($ctx, $lang);
        }

        // Save recommendation
        $db->query(
            "INSERT INTO ai_recommendations (user_id, type, content, provider, model) VALUES (?, 'full_plan', ?, ?, ?)",
            [$userId, $response, $db->getSetting('ai_provider'), $db->getSetting('ai_model')]
        );

        echo json_encode(['success' => true, 'plan' => $response, 'provider' => $db->getSetting('ai_provider')]);
        break;

    // ── Diet Plan Only ────────────────────────────────────────────────
    case 'diet_plan':
        $ctx  = getUserContext($db, $userId);
        $langInstr = langInstruction($lang);

        $prompt = "You are a certified nutritionist. {$langInstr}

Create a detailed 7-day meal plan for:
- Goal: {$ctx['goal_type']} weight | BMI: {$ctx['bmi']} | Target calories: {$ctx['target_cal']} kcal/day
- Meal times: Breakfast {$ctx['bf_time']}, Lunch {$ctx['lunch_time']}, Snack {$ctx['snack_time']}, Dinner {$ctx['dinner_time']}
- Dietary notes: {$ctx['diet_notes'] ?: 'None'}

For each day, list meals with:
- Time ⏰
- Food items with estimated grams
- Calories and main macros (P/C/F)

Include a daily total. Make it practical and realistic.";

        $response = AIProvider::call($prompt, null, null, $db);
        if (!$response) {
            echo json_encode(['success' => false, 'message' => 'AI unavailable']);
            break;
        }

        $db->query("INSERT INTO ai_recommendations (user_id, type, content, provider, model) VALUES (?, 'diet', ?, ?, ?)",
            [$userId, $response, $db->getSetting('ai_provider'), $db->getSetting('ai_model')]);

        echo json_encode(['success' => true, 'plan' => $response]);
        break;

    // ── Training Plan Only ────────────────────────────────────────────
    case 'training_plan':
        $ctx  = getUserContext($db, $userId);
        $days = !empty($ctx['gym_days']) ? implode(', ', $ctx['gym_days']) : 'any 3-4 days/week';
        $langInstr = langInstruction($lang);

        $prompt = "You are a certified personal trainer. {$langInstr}

Create a detailed weekly training plan for:
- Goal: {$ctx['goal_type']} weight | Fitness Level: {$ctx['fitness']}
- BMI: {$ctx['bmi']} | Weight: {$ctx['weight']}kg
- Gym days: {$days} at {$ctx['gym_time']} for {$ctx['gym_dur']} minutes

For each training day include:
🏋️ Warm-up (5 min)
💪 Main Workout:
  - Exercise name
  - Sets x Reps (or duration)
  - Rest between sets
  - Technique tips
🧘 Cool-down (5 min)

Also include:
- Rest day activities
- Weekly progression tips
- Safety notes for {$ctx['fitness']} level";

        $response = AIProvider::call($prompt, null, null, $db);
        if (!$response) {
            echo json_encode(['success' => false, 'message' => 'AI unavailable']);
            break;
        }

        $db->query("INSERT INTO ai_recommendations (user_id, type, content, provider, model) VALUES (?, 'training', ?, ?, ?)",
            [$userId, $response, $db->getSetting('ai_provider'), $db->getSetting('ai_model')]);

        echo json_encode(['success' => true, 'plan' => $response]);
        break;

    // ── Food Recognition ──────────────────────────────────────────────
    case 'recognize_food':
        $imageData = $_POST['image_data'] ?? '';
        $mimeType  = $_POST['mime_type']  ?? 'image/jpeg';

        if (!$imageData) {
            echo json_encode(['success' => false, 'message' => 'No image provided']);
            break;
        }

        $b64 = strpos($imageData, 'base64,') !== false ? explode('base64,', $imageData)[1] : $imageData;

        $prompt = 'Analyze this food image. Respond ONLY with valid JSON (no markdown, no explanation):
{"food_name":"name in local language and English","calories":number,"protein":number,"carbs":number,"fat":number,"portion":"estimated portion","confidence":"high|medium|low"}
If unidentifiable: {"food_name":"Unknown","calories":0,"protein":0,"carbs":0,"fat":0,"portion":"unknown","confidence":"low"}';

        $response = AIProvider::call($prompt, $b64, $mimeType, $db);

        if (!$response) {
            echo json_encode(['success' => false, 'message' => 'AI not available', 'fallback' => true]);
            break;
        }

        $cleaned  = preg_replace('/```json\s*|\s*```/', '', trim($response));
        $foodData = json_decode($cleaned, true);

        if (!$foodData || !isset($foodData['calories'])) {
            echo json_encode(['success' => false, 'message' => 'Could not parse AI response', 'raw' => $response]);
            break;
        }

        echo json_encode(['success' => true, 'food' => $foodData, 'provider' => $db->getSetting('ai_provider')]);
        break;

    // ── AI Chat ───────────────────────────────────────────────────────
    case 'chat':
        $message = trim($_POST['message'] ?? '');
        if (!$message) {
            echo json_encode(['success' => false, 'message' => 'Message required']);
            break;
        }

        $ctx  = getUserContext($db, $userId);
        $langInstr = langInstruction($lang);

        $prompt = "You are FitTrack AI, a friendly fitness and nutrition assistant. {$langInstr}
User profile: BMI={$ctx['bmi']} ({$ctx['bmi_cat']}), Goal={$ctx['goal_type']}, Weight={$ctx['weight']}kg, Height={$ctx['height']}cm
User question: {$message}
Be specific, practical, and encouraging. Keep response concise.";

        $response = AIProvider::call($prompt, null, null, $db);

        if (!$response) {
            $response = match($lang) {
                'en' => '🤖 AI is not available. Please configure an API key in admin settings.',
                'de' => '🤖 KI nicht verfügbar. Bitte API-Schlüssel in den Admin-Einstellungen konfigurieren.',
                default => '🤖 الذكاء الاصطناعي غير متاح. تأكد من إضافة API Key في إعدادات الأدمن.',
            };
        }

        echo json_encode(['success' => true, 'response' => $response]);
        break;

    // ── Saved Recommendations ─────────────────────────────────────────
    case 'saved':
        $type = $_GET['type'] ?? null;
        $sql  = "SELECT * FROM ai_recommendations WHERE user_id=?";
        $params = [$userId];
        if ($type) { $sql .= " AND type=?"; $params[] = $type; }
        $sql .= " ORDER BY created_at DESC LIMIT 5";

        $recs = $db->fetchAll($sql, $params);
        echo json_encode(['success' => true, 'recommendations' => $recs]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

// ── Fallback plan without AI ──────────────────────────────────────────
function buildFallbackPlan(array $ctx, string $lang): string {
    $targetCal = match($ctx['goal_type']) {
        'lose'  => max(1200, (int)$ctx['target_cal'] - 500),
        'gain'  => (int)$ctx['target_cal'] + 300,
        default => (int)$ctx['target_cal'],
    };

    if ($lang === 'en') {
        return "## 🤖 Basic Plan (AI not configured)\n\n"
            . "### 🎯 Daily Target: {$targetCal} calories\n\n"
            . "**To enable AI recommendations:**\n"
            . "Get a free key from https://aistudio.google.com/app/apikey\n"
            . "Set it in Admin → AI Settings\n\n"
            . "### 📋 Basic Guidelines\n"
            . "- Protein: ~" . round($targetCal * 0.3 / 4) . "g/day\n"
            . "- Carbs: ~" . round($targetCal * 0.45 / 4) . "g/day\n"
            . "- Fat: ~" . round($targetCal * 0.25 / 9) . "g/day";
    }

    return "## 🤖 خطة مبدئية (AI غير مفعّل)\n\n"
        . "### 🎯 الهدف اليومي: {$targetCal} سعرة\n\n"
        . "**لتفعيل خطط AI كاملة:**\n"
        . "احصل على مفتاح مجاني من https://aistudio.google.com/app/apikey\n"
        . "أضفه في الأدمن ← إعدادات AI\n\n"
        . "### 📋 توجيهات أساسية\n"
        . "- بروتين: ~" . round($targetCal * 0.3 / 4) . "ج/يوم\n"
        . "- كارب: ~" . round($targetCal * 0.45 / 4) . "ج/يوم\n"
        . "- دهون: ~" . round($targetCal * 0.25 / 9) . "ج/يوم";
}
