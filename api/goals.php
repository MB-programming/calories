<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'save':
        $goalType      = $_POST['goal_type'] ?? '';
        $targetWeight  = (float)($_POST['target_weight'] ?? 0) ?: null;
        $targetCalories= (int)($_POST['target_calories'] ?? 0) ?: null;
        $targetDate    = $_POST['target_date'] ?? null;
        $notes         = $_POST['notes'] ?? '';
        $aiPlan        = $_POST['ai_plan'] ?? null;

        if (!$goalType) {
            echo json_encode(['success' => false, 'message' => 'Goal type required']);
            break;
        }

        // Deactivate previous active goals
        $db->query("UPDATE goals SET status='inactive' WHERE user_id=? AND status='active'", [$userId]);

        $id = $db->insert(
            "INSERT INTO goals (user_id, goal_type, target_weight, target_calories, target_date, notes, ai_plan)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$userId, $goalType, $targetWeight, $targetCalories, $targetDate, $notes, $aiPlan]
        );

        echo json_encode(['success' => true, 'goal_id' => $id]);
        break;

    case 'active':
        $goal = $db->fetch(
            "SELECT * FROM goals WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );
        echo json_encode(['success' => true, 'goal' => $goal]);
        break;

    case 'list':
        $goals = $db->fetchAll(
            "SELECT * FROM goals WHERE user_id=? ORDER BY created_at DESC LIMIT 10",
            [$userId]
        );
        echo json_encode(['success' => true, 'goals' => $goals]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
