<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// ==============================================================================
// LOGIKA HAK AKSES (ROLE-BASED)
// ==============================================================================
$role_name_session = strtolower($_SESSION['role'] ?? '');

// Hak Akses Manajemen Gaji: Hanya Superadmin, Admin, HR, dan Finance
$can_manage_salary = in_array($role_name_session, ['superadmin', 'admin', 'hr', 'finance']);

// Tendang user jika tidak punya akses
if (!$can_manage_salary) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke pengaturan gaji.";
    $_SESSION['toast_type'] = "error";
    header("Location: " . ($base_url ?? '') . "/index");
    exit;
}

// ==============================================================================
// PENANGANAN AJAX (AMBIL DATA & SIMPAN DATA)
// ==============================================================================
if (isset($_REQUEST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_REQUEST['ajax_action'];

        // AJAX 1: GET SALARY DATA FOR EDIT
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_salary') {
            $target_user_id = $_GET['user_id'];
            
            // Fix: Menambahkan p.name as position_name yang sebelumnya tertinggal
            $stmt = $pdo->prepare("
                SELECT u.name, u.avatar, d.name as department_name, p.name as position_name,
                       COALESCE(us.basic_salary, 0) as basic_salary, 
                       COALESCE(us.overtime_rate, 0) as overtime_rate, 
                       COALESCE(us.payment_type, 'monthly') as payment_type, 
                       us.bank_name, us.bank_account
                FROM users u 
                LEFT JOIN positions p ON u.position_id = p.id
                LEFT JOIN departments d ON p.department_id = d.id
                LEFT JOIN user_salaries us ON u.id = us.user_id
                WHERE u.id = ? AND u.tenant_id = ?
            ");
            $stmt->execute([$target_user_id, $tenant_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Karyawan tidak ditemukan.']);
            }
            exit;
        }

        // AJAX 2: SAVE SALARY DATA
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_salary') {
            $target_user_id = $_POST['target_user_id'];
            $basic_salary = (float)($_POST['basic_salary'] ?? 0);
            $overtime_rate = (float)($_POST['overtime_rate'] ?? 0);
            $payment_type = $_POST['payment_type'] ?? 'monthly';
            $bank_name = trim($_POST['bank_name'] ?? '');
            $bank_account = trim($_POST['bank_account'] ?? '');

            // Cek apakah data gaji karyawan ini sudah ada di database
            $stmtCheck = $pdo->prepare("SELECT id FROM user_salaries WHERE user_id = ? AND tenant_id = ?");
            $stmtCheck->execute([$target_user_id, $tenant_id]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                // UPDATE
                $stmtUpdate = $pdo->prepare("
                    UPDATE user_salaries 
                    SET basic_salary = ?, overtime_rate = ?, payment_type = ?, bank_name = ?, bank_account = ? 
                    WHERE user_id = ? AND tenant_id = ?
                ");
                $stmtUpdate->execute([$basic_salary, $overtime_rate, $payment_type, $bank_name, $bank_account, $target_user_id, $tenant_id]);
            } else {
                // INSERT
                $stmtInsert = $pdo->prepare("
                    INSERT INTO user_salaries (tenant_id, user_id, basic_salary, overtime_rate, payment_type, bank_name, bank_account) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtInsert->execute([$tenant_id, $target_user_id, $basic_salary, $overtime_rate, $payment_type, $bank_name, $bank_account]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Pengaturan gaji karyawan berhasil disimpan.']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
// ==============================================================================

// MENGAMBIL DATA SEMUA KARYAWAN & GAJINYA UNTUK DATATABLES
$query = "
    SELECT u.id as user_id, u.name as employee_name, u.avatar, d.name as department_name, p.name as position_name,
           COALESCE(us.basic_salary, 0) as basic_salary, 
           COALESCE(us.overtime_rate, 0) as overtime_rate,
           us.bank_name, us.bank_account, us.payment_type
    FROM users u 
    LEFT JOIN positions p ON u.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN user_salaries us ON u.id = us.user_id
    WHERE u.tenant_id = ? AND u.deleted_at IS NULL
    ORDER BY u.name ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute([$tenant_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/components/head.php';

// LOAD JQUERY & DATATABLES (Fix versi file minified & tambah CSS dt)
echo '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">';
echo '<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>';
echo '<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>';
?>

<!-- STYLE CUSTOM UNTUK MENYATUKAN DATATABLES DENGAN DESAIN TAILWIND & FIX TOAST -->
<style>
    table.dataTable.no-footer { border-bottom: none !important; }
    table.dataTable thead th { border-bottom: 1px solid #f3f4f6 !important; padding: 0.75rem 1rem !important; background-color: #f9fafb; background-image: none !important;}
    table.dataTable tbody td { border-bottom: 1px solid #f3f4f6 !important; padding: 0.75rem 1rem !important; vertical-align: middle; }
    .dataTables_wrapper .bottom { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1rem; background-color: #ffffff; border-top: 1px solid #f3f4f6; }
    .dataTables_info { font-size: 0.65rem !important; color: #6b7280 !important; padding-top: 0 !important; }
    .dataTables_paginate { display: flex; gap: 0.25rem; padding-top: 0 !important; }
    .dataTables_paginate .paginate_button { padding: 0.25rem 0.6rem !important; border-radius: 0.5rem !important; font-size: 0.7rem !important; font-weight: 600 !important; background: white !important; border: 1px solid #e5e7eb !important; color: #4b5563 !important; cursor: pointer; margin-left: 0.25rem !important; }
    .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) { background: #f9fafb !important; color: #111827 !important; }
    .dataTables_paginate .paginate_button.current { background: #ea3800 !important; color: white !important; border-color: #ea3800 !important; }
    .dataTables_paginate .paginate_button.disabled { opacity: 0.5; cursor: not-allowed; }
    
    div[id*="toast"], div[class*="toast"], #toast-container { z-index: 999999 !important; }
    
    /* Chrome, Safari, Edge, Opera hilangkan arrow input number */
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    /* Firefox */
    input[type=number] { -moz-appearance: textfield; }
</style>

<?php require_once __DIR__ . '/components/sidebar.php'; ?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2 relative z-0">
            
            <div class="flex justify-between items-center px-1">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Pengaturan Gaji</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Kelola gaji pokok, tarif lembur, dan info bank karyawan</p>
                </div>
            </div>

            <!-- Form Pencarian -->
            <div class="relative z-0">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="dtSearchInput" placeholder="Cari nama karyawan, jabatan, atau bank..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
            </div>

            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-3 space-y-5 md:space-y-6">
                    
                    <section class="relative z-0">
                        <div class="bg-surface md:border border-gray-100 rounded-2xl md:shadow-sm overflow-hidden pb-2 md:pb-0">
                            <div class="overflow-x-auto">
                                <table id="salaryTable" class="w-full text-left whitespace-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Karyawan</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Gaji Pokok</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Tarif Lembur / Jam</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Info Bank</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($employees as $emp): 
                                            $safe_name = htmlspecialchars($emp['employee_name'] ?? 'Unknown');
                                            
                                            // Fix: Penanganan nilai null pada departemen & posisi
                                            $d_name = $emp['department_name'] ?? 'Tanpa Departemen';
                                            $p_name = $emp['position_name'] ?? 'Tanpa Posisi';
                                            $dept_pos = htmlspecialchars($d_name . " • " . $p_name);
                                            
                                            $avatar = !empty($emp['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($emp['avatar']) : "https://api.dicebear.com/9.x/pixel-art/svg?seed=" . urlencode($safe_name);
                                            
                                            // Format Uang
                                            $basic_str = "Rp " . number_format($emp['basic_salary'], 0, ',', '.');
                                            $overtime_str = "Rp " . number_format($emp['overtime_rate'], 0, ',', '.');
                                            
                                            // Status Set/Belum Set
                                            $is_set = $emp['basic_salary'] > 0;
                                            $basic_color = $is_set ? 'text-gray-800' : 'text-failed';
                                            
                                            // Info Bank
                                            $bank_info = (!empty($emp['bank_name']) && !empty($emp['bank_account'])) 
                                                ? htmlspecialchars($emp['bank_name']) . " - " . htmlspecialchars($emp['bank_account'])
                                                : '<span class="text-failed italic">Belum diatur</span>';
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
                                                            <p class="text-[10px] text-gray-500 font-medium truncate mt-0.5"><?= $dept_pos ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                
                                                <!-- Kolom Gaji Pokok -->
                                                <td class="text-right">
                                                    <span class="text-xs font-bold <?= $basic_color ?>"><?= $is_set ? $basic_str : 'Belum Diatur' ?></span>
                                                </td>

                                                <!-- Kolom Tarif Lembur -->
                                                <td class="text-right">
                                                    <span class="text-xs font-semibold text-gray-600"><?= $overtime_str ?></span>
                                                </td>
                                                
                                                <!-- Kolom Info Bank -->
                                                <td>
                                                    <span class="text-[10px] font-semibold text-gray-600"><?= $bank_info ?></span>
                                                </td>
                                                
                                                <!-- Kolom Aksi -->
                                                <td class="text-right">
                                                    <button onclick="openEditModal(<?= $emp['user_id'] ?>)" class="p-2 bg-primary/10 text-primary rounded-xl text-xs font-semibold inline-flex items-center justify-center hover:bg-primary hover:text-white transition shadow-sm active:scale-95" title="Atur Gaji">
                                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> <span class="ml-1.5 hidden md:inline">Atur</span>
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

<!-- ================= HYBRID MODAL/BOTTOM SHEET (EDIT SALARY) ================= -->
<div id="crudModal" class="fixed inset-0 hidden" style="z-index: 99998;">
    <div id="crudOverlay" onclick="closeCrud()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="crudCard" class="bg-surface w-full md:max-w-md rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh]">
            
            <div class="pt-5 pb-2 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeCrud()">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full"></div>
            </div>
            
            <button onclick="closeCrud()" class="hidden md:flex absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full z-10">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            
            <div class="px-6 pb-8 md:p-8 overflow-y-auto">
                <div class="text-center mb-6 mt-2 md:mt-0">
                    <h3 class="text-base md:text-lg font-bold text-gray-800">Pengaturan Gaji & Rekening</h3>
                    <p id="modalEmployeeName" class="text-xs text-primary font-medium mt-0.5">Memuat...</p>
                </div>
                
                <form id="salaryForm">
                    <input type="hidden" name="target_user_id" id="target_user_id">
                    
                    <div class="space-y-4">
                        <!-- Tipe Pembayaran -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Tipe Pembayaran</label>
                            <div class="relative">
                                <select name="payment_type" id="payment_type" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 appearance-none font-medium cursor-pointer">
                                    <option value="monthly">Bulanan (Monthly)</option>
                                    <option value="weekly">Mingguan (Weekly)</option>
                                    <option value="daily">Harian (Daily)</option>
                                </select>
                                <i data-lucide="chevron-down" class="w-4 h-4 absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- Gaji Pokok -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Gaji Pokok (Basic Salary)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500 text-xs font-bold">Rp</span>
                                <input type="number" name="basic_salary" id="basic_salary" required min="0" class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 font-bold" placeholder="0">
                            </div>
                        </div>

                        <!-- Tarif Lembur -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Tarif Lembur per Jam</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500 text-xs font-bold">Rp</span>
                                <input type="number" name="overtime_rate" id="overtime_rate" required min="0" class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 font-bold" placeholder="0">
                            </div>
                            <p class="text-[9px] text-gray-400 mt-1">Biarkan 0 jika lembur tidak dibayar uang.</p>
                        </div>

                        <!-- Info Bank -->
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nama Bank</label>
                                <input type="text" name="bank_name" id="bank_name" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Misal: BCA">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nomor Rekening</label>
                                <input type="number" name="bank_account" id="bank_account" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="0123456789">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-8 border-t border-gray-100 pt-6">
                        <button type="button" onclick="closeCrud()" class="w-1/3 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition active:scale-95">Batal</button>
                        <button type="submit" id="btnSaveSalary" class="flex-1 py-3 bg-primary text-white rounded-xl text-sm font-bold hover:bg-primary/90 transition shadow-sm active:scale-95 flex justify-center items-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    // INIT DATATABLES
    $(document).ready(function() {
        const table = $('#salaryTable').DataTable({
            "dom": 't<"bottom"ip>', 
            "pageLength": 10,
            "ordering": false,
            "language": {
                "emptyTable": "Belum ada data karyawan",
                "info": "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                "infoEmpty": "Menampilkan 0 s/d 0 dari 0 data",
                "paginate": { "previous": "Sebelumnya", "next": "Selanjutnya" }
            }
        });

        $('#dtSearchInput').on('keyup', function() { table.search(this.value).draw(); });
        table.on('draw', function() { lucide.createIcons(); });
    });

    // ==========================================
    // LOGIKA MODAL AJAX & SAVE
    // ==========================================
    const crudModal = document.getElementById('crudModal');
    const crudOverlay = document.getElementById('crudOverlay');
    const crudCard = document.getElementById('crudCard');

    window.openEditModal = function(userId) {
        // Reset Form & Show Loading Text
        $('#salaryForm')[0].reset();
        $('#modalEmployeeName').text('Memuat data...');
        $('#target_user_id').val(userId);
        
        crudModal.classList.remove('hidden');
        
        setTimeout(() => {
            crudOverlay.classList.remove('opacity-0');
            crudCard.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
            crudCard.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100');
        }, 10);

        // Fetch Data Gaji menggunakan URL yang aman
        fetch(window.location.href + `?ajax_action=get_salary&user_id=${userId}`)
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    const data = res.data;
                    
                    // Fix: Penggabungan data departemen dan posisi di modal
                    const dName = data.department_name || 'Tanpa Departemen';
                    const pName = data.position_name || 'Tanpa Posisi';
                    
                    $('#modalEmployeeName').text(`${data.name} • ${dName} / ${pName}`);
                    
                    $('#payment_type').val(data.payment_type);
                    $('#basic_salary').val(data.basic_salary);
                    $('#overtime_rate').val(data.overtime_rate);
                    $('#bank_name').val(data.bank_name);
                    $('#bank_account').val(data.bank_account);
                } else {
                    if(typeof window.showToast === 'function') window.showToast(res.message, 'error');
                    closeCrud();
                }
            })
            .catch(() => {
                if(typeof window.showToast === 'function') window.showToast("Gagal memuat data dari server.", 'error');
                closeCrud();
            });
    }

    window.closeCrud = function() {
        crudOverlay.classList.add('opacity-0');
        crudCard.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100'); 
        crudCard.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0'); 
        setTimeout(() => { crudModal.classList.add('hidden'); }, 300);
    }

    // FORM SUBMIT AJAX
    $('#salaryForm').on('submit', function(e) {
        e.preventDefault();
        
        const btnSave = $('#btnSaveSalary');
        const originalContent = btnSave.html();
        
        btnSave.prop('disabled', true).html('<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Menyimpan...');
        lucide.createIcons();

        const formData = new FormData(this);
        formData.append('ajax_action', 'save_salary');

        fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                if(typeof window.showToast === 'function') window.showToast(res.message, 'success');
                btnSave.html('<i data-lucide="check-circle" class="w-4 h-4"></i> Berhasil');
                lucide.createIcons();
                
                setTimeout(() => {
                    window.location.reload(); 
                }, 1000);
            } else {
                if(typeof window.showToast === 'function') window.showToast(res.message, 'error');
                btnSave.prop('disabled', false).html(originalContent);
            }
        }).catch(() => {
            if(typeof window.showToast === 'function') window.showToast("Gagal terhubung ke server", 'error');
            btnSave.prop('disabled', false).html(originalContent);
        });
    });

</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>