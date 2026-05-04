<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/forms.php";

$pageTitle = "Referral Inbox";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = trim((string) ($_SESSION["full_name"] ?? ""));

if (!campuscare_forms_can_manage($role)) {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

$error = "";
$success = "";
$selectedId = intval($_GET["id"] ?? 0);
$statusFilter = trim((string) ($_GET["status"] ?? ""));

campuscare_ensure_referral_forms_table($conn);

function referral_accessible_where(string $role): string
{
    if ($role === "Administrator") {
        return "1=1";
    }

    return "(referred_to_counselor_id = ? OR submitted_by_user_id = ?)";
}

function referral_can_manage_row(array $row, string $role, int $userId): bool
{
    if ($role === "Administrator") {
        return true;
    }

    return intval($row["referred_to_counselor_id"] ?? 0) === $userId || intval($row["submitted_by_user_id"] ?? 0) === $userId;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selectedId = intval($_POST["referral_id"] ?? 0);
    $status = trim((string) ($_POST["status"] ?? "Pending"));
    $dateReceived = trim((string) ($_POST["date_received"] ?? ""));
    $receivedBy = trim((string) ($_POST["received_by"] ?? ""));
    $actionsTaken = trim((string) ($_POST["actions_taken"] ?? ""));
    $actionsDatetime = trim((string) ($_POST["actions_datetime"] ?? ""));
    $counselorSignatureTyped = trim((string) ($_POST["counselor_signature_typed"] ?? ""));
    $counselorSignatureDrawn = trim((string) ($_POST["counselor_signature_drawn"] ?? ""));

    if ($selectedId <= 0) {
        $error = "Invalid referral selected.";
    } else {
        $check = $conn->prepare("SELECT * FROM referral_forms WHERE id = ? LIMIT 1");
        $row = null;

        if ($check) {
            $check->bind_param("i", $selectedId);
            $check->execute();
            $row = $check->get_result()->fetch_assoc();
            $check->close();
        }

        if (!is_array($row) || !referral_can_manage_row($row, $role, $userId)) {
            $error = "You do not have permission to update this referral.";
        } else {
            $validStatuses = campuscare_status_choices();
            if (!in_array($status, $validStatuses, true)) {
                $status = "Pending";
            }

            $dateReceivedValue = $dateReceived !== "" ? $dateReceived : null;
            $receivedByValue = $receivedBy !== "" ? $receivedBy : null;
            $actionsTakenValue = $actionsTaken !== "" ? $actionsTaken : null;
            $actionsDatetimeValue = $actionsDatetime !== "" ? $actionsDatetime : null;
            $counselorSignatureTypedValue = $counselorSignatureTyped !== "" ? $counselorSignatureTyped : null;
            $counselorSignatureDrawnValue = $counselorSignatureDrawn !== "" ? $counselorSignatureDrawn : null;

            if ($status === "Completed" && !campuscare_signature_present($counselorSignatureTyped, $counselorSignatureDrawn)) {
                $error = "Counselor signature is required before marking the referral as Completed.";
            } else {
                $update = $conn->prepare("UPDATE referral_forms SET status = ?, date_received = ?, received_by = ?, actions_taken = ?, actions_datetime = ?, counselor_signature_typed = ?, counselor_signature_drawn = ? WHERE id = ? LIMIT 1");

                if (!$update) {
                    $error = "Unable to update referral right now.";
                } else {
                    $update->bind_param(
                        "sssssssi",
                        $status,
                        $dateReceivedValue,
                        $receivedByValue,
                        $actionsTakenValue,
                        $actionsDatetimeValue,
                        $counselorSignatureTypedValue,
                        $counselorSignatureDrawnValue,
                        $selectedId
                    );

                    if ($update->execute()) {
                        $success = "Referral updated successfully.";
                    } else {
                        $error = "Failed to update referral.";
                    }

                    $update->close();
                }
            }
        }
    }
}

$selectedReferral = null;
if ($selectedId > 0) {
    $stmt = $conn->prepare("SELECT * FROM referral_forms WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $selectedId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (is_array($row) && referral_can_manage_row($row, $role, $userId)) {
            $selectedReferral = $row;
        }
    }
}

$list = [];
$sql = "SELECT id, submitted_by_name, referred_to_counselor_name, student_name, status, created_at FROM referral_forms WHERE ";
$sql .= referral_accessible_where($role);
if ($statusFilter !== "") {
    $sql .= " AND status = ?";
}
$sql .= " ORDER BY created_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($role === "Administrator") {
        if ($statusFilter !== "") {
            $stmt->bind_param("s", $statusFilter);
        }
    } else {
        if ($statusFilter !== "") {
            $stmt->bind_param("iis", $userId, $userId, $statusFilter);
        } else {
            $stmt->bind_param("ii", $userId, $userId);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $list[] = $row;
    }
    $stmt->close();
}

