<?php
// 1. TANGKAP SESSION DARI PHP
$toast_msg = '';
$toast_type = 'info';

// Pastikan session sudah berjalan (berjaga-jaga jika file ini dipanggil tanpa session_start)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['toast_msg']) && $_SESSION['toast_msg'] !== '') {
    $toast_msg = $_SESSION['toast_msg'];
    $toast_type = $_SESSION['toast_type'] ?? 'info';
    
    // Langsung hapus session agar tidak muncul lagi saat halaman di-refresh
    unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
}
?>

<!-- 2. HTML KOMPONEN TOAST -->
<!-- pointer-events-none: agar toast tidak menghalangi klik jika menutupi elemen lain -->
<div id="toast" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-[100] transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium w-[92vw] md:w-auto md:max-w-md pointer-events-none">
    <i id="toastIcon" class="w-4 h-4 shrink-0"></i>
    <span id="toastMsg" class="flex-1 truncate"></span>
</div>

<!-- 3. JAVASCRIPT GLOBAL UNTUK TOAST -->
<script>
    // Deklarasi fungsi secara global (window) agar bisa diakses dari script AJAX di file lain
    if (typeof window.showToast !== 'function') {
        window.showToast = function(msg, type) {
            const toast = document.getElementById('toast');
            const msgEl = document.getElementById('toastMsg');
            const iconEl = document.getElementById('toastIcon');

            if (!toast || !msgEl || !iconEl) return;

            msgEl.textContent = msg;
            
            // Reset class untuk berjaga-jaga
            toast.className = 'fixed top-5 left-1/2 transform -translate-x-1/2 z-[100] transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium w-[92vw] md:w-auto md:max-w-md pointer-events-none';

            // Set warna & ikon berdasarkan tipe
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
            
            // Render ulang ikon Lucide jika library tersedia
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Animasi turun (muncul)
            setTimeout(() => toast.classList.remove('opacity-0', '-translate-y-full'), 100);
            
            // Animasi naik (hilang) setelah 4 detik
            setTimeout(() => toast.classList.add('opacity-0', '-translate-y-full'), 4000);
        };
    }

    // Eksekusi otomatis jika ada pesan dari Session PHP (seperti saat redirect dari logout.php)
    // Menggunakan fungsi jalan-langsung (IIFE) agar tidak bergantung pada event DOMContentLoaded
    (function() {
        const phpMsg = <?= json_encode($toast_msg) ?>;
        const phpType = <?= json_encode($toast_type) ?>;
        
        if (phpMsg && phpMsg.trim() !== '') {
            // Beri jeda sangat kecil agar browser selesai merender kerangka HTML sebelum menariknya turun
            setTimeout(() => {
                window.showToast(phpMsg, phpType);
            }, 150);
        }
    })();
</script>