<?php
require_once __DIR__ . '/database.php';

// 1. Deteksi Base URL Otomatis (Support Localhost & Hostinger)
$isSecure = false;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
    $isSecure = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
    $isSecure = true;
}
$protocol = $isSecure ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
// Menghilangkan backslash pada root domain (terutama di Windows/Localhost)
$script_dir = str_replace('\\', '/', $script_dir); 
$base_url = $protocol . '://' . $host . ($script_dir === '/' ? '' : $script_dir);

// 2. Ambil Data App Settings dari PostgreSQL
try {
    $stmt = $pdo->query("SELECT app_name, description, slogan, logo, favicon, og_image, pwa_icon_192, pwa_icon_512, theme_color FROM app_settings LIMIT 1");
    $app_settings = $stmt->fetch();
    
    // Jika tabel kosong, berikan nilai default agar tidak error
    if (!$app_settings) {
        $app_settings = [
            'app_name' => 'HRIS Pro',
            'description' => 'Aplikasi HRIS SaaS',
            'slogan' => 'Kelola SDM dengan Mudah',
            'logo' => 'default_logo.png',
            'favicon' => 'default_favicon.png',
            'og_image' => 'default_og.png',
            'pwa_icon_192' => 'icon-192.png',
            'pwa_icon_512' => 'icon-512.png',
            'theme_color' => '#ea3800'
        ];
    }
} catch (PDOException $e) {
    die("Error mengambil app_settings: " . $e->getMessage());
}

// Path ke folder logo
$logo_path = $base_url . "/assets/img/logos/";
?>