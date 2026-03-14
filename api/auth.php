<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$db = Database::getInstance();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        $name  = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $pass  = $_POST['password'] ?? '';
        $age   = (int)($_POST['age'] ?? 0);
        $gender = $_POST['gender'] ?? '';

        if (!$name || !$email || !$pass) {
            echo json_encode(['success' => false, 'message' => 'All fields required']);
            break;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email']);
            break;
        }
        if (strlen($pass) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password min 6 characters']);
            break;
        }

        $existing = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
            break;
        }

        $id = $db->insert(
            "INSERT INTO users (name, email, password, age, gender) VALUES (?, ?, ?, ?, ?)",
            [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $age ?: null, $gender ?: null]
        );

        $_SESSION['user_id'] = $id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = 'user';
        echo json_encode(['success' => true, 'message' => 'Registered successfully', 'redirect' => 'bmi.php']);
        break;

    case 'login':
        $email = trim(strtolower($_POST['email'] ?? ''));
        $pass  = $_POST['password'] ?? '';

        $user = $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
        if (!$user || !password_verify($pass, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
            break;
        }

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        $redirect = $user['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php';
        echo json_encode(['success' => true, 'redirect' => $redirect]);
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['success' => true, 'redirect' => 'index.php']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
