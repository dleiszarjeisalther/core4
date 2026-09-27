<?php
require_once __DIR__.'/helpers.php';
require_login();
$u=current_user();
header('Content-Type: application/json; charset=utf-8');
try {
    if (($u['role'] ?? '') === 'Administrator') {
        $stmt=db()->prepare("UPDATE admin_notifications SET is_read=1 WHERE is_read=0 AND (user_id=? OR user_id IS NULL OR type IN ('feedback','data_transfer'))");
        $stmt->execute([(int)$u['id']]);
    } elseif (($u['role'] ?? '') === 'Staff') {
        $stmt=db()->prepare("UPDATE admin_notifications SET is_read=1 WHERE is_read=0 AND (user_id=? OR user_id IS NULL)");
        $stmt->execute([(int)$u['id']]);
    } else {
        http_response_code(403); echo json_encode(['ok'=>false]); exit;
    }
    echo json_encode(['ok'=>true]);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false]);
}
