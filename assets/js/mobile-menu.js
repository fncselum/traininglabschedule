/**
 * Mobile Menu Handler
 * Handles responsive navigation menu for mobile devices
 */

document.addEventListener('DOMContentLoaded', function() {
    const header = document.querySelector('header');
    const nav = document.querySelector('header nav');
    
    if (!header || !nav) return;
    
    // Create mobile menu toggle button
    function createMobileMenuToggle() {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'mobile-menu-toggle';
        toggleBtn.setAttribute('aria-label', 'Toggle navigation menu');
        toggleBtn.setAttribute('aria-expanded', 'false');
        toggleBtn.innerHTML = `
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        `;
        
        // Add styles
        toggleBtn.style.cssText = `
            display: none;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 8px;
            z-index: 1001;
        `;
        
        // Insert before nav
        header.querySelector('.container').insertBefore(toggleBtn, nav);
        
        return toggleBtn;
    }
    
    const toggleBtn = createMobileMenuToggle();
    
    // Toggle menu function
    function toggleMenu() {
        const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
        
        if (isExpanded) {
            closeMenu();
        } else {
            openMenu();
        }
    }
    
    function openMenu() {
        nav.classList.add('mobile-menu-open');
        toggleBtn.classList.add('active');
        toggleBtn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }
    
    function closeMenu() {
        nav.classList.remove('mobile-menu-open');
        toggleBtn.classList.remove('active');
        toggleBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }
    
    // Event listeners
    toggleBtn.addEventListener('click', toggleMenu);
    
    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!nav.contains(e.target) && !toggleBtn.contains(e.target)) {
                closeMenu();
            }
        }
    });
    
    // Close menu when clicking on a link
    nav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeMenu();
            }
        });
    });
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (window.innerWidth > 768) {
                closeMenu();
                toggleBtn.style.display = 'none';
            } else {
                toggleBtn.style.display = 'flex';
            }
        }, 250);
    });
    
    // Initial check
    if (window.innerWidth <= 768) {
        toggleBtn.style.display = 'flex';
    }
    
    // Add mobile menu styles
    const style = document.createElement('style');
    style.textContent = `
        .mobile-menu-toggle {
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 25px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            z-index: 1001;
            transition: transform 0.3s ease;
        }
        
        .mobile-menu-toggle:hover {
            transform: scale(1.1);
        }
        
        .hamburger-line {
            width: 100%;
            height: 3px;
            background: white;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .mobile-menu-toggle.active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }
        
        .mobile-menu-toggle.active .hamburger-line:nth-child(2) {
            opacity: 0;
        }
        
        .mobile-menu-toggle.active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }
        
        @media (max-width: 768px) {
            header .container {
                position: relative;
            }
            
            header nav {
                position: fixed;
                top: 0;
                right: -100%;
                width: 80%;
                max-width: 300px;
                height: 100vh;
                background: linear-gradient(135deg, #2e7d32 0%, #388e3c 100%);
                padding: 80px 20px 20px;
                transition: right 0.3s ease;
                box-shadow: -5px 0 15px rgba(0, 0, 0, 0.3);
                z-index: 1000;
                overflow-y: auto;
            }
            
            header nav.mobile-menu-open {
                right: 0;
            }
            
            header nav a,
            header nav span {
                display: block;
                width: 100%;
                margin: 0 0 15px 0 !important;
                padding: 12px 15px;
                text-align: left;
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.1);
            }
            
            header nav a:hover {
                background: rgba(255, 255, 255, 0.2);
            }
        }
        
        @media (min-width: 769px) {
            .mobile-menu-toggle {
                display: none !important;
            }
        }
    `;
    document.head.appendChild(style);
});
