<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/../config.php';
}

$siteTitle = getSettings('site_title') ?: 'কিলাগ্বো';
$siteFavicon = getSettings('site_favicon');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'সেলার'; ?> | <?php echo $siteTitle; ?></title>
    
    <?php if ($siteFavicon): ?>
    <link rel="icon" href="<?php echo uploadUrl($siteFavicon); ?>" type="image/x-icon">
    <?php endif; ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        * {
            font-family: 'Hind Siliguri', sans-serif;
        }
    </style>
</head>
<body>
