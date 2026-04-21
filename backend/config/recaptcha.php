<?php
$backendEnvPath = dirname(__DIR__) . "/.env";

if (is_readable($backendEnvPath)) {
    $envLines = file($backendEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($envLines !== false) {
        foreach ($envLines as $line) {
            $line = trim($line);

            if ($line === "" || $line[0] === "#") {
                continue;
            }

            $separatorIndex = strpos($line, "=");

            if ($separatorIndex === false) {
                continue;
            }

            $name = trim(substr($line, 0, $separatorIndex));
            $value = trim(substr($line, $separatorIndex + 1));
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if ($name === "") {
                continue;
            }

            $existingValue = getenv($name);

            if ($existingValue === false || trim((string) $existingValue) === "") {
                putenv($name . "=" . $value);
                $_ENV[$name] = $value;

                if (!isset($_SERVER[$name])) {
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

$RECAPTCHA_SECRET_KEY = getenv("RECAPTCHA_SECRET_KEY");

if ($RECAPTCHA_SECRET_KEY === false || trim((string) $RECAPTCHA_SECRET_KEY) === "") {
    $RECAPTCHA_SECRET_KEY = $_SERVER["RECAPTCHA_SECRET_KEY"] ?? "";
}

$RECAPTCHA_SECRET_KEY = trim((string) $RECAPTCHA_SECRET_KEY);

function verify_recaptcha_token(string $token, ?string $remoteIp = null): array
{
    global $RECAPTCHA_SECRET_KEY;

    if ($RECAPTCHA_SECRET_KEY === "") {
        return [
            "success" => false,
            "message" => "reCAPTCHA secret key is not configured on the server."
        ];
    }

    if ($token === "") {
        return [
            "success" => false,
            "message" => "reCAPTCHA token is missing."
        ];
    }

    $requestData = [
        "secret" => $RECAPTCHA_SECRET_KEY,
        "response" => $token
    ];

    if (!empty($remoteIp)) {
        $requestData["remoteip"] = $remoteIp;
    }

    $postFields = http_build_query($requestData);
    $rawResponse = false;

    if (function_exists("curl_init")) {
        $ch = curl_init("https://www.google.com/recaptcha/api/siteverify");

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded"]
        ]);

        $rawResponse = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/x-www-form-urlencoded\r\n",
                "content" => $postFields,
                "timeout" => 10
            ]
        ]);

        $rawResponse = @file_get_contents(
            "https://www.google.com/recaptcha/api/siteverify",
            false,
            $context
        );
    }

    if ($rawResponse === false) {
        return [
            "success" => false,
            "message" => "Unable to contact reCAPTCHA verification service."
        ];
    }

    $decoded = json_decode($rawResponse, true);

    if (!is_array($decoded) || !($decoded["success"] ?? false)) {
        $errorCodes = [];

        if (is_array($decoded) && isset($decoded["error-codes"]) && is_array($decoded["error-codes"])) {
            $errorCodes = $decoded["error-codes"];
        }

        return [
            "success" => false,
            "message" => empty($errorCodes)
                ? "reCAPTCHA verification failed."
                : "reCAPTCHA verification failed: " . implode(", ", $errorCodes)
        ];
    }

    return [
        "success" => true,
        "message" => "reCAPTCHA verification successful."
    ];
}
