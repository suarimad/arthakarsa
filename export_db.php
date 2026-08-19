<?php
// Panggil Konfigurasi Global & Database
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Panggil Komponen Auth
require_once __DIR__ . '/components/auth.php';


$user_name = $_SESSION['user_name'] ?? 'Superadmin';
$user_role = 'Developer';
$tenant_name = 'Sistem Pusat'; 

// ==========================================
// PROSES GENERATE SQL (PURE PHP UNTUK POSTGRESQL)
// ==========================================
$sql_dump = "-- ==================================================\n";
$sql_dump .= "-- Backup Database HRIS (PostgreSQL)\n";
$sql_dump .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n";
$sql_dump .= "-- ==================================================\n\n";

try {
    // 1. Ambil semua nama tabel di schema public
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $sql_dump .= "-- ------------------------------------------------\n";
        $sql_dump .= "-- Struktur Tabel: $table\n";
        $sql_dump .= "-- ------------------------------------------------\n";
        $sql_dump .= "DROP TABLE IF EXISTS $table CASCADE;\n";
        $sql_dump .= "CREATE TABLE $table (\n";

        // 2. Ambil struktur kolom untuk tabel ini
        $colStmt = $pdo->prepare("SELECT column_name, data_type, character_maximum_length, column_default, is_nullable FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position");
        $colStmt->execute([$table]);
        $columns = $colStmt->fetchAll(PDO::FETCH_ASSOC);

        $colDefs = [];
        foreach ($columns as $col) {
            $def = "    " . $col['column_name'] . " " . $col['data_type'];
            // Tambahkan panjang karakter jika ada (misal VARCHAR(255))
            if ($col['character_maximum_length']) {
                $def .= "(" . $col['character_maximum_length'] . ")";
            }
            // Nullable
            if ($col['is_nullable'] === 'NO') {
                $def .= " NOT NULL";
            }
            // Default value
            if ($col['column_default'] !== null) {
                $def .= " DEFAULT " . $col['column_default'];
            }
            $colDefs[] = $def;
        }
        $sql_dump .= implode(",\n", $colDefs);
        $sql_dump .= "\n);\n\n";

        // 3. Ambil dan Generate Data (INSERT INTO)
        $dataStmt = $pdo->query("SELECT * FROM $table");
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $sql_dump .= "-- Data untuk Tabel: $table\n";
            foreach ($rows as $row) {
                $keys = array_keys($row);
                $values = array_values($row);

                // Escape values
                $escapedValues = array_map(function($val) {
                    if ($val === null) return 'NULL';
                    $val = str_replace("'", "''", $val); // Escape single quotes in pgsql
                    return "'$val'";
                }, $values);

                $sql_dump .= "INSERT INTO $table (" . implode(", ", $keys) . ") VALUES (" . implode(", ", $escapedValues) . ");\n";
            }
        }
        $sql_dump .= "\n\n";
    }
    $sql_dump .= "-- ==================================================\n";
    $sql_dump .= "-- Backup Selesai\n";
    $sql_dump .= "-- ==================================================\n";

} catch (Exception $e) {
    $sql_dump = "Terjadi Kesalahan saat mengekspor database: \n" . $e->getMessage();
}

// Fitur Download SQL Otomatis via Action
if (isset($_GET['download']) && $_GET['download'] === 'true') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup_db_' . date('Y_m_d_His') . '.sql"');
    echo $sql_dump;
    exit;
}

// 1. Load Head
require_once __DIR__ . '/components/head.php';
// 2. Load Sidebar
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
    <!-- Tambahan flex flex-col agar area membentang sempurna ke bawah -->
    <main class="w-full min-h-screen pb-24 md:pb-8 md:px-6 flex flex-col">
        
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div id="toast" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium">
            <i id="toastIcon" class="w-4 h-4"></i>
            <span id="toastMsg"></span>
        </div>

        <!-- max-w-4xl diubah menjadi w-full, dan ditambahkan class flex-1 flex flex-col -->
        <div class="px-5 md:px-0 mt-2 w-full mx-auto space-y-5 flex-1 flex flex-col pb-4">
            
            <div class="flex justify-between items-center px-1 shrink-0">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Ekspor Database SQL</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Alat khusus developer untuk backup struktur & data.</p>
                </div>
                
                <div class="flex gap-2">
                    <button onclick="copySql()" class="bg-surface text-gray-600 border border-gray-200 px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:bg-gray-50 transition shadow-sm">
                        <i data-lucide="copy" class="w-4 h-4"></i> Salin
                    </button>
                    <a href="export_db?download=true" class="bg-primary text-surface px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:opacity-90 transition shadow-sm">
                        <i data-lucide="download" class="w-4 h-4"></i> Unduh (.sql)
                    </a>
                </div>
            </div>

            <!-- Textarea Output SQL (h-[65vh] diubah menjadi flex-1 min-h-[65vh] agar benar-benar Full Screen) -->
            <div class="bg-surface md:border border-gray-100 md:rounded-3xl shadow-sm p-4 flex-1 flex flex-col min-h-[65vh]">
                <textarea id="sqlOutput" class="w-full h-full bg-[#1e1e1e] text-[#d4d4d4] font-mono text-[11px] md:text-xs p-4 rounded-xl focus:outline-none resize-none border border-gray-200 leading-relaxed overflow-y-auto" readonly><?= htmlspecialchars($sql_dump) ?></textarea>
            </div>

        </div>
    </main>
</div>

<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>

<script>
    lucide.createIcons();

    function showToast(msg, type) {
        const toast = document.getElementById('toast');
        const msgEl = document.getElementById('toastMsg');
        const iconEl = document.getElementById('toastIcon');

        msgEl.textContent = msg;
        toast.className = 'fixed top-5 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium';

        if (type === 'failed' || type === 'error') {
            toast.classList.add('bg-failed/10', 'text-failed', 'border-failed/20');
            iconEl.setAttribute('data-lucide', 'alert-circle');
        } else if (type === 'warning') {
            toast.classList.add('bg-pending/10', 'text-pending', 'border-pending/20');
            iconEl.setAttribute('data-lucide', 'alert-triangle');
        } else {
            toast.classList.add('bg-success/10', 'text-success', 'border-success/20');
            iconEl.setAttribute('data-lucide', 'check-circle');
        }
        lucide.createIcons();

        setTimeout(() => toast.classList.remove('opacity-0', '-translate-y-full'), 100);
        setTimeout(() => toast.classList.add('opacity-0', '-translate-y-full'), 4000);
    }

    // Fungsi Salin SQL ke Clipboard
    function copySql() {
        const copyText = document.getElementById("sqlOutput");
        copyText.select();
        copyText.setSelectionRange(0, 999999); // Untuk perangkat mobile

        navigator.clipboard.writeText(copyText.value).then(() => {
            showToast('Script SQL berhasil disalin ke clipboard!', 'success');
        }).catch(err => {
            showToast('Gagal menyalin teks.', 'failed');
        });
    }
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>