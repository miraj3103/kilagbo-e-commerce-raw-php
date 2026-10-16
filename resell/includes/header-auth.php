<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'রিসেলার'; ?> - <?php echo getSettings('site_name') ?: 'Kilagbo'; ?></title>
    
    <!-- Favicon -->
    <?php if (getSettings('site_favicon')): ?>
    <link rel="icon" href="<?php echo uploadUrl(getSettings('site_favicon')); ?>">
    <?php endif; ?>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts - Hind Siliguri -->
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Hind Siliguri', sans-serif;
        }
        
        body {
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
