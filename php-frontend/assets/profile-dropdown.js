/**
 * Profile Dropdown Component - Refactored
 * Features:
 * - Keyboard navigation (Arrow keys, Home, End, Tab)
 * - Proper focus management
 * - Click outside to close
 * - Escape key to close
 * - Menu item selection and navigation
 * - Accessibility features (ARIA attributes)
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        triggerSelector: '.profile-trigger',
        menuSelector: '.profile-menu',
        menuItemSelector: '.profile-menu-item',
        dropdownArrowSelector: '.dropdown-arrow',
        focusOutlineClass: 'focus-visible'
    };

    // State
    let isMenuOpen = false;
    let currentFocusIndex = -1;
    let menuItems = [];

    // Get DOM elements
    const getTrigger = () => document.querySelector(CONFIG.triggerSelector);
    const getMenu = () => document.querySelector(CONFIG.menuSelector);
    const getMenuItems = () => Array.from(document.querySelectorAll(CONFIG.menuItemSelector));

    /**
     * Initialize the component
     */
    function init() {
        const trigger = getTrigger();
        const menu = getMenu();

        if (!trigger || !menu) {
            console.warn('Profile dropdown components not found');
            return;
        }

        // Update menu items list
        menuItems = getMenuItems();

        // Attach event listeners
        trigger.addEventListener('click', handleTriggerClick);
        menu.addEventListener('keydown', handleMenuKeydown);
        document.addEventListener('click', handleDocumentClick);
        document.addEventListener('keydown', handleEscapeKey);

        // Attach item click handlers
        menuItems.forEach((item, index) => {
            item.addEventListener('click', () => handleMenuItemClick(item, index));
            item.addEventListener('mouseenter', () => {
                currentFocusIndex = index;
                updateFocus();
            });
            item.addEventListener('mouseleave', () => {
                currentFocusIndex = -1;
                clearFocus();
            });
        });

        // Set initial state
        closeMenu();
    }

    /**
     * Handle trigger button click
     */
    function handleTriggerClick(event) {
        event.stopPropagation();
        toggleMenu();
    }

    /**
     * Handle document click (close menu on click outside)
     */
    function handleDocumentClick(event) {
        const trigger = getTrigger();
        const menu = getMenu();

        if (!trigger || !menu) return;

        const clickedInTrigger = trigger.contains(event.target);
        const clickedInMenu = menu.contains(event.target);

        if (!clickedInTrigger && !clickedInMenu) {
            closeMenu();
        }
    }

    /**
     * Handle Escape key press
     */
    function handleEscapeKey(event) {
        if (event.key === 'Escape' && isMenuOpen) {
            closeMenu();
            getTrigger()?.focus();
        }
    }

    /**
     * Handle menu keyboard navigation
     */
    function handleMenuKeydown(event) {
        const menu = getMenu();
        if (!menu || !isMenuOpen) return;

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                navigateMenu(1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                navigateMenu(-1);
                break;
            case 'Home':
                event.preventDefault();
                currentFocusIndex = 0;
                updateFocus();
                break;
            case 'End':
                event.preventDefault();
                currentFocusIndex = menuItems.length - 1;
                updateFocus();
                break;
            case 'Enter':
            case ' ':
                event.preventDefault();
                if (currentFocusIndex >= 0 && currentFocusIndex < menuItems.length) {
                    menuItems[currentFocusIndex].click();
                }
                break;
            case 'Tab':
                closeMenu();
                break;
        }
    }

    /**
     * Handle menu item click
     */
    function handleMenuItemClick(item, index) {
        // If it's a link, navigation will happen naturally
        // If it's a button, we can handle the action here
        closeMenu();
        getTrigger()?.focus();
    }

    /**
     * Navigate through menu items
     */
    function navigateMenu(direction) {
        if (menuItems.length === 0) return;

        if (currentFocusIndex === -1) {
            // First navigation
            currentFocusIndex = direction > 0 ? 0 : menuItems.length - 1;
        } else {
            // Subsequent navigation
            currentFocusIndex += direction;
            
            // Wrap around
            if (currentFocusIndex < 0) {
                currentFocusIndex = menuItems.length - 1;
            } else if (currentFocusIndex >= menuItems.length) {
                currentFocusIndex = 0;
            }
        }

        updateFocus();
    }

    /**
     * Update focus to current item
     */
    function updateFocus() {
        if (currentFocusIndex < 0 || currentFocusIndex >= menuItems.length) return;

        // Remove focus from all items
        menuItems.forEach(item => {
            item.removeAttribute('tabindex');
        });

        // Focus current item
        const currentItem = menuItems[currentFocusIndex];
        currentItem.setAttribute('tabindex', '0');
        currentItem.focus();
        
        // Scroll into view
        currentItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /**
     * Clear focus styling
     */
    function clearFocus() {
        menuItems.forEach(item => {
            item.removeAttribute('tabindex');
        });
    }

    /**
     * Toggle menu visibility
     */
    function toggleMenu() {
        if (isMenuOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    /**
     * Open menu
     */
    function openMenu() {
        const trigger = getTrigger();
        const menu = getMenu();

        if (!trigger || !menu) return;

        isMenuOpen = true;
        currentFocusIndex = -1;

        // Update ARIA attributes
        trigger.setAttribute('aria-expanded', 'true');
        menu.removeAttribute('hidden');
        menu.setAttribute('aria-expanded', 'true');

        // Set initial focus to first menu item
        if (menuItems.length > 0) {
            currentFocusIndex = 0;
            updateFocus();
        }

        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('profileMenuOpened'));
    }

    /**
     * Close menu
     */
    function closeMenu() {
        const trigger = getTrigger();
        const menu = getMenu();

        if (!trigger || !menu) return;

        isMenuOpen = false;
        currentFocusIndex = -1;

        // Update ARIA attributes
        trigger.setAttribute('aria-expanded', 'false');
        menu.setAttribute('hidden', '');
        menu.setAttribute('aria-expanded', 'false');

        // Clear focus
        clearFocus();

        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('profileMenuClosed'));
    }

    /**
     * Check if menu is open
     */
    function isOpen() {
        return isMenuOpen;
    }

    /**
     * Public API
     */
    window.profileDropdown = {
        init: init,
        open: openMenu,
        close: closeMenu,
        toggle: toggleMenu,
        isOpen: isOpen
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Reinitialize on dynamic content updates
    document.addEventListener('profileDropdownReload', () => {
        menuItems = getMenuItems();
        init();
    });
})();

/**
 * Auto-close menu when a link is clicked
 */
document.addEventListener('click', function(event) {
    const menu = document.querySelector('.profile-menu');
    const trigger = document.querySelector('.profile-trigger');
    
    if (menu && menu.contains(event.target)) {
        const link = event.target.closest('a');
        if (link) {
            setTimeout(() => {
                if (window.profileDropdown) {
                    window.profileDropdown.close();
                }
            }, 100);
        }
    }
});
