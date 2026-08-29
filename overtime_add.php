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

$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');

// Logika Status & Tombol (Karyawan vs Atasan)
$is_employee = ($role_id == 5 || $role_name_session === 'employee');

// ==============================================================================
// PENANGANAN FORM SUBMIT VIA AJAX
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'submit_overtime') {
    header('Content-Type: application/json');
    
    $ot_date = sprintf('%04d-%02d-%02d', $_POST['ot_year'], $_POST['ot_month'], $_POST['ot_day']);
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    $status = $is_employee ? 'pending' : 'approved';
    $approved_by = null;
    $approved_at = null;
    
    if ($status === 'approved') {
        $approved_by = $user_id;
        $approved_at = date('Y-m-d H:i:s');
    }

    $today = date('Y-m-d');

    // Validasi Tanggal & Form
    if (!checkdate((int)$_POST['ot_month'], (int)$_POST['ot_day'], (int)$_POST['ot_year'])) {
        echo json_encode(['status' => 'error', 'message' => 'Format tanggal yang Anda masukkan tidak valid!']);
        exit;
    } else if ($ot_date < $today) {
        echo json_encode(['status' => 'error', 'message' => 'Tanggal pengajuan tidak boleh menggunakan waktu di masa lampau!']);
        exit;
    } else if (empty($start_time) || empty($end_time)) {
        echo json_encode(['status' => 'error', 'message' => 'Jam mulai dan jam selesai wajib diisi!']);
        exit;
    } else if ($duration_minutes <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Durasi lembur tidak valid!']);
        exit;
    } else if (empty($reason)) {
        echo json_encode(['status' => 'error', 'message' => 'Tugas / Pekerjaan wajib diisi!']);
        exit;
    } else {
        try {
            $attachment = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                // Cek ukuran maks 10MB
                if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
                    throw new Exception("Ukuran file maksimal 10MB!");
                }

                $upload_dir = __DIR__ . '/assets/img/overtime_requests/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
                
                if (!in_array($ext, $allowed_ext)) {
                    throw new Exception("File wajib PDF atau Gambar!");
                }

                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    // Kompres & Ubah Format ke .PNG (Maks 200KB)
                    $attachment = 'ot_' . $user_id . '_' . time() . '.png';
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

                        // Simpan awal sebagai PNG dengan kompresi maksimal
                        imagepng($srcImage, $targetPath, 9);
                        imagedestroy($srcImage);

                        // Iterasi kompresi ulang dimensi jika file size > 200KB
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
                } else { // File PDF
                    $attachment = 'ot_' . $user_id . '_' . time() . '.pdf';
                    move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $attachment);
                }
            }

            // Insert Data ke overtime_requests
            $stmt = $pdo->prepare("
                INSERT INTO overtime_requests (tenant_id, user_id, date, start_time, end_time, duration_minutes, reason, attachment, status, approved_by, approved_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenant_id, $user_id, $ot_date, $start_time, $end_time, $duration_minutes, $reason, $attachment, $status, $approved_by, $approved_at]);

            $msg = "Berhasil mengajukan lembur" . ($status === 'approved' ? " (Otomatis disetujui)." : ".");
            
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
                <a href="<?= $base_url ?? '' ?>/overtime" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Pengajuan Lembur</h2>
                    <p class="text-[11px] text-gray-500">Isi formulir untuk mencatat jam tambahan kerja Anda.</p>
                </div>
            </div>

            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm md:p-6 p-1">
                <form id="overtimeForm" enctype="multipart/form-data">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                        <div class="space-y-5">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Informasi Waktu</h3>
                            
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Tanggal Pelaksanaan</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <select name="ot_day" id="ot_day" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php for($i=1; $i<=31; $i++): $val = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            <option value="<?= $val ?>" <?= $curr_d == $val ? 'selected' : '' ?>><?= $val ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    
                                    <select name="ot_month" id="ot_month" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php 
                                        $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
                                        foreach($months as $num => $name): $val = str_pad($num, 2, '0', STR_PAD_LEFT); 
                                        ?>
                                            <option value="<?= $val ?>" <?= $curr_m == $val ? 'selected' : '' ?>><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <select name="ot_year" id="ot_year" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php for($i=date('Y'); $i<=date('Y')+2; $i++): ?>
                                            <option value="<?= $i ?>" <?= $curr_y == $i ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Jam Mulai</label>
                                    <input type="time" name="start_time" id="start_time" required onchange="calculateDuration()" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 font-medium">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Jam Selesai</label>
                                    <input type="time" name="end_time" id="end_time" required onchange="calculateDuration()" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 font-medium">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Total Durasi Lembur</label>
                                <input type="hidden" name="duration_minutes" id="duration_minutes" value="0">
                                <input type="text" id="duration_display" placeholder="0 Jam 0 Menit" required readonly class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-primary font-bold cursor-not-allowed">
                                <p class="text-[9px] text-gray-400 mt-1.5">Durasi dihitung otomatis berdasarkan input jam Anda.</p>
                            </div>
                        </div>

                        <div class="space-y-5 mt-6 md:mt-0">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Tugas & Keterangan</h3>
                            
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Pekerjaan / Alasan Lembur</label>
                                <textarea name="reason" required rows="4" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Jelaskan secara singkat pekerjaan apa yang Anda lakukan selama lembur..."></textarea>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                                    Bukti Lampiran / Foto <span class="text-gray-400 lowercase font-medium">(Opsional)</span>
                                </label>
                                <input type="file" name="attachment" id="attachment" class="dropify" data-max-file-size="10M" data-allowed-file-extensions="pdf jpg jpeg png webp" />
                                <p class="text-[9px] text-gray-400 mt-1.5">Format didukung: PDF & Gambar (Maks 10MB).</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 mb-12 md:mb-2 border-t border-gray-100 flex gap-3">
                        <a href="<?= $base_url ?? '' ?>/overtime" class="w-1/3 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold text-center hover:bg-gray-50 transition flex items-center justify-center">
                            Batal
                        </a>
                        <button type="submit" class="flex-1 bg-primary text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i> <span>Simpan</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

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

    function calculateDuration() {
        const st = document.getElementById('start_time').value;
        const et = document.getElementById('end_time').value;
        
        const minValInput = document.getElementById('duration_minutes');
        const displayInput = document.getElementById('duration_display');
        
        if (st && et) {
            let startTime = new Date(`1970-01-01T${st}:00`);
            let endTime = new Date(`1970-01-01T${et}:00`);
            
            if (endTime < startTime) {
                endTime.setDate(endTime.getDate() + 1);
            }
            
            let diffMinutes = (endTime - startTime) / 1000 / 60;
            minValInput.value = diffMinutes;
            
            let hours = Math.floor(diffMinutes / 60);
            let mins = diffMinutes % 60;
            
            let displayStr = "";
            if (hours > 0) displayStr += hours + " Jam ";
            if (mins > 0) displayStr += mins + " Menit";
            
            if (diffMinutes === 0) displayStr = "0 Menit";
            
            displayInput.value = displayStr.trim();
        } else {
            minValInput.value = "0";
            displayInput.value = "";
        }
    }
    
    $('#overtimeForm').on('submit', function(e) {
        e.preventDefault();
        
        const duration = parseInt($('#duration_minutes').val());
        if (duration <= 0) {
            if(typeof window.showToast === 'function') {
                window.showToast("Durasi lembur tidak valid atau kosong!", "failed");
            }
            $('#duration_display').addClass('border-failed ring-1 ring-failed');
            setTimeout(() => { $('#duration_display').removeClass('border-failed ring-1 ring-failed'); }, 2000);
            return false;
        }

        document.getElementById('loadingOverlay').classList.remove('hidden');

        const formData = new FormData(this);
        formData.append('ajax_action', 'submit_overtime');

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                window.location.href = '<?= $base_url ?? '' ?>/overtime';
            } else {
                document.getElementById('loadingOverlay').classList.add('hidden');
                if(typeof window.showToast === 'function') window.showToast(data.message, "error");
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').classList.add('hidden');
            if(typeof window.showToast === 'function') window.showToast("Gagal terhubung ke server.", "error");
        });
    });

</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>