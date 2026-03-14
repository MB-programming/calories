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

function callGemini(string $prompt, ?string $imageBase64 = null, ?string $mimeType = null): ?string {
    $apiKey = GEMINI_API_KEY;
    if ($apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
        return null; // No key configured
    }

    $parts = [['text' => $prompt]];

    if ($imageBase64 && $mimeType) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $mimeType,
                'data'      => $imageBase64,
            ]
        ];
    }

    $payload = json_encode([
        'contents' => [['parts' => $parts]],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 1024,
        ]
    ]);

    $ch = curl_init(GEMINI_API_URL . '?key=' . $apiKey);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return null;

    $data = json_decode($response, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

switch ($action) {
    case 'plan':
        $user = $db->fetch("SELECT * FROM users WHERE id=?", [$userId]);
        $lastBmi = $db->fetch(
            "SELECT bmi, category FROM bmi_records WHERE user_id=? ORDER BY recorded_at DESC LIMIT 1",
            [$userId]
        );
        $goal = $db->fetch(
            "SELECT * FROM goals WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );

        $bmi      = $lastBmi['bmi'] ?? 'unknown';
        $category = $lastBmi['category'] ?? 'unknown';
        $goalType = $goal['goal_type'] ?? 'maintain';
        $targetW  = $goal['target_weight'] ?? 'not specified';
        $name     = $user['name'] ?? 'User';
        $age      = $user['age'] ?? 'unknown';
        $gender   = $user['gender'] ?? 'unknown';
        $weight   = $user['weight'] ?? 'unknown';
        $height   = $user['height'] ?? 'unknown';
        $activity = $user['activity_level'] ?? 'moderate';

        $prompt = "You are a professional fitness and nutrition coach. Create a detailed, personalized fitness and diet plan in Arabic (Egyptian dialect mixed with English terms) for:
- Name: $name
- Age: $age years
- Gender: $gender
- Height: {$height}cm, Weight: {$weight}kg
- BMI: $bmi ($category)
- Goal: $goalType
- Target weight: $targetW kg
- Activity level: $activity

Please provide:
1. 🎯 Weekly calorie target and macro breakdown
2. 🍽️ Sample daily meal plan (breakfast, lunch, dinner, snacks)
3. 💪 Weekly workout plan (3-5 days)
4. 📋 Key tips and advice
5. ⏱️ Estimated timeline to reach goal

Format with clear sections and emojis. Be encouraging and practical. Respond in Arabic.";

        $aiResponse = callGemini($prompt);

        if (!$aiResponse) {
            // Fallback plan without AI
            $targetCal = match($goalType) {
                'lose'   => 1500,
                'gain'   => 2500,
                default  => 2000
            };
            $aiResponse = "🤖 **خطة مبدئية (بدون AI)**\n\n";
            $aiResponse .= "🎯 **الهدف:** $goalType\n";
            $aiResponse .= "🔥 **السعرات اليومية المقترحة:** $targetCal سعرة\n\n";
            $aiResponse .= "📌 **لتفعيل الذكاء الاصطناعي:**\nأضف Gemini API Key في config/config.php\nاحصل على مفتاح مجاني من: https://aistudio.google.com/app/apikey";
        }

        echo json_encode(['success' => true, 'plan' => $aiResponse]);
        break;

    case 'recognize_food':
        // Food image recognition
        $imageData = $_POST['image_data'] ?? '';
        $mimeType  = $_POST['mime_type'] ?? 'image/jpeg';

        if (!$imageData) {
            echo json_encode(['success' => false, 'message' => 'No image provided']);
            break;
        }

        // Remove data URL prefix if present
        if (strpos($imageData, 'base64,') !== false) {
            $imageData = explode('base64,', $imageData)[1];
        }

        $prompt = "You are a nutrition expert. Analyze this food image and respond ONLY in JSON format (no markdown, no explanation) with this exact structure:
{
  \"food_name\": \"name of food in Arabic and English\",
  \"calories\": estimated calories per serving (number),
  \"protein\": grams of protein (number),
  \"carbs\": grams of carbohydrates (number),
  \"fat\": grams of fat (number),
  \"portion\": \"estimated portion size\",
  \"confidence\": \"high/medium/low\"
}
If you cannot identify the food, use: {\"food_name\": \"Unknown Food\", \"calories\": 0, \"protein\": 0, \"carbs\": 0, \"fat\": 0, \"portion\": \"unknown\", \"confidence\": \"low\"}";

        $aiResponse = callGemini($prompt, $imageData, $mimeType);

        if (!$aiResponse) {
            echo json_encode([
                'success'   => false,
                'message'   => 'AI not available. Please enter food details manually.',
                'fallback'  => true,
            ]);
            break;
        }

        // Parse JSON response
        $cleaned = preg_replace('/```json\s*|\s*```/', '', trim($aiResponse));
        $foodData = json_decode($cleaned, true);

        if (!$foodData) {
            echo json_encode(['success' => false, 'message' => 'Could not parse AI response', 'raw' => $aiResponse]);
            break;
        }

        echo json_encode(['success' => true, 'food' => $foodData]);
        break;

    case 'chat':
        $message = trim($_POST['message'] ?? '');
        if (!$message) {
            echo json_encode(['success' => false, 'message' => 'Message required']);
            break;
        }

        $user = $db->fetch("SELECT name, weight, height, age, gender FROM users WHERE id=?", [$userId]);
        $context = "User profile: Name={$user['name']}, Age={$user['age']}, Gender={$user['gender']}, Height={$user['height']}cm, Weight={$user['weight']}kg";

        $prompt = "You are FitTrack AI, a friendly fitness and nutrition assistant. $context\nUser question: $message\nAnswer in Arabic (Egyptian dialect), be helpful, specific, and concise.";
        $response = callGemini($prompt);

        if (!$response) {
            $response = "🤖 عذراً، الذكاء الاصطناعي مش متاح دلوقتي. تأكد من إضافة Gemini API Key.";
        }

        echo json_encode(['success' => true, 'response' => $response]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
