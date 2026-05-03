<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/mail.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id = intval($data["user_id"] ?? 0);
$counselor_id = isset($data["counselor_id"]) && intval($data["counselor_id"]) > 0 ? intval($data["counselor_id"]) : null;
$service = trim($data["service"] ?? "");
$counselor = trim($data["counselor"] ?? "");

if ($counselor_id === null || $counselor === "") {
    $counselor = "Unassigned";
    $counselor_id = null;
}

$appointment_date = trim($data["appointment_date"] ?? "");
$appointment_time = trim($data["appointment_time"] ?? "");

if (
    $user_id <= 0 ||
    $service === "" ||
    $appointment_date === "" ||
    $appointment_time === ""
) {
    echo json_encode([
        "success" => false,
        "message" => "All appointment fields are required."
    ]);
    exit();
}

$studentStmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ? LIMIT 1");

if (!$studentStmt) {
    echo json_encode([
        "success" => false,
        "message" => "Unable to load student information."
    ]);
    exit();
}

$studentStmt->bind_param("i", $user_id);
$studentStmt->execute();
$student = $studentStmt->get_result()->fetch_assoc();
$studentStmt->close();

if (!$student) {
    echo json_encode([
        "success" => false,
        "message" => "Student account not found."
    ]);
    exit();
}

$counselorUser = null;

if ($counselor_id !== null) {
    $counselorStmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ? LIMIT 1");

    if (!$counselorStmt) {
        echo json_encode([
            "success" => false,
            "message" => "Unable to load counselor information."
        ]);
        exit();
    }

    $counselorStmt->bind_param("i", $counselor_id);
    $counselorStmt->execute();
    $counselorUser = $counselorStmt->get_result()->fetch_assoc();
    $counselorStmt->close();

    if (!$counselorUser) {
        echo json_encode([
            "success" => false,
            "message" => "Counselor account not found."
        ]);
        exit();
    }
}

$slotAlreadyBooked = false;

