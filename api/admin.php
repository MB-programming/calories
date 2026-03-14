<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db     = Database::getInstance();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'user_detail':
        $id   = (int)($_GET['id'] ?? 0);
        $user = $db->fetch("SELECT id, name, email, age, gender, height, weight, activity_level, created_at FROM users WHERE id=?", [$id]);
        if (!$user) { echo json_encode(['success' => false, 'message' => 'User not found']); break; }

        $lastBmi   = $db->fetch("SELECT * FROM bmi_records WHERE user_id=? ORDER BY recorded_at DESC LIMIT 1", [$id]);
        $goal      = $db->fetch("SELECT * FROM goals WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 1", [$id]);
        $foodCount = $db->fetch("SELECT COUNT(*) as c FROM food_logs WHERE user_id=?", [$id])['c'];

        echo json_encode([
            'success'    => true,
            'user'       => $user,
            'last_bmi'   => $lastBmi,
            'goal'       => $goal,
            'food_count' => $foodCount,
        ]);
        break;

    case 'delete_user':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); break; }

        // Don't delete admin
        $user = $db->fetch("SELECT role FROM users WHERE id=?", [$id]);
        if (!$user || $user['role'] === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Cannot delete admin user']);
            break;
        }

        $db->query("DELETE FROM users WHERE id=?", [$id]);
        echo json_encode(['success' => true]);
        break;

    case 'logs_by_date':
        $date = $_GET['date'] ?? date('Y-m-d');
        $logs = $db->fetchAll("
            SELECT fl.*, u.name as user_name
            FROM food_logs fl
            JOIN users u ON u.id = fl.user_id
            WHERE fl.log_date = ?
            ORDER BY fl.created_at DESC
        ", [$date]);
        echo json_encode(['success' => true, 'logs' => $logs]);
        break;

    case 'stats':
        $stats = [
            'total_users'    => $db->fetch("SELECT COUNT(*) as c FROM users WHERE role='user'")['c'],
            'today_logs'     => $db->fetch("SELECT COUNT(*) as c FROM food_logs WHERE log_date=?", [date('Y-m-d')])['c'],
            'total_logs'     => $db->fetch("SELECT COUNT(*) as c FROM food_logs")['c'],
            'active_goals'   => $db->fetch("SELECT COUNT(*) as c FROM goals WHERE status='active'")['c'],
        ];
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
