<?php
// Panggil Konfigurasi Global & Database
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Panggil Komponen Auth (Memastikan user harus login)
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// ==============================================================================
// PENANGANAN AJAX: UPDATE FOTO AVATAR (DIPOTONG VIA CROPPER JS 1:1)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_avatar') {
    header('Content-Type: application/json');
    try {
        $image_base64 = $_POST['image'] ?? '';
        if (preg_match('/^data:image\/(\w+);base64,/', $image_base64)) {
            $data = substr($image_base64, strpos($image_base64, ',') + 1);
            $data = base64_decode($data);
            
            // Nama file unik
            $image_name = 'avatar_' . $user_id . '_' . time() . '.jpg';
            $upload_dir = __DIR__ . '/assets/img/avatars';
            
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            file_put_contents($upload_dir . '/' . $image_name, $data);

            // Update ke Database
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$image_name, $user_id, $tenant_id]);

            // Synchronize Session
            $_SESSION['avatar'] = $image_name;

            echo json_encode([
                'status' => 'success', 
                'message' => 'Foto profil berhasil diperbarui!',
                // PERBAIKAN iOS PWA: Balikkan Absolute Path ke JS
                'avatar_url' => ($base_url ?? '') . '/assets/img/avatars/' . $image_name
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Format gambar tidak valid.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
// ==============================================================================

// Menangkap Pesan Toast
$toast_msg = '';
$toast_type = '';

// ==============================================================================
// PENANGANAN POST: UPDATE DATA FORM (WHATSAPP)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'update_profile')) {
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET whatsapp = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$whatsapp, $user_id, $tenant_id]);

        $_SESSION['toast_msg'] = "Profil berhasil diperbarui!";
        $_SESSION['toast_type'] = "success";
        header("Location: profile");
        exit;
    } catch (Exception $e) {
        $toast_msg = "Gagal memperbarui profil: " . $e->getMessage();
        $toast_type = "error";
    }
}

// Fetch Data Terbaru User
try {
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, u.whatsapp, u.avatar, 
               p.name as position_name, d.name as department_name, t.name as tenant_name
        FROM users u 
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN tenants t ON u.tenant_id = t.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $user_name = $user_data['name'] ?? ($_SESSION['user_name'] ?? 'User');
    $user_email = $user_data['email'] ?? '';
    $user_whatsapp = $user_data['whatsapp'] ?? '';
    $user_avatar = $user_data['avatar'] ?? null;
    $user_pos = $user_data['position_name'] ?? 'Belum ada jabatan';
    $tenant_name_display = $user_data['tenant_name'] ?? 'Perusahaan';
} catch (Exception $e) {
    $user_name = $_SESSION['user_name'] ?? 'User';
    $user_email = '';
    $user_whatsapp = '';
    $user_avatar = null;
    $user_pos = 'Belum ada jabatan';
    $tenant_name_display = 'Perusahaan';
}

// PERBAIKAN iOS PWA: Tambahkan $base_url
$profile_avatar_url = !empty($user_avatar) 
    ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($user_avatar) 
    : "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($user_name);

$user_role = $user_pos;
$tenant_name = $tenant_name_display;

require_once __DIR__ . '/components/head.php';
// Memuat Cropper.js CSS & JS via CDN
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css"/>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>';

