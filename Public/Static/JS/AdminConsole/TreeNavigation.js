
(function() {
    'use strict';

    // ============================================
    // initialise on DOM ready
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        initDropdowns();
        initDate();
        initMobileNav();
    });



    // ============================================
    // mobile navigation toggle
    // ============================================
    function initMobileNav()
    {
        const toggle = document.getElementById('navToggle');
        const nav = document.getElementById('navPanel');
        const overlay = document.getElementById('navOverlay');

        if (!toggle || !nav) return;

        // toggle button click
        toggle.addEventListener('click', function() {
            const isOpen = nav.classList.contains('is-open');

            if (isOpen) {
                closeNav();
            } else {
                openNav();
            }
        });

        // overlay click closes nav
        if (overlay)
        {
            overlay.addEventListener('click', closeNav);
        }

        // close nav on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) {
                closeNav();
            }
        });

        // close nav when clicking an option (on mobile)
        const options = document.querySelectorAll('.nav-item--option');
        options.forEach(function(option) {
            option.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    closeNav();
                }
            });
        });

        function openNav()
        {
            nav.classList.add('is-open');
            toggle.classList.add('is-active');
            toggle.querySelector('.nav-toggle__icon').textContent = '✕';
            toggle.querySelector('.nav-toggle__text').textContent = 'Close';
            if (overlay) overlay.classList.add('is-visible');
            document.body.style.overflow = 'hidden'; // Prevent scroll
        }

        function closeNav()
        {
            nav.classList.remove('is-open');
            toggle.classList.remove('is-active');
            toggle.querySelector('.nav-toggle__icon').textContent = '☰';
            toggle.querySelector('.nav-toggle__text').textContent = 'Menu';
            if (overlay) overlay.classList.remove('is-visible');
            document.body.style.overflow = '';
        }
    }



    // ============================================
    // dropdown toggle functionality
    // ============================================
    function initDropdowns()
    {
        const dropdowns = document.querySelectorAll('.nav-item--dropdown');

        dropdowns.forEach(function(dropdown) {
            const toggle = dropdown.querySelector(':scope > .nav-item__toggle');

            if (toggle) {
                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleDropdown(dropdown);
                });
            }
        });
    }

    function toggleDropdown(dropdown)
    {
        const isOpen = dropdown.classList.contains('is-open');
        const expandIcon = dropdown.querySelector(':scope > .nav-item__toggle > .nav-item__expand-icon');

        if (isOpen)
        {
            // close
            dropdown.classList.remove('is-open');
            if (expandIcon) {
                expandIcon.textContent = '►';
            }
        }
        else
        {
            // open
            dropdown.classList.add('is-open');
            if (expandIcon) {
                expandIcon.textContent = '▼';
            }
        }
    }



    // ============================================
    // date display
    // ============================================
    function initDate()
    {
        const dateElement = document.getElementById('currentDate');

        if (dateElement) {
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            dateElement.textContent = new Date().toLocaleDateString('en-GB', options);
        }
    }

})();
