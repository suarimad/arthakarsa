<?php 
// Pastikan hanya role superadmin yang merender komponen ini
$debugger_role_id = $_SESSION['role_id'] ?? null;
$debugger_role_name = strtolower($_SESSION['role'] ?? '');

if ($debugger_role_id == 1 || $debugger_role_name === 'superadmin'): 
?>
<!-- ================= MODAL / BOTTOM SHEET MINI DEBUGGER ================= -->
<div id="globalDebuggerModal" class="fixed inset-0 hidden" style="z-index: 999999;">
    <!-- Overlay -->
    <div id="globalDebuggerOverlay" onclick="closeGlobalDebugger()" class="absolute inset-0 bg-gray-900/60 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    
    <!-- Modal Container -->
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="globalDebuggerCard" class="bg-[#1e1e1e] w-full md:max-w-3xl lg:max-w-4xl rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[85vh] md:max-h-[80vh] border-t-4 <?= !empty($debug_error) ? 'border-red-500' : 'border-green-500' ?>">
            
            <!-- Handle Geser untuk Mobile -->
            <div class="pt-4 pb-3 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeGlobalDebugger()">
                <div class="w-12 h-1.5 bg-gray-600 rounded-full"></div>
            </div>

            <!-- Header -->
            <div class="flex justify-between items-center bg-[#252526] px-6 py-4 md:rounded-t-3xl shrink-0 border-b border-gray-700">
                <div class="flex items-center gap-2">
                    <i data-lucide="terminal" class="w-5 h-5 text-green-400"></i>
                    <h3 class="text-sm font-bold text-white tracking-wider">Mini Debugger</h3>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-xs <?= !empty($debug_error) ? 'text-red-500 font-bold' : 'text-green-500' ?>">
                        <?= !empty($debug_error) ? 'Exception Caught' : 'OK (No Error)' ?>
                    </span>
                    <button onclick="closeGlobalDebugger()" class="text-gray-400 hover:text-white transition p-1 rounded-full outline-none">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <!-- Area Konten (Scrollable) -->
            <div class="overflow-y-auto p-5 md:p-6 space-y-5 flex-1 overscroll-y-contain font-mono text-[11px] md:text-xs leading-relaxed text-gray-300" style="scrollbar-width: thin; scrollbar-color: #4b5563 #1e1e1e;">
                <?php if (!empty($debug_error)): ?>
                    <div class="bg-red-900/20 border border-red-900/50 p-4 rounded-xl">
                        <p class="text-red-500 font-bold mb-1.5 text-xs">[ERROR MESSAGE]</p>
                        <p class="text-red-300 break-words"><?= htmlspecialchars($debug_error['message']) ?></p>
                    </div>
                    <div class="bg-black/20 border border-gray-800 p-4 rounded-xl">
                        <p class="text-yellow-500 font-bold mb-1.5 text-xs">[LOCATION]</p>
                        <p>File: <span class="text-yellow-200"><?= htmlspecialchars($debug_error['file']) ?></span></p>
                        <p>Line: <span class="text-yellow-200"><?= htmlspecialchars($debug_error['line']) ?></span></p>
                    </div>
                    <div class="bg-black/20 border border-gray-800 p-4 rounded-xl">
                        <p class="text-blue-400 font-bold mb-1.5 text-xs">[STACK TRACE]</p>
                        <pre class="whitespace-pre-wrap break-words text-blue-200 bg-black/40 p-4 rounded-lg overflow-x-auto"><?= htmlspecialchars($debug_error['trace']) ?></pre>
                    </div>
                <?php else: ?>
                    <div class="h-full min-h-[40vh] flex flex-col items-center justify-center opacity-50">
                        <i data-lucide="check-circle-2" class="w-16 h-16 text-green-500 mb-4"></i>
                        <p class="text-center text-sm">Tidak ada error SQL atau Exception yang tertangkap.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    window.openGlobalDebugger = function() {
        const m = document.getElementById('globalDebuggerModal');
        const o = document.getElementById('globalDebuggerOverlay');
        const c = document.getElementById('globalDebuggerCard');
        
        if(!m) return;
        
        // Pindahkan elemen ini ke body jika belum ada di sana agar tidak terjebak z-index container
        if (m.parentNode !== document.body) {
            document.body.appendChild(m);
        }

        m.classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
        
        setTimeout(() => {
            if(o) o.classList.remove('opacity-0');
            if(c) {
                c.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
                c.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100');
            }
        }, 10);
    }

    window.closeGlobalDebugger = function() {
        const m = document.getElementById('globalDebuggerModal');
        const o = document.getElementById('globalDebuggerOverlay');
        const c = document.getElementById('globalDebuggerCard');
        
        if(!m) return;
        if(o) o.classList.add('opacity-0');
        if(c) {
            c.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100');
            c.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0');
        }
        
        setTimeout(() => { 
            m.classList.add('hidden'); 
        }, 300);
    }

    // Auto-buka debugger jika ada error setelah halaman direload
    <?php if (!empty($debug_error)): ?>
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => { openGlobalDebugger(); }, 500);
        });
    <?php endif; ?>
</script>
<?php endif; ?>