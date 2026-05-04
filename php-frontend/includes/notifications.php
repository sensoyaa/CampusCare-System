<?php

function campuscare_notifications_table_exists(mysqli $conn, string $tableName): bool
{
    $safe = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result !== false && $result->num_rows > 0;
}

function campuscare_notifications_column_exists(mysqli $conn, string $tableName, string $columnName): bool
{
    $tableSafe = $conn->real_escape_string($tableName);
    $columnSafe = $conn->real_escape_string($columnName);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
    return $result !== false && $result->num_rows > 0;
}

function campuscare_notifications_index_exists(mysqli $conn, string $tableName, string $indexName): bool
{
    $tableSafe = $conn->real_escape_string($tableName);
    $indexSafe = $conn->real_escape_string($indexName);
    $result = $conn->query("SHOW INDEX FROM `{$tableSafe}` WHERE Key_name = '{$indexSafe}'");
    return $result !== false && $result->num_rows > 0;
}

function campuscare_notifications_normalize_role(string $role): string
{
    if (in_array($role, ["Counsellor", "Counselor", "Counselors"], true)) {
        return "Counselor";
    }

    return $role;
}

function campuscare_notifications_ensure_tables(mysqli $conn): bool
{
    $sql = "
        CREATE TABLE IF NOT EXISTS `user_notifications` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `user_id` int NOT NULL,
          `type` enum('appointment', 'event', 'system', 'security', 'message') DEFAULT 'system',
          `title` varchar(255) NOT NULL,
          `message` text,
          `related_id` int DEFAULT NULL,
          `related_type` varchar(50) DEFAULT NULL,
          `event_key` varchar(190) DEFAULT NULL,
          `is_read` tinyint(1) DEFAULT 0,
          `is_archived` tinyint(1) DEFAULT 0,
          `action_url` varchar(255) DEFAULT NULL,
          `read_at` timestamp NULL,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          KEY `idx_user_id` (`user_id`),
          KEY `idx_type` (`type`),
          KEY `idx_is_read` (`is_read`),
          KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if ($conn->query($sql) === false) {
        return false;
    }

    $columns = [
        ["related_id", "INT DEFAULT NULL"],
        ["related_type", "VARCHAR(50) DEFAULT NULL"],
        ["event_key", "VARCHAR(190) DEFAULT NULL"],
        ["is_archived", "TINYINT(1) DEFAULT 0"],
        ["action_url", "VARCHAR(255) DEFAULT NULL"],
        ["read_at", "TIMESTAMP NULL DEFAULT NULL"],
        ["updated_at", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"],
    ];

    foreach ($columns as [$columnName, $definition]) {
        if (!campuscare_notifications_column_exists($conn, "user_notifications", $columnName)) {
            if ($conn->query("ALTER TABLE `user_notifications` ADD COLUMN {$columnName} {$definition}") === false) {
                return false;
            }
        }
    }

    if (!campuscare_notifications_index_exists($conn, "user_notifications", "idx_user_event_key")) {
        $conn->query("ALTER TABLE `user_notifications` ADD UNIQUE KEY `idx_user_event_key` (`user_id`, `event_key`)");
    }

    return true;
}

function campuscare_notifications_user_preferences(mysqli $conn, int $userId): array
{
    $defaults = [
        "notifications_enabled" => true,
        "notifications_in_app" => true,
        "notify_appointments" => true,
        "notify_events" => true,
        "notify_system" => true,
    ];

    if (!campuscare_notifications_table_exists($conn, "user_preferences")) {
        return $defaults;
    }

    $stmt = $conn->prepare("
        SELECT notifications_enabled, notifications_in_app, notify_appointments, notify_events, notify_system
        FROM user_preferences
        WHERE user_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return $defaults;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!is_array($row)) {
        return $defaults;
    }

    return [
        "notifications_enabled" => intval($row["notifications_enabled"] ?? 1) === 1,
        "notifications_in_app" => intval($row["notifications_in_app"] ?? 1) === 1,
        "notify_appointments" => intval($row["notify_appointments"] ?? 1) === 1,
        "notify_events" => intval($row["notify_events"] ?? 1) === 1,
        "notify_system" => intval($row["notify_system"] ?? 1) === 1,
    ];
}

function campuscare_notifications_type_allowed(array $preferences, string $type): bool
{
    if (!$preferences["notifications_enabled"] || !$preferences["notifications_in_app"]) {
        return false;
    }

    if ($type === "appointment") {
        return $preferences["notify_appointments"];
    }

    if ($type === "event") {
        return $preferences["notify_events"];
    }

    return $preferences["notify_system"];
}

function campuscare_notifications_upsert(mysqli $conn, array $payload): bool
{
    if (!campuscare_notifications_ensure_tables($conn)) {
        return false;
    }

    $userId = intval($payload["user_id"] ?? 0);
    $title = trim((string) ($payload["title"] ?? ""));
    $eventKey = trim((string) ($payload["event_key"] ?? ""));

    if ($userId <= 0 || $title === "" || $eventKey === "") {
        return false;
    }

    $type = trim((string) ($payload["type"] ?? "system"));
    $message = trim((string) ($payload["message"] ?? ""));
    $relatedId = isset($payload["related_id"]) ? intval($payload["related_id"]) : null;
    $relatedType = trim((string) ($payload["related_type"] ?? ""));
    $actionUrl = trim((string) ($payload["action_url"] ?? ""));

    $stmt = $conn->prepare("
        INSERT INTO user_notifications
            (user_id, type, title, message, related_id, related_type, event_key, action_url, is_read, is_archived, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            message = VALUES(message),
            related_id = VALUES(related_id),
            related_type = VALUES(related_type),
            action_url = VALUES(action_url),
            is_archived = 0,
            updated_at = NOW()
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "isssisss",
        $userId,
        $type,
        $title,
        $message,
        $relatedId,
        $relatedType,
        $eventKey,
        $actionUrl
    );

    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function campuscare_notifications_format_datetime(string $dateValue, string $timeValue = ""): string
{
    $raw = trim($dateValue . " " . $timeValue);
    $timestamp = strtotime($raw);

    if ($timestamp === false) {
        return trim($dateValue);
    }

    return date("M j, Y g:i A", $timestamp);
}

function campuscare_notifications_sync_user(mysqli $conn, int $userId, string $role): void
{
    if ($userId <= 0 || !campuscare_notifications_ensure_tables($conn)) {
        return;
    }

    $role = campuscare_notifications_normalize_role($role);
    campuscare_notifications_sync_welcome($conn, $userId);
    campuscare_notifications_sync_appointments($conn, $userId, $role);
    campuscare_notifications_sync_referrals($conn, $userId, $role);
    campuscare_notifications_sync_testing_requests($conn, $userId, $role);
    campuscare_notifications_sync_events($conn, $userId, $role);

    if ($role === "Administrator") {
        campuscare_notifications_sync_admin_queue($conn, $userId);
    }
}

function campuscare_notifications_sync_welcome(mysqli $conn, int $userId): void
{
    campuscare_notifications_upsert($conn, [
        "user_id" => $userId,
        "type" => "system",
        "title" => "Welcome to CampusCare",
        "message" => "Your account notifications, approvals, and reminders will appear here.",
        "related_type" => "account",
        "event_key" => "system-welcome-user-" . $userId,
        "action_url" => "/campuscare-api/php-frontend/pages/users/settings.php",
    ]);
}

function campuscare_notifications_sync_appointments(mysqli $conn, int $userId, string $role): void
{
    if (!campuscare_notifications_table_exists($conn, "appointments")) {
        return;
    }

    $stmt = $conn->prepare("
        SELECT id, user_id, counselor_id, service, counselor, appointment_date, appointment_time, COALESCE(NULLIF(status, ''), 'Pending') AS status
        FROM appointments
        WHERE user_id = ? OR counselor_id = ?
        ORDER BY appointment_date DESC, appointment_time DESC
        LIMIT 40
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $appointmentId = intval($row["id"] ?? 0);
        $service = trim((string) ($row["service"] ?? "Counseling"));
        $counselorName = trim((string) ($row["counselor"] ?? "Counselor"));
        $status = trim((string) ($row["status"] ?? "Pending"));
        $dateLabel = campuscare_notifications_format_datetime((string) ($row["appointment_date"] ?? ""), (string) ($row["appointment_time"] ?? ""));
        $detailUrl = "/campuscare-api/php-frontend/pages/appointments/appointment_detail.php?id=" . $appointmentId;
        $appointmentTs = strtotime(trim((string) ($row["appointment_date"] ?? "") . " " . (string) ($row["appointment_time"] ?? "")));

        if (intval($row["user_id"] ?? 0) === $userId) {
            $studentMessages = [
                "Pending" => ["Appointment request received", "{$service} with {$counselorName} on {$dateLabel} is pending review."],
                "Approved" => ["Appointment approved", "{$service} with {$counselorName} on {$dateLabel} has been approved."],
                "Rejected" => ["Appointment request declined", "{$service} scheduled for {$dateLabel} was declined."],
                "Cancelled" => ["Appointment cancelled", "{$service} scheduled for {$dateLabel} was cancelled."],
                "Completed" => ["Appointment completed", "{$service} scheduled for {$dateLabel} has been marked completed."],
            ];

            if (isset($studentMessages[$status])) {
                campuscare_notifications_upsert($conn, [
                    "user_id" => $userId,
                    "type" => "appointment",
                    "title" => $studentMessages[$status][0],
                    "message" => $studentMessages[$status][1],
                    "related_id" => $appointmentId,
                    "related_type" => "appointment",
                    "event_key" => "appointment-student-{$appointmentId}-{$status}",
                    "action_url" => $detailUrl,
                ]);
            }

            if ($appointmentTs !== false && $appointmentTs >= time() && $appointmentTs <= (time() + (2 * 24 * 60 * 60)) && in_array($status, ["Pending", "Approved"], true)) {
                campuscare_notifications_upsert($conn, [
                    "user_id" => $userId,
                    "type" => "appointment",
                    "title" => "Upcoming appointment",
                    "message" => "{$service} with {$counselorName} is scheduled for {$dateLabel}.",
                    "related_id" => $appointmentId,
                    "related_type" => "appointment",
                    "event_key" => "appointment-student-reminder-{$appointmentId}-" . date("YmdHi", $appointmentTs),
                    "action_url" => $detailUrl,
                ]);
            }
        }

        if (intval($row["counselor_id"] ?? 0) === $userId) {
            if ($status === "Pending") {
                campuscare_notifications_upsert($conn, [
                    "user_id" => $userId,
                    "type" => "appointment",
                    "title" => "New appointment request",
                    "message" => "{$service} is waiting for your review on {$dateLabel}.",
                    "related_id" => $appointmentId,
                    "related_type" => "appointment",
                    "event_key" => "appointment-counselor-pending-{$appointmentId}",
                    "action_url" => $detailUrl,
                ]);
            }

            if ($appointmentTs !== false && $appointmentTs >= time() && $appointmentTs <= (time() + (24 * 60 * 60)) && in_array($status, ["Pending", "Approved"], true)) {
                campuscare_notifications_upsert($conn, [
                    "user_id" => $userId,
                    "type" => "appointment",
                    "title" => "Upcoming counseling session",
                    "message" => "{$service} is scheduled for {$dateLabel}.",
                    "related_id" => $appointmentId,
                    "related_type" => "appointment",
                    "event_key" => "appointment-counselor-reminder-{$appointmentId}-" . date("YmdHi", $appointmentTs),
                    "action_url" => $detailUrl,
                ]);
            }
        }
    }

    $stmt->close();
}

function campuscare_notifications_sync_referrals(mysqli $conn, int $userId, string $role): void
{
    if (!campuscare_notifications_table_exists($conn, "referral_forms")) {
        return;
    }

    $stmt = $conn->prepare("
        SELECT id, submitted_by_user_id, submitted_by_name, submitted_by_role, referred_to_counselor_id, referred_to_counselor_name,
               student_user_id, student_name, status, referral_datetime
        FROM referral_forms
        WHERE submitted_by_user_id = ? OR referred_to_counselor_id = ? OR student_user_id = ?
        ORDER BY referral_datetime DESC
        LIMIT 40
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param("iii", $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $referralId = intval($row["id"] ?? 0);
        $submittedBy = trim((string) ($row["submitted_by_name"] ?? "CampusCare"));
        $studentName = trim((string) ($row["student_name"] ?? "Student"));
        $status = trim((string) ($row["status"] ?? "Pending"));
        $dateLabel = campuscare_notifications_format_datetime((string) ($row["referral_datetime"] ?? ""));
        $previewUrl = "/campuscare-api/php-frontend/pages/forms/referral_form_preview.php?id=" . $referralId;

        if (intval($row["submitted_by_user_id"] ?? 0) === $userId) {
            campuscare_notifications_upsert($conn, [
                "user_id" => $userId,
                "type" => "system",
                "title" => "Referral submitted",
                "message" => "Your referral for {$studentName} was submitted on {$dateLabel}.",
                "related_id" => $referralId,
                "related_type" => "referral",
                "event_key" => "referral-submitter-created-{$referralId}",
                "action_url" => $previewUrl,
            ]);

            if ($status !== "Pending") {
                campuscare_notifications_upsert($conn, [
                    "user_id" => $userId,
                    "type" => "system",
                    "title" => "Referral status updated",
                    "message" => "The referral for {$studentName} is now {$status}.",
                    "related_id" => $referralId,
                    "related_type" => "referral",
                    "event_key" => "referral-submitter-status-{$referralId}-{$status}",
                    "action_url" => $previewUrl,
                ]);
            }
        }

        if (intval($row["referred_to_counselor_id"] ?? 0) === $userId) {
            campuscare_notifications_upsert($conn, [
                "user_id" => $userId,
                "type" => "system",
                "title" => "New referral assigned",
                "message" => "{$submittedBy} referred {$studentName} to you.",
                "related_id" => $referralId,
                "related_type" => "referral",
                "event_key" => "referral-counselor-assigned-{$referralId}",
                "action_url" => "/campuscare-api/php-frontend/pages/forms/referral_inbox.php?id=" . $referralId,
            ]);
        }

        if (intval($row["student_user_id"] ?? 0) === $userId) {
            campuscare_notifications_upsert($conn, [
                "user_id" => $userId,
                "type" => "system",
                "title" => "Counseling referral created",
                "message" => "{$submittedBy} submitted a referral related to your account.",
                "related_id" => $referralId,
                "related_type" => "referral",
                "event_key" => "referral-student-{$referralId}",
                "action_url" => "/campuscare-api/php-frontend/pages/forms/my_referrals.php",
            ]);
        }
    }

    $stmt->close();
}

function campuscare_notifications_sync_testing_requests(mysqli $conn, int $userId, string $role): void
{
    if (!campuscare_notifications_table_exists($conn, "testing_requests")) {
        return;
    }

    $stmt = $conn->prepare("
        SELECT id, requester_user_id, requester_role, target_student_user_id, target_student_name, reviewed_by_user_id, reviewed_by_name, status, request_date
        FROM testing_requests
        WHERE requester_user_id = ? OR target_student_user_id = ? OR reviewed_by_user_id = ?
        ORDER BY created_at DESC
        LIMIT 40
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param("iii", $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $requestId = intval($row["id"] ?? 0);
        $studentName = trim((string) ($row["target_student_name"] ?? "Student"));
        $status = trim((string) ($row["status"] ?? "Pending"));
        $requestDate = campuscare_notifications_format_datetime((string) ($row["request_date"] ?? ""));
        $previewUrl = "/campuscare-api/php-frontend/pages/forms/testing_request_preview.php?id=" . $requestId;

        if (intval($row["requester_user_id"] ?? 0) === $userId) {
            campuscare_notifications_upsert($conn, [
                "user_id" => $userId,
                "type" => "system",
                "title" => "Testing request submitted",
                "message" => "Your testing request for {$studentName} was submitted on {$requestDate}.",
                "related_id" => $requestId,
                "related_type" => "testing_request",
                "event_key" => "testing-request-created-{$requestId}",
                "action_url" => $previewUrl,
            ]);

            if ($status !== "Pending") {
                campuscare_notifications_upsert($conn, [
                    "user_id" => $userId,
                    "type" => "system",
                    "title" => "Testing request updated",
                    "message" => "Your testing request for {$studentName} is now {$status}.",
                    "related_id" => $requestId,
                    "related_type" => "testing_request",
                    "event_key" => "testing-request-status-{$requestId}-{$status}",
                    "action_url" => $previewUrl,
                ]);
            }
        }

        if (intval($row["target_student_user_id"] ?? 0) === $userId && intval($row["requester_user_id"] ?? 0) !== $userId) {
            campuscare_notifications_upsert($conn, [
                "user_id" => $userId,
                "type" => "system",
                "title" => "Testing request linked to you",
                "message" => "A request for testing has been submitted that references your account.",
                "related_id" => $requestId,
                "related_type" => "testing_request",
                "event_key" => "testing-request-student-{$requestId}",
                "action_url" => "/campuscare-api/php-frontend/pages/forms/testing_request_form.php?mode=view",
            ]);
        }
    }

    $stmt->close();
}

function campuscare_notifications_sync_events(mysqli $conn, int $userId, string $role): void
{
    if (!campuscare_notifications_table_exists($conn, "events")) {
        return;
    }

    $startExpression = campuscare_notifications_column_exists($conn, "events", "starts_at")
        ? "e.starts_at"
        : (campuscare_notifications_column_exists($conn, "events", "event_date")
            ? (
                campuscare_notifications_column_exists($conn, "events", "event_time")
                    ? "TIMESTAMP(e.event_date, COALESCE(e.event_time, '00:00:00'))"
                    : "TIMESTAMP(e.event_date, '00:00:00')"
            )
            : "NULL");

    if ($startExpression === "NULL") {
        return;
    }

    if (campuscare_notifications_table_exists($conn, "event_participants")) {
        $stmt = $conn->prepare("
            SELECT e.id, e.title, e.location, {$startExpression} AS starts_at
            FROM event_participants ep
            INNER JOIN events e ON e.id = ep.event_id
            WHERE ep.user_id = ?
              AND {$startExpression} IS NOT NULL
              AND {$startExpression} >= NOW()
              AND {$startExpression} <= DATE_ADD(NOW(), INTERVAL 7 DAY)
            ORDER BY {$startExpression} ASC
            LIMIT 20
        ");

        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $eventId = intval($row["id"] ?? 0);
                $startsAt = strtotime((string) ($row["starts_at"] ?? ""));
                $dateLabel = $startsAt !== false ? date("M j, Y g:i A", $startsAt) : "soon";
                $location = trim((string) ($row["location"] ?? ""));
                $message = trim((string) ($row["title"] ?? "Campus event")) . " is scheduled for {$dateLabel}" . ($location !== "" ? " at {$location}" : "") . ".";

                campuscare_notifications_upsert($conn, [
                    "user_id" => $userId,
                    "type" => "event",
                    "title" => "Upcoming event",
                    "message" => $message,
                    "related_id" => $eventId,
                    "related_type" => "event",
                    "event_key" => "event-reminder-participant-{$eventId}-" . date("YmdHi", $startsAt ?: time()),
                    "action_url" => "/campuscare-api/php-frontend/pages/events/event_detail.php?id=" . $eventId,
                ]);
            }

            $stmt->close();
        }
    }

    if (campuscare_notifications_column_exists($conn, "events", "created_by_user_id")) {
        $stmt = $conn->prepare("
            SELECT e.id, e.title, {$startExpression} AS starts_at
            FROM events e
            WHERE e.created_by_user_id = ?
            ORDER BY {$startExpression} DESC
            LIMIT 15
        ");

        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $eventId = intval($row["id"] ?? 0);
                $dateLabel = !empty($row["starts_at"]) ? date("M j, Y g:i A", strtotime((string) $row["starts_at"])) : "the schedule";
                campuscare_notifications_upsert($conn, [
                    "user_id" => $userId,
                    "type" => "event",
                    "title" => "Event published",
                    "message" => trim((string) ($row["title"] ?? "Campus event")) . " was published for {$dateLabel}.",
                    "related_id" => $eventId,
                    "related_type" => "event",
                    "event_key" => "event-created-owner-{$eventId}",
                    "action_url" => "/campuscare-api/php-frontend/pages/events/event_detail.php?id=" . $eventId,
                ]);
            }

            $stmt->close();
        }
    }
}

function campuscare_notifications_sync_admin_queue(mysqli $conn, int $userId): void
{
    if (campuscare_notifications_table_exists($conn, "appointments")) {
        $result = $conn->query("
            SELECT a.id, a.service, a.appointment_date, a.appointment_time, u.full_name AS student_name
            FROM appointments a
            INNER JOIN users u ON u.id = a.user_id
            WHERE COALESCE(NULLIF(a.status, ''), 'Pending') = 'Pending'
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 20
        ");

        while ($result && ($row = $result->fetch_assoc())) {
            $appointmentId = intval($row["id"] ?? 0);
            $dateLabel = campuscare_notifications_format_datetime((string) ($row["appointment_date"] ?? ""), (string) ($row["appointment_time"] ?? ""));
            campuscare_notifications_upsert($conn, [
                "user_id" => $userId,
                "type" => "appointment",
                "title" => "Approval needed",
                "message" => trim((string) ($row["student_name"] ?? "A student")) . " requested " . trim((string) ($row["service"] ?? "Counseling")) . " on {$dateLabel}.",
                "related_id" => $appointmentId,
                "related_type" => "appointment",
                "event_key" => "admin-appointment-pending-{$appointmentId}",
                "action_url" => "/campuscare-api/php-frontend/pages/appointments/manage_appointments.php",
            ]);
        }
    }

    if (campuscare_notifications_table_exists($conn, "referral_forms")) {
        $result = $conn->query("
            SELECT id, student_name, submitted_by_name
            FROM referral_forms
            WHERE status = 'Pending'
            ORDER BY created_at DESC
            LIMIT 20
        ");

        while ($result && ($row = $result->fetch_assoc())) {
            $referralId = intval($row["id"] ?? 0);
            campuscare_notifications_upsert($conn, [
                "user_id" => $userId,
                "type" => "system",
                "title" => "Referral awaiting review",
                "message" => trim((string) ($row["submitted_by_name"] ?? "A user")) . " submitted a referral for " . trim((string) ($row["student_name"] ?? "a student")) . ".",
                "related_id" => $referralId,
                "related_type" => "referral",
                "event_key" => "admin-referral-pending-{$referralId}",
                "action_url" => "/campuscare-api/php-frontend/pages/forms/referral_inbox.php?id=" . $referralId,
            ]);
        }
    }

    if (campuscare_notifications_table_exists($conn, "testing_requests")) {
        $result = $conn->query("
            SELECT id, target_student_name, requester_role
            FROM testing_requests
            WHERE status = 'Pending'
            ORDER BY created_at DESC
            LIMIT 20
        ");

        while ($result && ($row = $result->fetch_assoc())) {
            $requestId = intval($row["id"] ?? 0);
            campuscare_notifications_upsert($conn, [
                "user_id" => $userId,
                "type" => "system",
                "title" => "Testing request awaiting review",
                "message" => "A " . trim((string) ($row["requester_role"] ?? "user")) . " request for " . trim((string) ($row["target_student_name"] ?? "a student")) . " is pending review.",
                "related_id" => $requestId,
                "related_type" => "testing_request",
                "event_key" => "admin-testing-pending-{$requestId}",
                "action_url" => "/campuscare-api/php-frontend/pages/forms/testing_requests_inbox.php?id=" . $requestId,
            ]);
        }
    }

    if (campuscare_notifications_table_exists($conn, "users") && campuscare_notifications_column_exists($conn, "users", "created_at")) {
        $result = $conn->query("
            SELECT id, full_name, role
            FROM users
            ORDER BY created_at DESC
            LIMIT 15
        ");

        while ($result && ($row = $result->fetch_assoc())) {
            $accountId = intval($row["id"] ?? 0);
            campuscare_notifications_upsert($conn, [
                "user_id" => $userId,
                "type" => "security",
                "title" => "New account activity",
                "message" => trim((string) ($row["full_name"] ?? "A user")) . " was added as " . trim((string) ($row["role"] ?? "User")) . ".",
                "related_id" => $accountId,
                "related_type" => "user",
                "event_key" => "admin-user-created-{$accountId}",
                "action_url" => "/campuscare-api/php-frontend/pages/users/manage_users.php",
            ]);
        }
    }
}
