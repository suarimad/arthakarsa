<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

// ==============================================================================
// LOGIKA HAK AKSES
// ==============================================================================
$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');

// Admin, Superadmin, HR, dan Finance bisa melihat semua slip gaji. 
// Employee / Manager biasa hanya bisa melihat slip gajinya sendiri.
$can_view_all = in_array($role_id, [1, 2, 3, 6]) || in_array($role_name_session, ['superadmin', 'admin', 'hr', 'finance']);

// ==============================================================================
// VALIDASI & PENGAMBILAN DATA
// ==============================================================================
$payslip_id = $_GET['id'] ?? null;

if (!$payslip_id) {
    die("ID Slip Gaji tidak valid.");
}

// Ambil data slip gaji (Hanya yang berstatus 'paid')
$query = "
    SELECT p.*, 
           u.name as employee_name, u.email, 
           pos.name as position_name, 
           d.name as department_name, 
           us.bank_name, us.bank_account
    FROM payslips p 
    LEFT JOIN users u ON p.user_id = u.id 
    LEFT JOIN positions pos ON u.position_id = pos.id
    LEFT JOIN departments d ON pos.department_id = d.id
    LEFT JOIN user_salaries us ON u.id = us.user_id
    WHERE p.id = ? AND p.tenant_id = ? AND p.status = 'paid'
";

$params = [$payslip_id, $tenant_id];

// Jika bukan admin/hr/finance, pastikan slip gaji ini miliknya sendiri
if (!$can_view_all) {
    $query .= " AND p.user_id = ?";
    $params[] = $user_id;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payslip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payslip) {
    die("Slip gaji tidak ditemukan, belum dibayar, atau Anda tidak memiliki akses ke data ini.");
}

// Ambil rincian pendapatan & potongan
$stmtDet = $pdo->prepare("SELECT type, name, amount FROM payslip_details WHERE payslip_id = ? ORDER BY type DESC, id ASC");
$stmtDet->execute([$payslip_id]);
$details = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$earnings = [];
$deductions = [];
foreach ($details as $d) {
    if ($d['type'] === 'earning') $earnings[] = $d;
    else $deductions[] = $d;
}

// Ambil info perusahaan (Tenant) beserta logo
$stmtTenant = $pdo->prepare("SELECT name, email, phone, address, logo FROM tenants WHERE id = ?");
$stmtTenant->execute([$tenant_id]);
$tenant = $stmtTenant->fetch(PDO::FETCH_ASSOC);

// Helper Data
$month_names = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
$period_str = $month_names[$payslip['month']] . " " . $payslip['year'];

// Path ke folder assets/img/tenants/
$tenant_logo = !empty($tenant['logo']) ? ($base_url ?? '') . '/assets/img/tenants/' . htmlspecialchars($tenant['logo']) : null;