$statusChoices = campuscare_status_choices();

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
        <div class="page-shell" style="max-width:1100px;">
            <h1 class="page-title">Referral Inbox</h1>
            <p class="page-subtitle">Counselor/Admin review queue for referral slips.</p>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="GET" class="card" style="padding:12px; margin-bottom:12px; display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
                <div class="form-group" style="margin:0; min-width:220px;">
                    <label for="status">Filter by status</label>
                    <select id="status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($statusChoices as $choice): ?>
                            <option value="<?php echo htmlspecialchars($choice); ?>" <?php echo $statusFilter === $choice ? "selected" : ""; ?>><?php echo htmlspecialchars($choice); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn">Apply Filter</button>
            </form>

            <div class="card" style="padding:12px; margin-bottom:12px; overflow:auto;">
                <table class="table" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Submitted By</th>
                            <th>Counselor</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:14px; color:var(--text-muted);">No referral records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($list as $row): ?>
                                <tr>
                                    <td>#<?php echo intval($row["id"]); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row["student_name"] ?? "")); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row["submitted_by_name"] ?? "")); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row["referred_to_counselor_name"] ?? "")); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row["status"] ?? "")); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row["created_at"] ?? "")); ?></td>
                                    <td>
                                        <a class="btn-outline" href="?id=<?php echo intval($row["id"]); ?><?php echo $statusFilter !== "" ? "&status=" . urlencode($statusFilter) : ""; ?>">Open</a>
                                        <a class="btn-outline" target="_blank" rel="noopener" href="/campuscare-api/php-frontend/pages/forms/referral_form_preview.php?id=<?php echo intval($row["id"]); ?>">Preview</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (is_array($selectedReferral)): ?>
                <form method="POST" class="card" style="padding:18px;">
                    <style>
                    .inbox-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
                    .sig-card { border:1px solid var(--border); border-radius:10px; padding:10px; }
                    .sig-pad { width:100%; height:140px; border:1px dashed #96abc0; border-radius:8px; background:#fff; display:block; touch-action:none; cursor:crosshair; }
                    .sig-actions { margin-top:8px; display:flex; gap:8px; justify-content:flex-end; }
                    @media (max-width: 760px) { .inbox-grid { grid-template-columns: 1fr; } }
                    </style>
                    <input type="hidden" name="referral_id" value="<?php echo intval($selectedReferral["id"]); ?>">
                    <h2 class="card-title" style="margin-bottom:10px;">Update Referral #<?php echo intval($selectedReferral["id"]); ?></h2>

                    <div class="inbox-grid">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <?php foreach ($statusChoices as $choice): ?>
                                    <option value="<?php echo htmlspecialchars($choice); ?>" <?php echo trim((string) ($selectedReferral["status"] ?? "")) === $choice ? "selected" : ""; ?>><?php echo htmlspecialchars($choice); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Received By</label>
                            <input type="text" name="received_by" value="<?php echo htmlspecialchars((string) ($selectedReferral["received_by"] ?? $fullName)); ?>">
                        </div>

                        <div class="form-group">
                            <label>Date Received</label>
                            <input type="date" name="date_received" value="<?php echo htmlspecialchars((string) ($selectedReferral["date_received"] ?? "")); ?>">
                        </div>
                        <div class="form-group">
                            <label>Action Date and Time</label>
                            <input type="datetime-local" name="actions_datetime" value="<?php echo htmlspecialchars((string) ($selectedReferral["actions_datetime"] ?? "")); ?>">
                        </div>

                        <div class="form-group" style="grid-column:1 / -1;">
                            <label>Actions Taken</label>
                            <textarea name="actions_taken" rows="4"><?php echo htmlspecialchars((string) ($selectedReferral["actions_taken"] ?? "")); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Counselor Signature (typed)</label>
                            <input type="text" name="counselor_signature_typed" value="<?php echo htmlspecialchars((string) ($selectedReferral["counselor_signature_typed"] ?? $fullName)); ?>">
                        </div>
                    </div>

                    <div class="sig-card" style="margin-top:10px;">
                        <label>Counselor Drawn Signature</label>
                        <canvas id="counselorSignaturePad" class="sig-pad"></canvas>
                        <input type="hidden" id="counselor_signature_drawn" name="counselor_signature_drawn" value="<?php echo htmlspecialchars((string) ($selectedReferral["counselor_signature_drawn"] ?? "")); ?>">
                        <div class="sig-actions">
                            <button type="button" class="btn-outline" id="undoCounselorSignature">Undo</button>
                            <button type="button" class="btn-outline" id="clearCounselorSignature">Clear</button>
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:12px; flex-wrap:wrap;">
                        <button type="submit" class="btn">Save Referral Update</button>
                        <a class="btn-outline" target="_blank" rel="noopener" href="/campuscare-api/php-frontend/pages/forms/referral_form_preview.php?id=<?php echo intval($selectedReferral["id"]); ?>">Print / Save PDF</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

</div>
<script>
(function () {
    function setupSignaturePad(canvasId, hiddenInputId, clearButtonId, undoButtonId) {
        var canvas = document.getElementById(canvasId);
        var hiddenInput = document.getElementById(hiddenInputId);
        var clearButton = document.getElementById(clearButtonId);
        var undoButton = document.getElementById(undoButtonId);

        if (!canvas || !hiddenInput || !clearButton || !undoButton) {
            return;
        }

        var ctx = canvas.getContext("2d");
        var drawing = false;
        var hasStroke = false;
        var history = [];

        function pushHistory(snapshot) {
            if (history.length === 0 || history[history.length - 1] !== snapshot) {
                history.push(snapshot);
            }
            if (history.length > 50) {
                history.shift();
            }
        }

        function restoreSnapshot(snapshot) {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var displayWidth = canvas.clientWidth;
            var displayHeight = canvas.clientHeight;
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (snapshot === "") {
                hiddenInput.value = "";
                hasStroke = false;
                return;
            }

            var image = new Image();
            image.onload = function () {
                ctx.drawImage(image, 0, 0, displayWidth, displayHeight);
            };
            image.src = snapshot;
            hiddenInput.value = snapshot;
            hasStroke = true;
        }

        function resizeCanvas() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var displayWidth = canvas.clientWidth;
            var displayHeight = canvas.clientHeight;
            var snapshot = hiddenInput.value;

            canvas.width = Math.floor(displayWidth * ratio);
            canvas.height = Math.floor(displayHeight * ratio);
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineCap = "round";
            ctx.lineJoin = "round";
            ctx.lineWidth = 2;
            ctx.strokeStyle = "#102a43";

            restoreSnapshot(snapshot);
        }

        function pointFromEvent(event) {
            var rect = canvas.getBoundingClientRect();
            if (event.touches && event.touches.length > 0) {
                return {
                    x: event.touches[0].clientX - rect.left,
                    y: event.touches[0].clientY - rect.top,
                };
            }

            return {
                x: event.clientX - rect.left,
                y: event.clientY - rect.top,
            };
        }

        function beginDraw(event) {
            drawing = true;
            var point = pointFromEvent(event);
            ctx.beginPath();
            ctx.moveTo(point.x, point.y);
            event.preventDefault();
        }

        function draw(event) {
            if (!drawing) {
                return;
            }

            var point = pointFromEvent(event);
            ctx.lineTo(point.x, point.y);
            ctx.stroke();
            hasStroke = true;
            event.preventDefault();
        }

        function endDraw() {
            if (!drawing) {
                return;
            }

            drawing = false;
            ctx.closePath();
            if (hasStroke) {
                hiddenInput.value = canvas.toDataURL("image/png");
                pushHistory(hiddenInput.value);
            }
        }

        undoButton.addEventListener("click", function () {
            if (history.length <= 1) {
                restoreSnapshot("");
                history = [""];
                return;
            }

            history.pop();
            restoreSnapshot(history[history.length - 1] || "");
        });

        clearButton.addEventListener("click", function () {
            restoreSnapshot("");
            history = [""];
        });

        canvas.addEventListener("mousedown", beginDraw);
        canvas.addEventListener("mousemove", draw);
        canvas.addEventListener("mouseup", endDraw);
        canvas.addEventListener("mouseleave", endDraw);
        canvas.addEventListener("touchstart", beginDraw, { passive: false });
        canvas.addEventListener("touchmove", draw, { passive: false });
        canvas.addEventListener("touchend", endDraw);
        canvas.addEventListener("touchcancel", endDraw);

        window.addEventListener("resize", resizeCanvas);
        history = [hiddenInput.value || ""];
        resizeCanvas();
    }

    setupSignaturePad("counselorSignaturePad", "counselor_signature_drawn", "clearCounselorSignature", "undoCounselorSignature");


})();
</script>
</body>
</html>
