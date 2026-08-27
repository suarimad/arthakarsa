<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');

// Logika Status & Tombol (Karyawan vs Atasan)
$is_employee = ($role_id == 5 || $role_name_session === 'employee');

$toast_msg = '';
$toast_type = '';

// Menangkap parameter type dari URL (Misal: ?type=sakit atau hasil rewrite URL leave_add/sakit)
$type_param = $_GET['type'] ?? ''; 
$allowed_types = ['cuti', 'izin', 'sakit'];
if (!in_array(strtolower($type_param), $allowed_types)) {
    $type_param = 'cuti'; // Default jika parameter kosong/salah
} else {
    $type_param = strtolower($type_param);
}

// ==============================================================================
// PENANGANAN FORM SUBMIT
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'cuti';
    
    // Menggabungkan Input Tanggal Terpisah menjadi Format YYYY-MM-DD
    $start_date = sprintf('%04d-%02d-%02d', $_POST['start_year'], $_POST['start_month'], $_POST['start_day']);
    $end_date = sprintf('%04d-%02d-%02d', $_POST['end_year'], $_POST['end_month'], $_POST['end_day']);
    
    $total_days = $_POST['total_days'] ?? 1;
    $reason = trim($_POST['reason'] ?? '');
    
    // Status ditentukan oleh Role
    $status = $is_employee ? 'pending' : 'approved';
    
    $approved_by = null;
    $approved_at = null;
    
    if ($status === 'approved') {
        $approved_by = $user_id; // Disetujui otomatis oleh diri sendiri (Admin/HR/Manager)
        $approved_at = date('Y-m-d H:i:s');
    }

    $today = date('Y-m-d');

    // Validasi Tanggal (Tidak Boleh Lampau & Tanggal Harus Valid)
    if (!checkdate((int)$_POST['start_month'], (int)$_POST['start_day'], (int)$_POST['start_year']) ||
        !checkdate((int)$_POST['end_month'], (int)$_POST['end_day'], (int)$_POST['end_year'])) {
        $toast_msg = "Format tanggal yang Anda masukkan tidak valid!";
        $toast_type = "warning";
    } else if ($start_date < $today) {
        $toast_msg = "Tanggal pengajuan tidak boleh menggunakan waktu di masa lampau!";
        $toast_type = "warning";
    } else if (empty($reason)) {
        $toast_msg = "Alasan wajib diisi!";
        $toast_type = "warning";
    } else {
        try {
            // Upload Lampiran via Dropify (Wajib untuk sakit, opsional untuk cuti/izin)
            $attachment = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                // Path Baru sesuai instruksi
                $upload_dir = __DIR__ . '/assets/img/leave_requests/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
                
                if (in_array($ext, $allowed_ext)) {
                    $attachment = 'leave_' . $user_id . '_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $attachment);
                } else {
                    throw new Exception("Format file lampiran tidak valid. Gunakan ekstensi PDF atau Gambar.");
                }
            } else if ($type === 'sakit') {
                throw new Exception("Surat Dokter / Lampiran wajib diunggah untuk pengajuan sakit.");
            }

            // Insert Data
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests (tenant_id, user_id, type, start_date, end_date, total_days, reason, attachment, status, approved_by, approved_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenant_id, $user_id, $type, $start_date, $end_date, $total_days, $reason, $attachment, $status, $approved_by, $approved_at]);

            // Jika role Atasan mengajukan "Cuti" (otomatis approved), langsung potong kuota
            if ($status === 'approved' && $type === 'cuti') {
                $year = date('Y', strtotime($start_date));
                $pdo->prepare("UPDATE leave_balances SET used_quota = used_quota + ? WHERE user_id = ? AND year = ?")
                    ->execute([$total_days, $user_id, $year]);
            }

            // Kirim Toast Session ke leave.php
            $_SESSION['toast_msg'] = "Pengajuan berhasil " . ($status === 'approved' ? "dibuat dan disetujui." : "diajukan. Menunggu persetujuan atasan.");
            $_SESSION['toast_type'] = "success";
            
            // Redirect menggunakan base URL
            $redirect_url = rtrim($base_url ?? '', '/') . '/leave';
            header("Location: " . $redirect_url);
            exit;

        } catch (Exception $e) {
            $toast_msg = "Gagal memproses: " . $e->getMessage();
            $toast_type = "error";
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

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
    <main class="w-full min-h-screen pb-24 md:pb-8 md:px-6">
        
        <div class="hidden md:block">
            <?php require_once __DIR__ . '/components/header.php'; ?>
        </div>

        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full mx-auto">
            
            <div class="flex items-center gap-3 px-1 mb-6">
                <!-- Back Button diarahkan ke halaman leave -->
                <a href="<?= $base_url ?? '' ?>/leave" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Buat Pengajuan</h2>
                    <p class="text-[11px] text-gray-500">Isi formulir untuk pengajuan cuti, izin, atau sakit.</p>
                </div>
            </div>

            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm md:p-6 p-1">
                <form id="leaveForm" method="POST" action="" enctype="multipart/form-data">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                        <!-- KOLOM KIRI: Detail Tanggal -->
                        <div class="space-y-5">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Informasi Tanggal</h3>
                            
                            <!-- JENIS PENGAJUAN -->
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
                            </div>

                            <!-- TANGGAL MULAI (Tiga Input Terpisah) -->
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Tanggal Mulai</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <select name="start_day" id="start_day" onchange="calculateDays()" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php for($i=1; $i<=31; $i++): $val = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            <option value="<?= $val ?>" <?= $curr_d == $val ? 'selected' : '' ?>><?= $val ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    
                                    <select name="start_month" id="start_month" onchange="calculateDays()" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php 
                                        $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
                                        foreach($months as $num => $name): $val = str_pad($num, 2, '0', STR_PAD_LEFT); 
                                        ?>
                                            <option value="<?= $val ?>" <?= $curr_m == $val ? 'selected' : '' ?>><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <select name="start_year" id="start_year" onchange="calculateDays()" class="w-full px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-800 font-medium cursor-pointer">
                                        <?php for($i=date('Y'); $i<=date('Y')+2; $i++): ?>
                                            <option value="<?= $i ?>" <?= $curr_y == $i ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- TANGGAL SELESAI (Tiga Input Terpisah) -->
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

                            <!-- TOTAL HARI -->
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Total Hari Kerja</label>
                                <input type="number" name="total_days" id="total_days" required readonly class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-primary font-bold cursor-not-allowed">
                                <p class="text-[9px] text-gray-400 mt-1.5">Jumlah hari akan dikalkulasi otomatis.</p>
                            </div>
                        </div>

                        <!-- KOLOM KANAN: Keterangan & Lampiran -->
                        <div class="space-y-5 mt-6 md:mt-0">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Keterangan & Lampiran</h3>
                            
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Alasan / Detail Pengajuan</label>
                                <textarea name="reason" required rows="4" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Tuliskan keterangan lengkap pengajuan Anda..."></textarea>
                            </div>

                            <!-- INPUT LAMPIRAN DROPIFY -->
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                                    Lampiran Bukti <span id="lampiranStatus" class="text-gray-400 lowercase font-medium">(Opsional)</span>
                                </label>
                                <input type="file" name="attachment" id="attachment" class="dropify" data-max-file-size="3M" data-allowed-file-extensions="pdf jpg jpeg png webp gif" />
                                <p class="text-[9px] text-gray-400 mt-1.5">Format didukung: PDF, JPG, PNG, dll. Maks 3MB.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 mb-12 md:mb-2 border-t border-gray-100 flex gap-3">
                        <a href="<?= $base_url ?? '' ?>/leave" class="w-1/3 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold text-center hover:bg-gray-50 transition">
                            Batal
                        </a>
                        <button type="submit" class="flex-1 bg-primary text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i> <span id="btnSubmitText">Simpan</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<!-- Komponen Toast Global (Untuk Error Validasi Form) -->
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    // Trigger local validation error bila ada
    const phpMsg = <?= json_encode($toast_msg) ?>;
    const phpType = <?= json_encode($toast_type) ?>;
    if (phpMsg && typeof window.showToast === 'function') {
        window.showToast(phpMsg, phpType);
    }

    // ==========================================
    // INISIALISASI DROPIFY
    // ==========================================
    $(document).ready(function(){
        $('.dropify').dropify({
            messages: {
                'default': '',
                'replace': '',
                'remove':  'Hapus',
                'error':   'Ooops, terjadi kesalahan.'
            }
        });
    });

    // ==========================================
    // LOGIKA KALKULASI HARI TERPISAH
    // ==========================================
    function calculateDays() {
        const sd = document.getElementById('start_day').value;
        const sm = document.getElementById('start_month').value;
        const sy = document.getElementById('start_year').value;
        
        const ed = document.getElementById('end_day').value;
        const em = document.getElementById('end_month').value;
        const ey = document.getElementById('end_year').value;
        
        const totalInput = document.getElementById('total_days');
        
        if (sd && sm && sy && ed && em && ey) {
            const startDate = new Date(`${sy}-${sm}-${sd}`);
            const endDate = new Date(`${ey}-${em}-${ed}`);
            
            // Set time to midnight (menghindari offset zona waktu)
            startDate.setHours(0,0,0,0);
            endDate.setHours(0,0,0,0);
            
            const today = new Date();
            today.setHours(0,0,0,0);
            
            // Validasi masa lampau
            if (startDate < today) {
                if(typeof window.showToast === 'function') window.showToast("Tanggal pengajuan tidak boleh menggunakan waktu di masa lampau!", "warning");
                totalInput.value = '';
                return;
            }
            
            // Validasi tanggal terbalik
            if (endDate < startDate) {
                if(typeof window.showToast === 'function') window.showToast("Tanggal selesai tidak boleh kurang dari tanggal mulai!", "warning");
                totalInput.value = '';
                return;
            }
            
            // Hitung selisih (+1 karena inclusive hari pertama)
            const diffTime = Math.abs(endDate - startDate);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            totalInput.value = diffDays;
        } else {
            totalInput.value = '';
        }
    }
    
    // Panggil fungsi saat pertama kali halaman dimuat
    calculateDays();

    // ==========================================
    // LOGIKA DINAMISASI TOMBOL & LAMPIRAN
    // ==========================================
    document.getElementById('typeSelect').addEventListener('change', function() {
        const type = this.value; 
        
        // 1. Ubah Wording Cuti/Izin/Sakit
        let typeName = 'Cuti';
        if(type === 'izin') typeName = 'Izin';
        if(type === 'sakit') typeName = 'Sakit';
        
        // 2. Ubah Kata Depan berdasarkan Role
        const isEmp = <?= $is_employee ? 'true' : 'false' ?>;
        const prefix = isEmp ? 'Ajukan ' : 'Buat ';
        
        document.getElementById('btnSubmitText').innerText = prefix + typeName;

        // 3. Wajibkan Lampiran jika Sakit
        const lampiranStatus = document.getElementById('lampiranStatus');
        
        if (type === 'sakit') {
            lampiranStatus.innerHTML = '<span class="text-failed">(Wajib Surat Dokter)</span>';
        } else {
            lampiranStatus.innerHTML = '(Opsional)';
        }
    });
    
    // Cegah submit jika sakit tapi lampiran kosong
    $('#leaveForm').on('submit', function(e) {
        const type = $('#typeSelect').val();
        const filesCount = $('#attachment')[0].files.length;
        
        if (type === 'sakit' && filesCount === 0) {
            e.preventDefault();
            if(typeof window.showToast === 'function') {
                window.showToast("Surat Dokter / Lampiran wajib diunggah untuk pengajuan sakit.", "failed");
            }
        }
    });

    document.getElementById('typeSelect').dispatchEvent(new Event('change'));

</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>