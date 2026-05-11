/**
 * Responsive JavaScript for Training Laboratory Schedule System
 * Handles dynamic responsive behavior across all devices
 */

// Debounce function for performance optimization
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Device detection
const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
const isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

// Responsive Table Handler
document.addEventListener('DOMContentLoaded', function() {
    
    // ===================================
    // RESPONSIVE TABLES
    // ===================================
    function makeTablesResponsive() {
        const tables = document.querySelectorAll('.schedule-table');
        
        tables.forEach(table => {
            const headers = table.querySelectorAll('thead th');
            const rows = table.querySelectorAll('tbody tr');
            
            // Add data-label attributes for mobile card view
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    if (headers[index]) {
                        cell.setAttribute('data-label', headers[index].textContent.trim());
                    }
                });
            });
            
            // Convert to mobile card layout on small screens
            function updateTableLayout() {
                const screenWidth = window.innerWidth;
                
                if (screenWidth <= 576) {
                    // Mobile card layout
                    table.classList.add('schedule-table-mobile');
                    table.parentElement.classList.add('mobile-view');
                } else {
                    // Standard table layout
                    table.classList.remove('schedule-table-mobile');
                    table.parentElement.classList.remove('mobile-view');
                }
            }
            
            updateTableLayout();
            window.addEventListener('resize', debounce(updateTableLayout, 250));
        });
    }
    
    makeTablesResponsive();
    
    // ===================================
    // VIEWPORT HEIGHT FIX (Mobile Browsers)
    // ===================================
    function setViewportHeight() {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }
    
    setViewportHeight();
    window.addEventListener('resize', debounce(setViewportHeight, 250));
    
    // ===================================
    // SMOOTH SCROLLING
    // ===================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                    
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
    
    // ===================================
    // TOUCH FEEDBACK
    // ===================================
    if (isTouch) {
        document.querySelectorAll('.btn, .dashboard-nav a, header nav a').forEach(element => {
            element.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.97)';
                this.style.transition = 'transform 0.1s ease';
            }, { passive: true });
            
            element.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.style.transform = '';
                }, 100);
            }, { passive: true });
        });
    }
    
    // ===================================
    // AUTO-HIDE ALERTS
    // ===================================
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Add close button
        const closeBtn = document.createElement('span');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = 'float: right; font-size: 1.5rem; font-weight: bold; cursor: pointer; margin-left: 1rem;';
        closeBtn.onclick = function() {
            hideAlert(alert);
        };
        alert.insertBefore(closeBtn, alert.firstChild);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            hideAlert(alert);
        }, 5000);
    });
    
    function hideAlert(alert) {
        alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            alert.style.display = 'none';
        }, 500);
    }
    
    // ===================================
    // FORM SUBMISSION PROTECTION
    // ===================================
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                const originalText = submitBtn.textContent || submitBtn.value;
                
                if (submitBtn.tagName === 'BUTTON') {
                    submitBtn.innerHTML = '<span style="opacity: 0.7;">Processing...</span>';
                } else {
                    submitBtn.value = 'Processing...';
                }
                
                submitBtn.style.opacity = '0.6';
                submitBtn.style.cursor = 'not-allowed';
                
                // Re-enable after 3 seconds as fallback
                setTimeout(() => {
                    submitBtn.disabled = false;
                    if (submitBtn.tagName === 'BUTTON') {
                        submitBtn.textContent = originalText;
                    } else {
                        submitBtn.value = originalText;
                    }
                    submitBtn.style.opacity = '';
                    submitBtn.style.cursor = '';
                }, 3000);
            }
        });
    });
    
    // ===================================
    // RESPONSIVE NAVIGATION
    // ===================================
    function optimizeNavigation() {
        const nav = document.querySelector('header nav');
        if (!nav) return;
        
        const screenWidth = window.innerWidth;
        
        if (screenWidth <= 768) {
            nav.style.flexDirection = 'column';
            nav.style.width = '100%';
        } else {
            nav.style.flexDirection = 'row';
            nav.style.width = 'auto';
        }
    }
    
    optimizeNavigation();
    window.addEventListener('resize', debounce(optimizeNavigation, 250));
    
    // ===================================
    // IMAGE LAZY LOADING
    // ===================================
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // ===================================
    // FOCUS MANAGEMENT
    // ===================================
    // Show focus outline only for keyboard navigation
    let isUsingKeyboard = false;
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
            isUsingKeyboard = true;
            document.body.classList.add('keyboard-nav');
        }
    });
    
    document.addEventListener('mousedown', () => {
        isUsingKeyboard = false;
        document.body.classList.remove('keyboard-nav');
    });
    
    // ===================================
    // DYNAMIC FONT SIZING
    // ===================================
    function adjustFontSize() {
        const screenWidth = window.innerWidth;
        const root = document.documentElement;
        
        if (screenWidth <= 375) {
            root.style.fontSize = '14px';
        } else if (screenWidth <= 576) {
            root.style.fontSize = '15px';
        } else if (screenWidth <= 768) {
            root.style.fontSize = '15px';
        } else if (screenWidth <= 992) {
            root.style.fontSize = '15px';
        } else {
            root.style.fontSize = '16px';
        }
    }
    
    adjustFontSize();
    window.addEventListener('resize', debounce(adjustFontSize, 250));
    
    // ===================================
    // PERFORMANCE MONITORING
    // ===================================
    if (window.performance && window.performance.timing) {
        window.addEventListener('load', () => {
            const loadTime = window.performance.timing.domContentLoadedEventEnd - 
                           window.performance.timing.navigationStart;
            console.log(`Page loaded in ${loadTime}ms`);
        });
    }
    
});

