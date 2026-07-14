document.addEventListener('DOMContentLoaded', () => {
    const nav = document.getElementById('site-nav');
    const menuToggle = document.getElementById('menuToggle');
    const navLinks = document.getElementById('navLinks');
    const revealItems = document.querySelectorAll('.reveal');

    const applyScrollReveal = () => {
        if (!revealItems.length) {
            return;
        }

        if (!window.IntersectionObserver) {
            revealItems.forEach((item) => item.classList.add('is-revealed'));
            return;
        }

        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                const delay = Number(entry.target.getAttribute('data-delay') || 0);
                setTimeout(() => entry.target.classList.add('is-revealed'), delay);
                io.unobserve(entry.target);
            });
        }, { threshold: 0.2 });

        revealItems.forEach((item) => io.observe(item));
    };

    const STICKY_ENTER = 28;
    const STICKY_EXIT = 12;
    const FADE_DISTANCE = 160;

    const handleScroll = () => {
        if (!nav) {
            return;
        }

        const currentY = window.scrollY || 0;
        const progress = Math.min(Math.max(currentY / FADE_DISTANCE, 0), 1);

        nav.style.setProperty('--nav-progress', progress.toFixed(3));

        const isSticky = nav.classList.contains('is-sticky');
        if (!isSticky && currentY > STICKY_ENTER) {
            nav.classList.add('is-sticky');
            return;
        }

        if (isSticky && currentY < STICKY_EXIT) {
            nav.classList.remove('is-sticky');
        }
    };

    let ticking = false;
    const onScroll = () => {
        if (!ticking) {
            requestAnimationFrame(() => {
                handleScroll();
                ticking = false;
            });
            ticking = true;
        }
    };

    if (nav) {
        handleScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    applyScrollReveal();

    if (menuToggle && navLinks) {
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.addEventListener('click', () => {
            const isOpen = navLinks.classList.toggle('show');
            menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            menuToggle.setAttribute('aria-label', isOpen ? 'Đóng menu' : 'Mở menu');
        });

        navLinks.querySelectorAll('a').forEach((anchor) => {
            anchor.addEventListener('click', () => {
                if (window.innerWidth <= 960 && navLinks.classList.contains('show')) {
                    navLinks.classList.remove('show');
                    menuToggle.setAttribute('aria-expanded', 'false');
                    menuToggle.setAttribute('aria-label', 'Mở menu');
                }
            });
        });
    }
});
