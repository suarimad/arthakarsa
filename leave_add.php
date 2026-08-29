<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// Ambil Timezone dari tenant_settings
$stmtTS = $pdo->prepare("SELECT timezone FROM tenant_settings WHERE tenant_id = ?");
$stmtTS->execute([$tenant_id]);
$tz_setting = $stmtTS->fetchColumn() ?: 'Asia/Jakarta';
date_default_timezone_set($tz_setting);

// Waktu DATETIME berdasarkan timezone tenant
$current_time = date('Y-m-d H:i:s');

// Ambil Sisa Cuti User
$curr_year = date('Y');
$stmtQuota = $pdo->prepare("SELECT total_quota, used_quota FROM leave_balances WHERE user_id = ? AND year = ? AND tenant_id = ?");
$stmtQuota->execute([$user_id, $curr_year, $tenant_id]);
$quotaData = $stmtQuota->fetch(PDO::FETCH_ASSOC);
$total_quota = (int)($quotaData['total_quota'] ?? 12);
$used_quota = (int)($quotaData['used_quota'] ?? 0);
$sisa_cuti = max(0, $total_quota - $used_quota);

$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');

$is_employee = ($role_id == 5 || $role_name_session === 'employee');

$type_param = $_GET['type'] ?? ''; 
$allowed_types = ['cuti', 'izin', 'sakit'];
if (!in_array(strtolower($type_param), $allowed_types)) {
    $type_param = 'cuti';
} else {
    $type_param = strtolower($type_param);
}

