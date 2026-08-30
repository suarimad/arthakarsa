<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// SET TIMEZONE JAKARTA
date_default_timezone_set('Asia/Jakarta');
$current_time = date('Y-m-d H:i:s');

// Identifikasi ID Tenant dari Session User Aktif atau Session Registrasi Pending
$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['pending_tenant_id'] ?? null;

if (!$tenant_id) {
    header("Location: login");
    exit;
}

// Ambil data tenant terkini
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    header("Location: login");
    exit;
}

$tenant_status = strtolower($tenant['status'] ?? 'pending');
$has_package_selected = ((int)($tenant['total_users'] ?? 0) > 0);

// ==============================================================================
// PENANGANAN AJAX (SUBMIT PEMILIHAN/PERPANJANGAN PAKET & WA NOTIFICATION)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    $action = $_POST['ajax_action'];

    // 1. SUBMIT / UPDATE PAKET
    if ($action === 'submit_package') {
        $package_type = $_POST['package_type'] ?? 'demo';
        $phone_number = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');

        if (empty($phone_number)) {
            echo json_encode(['status' => 'error', 'message' => 'Nomor WhatsApp wajib diisi!']);
            exit;
        }

        $active_until = NULL;
        $total_price = 0;

        if ($package_type === 'demo') {
            $total_users = 5;
            $duration_choice = '7_days';
            $active_until = date('Y-m-d H:i:s', strtotime('+7 days'));
            $total_price = 0;
        } else {
            $total_users = (int)($_POST['total_users_count'] ?? 10);
            $total_users = max(1, $total_users);
            $duration_choice = $_POST['duration'] ?? '6_months';
            
            // Kalkulasi Biaya Backend (Lebih Aman)
            if ($total_users >= 101) $ppu = 5000;
            elseif ($total_users >= 51) $ppu = 8000;
            elseif ($total_users >= 6) $ppu = 10000;
            else $ppu = 15000;

            $months = 6;
            $discount = 1.0;
            if ($duration_choice === '6_months') $months = 6;
            elseif ($duration_choice === '1_year') $months = 12;
            elseif ($duration_choice === '5_years') { $months = 60; $discount = 0.5; }

            $total_price = ($total_users * $ppu * $months) * $discount;
        }

        try {
            // Update Tabel Tenants dengan Kolom total_price
            $stmtUpdate = $pdo->prepare("
                UPDATE tenants 
                SET phone = ?, total_users = ?, package_type = ?, duration = ?, total_price = ?, status = 'pending', active_until = ?, updated_at = ? 
                WHERE id = ?
            ");
            $stmtUpdate->execute([$phone_number, $total_users, $package_type, $duration_choice, $total_price, $active_until, $current_time, $tenant_id]);

            // Format Pesan WhatsApp Notifikasi
            $company_name = $tenant['name'];
            $admin_email = $tenant['email'] ?: ($_SESSION['pending_email'] ?? 'Tidak ada email');

            $pkg_label = ($package_type === 'demo') ? "Demo (7 Hari - Max 5 Karyawan)" : "Premium ({$total_users} Karyawan)";
            $dur_label = "7 Hari";
            if ($package_type === 'premium') {
                if ($duration_choice === '6_months') $dur_label = "6 Bulan";
                else if ($duration_choice === '1_year') $dur_label = "1 Tahun";
                else if ($duration_choice === '5_years') $dur_label = "5 Tahun (Diskon 50%)";
            }
            
            $formatted_price = "Rp " . number_format($total_price, 0, ',', '.');
            $type_request_title = $has_package_selected ? "REQUEST PERUBAHAN PAKET SAAS" : "REQUEST PEMBELIAN SAAS BARU";

            $wa_message = " *{$type_request_title}*\n\n";
            $wa_message .= " *Nama Perusahaan:* {$company_name}\n";
            $wa_message .= " *Email Admin:* {$admin_email}\n";
            $wa_message .= " *Nomor WA Admin:* {$phone_number}\n";
            $wa_message .= " *Paket Dipilih:* {$pkg_label}\n";
            $wa_message .= " *Durasi Paket:* {$dur_label}\n";
            $wa_message .= " *Jumlah Karyawan:* {$total_users} Karyawan\n";
            $wa_message .= " *Total Biaya:* {$formatted_price}\n";
            $wa_message .= " *Waktu Pengajuan:* " . date('d M Y H:i') . " WIB\n\n";
            $wa_message .= "Mohon segera diproses dan diverifikasi. Terima Kasih.";

            // Kirim WhatsApp Notification API via cURL
            $wa_api_url = "https://dewa.densucode.com/send.php";
            $wa_payload = http_build_query([
                'deviceId' => 'EVLC6mSp64Xe',
                'to'       => '6281314270616',
                'message'  => $wa_message
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $wa_api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $wa_payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "x-api-key: podcastseminggu",
                "Content-Type: application/x-www-form-urlencoded"
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $wa_response = curl_exec($ch);
            curl_close($ch);

            echo json_encode([
                'status' => 'success',
                'message' => 'Request berhasil dikirim!'
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memproses: ' . $e->getMessage()]);
            exit;
        }
    }

    // 2. REMINDER ADMIN VIA WA API
    if ($action === 'reminder_admin') {
        try {
            $company_name = $tenant['name'];
            $admin_email = $tenant['email'] ?: 'Tidak ada email';
            $phone_number = $tenant['phone'] ?: 'Tidak ada nomor WA';
            $package_type = $tenant['package_type'] ?? 'demo';
            $total_users = (int)($tenant['total_users'] ?? 5);
            $duration_choice = $tenant['duration'] ?? '7_days';
            $total_price = (float)($tenant['total_price'] ?? 0);

            $pkg_label = ($package_type === 'demo') ? "Demo" : "Premium ({$total_users} Karyawan)";
            $dur_label = "7 Hari";
            if ($package_type === 'premium') {
                if ($duration_choice === '6_months') $dur_label = "6 Bulan";
                else if ($duration_choice === '1_year') $dur_label = "1 Tahun";
                else if ($duration_choice === '5_years') $dur_label = "5 Tahun (Diskon 50%)";
            }
            
            $formatted_price = "Rp " . number_format($total_price, 0, ',', '.');

            $wa_message = " *REMINDER KONFIRMASI PEMBELIAN SAAS*\n\n";
            $wa_message .= " *Nama Perusahaan:* {$company_name}\n";
            $wa_message .= " *Email Admin:* {$admin_email}\n";
            $wa_message .= " *Nomor WA:* {$phone_number}\n";
            $wa_message .= " *Paket:* {$pkg_label}\n";
            $wa_message .= " *Durasi:* {$dur_label}\n";
            $wa_message .= " *Total Biaya:* {$formatted_price}\n";
            $wa_message .= " *Waktu Reminder:* " . date('d M Y H:i') . " WIB\n\n";
            $wa_message .= "Halo Admin, mohon follow up dan konfirmasi aktivasi akun perusahaan kami. Terima Kasih.";

            $wa_api_url = "https://dewa.densucode.com/send.php";
            $wa_payload = http_build_query([
                'deviceId' => 'EVLC6mSp64Xe',
                'to'       => '6281314270616',
                'message'  => $wa_message
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $wa_api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $wa_payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "x-api-key: podcastseminggu",
                "Content-Type: application/x-www-form-urlencoded"
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $wa_response = curl_exec($ch);
            curl_close($ch);

            echo json_encode([
                'status' => 'success',
                'message' => 'Reminder berhasil dikirim ke Admin!'
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengirim reminder: ' . $e->getMessage()]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $tenant_status === 'suspended' ? 'Akun Ditangguhkan' : 'Status Perusahaan' ?> - <?= htmlspecialchars($app_settings['app_name'] ?? 'HRIS') ?></title>
    <meta name="theme-color" content="#f3f4f6">
    <link rel="icon" type="image/png" href="<?= $logo_path . ($app_settings['favicon'] ?? 'default_favicon.png') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/output.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --color-primary: <?= htmlspecialchars($app_settings['theme_color'] ?? '#ea3800') ?>; }
        button, a, input, select, textarea { touch-action: manipulation; }
    </style>
</head>
<body class="bg-background font-poppins min-h-screen py-8 px-4 flex items-center justify-center relative">

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-background/80 z-[100] hidden items-center justify-center flex-col backdrop-blur-sm">
        <i data-lucide="loader-2" class="w-8 h-8 text-primary animate-spin mb-3"></i>
        <p class="text-xs font-semibold text-gray-700">Memproses...</p>
    </div>

    <div class="w-full max-w-xl bg-surface p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100 my-auto">
        
        <!-- HEADER DINAMIS BERDASARKAN STATUS -->
        <?php if ($tenant_status === 'suspended'): ?>
            <div class="text-center mb-6">
                <div class="w-14 h-14 rounded-full bg-failed/10 text-failed flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="alert-octagon" class="w-7 h-7"></i>
                </div>
                <h1 class="text-xl font-bold text-gray-800">Masa Berlangganan Berakhir</h1>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                    Akses layanan perusahaan <span class="font-bold text-gray-800"><?= htmlspecialchars($tenant['name']) ?></span> ditangguhkan (Expired). Silakan perbarui paket untuk mengaktifkan kembali akses.
                </p>
            </div>
        <?php else: ?>
            <div class="text-center mb-6">
                <div class="w-14 h-14 rounded-full bg-primary/10 text-primary flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="clock" class="w-7 h-7"></i>
                </div>
                <h1 class="text-xl font-bold text-gray-800">Status Akun: Menunggu Konfirmasi</h1>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                    Selamat datang <span class="font-bold text-gray-800"><?= htmlspecialchars($tenant['name']) ?></span>!
                </p>
            </div>
        <?php endif; ?>

        <!-- KONDISI 1: JIKA SUDAH PERNAH MEMILIH PAKET (total_users > 0) -->
        <?php if ($has_package_selected): ?>
            <div id="selectedPackageSummary" class="space-y-5">
                
                <div class="p-5 bg-gray-50 border border-gray-200 rounded-2xl space-y-3">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Paket Terpilih Saat Ini</span>
                        <?php if ($tenant_status === 'suspended'): ?>
                            <span class="px-2.5 py-1 bg-failed/10 text-failed text-[10px] font-bold rounded-md uppercase">Ditangguhkan</span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 bg-pending/10 text-pending text-[10px] font-bold rounded-md uppercase">Menunggu Verifikasi</span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-1">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Tipe Paket</span>
                            <span class="text-xs font-bold text-gray-800 capitalize"><?= htmlspecialchars($tenant['package_type'] ?? 'Demo') ?></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Batas Karyawan</span>
                            <span class="text-xs font-bold text-primary"><?= (int)$tenant['total_users'] ?> Karyawan</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Durasi</span>
                            <span class="text-xs font-bold text-gray-800">
                                <?php 
                                    $dur = $tenant['duration'] ?? '6_months';
                                    if ($dur === '6_months') echo "6 Bulan";
                                    else if ($dur === '1_year') echo "1 Tahun";
                                    else if ($dur === '5_years') echo "5 Tahun (Diskon 50%)";
                                    else echo "7 Hari (Demo)";
                                ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Nomor WhatsApp</span>
                            <span class="text-xs font-bold text-gray-800"><?= htmlspecialchars($tenant['phone'] ?: '-') ?></span>
                        </div>
                    </div>
                    <div class="pt-2 border-t border-gray-200 mt-2">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Total Biaya</span>
                        <span class="text-sm font-black text-emerald-600">Rp <?= number_format((float)($tenant['total_price'] ?? 0), 0, ',', '.') ?></span>
                    </div>
                </div>

                <div class="space-y-3 pt-2">
                    <!-- BUTTON REMINDER ADMIN -->
                    <button onclick="sendReminderAdmin()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-3.5 rounded-xl transition shadow-sm flex items-center justify-center gap-2">
                        <i data-lucide="bell-ring" class="w-4 h-4"></i> Reminder Admin via WhatsApp
                    </button>

                    <!-- BUTTON UBAH PAKET -->
                    <button onclick="showChangePackageForm()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold py-3 rounded-xl transition flex items-center justify-center gap-2">
                        <i data-lucide="edit-3" class="w-4 h-4"></i> Ubah Pilihan Paket
                    </button>

                    <a href="logout" class="block text-center text-xs font-semibold text-gray-500 hover:text-gray-800 py-2">
                        Keluar / Logout
                    </a>
                </div>

            </div>
        <?php endif; ?>

        <!-- FORM PEMILIHAN / PERUBAHAN PAKET -->
        <form id="packageForm" class="<?= $has_package_selected ? 'hidden mt-6 pt-6 border-t border-gray-100' : '' ?>">
            <div class="space-y-5">
                
                <!-- OPSI 1: DEMO / PREMIUM -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-600 mb-2 uppercase tracking-wider">Tipe Layanan</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative flex flex-col p-4 bg-gray-50 border-2 border-gray-200 rounded-2xl cursor-pointer hover:border-primary transition select-none has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="package_type" value="demo" <?= ($tenant['package_type'] ?? '') === 'demo' || !$has_package_selected ? 'checked' : '' ?> class="sr-only" onchange="togglePackageOptions()">
                            <span class="text-xs font-bold text-gray-800 flex items-center gap-1.5"><i data-lucide="sparkles" class="w-4 h-4 text-primary"></i> Demo (7 Hari)</span>
                            <span class="text-[10px] text-gray-500 mt-1">Maksimal 5 Karyawan</span>
                            <span class="text-xs font-black text-primary mt-2">GRATIS</span>
                        </label>

                        <label class="relative flex flex-col p-4 bg-gray-50 border-2 border-gray-200 rounded-2xl cursor-pointer hover:border-primary transition select-none has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="package_type" value="premium" <?= ($tenant['package_type'] ?? '') === 'premium' ? 'checked' : '' ?> class="sr-only" onchange="togglePackageOptions()">
                            <span class="text-xs font-bold text-gray-800 flex items-center gap-1.5"><i data-lucide="crown" class="w-4 h-4 text-amber-500"></i> Premium</span>
                            <span class="text-[10px] text-gray-500 mt-1">Sesuai skala perusahaan</span>
                            <span class="text-xs font-black text-primary mt-2">Mulai 5.000 / Karyawan</span>
                        </label>
                    </div>
                </div>

                <!-- OPSI KHUSUS PREMIUM (TIER KARYAWAN & DURASI) -->
                <div id="premiumOptionsContainer" class="hidden space-y-4 pt-2 border-t border-gray-100">
                    
                    <!-- HARGA BERDASARKAN JUMLAH KARYAWAN -->
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Skala Jumlah Karyawan</label>
                        <select name="user_tier" id="user_tier" onchange="calculatePrice()" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary cursor-pointer">
                            <option value="10" data-price="15000">1 - 10 Karyawan (Rp 15.000 / Karyawan / Bulan)</option>
                            <option value="50" data-price="10000">6 - 50 Karyawan (Rp 10.000 / Karyawan / Bulan)</option>
                            <option value="100" data-price="8000">51 - 100 Karyawan (Rp 8.000 / Karyawan / Bulan)</option>
                            <option value="500" data-price="5000">101 - 500 Karyawan (Rp 5.000 / Karyawan / Bulan)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Input Jumlah Karyawan Tepat</label>
                        <input type="number" name="total_users_count" id="total_users_count" value="<?= max(10, (int)($tenant['total_users'] ?? 10)) ?>" min="1" max="500" oninput="calculatePrice()" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                    </div>

                    <!-- DURASI PAKET -->
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Durasi Paket Berlangganan</label>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="p-3 bg-gray-50 border border-gray-200 rounded-xl text-center cursor-pointer hover:border-primary transition select-none has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                <input type="radio" name="duration" value="6_months" <?= ($tenant['duration'] ?? '6_months') === '6_months' || ($tenant['duration'] ?? '') === '7_days' ? 'checked' : '' ?> class="sr-only" onchange="calculatePrice()">
                                <span class="text-xs font-bold text-gray-800 block">6 Bulan</span>
                            </label>
                            <label class="p-3 bg-gray-50 border border-gray-200 rounded-xl text-center cursor-pointer hover:border-primary transition select-none has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                <input type="radio" name="duration" value="1_year" <?= ($tenant['duration'] ?? '') === '1_year' ? 'checked' : '' ?> class="sr-only" onchange="calculatePrice()">
                                <span class="text-xs font-bold text-gray-800 block">1 Tahun</span>
                            </label>
                            <label class="p-3 bg-gray-50 border border-gray-200 rounded-xl text-center cursor-pointer hover:border-primary transition select-none has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                <input type="radio" name="duration" value="5_years" <?= ($tenant['duration'] ?? '') === '5_years' ? 'checked' : '' ?> class="sr-only" onchange="calculatePrice()">
                                <span class="text-xs font-bold text-gray-800 block">5 Tahun</span>
                                <span class="text-[9px] font-bold text-emerald-600 block mt-0.5">Diskon 50%</span>
                            </label>
                        </div>
                    </div>

                    <!-- RINGKASAN ESTIMASI BIAYA -->
                    <div class="p-4 bg-primary/5 border border-primary/20 rounded-2xl flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Estimasi Total Biaya</span>
                            <span id="pricePerMonthText" class="text-[10px] text-gray-400">Rp 0 / bulan</span>
                        </div>
                        <span id="totalPriceText" class="text-base font-black text-primary">Rp 0</span>
                    </div>

                </div>

                <!-- INPUT NOMOR WHATSAPP (NUMERIC) -->
                <div>
                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nomor WhatsApp Admin</label>
                    <input type="text" pattern="[0-9]*" inputmode="numeric" name="phone" id="phone_input" required value="<?= htmlspecialchars($tenant['phone'] ?? '') ?>" placeholder="Contoh: 08123456789" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                    <p class="text-[9px] text-gray-400 mt-1">Nomor ini digunakan untuk konfirmasi aktivasi akun Anda.</p>
                </div>

            </div>

            <div class="mt-6 space-y-3">
                <button type="submit" class="w-full bg-primary text-surface text-sm font-bold py-3.5 rounded-xl hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i> Ajukan Request
                </button>

                <?php if ($has_package_selected): ?>
                    <button type="button" onclick="hideChangePackageForm()" class="w-full bg-gray-100 text-gray-600 text-xs font-semibold py-2.5 rounded-xl hover:bg-gray-200 transition">
                        Batal Ubah Paket
                    </button>
                <?php else: ?>
                    <a href="logout" class="block text-center text-xs font-semibold text-gray-500 hover:text-gray-800 py-2">
                        Keluar / Logout
                    </a>
                <?php endif; ?>
            </div>
        </form>

    </div>

    <!-- MODAL POPUP CHAT ADMIN / KONFIRMASI -->
    <div id="successModal" class="fixed inset-0 hidden" style="z-index: 99999;">
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-surface w-full max-w-sm rounded-3xl shadow-2xl p-6 text-center relative flex flex-col items-center">
                
                <div class="w-14 h-14 rounded-full bg-success/10 text-success flex items-center justify-center mb-4">
                    <i data-lucide="check-circle-2" class="w-8 h-8"></i>
                </div>

                <h3 class="text-base font-bold text-gray-800">Request Pembelian Sudah Diterima</h3>
                <p class="text-xs text-gray-500 mt-2 leading-relaxed">Silahkan hubungi admin via whatsapp dibawah untuk konfirmasi</p>

                <div class="w-full space-y-3 mt-6">
                    <a id="waDirectBtn" href="https://wa.me/6281314270616?text=Halo%20Admin,%20saya%20sudah%20melakukan%20request%20pembelian%20paket%20SaaS%20perusahaan%20<?= urlencode($tenant['name']) ?>." target="_blank" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center justify-center gap-2">
                        <i data-lucide="message-square" class="w-4 h-4"></i> Hubungi Admin via WhatsApp
                    </a>
                    <button onclick="window.location.reload()" class="w-full py-3 border border-gray-200 text-gray-600 rounded-xl text-xs font-semibold hover:bg-gray-50 transition">
                        Tutup & Reload
                    </button>
                </div>

            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/components/toast.php'; ?>

    <script>
        lucide.createIcons();

        function togglePackageOptions() {
            const pkg = document.querySelector('input[name="package_type"]:checked').value;
            const container = document.getElementById('premiumOptionsContainer');
            if (pkg === 'premium') {
                container.classList.remove('hidden');
                calculatePrice();
            } else {
                container.classList.add('hidden');
            }
        }

        function calculatePrice() {
            const users = parseInt(document.getElementById('total_users_count').value) || 1;
            let pricePerUser = 15000;

            if (users >= 101) pricePerUser = 5000;
            else if (users >= 51) pricePerUser = 8000;
            else if (users >= 6) pricePerUser = 10000;
            else pricePerUser = 15000;

            const durRadio = document.querySelector('input[name="duration"]:checked');
            const durVal = durRadio ? durRadio.value : '6_months';
            let months = 6;
            let discount = 1.0;

            if (durVal === '6_months') months = 6;
            else if (durVal === '1_year') months = 12;
            else if (durVal === '5_years') { months = 60; discount = 0.5; }

            const monthlyTotal = users * pricePerUser;
            const grandTotal = (monthlyTotal * months) * discount;

            const formatRp = (val) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);

            document.getElementById('pricePerMonthText').innerText = formatRp(monthlyTotal) + " / bulan";
            document.getElementById('totalPriceText').innerText = formatRp(grandTotal);
        }

        togglePackageOptions();

        function showChangePackageForm() {
            $('#selectedPackageSummary').addClass('hidden');
            $('#packageForm').removeClass('hidden');
            lucide.createIcons();
        }

        function hideChangePackageForm() {
            $('#packageForm').addClass('hidden');
            $('#selectedPackageSummary').removeClass('hidden');
            lucide.createIcons();
        }

        // FUNGSI REMINDER ADMIN VIA WA API
        function sendReminderAdmin() {
            document.getElementById('loadingOverlay').classList.remove('hidden');
            document.getElementById('loadingOverlay').classList.add('flex');

            const formData = new FormData();
            formData.append('ajax_action', 'reminder_admin');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.add('hidden');
                document.getElementById('loadingOverlay').classList.remove('flex');

                if (data.status === 'success') {
                    document.getElementById('successModal').classList.remove('hidden');
                    lucide.createIcons();
                } else {
                    if(typeof window.showToast === 'function') window.showToast(data.message, 'error');
                }
            })
            .catch(() => {
                document.getElementById('loadingOverlay').classList.add('hidden');
                document.getElementById('loadingOverlay').classList.remove('flex');
                if(typeof window.showToast === 'function') window.showToast('Gagal mengirim reminder.', 'error');
            });
        }

        // SUBMIT FORM PEMILIHAN / UBAH PAKET
        $('#packageForm').on('submit', function(e) {
            e.preventDefault();
            document.getElementById('loadingOverlay').classList.remove('hidden');
            document.getElementById('loadingOverlay').classList.add('flex');

            const formData = new FormData(this);
            formData.append('ajax_action', 'submit_package');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.add('hidden');
                document.getElementById('loadingOverlay').classList.remove('flex');

                if (data.status === 'success') {
                    document.getElementById('successModal').classList.remove('hidden');
                    lucide.createIcons();
                } else {
                    if(typeof window.showToast === 'function') window.showToast(data.message, 'error');
                }
            })
            .catch(() => {
                document.getElementById('loadingOverlay').classList.add('hidden');
                document.getElementById('loadingOverlay').classList.remove('flex');
                if(typeof window.showToast === 'function') window.showToast('Gagal memproses request.', 'error');
            });
        });
    </script>
</body>
</html>