// Format Rupiah
function formatRp($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji - <?= htmlspecialchars($payslip['employee_name']) ?> - <?= $period_str ?></title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { primary: '#ea3800' }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* Konfigurasi Ukuran Kertas A4 (Print to PDF) */
        @page {
            size: A4;
            margin: 0; /* Reset margin agar full control dari HTML */
        }
        
        body {
            background-color: #f3f4f6; /* Warna abu-abu background web */
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .a4-wrapper {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background-color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            position: relative;
            box-sizing: border-box;
            padding: 20mm;
        }

        /* Saat diprint (Save to PDF), sembunyikan semua elemen UI web dan pastikan A4 fit */
        @media print {
            body { background-color: #ffffff; margin: 0; }
            .a4-wrapper { margin: 0; box-shadow: none; border: none; width: 100%; min-height: auto; }
            .no-print { display: none !important; }
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 8rem;
            color: rgba(234, 56, 0, 0.03); /* Warna primary sangat transparan */
            font-weight: 800;
            white-space: nowrap;
            z-index: 0;
            pointer-events: none;
        }
    </style>
</head>
<body class="antialiased text-gray-800">

    <!-- Tombol Aksi Web (Sembunyi saat Print) -->
    <div class="no-print fixed top-4 right-4 flex gap-3 z-50">
        <button onclick="window.close()" class="px-5 py-2.5 bg-white text-gray-700 border border-gray-200 rounded-xl shadow-sm text-sm font-semibold hover:bg-gray-50 flex items-center gap-2 transition">
            <i data-lucide="x" class="w-4 h-4"></i> Tutup
        </button>
        <button onclick="window.print()" class="px-5 py-2.5 bg-primary text-white rounded-xl shadow-md text-sm font-semibold hover:opacity-90 flex items-center gap-2 transition">
            <i data-lucide="printer" class="w-4 h-4"></i> Cetak / Simpan PDF
        </button>
    </div>

    <!-- Kertas A4 -->
    <div class="a4-wrapper flex flex-col relative overflow-hidden">
        
        <!-- Watermark -->
        <div class="watermark">PAID</div>

        <!-- ================= HEADER PERUSAHAAN ================= -->
        <header class="flex justify-between items-start border-b-2 border-gray-800 pb-5 mb-6 relative z-10">
            <div class="flex items-center gap-3">
                <?php if ($tenant_logo): ?>
                    <img src="<?= $tenant_logo ?>" alt="Logo" class="w-14 h-14 object-contain">
                <?php else: ?>
                    <div class="w-14 h-14 bg-primary/10 text-primary rounded-xl flex items-center justify-center border border-primary/20">
                        <i data-lucide="building" class="w-7 h-7"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h1 class="text-xl font-black text-gray-900 tracking-tight"><?= htmlspecialchars($tenant['name'] ?? 'Perusahaan') ?></h1>
                    <p class="text-[10px] text-gray-500 mt-0.5 max-w-sm leading-relaxed">
                        <?= htmlspecialchars($tenant['address'] ?? 'Alamat belum diatur') ?><br>
                        <?php if(!empty($tenant['phone'])): ?>
                            Telp: <?= htmlspecialchars($tenant['phone']) ?>
                        <?php endif; ?>
                        <?php if(!empty($tenant['phone']) && !empty($tenant['email'])): ?> | <?php endif; ?>
                        <?php if(!empty($tenant['email'])): ?>
                            Email: <?= htmlspecialchars($tenant['email']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-black text-primary uppercase tracking-widest mb-1">SLIP GAJI</h2>
                <p class="text-[10px] font-bold text-gray-600 bg-gray-100 inline-block px-2.5 py-1 rounded-md">Periode: <?= $period_str ?></p>
            </div>
        </header>

        <!-- ================= INFO KARYAWAN ================= -->
        <section class="grid grid-cols-2 gap-6 mb-8 relative z-10">
            <div>
                <table class="w-full text-xs">
                    <tr><td class="py-1 text-gray-500 w-28 font-medium">Nama Karyawan</td><td class="py-1 font-bold text-gray-900">: <?= htmlspecialchars($payslip['employee_name']) ?></td></tr>
                    <tr><td class="py-1 text-gray-500 w-28 font-medium">Departemen</td><td class="py-1 font-bold text-gray-900">: <?= htmlspecialchars($payslip['department_name'] ?? '-') ?></td></tr>
                    <tr><td class="py-1 text-gray-500 w-28 font-medium">Jabatan</td><td class="py-1 font-bold text-gray-900">: <?= htmlspecialchars($payslip['position_name'] ?? '-') ?></td></tr>
                </table>
            </div>
            <div>
                <table class="w-full text-xs">
                    <tr><td class="py-1 text-gray-500 w-28 font-medium">ID Slip</td><td class="py-1 font-bold text-gray-900">: #PSL-<?= str_pad($payslip['id'], 6, '0', STR_PAD_LEFT) ?></td></tr>
                    <tr><td class="py-1 text-gray-500 w-28 font-medium">Tanggal Bayar</td><td class="py-1 font-bold text-gray-900">: <?= !empty($payslip['payment_date']) ? date('d F Y', strtotime($payslip['payment_date'])) : '-' ?></td></tr>
                    <tr><td class="py-1 text-gray-500 w-28 font-medium">Metode</td><td class="py-1 font-bold text-gray-900">: <?= htmlspecialchars($payslip['bank_name'] ?? 'Tunai') ?> (<?= htmlspecialchars($payslip['bank_account'] ?? '-') ?>)</td></tr>
                </table>
            </div>
        </section>

        <!-- ================= TABEL RINCIAN GAJI ================= -->
        <section class="flex-1 relative z-10">
            <div class="grid grid-cols-2 gap-6">
                
                <!-- PENDAPATAN (EARNINGS) -->
                <div>
                    <h3 class="text-xs font-black text-gray-800 uppercase tracking-wider mb-2 border-b border-gray-300 pb-1.5">Pendapatan</h3>
                    <table class="w-full text-xs">
                        <tbody>
                            <?php if (empty($earnings)): ?>
                                <tr><td class="py-1.5 text-gray-400 italic">Tidak ada pendapatan</td><td class="py-1.5 text-right">-</td></tr>
                            <?php else: ?>
                                <?php foreach($earnings as $e): ?>
                                    <tr>
                                        <td class="py-1.5 text-gray-700 font-medium"><?= htmlspecialchars($e['name']) ?></td>
                                        <td class="py-1.5 text-right font-semibold text-gray-900"><?= formatRp($e['amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- POTONGAN (DEDUCTIONS) -->
                <div>
                    <h3 class="text-xs font-black text-gray-800 uppercase tracking-wider mb-2 border-b border-gray-300 pb-1.5">Potongan</h3>
                    <table class="w-full text-xs">
                        <tbody>
                            <?php if (empty($deductions)): ?>
                                <tr><td class="py-1.5 text-gray-400 italic">Tidak ada potongan</td><td class="py-1.5 text-right">-</td></tr>
                            <?php else: ?>
                                <?php foreach($deductions as $d): ?>
                                    <tr>
                                        <td class="py-1.5 text-gray-700 font-medium"><?= htmlspecialchars($d['name']) ?></td>
                                        <td class="py-1.5 text-right font-semibold text-red-600">- <?= formatRp($d['amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- RINGKASAN (SUMMARY) -->
            <div class="mt-6 border-t-2 border-gray-800 pt-3">
                <div class="flex justify-end">
                    <div class="w-1/2">
                        <table class="w-full text-xs">
                            <tr>
                                <td class="py-1.5 text-gray-600 font-semibold">Total Pendapatan</td>
                                <td class="py-1.5 text-right font-bold text-gray-900"><?= formatRp($payslip['basic_salary'] + $payslip['total_earnings']) ?></td>
                            </tr>
                            <tr>
                                <td class="py-1.5 text-gray-600 font-semibold border-b border-gray-200 pb-3">Total Potongan</td>
                                <td class="py-1.5 text-right font-bold text-red-600 border-b border-gray-200 pb-3">- <?= formatRp($payslip['total_deductions']) ?></td>
                            </tr>
                            <tr>
                                <td class="py-3 text-sm font-black text-gray-800 uppercase tracking-wider">Take Home Pay</td>
                                <td class="py-3 text-right text-lg font-black text-primary"><?= formatRp($payslip['net_salary']) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= TANDA TANGAN ================= -->
        <section class="mt-12 flex justify-between relative z-10 pt-8">
            <div class="text-center w-40">
                <p class="text-xs font-semibold text-gray-600 mb-16">Penerima,</p>
                <p class="text-xs font-bold text-gray-900 underline underline-offset-4"><?= htmlspecialchars($payslip['employee_name']) ?></p>
                <p class="text-[10px] text-gray-500 mt-1">Karyawan</p>
            </div>
            
            <div class="text-center w-40">
                <p class="text-xs font-semibold text-gray-600 mb-16">Disetujui Oleh,</p>
                <p class="text-xs font-bold text-gray-900 underline underline-offset-4">HR & Finance</p>
                <p class="text-[10px] text-gray-500 mt-1"><?= htmlspecialchars($tenant['name'] ?? 'Perusahaan') ?></p>
            </div>
        </section>

        <!-- Footer -->
        <footer class="mt-8 pt-4 border-t border-gray-200 text-center text-[9px] text-gray-400 relative z-10">
            Dokumen ini diterbitkan secara elektronik oleh sistem HRIS dan sah secara hukum tanpa tanda tangan basah.
        </footer>

    </div>

    <script>
        lucide.createIcons();
        
        // Opsional: Otomatis memicu jendela Print saat halaman selesai dimuat.
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>