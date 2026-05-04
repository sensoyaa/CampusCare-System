<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Mental Health Test";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);

if ($role !== "Student") {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

$questions = [
    "Over the past 2 weeks, how often have you felt down, depressed, or hopeless?",
    "How often have you had little interest or pleasure in doing things?",
    "How often have you felt nervous, anxious, or on edge?",
    "How often have you had trouble falling or staying asleep, or sleeping too much?",
    "How often have you felt tired or had little energy?",
];

$options = [
    "Not at all",
    "Several days",
    "More than half the days",
    "Nearly every day",
];

$totalQuestions = count($questions);
$current = intval($_POST["current"] ?? 0);
$answers = $_POST["answers"] ?? [];
$submitted = false;
$message = "";
$level = "";
$score = 0;
$reactionEmoji = "";
$reactionTitle = "";

for ($i = 0; $i < $totalQuestions; $i++) {
    if (!isset($answers[$i])) {
        $answers[$i] = "";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["go_prev"])) {
        $current = max(0, $current - 1);
    } elseif (isset($_POST["go_next"])) {
        if ($answers[$current] !== "") {
            $current = min($totalQuestions - 1, $current + 1);
        }
    } elseif (isset($_POST["jump_to"])) {
        $jumpIndex = intval($_POST["jump_to"]);
        if ($jumpIndex >= 0 && $jumpIndex < $totalQuestions) {
            $current = $jumpIndex;
        }
    } elseif (isset($_POST["submit_test"])) {
        $allAnswered = true;

        for ($i = 0; $i < $totalQuestions; $i++) {
            if ($answers[$i] === "") {
                $allAnswered = false;
                $current = $i;
                break;
            }
        }

        if ($allAnswered) {
            $score = 0;

            for ($i = 0; $i < $totalQuestions; $i++) {
                $score += intval($answers[$i]);
            }

            if ($score <= 4) {
                $level = "Low";
                $message = "You seem to be doing well. Keep up with healthy habits and self-care routines.";
                $reactionEmoji = "😊";
                $reactionTitle = "You look steady";
            } elseif ($score <= 9) {
                $level = "Moderate";
                $message = "Consider talking to a counselor for additional support. It is okay to seek help.";
                $reactionEmoji = "🌤️";
                $reactionTitle = "A little support may help";
            } else {
                $level = "High";
                $message = "We recommend scheduling an appointment with the guidance office for support.";
                $reactionEmoji = "💙";
                $reactionTitle = "Please reach out soon";
            }

            $answer1Text = $options[intval($answers[0])];
            $answer2Text = $options[intval($answers[1])];
            // Include numeric score in result_text so dashboard can parse and display percentage/trend
            $resultText = "Stress Level: " . $level . " | Score: " . intval($score) . "/" . ($totalQuestions * 3) . " | Reaction: " . $reactionEmoji . " | " . $message;

            $stmt = $conn->prepare(
                "INSERT INTO mental_health_tests (user_id, answer_1, answer_2, result_text)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("isss", $userId, $answer1Text, $answer2Text, $resultText);
            $submitted = $stmt->execute();
            $stmt->close();

            if (!$submitted) {
                $message = "Failed to save assessment result. Please try again.";
            }
        }
    } elseif (isset($_POST["retake"])) {
        $answers = array_fill(0, $totalQuestions, "");
        $current = 0;
    }
}

$answeredCount = 0;
for ($i = 0; $i < $totalQuestions; $i++) {
    if ($answers[$i] !== "") {
        $answeredCount++;
    }
}

$progress = ($answeredCount / $totalQuestions) * 100;

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
        <div class="page-shell test-shell">
            <?php if ($submitted): ?>
                <div class="card" style="max-width: 660px; margin: 0 auto; text-align: center;">
                    <h1 class="page-title" style="font-size: 34px; margin-bottom: 10px;">Assessment Complete</h1>
                    <p class="page-subtitle" style="margin-bottom: 18px;">Your response has been recorded.</p>

                    <div class="card" style="background: <?php echo $level === "High" ? "#fff4f4" : ($level === "Moderate" ? "#f2fbf3" : "#f4f8ff"); ?>; border: 1px solid <?php echo $level === "High" ? "#f3c8c8" : ($level === "Moderate" ? "#cfead4" : "#d7e6f7"); ?>; border-radius: 16px; padding: 18px; margin-bottom: 18px; text-align: left;">
                        <div style="display:flex; align-items:center; gap:14px; margin-bottom:10px;">
                            <div style="width:56px; height:56px; border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:28px; background: rgba(255,255,255,0.7);">
                                <?php echo htmlspecialchars($reactionEmoji); ?>
                            </div>
                            <div>
                                <strong style="display:block; font-size:18px; color:#1e2f40;"><?php echo htmlspecialchars($reactionTitle); ?></strong>
                                <span style="color:#6f8295; font-size:13px;">Stress level: <?php echo htmlspecialchars($level); ?></span>
                            </div>
                        </div>
                        <p style="margin:0; color:#35536f; line-height:1.5;"><?php echo htmlspecialchars($message); ?></p>
                    </div>

                    <div class="test-actions" style="justify-content: center;">
                        <form method="POST">
                            <?php for ($i = 0; $i < $totalQuestions; $i++): ?>
                                <input type="hidden" name="answers[<?php echo $i; ?>]" value="">
                            <?php endfor; ?>
                            <button type="submit" name="retake" class="btn-secondary btn">Retake</button>
                        </form>

                        <a href="/campuscare-api/php-frontend/pages/appointments/book_appointment.php" class="btn btn-primary">Book Appointment</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="test-head-top">
                    
                    <div class="test-head">
                        <div>
                            <h1 class="page-title" style="font-size: 40px; margin-bottom: 3px;">Mental Health Check-in</h1>
                            <p class="page-subtitle" style="color: #ffffff;">Quick self-assessment</p>
                        </div>
                    </div>

                    <div class="progress-info" style="color: white;">
                        <span>Question <?php echo $current + 1; ?> of <?php echo $totalQuestions; ?></span>
                        <span><?php echo $answeredCount; ?> answered</span>
                    </div>

                    <div class="progress-track" style="color: white;">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="current" value="<?php echo $current; ?>">

                    <?php for ($i = 0; $i < $totalQuestions; $i++): ?>
                        <input type="hidden" name="answers[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($answers[$i]); ?>">
                    <?php endfor; ?>

                    <div class="question-card">
                        <h2 class="question-text"><?php echo htmlspecialchars($questions[$current]); ?></h2>

                        <div class="option-list">
                            <?php foreach ($options as $index => $opt): ?>
                                <?php
                                    $isSelected = strval($answers[$current]) === strval($index);
                                    $optionClass = $isSelected ? "option-btn selected" : "option-btn";
                                ?>
                                <button
                                    type="submit"
                                    name="answers[<?php echo $current; ?>]"
                                    value="<?php echo $index; ?>"
                                    class="<?php echo $optionClass; ?>"
                                >
                                    <span class="letter"><?php echo chr(65 + $index); ?></span>
                                    <?php echo htmlspecialchars($opt); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="test-actions">
                        <button
                            type="submit"
                            name="go_prev"
                            class="btn btn-secondary"
                            <?php echo $current === 0 ? "disabled" : ""; ?>
                        >
                            Back
                        </button>

                        <?php if ($current < $totalQuestions - 1): ?>
                            <button
                                type="submit"
                                name="go_next"
                                class="btn btn-primary btn-next"
                                <?php echo $answers[$current] === "" ? "disabled" : ""; ?>
                            >
                                Next
                            </button>
                        <?php else: ?>
                            <button
                                type="submit"
                                name="submit_test"
                                class="btn btn-primary btn-next"
                            >
                                Submit Assessment
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="dot-nav">
                        <?php for ($i = 0; $i < $totalQuestions; $i++): ?>
                            <?php
                                $dotClass = "dot";
                                if ($i === $current) {
                                    $dotClass .= " active";
                                } elseif ($answers[$i] !== "") {
                                    $dotClass .= " done";
                                }
                            ?>

                            <button
                                type="submit"
                                name="jump_to"
                                value="<?php echo $i; ?>"
                                class="<?php echo $dotClass; ?>"
                                aria-label="Go to question <?php echo $i + 1; ?>"
                            ></button>
                        <?php endfor; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <a href="#" class="chat-fab" aria-label="Open chat">?</a>
    </div>
</main>

</div>
<script>
(function () {

})();
</script>
</body>
</html>