require_once __DIR__ . '/components/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
    <main class="w-full min-h-screen pb-8 md:px-6">
        
        <!-- HEADER DESKTOP -->
        <div class="hidden md:block">
            <?php require_once __DIR__ . '/components/header.php'; ?>
        </div>

        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full mx-auto">
            
            <!-- Judul & Back Button -->
            <div class="flex items-center gap-3 px-1 mb-6">
                <a href="<?= ($base_url ?? '') ?>/profile" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition shadow-sm active:scale-95">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Edit Profil</h2>
                    <p class="text-[11px] text-gray-500">Perbarui foto profil dan nomor kontak Anda.</p>
                </div>
            </div>

            <!-- Form Card (Disamakan layout-nya dengan change_password.php) -->
            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm p-6 md:p-8 space-y-6">
                
                <!-- UPLOAD AVATAR SECTION -->
                <div class="flex flex-col items-center justify-center text-center pb-6 border-b border-gray-100">
                    <div class="relative w-28 h-28 md:w-32 md:h-32 rounded-full border-4 border-surface shadow-md bg-gray-50 group">
                        <img id="avatarPreview" src="<?= $profile_avatar_url ?>" alt="Avatar" class="w-full h-full rounded-full object-cover">
                        
                        <!-- Tombol Kamera (Membuka File Manager) -->
                        <button type="button" onclick="document.getElementById('avatarInput').click()" class="absolute bottom-0 right-0 w-9 h-9 bg-primary text-surface rounded-full flex items-center justify-center border-2 border-surface shadow-sm hover:scale-110 active:scale-95 transition cursor-pointer" title="Ubah Foto Profil">
                            <i data-lucide="camera" class="w-4 h-4"></i>
                        </button>
                    </div>
                    
                    <!-- Input File Tersembunyi -->
                    <input type="file" id="avatarInput" accept="image/*" class="hidden">
                    <p class="text-[10px] text-gray-400 mt-3 font-medium">Klik icon kamera untuk mengubah foto (Rasio 1:1)</p>
                </div>

                <!-- FORM EDIT DATA -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="space-y-4">
                        
                        <!-- Nama (Readonly) -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nama Lengkap</label>
                            <input type="text" value="<?= htmlspecialchars($user_name) ?>" readonly class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-xs text-gray-500 cursor-not-allowed focus:outline-none transition">
                            <p class="text-[9px] text-gray-400 mt-1.5">Nama hanya dapat diubah oleh Administrator / HRD.</p>
                        </div>

                        <!-- Email (Readonly) -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Alamat Email</label>
                            <input type="email" value="<?= htmlspecialchars($user_email) ?>" readonly class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-xs text-gray-500 cursor-not-allowed focus:outline-none transition">
                            <p class="text-[9px] text-gray-400 mt-1.5">Email digunakan sebagai ID login akun Anda.</p>
                        </div>

                        <!-- WhatsApp (Editable) Numeric Input Mobile -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nomor WhatsApp</label>
                            <input type="tel" inputmode="numeric" pattern="[0-9\-\+]*" name="whatsapp" value="<?= htmlspecialchars($user_whatsapp) ?>" oninput="this.value = this.value.replace(/[^0-9\-\+]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition" placeholder="Misal: 08123456789">
                        </div>

                    </div>

                    <!-- Tombol Aksi -->
                    <div class="mt-8 pt-6 border-t border-gray-100 flex gap-3">
                        <a href="<?= ($base_url ?? '') ?>/profile" class="w-1/3 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold text-center hover:bg-gray-50 transition active:scale-95 flex items-center justify-center">
                            Batal
                        </a>
                        <button type="submit" class="flex-1 bg-primary text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2 active:scale-95">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>

            </div>

        </div>
    </main>
</div>

<!-- ================= MODAL CROPPER FOTO (1:1) ================= -->
<div id="cropModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <!-- Overlay -->
    <div id="cropOverlay" onclick="closeCropModal()" class="absolute inset-0 bg-gray-900/80 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>

    <!-- Container Card -->
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none p-4">
        <div id="cropCard" class="bg-surface w-full max-w-md rounded-3xl shadow-2xl transform scale-95 opacity-0 transition-all duration-300 pointer-events-auto relative overflow-hidden flex flex-col p-6">
            
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold text-gray-800">Potong Foto Profil (1:1)</h3>
                <button type="button" onclick="closeCropModal()" class="text-gray-400 hover:text-failed p-1.5 rounded-full transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Preview Image Area -->
            <div class="relative bg-black rounded-2xl overflow-hidden aspect-square max-h-[350px] w-full flex items-center justify-center mb-6">
                <img id="cropImage" src="" class="max-w-full">
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3">
                <button type="button" onclick="closeCropModal()" class="flex-1 py-3 bg-gray-100 text-gray-700 rounded-xl text-xs font-semibold hover:bg-gray-200 transition active:scale-95">
                    Batal
                </button>
                <button type="button" id="btnSaveCrop" onclick="uploadCroppedImage()" class="flex-1 py-3 bg-primary text-surface rounded-xl text-xs font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2 active:scale-95">
                    <i data-lucide="check" class="w-4 h-4"></i> Simpan Foto
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Bottom Nav sengaja TIDAK dipanggil agar bersih di layar form edit (mobile) -->

