/**
 * Notifications Handler
 * Provides badge refresh, dropdown open/close behavior, read state, and clear actions.
 */

(function () {
    "use strict";

    const CONFIG = {
        menuSelector: ".notify-menu",
        triggerSelector: ".notify-summary",
        panelSelector: ".notify-panel",
        badgeSelector: ".notify-badge",
        listSelector: ".notify-list",
        emptySelector: ".notify-empty",
        markAllSelector: ".notify-mark-all-btn",
        clearSelector: ".notify-clear-btn",
        apiEndpoint: "/campuscare-api/backend/api/notifications.php",
        refreshInterval: 20000
    };

    let refreshTimer = null;
    let isOpen = false;
    let isLoading = false;

    function qs(selector) {
        return document.querySelector(selector);
    }

    function init() {
        const menu = qs(CONFIG.menuSelector);
        const trigger = qs(CONFIG.triggerSelector);
        const panel = qs(CONFIG.panelSelector);

        if (!menu || !trigger || !panel) {
            return;
        }

        trigger.addEventListener("click", handleTriggerClick);
        document.addEventListener("click", handleDocumentClick);
        document.addEventListener("keydown", handleEscapeKey);
        document.addEventListener("visibilitychange", handleVisibilityChange);

        const markAllButton = qs(CONFIG.markAllSelector);
        if (markAllButton) {
            markAllButton.addEventListener("click", handleMarkAllRead);
        }

        const clearButton = qs(CONFIG.clearSelector);
        if (clearButton) {
            clearButton.addEventListener("click", handleClearAll);
        }

        loadNotifications();
        startAutoRefresh();
    }

    function handleTriggerClick(event) {
        event.preventDefault();
        event.stopPropagation();

        if (isOpen) {
            closePanel();
            return;
        }

        openPanel();
    }

    function handleDocumentClick(event) {
        const menu = qs(CONFIG.menuSelector);
        if (!menu || !isOpen) {
            return;
        }

        if (!menu.contains(event.target)) {
            closePanel();
        }
    }

    function handleEscapeKey(event) {
        if (event.key === "Escape" && isOpen) {
            closePanel();
            qs(CONFIG.triggerSelector)?.focus();
        }
    }

    function handleVisibilityChange() {
        if (document.visibilityState === "visible") {
            loadNotifications();
        }
    }

    function openPanel() {
        const menu = qs(CONFIG.menuSelector);
        const trigger = qs(CONFIG.triggerSelector);
        const panel = qs(CONFIG.panelSelector);

        if (!menu || !trigger || !panel) {
            return;
        }

        isOpen = true;
        menu.classList.add("is-open");
        panel.hidden = false;
        trigger.setAttribute("aria-expanded", "true");
        loadNotifications();
    }

    function closePanel() {
        const menu = qs(CONFIG.menuSelector);
        const trigger = qs(CONFIG.triggerSelector);
        const panel = qs(CONFIG.panelSelector);

        if (!menu || !trigger || !panel) {
            return;
        }

        isOpen = false;
        menu.classList.remove("is-open");
        trigger.setAttribute("aria-expanded", "false");

        window.setTimeout(function () {
            if (!isOpen) {
                panel.hidden = true;
            }
        }, 180);
    }

    async function loadNotifications() {
        if (isLoading) {
            return;
        }

        isLoading = true;

        try {
            const response = await fetch(CONFIG.apiEndpoint, {
                credentials: "same-origin",
                cache: "no-store",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            });

            const data = await response.json();

            if (data.success && Array.isArray(data.notifications)) {
                renderNotifications(data.notifications, data.unreadCount || 0);
            }
        } catch (error) {
            console.error("Notifications error:", error);
        } finally {
            isLoading = false;
        }
    }

    function renderNotifications(notifications, unreadCount) {
        const list = qs(CONFIG.listSelector);
        const emptyState = qs(CONFIG.emptySelector);
        const badge = qs(CONFIG.badgeSelector);
        const markAllButton = qs(CONFIG.markAllSelector);
        const clearButton = qs(CONFIG.clearSelector);

        if (!list || !emptyState) {
            return;
        }

        updateBadge(unreadCount, badge);
        list.innerHTML = "";

        if (notifications.length === 0) {
            list.style.display = "none";
            emptyState.style.display = "flex";
            if (markAllButton) {
                markAllButton.disabled = true;
            }
            if (clearButton) {
                clearButton.disabled = true;
            }
            return;
        }

        list.style.display = "flex";
        emptyState.style.display = "none";

        if (markAllButton) {
            markAllButton.disabled = unreadCount === 0;
        }

        if (clearButton) {
            clearButton.disabled = false;
        }

        notifications.forEach(function (notification) {
            list.appendChild(createNotificationItem(notification));
        });
    }

    function updateBadge(unreadCount, badge) {
        if (!badge) {
            return;
        }

        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? "99+" : String(unreadCount);
            badge.style.display = "inline-flex";
            badge.dataset.count = String(unreadCount);
        } else {
            badge.textContent = "";
            badge.style.display = "none";
            badge.dataset.count = "0";
        }
    }

    function createNotificationItem(notification) {
        const item = document.createElement("div");
        item.className = "notify-item " + (notification.isRead ? "read" : "unread");
        item.dataset.id = String(notification.id);
        item.dataset.actionUrl = notification.actionUrl || "";
        item.dataset.type = notification.type || "system";
        item.setAttribute("role", "listitem");

        const icon = getIconSvg(notification.type || "system");
        const timeAgo = formatTimeAgo(notification.createdAt);

        item.innerHTML = [
            '<div class="notify-item-icon notify-icon">',
            icon,
            "</div>",
            '<div class="notify-copy">',
            '<p class="notify-item-title">' + escapeHtml(notification.title || "Notification") + "</p>",
            notification.message ? '<p class="notify-item-body">' + escapeHtml(notification.message) + "</p>" : "",
            '<span class="notify-item-time">' + escapeHtml(timeAgo) + "</span>",
            "</div>",
            '<div class="notify-item-controls">',
            !notification.isRead ? '<button class="notify-item-mark" type="button" aria-label="Mark notification as read">' + getMarkIconSvg() + "</button>" : "",
            notification.actionUrl ? '<a href="' + escapeHtml(notification.actionUrl) + '" class="notify-item-action">Open</a>' : "",
            "</div>"
        ].join("");

        item.addEventListener("click", function (event) {
            const markButton = event.target.closest(".notify-item-mark");
            if (markButton) {
                event.preventDefault();
                event.stopPropagation();
                markItemAsRead(notification.id, item, false);
                return;
            }

            const actionLink = event.target.closest(".notify-item-action");
            if (actionLink) {
                event.preventDefault();
            }

            handleNotificationOpen(notification, item);
        });

        return item;
    }

    async function handleNotificationOpen(notification, item) {
        await markItemAsRead(notification.id, item, true);

        if (notification.actionUrl) {
            closePanel();
            window.location.href = notification.actionUrl;
        }
    }

    async function markItemAsRead(notificationId, item, refreshAfter) {
        const success = await postAction("mark-read", {
            notification_id: notificationId
        });

        if (!success) {
            return;
        }

        if (item) {
            item.classList.remove("unread");
            item.classList.add("read");
            const markButton = item.querySelector(".notify-item-mark");
            if (markButton) {
                markButton.remove();
            }
        }

        if (refreshAfter) {
            loadNotifications();
        } else {
            decrementBadge();
        }
    }

    async function handleMarkAllRead(event) {
        event.preventDefault();
        event.stopPropagation();

        const success = await postAction("mark-all-read");
        if (success) {
            loadNotifications();
        }
    }

    async function handleClearAll(event) {
        event.preventDefault();
        event.stopPropagation();

        if (window.Swal && typeof window.Swal.fire === "function") {
            const result = await window.Swal.fire({
                title: "Clear notifications?",
                text: "This will remove all notifications from your current list.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Clear",
                cancelButtonText: "Cancel",
                confirmButtonColor: "#c14949",
                background: document.body.classList.contains("theme-dark") ? "#121d2b" : "#ffffff",
                color: document.body.classList.contains("theme-dark") ? "#e6edf5" : "#1e2f40"
            });

            if (!result.isConfirmed) {
                return;
            }
        } else if (!window.confirm("Clear all notifications from this list?")) {
            return;
        }

        const success = await postAction("clear");
        if (success) {
            loadNotifications();
        }
    }

    async function postAction(action, payload) {
        try {
            const response = await fetch(CONFIG.apiEndpoint + "?action=" + encodeURIComponent(action), {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: payload ? JSON.stringify(payload) : "{}"
            });

            const data = await response.json();
            return !!data.success;
        } catch (error) {
            console.error("Notifications action failed:", error);
            return false;
        }
    }

    function decrementBadge() {
        const badge = qs(CONFIG.badgeSelector);
        if (!badge) {
            return;
        }

        const current = parseInt(badge.dataset.count || "0", 10);
        const next = Math.max(0, current - 1);
        updateBadge(next, badge);
    }

    function startAutoRefresh() {
        refreshTimer = window.setInterval(function () {
            if (document.visibilityState === "visible") {
                loadNotifications();
            }
        }, CONFIG.refreshInterval);
    }

    function formatTimeAgo(timestamp) {
        if (!timestamp) {
            return "Just now";
        }

        const date = new Date(timestamp);
        if (Number.isNaN(date.getTime())) {
            return "Just now";
        }

        const diffSeconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
        if (diffSeconds < 60) {
            return "Just now";
        }

        const minutes = Math.floor(diffSeconds / 60);
        if (minutes < 60) {
            return minutes + "m ago";
        }

        const hours = Math.floor(minutes / 60);
        if (hours < 24) {
            return hours + "h ago";
        }

        const days = Math.floor(hours / 24);
        if (days < 7) {
            return days + "d ago";
        }

        return date.toLocaleDateString();
    }

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text == null ? "" : String(text);
        return div.innerHTML;
    }

    function getMarkIconSvg() {
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M9.55 16.6 4.95 12l1.41-1.41 3.19 3.18 8.09-8.08L19.05 7 9.55 16.6Z"/></svg>';
    }

    function getIconSvg(type) {
        const icons = {
            appointment: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 15H5V10h14v9Z"/></svg>',
            event: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17 12h-5v5h-2v-5H5v-2h5V5h2v5h5v2Z"/></svg>',
            security: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2 4 5v6c0 5 3.4 9.74 8 11 4.6-1.26 8-6 8-11V5l-8-3Zm0 9.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm1 5h-2v-4h2v4Z"/></svg>',
            message: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2Z"/></svg>',
            system: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M11 7h2v6h-2V7Zm0 8h2v2h-2v-2Zm1-13a10 10 0 1 0 10 10A10 10 0 0 0 12 2Z"/></svg>'
        };

        return icons[type] || icons.system;
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
