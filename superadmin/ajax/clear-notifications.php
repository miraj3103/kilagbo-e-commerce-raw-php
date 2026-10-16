<?php
/**
 * Kilagbo Admin - Clear All Notifications
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
    // Delete all notifications
    $pdo->query("DELETE FROM notifications");
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
