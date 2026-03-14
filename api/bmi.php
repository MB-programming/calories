<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'get';

switch ($action) {
    case 'calculate':
        $height = (float)($_POST['height'] ?? 0); // cm
        $weight = (float)($_POST['weight'] ?? 0); // kg
        $age    = (int)($_POST['age'] ?? 0);
        $gender = $_POST['gender'] ?? '';
        $activity = $_POST['activity_level'] ?? 'moderate';

        if ($height <= 0 || $weight <= 0) {
            echo json_encode(['success' => false, 'message' => 'Enter valid height and weight']);
            break;
        }

        $heightM = $height / 100;
        $bmi = round($weight / ($heightM * $heightM), 1);

        $category = match(true) {
            $bmi < 18.5 => 'Underweight',
            $bmi < 25   => 'Normal weight',
            $bmi < 30   => 'Overweight',
            default     => 'Obese'
        };

        // BMR (Mifflin-St Jeor)
        if ($gender === 'male') {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
        } else {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
        }

        $activityMultipliers = [
            'sedentary'  => 1.2,
            'light'      => 1.375,
            'moderate'   => 1.55,
            'active'     => 1.725,
            'very_active'=> 1.9,
        ];
        $tdee = round($bmr * ($activityMultipliers[$activity] ?? 1.55));

        // Save BMI record
        $db->query(
            "INSERT INTO bmi_records (user_id, height, weight, bmi, category) VALUES (?, ?, ?, ?, ?)",
            [$userId, $height, $weight, $bmi, $category]
        );

        // Update user profile
        $db->query(
            "UPDATE users SET height=?, weight=?, age=?, gender=?, activity_level=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
            [$height, $weight, $age, $gender, $activity, $userId]
        );

        echo json_encode([
            'success'  => true,
            'bmi'      => $bmi,
            'category' => $category,
            'bmr'      => round($bmr),
            'tdee'     => $tdee,
            'height'   => $height,
            'weight'   => $weight,
        ]);
        break;

    case 'history':
        $records = $db->fetchAll(
            "SELECT * FROM bmi_records WHERE user_id=? ORDER BY recorded_at DESC LIMIT 10",
            [$userId]
        );
        echo json_encode(['success' => true, 'records' => $records]);
        break;

    case 'get':
        $user = $db->fetch("SELECT height, weight, age, gender, activity_level FROM users WHERE id=?", [$userId]);
        echo json_encode(['success' => true, 'user' => $user]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
