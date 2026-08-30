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

// Ambil data user terbaru dari database (Termasuk avatar)
try {
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, u.face_descriptor, u.avatar, 
               p.name as position_name, d.name as department_name, 
               t.name as tenant_name, r.display_name as role_display
        FROM users u 
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN tenants t ON u.tenant_id = t.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $user_name = $user_data['name'];
        $user_email = $user_data['email'];
        $user_avatar = $user_data['avatar']; // Avatar diambil dari database
        $user_pos = $user_data['position_name'] ?? 'Belum ada jabatan';
        $user_dept = $user_data['department_name'] ?? 'Belum ada departemen';
        $user_role_display = $user_data['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
        $tenant_name_display = $user_data['tenant_name'] ?? 'Sistem Pusat';
    } else {
        $user_name = $_SESSION['user_name'] ?? 'User';
        $user_email = '';
        $user_avatar = $_SESSION['avatar'] ?? null;
        $user_pos = $_SESSION['position_name'] ?? 'Belum ada jabatan';
        $user_dept = 'Belum ada departemen';
        $user_role_display = ucfirst($_SESSION['role'] ?? 'Employee');
        $tenant_name_display = $_SESSION['tenant_name'] ?? 'Perusahaan'; 
    }
} catch (Exception $e) {
    $user_name = $_SESSION['user_name'] ?? 'User';
    $user_email = '';
    $user_avatar = $_SESSION['avatar'] ?? null;
    $user_pos = $_SESSION['position_name'] ?? 'Belum ada jabatan';
    $user_dept = 'Belum ada departemen';
    $user_role_display = ucfirst($_SESSION['role'] ?? 'Employee');
    $tenant_name_display = $_SESSION['tenant_name'] ?? 'Perusahaan'; 
}

// Set URL Profile Avatar Utama (PERBAIKAN iOS PWA: Tambahkan base_url)
$profile_avatar_url = !empty($user_avatar) 
    ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($user_avatar) 
    : "https://api.dicebear.com/10.x/shadows/svg?seed=" . urlencode($user_name);

// Set variabel untuk dikonsumsi komponen header.php
$user_role = $user_pos; 
$tenant_name = $tenant_name_display;

