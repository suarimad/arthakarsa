<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

// Guard: Hanya admin & superadmin
if (!in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['toast_type'] = "failed";
    header("Location: index");
    exit;
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// ==============================================================================
// PENANGANAN AJAX (ADD, EDIT, DELETE, VIEW)
// ==============================================================================
if (isset($_POST['ajax_action']) || isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        // --- 1. VIEW DATA (GET) ---
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['ajax_action'] === 'view') {
            $id = $_GET['location_id'];
            
            // Hitung total karyawan di lokasi ini
            $stmt = $pdo->prepare("SELECT COUNT(id) as total_users FROM users WHERE location_id = ? AND tenant_id = ? AND deleted_at IS NULL");
            $stmt->execute([$id, $tenant_id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'users' => $res['total_users'] ?? 0
                ]
            ]);
            exit;
        }

        // --- 2. ADD DATA (POST) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['ajax_action'] === 'add') {
            $name = trim($_POST['name']);
            $latitude = $_POST['latitude'];
            $longitude = $_POST['longitude'];
            $radius = (int)$_POST['radius'];
            
            $stmt = $pdo->prepare("INSERT INTO locations (tenant_id, name, latitude, longitude, radius) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $name, $latitude, $longitude, $radius]);
            
            $_SESSION['toast_msg'] = "Lokasi berhasil ditambahkan!";
            $_SESSION['toast_type'] = "success";
            echo json_encode(['status' => 'success']);
            exit;
        }

        // --- 3. EDIT DATA (POST) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['ajax_action'] === 'edit') {
            $id = $_POST['location_id'];
            $name = trim($_POST['name']);
            $latitude = $_POST['latitude'];
            $longitude = $_POST['longitude'];
            $radius = (int)$_POST['radius'];
            
            $stmt = $pdo->prepare("UPDATE locations SET name = ?, latitude = ?, longitude = ?, radius = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$name, $latitude, $longitude, $radius, $id, $tenant_id]);
            
            $_SESSION['toast_msg'] = "Lokasi berhasil diperbarui!";
            $_SESSION['toast_type'] = "success";
            echo json_encode(['status' => 'success']);
            exit;
        }

        // --- 4. DELETE DATA (POST) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['ajax_action'] === 'delete') {
            $id = $_POST['location_id'];
            
            // Menggunakan Soft Delete karena tabel locations memiliki deleted_at
            $stmt = $pdo->prepare("UPDATE locations SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            
            $_SESSION['toast_msg'] = "Lokasi berhasil dihapus!";
            $_SESSION['toast_type'] = "success";
            echo json_encode(['status' => 'success']);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
// ==============================================================================

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan'; 

// MENGAMBIL DATA LOKASI UNTUK RENDER HALAMAN UTAMA
$stmt = $pdo->prepare("
    SELECT * FROM locations 
    WHERE tenant_id = ? AND deleted_at IS NULL 
    ORDER BY name ASC
");
$stmt->execute([$tenant_id]);
$locations = $stmt->fetchAll();

require_once __DIR__ . '/components/head.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2 relative z-0">
            
            <div class="flex justify-between items-center px-1">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Manajemen Lokasi</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Kelola titik koordinat (Geofencing) perusahaan</p>
                </div>
                
                <!-- TOMBOL TAMBAH AJAX -->
                <button onclick="openCrud('add')" class="bg-primary/10 text-primary px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:bg-primary hover:text-surface transition shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i> Tambah
                </button>
            </div>

            <!-- Form Pencarian (Local JS) -->
            <div class="relative z-0">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="searchInput" placeholder="Cari nama lokasi..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
            </div>

            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-3 space-y-5 md:space-y-6">
                    
                    <section class="relative z-0">
                        <div class="flex justify-between items-center mb-3 px-1">
                            <h3 class="text-xs md:text-sm font-semibold text-gray-800">Daftar Lokasi</h3>
                            <span class="text-[10px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full" id="locationCount"><?= count($locations) ?> Lokasi</span>
                        </div>
                        
                        <!-- Kontainer List Lokasi -->
                        <div id="locationListContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4 relative z-0">
                            <?php foreach($locations as $loc): 
                                $safe_name = htmlspecialchars($loc['name'], ENT_QUOTES);
                                $lat = $loc['latitude'];
                                $lng = $loc['longitude'];
                                $radius = $loc['radius'];
                            ?>
                                <div class="bg-surface border border-gray-100 rounded-xl p-4 shadow-sm flex flex-col gap-3 transition hover:border-gray-200 group location-card relative z-0" data-name="<?= strtolower($safe_name) ?>">
                                    
                                    <!-- Header Card -->
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                            <i data-lucide="map-pin" class="w-6 h-6"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-sm font-bold text-gray-800 truncate group-hover:text-primary transition-colors"><?= $safe_name ?></h4>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <i data-lucide="crosshair" class="w-3 h-3 text-gray-400"></i>
                                                <p class="text-[10px] text-gray-500 font-medium truncate"><?= $lat ?>, <?= $lng ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Info Jarak Radius -->
                                    <div class="bg-gray-50 rounded-lg p-2.5 flex items-center justify-between mt-1 border border-gray-100/50">
                                        <span class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider">Radius Absen</span>
                                        <span class="text-xs font-bold text-gray-700"><?= $radius ?> Meter</span>
                                    </div>

                                    <!-- Aksi CRUD AJAX -->
                                    <div class="flex gap-1.5 mt-2">
                                        <button onclick="openCrud('view', <?= $loc['id'] ?>, '<?= $safe_name ?>')" class="flex-1 py-2 bg-gray-50 text-gray-600 rounded-lg text-[10px] font-semibold flex items-center justify-center gap-1 hover:bg-success/10 hover:text-success transition">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i> Lihat
                                        </button>
                                        <button onclick="openCrud('edit', <?= $loc['id'] ?>, '<?= $safe_name ?>', '<?= $lat ?>', '<?= $lng ?>', '<?= $radius ?>')" class="flex-1 py-2 bg-gray-50 text-gray-600 rounded-lg text-[10px] font-semibold flex items-center justify-center gap-1 hover:bg-primary/10 hover:text-primary transition">
                                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit
                                        </button>
                                        <button onclick="openCrud('delete', <?= $loc['id'] ?>, '<?= $safe_name ?>')" class="flex-1 py-2 bg-gray-50 text-gray-600 rounded-lg text-[10px] font-semibold flex items-center justify-center gap-1 hover:bg-failed/10 hover:text-failed transition">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus
                                        </button>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                            
                            <?php if(empty($locations)): ?>
                                <div class="col-span-full bg-surface border border-dashed border-gray-200 rounded-2xl p-8 text-center">
                                    <div class="w-12 h-12 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="map" class="w-6 h-6"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-gray-800">Belum ada lokasi</h4>
                                    <p class="text-xs text-gray-500 mt-1 max-w-xs mx-auto">Klik tombol tambah di atas untuk mendaftarkan titik geofencing kantor Anda.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                </div>
            </div>
        </div>
    </main>
</div>

<!-- ================= HYBRID MODAL/BOTTOM SHEET (CRUD) ================= -->
<div id="crudModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <!-- Overlay -->
    <div id="crudOverlay" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    
    <!-- Modal Container: flex items-end (mobile) md:items-center (desktop) -->
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <!-- Content Card -->
        <div id="crudCard" class="bg-surface w-full max-w-md rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh]">
            
            <!-- Handle Drag line for mobile -->
            <div class="pt-5 pb-2 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeCrud()">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full"></div>
            </div>
            
            <!-- Close button desktop -->
            <button onclick="closeCrud()" class="hidden md:flex absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full z-10">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            
            <!-- Dynamic Content Injected Here -->
            <div id="crudContent" class="px-6 pb-8 md:p-8 overflow-y-auto"></div>
        </div>
    </div>
</div>

<!-- ================= BOTTOM SHEET REQUEST (NAV) ================= -->
<div id="requestBottomSheet" class="fixed inset-0 hidden" style="z-index: 99998;">
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

<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>

<!-- Komponen Toast Global (Menangkap Session) -->
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    // ==========================================
    // LOKAL SEARCH JS (Tanpa Loading/AJAX)
    // ==========================================
    const searchInput = document.getElementById('searchInput');
    const locationCards = document.querySelectorAll('.location-card');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            locationCards.forEach(card => {
                const name = card.getAttribute('data-name');
                if (name.includes(q)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // ==========================================
    // DETEKSI LOKASI (GEOLOCATION API)
    // ==========================================
    window.detectLocation = function(btn) {
        if (!navigator.geolocation) {
            if(typeof window.showToast === 'function') window.showToast("Browser Anda tidak mendukung deteksi lokasi.", "error");
            return;
        }

        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Mendeteksi...';
        btn.disabled = true;
        lucide.createIcons();

        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                document.getElementById('inputLat').value = lat;
                document.getElementById('inputLng').value = lng;
                
                btn.innerHTML = '<i data-lucide="check" class="w-3 h-3"></i> Berhasil';
                btn.classList.replace('text-primary', 'text-success');
                btn.classList.replace('bg-primary/10', 'bg-success/10');
                lucide.createIcons();

                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    btn.classList.replace('text-success', 'text-primary');
                    btn.classList.replace('bg-success/10', 'bg-primary/10');
                    lucide.createIcons();
                }, 2500);
            },
            function(error) {
                if(typeof window.showToast === 'function') window.showToast("Gagal mengambil lokasi. Pastikan GPS aktif dan izin diberikan.", "error");
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                lucide.createIcons();
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    // ==========================================
    // HYBRID MODAL CRUD AJAX (DESKTOP & MOBILE)
    // ==========================================
    const crudModal = document.getElementById('crudModal');
    const crudOverlay = document.getElementById('crudOverlay');
    const crudCard = document.getElementById('crudCard');
    const crudContent = document.getElementById('crudContent');
    
    // Pindahkan modal ke body agar z-index bekerja absolut di luar sidebar
    document.body.appendChild(crudModal);

    window.openCrud = function(action, id = '', name = '', lat = '', lng = '', rad = 50) {
        // 1. Generate HTML berdasarkan aksi
        let html = '';
        if (action === 'add' || action === 'edit') {
            const title = action === 'add' ? 'Tambah Lokasi' : 'Edit Lokasi';
            
            html = `
                <h3 class="text-base font-bold text-gray-800 mb-6 text-center">${title}</h3>
                <form id="ajaxCrudForm">
                    <input type="hidden" name="ajax_action" value="${action}">
                    ${action === 'edit' ? `<input type="hidden" name="location_id" value="${id}">` : ''}
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Nama Lokasi (Kantor)</label>
                            <input type="text" name="name" value="${name}" required placeholder="Misal: Kantor Pusat Jakarta" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-1 focus:ring-primary focus:outline-none transition">
                        </div>
                        
                        <div class="relative pt-2 pb-2">
                            <div class="flex justify-between items-center mb-1.5">
                                <label class="block text-[10px] font-semibold text-gray-600 uppercase">Titik Koordinat</label>
                                <button type="button" onclick="detectLocation(this)" class="text-[10px] bg-primary/10 text-primary px-2.5 py-1.5 rounded-lg flex items-center gap-1 hover:bg-primary hover:text-surface transition active:scale-95">
                                    <i data-lucide="crosshair" class="w-3 h-3"></i> Gunakan Lokasi Saat Ini
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <input type="number" step="any" name="latitude" id="inputLat" value="${lat}" required placeholder="Latitude" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-1 focus:ring-primary focus:outline-none transition">
                                </div>
                                <div>
                                    <input type="number" step="any" name="longitude" id="inputLng" value="${lng}" required placeholder="Longitude" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-1 focus:ring-primary focus:outline-none transition">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Radius Toleransi (Meter)</label>
                            <input type="number" name="radius" value="${rad}" required placeholder="50" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-1 focus:ring-primary focus:outline-none transition">
                        </div>
                    </div>
                    <button type="submit" id="btnSubmitCrud" class="w-full bg-primary text-surface py-3 rounded-xl text-sm font-semibold mt-8 flex justify-center items-center gap-2 hover:opacity-90 transition">
                        <i data-lucide="save" class="w-4 h-4"></i> Simpan Data
                    </button>
                </form>
            `;
        } 
        else if (action === 'delete') {
            html = `
                <div class="text-center">
                    <div class="w-14 h-14 bg-failed/10 text-failed rounded-full flex items-center justify-center mx-auto mb-4 mt-2 md:mt-0">
                        <i data-lucide="trash-2" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-800 mb-2">Hapus Lokasi?</h3>
                    <p class="text-xs text-gray-500 mb-6">Apakah Anda yakin ingin menghapus <b>${name}</b>? Data lokasi ini akan disembunyikan (soft delete).</p>
                    <form id="ajaxCrudForm">
                        <input type="hidden" name="ajax_action" value="delete">
                        <input type="hidden" name="location_id" value="${id}">
                        <div class="flex gap-3">
                            <button type="button" onclick="closeCrud()" class="flex-1 py-3 bg-gray-50 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-100 transition">Batal</button>
                            <button type="submit" id="btnSubmitCrud" class="flex-1 bg-failed text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition">Ya, Hapus</button>
                        </div>
                    </form>
                </div>
            `;
        }
        else if (action === 'view') {
            html = `
                <div class="text-center mb-6 mt-2 md:mt-0">
                    <div class="w-14 h-14 bg-primary/10 text-primary rounded-full flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="map-pin" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-800">Statistik Lokasi</h3>
                    <p class="text-xs text-primary font-medium mt-0.5">${name}</p>
                </div>
                
                <div id="viewLoader" class="flex justify-center py-6">
                    <i data-lucide="loader-2" class="w-6 h-6 animate-spin text-primary"></i>
                </div>
                
                <div id="viewStats" class="hidden space-y-3">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
                        <div class="flex items-center gap-3">
                            <i data-lucide="users" class="w-4 h-4 text-gray-400"></i>
                            <span class="text-xs font-semibold text-gray-700">Karyawan yang di-Assign</span>
                        </div>
                        <span id="st-users" class="text-sm font-bold text-primary">0</span>
                    </div>
                </div>
                <button onclick="closeCrud()" class="w-full mt-6 py-3 bg-gray-50 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-100 transition">Tutup</button>
            `;
            
            // Fetch View Data
            fetch(`?ajax_action=view&location_id=${id}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('viewLoader').classList.add('hidden');
                    document.getElementById('viewStats').classList.remove('hidden');
                    document.getElementById('st-users').innerText = data.data.users;
                });
        }

        crudContent.innerHTML = html;
        lucide.createIcons();

        // 2. Animasi Buka Modal/Bottom Sheet
        crudModal.classList.remove('hidden');
        setTimeout(() => {
            crudOverlay.classList.remove('opacity-0');
            
            // Animasi Mobile
            crudCard.classList.remove('translate-y-full');
            crudCard.classList.add('translate-y-0');
            
            // Animasi Desktop
            crudCard.classList.remove('md:scale-95', 'md:opacity-0');
            crudCard.classList.add('md:scale-100', 'md:opacity-100');
        }, 10);
        
        // 3. Attach Form Submit Event (Delegation)
        const form = document.getElementById('ajaxCrudForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = document.getElementById('btnSubmitCrud');
                btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Memproses...';
                btn.disabled = true;
                lucide.createIcons();

                const formData = new FormData(this);
                fetch('', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            window.location.reload();
                        } else {
                            if(typeof window.showToast === 'function') window.showToast(data.message || 'Terjadi kesalahan sistem', 'error');
                            btn.innerHTML = 'Coba Lagi';
                            btn.disabled = false;
                        }
                    })
                    .catch(() => {
                        if(typeof window.showToast === 'function') window.showToast('Gagal terhubung ke server', 'error');
                        btn.innerHTML = 'Coba Lagi';
                        btn.disabled = false;
                    });
            });
        }
    }

    function closeCrud() {
        crudOverlay.classList.add('opacity-0');
        
        // Tutup Animasi Mobile
        crudCard.classList.remove('translate-y-0');
        crudCard.classList.add('translate-y-full'); 
        
        // Tutup Animasi Desktop
        crudCard.classList.remove('md:scale-100', 'md:opacity-100'); 
        crudCard.classList.add('md:scale-95', 'md:opacity-0'); 
        
        setTimeout(() => { crudModal.classList.add('hidden'); }, 300);
    }
    
    if (crudOverlay) crudOverlay.addEventListener('click', closeCrud);

    // ==========================================
    // BOTTOM NAV REQUEST MODAL
    // ==========================================
    document.addEventListener('DOMContentLoaded', () => {
        const requestBtn = document.getElementById('requestBtn');
        const bottomSheet = document.getElementById('requestBottomSheet');
        const overlay = document.getElementById('requestOverlay');
        const sheet = document.getElementById('requestSheet');

        document.body.appendChild(bottomSheet);

        function openSheet() {
            bottomSheet.classList.remove('hidden');
            setTimeout(() => { overlay.classList.remove('opacity-0'); sheet.classList.remove('translate-y-full'); }, 10);
        }
        function closeSheet() {
            overlay.classList.add('opacity-0'); sheet.classList.add('translate-y-full');
            setTimeout(() => { bottomSheet.classList.add('hidden'); }, 300);
        }

        if (requestBtn) requestBtn.addEventListener('click', (e) => { e.preventDefault(); openSheet(); });
        if (overlay) overlay.addEventListener('click', closeSheet);
    });
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>