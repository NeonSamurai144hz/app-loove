let pusher;
document.addEventListener('DOMContentLoaded', () => {
    pusher = new Pusher('51d11d1b9e8bac345975', {
        cluster: 'eu',
        encrypted: true
    });

    document.querySelectorAll('[data-route]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const page = e.target.getAttribute('data-route');
            router.navigate(page);
        });
    });
});