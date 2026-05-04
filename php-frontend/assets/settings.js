/**
 * Settings Page - Real-time handlers and dark mode
 * Handles:
 * - Dark mode toggle with immediate visual feedback
 * - Notification dependent field management
 * - Real-time form submission via AJAX
 * - Success/error notifications
 */

(function() {
    'use strict';

    const CONFIG = {
        preferencesForm: '.settings-stack',
        darkModeToggle: 'input[name="dark_mode"]',
        enableNotificationsToggle: '#enableNotificationsToggle',
        notificationDependentClass: '.notification-dependent',
        notificationDependentInputs: '.notification-dependent input, .notification-dependent select',
        successAlertClass: '.alert-success',
        errorAlertClass: '.alert-error',
        apiEndpoint: '/backend/api/user-preferences.php'
    };

    let isSubmitting = false;

    /**
     * Initialize event listeners
     */
    function init() {
        const preferencesForm = document.querySelector(CONFIG.preferencesForm);
        const darkModeToggle = document.querySelector(CONFIG.darkModeToggle);
        const enableNotificationsToggle = document.querySelector(CONFIG.enableNotificationsToggle);

        if (!preferencesForm) {
            console.warn('Settings form not found');
            return;
        }

        // Dark mode toggle - immediate visual feedback
        if (darkModeToggle) {
            darkModeToggle.addEventListener('change', handleDarkModeToggle);
        }

        // Enable notifications toggle - control dependent fields
        if (enableNotificationsToggle) {
            enableNotificationsToggle.addEventListener('change', handleNotificationsToggle);
            // Set initial state of dependent fields
            updateNotificationDependentFields();
        }

        // Form submission via AJAX
        preferencesForm.addEventListener('submit', handleFormSubmit);

        // Auto-apply dark mode on page load
        applyDarkModeFromCookie();
    }

    /**
     * Handle dark mode toggle change
     */
    function handleDarkModeToggle(event) {
        const isEnabled = event.target.checked;
        
        // Apply immediately to page
        if (isEnabled) {
            document.body.classList.add('theme-dark');
            localStorage.setItem('campuscare_dark_mode', 'true');
            document.cookie = "campuscare_dark_mode=true; path=/; max-age=" + (365 * 24 * 60 * 60);
        } else {
            document.body.classList.remove('theme-dark');
            localStorage.setItem('campuscare_dark_mode', 'false');
            document.cookie = "campuscare_dark_mode=false; path=/; max-age=" + (365 * 24 * 60 * 60);
        }
    }

    /**
     * Handle notifications enabled toggle - control dependent fields
     */
    function handleNotificationsToggle(event) {
        updateNotificationDependentFields();
    }

    /**
     * Update notification dependent fields state
     */
    function updateNotificationDependentFields() {
        const enableNotificationsToggle = document.querySelector(CONFIG.enableNotificationsToggle);
        const isEnabled = enableNotificationsToggle?.checked ?? false;
        const dependentInputs = document.querySelectorAll(CONFIG.notificationDependentInputs);

        dependentInputs.forEach(input => {
            if (input === enableNotificationsToggle) return; // Skip the master toggle itself

            if (isEnabled) {
                input.removeAttribute('disabled');
            } else {
                input.setAttribute('disabled', 'disabled');
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = false;
                }
            }
        });
    }

    /**
     * Handle form submission via AJAX
     */
    async function handleFormSubmit(event) {
        event.preventDefault();

        if (isSubmitting) return;
        isSubmitting = true;

        const form = event.target;
        const formData = new FormData(form);
        
        // Show loading state
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton?.textContent;
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
        }

        try {
            // For password changes, allow normal form submission
            const action = formData.get('action');
            if (action === 'change_password') {
                form.submit();
                return;
            }

            // For preferences, submit via AJAX
            const response = await fetch(form.action || CONFIG.apiEndpoint, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (response.ok && data.success) {
                showAlert('Preferences updated successfully!', 'success');
                // Form already updated the page, just show confirmation
            } else {
                showAlert(data.error || 'Failed to save preferences', 'error');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            showAlert('Error saving preferences: ' + error.message, 'error');
        } finally {
            isSubmitting = false;
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        }
    }

    /**
     * Show alert message
     */
    function showAlert(message, type) {
        const alertClass = type === 'success' ? CONFIG.successAlertClass : CONFIG.errorAlertClass;
        let alertEl = document.querySelector(alertClass);

        if (!alertEl) {
            alertEl = document.createElement('div');
            alertEl.className = `alert alert-${type}`;
            const form = document.querySelector(CONFIG.preferencesForm);
            form?.parentNode?.insertBefore(alertEl, form);
        }

        alertEl.textContent = message;
        alertEl.className = `alert alert-${type}`;
        alertEl.style.display = 'block';

        // Auto-hide after 5 seconds
        setTimeout(() => {
            alertEl.style.display = 'none';
        }, 5000);
    }

    /**
     * Apply dark mode from cookie on page load
     */
    function applyDarkModeFromCookie() {
        const darkModeCookie = document.cookie
            .split(';')
            .find(c => c.trim().startsWith('campuscare_dark_mode='));

        if (darkModeCookie && darkModeCookie.includes('true')) {
            document.body.classList.add('theme-dark');
        } else {
            document.body.classList.remove('theme-dark');
        }
    }

    /**
     * Initialize on DOM ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
