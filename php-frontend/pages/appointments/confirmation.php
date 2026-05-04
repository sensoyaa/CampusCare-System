<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();

$pageTitle = "Booking Confirmation";

$service = trim((string) ($_GET["service"] ?? ""));
$counselor = trim((string) ($_GET["counselor"] ?? ""));
$dateLabel = trim((string) ($_GET["date"] ?? ""));
$timeLabel = trim((string) ($_GET["time"] ?? ""));
$emailStatus = trim((string) ($_GET["email"] ?? ""));
$autoPdf = trim((string) ($_GET["auto_pdf"] ?? "")) === "1";
$fullName = $_SESSION["full_name"] ?? "User";
$showCounselor = $counselor !== "" && strcasecmp($counselor, "Unassigned") !== 0;

require_once __DIR__ . "/../../includes/header.php";
require_once __DIR__ . "/../../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <button class="menu-toggle" type="button" aria-label="Sidebar">
            <span class="menu-lines"></span>
        </button>

        <?php require_once __DIR__ . "/../../includes/topbar_user_dropdown.php"; ?>
    </div>

    <div class="content">
        <style>
            :root {
                --confirm-brand-1: #1f4f78;
                --confirm-brand-2: #4b95bb;
                --confirm-ink: #1d3850;
                --confirm-muted: #60768c;
                --confirm-line: #dbe7f0;
                --confirm-soft: #f5faff;
            }

            body.theme-dark {
                --confirm-brand-1: #8bb8df;
                --confirm-brand-2: #68a8dc;
                --confirm-ink: #e6edf5;
                --confirm-muted: #9fb0c3;
                --confirm-line: #2b3b4f;
                --confirm-soft: #162534;
            }

            .confirmation-shell {
                max-width: 860px;
                margin: 0 auto;
                padding: 8px 0 28px;
            }

            .confirmation-card {
                background: var(--card-bg);
                border: 1px solid var(--confirm-line);
                border-radius: 26px;
                overflow: hidden;
                box-shadow: 0 20px 46px rgba(19, 41, 61, 0.08);
            }

            .confirmation-head {
                padding: 26px 28px 22px;
                background: linear-gradient(135deg, #215179 0%, #4f9bc0 100%);
                color: #fff;
                text-align: center;
            }

            .confirmation-icon {
                width: 90px;
                height: 90px;
                border-radius: 999px;
                background: rgba(255,255,255,0.14);
                color: #fff;
                margin: 0 auto 18px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid rgba(255,255,255,0.25);
            }

            .confirmation-head h1 {
                margin: 0 0 8px;
                font-size: 34px;
            }

            .confirmation-head p {
                margin: 0;
                color: rgba(255,255,255,0.92);
            }

            .confirmation-body {
                padding: 28px;
            }

            .confirmation-note {
                border: 1px solid #d9e7dc;
                background: #f4fbf6;
                color: #2c6342;
                border-radius: 16px;
                padding: 15px 18px;
                margin-bottom: 14px;
            }

            .confirmation-note.error {
                border-color: #f0d4d4;
                background: #fff6f6;
                color: #8b3f3f;
            }

            .confirmation-sheet {
                border: 1px dashed var(--confirm-line);
                border-radius: 24px;
                padding: 18px;
                background: linear-gradient(180deg, #fcfeff 0%, #f6faff 100%);
                margin-bottom: 18px;
            }

            .confirmation-section {
                border: 1px solid var(--confirm-line);
                border-radius: 22px;
                background: var(--card-bg);
                padding: 22px;
                margin-bottom: 14px;
                box-shadow: 0 14px 34px rgba(20, 46, 68, 0.06);
            }

            .confirmation-section:last-child {
                margin-bottom: 0;
            }

            .confirmation-section h2 {
                margin: 0 0 16px;
                text-align: left;
                font-size: 13px;
                letter-spacing: 0.9px;
                text-transform: uppercase;
                color: var(--confirm-brand-2);
            }

            .confirmation-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 14px;
            }

            .confirmation-grid-3 {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
            }

            .confirmation-field {
                min-height: 96px;
                border: 1px solid var(--confirm-line);
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #f9fcff 100%);
                padding: 16px 18px;
            }

            .confirmation-field strong {
                display: block;
                margin-bottom: 6px;
                font-size: 11px;
                letter-spacing: 0.8px;
                text-transform: uppercase;
                color: #7187a1;
            }

            .confirmation-field div {
                font-size: 15px;
                color: #405b76;
                line-height: 1.55;
                word-break: break-word;
                white-space: pre-line;
            }

            .confirmation-field.full {
                grid-column: 1 / -1;
            }

            .check-information-head {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 16px;
                margin-bottom: 18px;
            }

            .check-information-copy p {
                margin: 8px 0 0;
                color: var(--confirm-muted);
                line-height: 1.55;
            }

            .status-pill {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                border-radius: 999px;
                background: #e8f6ee;
                color: #1f8b5f;
                padding: 10px 14px;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.7px;
                white-space: nowrap;
            }

            .status-pill::before {
                content: "";
                width: 8px;
                height: 8px;
                border-radius: 999px;
                background: currentColor;
            }

            .sweu-section {
                display: grid;
                grid-template-columns: 96px minmax(0, 1fr);
                gap: 18px;
                align-items: center;
            }

            .sweu-logo {
                width: 78px;
                height: 78px;
                object-fit: contain;
                justify-self: center;
            }

            .sweu-copy h3 {
                margin: 0 0 8px;
                font-size: 20px;
                color: var(--confirm-ink);
            }

            .sweu-copy p {
                margin: 0 0 6px;
                font-size: 14px;
                color: #536d87;
            }

            .sweu-contact-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                margin-top: 14px;
            }

            .contact-chip {
                border: 1px solid var(--confirm-line);
                border-radius: 16px;
                background: var(--confirm-soft);
                padding: 13px 14px;
            }

            .contact-chip strong {
                display: block;
                margin-bottom: 4px;
                font-size: 11px;
                letter-spacing: 0.7px;
                text-transform: uppercase;
                color: #7087a2;
            }

            .contact-chip span {
                font-size: 14px;
                color: var(--confirm-ink);
                word-break: break-word;
                white-space: pre-line;
            }

            .confirmation-actions {
                display: flex;
                gap: 10px;
                justify-content: center;
                flex-wrap: wrap;
            }

            .print-only {
                display: none;
            }

            body.theme-dark .confirmation-card,
            body.theme-dark .confirmation-section,
            body.theme-dark .confirmation-field,
            body.theme-dark .contact-chip {
                background: var(--card-bg);
                color: var(--confirm-ink);
                border-color: var(--confirm-line);
            }

            body.theme-dark .confirmation-sheet {
                background: linear-gradient(180deg, #121d2b 0%, #162534 100%);
                border-color: var(--confirm-line);
            }

            body.theme-dark .confirmation-field {
                background: linear-gradient(180deg, #132131 0%, #121d2b 100%);
            }

            body.theme-dark .confirmation-note {
                border-color: #29523b;
                background: #143123;
                color: #76d39a;
            }

            body.theme-dark .confirmation-note.error {
                border-color: #5a2b34;
                background: #3a1d21;
                color: #f0a0aa;
            }

            body.theme-dark .confirmation-field strong,
            body.theme-dark .contact-chip strong {
                color: #9fb0c3;
            }

            body.theme-dark .confirmation-field div,
            body.theme-dark .contact-chip span,
            body.theme-dark .sweu-copy p,
            body.theme-dark .check-information-copy p {
                color: var(--confirm-muted);
            }

            body.theme-dark .sweu-copy h3 {
                color: var(--confirm-ink);
            }

            @media (max-width: 760px) {
                .confirmation-body,
                .confirmation-head {
                    padding-left: 18px;
                    padding-right: 18px;
                }

                .check-information-head {
                    flex-direction: column;
                    align-items: stretch;
                }

                .confirmation-grid,
                .confirmation-grid-3,
                .sweu-section,
                .sweu-contact-grid {
                    grid-template-columns: 1fr;
                }
            }

            @media print {
                @page {
                    size: A4;
                    margin: 12mm;
                }

                .topbar,
                .sidebar,
                .chat-fab,
                .confirmation-actions,
                .menu-toggle {
                    display: none !important;
                }

                .content,
                .page-shell,
                .confirmation-shell {
                    max-width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .confirmation-card {
                    box-shadow: none;
                    border-radius: 0;
                    border: none;
                }

                .confirmation-section,
                .confirmation-field,
                .contact-chip,
                .confirmation-note {
                    break-inside: avoid;
                    box-shadow: none;
                }

                .print-only {
                    display: block;
                }
            }
        </style>

        <div class="page-shell confirmation-shell">
            <section class="confirmation-card">
                <div class="confirmation-head">
                    <div class="confirmation-icon" aria-hidden="true">
                        <span style="width: 44px; height: 44px; display:inline-flex; align-items:center; justify-content:center;">
                            <?php echo sidebarIconSvg("check-circle"); ?>
                        </span>
                    </div>
                    <h1>Booking Submitted!</h1>
                    <p>Your appointment request has been successfully submitted.</p>
                </div>

                <div class="confirmation-body">
                    <div class="confirmation-note">
                        Please wait for the approval of your form or your submission.
                    </div>

                    <?php if ($emailStatus === "sent"): ?>
                        <div class="confirmation-note">
                            A confirmation email has been sent to your account.
                        </div>
                    <?php elseif ($emailStatus === "not-sent"): ?>
                        <div class="confirmation-note error">
                            Your appointment was saved, but the confirmation email could not be sent.
                        </div>
                    <?php endif; ?>

                    <div class="confirmation-sheet">
                        <section class="confirmation-section">
                            <div class="check-information-head">
                                <div class="check-information-copy">
                                    <h2>Check Information</h2>
                                    <p>Your appointment request is now waiting for approval. Review the key details below for reference.</p>
                                </div>
                                <span class="status-pill">Pending Review</span>
                            </div>
                            <div class="confirmation-grid-3">
                                <div class="confirmation-field">
                                    <strong>Student</strong>
                                    <div><?php echo htmlspecialchars($fullName); ?></div>
                                </div>
                                <div class="confirmation-field">
                                    <strong>Service</strong>
                                    <div><?php echo htmlspecialchars($service !== "" ? $service : "-"); ?></div>
                                </div>
                                <div class="confirmation-field">
                                    <strong>Status</strong>
                                    <div>Pending</div>
                                </div>
                                <div class="confirmation-field">
                                    <strong>Date</strong>
                                    <div><?php echo htmlspecialchars($dateLabel !== "" ? $dateLabel : "-"); ?></div>
                                </div>
                                <div class="confirmation-field">
                                    <strong>Time</strong>
                                    <div><?php echo htmlspecialchars($timeLabel !== "" ? $timeLabel : "-"); ?></div>
                                </div>
                                <?php if ($showCounselor): ?>
                                    <div class="confirmation-field">
                                        <strong>Counselor</strong>
                                        <div><?php echo htmlspecialchars($counselor); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="confirmation-section">
                            <h2>SWEU Information</h2>
                            <div class="sweu-section">
                                <img class="sweu-logo" src="/campuscare-api/php-frontend/assets/images/sweulogo.jpg" alt="SWEU logo">
                                <div class="sweu-copy">
                                    <h3>Student Welfare and Engagement Unit</h3>
                                    <p>BukSU - University Guidance Center</p>
                                    <div class="sweu-contact-grid">
                                        <div class="contact-chip">
                                            <strong>Guidance Email</strong>
                                            <span>guidancecenter@buksu.edu.ph</span>
                                        </div>
                                        <div class="contact-chip">
                                            <strong>Testing Email</strong>
                                            <span>testingcenter@buksu.edu.ph</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="confirmation-actions">
                        <a href="/campuscare-api/php-frontend/pages/appointments/schedule.php" class="btn btn-outline">View Schedule</a>
                        <a href="/campuscare-api/php-frontend/pages/dashboard/dashboard.php" class="btn">Back to Dashboard</a>
                    </div>
                </div>
            </section>
        </div>

        <a href="#" class="chat-fab" aria-label="Open chat">?</a>
    </div>
</main>

</div>
<script>
(function () {
    const shouldAutoPdf = <?php echo $autoPdf ? "true" : "false"; ?>;
    const autoPdfKey = "campuscare-confirmation-auto-pdf";

    if (shouldAutoPdf) {
        const currentUrl = window.location.href;
        try {
            const lastPrinted = sessionStorage.getItem(autoPdfKey);
            if (lastPrinted !== currentUrl) {
                sessionStorage.setItem(autoPdfKey, currentUrl);
                window.addEventListener("load", function () {
                    window.setTimeout(function () {
                        window.print();
                    }, 450);
                }, { once: true });
            }
        } catch (error) {
            window.addEventListener("load", function () {
                window.setTimeout(function () {
                    window.print();
                }, 450);
            }, { once: true });
        }
    }


})();
</script>
</body>
</html>


