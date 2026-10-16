<?php
/**
 * Kilagbo Admin - Mark Notifications as Read
 * AJAX Handler
 */

require_once __DIR__ . '/../../config/config.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

try {
    // Mark all notifications as read
    $pdo->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
