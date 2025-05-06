document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-route]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const page = e.target.getAttribute('data-route');
            router.navigate(page);
        });
    });
});