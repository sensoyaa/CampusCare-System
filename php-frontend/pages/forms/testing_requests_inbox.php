<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/forms.php";

$pageTitle = "Testing Requests Inbox";
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

campuscare_ensure_testing_requests_table($conn);
$statusChoices = campuscare_status_choices();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selectedId = intval($_POST["request_id"] ?? 0);
    $status = trim((string) ($_POST["status"] ?? "Pending"));
    $typeOfTests = trim((string) ($_POST["counselor_type_of_tests"] ?? ""));
    $counselorNotes = trim((string) ($_POST["counselor_notes"] ?? ""));

    if ($selectedId <= 0) {
        $error = "Invalid testing request selected.";
    } else {
        if (!in_array($status, $statusChoices, true)) {
            $status = "Pending";
        }

        $typeValue = $typeOfTests !== "" ? $typeOfTests : null;
        $notesValue = $counselorNotes !== "" ? $counselorNotes : null;

        $update = $conn->prepare("UPDATE testing_requests SET status = ?, counselor_type_of_tests = ?, counselor_notes = ?, reviewed_by_user_id = ?, reviewed_by_name = ? WHERE id = ? LIMIT 1");

        if (!$update) {
            $error = "Unable to update testing request right now.";
        } else {
            $update->bind_param("sssisi", $status, $typeValue, $notesValue, $userId, $fullName, $selectedId);

            if ($update->execute()) {
                $success = "Testing request updated successfully.";
            } else {
                $error = "Failed to update testing request.";
            }

            $update->close();
        }
    }
}

$selectedRequest = null;
if ($selectedId > 0) {
    $stmt = $conn->prepare("SELECT * FROM testing_requests WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $selectedId);
        $stmt->execute();
        $selectedRequest = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$list = [];
$sql = "SELECT id, target_student_name, requester_role, applicant_name_signature_typed, status, request_date, created_at FROM testing_requests";
if ($statusFilter !== "") {
    $sql .= " WHERE status = ?";
}
$sql .= " ORDER BY created_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($statusFilter !== "") {
        $stmt->bind_param("s", $statusFilter);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $list[] = $row;
    }
    $stmt->close();
}

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
            <h1 class="page-title">Testing Requests Inbox</h1>
            <p class="page-subtitle">Counselor/Admin review queue for request for testing forms.</p>

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
                            <th>Requester Role</th>
                            <th>Applicant Signature</th>
                            <th>Status</th>
                            <th>Request Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:14px; color:var(--text-muted);">No testing request records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($list as $row): ?>
                                <tr>
                                    <td>#<?php echo intval($row["id"]); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row["target_student_name"] ?? "")); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row["requester_role"] ?? "")); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row["applicant_name_signature_typed"] ?? "")); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row["status"] ?? "")); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row["request_date"] ?? "")); ?></td>
                                    <td>
                                        <a class="btn-outline" href="?id=<?php echo intval($row["id"]); ?><?php echo $statusFilter !== "" ? "&status=" . urlencode($statusFilter) : ""; ?>">Open</a>
                                        <a class="btn-outline" target="_blank" rel="noopener" href="/campuscare-api/php-frontend/pages/forms/testing_request_preview.php?id=<?php echo intval($row["id"]); ?>">Preview</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (is_array($selectedRequest)): ?>
                <form method="POST" class="card" style="padding:18px;">
                    <input type="hidden" name="request_id" value="<?php echo intval($selectedRequest["id"]); ?>">
                    <h2 class="card-title" style="margin-bottom:10px;">Update Testing Request #<?php echo intval($selectedRequest["id"]); ?></h2>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <?php foreach ($statusChoices as $choice): ?>
                                <option value="<?php echo htmlspecialchars($choice); ?>" <?php echo trim((string) ($selectedRequest["status"] ?? "")) === $choice ? "selected" : ""; ?>><?php echo htmlspecialchars($choice); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Type of Tests</label>
                        <textarea name="counselor_type_of_tests" rows="3"><?php echo htmlspecialchars((string) ($selectedRequest["counselor_type_of_tests"] ?? "")); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Counselor Notes</label>
                        <textarea name="counselor_notes" rows="4"><?php echo htmlspecialchars((string) ($selectedRequest["counselor_notes"] ?? "")); ?></textarea>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:12px; flex-wrap:wrap;">
                        <button type="submit" class="btn">Save Testing Request Update</button>
                        <a class="btn-outline" target="_blank" rel="noopener" href="/campuscare-api/php-frontend/pages/forms/testing_request_preview.php?id=<?php echo intval($selectedRequest["id"]); ?>">Print / Save PDF</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

</div>
<script>
(function () {
    const profileMenuToggle = document.querySelector(".profile-menu-toggle");
    const profileDropdown = document.querySelector(".profile-dropdown");

    if (!profileMenuToggle || !profileDropdown) {
        return;
    }

    profileMenuToggle.addEventListener("click", function (e) {
        e.stopPropagation();
        const parent = profileMenuToggle.closest(".topbar-user");
        const isOpen = parent.classList.toggle("is-open");
        profileMenuToggle.setAttribute("aria-expanded", isOpen);
    });

    document.addEventListener("click", function () {
        const parent = profileMenuToggle.closest(".topbar-user");
        if (parent) {
            parent.classList.remove("is-open");
        }
        profileMenuToggle.setAttribute("aria-expanded", "false");
    });

    profileDropdown.addEventListener("click", function (e) {
        e.stopPropagation();
    });
})();
</script>
</body>
</html>
