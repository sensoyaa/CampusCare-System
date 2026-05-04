/**
 * Notifications Handler - Real-time notification management
 * Handles:
 * - Fetching notifications from API
 * - Displaying notifications in topbar menu
 * - Marking notifications as read
 * - Deleting/clearing notifications
 * - Updating notification count badge
 */

(function() {
    'use strict';

    const CONFIG = {
        notifyMenuSelector: '.notify-menu',
        notifySummarySelector: '.notify-summary',
        notifyBadgeSelector: '.notify-badge',
        notifyListSelector: '.notify-list',
        notifyPanelSelector: '.notify-panel',
        notifyEmptySelector: '.notify-empty',
        notifyItemSelector: '.notify-item',
        notifyClearBtnSelector: '.notify-clear-btn',
        apiEndpoint: '/campuscare-api/backend/api/notifications.php',
        refreshInterval: 30000 // 30 seconds
    };

    let refreshTimer = null;
    let isOpen = false;

    /**
     * Initialize notifications handler
     */
    function init() {
        const notifyMenu = document.querySelector(CONFIG.notifyMenuSelector);
        const notifySummary = document.querySelector(CONFIG.notifySummarySelector);
        const notifyClearBtn = document.querySelector(CONFIG.notifyClearBtnSelector);

        if (!notifyMenu || !notifySummary) {
            console.warn('Notification menu components not found');
            return;
        }

        // Load notifications on page load
        loadNotifications();

        // Listen for details element open/close
        notifyMenu.addEventListener('toggle', handleMenuToggle);

        // Clear button handler
        if (notifyClearBtn) {
            notifyClearBtn.addEventListener('click', handleClearAll);
        }

        // Refresh notifications periodically
        startAutoRefresh();

        // Attach click handlers to notification items
        attachNotificationItemHandlers();
    }

    /**
     * Handle notification menu toggle (open/close)
     */
    function handleMenuToggle(event) {
        isOpen = event.target.open;

        if (isOpen) {
            // Refresh notifications when menu opens
            loadNotifications();
        }
    }

    /**
     * Load notifications from API
     */
    async function loadNotifications() {
        try {
            const response = await fetch(CONFIG.apiEndpoint);
            const data = await response.json();

            if (data.success && Array.isArray(data.notifications)) {
                renderNotifications(data.notifications, data.unreadCount);
            } else {
                console.warn('Failed to load notifications:', data.error);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    /**
     * Render notifications in the UI
     */
    function renderNotifications(notifications, unreadCount) {
        const notifyList = document.querySelector(CONFIG.notifyListSelector);
        const notifyEmpty = document.querySelector(CONFIG.notifyEmptySelector);
        const notifyBadge = document.querySelector(CONFIG.notifyBadgeSelector);
        const notifyHeader = document.querySelector('.notify-header');

        if (!notifyList || !notifyEmpty) return;

        // Update badge
        if (notifyBadge && unreadCount > 0) {
            notifyBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            notifyBadge.style.display = 'block';
            notifyBadge.dataset.count = unreadCount;
        } else if (notifyBadge) {
            notifyBadge.style.display = 'none';
        }

        // Clear previous notifications
        notifyList.innerHTML = '';

        if (notifications.length === 0) {
            notifyEmpty.style.display = 'flex';
            notifyList.style.display = 'none';
            if (notifyHeader) {
                notifyHeader.style.display = 'none';
            }
            return;
        }

        notifyEmpty.style.display = 'none';
        notifyList.style.display = 'block';
        if (notifyHeader) {
            notifyHeader.style.display = 'flex';
        }

        // Create notification items
        notifications.forEach(notif => {
            const item = createNotificationItem(notif);
            notifyList.appendChild(item);
        });

        // Re-attach click handlers
        attachNotificationItemHandlers();
    }

    /**
     * Create a notification item element
     */
    function createNotificationItem(notif) {
        const item = document.createElement('div');
        item.className = `notify-item ${notif.isRead ? 'read' : 'unread'}`;
        item.dataset.type = notif.type;
        item.dataset.id = notif.id;

        const iconSvg = getIconSvg(notif.type);

        const timeAgo = formatTimeAgo(notif.createdAt);

        item.innerHTML = `
            <div class="notify-item-icon notify-icon">
                ${iconSvg}
            </div>
            <div class="notify-item-content notify-copy">
                <p class="notify-item-title">${escapeHtml(notif.title)}</p>
                ${notif.message ? `<p class="notify-item-message notify-item-body">${escapeHtml(notif.message)}</p>` : ''}
                <span class="notify-item-time">${timeAgo}</span>
            </div>
            ${notif.actionUrl ? `<a href="${escapeHtml(notif.actionUrl)}" class="notify-item-action" aria-label="Open ${escapeHtml(notif.title)}">Open</a>` : ''}
        `;

        item.style.cursor = 'pointer';
        item.addEventListener('click', () => handleNotificationClick(notif, item));

        return item;
    }

    /**
     * Handle notification item click
     */
    async function handleNotificationClick(notif, itemEl) {
        // Mark as read if not already
        if (!notif.isRead) {
            await markNotificationRead(notif.id);
            itemEl.classList.remove('unread');
            itemEl.classList.add('read');
        }

        // Navigate to action URL if available
        if (notif.actionUrl) {
            window.location.href = notif.actionUrl;
        }
    }

    /**
     * Mark notification as read
     */
    async function markNotificationRead(notificationId) {
        try {
            const response = await fetch(CONFIG.apiEndpoint + '?action=mark-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    notification_id: notificationId
                })
            });

            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error('Error marking notification as read:', error);
            return false;
        }
    }

    /**
     * Handle clear all notifications
     */
    async function handleClearAll(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!confirm('Are you sure you want to clear all notifications?')) {
            return;
        }

        try {
            const response = await fetch(CONFIG.apiEndpoint + '?action=mark-all-read', {
                method: 'POST'
            });

            const data = await response.json();
            if (data.success) {
                loadNotifications();
            }
        } catch (error) {
            console.error('Error clearing notifications:', error);
        }
    }

    /**
     * Attach click handlers to notification items
     */
    function attachNotificationItemHandlers() {
        const notifyItems = document.querySelectorAll(CONFIG.notifyItemSelector);

        notifyItems.forEach(item => {
            const notifId = item.dataset.id;
            const actionBtn = item.querySelector('.notify-item-action');

            if (actionBtn) {
                actionBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (!item.classList.contains('read')) {
                        markNotificationRead(notifId);
                        item.classList.remove('unread');
                        item.classList.add('read');
                    }
                });
            }
        });
    }

    /**
     * Get icon SVG for notification type
     */
    function getIconSvg(type) {
        const icons = {
            appointment: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm0 16H5V8h14v11z"/></svg>',
            event: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>',
            security: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>',
            message: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>',
            system: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>'
        };

        return icons[type] || icons.system;
    }

    /**
     * Format time ago (e.g., "2 minutes ago")
     */
    function formatTimeAgo(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'Just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        if (days < 7) return `${days}d ago`;

        return date.toLocaleDateString();
    }

    /**
     * Escape HTML special characters
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Start auto-refresh of notifications
     */
    function startAutoRefresh() {
        refreshTimer = setInterval(() => {
            if (isOpen) {
                loadNotifications();
            }
        }, CONFIG.refreshInterval);
    }

    /**
     * Stop auto-refresh
     */
    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    /**
     * Cleanup on page unload
     */
    window.addEventListener('beforeunload', stopAutoRefresh);

    /**
     * Initialize on DOM ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Export for testing/debugging
    window.NotificationsHandler = {
        loadNotifications,
        markNotificationRead
    };
})();