if ($counselor_id !== null) {
    $slotCheckStmt = $conn->prepare("
        SELECT id
        FROM appointments
        WHERE counselor_id = ?
          AND appointment_date = ?
          AND appointment_time = ?
          AND COALESCE(NULLIF(status, ''), 'Pending') NOT IN ('Cancelled', 'Rejected')
        LIMIT 1
    ");

    if (!$slotCheckStmt) {
        echo json_encode([
            "success" => false,
            "message" => "Unable to validate appointment slot."
        ]);
        exit();
    }

    $slotCheckStmt->bind_param("iss", $counselor_id, $appointment_date, $appointment_time);
    $slotCheckStmt->execute();
    $slotAlreadyBooked = $slotCheckStmt->get_result()->fetch_assoc() !== null;
    $slotCheckStmt->close();

    if ($slotAlreadyBooked) {
        echo json_encode([
            "success" => false,
            "message" => "This time slot is already booked. Please choose another available time."
        ]);
        exit();
    }
}

$stmt = $conn->prepare("
    INSERT INTO appointments 
    (user_id, counselor_id, service, counselor, appointment_date, appointment_time, status)
    VALUES (?, ?, ?, ?, ?, ?, 'Pending')
");

$stmt->bind_param(
    "iissss",
    $user_id,
    $counselor_id,
    $service,
    $counselor,
    $appointment_date,
    $appointment_time
);

if ($stmt->execute()) {
    $displayDate = date("F j, Y", strtotime($appointment_date));
    $displayTime = date("g:i A", strtotime($appointment_time));
    $studentName = trim((string) ($student["full_name"] ?? "Student"));
    $studentEmail = trim((string) ($student["email"] ?? ""));
    $counselorName = trim((string) ($counselorUser["full_name"] ?? $counselor));
    $counselorEmail = trim((string) ($counselorUser["email"] ?? ""));
    $emailResult = [
        "success" => false,
        "message" => "Student email address is missing."
    ];
    $counselorEmailResult = [
        "success" => false,
        "message" => "Counselor email address is missing."
    ];

    if ($studentEmail !== "") {
        $subject = "CampusCare Appointment Confirmation";
        $htmlBody = campuscare_email_template(
            "Appointment Confirmation",
            "Your appointment request has been received and is now pending review.",
            "
            <p style=\"margin:0 0 16px;\">Hello " . htmlspecialchars($studentName, ENT_QUOTES, "UTF-8") . ",</p>
            <p style=\"margin:0 0 24px;\">Your CampusCare appointment request has been submitted successfully.</p>
            <div style=\"margin:0 0 24px; padding:22px; border-radius:20px; background:#f4f9fc; border:1px solid #d5e7f2;\">
                <div style=\"font-size:13px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:#5d7a91; margin-bottom:14px;\">Appointment Details</div>
                <div style=\"margin-bottom:10px;\"><strong>Service:</strong> " . htmlspecialchars($service, ENT_QUOTES, "UTF-8") . "</div>
                <div style=\"margin-bottom:10px;\"><strong>Counselor:</strong> " . htmlspecialchars($counselor, ENT_QUOTES, "UTF-8") . "</div>
                <div style=\"margin-bottom:10px;\"><strong>Date:</strong> " . htmlspecialchars($displayDate, ENT_QUOTES, "UTF-8") . "</div>
                <div style=\"margin-bottom:10px;\"><strong>Time:</strong> " . htmlspecialchars($displayTime, ENT_QUOTES, "UTF-8") . "</div>
                <div><strong>Status:</strong> Pending</div>
            </div>
            <div style=\"margin:0 0 24px; padding:18px 20px; border-radius:18px; background:#eef8ef; border:1px solid #cfe4d1; color:#2d5d35;\">
                We will notify you once there is an update from the guidance office.
            </div>
            <p style=\"margin:0;\">Please keep this message for your reference.</p>
            ",
            [
                "preview" => "Your CampusCare appointment request has been received.",
                "footer" => "Need to make changes? Please contact the guidance office or use your CampusCare account."
            ]
        );
        $textBody = "Hello {$studentName},\n\nYour appointment request has been received successfully.\n\nService: {$service}\nCounselor: {$counselor}\nDate: {$displayDate}\nTime: {$displayTime}\nStatus: Pending\n\nPlease wait for further updates from CampusCare.";
        $emailResult = send_smtp_mail($studentEmail, $studentName, $subject, $htmlBody, $textBody);
    }

    if ($counselorEmail !== "") {
        $subject = "New CampusCare Appointment Request";
        $htmlBody = campuscare_email_template(
            "New Appointment Request",
            "A student has booked an appointment with you in CampusCare.",
            "
            <p style=\"margin:0 0 16px;\">Hello " . htmlspecialchars($counselorName, ENT_QUOTES, "UTF-8") . ",</p>
            <p style=\"margin:0 0 24px;\">A new appointment request has been assigned to you.</p>
            <div style=\"margin:0 0 24px; padding:22px; border-radius:20px; background:#f4f9fc; border:1px solid #d5e7f2;\">
                <div style=\"font-size:13px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:#5d7a91; margin-bottom:14px;\">Appointment Details</div>
                <div style=\"margin-bottom:10px;\"><strong>Student:</strong> " . htmlspecialchars($studentName, ENT_QUOTES, "UTF-8") . "</div>
                <div style=\"margin-bottom:10px;\"><strong>Service:</strong> " . htmlspecialchars($service, ENT_QUOTES, "UTF-8") . "</div>
                <div style=\"margin-bottom:10px;\"><strong>Date:</strong> " . htmlspecialchars($displayDate, ENT_QUOTES, "UTF-8") . "</div>
                <div style=\"margin-bottom:10px;\"><strong>Time:</strong> " . htmlspecialchars($displayTime, ENT_QUOTES, "UTF-8") . "</div>
                <div><strong>Status:</strong> Pending</div>
            </div>
            <p style=\"margin:0;\">Please check your CampusCare schedule for the full appointment details.</p>
            ",
            [
                "preview" => "A new appointment request has been assigned to you.",
                "footer" => "This notification was sent because you are the assigned counselor for this appointment."
            ]
        );
        $textBody = "Hello {$counselorName},\n\nA new appointment request has been assigned to you.\n\nStudent: {$studentName}\nService: {$service}\nDate: {$displayDate}\nTime: {$displayTime}\nStatus: Pending\n\nPlease check your CampusCare schedule for the full appointment details.";
        $counselorEmailResult = send_smtp_mail($counselorEmail, $counselorName, $subject, $htmlBody, $textBody);
    }

    echo json_encode([
        "success" => true,
        "message" => $emailResult["success"]
            ? "Appointment booked successfully. Confirmation email sent."
            : "Appointment booked successfully, but the confirmation email could not be sent.",
        "email_sent" => $emailResult["success"],
        "email_error" => $emailResult["success"] ? null : $emailResult["message"],
        "counselor_email_sent" => $counselorEmailResult["success"],
        "counselor_email_error" => $counselorEmailResult["success"] ? null : $counselorEmailResult["message"]
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to save appointment."
    ]);
}

$stmt->close();
$conn->close();
?>
