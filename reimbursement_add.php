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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'submit_reimbursement') {
    header('Content-Type: application/json'); // Wajib return JSON untuk AJAX
    
    // Menggabungkan Input Tanggal Terpisah menjadi Format YYYY-MM-DD
    $rb_date = sprintf('%04d-%02d-%02d', $_POST['rb_year'], $_POST['rb_month'], $_POST['rb_day']);
    
    $type = $_POST['type'] ?? '';
    // Mengambil nominal, pastikan format dari UI bersih dari separator ribuan
    $amount = (float)($_POST['amount'] ?? 0); 
    $description = trim($_POST['description'] ?? '');
    
    // Status ditentukan oleh Role
    $status = $is_employee ? 'pending' : 'approved';
    
    $approved_by = null;
    $approved_at = null;
    
    if ($status === 'approved') {
        $approved_by = $user_id; // Disetujui otomatis oleh diri sendiri (Admin/HR/Manager/Finance)
        $approved_at = date('Y-m-d H:i:s');
    }

    $today = date('Y-m-d');

    // Validasi Tanggal & Form
    if (!checkdate((int)$_POST['rb_month'], (int)$_POST['rb_day'], (int)$_POST['rb_year'])) {
        echo json_encode(['status' => 'error', 'message' => 'Format tanggal yang Anda masukkan tidak valid!']);
        exit;
    } else if ($rb_date > $today) {
        echo json_encode(['status' => 'error', 'message' => 'Tanggal nota/struk tidak boleh menggunakan tanggal di masa depan!']);
        exit;
    } else if (empty($type)) {
        echo json_encode(['status' => 'error', 'message' => 'Pilih kategori jenis pengeluaran!']);
        exit;
    } else if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Nominal reimbursement tidak valid!']);
        exit;
    } else if (empty($description)) {
        echo json_encode(['status' => 'error', 'message' => 'Keterangan pengeluaran wajib diisi!']);
        exit;
    } else if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Lampiran struk atau nota wajib diunggah!']);
        exit;
    } else {
        try {
            $attachment = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                // Cek ukuran maks 10MB
                if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
                    throw new Exception("Ukuran file lampiran maksimal 10MB!");
                }

                $upload_dir = __DIR__ . '/assets/img/reimbursement_requests/';
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
                    $attachment = 'rb_' . $user_id . '_' . time() . '.png';
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
                    $attachment = 'rb_' . $user_id . '_' . time() . '.pdf';
                    move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $attachment);
                }
            } else {
                throw new Exception("Lampiran struk atau nota wajib diunggah!");
            }

            // Insert Data ke reimbursement_requests
            $stmt = $pdo->prepare("
                INSERT INTO reimbursement_requests (tenant_id, user_id, date, type, amount, description, attachment, status, approved_by, approved_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenant_id, $user_id, $rb_date, $type, $amount, $description, $attachment, $status, $approved_by, $approved_at]);

            $msg = "Klaim berhasil dibuat.";
            
            // Simpan pesan ke session untuk dikirim ke halaman reimbursement.php
            $_SESSION['toast_msg'] = $msg;
            $_SESSION['toast_type'] = "success";
            
            // Response sukses ke AJAX
            echo json_encode(['status' => 'success']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memproses: ' . $e->getMessage()]);
            exit;
        }
    }
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan';

// Helper Tanggal Hari Ini untuk Form Default
$curr_d = date('d');
$curr_m = date('m');
$curr_y = date('Y');

// Prefix Teks Tombol Berdasarkan Role
$btn_submit_text = $is_employee ? "Ajukan Klaim" : "Buat Klaim";

require_once __DIR__ . '/components/head.php';

// Memuat jQuery & Dropify
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css" />';
echo '<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"></script>';

require_once __DIR__ . '/components/sidebar.php';
?>

