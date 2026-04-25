<?php
$topbarFullName = trim((string) ($_SESSION["full_name"] ?? ""));
$topbarEmail = trim((string) ($_SESSION["email"] ?? ""));
$topbarRole = trim((string) ($_SESSION["role"] ?? "Student"));
$topbarAvatarPath = trim((string) ($_SESSION["avatar_path"] ?? ""));
$topbarAvatarInitial = strtoupper(substr($topbarFullName !== "" ? $topbarFullName : "U", 0, 1));
?>
<div class="topbar-user topbar-user-modern">
    <div class="topbar-divider" aria-hidden="true"></div>
    <div class="profile-pill">
        <button class="profile-menu-toggle modern-profile-toggle" aria-label="Profile menu" aria-expanded="false" type="button">
            <span class="modern-avatar">
                <?php if ($topbarAvatarPath !== ""): ?>
                    <img src="<?php echo htmlspecialchars($topbarAvatarPath); ?>" alt="Profile avatar" class="modern-avatar-image">
                <?php else: ?>
                    <?php echo htmlspecialchars($topbarAvatarInitial); ?>
                <?php endif; ?>
            </span>
            <span class="modern-name"><?php echo htmlspecialchars($topbarFullName !== "" ? $topbarFullName : "User"); ?></span>
            <svg class="modern-caret" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M7 10l5 5 5-5z"></path>
            </svg>
        </button>

        <div class="profile-dropdown modern-profile-dropdown">
            <div class="modern-dropdown-head">
                <p class="modern-dropdown-role"><?php echo htmlspecialchars($topbarRole); ?></p>
                <p class="modern-dropdown-email"><?php echo htmlspecialchars($topbarEmail !== "" ? $topbarEmail : "no-email@campuscare.local"); ?></p>
            </div>
            <a href="/campuscare-api/php-frontend/pages/users/edit_profile.php" class="profile-dropdown-item">
                <svg class="dropdown-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4.42 0-8 1.79-8 4v2h16v-2c0-2.21-3.58-4-8-4z"/></svg>
                <span>My Profile</span>
            </a>
            <a href="/campuscare-api/php-frontend/pages/users/settings.php" class="profile-dropdown-item">
                <svg class="dropdown-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19.14 12.94a7.14 7.14 0 0 0 .05-.94 7.14 7.14 0 0 0-.05-.94l2.03-1.58a.49.49 0 0 0 .12-.63l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.28 7.28 0 0 0-1.63-.94L14.4 2.8a.49.49 0 0 0-.49-.4h-3.82a.49.49 0 0 0-.49.4l-.36 2.53a7.28 7.28 0 0 0-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.7 8.85a.49.49 0 0 0 .12.63l2.03 1.58a7.14 7.14 0 0 0-.05.94 7.14 7.14 0 0 0 .05.94L2.82 14.52a.49.49 0 0 0-.12.63l1.92 3.32a.5.5 0 0 0 .6.22l2.39-.96a7.28 7.28 0 0 0 1.63.94l.36 2.53a.49.49 0 0 0 .49.4h3.82a.49.49 0 0 0 .49-.4l.36-2.53a7.28 7.28 0 0 0 1.63-.94l2.39.96a.5.5 0 0 0 .6-.22l1.92-3.32a.49.49 0 0 0-.12-.63zM12 15.5A3.5 3.5 0 1 1 15.5 12 3.5 3.5 0 0 1 12 15.5z"/></svg>
                <span>Account Management</span>
            </a>
            <a href="/campuscare-api/php-frontend/pages/dashboard/dashboard.php" class="profile-dropdown-item">
                <svg class="dropdown-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 13h8V3H3zm0 8h8v-6H3zm10 0h8V11h-8zm0-18v6h8V3z"/></svg>
                <span>Dashboard</span>
            </a>
            <a href="/campuscare-api/php-frontend/pages/users/settings.php" class="profile-dropdown-item">
                <svg class="dropdown-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19.43 12.98a7.93 7.93 0 0 0 .07-.98 7.93 7.93 0 0 0-.07-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.61-.22l-2.49 1a7.14 7.14 0 0 0-1.69-.98l-.38-2.65a.5.5 0 0 0-.49-.42h-4a.5.5 0 0 0-.49.42L9.13 5.07a7.14 7.14 0 0 0-1.69.98l-2.49-1a.5.5 0 0 0-.61.22l-2 3.46a.5.5 0 0 0 .12.64L4.57 11a7.93 7.93 0 0 0-.07.98 7.93 7.93 0 0 0 .07.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .61.22l2.49-1a7.14 7.14 0 0 0 1.69.98l.38 2.65a.5.5 0 0 0 .49.42h4a.5.5 0 0 0 .49-.42l.38-2.65a7.14 7.14 0 0 0 1.69-.98l2.49 1a.5.5 0 0 0 .61-.22l2-3.46a.5.5 0 0 0-.12-.64zM12 15.5a3.5 3.5 0 1 1 3.5-3.5 3.5 3.5 0 0 1-3.5 3.5z"/></svg>
                <span>Settings</span>
            </a>
            <div class="profile-dropdown-divider"></div>
            <a href="/campuscare-api/php-frontend/pages/auth/logout.php" class="profile-dropdown-item logout-item">
                <svg class="dropdown-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10 17v-2h4V9h-4V7h6v10zM6 19V5h7V3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7v-2zm12.59-7L16 9.41 17.41 8 22 12l-4.59 4-1.41-1.41z"/></svg>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>
