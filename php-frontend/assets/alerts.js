/**
 * CampusCare Enhanced Alert System
 * Provides consistent, beautiful alerts using SweetAlert2
 */

const CampusCareAlerts = {
    success: (message, title = 'Success') => {
        Swal.fire({
            icon: 'success',
            title: title,
            text: message,
            confirmButtonColor: '#1c4f7b',
            confirmButtonText: 'OK',
            timer: 3000,
            timerProgressBar: true
        });
    },

    error: (message, title = 'Error') => {
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            confirmButtonColor: '#c14949',
            confirmButtonText: 'OK'
        });
    },

    warning: (message, title = 'Warning') => {
        Swal.fire({
            icon: 'warning',
            title: title,
            text: message,
            confirmButtonColor: '#f59e0b',
            confirmButtonText: 'OK'
        });
    },

    info: (message, title = 'Information') => {
        Swal.fire({
            icon: 'info',
            title: title,
            text: message,
            confirmButtonColor: '#2f85a0',
            confirmButtonText: 'OK'
        });
    },

    confirm: async (message, title = 'Are you sure?', confirmText = 'Yes', cancelText = 'Cancel') => {
        const result = await Swal.fire({
            icon: 'question',
            title: title,
            text: message,
            showCancelButton: true,
            confirmButtonColor: '#1c4f7b',
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText,
            cancelButtonText: cancelText
        });
        return result.isConfirmed;
    },

    loading: (message = 'Processing...') => {
        Swal.fire({
            title: message,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    },

    close: () => {
        Swal.close();
    },

    toast: (message, type = 'success') => {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: type,
            title: message
        });
    }
};

// Replace native alert with enhanced version
window.alert = (message) => {
    CampusCareAlerts.info(message, 'Alert');
};

// Auto-convert HTML alerts to SweetAlert2
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const message = alert.textContent.trim();
        if (message) {
            if (alert.classList.contains('alert-success')) {
                CampusCareAlerts.toast(message, 'success');
            } else if (alert.classList.contains('alert-error')) {
                CampusCareAlerts.toast(message, 'error');
            } else if (alert.classList.contains('alert-warning')) {
                CampusCareAlerts.toast(message, 'warning');
            } else {
                CampusCareAlerts.toast(message, 'info');
            }
            alert.style.display = 'none';
        }
    });
});
