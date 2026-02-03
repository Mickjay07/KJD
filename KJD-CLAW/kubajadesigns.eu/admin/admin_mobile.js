// Mobilní menu pro administraci
document.addEventListener('DOMContentLoaded', function() {
    // Přidání hamburger menu tlačítka pokud neexistuje
    if (!document.querySelector('.mobile-menu-toggle')) {
        const body = document.querySelector('body');
        const mobileToggle = document.createElement('button');
        mobileToggle.className = 'mobile-menu-toggle';
        mobileToggle.innerHTML = '<i class="fas fa-bars"></i> Menu';
        body.appendChild(mobileToggle);
        
        // Přidání overlay prvku
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        body.appendChild(overlay);
        
        // Funkcionalita pro otevření/zavření menu
        mobileToggle.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
        
        // Zavírání menu po kliknutí na overlay
        overlay.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
        
        // Zavírání menu po kliknutí na položku menu
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 991) {
                    const sidebar = document.querySelector('.sidebar');
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }
            });
        });
    }
}); 