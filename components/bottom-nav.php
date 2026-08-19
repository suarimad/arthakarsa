<?php 
// Deteksi halaman saat ini (tanpa ekstensi .php)
$current_page = basename($_SERVER['PHP_SELF'], '.php'); 
// Jika halaman kosong (root), set sebagai index
if ($current_page == '') $current_page = 'index';
?>
<nav class="md:hidden fixed bottom-0 w-full bg-surface border-t border-gray-200 pb-safe shadow-[0_-4px_20px_rgba(0,0,0,0.04)] z-40">
    <div class="flex justify-between items-center px-5 py-4 relative">
        <!-- 1. Home -->
        <a href="<?= $base_url ?>/index" class="flex flex-col items-center gap-1.5 w-12 <?= ($current_page == 'index') ? 'text-primary' : 'text-gray-400 hover:text-primary transition' ?>">
            <i data-lucide="home" class="w-5 h-5"></i>
            <span class="text-[9px] <?= ($current_page == 'index') ? 'font-semibold' : 'font-medium' ?>">Home</span>
        </a>
        
        <!-- 2. Employees -->
        <a href="<?= $base_url ?>/employee" class="flex flex-col items-center gap-1.5 w-12 <?= ($current_page == 'employee') ? 'text-primary' : 'text-gray-400 hover:text-primary transition' ?>">
            <i data-lucide="users" class="w-5 h-5"></i>
            <span class="text-[9px] <?= ($current_page == 'employee') ? 'font-semibold' : 'font-medium' ?>">Employees</span>
        </a>
        
        <!-- 3. Request (FAB Tengah Mengambang) -->
        <div class="w-12 flex justify-center">
            <!-- ID requestBtn akan memicu Bottom Sheet di index.php/employee.php -->
            <button id="requestBtn" class="absolute -top-5 flex items-center justify-center w-14 h-14 bg-primary text-surface rounded-full shadow-lg hover:bg-accent transition transform hover:scale-105 border-[5px] border-surface">
                <i data-lucide="plus" class="w-6 h-6"></i>
            </button>
            <span class="text-[9px] font-medium text-gray-400 mt-7 pt-0.5">Request</span>
        </div>
        
        <!-- 4. Menu -->
        <a href="menu" class="flex flex-col items-center gap-1.5 w-12 <?= ($current_page == 'menu') ? 'text-primary' : 'text-gray-400 hover:text-primary transition' ?>">
            <i data-lucide="layout-grid" class="w-5 h-5"></i>
            <span class="text-[9px] <?= ($current_page == 'menu') ? 'font-semibold' : 'font-medium' ?>">Menu</span>
        </a>
        
        <!-- 5. Profile -->
        <a href="profile" class="flex flex-col items-center gap-1.5 w-12 <?= ($current_page == 'profile') ? 'text-primary' : 'text-gray-400 hover:text-primary transition' ?>">
            <i data-lucide="user" class="w-5 h-5"></i>
            <span class="text-[9px] <?= ($current_page == 'profile') ? 'font-semibold' : 'font-medium' ?>">Profile</span>
        </a>
    </div>
</nav>