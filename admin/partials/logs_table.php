<?php
// This file is included in admin/index.php — $db is already available
$todayLogs2 = $db->fetchAll("
    SELECT fl.*, u.name as user_name
    FROM food_logs fl
    JOIN users u ON u.id = fl.user_id
    WHERE fl.log_date = ?
    ORDER BY fl.created_at DESC
", [date('Y-m-d')]);
$mealAr = ['breakfast'=>'فطار','lunch'=>'غدا','dinner'=>'عشا','snack'=>'سناك','other'=>'أخرى'];
?>
<table class="table">
    <thead>
        <tr><th>المستخدم</th><th>الأكلة</th><th>السعرات</th><th>الوجبة</th><th>الوقت</th></tr>
    </thead>
    <tbody>
    <?php if ($todayLogs2): ?>
        <?php foreach ($todayLogs2 as $l): ?>
        <tr>
            <td><?= htmlspecialchars($l['user_name']) ?></td>
            <td>
                <?= htmlspecialchars($l['food_name']) ?>
                <?= $l['ai_detected'] ? '<span class="badge badge-success" style="font-size:.7rem">AI</span>' : '' ?>
            </td>
            <td><?= round($l['calories']) ?></td>
            <td><?= $mealAr[$l['meal_type']] ?? $l['meal_type'] ?></td>
            <td style="font-size:.8rem;color:#666"><?= substr($l['created_at'], 11, 5) ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="5" class="text-center text-muted">لا يوجد سجلات اليوم</td></tr>
    <?php endif; ?>
    </tbody>
</table>