// ===================================
// ORIENTATION CHANGE HANDLER
// ===================================
window.addEventListener('orientationchange', function() {
    // Adjust layout after orientation change
    setTimeout(() => {
        setViewportHeight();
        window.scrollTo(0, 0);
        
        // Trigger resize event for other handlers
        window.dispatchEvent(new Event('resize'));
    }, 100);
});

// ===================================
// NETWORK STATUS MONITORING
// ===================================
let isOnline = navigator.onLine;

window.addEventListener('online', function() {
    isOnline = true;
    showNetworkStatus('Connection restored', 'success');
    console.log('✓ Connection restored');
});

window.addEventListener('offline', function() {
    isOnline = false;
    showNetworkStatus('You are currently offline', 'error');
    console.log('✗ Connection lost');
});

function showNetworkStatus(message, type) {
    // Remove existing network alerts
    const existingAlert = document.querySelector('.network-alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} network-alert`;
    alert.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 300px; animation: slideIn 0.3s ease;';
    alert.textContent = message;
    
    document.body.appendChild(alert);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        alert.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => alert.remove(), 300);
    }, 3000);
}

// ===================================
// SCROLL BEHAVIOR
// ===================================
let lastScrollTop = 0;
const header = document.querySelector('header');

window.addEventListener('scroll', debounce(function() {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    // Add shadow to header when scrolled
    if (scrollTop > 10) {
        header?.classList.add('scrolled');
    } else {
        header?.classList.remove('scrolled');
    }
    
    lastScrollTop = scrollTop;
}, 100), { passive: true });

// ===================================
// BACK TO TOP BUTTON
// ===================================
function createBackToTopButton() {
    const button = document.createElement('button');
    button.innerHTML = '↑';
    button.className = 'back-to-top';
    button.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2e7d32 0%, #388e3c 100%);
        color: white;
        border: none;
        font-size: 24px;
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
    `;
    
    button.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    document.body.appendChild(button);
    
    // Show/hide based on scroll position
    window.addEventListener('scroll', debounce(() => {
        if (window.pageYOffset > 300) {
            button.style.opacity = '1';
            button.style.visibility = 'visible';
        } else {
            button.style.opacity = '0';
            button.style.visibility = 'hidden';
        }
    }, 100), { passive: true });
}

// Only add back-to-top on pages with enough content
if (document.body.scrollHeight > window.innerHeight * 2) {
    createBackToTopButton();
}

// ===================================
// ACCESSIBILITY ENHANCEMENTS
// ===================================
// Skip to main content link
function addSkipLink() {
    const skipLink = document.createElement('a');
    skipLink.href = '#main';
    skipLink.textContent = 'Skip to main content';
    skipLink.className = 'skip-link';
    skipLink.style.cssText = `
        position: absolute;
        top: -40px;
        left: 0;
        background: #2e7d32;
        color: white;
        padding: 8px 16px;
        text-decoration: none;
        z-index: 10000;
        transition: top 0.3s ease;
    `;
    
    skipLink.addEventListener('focus', () => {
        skipLink.style.top = '0';
    });
    
    skipLink.addEventListener('blur', () => {
        skipLink.style.top = '-40px';
    });
    
    document.body.insertBefore(skipLink, document.body.firstChild);
    
    // Add id to main if not present
    const main = document.querySelector('main');
    if (main && !main.id) {
        main.id = 'main';
    }
}

addSkipLink();

// ===================================
// ANIMATIONS
// ===================================
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .keyboard-nav *:focus {
        outline: 3px solid #66bb6a !important;
        outline-offset: 2px !important;
    }
    
    .back-to-top:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(46, 125, 50, 0.4);
    }
    
    .back-to-top:active {
        transform: translateY(-1px);
    }
    
    /* Mobile-specific improvements */
    @media (max-width: 576px) {
        .back-to-top {
            bottom: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
            font-size: 20px;
        }
        
        .table-responsive.mobile-view::after {
            content: '← Swipe to see more →';
            display: block;
            text-align: center;
            color: #66bb6a;
            font-size: 0.85rem;
            margin-top: 0.5rem;
            font-style: italic;
        }
    }
`;
document.head.appendChild(style);

// ===================================
// UTILITY FUNCTIONS
// ===================================
// Get current breakpoint
function getCurrentBreakpoint() {
    const width = window.innerWidth;
    if (width < 576) return 'xs';
    if (width < 768) return 'sm';
    if (width < 992) return 'md';
    if (width < 1200) return 'lg';
    return 'xl';
}

// Check if element is in viewport
function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

// Export utilities for use in other scripts
window.ResponsiveUtils = {
    isMobile,
    isTouch,
    getCurrentBreakpoint,
    isInViewport,
    debounce
};

// Log initialization
console.log('✓ Responsive system initialized');
console.log(`Device: ${isMobile ? 'Mobile' : 'Desktop'}`);
console.log(`Touch: ${isTouch ? 'Yes' : 'No'}`);
console.log(`Breakpoint: ${getCurrentBreakpoint()}`);
console.log(`Online: ${isOnline ? 'Yes' : 'No'}`);