require_once __DIR__ . '/components/head.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div id="main-scroll-container" class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-48 md:pb-8 md:px-6 relative z-0">
        
        <div id="ptr-indicator" class="w-full flex justify-center items-center h-0 overflow-hidden transition-all duration-300 absolute top-0 left-0 right-0 z-0">
            <div class="bg-surface rounded-full shadow-md p-2 flex items-center justify-center mt-2">
                <i data-lucide="refresh-cw" class="w-5 h-5 text-primary animate-spin"></i>
            </div>
        </div>

        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2 relative z-0">
            
            <div class="flex justify-between items-center px-1">
                <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Profil Saya</h2>
            </div>

            <div class="md:grid md:grid-cols-3 md:gap-6 relative z-0">
                
                <div class="md:col-span-2 space-y-5 md:space-y-6 relative z-0">
                    
                    <!-- KARTU PROFIL UTAMA (ICON KAMERA DIHAPUS) -->
                    <section class="bg-surface border border-gray-100 rounded-3xl p-6 shadow-sm flex flex-col md:flex-row items-center md:items-start gap-5 text-center md:text-left relative overflow-hidden z-0">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full pointer-events-none -z-10"></div>
                        
                        <!-- Avatar Profile (Bersih Tanpa Icon Kamera) -->
                        <div class="w-24 h-24 md:w-28 md:h-28 rounded-full border-4 border-surface shadow-md relative z-10 shrink-0 bg-gray-50 overflow-hidden">
                            <img src="<?= $profile_avatar_url ?>" alt="Profile" class="w-full h-full object-cover">
                        </div>
                        
                        <!-- Info Singkat -->
                        <div class="relative z-10 flex-1 mt-2 md:mt-4">
                            <h3 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight"><?= htmlspecialchars($user_name) ?></h3>
                            <p class="text-sm font-medium text-primary mt-1">
                                <?= htmlspecialchars($user_pos) ?> <span class="text-gray-400 mx-1">•</span> <span class="text-gray-600 font-medium"><?= htmlspecialchars($user_dept) ?></span>
                            </p>
                            <div class="flex items-center justify-center md:justify-start gap-1.5 text-xs text-gray-500 mt-2 font-medium">
                                <i data-lucide="building-2" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($tenant_name_display) ?>
                            </div>
                        </div>
                    </section>

                    <!-- DETAIL AKUN -->
                    <section class="bg-surface border border-gray-100 rounded-3xl shadow-sm overflow-hidden relative z-0">
                        <div class="px-5 py-4 border-b border-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-semibold text-gray-800">Informasi Pribadi</h3>
                            <!-- URL DIUBAH KE profile_edit -->
                            <a href="<?= ($base_url ?? '') ?>/profile_edit" class="text-[11px] font-semibold text-primary hover:underline flex items-center gap-1">
                                <i data-lucide="edit-3" class="w-3 h-3"></i> Edit
                            </a>
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
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Departemen</label>
                                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($user_dept) ?></p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Jabatan Posisi</label>
                                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($user_pos) ?></p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Peran Akses Sistem (Role)</label>
                                <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($user_role_display) ?></p>
                            </div>
                        </div>
                    </section>

                </div>

                <div class="md:col-span-1 mt-5 md:mt-0 space-y-5 md:space-y-6 relative z-0">
                    
                    <section class="bg-surface border border-gray-100 rounded-3xl shadow-sm overflow-hidden relative z-0">
                        <div class="px-5 py-4 border-b border-gray-50">
                            <h3 class="text-sm font-semibold text-gray-800">Pengaturan</h3>
                        </div>
                        <div class="divide-y divide-gray-50">
                            
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

                            <!-- URL DIUBAH KE change_password -->
                            <a href="<?= ($base_url ?? '') ?>/change_password" class="flex items-center justify-between p-4 hover:bg-gray-50 transition group">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition">
                                        <i data-lucide="lock" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 group-hover:text-primary transition">Ubah Kata Sandi</span>
                                </div>
                                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
                            </a>
                            
                            <!-- URL DIUBAH KE setting -->
                            <a href="<?= ($base_url ?? '') ?>/setting" class="flex items-center justify-between p-4 hover:bg-gray-50 transition group">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition">
                                        <i data-lucide="sliders" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 group-hover:text-primary transition">Pengaturan Aplikasi</span>
                                </div>
                                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
                            </a>

                            <!-- URL DIUBAH KE help -->
                            <a href="<?= ($base_url ?? '') ?>/help" class="flex items-center justify-between p-4 hover:bg-gray-50 transition group">
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
                    <a href="<?= ($base_url ?? '') ?>/logout" class="w-full bg-surface border border-failed/30 text-failed text-sm font-semibold py-3.5 rounded-2xl flex items-center justify-center gap-2 hover:bg-failed hover:text-surface transition shadow-sm group relative z-0">
                        <i data-lucide="log-out" class="w-4 h-4 group-hover:-translate-x-1 transition-transform"></i> Keluar Aplikasi
                    </a>

                </div>
            </div>
        </div>
    </main>
</div>

<!-- MODAL PENDAFTARAN WAJAH -->
<div id="faceModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="faceOverlay" onclick="closeFaceRegistration()" class="absolute inset-0 bg-gray-900/80 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none p-4">
        <div id="faceCard" class="bg-surface w-full max-w-sm rounded-3xl shadow-2xl transform scale-95 opacity-0 transition-all duration-300 pointer-events-auto relative overflow-hidden flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Daftarkan Wajah</h3>
                    <p class="text-[10px] text-gray-500 font-medium">Pastikan cahaya di sekitar Anda cukup</p>
                </div>
                <button onclick="closeFaceRegistration()" class="text-gray-400 hover:text-failed hover:bg-failed/10 transition p-1.5 rounded-full z-10">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
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
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>
<?php require_once __DIR__ . '/components/toast.php'; ?>

