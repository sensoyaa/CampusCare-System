<?php
require_once __DIR__ . "/../../includes/db.php";

$databaseName = "campuscare_db";
$connectionOk = !$conn->connect_errno;
$serverInfo = $conn->server_info ?? "unknown";
$currentDb = "";
$dbExists = false;
$requiredTables = ["users", "appointments", "events"];
$tableStatus = [];
$errorMessage = "";

if ($connectionOk) {
    $dbResult = $conn->query("SELECT DATABASE() AS db_name");
    if ($dbResult) {
        $row = $dbResult->fetch_assoc();
        $currentDb = (string) ($row["db_name"] ?? "");
    }

    $dbExists = $currentDb === $databaseName;

    $existingTables = [];
    $tablesResult = $conn->query("SHOW TABLES");
    if ($tablesResult) {
        while ($tableRow = $tablesResult->fetch_array(MYSQLI_NUM)) {
            $tableName = (string) ($tableRow[0] ?? "");
            if ($tableName !== "") {
                $existingTables[$tableName] = true;
            }
        }
    }

    foreach ($requiredTables as $tableName) {
        $tableStatus[$tableName] = isset($existingTables[$tableName]);
    }
} else {
    $errorMessage = $conn->connect_error ?? "Unknown connection error.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Status | CampusCare</title>
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fb;
            color: #0f172a;
        }

        .shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 760px;
            background: #fff;
            border: 1px solid #dbe3ee;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: -0.4px;
        }

        .subtitle {
            margin: 8px 0 20px;
            color: #64748b;
            font-size: 14px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 14px;
        }

        .status.ok {
            background: #e8f8ed;
            color: #166534;
            border: 1px solid #b7e4c7;
        }

        .status.fail {
            background: #fdecec;
            color: #991b1b;
            border: 1px solid #f8b4b4;
        }

        .grid {
            margin-top: 20px;
            display: grid;
            gap: 12px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
        }

        .label {
            color: #334155;
            font-weight: 600;
        }

        .value {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
        }

        .ok {
            color: #166534;
        }

        .fail {
            color: #991b1b;
        }

        .note {
            margin-top: 18px;
            font-size: 13px;
            color: #475569;
            line-height: 1.45;
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="card">
        <h1>Database Status Check</h1>
        <p class="subtitle">Quick health check for the CampusCare database connection.</p>

        <?php if ($connectionOk): ?>
            <div class="status ok">Connected to MySQL successfully</div>
        <?php else: ?>
            <div class="status fail">Database connection failed</div>
        <?php endif; ?>

        <div class="grid">
            <div class="row">
                <span class="label">Configured Database</span>
                <span class="value"><?php echo htmlspecialchars($databaseName); ?></span>
            </div>
            <div class="row">
                <span class="label">Current Selected Database</span>
                <span class="value <?php echo $currentDb === $databaseName ? "ok" : "fail"; ?>"><?php echo htmlspecialchars($currentDb !== "" ? $currentDb : "(none)"); ?></span>
            </div>
            <div class="row">
                <span class="label">Database Exists</span>
                <span class="value <?php echo $dbExists ? "ok" : "fail"; ?>"><?php echo $dbExists ? "Yes" : "No"; ?></span>
            </div>
            <div class="row">
                <span class="label">MySQL Server Version</span>
                <span class="value"><?php echo htmlspecialchars($serverInfo); ?></span>
            </div>
        </div>

        <div class="grid" style="margin-top: 18px;">
            <?php foreach ($tableStatus as $tableName => $exists): ?>
                <div class="row">
                    <span class="label">Table: <?php echo htmlspecialchars($tableName); ?></span>
                    <span class="value <?php echo $exists ? "ok" : "fail"; ?>"><?php echo $exists ? "Found" : "Missing"; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($errorMessage !== ""): ?>
            <p class="note"><strong>Error:</strong> <?php echo htmlspecialchars($errorMessage); ?></p>
        <?php else: ?>
            <p class="note">If any table is marked as Missing, import the latest SQL dump to complete setup.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
