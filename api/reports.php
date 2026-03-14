<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$db     = Database::getInstance();
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'range';

switch ($action) {
    case 'range':
        $from   = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
        $to     = $_GET['to']   ?? date('Y-m-d');

        // Daily summaries for chart
        $daily = $db->fetchAll(
            "SELECT summary_date, total_calories, total_protein, total_carbs, total_fat, target_calories
             FROM daily_summaries WHERE user_id=? AND summary_date BETWEEN ? AND ?
             ORDER BY summary_date",
            [$userId, $from, $to]
        );

        // Aggregate stats
        $stats = $db->fetch(
            "SELECT
                COUNT(*) as days_logged,
                ROUND(AVG(total_calories),0) as avg_calories,
                ROUND(AVG(total_protein),1)  as avg_protein,
                ROUND(AVG(total_carbs),1)    as avg_carbs,
                ROUND(AVG(total_fat),1)      as avg_fat,
                SUM(CASE WHEN total_calories <= target_calories THEN 1 ELSE 0 END) as days_on_goal,
                ROUND(AVG(target_calories),0) as avg_target
             FROM daily_summaries WHERE user_id=? AND summary_date BETWEEN ? AND ?",
            [$userId, $from, $to]
        );

        // Total food items
        $foodCount = $db->fetch(
            "SELECT COUNT(*) as c, SUM(calories) as total_cal FROM food_logs WHERE user_id=? AND log_date BETWEEN ? AND ?",
            [$userId, $from, $to]
        )['c'];

        // Goal achievement rate
        $goalRate = ($stats['days_logged'] > 0)
            ? round(($stats['days_on_goal'] / $stats['days_logged']) * 100)
            : 0;

        // Meal type breakdown
        $mealBreakdown = $db->fetchAll(
            "SELECT meal_type, COUNT(*) as cnt, ROUND(SUM(calories),0) as total_cal
             FROM food_logs WHERE user_id=? AND log_date BETWEEN ? AND ?
             GROUP BY meal_type",
            [$userId, $from, $to]
        );

        // Most logged foods (top 5)
        $topFoods = $db->fetchAll(
            "SELECT food_name, COUNT(*) as freq, ROUND(AVG(calories),0) as avg_cal
             FROM food_logs WHERE user_id=? AND log_date BETWEEN ? AND ?
             GROUP BY food_name ORDER BY freq DESC LIMIT 5",
            [$userId, $from, $to]
        );

        // Streak calculation
        $allDates = $db->fetchAll(
            "SELECT DISTINCT log_date FROM food_logs WHERE user_id=? ORDER BY log_date DESC",
            [$userId]
        );
        $streak = 0;
        $checkDate = date('Y-m-d');
        foreach ($allDates as $d) {
            if ($d['log_date'] === $checkDate) {
                $streak++;
                $checkDate = date('Y-m-d', strtotime($checkDate . ' -1 day'));
            } else {
                break;
            }
        }

        // Weight trend from BMI records
        $weightTrend = $db->fetchAll(
            "SELECT DATE(recorded_at) as date, weight
             FROM bmi_records WHERE user_id=? AND recorded_at >= ?
             ORDER BY recorded_at",
            [$userId, $from . ' 00:00:00']
        );

        echo json_encode([
            'success'       => true,
            'daily'         => $daily,
            'stats'         => $stats,
            'goal_rate'     => $goalRate,
            'streak'        => $streak,
            'food_count'    => $foodCount,
            'meal_breakdown'=> $mealBreakdown,
            'top_foods'     => $topFoods,
            'weight_trend'  => $weightTrend,
        ]);
        break;

    case 'export_csv':
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $_GET['to']   ?? date('Y-m-d');

        $logs = $db->fetchAll(
            "SELECT log_date, meal_type, food_name, calories, protein, carbs, fat, portion, created_at
             FROM food_logs WHERE user_id=? AND log_date BETWEEN ? AND ? ORDER BY log_date, created_at",
            [$userId, $from, $to]
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fittrack_export_' . $from . '_' . $to . '.csv"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Meal', 'Food', 'Calories', 'Protein(g)', 'Carbs(g)', 'Fat(g)', 'Portion']);
        foreach ($logs as $row) {
            fputcsv($out, [
                $row['log_date'], $row['meal_type'], $row['food_name'],
                $row['calories'], $row['protein'], $row['carbs'], $row['fat'], $row['portion']
            ]);
        }
        fclose($out);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
