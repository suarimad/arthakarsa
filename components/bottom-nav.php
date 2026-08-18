<nav class="md:hidden fixed bottom-0 w-full bg-surface border-t border-gray-200 pb-safe shadow-[0_-4px_20px_rgba(0,0,0,0.04)] z-40">
    <div class="flex justify-between items-center px-5 py-2.5 relative">
        <!-- 1. Home (Aktif) -->
        <a href="<?= $base_url ?>/." class="flex flex-col items-center gap-1 text-primary w-12">
            <i data-lucide="home" class="w-5 h-5"></i>
            <span class="text-[9px] font-semibold">Home</span>
        </a>
        
        <!-- 2. Employees -->
        <a href="<?= $base_url ?>/employee" class="flex flex-col items-center gap-1 text-gray-400 hover:text-primary transition w-12">
            <i data-lucide="users" class="w-5 h-5"></i>
            <span class="text-[9px] font-medium">Employees</span>
        </a>
        
        <!-- 3. Request (FAB Tengah Mengambang) -->
        <div class="w-12 flex justify-center">
            <!-- ID requestBtn akan memicu Bottom Sheet di index.php -->
            <button id="requestBtn" class="absolute -top-5 flex items-center justify-center w-12 h-12 bg-primary text-surface rounded-full shadow-lg hover:bg-accent transition transform hover:scale-105 border-4 border-surface">
                <i data-lucide="plus" class="w-6 h-6"></i>
            </button>
            <span class="text-[9px] font-medium text-gray-400 mt-6 pt-1">Request</span>
        </div>
        
        <!-- 4. Menu -->
        <a href="#" class="flex flex-col items-center gap-1 text-gray-400 hover:text-primary transition w-12">
            <i data-lucide="layout-grid" class="w-5 h-5"></i>
            <span class="text-[9px] font-medium">Menu</span>
        </a>
        
        <!-- 5. Profile -->
        <a href="#" class="flex flex-col items-center gap-1 text-gray-400 hover:text-primary transition w-12">
            <i data-lucide="user" class="w-5 h-5"></i>
            <span class="text-[9px] font-medium">Profile</span>
        </a>
    </div>
</nav>