// ==============================================================================
// PENANGANAN FORM SUBMIT VIA AJAX
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'submit_leave') {
    header('Content-Type: application/json');
    
    $type = $_POST['type'] ?? 'cuti';
    
    $start_date = sprintf('%04d-%02d-%02d', $_POST['start_year'], $_POST['start_month'], $_POST['start_day']);
    $end_date = sprintf('%04d-%02d-%02d', $_POST['end_year'], $_POST['end_month'], $_POST['end_day']);
    
    $total_days = isset($_POST['total_days']) ? (int)$_POST['total_days'] : 0;
    $reason = trim($_POST['reason'] ?? '');
    
    $status = $is_employee ? 'pending' : 'approved';
    
    $approved_by = null;
    $approved_at = null;
    
    if ($status === 'approved') {
        $approved_by = $user_id;
        $approved_at = $current_time;
    }

    $today = date('Y-m-d');

    // Validasi Ringkas
    if (!checkdate((int)$_POST['start_month'], (int)$_POST['start_day'], (int)$_POST['start_year']) ||
        !checkdate((int)$_POST['end_month'], (int)$_POST['end_day'], (int)$_POST['end_year'])) {
        echo json_encode(['status' => 'error', 'message' => 'Format tanggal tidak valid!']);
        exit;
    } else if ($start_date < $today) {
        echo json_encode(['status' => 'error', 'message' => 'Tanggal tidak boleh masa lampau!']);
        exit;
    } else if ($total_days <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Total hari tidak valid!']);
        exit;
    } else if (empty($reason)) {
        echo json_encode(['status' => 'error', 'message' => 'Alasan wajib diisi!']);
        exit;
    } else {
        try {
            $attachment = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
                    throw new Exception("Ukuran file maksimal 10MB!");
                }

                $upload_dir = __DIR__ . '/assets/img/leave_requests/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
                
                if (!in_array($ext, $allowed_ext)) {
                    throw new Exception("File wajib PDF atau Gambar!");
                }

                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $attachment = 'leave_' . $user_id . '_' . time() . '.png';
                    $targetPath = $upload_dir . $attachment;
                    
                    switch ($ext) {
                        case 'jpg':
                        case 'jpeg':
                            $srcImage = @imagecreatefromjpeg($_FILES['attachment']['tmp_name']);
                            break;
                        case 'png':
                            $srcImage = @imagecreatefrompng($_FILES['attachment']['tmp_name']);
                            break;
                        case 'webp':
                            $srcImage = @imagecreatefromwebp($_FILES['attachment']['tmp_name']);
                            break;
                        default:
                            $srcImage = false;
                    }

                    if ($srcImage) {
                        $width = imagesx($srcImage);
                        $height = imagesy($srcImage);
                        
                        $maxDim = 1200;
                        if ($width > $maxDim || $height > $maxDim) {
                            $ratio = min($maxDim / $width, $maxDim / $height);
                            $newW = (int)($width * $ratio);
                            $newH = (int)($height * $ratio);
                            $resized = imagecreatetruecolor($newW, $newH);
                            imagealphablending($resized, false);
                            imagesavealpha($resized, true);
                            imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $newW, $newH, $width, $height);
                            imagedestroy($srcImage);
                            $srcImage = $resized;
                        }

                        imagepng($srcImage, $targetPath, 9);
                        imagedestroy($srcImage);

                        while (filesize($targetPath) > 200 * 1024) {
                            $srcImg2 = imagecreatefrompng($targetPath);
                            if (!$srcImg2) break;
                            $w2 = (int)(imagesx($srcImg2) * 0.8);
                            $h2 = (int)(imagesy($srcImg2) * 0.8);
                            if ($w2 < 100 || $h2 < 100) { imagedestroy($srcImg2); break; }
                            $resizedImg2 = imagecreatetruecolor($w2, $h2);
                            imagealphablending($resizedImg2, false);
                            imagesavealpha($resizedImg2, true);
                            imagecopyresampled($resizedImg2, $srcImg2, 0, 0, 0, 0, $w2, $h2, imagesx($srcImg2), imagesy($srcImg2));
                            imagepng($resizedImg2, $targetPath, 9);
                            imagedestroy($srcImg2);
                            imagedestroy($resizedImg2);
                        }
                    } else {
                        move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath);
                    }
                } else { 
                    $attachment = 'leave_' . $user_id . '_' . time() . '.pdf';
                    move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $attachment);
                }
            } else if ($type === 'sakit') {
                throw new Exception("Surat dokter wajib diunggah!");
            }

            // Insert Data dengan kolom waktu DATETIME eksplisit sesuai timezone tenant
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests (tenant_id, user_id, type, start_date, end_date, total_days, reason, attachment, status, approved_by, approved_at, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenant_id, $user_id, $type, $start_date, $end_date, $total_days, $reason, $attachment, $status, $approved_by, $approved_at, $current_time, $current_time]);

            if ($status === 'approved' && $type === 'cuti') {
                $year = date('Y', strtotime($start_date));
                // Update quota dan waktu updated_at pada tabel balances
                $pdo->prepare("UPDATE leave_balances SET used_quota = used_quota + ?, updated_at = ? WHERE user_id = ? AND year = ? AND tenant_id = ?")
                    ->execute([$total_days, $current_time, $user_id, $year, $tenant_id]);
            }

            $type_label = ucfirst($type);
            $msg = "Pengajuan {$type_label} berhasil dibuat.";
            
            $_SESSION['toast_msg'] = $msg;
            $_SESSION['toast_type'] = "success";
            
            echo json_encode(['status' => 'success']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan';

$curr_d = date('d');
$curr_m = date('m');
$curr_y = date('Y');

require_once __DIR__ . '/components/head.php';

echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css" />';
echo '<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"></script>';

require_once __DIR__ . '/components/sidebar.php';
?>

<style>
    .dropify-wrapper {
        border-radius: 0.75rem !important;
        border: 1px solid #e5e7eb !important;
        background-color: #f9fafb !important;
    }
    .dropify-wrapper:hover {
        background-image: linear-gradient(-45deg, #f9fafb 25%, transparent 25%, transparent 50%, #f9fafb 50%, #f9fafb 75%, transparent 75%, transparent);
    }
</style>

<!-- OVERLAY LOADING SAAT SUBMIT -->
<div id="loadingOverlay" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm hidden flex items-center justify-center" style="z-index: 999999;">
    <div class="bg-surface p-6 rounded-3xl shadow-2xl flex flex-col items-center gap-3">
        <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-primary"></i>
        <p class="text-xs font-bold text-gray-800">Memproses Pengajuan...</p>
    </div>
</div>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
    <main class="w-full min-h-screen pb-24 md:pb-8 md:px-6">
        
        <div class="hidden md:block">
            <?php require_once __DIR__ . '/components/header.php'; ?>
        </div>

        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full mx-auto">
            
            <div class="flex items-center gap-3 px-1 mb-6">
                <a href="<?= $base_url ?? '' ?>/leave" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Buat Pengajuan</h2>
                    <p class="text-[11px] text-gray-500">Isi formulir untuk pengajuan cuti, izin, atau sakit.</p>
                </div>
            </div>

            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm md:p-6 p-1">
                <form id="leaveForm" enctype="multipart/form-data">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                        <div class="space-y-5">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Informasi Tanggal</h3>
                            
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Jenis Pengajuan</label>
                                <div class="relative">
                                    <select name="type" id="typeSelect" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 appearance-none font-medium cursor-pointer">
                                        <option value="cuti" <?= $type_param == 'cuti' ? 'selected' : '' ?>>Cuti Tahunan</option>
                                        <option value="izin" <?= $type_param == 'izin' ? 'selected' : '' ?>>Izin Absen</option>
                                        <option value="sakit" <?= $type_param == 'sakit' ? 'selected' : '' ?>>Izin Sakit</option>
                                    </select>
                                    <i data-lucide="chevron-down" class="w-4 h-4 absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                </div>
                                
                                <!-- TAMPILAN SISA CUTI USER -->
                                <div id="quotaCard" class="mt-2.5 p-3 bg-primary/5 border border-primary/20 rounded-xl flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-gray-600 uppercase tracking-wider">Sisa Cuti Tahun Ini</span>
                                    <span class="text-xs font-black text-primary"><span id="userSisaCutiVal"><?= $sisa_cuti ?></span> Hari</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Tanggal Mulai</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <select name="start_day" id="start_day" onchange="syncEndDate()" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php for($i=1; $i<=31; $i++): $val = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            <option value="<?= $val ?>" <?= $curr_d == $val ? 'selected' : '' ?>><?= $val ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    
                                    <select name="start_month" id="start_month" onchange="syncEndDate()" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php 
                                        $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
                                        foreach($months as $num => $name): $val = str_pad($num, 2, '0', STR_PAD_LEFT); 
                                        ?>
                                            <option value="<?= $val ?>" <?= $curr_m == $val ? 'selected' : '' ?>><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <select name="start_year" id="start_year" onchange="syncEndDate()" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php for($i=date('Y'); $i<=date('Y')+2; $i++): ?>
                                            <option value="<?= $i ?>" <?= $curr_y == $i ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Tanggal Selesai</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <select name="end_day" id="end_day" onchange="calculateDays()" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php for($i=1; $i<=31; $i++): $val = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            <option value="<?= $val ?>" <?= $curr_d == $val ? 'selected' : '' ?>><?= $val ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    
                                    <select name="end_month" id="end_month" onchange="calculateDays()" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php foreach($months as $num => $name): $val = str_pad($num, 2, '0', STR_PAD_LEFT); ?>
                                            <option value="<?= $val ?>" <?= $curr_m == $val ? 'selected' : '' ?>><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <select name="end_year" id="end_year" onchange="calculateDays()" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php for($i=date('Y'); $i<=date('Y')+2; $i++): ?>
                                            <option value="<?= $i ?>" <?= $curr_y == $i ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Total Hari Kerja</label>
                                <input type="number" name="total_days" id="total_days" required readonly class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-primary font-bold cursor-not-allowed">
                                <p class="text-[9px] text-gray-400 mt-1.5">Sabtu dan Minggu tidak dihitung sebagai hari pengajuan.</p>
                            </div>
                        </div>

                        <div class="space-y-5 mt-6 md:mt-0">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Keterangan & Lampiran</h3>
                            
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Alasan / Detail Pengajuan</label>
                                <textarea name="reason" required rows="4" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Tuliskan keterangan lengkap pengajuan Anda..."></textarea>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                                    Lampiran Bukti <span id="lampiranStatus" class="text-gray-400 lowercase font-medium">(Opsional)</span>
                                </label>
                                <input type="file" name="attachment" id="attachment" class="dropify" data-max-file-size="10M" data-allowed-file-extensions="pdf jpg jpeg png webp" />
                                <p class="text-[9px] text-gray-400 mt-1.5">Format didukung: PDF & Gambar (Maks 10MB).</p>
                            </div>
                        </div>
                    </div>

                    <!-- TOMBOL TETAP STATIC -->
                    <div class="mt-8 pt-6 mb-12 md:mb-2 border-t border-gray-100 flex gap-3">
                        <a href="<?= $base_url ?? '' ?>/leave" id="btnCancel" class="w-1/3 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold text-center hover:bg-gray-50 transition flex items-center justify-center">
                            Batal
                        </a>
                        <button type="submit" id="btnSubmitForm" class="flex-1 bg-primary text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i> <span>Ajukan Cuti</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<!-- ================= MODAL KONFIRMASI KUOTA CUTI MELEBIHI ================= -->
<div id="confirmQuotaModal" class="fixed inset-0 hidden" style="z-index: 999999;">
    <div id="quotaOverlay" onclick="closeQuotaModal()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="quotaCardModal" class="bg-surface w-full md:max-w-md rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col p-6">
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-pending/10 text-pending mx-auto flex items-center justify-center mb-4">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Cuti Melebihi Kuota</h3>
                <p class="text-xs text-gray-500 mt-2 leading-relaxed">Total hari cuti yang diajukan (<span id="modalReqDays" class="font-bold text-primary">0</span> Hari) melebihi sisa cuti Anda (<span id="modalUserQuota" class="font-bold text-failed">0</span> Hari). Tetap lanjutkan pengajuan?</p>
                <div class="flex gap-3 mt-8">
                    <button onclick="closeQuotaModal()" class="flex-1 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Batal</button>
                    <button onclick="confirmSubmitLeave()" class="flex-1 py-3 bg-pending hover:bg-pending/90 text-white rounded-xl text-sm font-bold transition shadow-sm">Tetap Lanjutkan</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    const userSisaCuti = <?= $sisa_cuti ?>;

    $(document).ready(function(){
        $('.dropify').dropify({
            messages: {
                'default': '',
                'replace': '',
                'remove':  'Hapus',
                'error':   'File tidak valid.'
            }
        });
    });

    // SINKRONISASI OTOMATIS: SAAT TANGGAL MULAI BERUBAH, TANGGAL SELESAI IKUT BERUBAH
    function syncEndDate() {
        document.getElementById('end_day').value = document.getElementById('start_day').value;
        document.getElementById('end_month').value = document.getElementById('start_month').value;
        document.getElementById('end_year').value = document.getElementById('start_year').value;
        calculateDays();
    }

    function calculateDays() {
        const sd = parseInt(document.getElementById('start_day').value, 10);
        const sm = parseInt(document.getElementById('start_month').value, 10) - 1;
        const sy = parseInt(document.getElementById('start_year').value, 10);
        
        const ed = parseInt(document.getElementById('end_day').value, 10);
        const em = parseInt(document.getElementById('end_month').value, 10) - 1;
        const ey = parseInt(document.getElementById('end_year').value, 10);
        
        const totalInput = document.getElementById('total_days');
        
        if (!isNaN(sd) && !isNaN(sm) && !isNaN(sy) && !isNaN(ed) && !isNaN(em) && !isNaN(ey)) {
            const startDate = new Date(sy, sm, sd, 0, 0, 0, 0);
            const endDate = new Date(ey, em, ed, 0, 0, 0, 0);
            const today = new Date();
            today.setHours(0,0,0,0);
            
            if (startDate < today) {
                if(typeof window.showToast === 'function') window.showToast("Tanggal tidak boleh masa lampau!", "warning");
                totalInput.value = '';
                return;
            }
            
            if (endDate < startDate) {
                if(typeof window.showToast === 'function') window.showToast("Tanggal selesai tidak boleh kurang dari tanggal mulai!", "warning");
                totalInput.value = '';
                return;
            }
            
            let countDays = 0;
            let currentDate = new Date(startDate);
            
            while (currentDate <= endDate) {
                const dayOfWeek = currentDate.getDay();
                if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                    countDays++;
                }
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            if (countDays === 0) {
                if(typeof window.showToast === 'function') window.showToast("Rentang tanggal hanya hari libur!", "failed");
                totalInput.value = '';
                return;
            }

            totalInput.value = countDays;
        } else {
            totalInput.value = '';
        }
    }
    
    calculateDays();

    // DINAMISASI HANYA PADA KARTU QUOTA DAN STATUS LAMPIRAN (TEKS TOMBOL STATIC)
    document.getElementById('typeSelect').addEventListener('change', function() {
        const type = this.value; 
        const quotaCard = document.getElementById('quotaCard');
        
        if (type === 'cuti') {
            quotaCard.classList.remove('hidden');
        } else {
            quotaCard.classList.add('hidden');
        }

        const lampiranStatus = document.getElementById('lampiranStatus');
        if (type === 'sakit') {
            lampiranStatus.innerHTML = '<span class="text-failed">(Wajib Surat Dokter)</span>';
        } else {
            lampiranStatus.innerHTML = '(Opsional)';
        }
    });

    document.getElementById('typeSelect').dispatchEvent(new Event('change'));

    // MODAL KONFIRMASI OVER QUOTA
    function openQuotaModal(reqDays) {
        document.getElementById('modalReqDays').innerText = reqDays;
        document.getElementById('modalUserQuota').innerText = userSisaCuti;
        
        const qm = document.getElementById('confirmQuotaModal');
        qm.classList.remove('hidden');
        lucide.createIcons();
        setTimeout(() => {
            document.getElementById('quotaOverlay').classList.remove('opacity-0');
            document.getElementById('quotaCardModal').classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
            document.getElementById('quotaCardModal').classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100');
        }, 10);
    }

    function closeQuotaModal() {
        document.getElementById('quotaOverlay').classList.add('opacity-0');
        document.getElementById('quotaCardModal').classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100');
        document.getElementById('quotaCardModal').classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0');
        setTimeout(() => { document.getElementById('confirmQuotaModal').classList.add('hidden'); }, 300);
    }

    // FORM SUBMIT HANDLING
    $('#leaveForm').on('submit', function(e) {
        e.preventDefault();

        const type = $('#typeSelect').val();
        const totalDays = parseInt($('#total_days').val()) || 0;
        const filesCount = $('#attachment')[0].files.length;
        
        if (type === 'sakit' && filesCount === 0) {
            if(typeof window.showToast === 'function') window.showToast("Surat dokter wajib diunggah!", "failed");
            return false;
        }

        // Cek jika cuti melebihi sisa cuti -> Tampilkan modal konfirmasi
        if (type === 'cuti' && totalDays > userSisaCuti) {
            openQuotaModal(totalDays);
            return false;
        }

        executeSubmitLeave();
    });

    function confirmSubmitLeave() {
        closeQuotaModal();
        setTimeout(() => { executeSubmitLeave(); }, 300);
    }

    function executeSubmitLeave() {
        // Tampilkan Overlay Loading
        document.getElementById('loadingOverlay').classList.remove('hidden');

        const formData = new FormData(document.getElementById('leaveForm'));
        formData.append('ajax_action', 'submit_leave');

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                window.location.href = '<?= $base_url ?? '' ?>/leave';
            } else {
                document.getElementById('loadingOverlay').classList.add('hidden');
                if(typeof window.showToast === 'function') window.showToast(data.message, "error");
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').classList.add('hidden');
            if(typeof window.showToast === 'function') window.showToast("Gagal terhubung ke server.", "error");
        });
    }
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>