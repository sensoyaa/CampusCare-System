<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Campus Events";

$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);

$colleges = [
    "College of Technology",
    "College of Public Administration and Governance",
    "College of Nursing",
    "College of Medicine",
    "College of Law",
    "College of Education",
    "College of Business",
    "College of Arts and Sciences",
];

function collegeAliases(string $college): array
{
    static $aliasMap = [
        "College of Technology" => ["college of technology", "technology"],
        "College of Public Administration and Governance" => ["college of public administration and governance", "public administration and governance"],
        "College of Nursing" => ["college of nursing", "nursing"],
        "College of Medicine" => ["college of medicine", "medicine"],
        "College of Law" => ["college of law", "law"],
        "College of Education" => ["college of education", "education"],
        "College of Business" => ["college of business", "business"],
        "College of Arts and Sciences" => ["college of arts and sciences", "college of art and sciences", "arts and sciences", "art and sciences"],
    ];

    return $aliasMap[$college] ?? [strtolower(trim($college))];
}

// Get event counts for each college
$collegeEventCounts = [];
foreach ($colleges as $college) {
    $aliases = collegeAliases($college);
    $placeholders = implode(", ", array_fill(0, count($aliases), "?"));
    $types = str_repeat("s", count($aliases));
    $countStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM events e
         LEFT JOIN users creator ON creator.id = e.created_by_user_id
         WHERE LOWER(TRIM(COALESCE(NULLIF(e.college, ''), creator.college, ''))) IN ({$placeholders})
           AND e.starts_at >= NOW()"
    );
    $countStmt->bind_param($types, ...$aliases);
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $collegeEventCounts[$college] = intval($countResult["total"] ?? 0);
    $countStmt->close();
}

$collegeIconMap = [
    "college of technology" => "Technology.png",
    "technology" => "Technology.png",
    "college of public administration and governance" => "Public Administration and Governance.png",
    "public administration and governance" => "Public Administration and Governance.png",
    "college of nursing" => "Nursing.png",
    "nursing" => "Nursing.png",
    "college of medicine" => "Medicine.png",
    "medicine" => "Medicine.png",
    "college of law" => "Law.png",
    "law" => "Law.png",
    "college of education" => "Education.png",
    "education" => "Education.png",
    "college of business" => "Business.png",
    "business" => "Business.png",
    "college of arts and sciences" => "Art and Sciences.png",
    "college of art and sciences" => "Art and Sciences.png",
    "arts and sciences" => "Art and Sciences.png",
    "art and sciences" => "Art and Sciences.png",
];

function collegeIconUrl(string $college, array $collegeIconMap): ?string
{
    $key = strtolower(trim($college));

    if (!isset($collegeIconMap[$key])) {
        return null;
    }

    return "/campuscare-api/php-frontend/assets/images/colleges/" . rawurlencode($collegeIconMap[$key]);
}

require_once __DIR__ . "/../../includes/header.php";
require_once __DIR__ . "/../../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <button class="menu-toggle" type="button" aria-label="Sidebar">
            <span class="menu-lines"></span>
        </button>

        <?php require_once __DIR__ . "/../../includes/topbar_user_dropdown.php"; ?>
    </div>

    <div class="content">
        <div class="page-shell">
            <div class="dashboard-head">
                <div>
                    <h1 class="page-title">Campus Events</h1>
                    <p class="page-subtitle">Discover workshops, brown bag sessions, forums and more</p>
                </div>
            </div>

            <!-- College Selection Section -->
            <section class="events-section">
                <h2 class="events-section-title">Select a College</h2>
                <div class="colleges-grid">
                    <?php foreach ($colleges as $college): ?>
                        <?php $collegeIconUrl = collegeIconUrl($college, $collegeIconMap); ?>
                        <a href="/campuscare-api/php-frontend/pages/events/view_college_events.php?college=<?php echo urlencode($college); ?>" class="college-selector-card">
                            <?php if (($collegeEventCounts[$college] ?? 0) > 0): ?>
                                <span class="college-event-badge"><?php echo $collegeEventCounts[$college]; ?></span>
                            <?php endif; ?>
                            <span class="college-selector-icon">
                                <?php if ($collegeIconUrl !== null): ?>
                                    <img src="<?php echo htmlspecialchars($collegeIconUrl); ?>" alt="" loading="lazy">
                                <?php endif; ?>
                            </span>
                            <h4><?php echo htmlspecialchars($college); ?></h4>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</main>

<script>
(function () {

})();
</script>
</body>
</html>
</html>
</html>

