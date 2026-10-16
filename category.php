<?php
/**
 * Kilagbo - Category Page
 * Developed by Webnify Bangladesh
 */

require_once 'config/config.php';

// Get and decode the slug (handles URL-encoded Bangla characters)
$slug = urldecode(trim($_GET['slug'] ?? ''));

if (!$slug) {
    redirect(baseUrl('shop'));
}

// Fetch category by slug
$catStmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? AND status = 1");
$catStmt->execute([$slug]);
$category = $catStmt->fetch();

if (!$category) {
    // Try URL-encoded version as fallback
    $catStmt->execute([urlencode($slug)]);
    $category = $catStmt->fetch();
}

if (!$category) {
    redirect(baseUrl('shop'));
}

$currentPage = 'retail';
$pageTitle = $category['name'];

// Redirect to shop with category filter using slug
redirect(baseUrl('shop?category=' . urlencode($slug)));
