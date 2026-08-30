<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// Logika Akses: Admin, Superadmin, dan HR bisa lihat semua, sisanya hanya milik sendiri
$role_name_session = strtolower($_SESSION['role'] ?? '');
$can_view_all = in_array($role_name_session, ['admin', 'superadmin', 'hr']);

// ==============================================================================
// PENANGANAN AJAX (VIEW DETAIL ABSENSI)
// ==============================================================================
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['ajax_action'] === 'view') {
            $id = $_GET['id'];
            
            // Validasi kepemilikan data jika bukan admin
            $query = "SELECT a.*, u.name as employee_name FROM attendances a LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ? AND a.tenant_id = ?";
            $params = [$id, $tenant_id];
            
            if (!$can_view_all) {
                $query .= " AND a.user_id = ?";
                $params[] = $user_id;
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan atau akses ditolak.']);
            }
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

// MENGAMBIL DATA ABSENSI UNTUK DATATABLES
if ($can_view_all) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as employee_name, u.avatar, d.name as department_name 
        FROM attendances a 
        LEFT JOIN users u ON a.user_id = u.id 
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        WHERE a.tenant_id = ? 
        ORDER BY a.date DESC, a.clock_in_time DESC
    ");
    $stmt->execute([$tenant_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as employee_name, u.avatar, d.name as department_name 
        FROM attendances a 
        LEFT JOIN users u ON a.user_id = u.id 
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        WHERE a.tenant_id = ? AND a.user_id = ? 
        ORDER BY a.date DESC, a.clock_in_time DESC
    ");
    $stmt->execute([$tenant_id, $user_id]);
}
$attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/components/head.php';

// LOAD JQUERY & DATATABLES
echo '<script src="https://code.jquery.com/jquery-3.7.0.js"></script>';
echo '<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>';
?>

<!-- STYLE CUSTOM UNTUK MENYATUKAN DATATABLES DENGAN DESAIN TAILWIND -->
<style>
    /* Hapus border default tabel datatables */
    table.dataTable.no-footer {
        border-bottom: none !important;
    }
    table.dataTable thead th {
        border-bottom: 1px solid #f3f4f6 !important;
        padding: 0.75rem 1rem !important;
        background-color: #f9fafb;
    }
    table.dataTable tbody td {
        border-bottom: 1px solid #f3f4f6 !important;
        padding: 0.75rem 1rem !important;
        vertical-align: middle;
    }
    /* Layout bawah (Info & Pagination) */
    .dataTables_wrapper .bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1rem;
        background-color: #ffffff;
        border-top: 1px solid #f3f4f6;
    }
    .dataTables_info {
        font-size: 0.65rem !important;
        color: #6b7280 !important;
        padding-top: 0 !important;
    }
    .dataTables_paginate {
        display: flex;
        gap: 0.25rem;
        padding-top: 0 !important;
    }
    .dataTables_paginate .paginate_button {
        padding: 0.25rem 0.6rem !important;
        border-radius: 0.5rem !important;
        font-size: 0.7rem !important;
        font-weight: 600 !important;
        background: white !important;
        border: 1px solid #e5e7eb !important;
        color: #4b5563 !important;
        cursor: pointer;
        margin-left: 0.25rem !important;
    }
    .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) {
        background: #f9fafb !important;
        color: #111827 !important;
    }
    .dataTables_paginate .paginate_button.current {
        background: #ea3800 !important; /* Warna Primary */
        color: white !important;
        border-color: #ea3800 !important;
    }
    .dataTables_paginate .paginate_button.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
</style>