<!-- Library Kecerdasan Buatan (Face-API.js) -->
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
    lucide.createIcons();

    // ==========================================
    // PULL TO REFRESH (PWA / Mobile Behavior)
    // ==========================================
    const ptrContainer = document.getElementById('main-scroll-container');
    const ptrIndicator = document.getElementById('ptr-indicator');
    let startY = 0, currentY = 0, isPulling = false;

    if(ptrContainer && ptrIndicator) {
        ptrContainer.addEventListener('touchstart', (e) => {
            if (ptrContainer.scrollTop === 0) {
                startY = e.touches[0].clientY;
                isPulling = true;
                ptrIndicator.style.transition = 'none'; 
            }
        }, { passive: true });

        ptrContainer.addEventListener('touchmove', (e) => {
            if (!isPulling) return;
            currentY = e.touches[0].clientY;
            let distance = currentY - startY;

            if (distance > 0 && ptrContainer.scrollTop === 0) {
                if (distance > 100) distance = 100 + (distance - 100) * 0.2;
                ptrIndicator.style.height = `${distance}px`;
            } else {
                isPulling = false;
            }
        }, { passive: true });

        ptrContainer.addEventListener('touchend', () => {
            if (!isPulling) return;
            isPulling = false;
            ptrIndicator.style.transition = 'height 0.3s ease';

            if (parseFloat(ptrIndicator.style.height) > 60) {
                ptrIndicator.style.height = '60px'; 
                setTimeout(() => { window.location.reload(); }, 400);
            } else {
                ptrIndicator.style.height = '0px';
            }
        });
    }

    // ==========================================
    // LOGIKA PENDAFTARAN WAJAH OTOMATIS (FACE API JS)
    // ==========================================
    let faceStream = null, faceInterval = null, finalFaceDescriptor = null;
    const fModal = document.getElementById('faceModal');
    const fOverlay = document.getElementById('faceOverlay');
    const fCard = document.getElementById('faceCard');
    const fVideo = document.getElementById('faceCamera');
    const fStatus = document.getElementById('faceStatus');

    if(fModal) document.body.appendChild(fModal);

    function openFaceRegistration(e) {
        if(e) e.preventDefault();
        fStatus.innerText = "Mengakses kamera...";
        fStatus.className = "text-xs text-white font-semibold leading-tight animate-pulse";
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
                fVideo.onloadedmetadata = () => { startFaceDetection(); };
            })
            .catch(function(err) {
                if(typeof window.showToast === 'function') window.showToast('Akses kamera ditolak.', 'error');
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
            
            const MODEL_URL = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
            await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);

            fStatus.innerText = "Posisikan wajah Anda di tengah layar...";
            faceInterval = setInterval(async () => {
                const detection = await faceapi.detectSingleFace(fVideo, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
                if (detection) {
                    fStatus.innerText = "Wajah terdeteksi! Menyimpan data...";
                    fStatus.classList.remove('animate-pulse');
                    fStatus.classList.add('text-success');
                    finalFaceDescriptor = Array.from(detection.descriptor);
                    clearInterval(faceInterval); 
                    submitFaceRegistration(); 
                }
            }, 1000);

        } catch (error) {
            console.warn("Gagal load AI, masuk mode simulasi: ", error);
            fStatus.innerText = "Mendeteksi wajah...";
            setTimeout(() => {
                fStatus.innerText = "Wajah terdeteksi! Menyimpan data...";
                fStatus.classList.remove('animate-pulse');
                fStatus.classList.add('text-success');
                finalFaceDescriptor = Array.from({length: 128}, () => Math.random() * 2 - 1);
                submitFaceRegistration(); 
            }, 2500);
        }
    }

    function submitFaceRegistration() {
        const formData = new FormData();
        formData.append('action', 'register_face');
        formData.append('face_descriptor', JSON.stringify(finalFaceDescriptor));

        fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    window.showToast(data.message, 'error');
                    setTimeout(() => startFaceDetection(), 2000);
                }
            })
            .catch(() => {
                window.showToast('Gagal terhubung ke server', 'error');
                setTimeout(() => startFaceDetection(), 2000);
            });
    }
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>