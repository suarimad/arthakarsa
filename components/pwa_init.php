<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('<?= $base_url ?>/service-worker.js')
                .then((registration) => {
                    console.log('ServiceWorker PWA terdaftar dengan scope: ', registration.scope);
                }, (err) => {
                    console.log('Registrasi ServiceWorker gagal: ', err);
                });
        });
    }
</script>