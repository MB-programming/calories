<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$db     = Database::getInstance();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'save_profile':
        // Update user info
        $name     = trim($_POST['name'] ?? '');
        $age      = (int)($_POST['age'] ?? 0);
        $gender   = $_POST['gender'] ?? '';
        $height   = (float)($_POST['height'] ?? 0);
        $weight   = (float)($_POST['weight'] ?? 0);
        $activity = $_POST['activity_level'] ?? 'moderate';

        if ($name) {
            $db->query(
                "UPDATE users SET name=?, age=?, gender=?, height=?, weight=?, activity_level=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
                [$name, $age ?: null, $gender, $height ?: null, $weight ?: null, $activity, $userId]
            );
            $_SESSION['user_name'] = $name;
        }

        // Update preferences
        $lang       = $_POST['language'] ?? 'ar';
        $gymDays    = json_encode($_POST['gym_days'] ?? []);
        $gymTime    = $_POST['gym_time'] ?? '18:00';
        $gymDur     = (int)($_POST['gym_duration'] ?? 60);
        $bfTime     = $_POST['breakfast_time'] ?? '08:00';
        $lunchTime  = $_POST['lunch_time'] ?? '13:00';
        $snackTime  = $_POST['snack_time'] ?? '16:00';
        $dinnerTime = $_POST['dinner_time'] ?? '19:00';
        $dietNotes  = trim($_POST['dietary_notes'] ?? '');
        $fitnessLvl = $_POST['fitness_level'] ?? 'beginner';

        $db->query(
            "INSERT INTO user_preferences
                (user_id, language, gym_days, gym_time, gym_duration, breakfast_time, lunch_time, snack_time, dinner_time, dietary_notes, fitness_level)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(user_id) DO UPDATE SET
                language=excluded.language,
                gym_days=excluded.gym_days,
                gym_time=excluded.gym_time,
                gym_duration=excluded.gym_duration,
                breakfast_time=excluded.breakfast_time,
                lunch_time=excluded.lunch_time,
                snack_time=excluded.snack_time,
                dinner_time=excluded.dinner_time,
                dietary_notes=excluded.dietary_notes,
                fitness_level=excluded.fitness_level",
            [$userId, $lang, $gymDays, $gymTime, $gymDur, $bfTime, $lunchTime, $snackTime, $dinnerTime, $dietNotes, $fitnessLvl]
        );

        $_SESSION['lang'] = $lang;
        echo json_encode(['success' => true]);
        break;

    case 'get':
        $user  = $db->fetch("SELECT name, email, age, gender, height, weight, activity_level FROM users WHERE id=?", [$userId]);
        $prefs = $db->fetch("SELECT * FROM user_preferences WHERE user_id=?", [$userId]);
        echo json_encode(['success' => true, 'user' => $user, 'prefs' => $prefs]);
        break;

    case 'change_password':
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';

        if (strlen($new) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password min 6 characters']);
            break;
        }

        $user = $db->fetch("SELECT password FROM users WHERE id=?", [$userId]);
        if (!password_verify($current, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password incorrect']);
            break;
        }

        $db->query("UPDATE users SET password=? WHERE id=?", [password_hash($new, PASSWORD_DEFAULT), $userId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
