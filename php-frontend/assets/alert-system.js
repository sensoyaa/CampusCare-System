/**
 * CampusCare Modern Alert System
 * Provides consistent, beautiful, and accessible alerts
 */

const CampusCareAlerts = {
  /**
   * Show a success alert
   */
  success: (message, title = 'Success', duration = 5000) => {
    return CampusCareAlerts.showAlert('success', title, message, duration);
  },

  /**
   * Show an error alert
   */
  error: (message, title = 'Error', duration = 0) => {
    return CampusCareAlerts.showAlert('error', title, message, duration);
  },

  /**
   * Show a warning alert
   */
  warning: (message, title = 'Warning', duration = 6000) => {
    return CampusCareAlerts.showAlert('warning', title, message, duration);
  },

  /**
   * Show an info alert
   */
  info: (message, title = 'Information', duration = 5000) => {
    return CampusCareAlerts.showAlert('info', title, message, duration);
  },

  /**
   * Show a validation error alert
   */
  validation: (message, title = 'Validation Error', duration = 0) => {
    return CampusCareAlerts.showAlert('validation', title, message, duration);
  },

  /**
   * Show a toast notification
   */
  toast: (message, type = 'success', duration = 3000) => {
    const container = CampusCareAlerts.getToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'polite');
    
    const iconSvg = CampusCareAlerts.getIconSvg(type);
    
    toast.innerHTML = `
      <div class="toast-icon">${iconSvg}</div>
      <div class="toast-content">
        <div class="toast-message">${CampusCareAlerts.escapeHtml(message)}</div>
      </div>
      <button class="toast-close" aria-label="Close notification">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    `;
    
    container.appendChild(toast);
    
    // Close button handler
    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => {
      CampusCareAlerts.removeToast(toast);
    });
    
    // Auto-remove after duration
    if (duration > 0) {
      setTimeout(() => {
        CampusCareAlerts.removeToast(toast);
      }, duration);
    }
    
    return toast;
  },

  /**
   * Show a confirmation dialog
   */
  confirm: (message, title = 'Are you sure?', confirmText = 'Confirm', cancelText = 'Cancel') => {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.className = 'confirm-overlay';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');
      overlay.setAttribute('aria-labelledby', 'confirm-title');
      
      overlay.innerHTML = `
        <div class="confirm-dialog">
          <div class="confirm-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M8 9h8M8 13h6M12 21a9 9 0 100-18 9 9 0 000 18z"/>
            </svg>
          </div>
          <h3 id="confirm-title" class="confirm-title">${CampusCareAlerts.escapeHtml(title)}</h3>
          <p class="confirm-message">${CampusCareAlerts.escapeHtml(message)}</p>
          <div class="confirm-actions">
            <button class="btn btn-outline confirm-cancel">${CampusCareAlerts.escapeHtml(cancelText)}</button>
            <button class="btn btn-primary confirm-ok">${CampusCareAlerts.escapeHtml(confirmText)}</button>
          </div>
        </div>
      `;
      
      document.body.appendChild(overlay);
      
      const dialog = overlay.querySelector('.confirm-dialog');
      const cancelBtn = overlay.querySelector('.confirm-cancel');
      const okBtn = overlay.querySelector('.confirm-ok');
      
      const cleanup = () => {
        overlay.style.opacity = '0';
        setTimeout(() => {
          document.body.removeChild(overlay);
        }, 200);
      };
      
      cancelBtn.addEventListener('click', () => {
        cleanup();
        resolve(false);
      });
      
      okBtn.addEventListener('click', () => {
        cleanup();
        resolve(true);
      });
      
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
          cleanup();
          resolve(false);
        }
      });
      
      // Focus the confirm button
      setTimeout(() => okBtn.focus(), 100);
    });
  },

  /**
   * Show a loading alert
   */
  loading: (message = 'Processing...') => {
    const alert = document.createElement('div');
    alert.className = 'alert alert-info alert-loading';
    alert.setAttribute('role', 'alert');
    alert.setAttribute('aria-live', 'polite');
    alert.id = 'campuscare-loading-alert';
    
    alert.innerHTML = `
      <div class="alert-icon">
        <svg class="animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/>
        </svg>
      </div>
      <div class="alert-content">
        <div class="alert-message">${CampusCareAlerts.escapeHtml(message)}</div>
      </div>
    `;
    
    // Add spin animation
    const style = document.createElement('style');
    style.textContent = `
      @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }
      .animate-spin {
        animation: spin 1s linear infinite;
      }
    `;
    document.head.appendChild(style);
    
    const container = document.querySelector('.content') || document.body;
    container.insertBefore(alert, container.firstChild);
    
    return alert;
  },

  /**
   * Close loading alert
   */
  closeLoading: () => {
    const loadingAlert = document.getElementById('campuscare-loading-alert');
    if (loadingAlert) {
      loadingAlert.remove();
    }
  },

  /**
   * Show a standard alert
   */
  showAlert: (type, title, message, duration = 0) => {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.setAttribute('role', 'alert');
    alert.setAttribute('aria-live', 'polite');
    
    const iconSvg = CampusCareAlerts.getIconSvg(type);
    
    alert.innerHTML = `
      <div class="alert-icon">${iconSvg}</div>
      <div class="alert-content">
        ${title ? `<div class="alert-title">${CampusCareAlerts.escapeHtml(title)}</div>` : ''}
        <div class="alert-message">${CampusCareAlerts.escapeHtml(message)}</div>
      </div>
      <button class="alert-close" aria-label="Close alert">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
      ${duration > 0 ? '<div class="alert-progress"><div class="alert-progress-bar"></div></div>' : ''}
    `;
    
    const container = document.querySelector('.content') || document.body;
    container.insertBefore(alert, container.firstChild);
    
    // Close button handler
    const closeBtn = alert.querySelector('.alert-close');
    closeBtn.addEventListener('click', () => {
      alert.remove();
    });
    
    // Auto-remove after duration
    if (duration > 0) {
      const progressBar = alert.querySelector('.alert-progress-bar');
      if (progressBar) {
        progressBar.style.animation = `progressShrink ${duration}ms linear`;
      }
      
      setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
      }, duration);
    }
    
    return alert;
  },

  /**
   * Get or create toast container
   */
  getToastContainer: () => {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('aria-atomic', 'false');
      document.body.appendChild(container);
    }
    return container;
  },

  /**
   * Remove a toast notification
   */
  removeToast: (toast) => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100%)';
    setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 300);
  },

  /**
   * Get icon SVG for alert type
   */
  getIconSvg: (type) => {
    const icons = {
      success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
      error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
      warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
      info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
      validation: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
    };
    return icons[type] || icons.info;
  },

  /**
   * Escape HTML to prevent XSS
   */
  escapeHtml: (text) => {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
};

// Auto-convert existing HTML alerts on page load
document.addEventListener('DOMContentLoaded', () => {
  const alerts = document.querySelectorAll('.alert:not([data-converted])');
  
  alerts.forEach(alert => {
    const message = alert.textContent.trim();
    if (!message) return;
    
    let type = 'info';
    if (alert.classList.contains('alert-success')) type = 'success';
    else if (alert.classList.contains('alert-error')) type = 'error';
    else if (alert.classList.contains('alert-warning')) type = 'warning';
    else if (alert.classList.contains('alert-validation')) type = 'validation';
    
    // Mark as converted to avoid re-processing
    alert.setAttribute('data-converted', 'true');
    
    // Show toast for the alert
    CampusCareAlerts.toast(message, type, type === 'error' || type === 'validation' ? 0 : 4000);
    
    // Hide the original alert
    alert.style.display = 'none';
  });
});

// Make globally available
window.CampusCareAlerts = CampusCareAlerts;
