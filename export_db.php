<?php
// Load konfigurasi database (Pastikan masih terhubung ke PostgreSQL)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Ambil schema dari .env, default ke 'public' jika kosong
$schema = getenv('DB_SCHEMA') ?: 'public';
$db_structure = "";

try {
    // 1. Ambil semua nama tabel
    $stmtTables = $pdo->prepare("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = :schema AND table_type = 'BASE TABLE'
        ORDER BY table_name
    ");
    $stmtTables->execute(['schema' => $schema]);
    $tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tables as $t) {
        $tableName = $t['table_name'];
        $db_structure .= "Tabel: {$tableName}\n";
        $db_structure .= str_repeat("=", 80) . "\n";
        $db_structure .= sprintf("%-20s | %-25s | %-8s | %s\n", "NAMA KOLOM", "TIPE DATA", "NULLABLE", "DEFAULT");
        $db_structure .= str_repeat("-", 80) . "\n";

        // 2. Ambil detail kolom untuk setiap tabel
        $stmtCols = $pdo->prepare("
            SELECT column_name, data_type, character_maximum_length, column_default, is_nullable
            FROM information_schema.columns
            WHERE table_schema = :schema AND table_name = :table_name
            ORDER BY ordinal_position
        ");
        $stmtCols->execute(['schema' => $schema, 'table_name' => $tableName]);
        $columns = $stmtCols->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $c) {
            $colName = $c['column_name'];
            
            // Format tipe data (misal: character varying(255))
            $type = $c['data_type'];
            if ($c['character_maximum_length']) {
                $type .= "(" . $c['character_maximum_length'] . ")";
            }
            
            $nullable = $c['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $c['column_default'] ? $c['column_default'] : "Tidak Ada";

            $db_structure .= sprintf("%-20s | %-25s | %-8s | %s\n", $colName, $type, $nullable, $default);
        }
        $db_structure .= "\n\n";
    }
} catch (PDOException $e) {
    die("Gagal membaca struktur database: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Struktur DB (PostgreSQL)</title>
    <style>
        body { font-family: monospace; background-color: #1e1e1e; color: #d4d4d4; padding: 20px; margin: 0; }
        .container { max-width: 900px; margin: auto; }
        h2 { color: #569cd6; }
        p { color: #9cdcfe; font-size: 14px; }
        textarea { width: 100%; height: 70vh; background-color: #252526; color: #d4d4d4; border: 1px solid #333; padding: 15px; font-family: monospace; font-size: 13px; outline: none; border-radius: 8px; resize: vertical; }
        button { padding: 12px 24px; background-color: #ea3800; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; margin-bottom: 15px; transition: 0.2s; }
        button:hover { background-color: #c93000; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Export Struktur PostgreSQL (Schema: <?= htmlspecialchars($schema) ?>)</h2>
        <p>Silakan klik tombol "Copy Semua", lalu paste (*tempel*) isinya ke chat agar saya bisa mengubahnya menjadi SQL MySQL murni.</p>
        
        <button id="copyBtn">📋 Copy Semua Teks</button>
        
        <textarea id="dbText" readonly><?= htmlspecialchars($db_structure) ?></textarea>
    </div>

    <script>
        document.getElementById('copyBtn').addEventListener('click', function() {
            var textarea = document.getElementById('dbText');
            textarea.select();
            textarea.setSelectionRange(0, 99999); /* Untuk mobile */
            document.execCommand('copy');
            this.innerText = '✅ Teks Berhasil Dicopy!';
            this.style.backgroundColor = '#28a745';
            setTimeout(() => {
                this.innerText = '📋 Copy Semua Teks';
                this.style.backgroundColor = '#ea3800';
            }, 3000);
        });
    </script>
</body>
</html>