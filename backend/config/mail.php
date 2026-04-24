<?php
require_once __DIR__ . "/env.php";

function smtp_read_response($socket): string
{
    $response = "";

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (isset($line[3]) && $line[3] === " ") {
            break;
        }
    }

    return $response;
}

function smtp_expect_code($socket, array $expectedCodes): array
{
    $response = smtp_read_response($socket);
    $code = intval(substr($response, 0, 3));

    return [
        "success" => in_array($code, $expectedCodes, true),
        "code" => $code,
        "response" => $response
    ];
}

function smtp_write_line($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function mailer_config(): array
{
    $smtpHost = env_value("SMTP_HOST", "");
    $smtpPort = intval(env_value("SMTP_PORT", "587"));
    $smtpKey = trim((string) env_value("SMTP_KEY", ""));
    $smtpUser = trim((string) env_value("SMTP_USER", env_value("SMTP_FROM_EMAIL", "")));
    $fromEmail = trim((string) env_value("SMTP_FROM_EMAIL", $smtpUser));
    $fromName = trim((string) env_value("SMTP_FROM_NAME", "CampusCare"));

    return [
        "host" => $smtpHost,
        "port" => $smtpPort,
        "password" => str_replace(" ", "", $smtpKey),
        "username" => $smtpUser,
        "from_email" => $fromEmail,
        "from_name" => $fromName
    ];
}

function mailer_validate_config(): array
{
    $config = mailer_config();
    $errors = [];

    if ($config["host"] === "") {
        $errors[] = "SMTP_HOST is missing.";
    }

    if ($config["port"] <= 0) {
        $errors[] = "SMTP_PORT must be a valid port number.";
    }

    if ($config["username"] === "") {
        $errors[] = "SMTP_USER or SMTP_FROM_EMAIL is required.";
    }

    if ($config["from_email"] === "" || !filter_var($config["from_email"], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "SMTP_FROM_EMAIL must be a valid email address.";
    }

    if ($config["password"] === "") {
        $errors[] = "SMTP_KEY is missing.";
    } elseif (!preg_match('/^[A-Za-z0-9]{16}$/', $config["password"])) {
        $errors[] = "SMTP_KEY should look like a 16-character app password.";
    }

    return [
        "success" => empty($errors),
        "config" => $config,
        "errors" => $errors
    ];
}

function campuscare_email_template(string $title, string $intro, string $bodyHtml, array $options = []): string
{
    $accent = $options["accent"] ?? "#2f6d9f";
    $badge = $options["badge"] ?? "CampusCare";
    $footer = $options["footer"] ?? "This is an automated CampusCare message.";
    $preview = $options["preview"] ?? $title;

    return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title, ENT_QUOTES, "UTF-8") . '</title>
</head>
<body style="margin:0; padding:0; background:#eef4f8; font-family:Arial, Helvetica, sans-serif; color:#183247;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">' . htmlspecialchars($preview, ENT_QUOTES, "UTF-8") . '</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef4f8; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; background:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 18px 45px rgba(30, 68, 96, 0.14);">
                    <tr>
                        <td style="background:linear-gradient(135deg, ' . $accent . ' 0%, #5ea8d8 100%); padding:28px 32px; color:#ffffff;">
                            <div style="font-size:13px; font-weight:700; letter-spacing:1.6px; text-transform:uppercase; opacity:0.9;">' . htmlspecialchars($badge, ENT_QUOTES, "UTF-8") . '</div>
                            <div style="font-size:32px; font-weight:700; margin-top:10px; line-height:1.2;">' . htmlspecialchars($title, ENT_QUOTES, "UTF-8") . '</div>
                            <div style="font-size:15px; line-height:1.7; margin-top:10px; max-width:480px; color:rgba(255,255,255,0.92);">' . htmlspecialchars($intro, ENT_QUOTES, "UTF-8") . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <div style="font-size:16px; line-height:1.8; color:#28465f;">' . $bodyHtml . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 28px; font-size:13px; line-height:1.7; color:#6f8598;">' . htmlspecialchars($footer, ENT_QUOTES, "UTF-8") . '</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

function send_smtp_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ""): array
{
    $validation = mailer_validate_config();

    if (!$validation["success"]) {
        return [
            "success" => false,
            "message" => implode(" ", $validation["errors"])
        ];
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            "success" => false,
            "message" => "Recipient email address is invalid."
        ];
    }

    $config = $validation["config"];
    $transport = $config["port"] === 465 ? "ssl://" : "";
    $socket = stream_socket_client(
        $transport . $config["host"] . ":" . $config["port"],
        $errorNumber,
        $errorMessage,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        return [
            "success" => false,
            "message" => "SMTP connection failed: " . $errorMessage
        ];
    }

    stream_set_timeout($socket, 20);

    $greeting = smtp_expect_code($socket, [220]);
    if (!$greeting["success"]) {
        fclose($socket);
        return ["success" => false, "message" => "SMTP greeting failed."];
    }

    smtp_write_line($socket, "EHLO localhost");
    $ehlo = smtp_expect_code($socket, [250]);

    if (!$ehlo["success"]) {
        fclose($socket);
        return ["success" => false, "message" => "SMTP EHLO failed."];
    }

    if ($config["port"] === 587) {
        smtp_write_line($socket, "STARTTLS");
        $startTls = smtp_expect_code($socket, [220]);

        if (!$startTls["success"] || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return ["success" => false, "message" => "Unable to start TLS for SMTP."];
        }

        smtp_write_line($socket, "EHLO localhost");
        $tlsEhlo = smtp_expect_code($socket, [250]);

        if (!$tlsEhlo["success"]) {
            fclose($socket);
            return ["success" => false, "message" => "SMTP EHLO after STARTTLS failed."];
        }
    }

    smtp_write_line($socket, "AUTH LOGIN");
    $authPrompt = smtp_expect_code($socket, [334]);

    if (!$authPrompt["success"]) {
        fclose($socket);
        return ["success" => false, "message" => "SMTP AUTH LOGIN failed to start."];
    }

    smtp_write_line($socket, base64_encode($config["username"]));
    $userPrompt = smtp_expect_code($socket, [334]);

    if (!$userPrompt["success"]) {
        fclose($socket);
        return ["success" => false, "message" => "SMTP username was rejected."];
    }

    smtp_write_line($socket, base64_encode($config["password"]));
    $authResult = smtp_expect_code($socket, [235]);

    if (!$authResult["success"]) {
        fclose($socket);
        return ["success" => false, "message" => "SMTP password was rejected."];
    }

    smtp_write_line($socket, "MAIL FROM:<" . $config["from_email"] . ">");
    $mailFrom = smtp_expect_code($socket, [250]);

    if (!$mailFrom["success"]) {
        fclose($socket);
        return ["success" => false, "message" => "SMTP sender address was rejected."];
    }

    smtp_write_line($socket, "RCPT TO:<" . $toEmail . ">");
    $recipient = smtp_expect_code($socket, [250, 251]);

    if (!$recipient["success"]) {
        fclose($socket);
        return ["success" => false, "message" => "SMTP recipient address was rejected."];
    }

    smtp_write_line($socket, "DATA");
    $dataPrompt = smtp_expect_code($socket, [354]);

    if (!$dataPrompt["success"]) {
        fclose($socket);
        return ["success" => false, "message" => "SMTP DATA command failed."];
    }

    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $safeFromName = str_replace(['"', "\r", "\n"], "", $config["from_name"]);
    $safeToName = str_replace(['"', "\r", "\n"], "", $toName);
    $boundary = "campuscare_" . bin2hex(random_bytes(12));
    $plainBody = $textBody !== "" ? $textBody : trim(strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $htmlBody)));

    $messageLines = [
        "From: \"" . addslashes($safeFromName) . "\" <" . $config["from_email"] . ">",
        "To: \"" . addslashes($safeToName) . "\" <" . $toEmail . ">",
        "Subject: " . $encodedSubject,
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"",
        "",
        "--" . $boundary,
        "Content-Type: text/plain; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
        "",
        $plainBody,
        "",
        "--" . $boundary,
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
        "",
        $htmlBody,
        "",
        "--" . $boundary . "--",
        "."
    ];

    fwrite($socket, implode("\r\n", $messageLines) . "\r\n");
    $sendResult = smtp_expect_code($socket, [250]);

    smtp_write_line($socket, "QUIT");
    fclose($socket);

    if (!$sendResult["success"]) {
        return ["success" => false, "message" => "SMTP server did not accept the email body."];
    }

    return ["success" => true];
}
?>
