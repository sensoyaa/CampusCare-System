<?php

function appointment_audit_metadata_to_json($metadata): ?string
{
    if ($metadata === null) {
        return null;
    }

    if (is_string($metadata)) {
        $metadata = trim($metadata);
        return $metadata === '' ? null : $metadata;
    }

    if (is_array($metadata) || is_object($metadata)) {
        $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    return null;
}

function appointment_audit_insert(mysqli $conn, int $appointmentId, ?int $userId, string $action, $metadata = null): bool
{
    if ($appointmentId <= 0 || trim($action) === '') {
        return false;
    }

    $metadataJson = appointment_audit_metadata_to_json($metadata);
    $stmt = $conn->prepare(
        "INSERT INTO appointment_audit (appointment_id, user_id, action, metadata) VALUES (?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("iiss", $appointmentId, $userId, $action, $metadataJson);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}
