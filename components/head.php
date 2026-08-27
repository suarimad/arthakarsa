<?php 
// Pastikan $app_settings dan $base_url sudah di-load dari config.php sebelum file ini dipanggil
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <!-- Meta Data Dinamis -->
    <title><?= htmlspecialchars($app_settings['app_name'] ?? 'HRIS Pro') ?> - <?= htmlspecialchars($app_settings['slogan'] ?? '') ?></title>
    <meta name="description" content="<?= htmlspecialchars($app_settings['description'] ?? '') ?>">
    <!-- <meta name="theme-color" content="<?= htmlspecialchars($app_settings['theme_color'] ?? '#ea3800') ?>"> -->
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <!-- PWA & Favicon Dinamis -->
    <link rel="manifest" href="<?= $base_url ?>/manifest.php">
    <link rel="icon" type="image/png" href="<?= $logo_path . ($app_settings['favicon'] ?? 'default_favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= $logo_path . ($app_settings['pwa_icon_192'] ?? 'icon-192.png') ?>">
    
    <!-- Open Graph (SEO/Share) -->
    <meta property="og:title" content="<?= htmlspecialchars($app_settings['app_name'] ?? 'HRIS Pro') ?>">
    <meta property="og:image" content="<?= $logo_path . ($app_settings['og_image'] ?? 'default_og.png') ?>">
    
    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Local Tailwind CSS (Hasil Kompilasi npm run watch/build) -->
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/output.css">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Inject Warna Dinamis dari Database ke Variable CSS -->
    <style>
        :root {
            --color-primary: <?= htmlspecialchars($app_settings['theme_color'] ?? '#ea3800') ?>;
        }
    </style>
</head>
<body class="text-secondary bg-white flex h-screen overflow-hidden">