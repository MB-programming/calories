<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$db     = Database::getInstance();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'add':
        $logDate  = $_POST['log_date'] ?? date('Y-m-d');
        $mealType = $_POST['meal_type'] ?? 'other';
        $foodName = trim($_POST['food_name'] ?? '');
        $calories = (float)($_POST['calories'] ?? 0);
        $protein  = (float)($_POST['protein'] ?? 0);
        $carbs    = (float)($_POST['carbs'] ?? 0);
        $fat      = (float)($_POST['fat'] ?? 0);
        $portion  = $_POST['portion'] ?? '';
        $aiDetected = (int)($_POST['ai_detected'] ?? 0);
        $notes    = $_POST['notes'] ?? '';
        $imagePath = null;

        if (!$foodName || $calories < 0) {
            echo json_encode(['success' => false, 'message' => 'Food name and calories required']);
            break;
        }

        // Handle image upload
        if (!empty($_FILES['food_image']['tmp_name'])) {
            $file = $_FILES['food_image'];
            if ($file['size'] > UPLOAD_MAX_SIZE) {
                echo json_encode(['success' => false, 'message' => 'Image too large (max 5MB)']);
                break;
            }
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image type']);
                break;
            }

            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'food_' . $userId . '_' . time() . '.' . $ext;
            $destPath = UPLOAD_DIR . $filename;

            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $imagePath = 'assets/uploads/' . $filename;
            }
        }

        $id = $db->insert(
            "INSERT INTO food_logs (user_id, log_date, meal_type, food_name, calories, protein, carbs, fat, portion, image_path, ai_detected, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$userId, $logDate, $mealType, $foodName, $calories, $protein, $carbs, $fat, $portion, $imagePath, $aiDetected, $notes]
        );

        // Update daily summary
        updateDailySummary($db, $userId, $logDate);

        echo json_encode(['success' => true, 'id' => $id]);
        break;

    case 'update':
        $id       = (int)($_POST['id'] ?? 0);
        $foodName = trim($_POST['food_name'] ?? '');
        $calories = (float)($_POST['calories'] ?? 0);
        $protein  = (float)($_POST['protein'] ?? 0);
        $carbs    = (float)($_POST['carbs'] ?? 0);
        $fat      = (float)($_POST['fat'] ?? 0);
        $portion  = $_POST['portion'] ?? '';
        $mealType = $_POST['meal_type'] ?? 'other';

        if (!$id || !$foodName) {
            echo json_encode(['success' => false, 'message' => 'ID and food name required']);
            break;
        }

        $existing = $db->fetch("SELECT id, log_date FROM food_logs WHERE id=? AND user_id=?", [$id, $userId]);
        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
            break;
        }

        $db->query(
            "UPDATE food_logs SET food_name=?, calories=?, protein=?, carbs=?, fat=?, portion=?, meal_type=? WHERE id=? AND user_id=?",
            [$foodName, $calories, $protein, $carbs, $fat, $portion, $mealType, $id, $userId]
        );

        updateDailySummary($db, $userId, $existing['log_date']);
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $existing = $db->fetch("SELECT log_date FROM food_logs WHERE id=? AND user_id=?", [$id, $userId]);
        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
            break;
        }

        $db->query("DELETE FROM food_logs WHERE id=? AND user_id=?", [$id, $userId]);
        updateDailySummary($db, $userId, $existing['log_date']);
        echo json_encode(['success' => true]);
        break;

    case 'list':
        $date = $_GET['date'] ?? date('Y-m-d');
        $logs = $db->fetchAll(
            "SELECT * FROM food_logs WHERE user_id=? AND log_date=? ORDER BY created_at ASC",
            [$userId, $date]
        );
        $summary = $db->fetch(
            "SELECT * FROM daily_summaries WHERE user_id=? AND summary_date=?",
            [$userId, $date]
        );

        // Get user's target calories
        $goal = $db->fetch(
            "SELECT target_calories FROM goals WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );
        $targetCalories = $goal['target_calories'] ?? 2000;

        echo json_encode([
            'success' => true,
            'logs'    => $logs,
            'summary' => $summary,
            'target_calories' => $targetCalories,
        ]);
        break;

    case 'weekly':
        $logs = $db->fetchAll(
            "SELECT summary_date, total_calories, target_calories FROM daily_summaries
             WHERE user_id=? AND summary_date >= date('now', '-7 days')
             ORDER BY summary_date ASC",
            [$userId]
        );
        echo json_encode(['success' => true, 'logs' => $logs]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

function updateDailySummary(Database $db, int $userId, string $date): void {
    $totals = $db->fetch(
        "SELECT SUM(calories) as cal, SUM(protein) as prot, SUM(carbs) as carb, SUM(fat) as fat
         FROM food_logs WHERE user_id=? AND log_date=?",
        [$userId, $date]
    );

    $goal = $db->fetch(
        "SELECT target_calories FROM goals WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 1",
        [$userId]
    );
    $targetCal = $goal['target_calories'] ?? 2000;

    $db->query(
        "INSERT INTO daily_summaries (user_id, summary_date, total_calories, total_protein, total_carbs, total_fat, target_calories)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON CONFLICT(user_id, summary_date) DO UPDATE SET
           total_calories=excluded.total_calories,
           total_protein=excluded.total_protein,
           total_carbs=excluded.total_carbs,
           total_fat=excluded.total_fat,
           target_calories=excluded.target_calories",
        [$userId, $date, $totals['cal'] ?? 0, $totals['prot'] ?? 0, $totals['carb'] ?? 0, $totals['fat'] ?? 0, $targetCal]
    );
}