<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    const phpMsg = <?= json_encode($toast_msg) ?>;
    const phpType = <?= json_encode($toast_type) ?>;
    if (phpMsg && typeof window.showToast === 'function') {
        window.showToast(phpMsg, phpType);
    }

    // ==========================================
    // LOGIKA CROPPER JS & AJAX UPLOAD
    // ==========================================
    let cropper = null;
    const avatarInput = document.getElementById('avatarInput');
    const cropModal = document.getElementById('cropModal');
    const cropOverlay = document.getElementById('cropOverlay');
    const cropCard = document.getElementById('cropCard');
    const cropImage = document.getElementById('cropImage');
    const avatarPreview = document.getElementById('avatarPreview');

    if (avatarInput) {
        avatarInput.addEventListener('change', function (e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const file = files[0];
                if (!file.type.startsWith('image/')) {
                    if (typeof window.showToast === 'function') window.showToast('Harap pilih file gambar!', 'error');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    cropImage.src = e.target.result;
                    openCropModal();
                };
                reader.readAsDataURL(file);
            }
        });
    }

    function openCropModal() {
        cropModal.classList.remove('hidden');
        setTimeout(() => {
            cropOverlay.classList.remove('opacity-0');
            cropCard.classList.remove('scale-95', 'opacity-0');
            cropCard.classList.add('scale-100', 'opacity-100');
            
            // Inisialisasi Cropper.js 1:1
            if (cropper) cropper.destroy();
            cropper = new Cropper(cropImage, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 1,
                responsive: true,
                background: false
            });
        }, 10);
    }

    function closeCropModal() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        avatarInput.value = ''; // Reset file input
        cropOverlay.classList.add('opacity-0');
        cropCard.classList.remove('scale-100', 'opacity-100');
        cropCard.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { cropModal.classList.add('hidden'); }, 300);
    }

    function uploadCroppedImage() {
        if (!cropper) return;

        const btnSave = document.getElementById('btnSaveCrop');
        btnSave.disabled = true;
        btnSave.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Menyimpan...';
        lucide.createIcons();

        // Mengambil hasil potongan gambar dengan rasio 1:1 (Resolusi 400x400)
        const canvas = cropper.getCroppedCanvas({
            width: 400,
            height: 400
        });

        const base64Image = canvas.toDataURL('image/jpeg', 0.85);

        const formData = new FormData();
        formData.append('action', 'update_avatar');
        formData.append('image', base64Image);

        fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                btnSave.disabled = false;
                btnSave.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Simpan Foto';
                lucide.createIcons();

                if (data.status === 'success') {
                    // Update tampilan preview tanpa perlu reload
                    avatarPreview.src = data.avatar_url;
                    
                    // Update juga foto profil di header desktop & mobile jika ada
                    const headerAvatars = document.querySelectorAll('header img');
                    headerAvatars.forEach(img => img.src = data.avatar_url);

                    if (typeof window.showToast === 'function') {
                        window.showToast(data.message, 'success');
                    }
                    closeCropModal();
                } else {
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.message, 'error');
                    }
                }
            })
            .catch(() => {
                btnSave.disabled = false;
                btnSave.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Simpan Foto';
                lucide.createIcons();
                if (typeof window.showToast === 'function') {
                    window.showToast('Gagal terhubung ke server.', 'error');
                }
            });
    }
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>