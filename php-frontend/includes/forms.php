<?php

function campuscare_forms_table_exists(mysqli $conn, string $tableName): bool
{
    $safe = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result !== false && $result->num_rows > 0;
}

function campuscare_ensure_referral_forms_table(mysqli $conn): bool
{
    $sql = "
        CREATE TABLE IF NOT EXISTS referral_forms (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            submitted_by_user_id INT UNSIGNED NOT NULL,
            submitted_by_name VARCHAR(160) NOT NULL,
            submitted_by_role VARCHAR(60) NOT NULL,
            referred_to_counselor_id INT UNSIGNED DEFAULT NULL,
            referred_to_counselor_name VARCHAR(160) NOT NULL,
            referral_datetime DATETIME NOT NULL,
            student_user_id INT UNSIGNED DEFAULT NULL,
            student_name VARCHAR(160) NOT NULL,
            course_year_section VARCHAR(160) NOT NULL,
            date_received DATE DEFAULT NULL,
            received_by VARCHAR(160) DEFAULT NULL,
            reasons_json LONGTEXT NOT NULL,
            other_reason TEXT DEFAULT NULL,
            faculty_signature_typed VARCHAR(160) DEFAULT NULL,
            faculty_signature_drawn LONGTEXT DEFAULT NULL,
            actions_taken TEXT DEFAULT NULL,
            actions_datetime DATETIME DEFAULT NULL,
            counselor_signature_typed VARCHAR(160) DEFAULT NULL,
            counselor_signature_drawn LONGTEXT DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'Pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_referral_submitter (submitted_by_user_id),
            KEY idx_referral_student (student_user_id),
            KEY idx_referral_status (status),
            KEY idx_referral_counselor (referred_to_counselor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    return $conn->query($sql) !== false;
}

function campuscare_ensure_testing_requests_table(mysqli $conn): bool
{
    $sql = "
        CREATE TABLE IF NOT EXISTS testing_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            requester_user_id INT UNSIGNED NOT NULL,
            requester_role VARCHAR(60) NOT NULL,
            applicant_name_signature_typed VARCHAR(160) DEFAULT NULL,
            applicant_name_signature_drawn LONGTEXT DEFAULT NULL,
            request_date DATE NOT NULL,
            target_student_user_id INT UNSIGNED DEFAULT NULL,
            target_student_name VARCHAR(160) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            organization_office VARCHAR(180) DEFAULT NULL,
            purpose TEXT DEFAULT NULL,
            counselor_type_of_tests TEXT DEFAULT NULL,
            counselor_notes TEXT DEFAULT NULL,
            reviewed_by_user_id INT UNSIGNED DEFAULT NULL,
            reviewed_by_name VARCHAR(160) DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'Pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_testing_requester (requester_user_id),
            KEY idx_testing_student (target_student_user_id),
            KEY idx_testing_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    return $conn->query($sql) !== false;
}

function campuscare_signature_present(string $typed, string $drawn): bool
{
    return trim($typed) !== "" || trim($drawn) !== "";
}

function campuscare_status_choices(): array
{
    return ["Pending", "In Review", "For Scheduling", "Completed", "Closed"];
}

function campuscare_forms_can_manage(string $role): bool
{
    $normalizedRole = normalizeRole($role);
    return in_array($normalizedRole, ["Counselor", "Administrator"], true);
}
