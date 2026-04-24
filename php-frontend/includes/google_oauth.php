<?php

function campuscare_read_backend_env_value(string $targetKey): string
{
    $backendEnvPath = __DIR__ . "/../../backend/.env";

    if (!is_readable($backendEnvPath)) {
        return "";
    }

    $lines = file($backendEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return "";
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === "" || $line[0] === "#") {
            continue;
        }

        $separatorIndex = strpos($line, "=");

        if ($separatorIndex === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separatorIndex));

        if ($key !== $targetKey) {
            continue;
        }

        $value = trim(substr($line, $separatorIndex + 1));
        return trim($value, " \t\n\r\0\x0B\"'");
    }

    return "";
}

function campuscare_env_value(string $name): string
{
    $value = trim((string) (getenv($name) ?: ""));

    if ($value === "") {
        $value = trim((string) ($_SERVER[$name] ?? ""));
    }

    if ($value === "") {
        $value = campuscare_read_backend_env_value($name);
    }

    return trim($value);
}

function campuscare_google_oauth_config(): array
{
    $clientId = campuscare_env_value("GOOGLE_CLIENT_ID");
    $clientSecret = campuscare_env_value("GOOGLE_CLIENT_SECRET");
    $redirectUri = campuscare_env_value("GOOGLE_REDIRECT_URI");

    return [
        "client_id" => $clientId,
        "client_secret" => $clientSecret,
        "redirect_uri" => $redirectUri,
        "is_configured" => $clientId !== "" && $clientSecret !== "" && $redirectUri !== "",
    ];
}
