<?php

function load_env_file(string $filePath): array
{
    static $cache = [];

    if (isset($cache[$filePath])) {
        return $cache[$filePath];
    }

    $values = [];

    if (!is_file($filePath)) {
        $cache[$filePath] = $values;
        return $values;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === "" || str_starts_with($trimmed, "#")) {
            continue;
        }

        $parts = explode("=", $line, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($key === "") {
            continue;
        }

        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
            ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $values[$key] = $value;
    }

    $cache[$filePath] = $values;

    return $values;
}

function env_value(string $key, ?string $default = null, ?string $envFile = null): ?string
{
    $envFile = $envFile ?? dirname(__DIR__) . "/.env";
    $values = load_env_file($envFile);

    return $values[$key] ?? $default;
}
?>
