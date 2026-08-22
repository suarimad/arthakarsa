<?php
// Panggil Konfigurasi Global
require_once __DIR__ . '/config/config.php';

// Panggil Komponen Auth (Memastikan user harus login)
require_once __DIR__ . '/components/auth.php';

// Data Karyawan & Tenant (Dinamis dari Session)
$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan'; 

// ==========================================
// 1. DATA DINAMIS ABSENSI, AKTIVITAS & SHIFT
// ==========================================

// Setup Tanggal Hari Ini (Format Indonesia)
$hari = array("Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu");
$bulan = array("","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember");
$date_now = $hari[date("w")] . ", " . date("j") . " " . $bulan[date("n")] . " " . date("Y");

// Default Fallback
$shift_start = "--:--";
$shift_end = "--:--";
$is_geofence_enabled = 1; // Default strict
$office_lat = null;
$office_lng = null;
$office_radius = 50; // Default radius

$attendances = [];
$activities = [];

try {
    // --- AMBIL PENGATURAN TENANT ---
    $stmtSet = $pdo->prepare("SELECT is_geofence_enabled FROM tenant_settings WHERE tenant_id = ?");
    $stmtSet->execute([$tenant_id]);
    $setting = $stmtSet->fetch(PDO::FETCH_ASSOC);
    if ($setting) {
        $is_geofence_enabled = (int)$setting['is_geofence_enabled'];
    }

    // --- AMBIL SHIFT & LOKASI USER AKTIF ---
    $stmtUser = $pdo->prepare("
        SELECT s.start, s.end, l.latitude, l.longitude, l.radius 
        FROM users u 
        LEFT JOIN shifts s ON u.shift_id = s.id 
        LEFT JOIN locations l ON u.location_id = l.id
        WHERE u.id = ?
    ");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if ($userData) {
        if ($userData['start'] && $userData['end']) {
            $shift_start = date('H:i', strtotime($userData['start']));
            $shift_end = date('H:i', strtotime($userData['end']));
        }
        if ($userData['latitude'] && $userData['longitude']) {
            $office_lat = $userData['latitude'];
            $office_lng = $userData['longitude'];
            $office_radius = (int)$userData['radius'];
        }
    }

    // --- RIWAYAT ABSENSI ---
    $stmtAtt = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? ORDER BY date DESC LIMIT 5");
    $stmtAtt->execute([$user_id]);
    $attendances = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

    // --- AKTIVITAS ---
    $stmtAct = $pdo->prepare("SELECT * FROM activities WHERE (user_id = ? OR user_id IS NULL) AND tenant_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmtAct->execute([$user_id, $tenant_id]);
    $activities = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Fallback Mockup Data
    $shift_start = "08:00";
    $shift_end = "17:00";
    
    $attendances = [
        [
            'date' => date('Y-m-d', strtotime('-1 days')), 
            'clock_in_time' => '07:55:00', 'clock_out_time' => '17:10:00', 
            'clock_in_status' => 'on_time'
        ]
    ];
}

// 1. Load Head 
require_once __DIR__ . '/components/head.php';

// 2. Load Sidebar (Desktop)
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        
        <!-- 3. Load Komponen Header (Navigasi Atas) -->
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <!-- DASHBOARD CONTENT -->
        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                
                <!-- Kiri (2 Kolom di Desktop) -->
                <div class="md:col-span-2 space-y-5 md:space-y-6">
                    
                    <!-- ATTENDANCE CARD -->
                    <section class="bg-primary rounded-2xl p-5 text-surface shadow-md relative overflow-hidden">
                        <div class="absolute top-0 right-0 opacity-10 pointer-events-none">
                            <i data-lucide="fingerprint" class="w-40 h-40 md:w-56 md:h-56 -mt-6 -mr-6"></i>
                        </div>
                        <div class="relative z-10">
                            <p class="text-xs md:text-sm text-surface/80 mb-0.5"><?= $date_now ?></p>
                            
                            <h2 class="text-3xl md:text-4xl font-bold mb-1 tracking-tight">
                                <span id="realtimeClock">00:00</span> 
                                <span class="text-sm md:text-base font-normal text-surface/80">WIB</span>
                            </h2>
                            
                            <p class="text-[10px] md:text-xs text-surface/90 mb-6 bg-surface/20 inline-block px-2.5 py-1 rounded-md font-medium">
                                <i data-lucide="clock" class="w-3 h-3 inline-block -mt-0.5 mr-0.5"></i> Shift: <?= $shift_start ?> - <?= $shift_end ?>
                            </p>
                            
                            <div class="flex gap-3 md:w-2/3 lg:w-1/2">
                                <button onclick="openAttendance('in')" class="flex-1 bg-surface text-primary text-sm font-semibold py-3 md:py-3.5 rounded-xl flex items-center justify-center gap-2 hover:bg-gray-50 transition shadow-sm active:scale-95">
                                    <i data-lucide="log-in" class="w-4 h-4"></i> Masuk
                                </button>
                                <button onclick="openAttendance('out')" class="flex-1 bg-transparent border-2 border-surface/30 text-surface text-sm font-semibold py-3 md:py-3.5 rounded-xl flex items-center justify-center gap-2 hover:bg-surface/10 transition active:scale-95">
                                    <i data-lucide="log-out" class="w-4 h-4"></i> Pulang
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- RIWAYAT ABSENSI -->
                    <section>
                        <div class="flex justify-between items-end mb-3 px-1">
                            <h3 class="text-xs md:text-sm font-semibold text-gray-800">Riwayat Kehadiran</h3>
                            <a href="#" class="text-[11px] text-primary font-medium hover:underline">Lihat Semua</a>
                        </div>
                        
                        <div class="space-y-3">
                            <?php if(empty($attendances)): ?>
                                <div class="bg-surface border border-gray-100 rounded-xl p-4 text-center">
                                    <p class="text-xs text-gray-400">Belum ada riwayat absensi.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($attendances as $att): 
                                    $time_in = isset($att['clock_in_time']) ? date('H:i', strtotime($att['clock_in_time'])) : '--:--';
                                    $time_out = isset($att['clock_out_time']) ? date('H:i', strtotime($att['clock_out_time'])) : '--:--';
                                    
                                    // Logika Status
                                    $status_label = "Tepat Waktu";
                                    $status_color = "text-success bg-success/10";
                                    if(isset($att['clock_in_status']) && $att['clock_in_status'] === 'late') {
                                        $status_label = "Terlambat";
                                        $status_color = "text-failed bg-failed/10";
                                    }
                                ?>
                                <div class="bg-surface border border-gray-100 rounded-xl p-3 md:p-4 shadow-sm flex items-center justify-between transition hover:border-gray-200 cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <div class="w-11 h-11 rounded-xl bg-gray-50 flex flex-col items-center justify-center border border-gray-100 shrink-0">
                                            <span class="text-[9px] text-gray-500 font-medium uppercase"><?= date('M', strtotime($att['date'])) ?></span>
                                            <span class="text-xs font-bold text-gray-800"><?= date('d', strtotime($att['date'])) ?></span>
                                        </div>
                                        <div>
                                            <p class="text-[11px] text-gray-500 font-medium"><?= $hari[date('w', strtotime($att['date']))] ?></p>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="text-xs font-semibold text-gray-800 flex items-center gap-1">
                                                    <i data-lucide="log-in" class="w-3 h-3 text-success"></i> <?= $time_in ?>
                                                </span>
                                                <span class="text-gray-300 text-[10px]">-</span>
                                                <span class="text-xs font-semibold text-gray-800 flex items-center gap-1">
                                                    <i data-lucide="log-out" class="w-3 h-3 text-failed"></i> <?= $time_out ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 <?= $status_color ?> text-[9px] font-bold rounded-md"><?= $status_label ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <!-- Kanan (1 Kolom di Desktop): RECENT ACTIVITY -->
                <div class="md:col-span-1 mt-5 md:mt-0">
                    <section class="bg-surface md:p-5 md:rounded-2xl md:shadow-sm md:border md:border-gray-100 h-full">
                        <div class="flex justify-between items-end mb-3 px-1 md:px-0">
                            <h3 class="text-xs md:text-sm font-semibold text-gray-800">Aktivitas & Info</h3>
                        </div>
                        
                        <div class="space-y-2.5">
                            <?php if(empty($activities)): ?>
                                <div class="p-3 text-center text-xs text-gray-400 border border-dashed border-gray-200 rounded-xl">
                                    Tidak ada aktivitas terbaru.
                                </div>
                            <?php else: ?>
                                <?php foreach($activities as $act): 
                                    $time_diff = time() - strtotime($act['created_at']);
                                    $time_str = "baru saja";
                                    if($time_diff > 86400) $time_str = floor($time_diff/86400) . "h lalu";
                                    elseif($time_diff > 3600) $time_str = floor($time_diff/3600) . "j lalu";
                                    elseif($time_diff > 60) $time_str = floor($time_diff/60) . "m lalu";
                                ?>
                                <div class="flex items-start gap-3 p-3 md:p-0 md:py-3 bg-gray-50 md:bg-transparent rounded-xl md:border-none border border-gray-100 md:border-b md:rounded-none md:border-gray-50 cursor-pointer hover:bg-gray-50 transition">
                                    <div class="w-9 h-9 rounded-full <?= $act['icon_bg_class'] ?> <?= $act['icon_color_class'] ?> flex items-center justify-center shrink-0 mt-0.5">
                                        <i data-lucide="<?= $act['icon'] ?>" class="w-4.5 h-4.5"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start gap-2">
                                            <h4 class="text-[11px] font-semibold text-gray-800 truncate"><?= htmlspecialchars($act['title']) ?></h4>
                                            <span class="text-[9px] text-gray-400 font-medium shrink-0"><?= $time_str ?></span>
                                        </div>
                                        <p class="text-[10px] text-gray-500 mt-0.5 leading-relaxed line-clamp-2"><?= htmlspecialchars($act['description']) ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

            </div>
        </div>
    </main>
</div>

<!-- ================= MODAL ABSENSI (GPS & KAMERA) ================= -->
<div id="attendanceModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <!-- Overlay (Sekarang ditambahkan onclick untuk close) -->
    <div id="attendanceOverlay" onclick="closeAttendance()" class="absolute inset-0 bg-gray-900/80 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    
    <!-- Modal Container -->
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none p-4">
        <div id="attendanceCard" class="bg-surface w-full max-w-sm rounded-3xl shadow-2xl transform scale-95 opacity-0 transition-all duration-300 pointer-events-auto relative overflow-hidden flex flex-col">
            
            <!-- Header Modal -->
            <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <div>
                    <h3 class="text-sm font-bold text-gray-800" id="attendanceTitle">Absen Masuk</h3>
                    <p class="text-[10px] text-gray-500 font-medium" id="attendanceTime">--:-- WIB</p>
                </div>
                <button onclick="closeAttendance()" class="text-gray-400 hover:text-failed hover:bg-failed/10 transition p-1.5 rounded-full">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Area Feed Kamera & Status Lokasi -->
            <div class="relative bg-black aspect-[3/4] w-full flex items-center justify-center overflow-hidden">
                <video id="cameraStream" autoplay playsinline class="w-full h-full object-cover transform scale-x-[-1]"></video>
                
                <!-- Overlay Status Pencarian GPS -->
                <div class="absolute bottom-4 left-4 right-4 bg-black/50 backdrop-blur-md rounded-xl p-3 border border-white/10">
                    <div class="flex items-start gap-2">
                        <i data-lucide="map-pin" class="w-4 h-4 text-primary mt-0.5 shrink-0"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] text-white/70 font-medium mb-0.5">Status Lokasi</p>
                            <p id="locationStatus" class="text-xs text-white font-semibold leading-tight animate-pulse">Mencari kordinat GPS...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Area Tombol Aksi -->
            <div class="p-5">
                <input type="hidden" id="attType" value="">
                <input type="hidden" id="attLat" value="">
                <input type="hidden" id="attLng" value="">
                <button id="btnCapture" onclick="captureAndSubmit()" disabled class="w-full bg-primary text-surface py-3.5 rounded-xl text-sm font-bold flex justify-center items-center gap-2 hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="camera" class="w-5 h-5"></i> Ambil Foto & Absen
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

