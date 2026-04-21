<?php
require_once __DIR__ . "/../../backend/config/recaptcha.php";

function read_env_value_from_file(string $filePath, string $targetKey): string
{
    if (!is_readable($filePath)) {
        return "";
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

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

function resolve_recaptcha_site_key(): string
{
    $siteKey = trim((string) (getenv("RECAPTCHA_SITE_KEY") ?: ""));

    if ($siteKey === "") {
        $siteKey = trim((string) ($_SERVER["RECAPTCHA_SITE_KEY"] ?? ""));
    }

    if ($siteKey === "") {
        $siteKey = read_env_value_from_file(__DIR__ . "/../../backend/.env", "RECAPTCHA_SITE_KEY");
    }

    return trim($siteKey);
}

$RECAPTCHA_SITE_KEY = resolve_recaptcha_site_key();
