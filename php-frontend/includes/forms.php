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
            student_email VARCHAR(160) DEFAULT NULL,
            course_year_section VARCHAR(160) NOT NULL,
            is_external_student BOOLEAN DEFAULT FALSE,
            description TEXT DEFAULT NULL,
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
            is_anonymous BOOLEAN DEFAULT FALSE,
            email_notification_sent BOOLEAN DEFAULT FALSE,
            email_notification_date DATETIME DEFAULT NULL,
            intake_form_id INT UNSIGNED DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'Pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_referral_submitter (submitted_by_user_id),
            KEY idx_referral_student (student_user_id),
            KEY idx_referral_status (status),
            KEY idx_referral_counselor (referred_to_counselor_id),
            KEY idx_referral_external (is_external_student, student_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    return $conn->query($sql) !== false;
}

function campuscare_ensure_referral_intake_forms_table(mysqli $conn): bool
{
    $sql = "
        CREATE TABLE IF NOT EXISTS referral_intake_forms (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            referral_id INT UNSIGNED NOT NULL,
            student_user_id INT UNSIGNED DEFAULT NULL,
            student_name VARCHAR(160) NOT NULL,
            student_email VARCHAR(160) DEFAULT NULL,
            intake_datetime DATETIME NOT NULL,
            why_visiting TEXT DEFAULT NULL,
            what_concerns TEXT DEFAULT NULL,
            how_long VARCHAR(160) DEFAULT NULL,
            previous_counseling BOOLEAN DEFAULT FALSE,
            emergency_contact VARCHAR(160) DEFAULT NULL,
            completed_by_student BOOLEAN DEFAULT FALSE,
            reviewed_by_counselor_id INT UNSIGNED DEFAULT NULL,
            reviewed_by_counselor_name VARCHAR(160) DEFAULT NULL,
            counselor_notes TEXT DEFAULT NULL,
            counselor_approved BOOLEAN DEFAULT FALSE,
            approval_datetime DATETIME DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'Pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_intake_referral (referral_id),
            KEY idx_intake_student (student_user_id),
            KEY idx_intake_status (status)
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

function campuscare_send_referral_notification(string $studentEmail, string $studentName, string $referrerName, string $referralReason): bool
{
    require_once __DIR__ . "/mail.php";
    
    $subject = "You've Been Referred to Student Services - Action Required";
    
    $htmlBody = "
    <h2>Hello {$studentName},</h2>
    <p>You have been referred to our counseling services by <strong>{$referrerName}</strong>.</p>
    
    <div style='background: #f0f4f8; padding: 15px; border-left: 4px solid #0066cc; margin: 20px 0;'>
        <p><strong>Reason for Referral:</strong><br>{$referralReason}</p>
    </div>
    
    <h3>What You Need to Do:</h3>
    <ol>
        <li>Visit the guidance office at your earliest convenience</li>
        <li>Check in with the receptionist and mention this referral</li>
        <li>A counselor will help guide you through the next steps</li>
        <li>You'll have a short intake conversation to discuss your concerns</li>
    </ol>
    
    <p><strong>Why This Matters:</strong><br>
    This referral means someone cares about your well-being. Speaking with a counselor can help you:
    </p>
    <ul>
        <li>Address concerns in a confidential setting</li>
        <li>Develop coping strategies</li>
        <li>Find resources and support</li>
    </ul>
    
    <p style='margin-top: 30px; color: #666;'>
        <strong>Have questions?</strong> Stop by the Student Services office or call the main office.<br>
        <em>All conversations are confidential and judgment-free.</em>
    </p>
    ";
    
    $textBody = "Hello {$studentName},\n\n";
    $textBody .= "You have been referred to our counseling services by {$referrerName}.\n\n";
    $textBody .= "Reason for Referral: {$referralReason}\n\n";
    $textBody .= "What You Need to Do:\n";
    $textBody .= "1. Visit the guidance office at your earliest convenience\n";
    $textBody .= "2. Check in with the receptionist and mention this referral\n";
    $textBody .= "3. A counselor will help guide you through the next steps\n";
    $textBody .= "4. You'll have a short intake conversation to discuss your concerns\n\n";
    $textBody .= "All conversations are confidential and judgment-free.";
    
    return campuscare_send_email(
        $studentEmail,
        $subject,
        $htmlBody,
        $textBody
    );
}

