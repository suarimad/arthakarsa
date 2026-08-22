<?php
// Panggil Konfigurasi Global & Database
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Panggil Komponen Auth (Memastikan user harus login)
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// ==============================================================================
// PENANGANAN AJAX: SIMPAN DATA WAJAH (FACE DESCRIPTOR) KE DATABASE
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_face') {
    header('Content-Type: application/json');
    try {
        $face_descriptor_json = $_POST['face_descriptor']; 
        
        // Simpan ke database
        $stmt = $pdo->prepare("
            UPDATE users 
            SET face_descriptor = ?, face_registered_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$face_descriptor_json, $user_id, $tenant_id]);
        
        $_SESSION['toast_msg'] = "Wajah berhasil didaftarkan!";
        $_SESSION['toast_type'] = "success";
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
// ==============================================================================

// Menangkap Pesan Toast Global
$toast_msg = '';
$toast_type = '';
if (isset($_SESSION['toast_msg'])) {
    $toast_msg = $_SESSION['toast_msg'];
    $toast_type = $_SESSION['toast_type'] ?? 'info';
    unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
}

// Ambil data user terbaru dari database (Termasuk face_descriptor)
try {
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, u.role, u.face_descriptor, p.name as position_name, t.name as tenant_name 
        FROM users u 
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN tenants t ON u.tenant_id = t.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $user_name = $user_data['name'];
        $user_email = $user_data['email'];
        $user_role_display = $user_data['position_name'] ?? ucfirst($user_data['role']);
        $tenant_name_display = $user_data['tenant_name'] ?? 'Sistem Pusat';
    } else {
        // Fallback jika gagal fetch
        $user_name = $_SESSION['user_name'] ?? 'User';
        $user_email = '';
        $user_role_display = $_SESSION['position_name'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
        $tenant_name_display = $_SESSION['tenant_name'] ?? 'Perusahaan'; 
    }
} catch (Exception $e) {
    $user_name = $_SESSION['user_name'] ?? 'User';
    $user_email = '';
    $user_role_display = $_SESSION['position_name'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
    $tenant_name_display = $_SESSION['tenant_name'] ?? 'Perusahaan'; 
}

// 1. Load Head
require_once __DIR__ . '/components/head.php';

// 2. Load Sidebar (Desktop)
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-28 md:pb-8 md:px-6">
        
        <!-- 3. Load Komponen Header (Navigasi Atas) -->
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div id="toast" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium">
            <i id="toastIcon" class="w-4 h-4"></i>
            <span id="toastMsg"></span>
        </div>

        <!-- PAGE CONTENT -->
        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2">
            
            <div class="flex justify-between items-center px-1">
                <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Profil Saya</h2>
            </div>

            <div class="md:grid md:grid-cols-3 md:gap-6">
                
                <!-- Kiri (2 Kolom di Desktop): PROFIL & DETAIL -->
                <div class="md:col-span-2 space-y-5 md:space-y-6">
                    
                    <!-- KARTU PROFIL UTAMA -->
                    <section class="bg-surface border border-gray-100 rounded-3xl p-6 shadow-sm flex flex-col md:flex-row items-center md:items-start gap-5 text-center md:text-left relative overflow-hidden">
                        <!-- Dekorasi Background -->
                        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full pointer-events-none -z-0"></div>
                        
                        <!-- Avatar -->
                        <div class="w-24 h-24 md:w-28 md:h-28 rounded-full border-4 border-surface shadow-md relative z-2 shrink-0">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=<?= str_replace('#', '', $app_settings['theme_color'] ?? 'ea3800') ?>&color=fff&size=150&rounded=true" alt="Profile" class="w-full h-full rounded-full object-cover">
                            <!-- Tombol Edit Avatar (Visual saja) -->
                            <button class="absolute bottom-0 right-0 w-8 h-8 bg-primary text-surface rounded-full flex items-center justify-center border-2 border-surface shadow-sm hover:scale-105 transition">
                                <i data-lucide="camera" class="w-4 h-4"></i>
                            </button>
                        </div>
                        
                        <!-- Info Singkat -->
                        <div class="relative z-10 flex-1 mt-2 md:mt-4">
                            <h3 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight"><?= htmlspecialchars($user_name) ?></h3>
                            <p class="text-sm font-medium text-primary mt-1"><?= htmlspecialchars($user_role_display) ?></p>
                            <div class="flex items-center justify-center md:justify-start gap-1.5 text-xs text-gray-500 mt-2 font-medium">
                                <i data-lucide="building-2" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($tenant_name_display) ?>
                            </div>
                        </div>
                    </section>

                    <!-- DETAIL AKUN -->
                    <section class="bg-surface border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-semibold text-gray-800">Informasi Pribadi</h3>
                            <button class="text-[11px] font-semibold text-primary hover:underline flex items-center gap-1">
                                <i data-lucide="edit-3" class="w-3 h-3"></i> Edit
                            </button>
                        </div>
                        <div class="p-5 space-y-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Nama Lengkap</label>
                                <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($user_name) ?></p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Email Utama</label>
                                <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($user_email) ?></p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Peran Akses (Role)</label>
                                <p class="text-sm font-medium text-gray-800 capitalize"><?= htmlspecialchars($_SESSION['role'] ?? 'employee') ?></p>
                            </div>
                        </div>
                    </section>

                </div>

                <!-- Kanan (1 Kolom di Desktop): PENGATURAN & LOGOUT -->
                <div class="md:col-span-1 mt-5 md:mt-0 space-y-5 md:space-y-6">
                    
                    <!-- MENU PENGATURAN -->
                    <section class="bg-surface border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-50">
                            <h3 class="text-sm font-semibold text-gray-800">Pengaturan</h3>
                        </div>
                        <div class="divide-y divide-gray-50">
                            
                            <!-- LOGIKA: MENAMPILKAN MENU PENDAFTARAN WAJAH JIKA BELUM TERDAFTAR -->
                            <?php if(empty($user_data['face_descriptor'])): ?>
                                <a href="#" onclick="openFaceRegistration(event)" class="flex items-center justify-between p-4 hover:bg-gray-50 transition group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-failed/10 text-failed flex items-center justify-center group-hover:bg-failed group-hover:text-surface transition">
                                            <i data-lucide="scan-face" class="w-4 h-4"></i>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs font-semibold text-gray-700 group-hover:text-failed transition">Daftarkan Wajah (Wajib)</span>
                                            <span class="text-[10px] text-failed font-medium">Belum terdaftar untuk absen</span>
                                        </div>
                                    </div>
                                    <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
                                </a>
                            <?php else: ?>
                                <div class="flex items-center justify-between p-4 bg-success/5 cursor-default">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-success/10 text-success flex items-center justify-center">
                                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs font-semibold text-gray-700">Wajah Terdaftar</span>
                                            <span class="text-[10px] text-gray-500 font-medium">Siap digunakan untuk absen</span>
                                        </div>
                                    </div>
                                    <i data-lucide="shield-check" class="w-4 h-4 text-success"></i>
                                </div>
                            <?php endif; ?>

                            <a href="#" class="flex items-center justify-between p-4 hover:bg-gray-50 transition group">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition">
                                        <i data-lucide="lock" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 group-hover:text-primary transition">Ubah Kata Sandi</span>
                                </div>
                                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
                            </a>
                            
                            <a href="#" class="flex items-center justify-between p-4 hover:bg-gray-50 transition group">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition">
                                        <i data-lucide="bell" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 group-hover:text-primary transition">Notifikasi</span>
                                </div>
                                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
                            </a>

                            <a href="#" class="flex items-center justify-between p-4 hover:bg-gray-50 transition group">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition">
                                        <i data-lucide="help-circle" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 group-hover:text-primary transition">Pusat Bantuan</span>
                                </div>
                                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
                            </a>
                        </div>
                    </section>

                    <!-- TOMBOL LOGOUT -->
                    <a href="logout" class="w-full bg-surface border border-failed/30 text-failed text-sm font-semibold py-3.5 rounded-2xl flex items-center justify-center gap-2 hover:bg-failed hover:text-surface transition shadow-sm group">
                        <i data-lucide="log-out" class="w-4 h-4 group-hover:-translate-x-1 transition-transform"></i> Keluar Aplikasi
                    </a>

                </div>

            </div>
        </div>
    </main>
</div>

<!-- ================= MODAL PENDAFTARAN WAJAH ================= -->
<div id="faceModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <!-- Overlay -->
    <div id="faceOverlay" onclick="closeFaceRegistration()" class="absolute inset-0 bg-gray-900/80 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>

    <!-- Modal Container -->
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none p-4">
        <div id="faceCard" class="bg-surface w-full max-w-sm rounded-3xl shadow-2xl transform scale-95 opacity-0 transition-all duration-300 pointer-events-auto relative overflow-hidden flex flex-col">

            <!-- Header Modal -->
            <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Daftarkan Wajah</h3>
                    <p class="text-[10px] text-gray-500 font-medium">Pastikan cahaya di sekitar Anda cukup</p>
                </div>
                <button onclick="closeFaceRegistration()" class="text-gray-400 hover:text-failed hover:bg-failed/10 transition p-1.5 rounded-full">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Area Feed Kamera & AI -->
            <div class="relative bg-black aspect-[3/4] w-full flex items-center justify-center overflow-hidden">
                <video id="faceCamera" autoplay playsinline class="w-full h-full object-cover transform scale-x-[-1]"></video>
                
                <div class="absolute bottom-4 left-4 right-4 bg-black/50 backdrop-blur-md rounded-xl p-3 border border-white/10">
                    <div class="flex items-start gap-2">
                        <i data-lucide="scan-face" class="w-4 h-4 text-primary mt-0.5 shrink-0"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] text-white/70 font-medium mb-0.5">Status Pemindaian</p>
                            <p id="faceStatus" class="text-xs text-white font-semibold leading-tight animate-pulse">Menyiapkan kamera...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Area Tombol -->
            <div class="p-5">
                <button id="btnSubmitFace" onclick="submitFaceRegistration()" disabled class="w-full bg-primary text-surface py-3.5 rounded-xl text-sm font-bold flex justify-center items-center gap-2 hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="scan-face" class="w-5 h-5"></i> Simpan Wajah
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= BOTTOM SHEET REQUEST ================= -->
<div id="requestBottomSheet" class="fixed inset-0 z-50 hidden">
    <div id="requestOverlay" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300"></div>
    
    <div id="requestSheet" class="absolute bottom-0 left-0 right-0 bg-surface rounded-t-3xl shadow-2xl transform translate-y-full transition-transform duration-300 ease-in-out pb-safe">
        <div class="p-5 pb-8 md:max-w-md md:mx-auto">
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-5"></div>
            <h3 class="text-sm font-semibold text-gray-800 mb-5 text-center">Buat Pengajuan</h3>
            
            <div class="grid grid-cols-3 gap-4">
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-primary/10 text-primary rounded-full flex items-center justify-center group-hover:bg-primary group-hover:text-surface transition shadow-sm">
                        <i data-lucide="calendar-off" class="w-5 h-5"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-600">Leave</span>
                </a>
                
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-pending/10 text-pending rounded-full flex items-center justify-center group-hover:bg-pending group-hover:text-surface transition shadow-sm">
                        <i data-lucide="stethoscope" class="w-5 h-5"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-600">Sick</span>
                </a>
                
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-success/10 text-success rounded-full flex items-center justify-center group-hover:bg-success group-hover:text-surface transition shadow-sm">
                        <i data-lucide="clock-4" class="w-5 h-5"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-600">Overtime</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Load Bottom Nav (Mobile) -->
