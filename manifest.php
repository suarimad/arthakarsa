<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/config.php';

$manifest = [
    "name" => $app_settings['app_name'],
    "short_name" => $app_settings['app_name'],
    "description" => $app_settings['description'],
    "start_url" => $base_url . "/",
    "display" => "standalone",
    "background_color" => "#f3f4f6", // Warna background dari warna 'background' tailwind
    "theme_color" => $app_settings['theme_color'],
    "orientation" => "portrait-primary",
    "icons" => [
        [
            "src" => $logo_path . $app_settings['pwa_icon_192'],
            "sizes" => "192x192",
            "type" => "image/png"
        ],
        [
            "src" => $logo_path . $app_settings['pwa_icon_512'],
            "sizes" => "512x512",
            "type" => "image/png"
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>