<?php require_once __DIR__ . '/components/sidebar.php'; ?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2 relative z-0">
            
            <div class="flex justify-between items-center px-1">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Log Absensi</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Rekapitulasi riwayat kehadiran karyawan</p>
                </div>
            </div>

            <!-- Form Pencarian (Terikat dengan DataTables via JS) -->
            <div class="relative z-0">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="dtSearchInput" placeholder="Cari nama karyawan, tanggal, atau status..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
            </div>

            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-3 space-y-5 md:space-y-6">
                    
                    <section class="relative z-0">
                        <div class="bg-surface md:border border-gray-100 rounded-2xl md:shadow-sm overflow-hidden pb-2 md:pb-0">
                            <!-- Container Scroll Kanan-Kiri untuk Mobile -->
                            <div class="overflow-x-auto">
                                <table id="attendanceTable" class="w-full text-left whitespace-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Karyawan</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Tanggal</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Jam Masuk</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Jam Pulang</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($attendances as $att): 
                                            $safe_name = htmlspecialchars($att['employee_name'] ?? 'Unknown');
                                            $dept_name = htmlspecialchars($att['department_name'] ?? '-');
                                            $avatar = !empty($att['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($att['avatar']) : "https://api.dicebear.com/9.x/shadows/svg?seed=" . urlencode($safe_name);
                                            
                                            // Format Waktu
                                            $date_display = date('d M Y', strtotime($att['date']));
                                            $time_in = !empty($att['clock_in_time']) ? date('H:i', strtotime($att['clock_in_time'])) : '--:--';
                                            $time_out = !empty($att['clock_out_time']) ? date('H:i', strtotime($att['clock_out_time'])) : '--:--';
                                            
                                            // Status Badge
                                            $status_in = strtolower($att['clock_in_status'] ?? '');
                                            if($status_in === 'late' || $status_in === 'terlambat') {
                                                $badge_color = 'bg-failed/10 text-failed border-failed/20';
                                                $badge_label = 'Terlambat';
                                            } else {
                                                $badge_color = 'bg-success/10 text-success border-success/20';
                                                $badge_label = 'On Time';
                                            }
                                        ?>
                                            <tr class="hover:bg-gray-50/50 transition-colors group">
                                                <!-- Kolom Karyawan -->
                                                <td>
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-9 h-9 rounded-full bg-gray-100 shrink-0 overflow-hidden border border-gray-200">
                                                            <img src="<?= $avatar ?>" class="w-full h-full object-cover" alt="Avatar">
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <h4 class="text-xs font-bold text-gray-800"><?= $safe_name ?></h4>
                                                            <p class="text-[10px] text-gray-500 font-medium truncate mt-0.5"><?= $dept_name ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                
                                                <!-- Kolom Tanggal -->
                                                <td>
                                                    <span class="text-xs font-semibold text-gray-700"><?= $date_display ?></span>
                                                </td>
                                                
                                                <!-- Kolom Masuk -->
                                                <td>
                                                    <div class="flex flex-col gap-1 items-start">
                                                        <span class="text-xs font-bold text-gray-800 flex items-center gap-1.5"><i data-lucide="log-in" class="w-3.5 h-3.5 text-success"></i> <?= $time_in ?></span>
                                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border <?= $badge_color ?>"><?= $badge_label ?></span>
                                                    </div>
                                                </td>
                                                
                                                <!-- Kolom Pulang -->
                                                <td>
                                                    <div class="flex flex-col gap-1 items-start">
                                                        <span class="text-xs font-bold text-gray-800 flex items-center gap-1.5"><i data-lucide="log-out" class="w-3.5 h-3.5 text-failed"></i> <?= $time_out ?></span>
                                                        <?php if(!empty($att['work_hours'])): ?>
                                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border bg-gray-100 text-gray-500 border-gray-200"><i data-lucide="clock" class="w-2.5 h-2.5 inline-block -mt-0.5"></i> <?= $att['work_hours'] ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                
                                                <!-- Kolom Aksi -->
                                                <td class="text-right">
                                                    <button onclick="openViewModal(<?= $att['id'] ?>)" class="p-2 bg-gray-50 text-gray-600 rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 hover:bg-primary hover:text-white transition shadow-sm ml-auto active:scale-95">
                                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> <span class="hidden md:inline">Detail</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                </div>
            </div>
        </div>
    </main>
</div>

<!-- ================= HYBRID MODAL/BOTTOM SHEET (VIEW DETAIL) ================= -->
<div id="crudModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="crudOverlay" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="crudCard" class="bg-surface w-full max-w-sm rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh]">
            
            <div class="pt-5 pb-2 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeCrud()">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full"></div>
            </div>
            
            <button onclick="closeCrud()" class="hidden md:flex absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full z-10">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            
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

<!-- Komponen Toast Global -->
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    // ==========================================
    // INIT DATATABLES JS & BIND SEARCH INPUT
    // ==========================================
    $(document).ready(function() {
        const table = $('#attendanceTable').DataTable({
            "dom": 't<"bottom"ip>', // Hanya Tabel, Info, dan Pagination (Menyembunyikan default search box)
            "pageLength": 10,
            "ordering": false,
            "language": {
                "emptyTable": "Belum ada riwayat absensi",
                "info": "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                "infoEmpty": "Menampilkan 0 s/d 0 dari 0 data",
                "paginate": {
                    "previous": "Sebelumnya",
                    "next": "Selanjutnya"
                }
            }
        });

        // Hubungkan custom search input dengan filter DataTables
        $('#dtSearchInput').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Re-render lucide icons setiap kali halaman diganti atau di search
        table.on('draw', function() {
            lucide.createIcons();
        });
    });

    // ==========================================
    // HYBRID MODAL AJAX (VIEW DETAIL PHOTO)
    // ==========================================
    const crudModal = document.getElementById('crudModal');
    const crudOverlay = document.getElementById('crudOverlay');
    const crudCard = document.getElementById('crudCard');
    const crudContent = document.getElementById('crudContent');
    const baseUrl = '<?= $base_url ?? '' ?>';
    
    document.body.appendChild(crudModal);

    window.openViewModal = function(id) {
        crudContent.innerHTML = `
            <div class="flex justify-center py-10">
                <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-primary"></i>
            </div>
        `;
        
        crudModal.classList.remove('hidden');
        lucide.createIcons();
        
        setTimeout(() => {
            crudOverlay.classList.remove('opacity-0');
            crudCard.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
            crudCard.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100');
        }, 10);

        fetch(`?ajax_action=view&id=${id}`)
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    const data = res.data;
                    
                    const formatTime = (timeStr) => {
                        if(!timeStr) return '--:--';
                        const d = new Date(`1970-01-01T${timeStr}Z`);
                        return d.toISOString().substr(11, 5); 
                    };

                    const inImg = data.clock_in_image ? `${baseUrl}/assets/img/attendances/${data.clock_in_image}` : 'https://placehold.co/400x500/f9fafb/9ca3af?text=No+Photo';
                    const outImg = data.clock_out_image ? `${baseUrl}/assets/img/attendances/${data.clock_out_image}` : 'https://placehold.co/400x500/f9fafb/9ca3af?text=Belum+Pulang';
                    
                    const inTime = data.clock_in_time ? data.clock_in_time.substring(11, 16) : '--:--';
                    const outTime = data.clock_out_time ? data.clock_out_time.substring(11, 16) : '--:--';

                    crudContent.innerHTML = `
                        <div class="text-center mb-6 mt-2 md:mt-0">
                            <h3 class="text-base font-bold text-gray-800">Detail Validasi Absensi</h3>
                            <p class="text-xs text-primary font-medium mt-0.5">${data.employee_name} <span class="text-gray-400 mx-1">•</span> ${data.date}</p>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Foto Masuk -->
                            <div class="flex flex-col gap-2">
                                <div class="bg-gray-50 border border-gray-200 rounded-2xl overflow-hidden aspect-[3/4] relative">
                                    <img src="${inImg}" class="w-full h-full object-cover" alt="Clock In">
                                </div>
                                <div class="text-center">
                                    <p class="text-[10px] font-bold text-gray-500 mb-0.5 uppercase">Absen Masuk</p>
                                    <span class="text-xs font-bold text-gray-800 flex items-center justify-center gap-1.5"><i data-lucide="log-in" class="w-3.5 h-3.5 text-success"></i> ${inTime}</span>
                                </div>
                            </div>
                            
                            <!-- Foto Pulang -->
                            <div class="flex flex-col gap-2">
                                <div class="bg-gray-50 border border-gray-200 rounded-2xl overflow-hidden aspect-[3/4] relative">
                                    <img src="${outImg}" class="w-full h-full object-cover" alt="Clock Out">
                                </div>
                                <div class="text-center">
                                    <p class="text-[10px] font-bold text-gray-500 mb-0.5 uppercase">Absen Pulang</p>
                                    <span class="text-xs font-bold text-gray-800 flex items-center justify-center gap-1.5"><i data-lucide="log-out" class="w-3.5 h-3.5 text-failed"></i> ${outTime}</span>
                                </div>
                            </div>
                        </div>
                        
                        <button onclick="closeCrud()" class="w-full mt-8 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition active:scale-95">Tutup Detail</button>
                    `;
                    lucide.createIcons();
                } else {
                    crudContent.innerHTML = `<div class="text-center text-failed py-6 text-sm font-semibold">${res.message}</div>`;
                }
            })
            .catch(() => {
                crudContent.innerHTML = `<div class="text-center text-failed py-6 text-sm font-semibold">Gagal memuat data dari server.</div>`;
            });
    }

    window.closeCrud = function() {
        crudOverlay.classList.add('opacity-0');
        crudCard.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100'); 
        crudCard.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0'); 
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