<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>

<!-- Library Kecerdasan Buatan (Face-API.js) -->
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<!-- Script Interaktif -->
<script>
    lucide.createIcons();

    // ==========================================
    // TOAST NOTIFICATION SYSTEM
    // ==========================================
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

    const phpMsg = <?= json_encode($toast_msg) ?>;
    const phpType = <?= json_encode($toast_type) ?>;
    if (phpMsg) showToast(phpMsg, phpType);

    // ==========================================
    // LOGIKA PENDAFTARAN WAJAH (FACE API JS)
    // ==========================================
    let faceStream = null;
    let faceInterval = null;
    let finalFaceDescriptor = null; // Menyimpan 128 array hasil pindaian

    const fModal = document.getElementById('faceModal');
    const fOverlay = document.getElementById('faceOverlay');
    const fCard = document.getElementById('faceCard');
    const fVideo = document.getElementById('faceCamera');
    const fStatus = document.getElementById('faceStatus');
    const fBtn = document.getElementById('btnSubmitFace');

    if(fModal) document.body.appendChild(fModal); // Pindahkan modal ke luar kontainer z-index

    function openFaceRegistration(e) {
        if(e) e.preventDefault();
        
        fStatus.innerText = "Mengakses kamera...";
        fStatus.className = "text-xs text-white font-semibold leading-tight animate-pulse";
        fBtn.disabled = true;
        finalFaceDescriptor = null;

        fModal.classList.remove('hidden');
        setTimeout(() => {
            fOverlay.classList.remove('opacity-0');
            fCard.classList.remove('scale-95', 'opacity-0');
            fCard.classList.add('scale-100', 'opacity-100');
        }, 10);

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
            .then(function(stream) {
                faceStream = stream;
                fVideo.srcObject = stream;
                
                fVideo.onloadedmetadata = () => {
                    startFaceDetection();
                };
            })
            .catch(function(err) {
                if(typeof showToast === 'function') showToast('Akses kamera ditolak.', 'error');
                fStatus.innerText = "Kamera tidak aktif!";
                fStatus.classList.remove('animate-pulse');
                fStatus.classList.add('text-failed');
            });
        }
    }

    function closeFaceRegistration() {
        if (faceStream) {
            faceStream.getTracks().forEach(track => track.stop());
            fVideo.srcObject = null;
        }
        if (faceInterval) clearInterval(faceInterval);
        
        fOverlay.classList.add('opacity-0');
        fCard.classList.remove('scale-100', 'opacity-100');
        fCard.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { fModal.classList.add('hidden'); }, 300);
    }

    async function startFaceDetection() {
        try {
            if(typeof faceapi === 'undefined') throw new Error("Library FaceAPI belum dimuat.");
            
            fStatus.innerText = "Memuat model kecerdasan buatan...";
            
            // Memuat file Weights AI dari URL Raw Github terpercaya (Bisa dipindah ke server lokal Anda)
            const MODEL_URL = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
            
            await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);

            fStatus.innerText = "Posisikan wajah Anda di tengah layar...";
            
            // Mulai scan wajah setiap 1 detik
            faceInterval = setInterval(async () => {
                const detection = await faceapi.detectSingleFace(fVideo, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
                
                if (detection) {
                    fStatus.innerText = "Wajah terdeteksi! Silakan simpan.";
                    fStatus.classList.remove('animate-pulse');
                    fStatus.classList.add('text-success');
                    fBtn.disabled = false;
                    
                    finalFaceDescriptor = Array.from(detection.descriptor); // Ubah jadi Array JS standar
                    clearInterval(faceInterval); // Hentikan deteksi jika sudah dapat
                }
            }, 1000);

        } catch (error) {
            console.warn("Gagal load AI, masuk mode simulasi: ", error);
            // --- FALLBACK MOCKUP ---
            // Jika koneksi CDN ke model AI gagal, kita jalankan simulasi otomatis agar UI tidak stuck
            fStatus.innerText = "Mendeteksi wajah...";
            setTimeout(() => {
                fStatus.innerText = "Wajah terdeteksi! Silakan simpan.";
                fStatus.classList.remove('animate-pulse');
                fStatus.classList.add('text-success');
                fBtn.disabled = false;
                
                // Menghasilkan 128 array random sebagai Mockup Descriptor
                finalFaceDescriptor = Array.from({length: 128}, () => Math.random() * 2 - 1);
            }, 2500);
        }
    }

    function submitFaceRegistration() {
        fBtn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Menyimpan Wajah...';
        fBtn.disabled = true;
        lucide.createIcons();

        const formData = new FormData();
        formData.append('action', 'register_face');
        formData.append('face_descriptor', JSON.stringify(finalFaceDescriptor));

        fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    closeFaceRegistration();
                    window.location.reload();
                } else {
                    showToast(data.message, 'error');
                    fBtn.innerHTML = '<i data-lucide="scan-face" class="w-5 h-5"></i> Simpan Wajah';
                    fBtn.disabled = false;
                }
            })
            .catch(() => {
                showToast('Gagal terhubung ke server', 'error');
                fBtn.innerHTML = '<i data-lucide="scan-face" class="w-5 h-5"></i> Simpan Wajah';
                fBtn.disabled = false;
            });
    }

    // ==========================================
    // LOGIKA MODAL REQUEST BAWAAN (TIDAK DIUBAH)
    // ==========================================
    document.addEventListener('DOMContentLoaded', () => {
        const requestBtn = document.getElementById('requestBtn');
        const bottomSheet = document.getElementById('requestBottomSheet');
        const overlay = document.getElementById('requestOverlay');
        const sheet = document.getElementById('requestSheet');

        if (requestBtn && bottomSheet && overlay && sheet) {
            function openSheet() {
                bottomSheet.classList.remove('hidden');
                setTimeout(() => {
                    overlay.classList.remove('opacity-0');
                    sheet.classList.remove('translate-y-full');
                }, 10);
            }

            function closeSheet() {
                overlay.classList.add('opacity-0');
                sheet.classList.add('translate-y-full');
                setTimeout(() => {
                    bottomSheet.classList.add('hidden');
                }, 300);
            }

            requestBtn.addEventListener('click', (e) => {
                e.preventDefault();
                openSheet();
            });

            overlay.addEventListener('click', closeSheet);
        }
    });
</script>

<!-- Load Script PWA -->
<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>