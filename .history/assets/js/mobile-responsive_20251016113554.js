/**
 * Mobile Responsive JavaScript for JeepniGo Dashboards
 * Handles mobile menu toggle, overlay, and responsive behaviors
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get elements
    const menuBtn = document.querySelector('.mobile-nav-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const sidebarLinks = sidebar ? sidebar.querySelectorAll('.nav-link, .sidebar-link') : [];
    
    // Verify elements exist
    if (!menuBtn || !sidebar) {
        console.warn('Mobile navigation elements not found');
        return;
    }

    // Toggle sidebar on button click
    menuBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('mobile-active');
        
        if (overlay) {
            overlay.classList.toggle('active');
        }
        
        // Change icon
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('mobile-active')) {
            icon.className = 'bi bi-x-lg';
            document.body.style.overflow = 'hidden';
        } else {
            icon.className = 'bi bi-list';
            document.body.style.overflow = '';
        }
    });

    // Close sidebar when clicking overlay
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-active');
            overlay.classList.remove('active');
            const icon = menuBtn.querySelector('i');
            if (icon) icon.className = 'bi bi-list';
            document.body.style.overflow = '';
        });
    }

    // Close sidebar when clicking a link on mobile
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 991) {
                sidebar.classList.remove('mobile-active');
                if (overlay) overlay.classList.remove('active');
                const icon = menuBtn.querySelector('i');
                if (icon) icon.className = 'bi bi-list';
                document.body.style.overflow = '';
            }
        });
    });

    // Close sidebar on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('mobile-active')) {
            sidebar.classList.remove('mobile-active');
            if (overlay) overlay.classList.remove('active');
            const icon = menuBtn.querySelector('i');
            if (icon) icon.className = 'bi bi-list';
            document.body.style.overflow = '';
        }
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 991) {
                sidebar.classList.remove('mobile-active');
                if (overlay) overlay.classList.remove('active');
                const icon = menuBtn.querySelector('i');
                if (icon) icon.className = 'bi bi-list';
                document.body.style.overflow = '';
            }
        }, 250);
    });
}

    // Make tables responsive by wrapping them
    const tables = document.querySelectorAll('table:not(.table-responsive table)');
    tables.forEach(table => {
        if (!table.closest('.table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });

    // Add touch feedback for buttons on mobile
    if ('ontouchstart' in window) {
        const buttons = document.querySelectorAll('.btn, .nav-link, .card');
        buttons.forEach(btn => {
            btn.addEventListener('touchstart', function() {
                this.style.opacity = '0.7';
            });
            btn.addEventListener('touchend', function() {
                this.style.opacity = '1';
            });
        });
    }

    // Prevent zoom on input focus (iOS Safari fix)
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        if (!input.style.fontSize || parseInt(input.style.fontSize) < 16) {
            input.style.fontSize = '16px';
        }
    });

    // Add swipe gesture to close sidebar
    let touchStartX = 0;
    let touchEndX = 0;

    if (sidebar) {
        sidebar.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        });

        sidebar.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });

        function handleSwipe() {
            const swipeThreshold = 50;
            const swipeDistance = touchStartX - touchEndX;

            // Swipe left to close
            if (swipeDistance > swipeThreshold && sidebar.classList.contains('mobile-active')) {
                sidebar.classList.remove('mobile-active');
                overlay.classList.remove('active');
                toggleBtn.querySelector('i').className = 'bi bi-list';
            }
        }
    }

    // Lazy load images for better performance
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                imageObserver.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));

    // Add pull-to-refresh indicator (optional)
    let startY = 0;
    let pulling = false;

    window.addEventListener('touchstart', function(e) {
        startY = e.touches[0].pageY;
    });

    window.addEventListener('touchmove', function(e) {
        const y = e.touches[0].pageY;
        // Only consider pulling when at the top of the page
        if (document.documentElement.scrollTop === 0 && y > startY + 100) {
            pulling = true;
        }
    });

    window.addEventListener('touchend', function() {
        if (pulling) {
            // You can add refresh logic here
            pulling = false;
        }
    });

    // Add viewport height fix for mobile browsers
    function setViewportHeight() {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }

    setViewportHeight();
    window.addEventListener('resize', setViewportHeight);

    // Optimize scroll performance
    let ticking = false;
    let lastScrollY = window.scrollY;

    window.addEventListener('scroll', function() {
        lastScrollY = window.scrollY;

        if (!ticking) {
            window.requestAnimationFrame(function() {
                // Add your scroll-based animations here
                ticking = false;
            });

            ticking = true;
        }
    });

    console.log('✅ Mobile responsive JavaScript loaded successfully');
});

// Service Worker registration for PWA capability (optional)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        // Uncomment to enable PWA
        // navigator.serviceWorker.register('/service-worker.js')
        //     .then(reg => console.log('Service Worker registered'))
        //     .catch(err => console.log('Service Worker registration failed'));
    });
}

// Add iOS standalone mode detection
function isIOSStandalone() {
    return ('standalone' in window.navigator) && (window.navigator.standalone);
}

if (isIOSStandalone()) {
    document.body.classList.add('ios-standalone');
}

// Prevent double-tap zoom on iOS
let lastTouchEnd = 0;
document.addEventListener('touchend', function(event) {
    const now = Date.now();
    if (now - lastTouchEnd <= 300) {
        event.preventDefault();
    }
    lastTouchEnd = now;
}, false);

