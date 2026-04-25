<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

http_response_code(404);

$isLoggedIn = isset($_SESSION["user_id"]);
$returnHref = $isLoggedIn ? "/campuscare-api/php-frontend/pages/dashboard/dashboard.php" : "/campuscare-api/php-frontend/index.php";
$returnLabel = $isLoggedIn ? "Return to Dashboard" : "Return to Home";
$requestedPath = trim((string) ($_SERVER["REQUEST_URI"] ?? ""));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 | CampusCare</title>
    <link rel="stylesheet" href="/campuscare-api/php-frontend/assets/style.css">
</head>
<body>
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #eef3f8; padding: 22px;">
        <div class="card" style="max-width: 560px; width: 100%; text-align: center;">
            <h1 class="page-title" style="font-size: 64px; margin-bottom: 8px;">404</h1>
            <p style="font-size: 24px; font-weight: 700; margin-bottom: 8px;">Oops! Page not found</p>
            <p class="page-subtitle" style="margin-bottom: 18px;">
                The page you are trying to access does not exist.
            </p>

            <?php if ($requestedPath !== ""): ?>
                <p class="mono" style="margin-bottom: 18px;">Requested path: <?php echo htmlspecialchars($requestedPath); ?></p>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($returnHref); ?>" class="small-link"><?php echo htmlspecialchars($returnLabel); ?></a>
        </div>
    </div>
</body>
</html>