<!-- Panggil Komponen Toast Secara Global -->
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    function updateRealtimeClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const clockElement = document.getElementById('realtimeClock');
        if (clockElement) {
            clockElement.textContent = hours + ':' + minutes;
        }
    }
    setInterval(updateRealtimeClock, 1000);
    updateRealtimeClock();

    // ==========================================
    // LOGIKA ABSENSI (GPS, Geofence & KAMERA)
    // ==========================================
    let cameraStream = null;
    const attModal = document.getElementById('attendanceModal');
    const attOverlay = document.getElementById('attendanceOverlay');
    const attCard = document.getElementById('attendanceCard');
    
    const video = document.getElementById('cameraStream');
    const locStatus = document.getElementById('locationStatus');
    const btnCapture = document.getElementById('btnCapture');
    
    const attType = document.getElementById('attType');
    const attLat = document.getElementById('attLat');
    const attLng = document.getElementById('attLng');
    const attTitle = document.getElementById('attendanceTitle');
    const attTime = document.getElementById('attendanceTime');

    // Variabel Settings dari PHP
    const isGeofenceEnabled = <?= $is_geofence_enabled ?>;
    const officeLat = <?= $office_lat !== null ? $office_lat : 'null' ?>;
    const officeLng = <?= $office_lng !== null ? $office_lng : 'null' ?>;
    const officeRadius = <?= $office_radius ?>;

    document.body.appendChild(attModal);

    // Rumus Haversine untuk menghitung jarak dalam meter
    function getDistanceFromLatLonInM(lat1, lon1, lat2, lon2) {
        var R = 6371000; // Radius bumi dalam meter
        var dLat = deg2rad(lat2-lat1);
        var dLon = deg2rad(lon2-lon1); 
        var a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
            Math.sin(dLon/2) * Math.sin(dLon/2); 
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
        return R * c; 
    }
    function deg2rad(deg) { return deg * (Math.PI/180); }

    function openAttendance(type) {
        attType.value = type;
        attTitle.innerText = type === 'in' ? 'Absen Masuk' : 'Absen Pulang';
        
        const now = new Date();
        attTime.innerText = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0') + ' WIB';

        locStatus.innerText = "Mencari lokasi akurat...";
        locStatus.className = "text-xs text-white font-semibold leading-tight animate-pulse";
        btnCapture.disabled = true;
        attLat.value = "";
        attLng.value = "";

        attModal.classList.remove('hidden');
        setTimeout(() => {
            attOverlay.classList.remove('opacity-0');
            attCard.classList.remove('scale-95', 'opacity-0');
            attCard.classList.add('scale-100', 'opacity-100');
        }, 10);

        // Minta Akses Kamera Depan
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
            .then(function(stream) {
                cameraStream = stream;
                video.srcObject = stream;
            })
            .catch(function(err) {
                if(typeof showToast === 'function') showToast('Akses kamera ditolak atau tidak tersedia.', 'error');
                locStatus.innerText = "Kamera tidak aktif!";
                locStatus.classList.remove('animate-pulse');
                locStatus.classList.add('text-failed');
            });
        }

        // Minta Akses GPS dan Eksekusi Geofencing
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const currentLat = position.coords.latitude;
                    const currentLng = position.coords.longitude;
                    
                    attLat.value = currentLat;
                    attLng.value = currentLng;
                    
                    // Logika Geofencing (Settings)
                    if (isGeofenceEnabled === 1 && officeLat !== null && officeLng !== null) {
                        const distance = getDistanceFromLatLonInM(currentLat, currentLng, officeLat, officeLng);
                        const distanceRounded = Math.round(distance);
                        
                        if (distance > officeRadius) {
                            locStatus.innerHTML = `Di luar radius! Jarak: ${distanceRounded}m (Maks: ${officeRadius}m)`;
                            locStatus.classList.remove('animate-pulse');
                            locStatus.classList.add('text-failed');
                            btnCapture.disabled = true; // Kunci tombol jika di luar radius
                        } else {
                            locStatus.innerHTML = `Dalam radius kantor. Jarak: ${distanceRounded}m`;
                            locStatus.classList.remove('animate-pulse');
                            locStatus.classList.add('text-success');
                            btnCapture.disabled = false; // Buka kunci tombol
                        }
                    } else {
                        // Jika Mode Bebas (WFA) atau Karyawan belum diset lokasinya
                        locStatus.innerHTML = `GPS Terkunci: Mode Absen WFA`;
                        locStatus.classList.remove('animate-pulse');
                        locStatus.classList.add('text-success');
                        btnCapture.disabled = false;
                    }
                },
                function(error) {
                    locStatus.innerText = "Gagal mendapatkan lokasi GPS";
                    locStatus.classList.remove('animate-pulse');
                    locStatus.classList.add('text-failed');
                    if(typeof showToast === 'function') showToast('Akses lokasi (GPS) ditolak.', 'error');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        } else {
            locStatus.innerText = "GPS tidak didukung browser ini";
            locStatus.classList.remove('animate-pulse');
            locStatus.classList.add('text-failed');
        }
    }

    function closeAttendance() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            video.srcObject = null;
        }
        attOverlay.classList.add('opacity-0');
        attCard.classList.remove('scale-100', 'opacity-100');
        attCard.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { attModal.classList.add('hidden'); }, 300);
    }

    // Tambahkan event listener cadangan untuk overlay jika onclick HTML gagal
    if (attOverlay) {
        attOverlay.addEventListener('click', closeAttendance);
    }

    function captureAndSubmit() {
        btnCapture.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Memproses...';
        btnCapture.disabled = true;
        lucide.createIcons();

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        const base64Image = canvas.toDataURL('image/jpeg', 0.8);

        const formData = new FormData();
        formData.append('type', attType.value); 
        formData.append('lat', attLat.value);
        formData.append('lng', attLng.value);
        formData.append('image', base64Image);

        // MOCKUP PROSES AJAX
        setTimeout(() => {
            closeAttendance();
            if(typeof showToast === 'function') {
                showToast(`Absen ${attType.value === 'in' ? 'Masuk' : 'Pulang'} berhasil dicatat!`, 'success');
            }
            btnCapture.innerHTML = '<i data-lucide="camera" class="w-5 h-5"></i> Ambil Foto & Absen';
        }, 1500);
    }

    // ==========================================
    // LOGIKA MODAL REQUEST BAWAAN
    // ==========================================
    document.addEventListener('DOMContentLoaded', () => {
        const requestBtn = document.getElementById('requestBtn');
        const bottomSheet = document.getElementById('requestBottomSheet');
        const overlay = document.getElementById('requestOverlay');
        const sheet = document.getElementById('requestSheet');

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

<!-- Load Script PWA -->
<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>