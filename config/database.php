<?php
// Fungsi sederhana untuk membaca file .env
function loadEnv($path) {
    if (!file_exists($path)) {
        die("File .env tidak ditemukan!");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Abaikan baris komentar
        if (strpos(trim($line), '#') === 0) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load .env dari root folder (naik 1 tingkat dari folder config)
loadEnv(__DIR__ . '/../.env');

// Ambil kredensial dari Environment Variables
$db_host   = getenv('DB_HOST');
$db_port   = getenv('DB_PORT');
$db_name   = getenv('DB_NAME');
$db_user   = getenv('DB_USER');
$db_pass   = getenv('DB_PASS');
$db_schema = getenv('DB_SCHEMA');

try {
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    
    $pdo->exec("SET search_path TO $db_schema");

} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}
?>