<!-- STYLE KHUSUS DROPIFY OVERRIDE UNTUK TAILWIND -->
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

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
    <main class="w-full min-h-screen pb-24 md:pb-8 md:px-6">
        
        <div class="hidden md:block">
            <?php require_once __DIR__ . '/components/header.php'; ?>
        </div>

        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full mx-auto ">
            
            <div class="flex items-center gap-3 px-1 mb-6">
                <!-- Back Button diarahkan ke halaman reimbursement -->
                <a href="<?= $base_url ?? '' ?>/reimbursement" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Buat Klaim Reimbursement</h2>
                    <p class="text-[11px] text-gray-500">Isi formulir untuk mengajukan penggantian dana pengeluaran Anda.</p>
                </div>
            </div>

            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm md:p-6 p-1">
                <form id="reimburseForm" enctype="multipart/form-data">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                        <!-- KOLOM KIRI: Detail Data -->
                        <div class="space-y-5">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Informasi Transaksi</h3>
                            
                            <!-- JENIS PENGELUARAN -->
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Kategori Pengeluaran</label>
                                <div class="relative">
                                    <select name="type" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 appearance-none font-medium cursor-pointer">
                                        <option value="" disabled selected>Pilih Kategori...</option>
                                        <option value="Transportasi">Transportasi</option>
                                        <option value="Konsumsi">Konsumsi</option>
                                        <option value="Perjalanan Dinas">Perjalanan Dinas</option>
                                        <option value="Medis">Medis / Kesehatan</option>
                                        <option value="ATK">Alat Tulis Kantor (ATK)</option>
                                        <option value="Lainnya">Lainnya</option>
                                    </select>
                                    <i data-lucide="chevron-down" class="w-4 h-4 absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                </div>
                            </div>

                            <!-- NOMINAL UANG -->
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nominal Diklaim (Rp)</label>
                                <input type="number" name="amount" min="1" required placeholder="Contoh: 150000" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 font-bold">
                                <p class="text-[9px] text-gray-400 mt-1.5">Tulis nominal dengan angka tanpa titik atau koma.</p>
                            </div>

                            <!-- TANGGAL NOTA/STRUK -->
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Tanggal Pada Struk/Nota</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <select name="rb_day" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php for($i=1; $i<=31; $i++): $val = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            <option value="<?= $val ?>" <?= $curr_d == $val ? 'selected' : '' ?>><?= $val ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    
                                    <select name="rb_month" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php 
                                        $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
                                        foreach($months as $num => $name): $val = str_pad($num, 2, '0', STR_PAD_LEFT); 
                                        ?>
                                            <option value="<?= $val ?>" <?= $curr_m == $val ? 'selected' : '' ?>><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <select name="rb_year" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php for($i=date('Y')-1; $i<=date('Y'); $i++): ?>
                                            <option value="<?= $i ?>" <?= $curr_y == $i ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- KOLOM KANAN: Keterangan & Lampiran -->
                        <div class="space-y-5 mt-6 md:mt-0">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Keterangan & Lampiran</h3>
                            
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Keterangan / Alasan</label>
                                <textarea name="description" required rows="4" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Jelaskan secara singkat untuk apa pengeluaran ini..."></textarea>
                            </div>

                            <!-- INPUT LAMPIRAN DROPIFY -->
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                                    Bukti Lampiran / Foto <span class="text-failed lowercase font-medium">(Wajib)</span>
                                </label>
                                <input type="file" name="attachment" required class="dropify" data-max-file-size="10M" data-allowed-file-extensions="pdf jpg jpeg png webp" />
                                <p class="text-[9px] text-gray-400 mt-1.5">Format didukung: PDF & Gambar (Maks 10MB).</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 mb-12 md:mb-2 border-t border-gray-100 flex gap-3">
                        <a href="<?= $base_url ?? '' ?>/reimbursement" id="btnCancel" class="w-1/3 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold text-center hover:bg-gray-50 transition flex items-center justify-center">
                            Batal
                        </a>
                        <button type="submit" id="btnSubmitForm" class="flex-1 bg-primary text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i> <span id="btnSubmitText"><?= $btn_submit_text ?></span>
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

    // ==========================================
    // INISIALISASI DROPIFY
    // ==========================================
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

    // ==========================================
    // AJAX FORM SUBMIT + DELAY REDIRECT
    // ==========================================
    $('#reimburseForm').on('submit', function(e) {
        e.preventDefault();
        
        document.getElementById('loadingOverlay').classList.remove('hidden');

        const formData = new FormData(this);
        formData.append('ajax_action', 'submit_reimbursement');

        // Menggunakan target window.location.href agar aman dari intercept Service Worker PWA
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                window.location.href = '<?= $base_url ?? '' ?>/reimbursement';
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