<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Event Details";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = $_SESSION["full_name"] ?? "User";

$eventId = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

if ($eventId <= 0) {
    header("Location: /campuscare-api/php-frontend/pages/events/events.php");
    exit();
}

// Get event details
$eventStmt = $conn->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id) as participant_count,
           (SELECT COUNT(*) FROM event_checkins WHERE event_id = e.id) as checkin_count
    FROM events e
    WHERE e.id = ?
");
$eventStmt->bind_param("i", $eventId);
$eventStmt->execute();
$event = $eventStmt->get_result()->fetch_assoc();
$eventStmt->close();

if (!$event) {
    header("Location: /campuscare-api/php-frontend/pages/events/events.php");
    exit();
}

// Check if user is joined
$isJoined = false;
$joinedAt = null;
$joinStmt = $conn->prepare("
    SELECT joined_at 
    FROM event_participants 
    WHERE event_id = ? AND user_id = ?
");
$joinStmt->bind_param("ii", $eventId, $userId);
$joinStmt->execute();
$joinResult = $joinStmt->get_result()->fetch_assoc();
if ($joinResult) {
    $isJoined = true;
    $joinedAt = $joinResult["joined_at"];
}
$joinStmt->close();

// Check if user has checked in
$isCheckedIn = false;
$checkedInAt = null;
$checkinStmt = $conn->prepare("
    SELECT checked_in_at 
    FROM event_checkins 
    WHERE event_id = ? AND user_id = ?
");
$checkinStmt->bind_param("ii", $eventId, $userId);
$checkinStmt->execute();
$checkinResult = $checkinStmt->get_result()->fetch_assoc();
if ($checkinResult) {
    $isCheckedIn = true;
    $checkedInAt = $checkinResult["checked_in_at"];
}
$checkinStmt->close();

// Check if user has provided feedback
$hasFeedback = false;
$feedbackStmt = $conn->prepare("
    SELECT id, rating, feedback, is_anonymous 
    FROM event_feedback 
    WHERE event_id = ? AND user_id = ?
");
$feedbackStmt->bind_param("ii", $eventId, $userId);
$feedbackStmt->execute();
$feedbackResult = $feedbackStmt->get_result()->fetch_assoc();
if ($feedbackResult) {
    $hasFeedback = true;
}
$feedbackStmt->close();

// Get event comments with user info
$comments = [];
$commentsStmt = $conn->prepare("
    SELECT c.*, u.full_name, u.student_id
    FROM event_comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.event_id = ? AND c.parent_id IS NULL
    ORDER BY c.created_at DESC
");
$commentsStmt->bind_param("i", $eventId);
$commentsStmt->execute();
$commentsResult = $commentsStmt->get_result();
while ($row = $commentsResult->fetch_assoc()) {
    $comments[] = $row;
}
$commentsStmt->close();

// Get event feedback statistics
$avgRating = 0;
$totalRatings = 0;
$ratingStmt = $conn->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings
    FROM event_feedback
    WHERE event_id = ?
");
$ratingStmt->bind_param("i", $eventId);
$ratingStmt->execute();
$ratingResult = $ratingStmt->get_result()->fetch_assoc();
if ($ratingResult) {
    $avgRating = floatval($ratingResult["avg_rating"] ?? 0);
    $totalRatings = intval($ratingResult["total_ratings"] ?? 0);
}
$ratingStmt->close();

// Calculate event status
$currentTime = time();
$eventStart = strtotime($event["starts_at"]);
$eventEnd = !empty($event["ends_at"]) ? strtotime($event["ends_at"]) : ($eventStart + (2 * 60 * 60));
$hasEventStarted = $currentTime >= $eventStart;
$hasEventEnded = $currentTime >= $eventEnd;
$checkInStart = $eventStart - (20 * 60);
$checkInEnd = $eventEnd;
$isCheckInWindow = $currentTime >= $checkInStart && $currentTime <= $checkInEnd;

// Format event times
$startDateTime = new DateTime($event["starts_at"]);
$dateStr = $startDateTime->format("F j, Y");
$timeStr = $startDateTime->format("g:i A");
$endTimeStr = !empty($event["ends_at"]) ? (new DateTime($event["ends_at"]))->format("g:i A") : "";
$displayTime = $endTimeStr !== "" ? $timeStr . " - " . $endTimeStr : $timeStr;

// Handle form submissions
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? ""));

    // Join/Unjoin event
    if ($action === "join") {
        if (!$isJoined) {
            $insertStmt = $conn->prepare("
                INSERT INTO event_participants (event_id, user_id) 
                VALUES (?, ?)
            ");
            $insertStmt->bind_param("ii", $eventId, $userId);
            if ($insertStmt->execute()) {
                $isJoined = true;
                $joinedAt = date("Y-m-d H:i:s");
                $success = "You have successfully joined the event!";
            } else {
                $error = "Failed to join the event. Please try again.";
            }
            $insertStmt->close();
        }
    } elseif ($action === "unjoin") {
        if ($isJoined && !$hasEventStarted) {
            $deleteStmt = $conn->prepare("
                DELETE FROM event_participants 
                WHERE event_id = ? AND user_id = ?
            ");
            $deleteStmt->bind_param("ii", $eventId, $userId);
            if ($deleteStmt->execute()) {
                $isJoined = false;
                $joinedAt = null;
                $success = "You have successfully unjoined the event.";
            } else {
                $error = "Failed to unjoin the event. Please try again.";
            }
            $deleteStmt->close();
        } else {
            $error = "You cannot unjoin an event that has already started.";
        }
    }

    // Check in to event
    elseif ($action === "checkin") {
        if ($isJoined && !$isCheckedIn && $isCheckInWindow) {
            $insertStmt = $conn->prepare("
                INSERT INTO event_checkins (event_id, user_id) 
                VALUES (?, ?)
            ");
            $insertStmt->bind_param("ii", $eventId, $userId);
            if ($insertStmt->execute()) {
                $isCheckedIn = true;
                $checkedInAt = date("Y-m-d H:i:s");
                $success = "You have successfully checked in to the event!";
            } else {
                $error = "Failed to check in. Please try again.";
            }
            $insertStmt->close();
        } elseif (!$isJoined) {
            $error = "You must join the event before checking in.";
        } elseif ($isCheckedIn) {
            $error = "You have already checked in to this event.";
        } else {
            $error = "Check-in is not available at this time. Check-in opens 20 minutes before the event starts.";
        }
    }

    // Submit feedback
    elseif ($action === "submit_feedback") {
        if ($isJoined && $hasEventEnded) {
            $rating = intval($_POST["rating"] ?? 0);
            $feedback = trim((string) ($_POST["feedback"] ?? ""));
            $isAnonymous = isset($_POST["is_anonymous"]) ? 1 : 0;

            if ($rating < 1 || $rating > 5) {
                $error = "Please select a valid rating (1-5 stars).";
            } elseif (empty($feedback)) {
                $error = "Please provide your feedback.";
            } else {
                if ($hasFeedback) {
                    // Update existing feedback
                    $updateStmt = $conn->prepare("
                        UPDATE event_feedback 
                        SET rating = ?, feedback = ?, is_anonymous = ?
                        WHERE event_id = ? AND user_id = ?
                    ");
                    $updateStmt->bind_param("isiii", $rating, $feedback, $isAnonymous, $eventId, $userId);
                    if ($updateStmt->execute()) {
                        $success = "Your feedback has been updated!";
                    } else {
                        $error = "Failed to update feedback. Please try again.";
                    }
                    $updateStmt->close();
                } else {
                    // Insert new feedback
                    $insertStmt = $conn->prepare("
                        INSERT INTO event_feedback (event_id, user_id, rating, feedback, is_anonymous) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $insertStmt->bind_param("iiisi", $eventId, $userId, $rating, $feedback, $isAnonymous);
                    if ($insertStmt->execute()) {
                        $hasFeedback = true;
                        $success = "Thank you for your feedback!";
                    } else {
                        $error = "Failed to submit feedback. Please try again.";
                    }
                    $insertStmt->close();
                }
            }
        } elseif (!$isJoined) {
            $error = "You must have joined the event to provide feedback.";
        } elseif (!$hasEventEnded) {
            $error = "Feedback can only be submitted after the event has ended.";
        }
    }

    // Submit comment
    elseif ($action === "submit_comment") {
        if ($isJoined) {
            $comment = trim((string) ($_POST["comment"] ?? ""));
            if (empty($comment)) {
                $error = "Please enter a comment.";
            } else {
                $insertStmt = $conn->prepare("
                    INSERT INTO event_comments (event_id, user_id, comment) 
                    VALUES (?, ?, ?)
                ");
                $insertStmt->bind_param("iis", $eventId, $userId, $comment);
                if ($insertStmt->execute()) {
                    $success = "Your comment has been posted!";
                    // Refresh comments
                    $comments = [];
                    $commentsStmt = $conn->prepare("
                        SELECT c.*, u.full_name, u.student_id
                        FROM event_comments c
                        JOIN users u ON c.user_id = u.id
                        WHERE c.event_id = ? AND c.parent_id IS NULL
                        ORDER BY c.created_at DESC
                    ");
                    $commentsStmt->bind_param("i", $eventId);
                    $commentsStmt->execute();
                    $commentsResult = $commentsStmt->get_result();
                    while ($row = $commentsResult->fetch_assoc()) {
                        $comments[] = $row;
                    }
                    $commentsStmt->close();
                } else {
                    $error = "Failed to post comment. Please try again.";
                }
                $insertStmt->close();
            }
        } else {
            $error = "You must join the event to post comments.";
        }
    }
}

// Get participants list for admin/facilitator/counselor
$participants = [];
if (in_array($role, ["Administrator", "Facilitator", "Counselor"], true)) {
    $participantsStmt = $conn->prepare("
        SELECT u.id, u.full_name, u.student_id, u.email, u.role,
               ep.joined_at,
               ec.checked_in_at
        FROM event_participants ep
        JOIN users u ON ep.user_id = u.id
        LEFT JOIN event_checkins ec ON ep.event_id = ec.event_id AND ep.user_id = ec.user_id
        WHERE ep.event_id = ?
        ORDER BY ep.joined_at ASC
    ");
    $participantsStmt->bind_param("i", $eventId);
    $participantsStmt->execute();
    $participantsResult = $participantsStmt->get_result();
    while ($row = $participantsResult->fetch_assoc()) {
        $participants[] = $row;
    }
    $participantsStmt->close();
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
        <div class="event-detail-page">
                <!-- Back Button -->
                <a href="/campuscare-api/php-frontend/pages/events/events.php" class="btn btn-outline event-back-link">
                    <?php echo sidebarIconSvg("arrow-left"); ?>
                    Back to Events
                </a>

                <?php if ($error !== ""): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success !== ""): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <!-- Event Header -->
                <header class="event-detail-header">
                    <div class="event-detail-header-main">
                        <div class="event-detail-kicker">
                            <span class="event-detail-kicker-icon"><?php echo sidebarIconSvg("calendar"); ?></span>
                            <span><?php echo htmlspecialchars($event["category"] ?? "General"); ?></span>
                        </div>
                        <h1 class="event-detail-title"><?php echo htmlspecialchars($event["title"]); ?></h1>
                        <p class="event-detail-subtitle">A structured view of the event details, timing, and attendance status.</p>
                    </div>

                    <div class="event-detail-status">
                        <?php if ($hasEventEnded): ?>
                            <span class="status-badge status-ended"><?php echo sidebarIconSvg("check-circle"); ?> Event Ended</span>
                        <?php elseif ($hasEventStarted): ?>
                            <span class="status-badge status-ongoing"><?php echo sidebarIconSvg("trend"); ?> Ongoing</span>
                        <?php else: ?>
                            <span class="status-badge status-upcoming"><?php echo sidebarIconSvg("calendar"); ?> Upcoming</span>
                        <?php endif; ?>
                    </div>
                </header>

                <section class="event-detail-meta-grid">
                    <div class="event-meta-card">
                        <span class="event-meta-icon"><?php echo sidebarIconSvg("calendar"); ?></span>
                        <div>
                            <span class="event-meta-label">Date</span>
                            <span class="event-meta-value"><?php echo htmlspecialchars($dateStr); ?></span>
                        </div>
                    </div>
                    <div class="event-meta-card">
                        <span class="event-meta-icon"><?php echo sidebarIconSvg("clock"); ?></span>
                        <div>
                            <span class="event-meta-label">Time</span>
                            <span class="event-meta-value"><?php echo htmlspecialchars($displayTime); ?></span>
                        </div>
                    </div>
                    <div class="event-meta-card">
                        <span class="event-meta-icon"><?php echo sidebarIconSvg("pin"); ?></span>
                        <div>
                            <span class="event-meta-label">Location</span>
                            <span class="event-meta-value"><?php echo htmlspecialchars($event["location"]); ?></span>
                        </div>
                    </div>
                    <div class="event-meta-card">
                        <span class="event-meta-icon"><?php echo sidebarIconSvg("users"); ?></span>
                        <div>
                            <span class="event-meta-label">Attendees</span>
                            <span class="event-meta-value"><?php echo intval($event["participant_count"]); ?> joined</span>
                        </div>
                    </div>
                </section>

                <!-- Event Description -->
                <section class="event-detail-description">
                    <div class="section-heading">
                        <span class="section-heading-icon"><?php echo sidebarIconSvg("message"); ?></span>
                        <div>
                            <h2>About This Event</h2>
                            <p class="section-heading-subtext">Key details, purpose, and what participants can expect.</p>
                        </div>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($event["description"] ?? "No description available.")); ?></p>
                </section>

                <!-- Event Statistics (Admin/Teacher/Counselor Only) -->
                <?php if (in_array($role, ["Administrator", "Teacher", "Counselor", "Facilitator"], true)): ?>
                <section class="event-detail-stats">
                    <div class="stat-card">
                        <div class="stat-icon"><?php echo sidebarIconSvg("users"); ?></div>
                        <div class="stat-info">
                            <span class="stat-value"><?php echo intval($event["participant_count"]); ?></span>
                            <span class="stat-label">Participants</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><?php echo sidebarIconSvg("check"); ?></div>
                        <div class="stat-info">
                            <span class="stat-value"><?php echo intval($event["checkin_count"]); ?></span>
                            <span class="stat-label">Checked In</span>
                        </div>
                    </div>
                    <?php if ($totalRatings > 0): ?>
                        <div class="stat-card">
                            <div class="stat-icon"><?php echo sidebarIconSvg("star"); ?></div>
                            <div class="stat-info">
                                <span class="stat-value"><?php echo number_format($avgRating, 1); ?></span>
                                <span class="stat-label"><?php echo $totalRatings; ?> Rating<?php echo $totalRatings !== 1 ? "s" : ""; ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <!-- Student View -->
                <?php if ($role === "Student"): ?>
                    <!-- Action Buttons -->
                    <section class="event-detail-actions">
                        <?php if (!$isJoined): ?>
                            <?php if (!$hasEventStarted): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="join">
                                    <button type="submit" class="btn btn-primary btn-large">
                                        <?php echo sidebarIconSvg("user-plus"); ?>
                                        Join Event
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-disabled btn-large" disabled>
                                    Registration Closed
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if (!$hasEventStarted): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="unjoin">
                                    <button type="submit" class="btn btn-outline btn-large">
                                        <?php echo sidebarIconSvg("user-minus"); ?>
                                        Unjoin Event
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-disabled btn-large" disabled>
                                    <?php echo sidebarIconSvg("user"); ?>
                                    Joined
                                </button>
                            <?php endif; ?>

                            <?php if ($isCheckInWindow && !$isCheckedIn): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="checkin">
                                    <button type="submit" class="btn btn-primary btn-large">
                                        <?php echo sidebarIconSvg("check-circle"); ?>
                                        Check In Now
                                    </button>
                                </form>
                            <?php elseif ($isCheckedIn): ?>
                                <div class="checkin-success">
                                    <?php echo sidebarIconSvg("check-circle"); ?>
                                    <span>Checked In</span>
                                </div>
                            <?php elseif ($hasEventStarted && !$hasEventEnded): ?>
                                <div class="checkin-reminder">
                                    <?php echo sidebarIconSvg("bell"); ?>
                                    <span>Remember to check in for attendance!</span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>

                    <!-- Feedback Section (After Event Ends) -->
                    <?php if ($hasEventEnded && $isJoined): ?>
                        <section class="event-detail-feedback">
                            <h2>Event Feedback</h2>

                            <?php if (!$hasFeedback): ?>
                                <form method="POST" class="feedback-form">
                                    <input type="hidden" name="action" value="submit_feedback">

                                    <div class="form-group">
                                        <label> How would you rate this event?</label>
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" required>
                                                <label for="rating<?php echo $i; ?>" class="rating-star">
                                                    <span class="rating-emoji" aria-hidden="true"><?php echo ["😞", "😐", "🙂", "😊", "🤩"][($i - 1)]; ?></span>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="feedback">Your Feedback</label>
                                        <textarea
                                            id="feedback"
                                            name="feedback"
                                            rows="5"
                                            placeholder="Share your thoughts about the event..."
                                            required
                                        ></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="is_anonymous">
                                            <span>Submit anonymously</span>
                                        </label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        Submit Feedback
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="feedback-submitted">
                                    <?php echo sidebarIconSvg("check-circle"); ?>
                                    <span>Thank you for your feedback!</span>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <!-- Comments Section -->
                    <?php if ($isJoined): ?>
                        <section class="event-detail-comments">
                            <div class="section-heading">
                                <span class="section-heading-icon"><?php echo sidebarIconSvg("message"); ?></span>
                                <h2>Comments & Discussion</h2>
                            </div>
                            <!-- Add Comment Form -->
                            <form method="POST" class="comment-form">
                                <input type="hidden" name="action" value="submit_comment">
                                <div class="form-group">
                                    <textarea
                                        name="comment"
                                        rows="3"
                                        placeholder="Share your thoughts or ask a question..."
                                        required
                                    ></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-small">
                                    Post Comment
                                </button>
                            </form>

                            <!-- Comments List -->
                            <div class="comments-list">
                                <?php if (empty($comments)): ?>
                                    <div class="no-comments">
                                        No comments yet. Be the first to share your thoughts!
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($comments as $comment): ?>
                                        <article class="comment-card">
                                            <div class="comment-header">
                                                <span class="comment-author">
                                                    <?php echo htmlspecialchars($comment["full_name"]); ?>
                                                </span>
                                                <span class="comment-date">
                                                    <?php echo date("F j, Y \a\t g:i A", strtotime($comment["created_at"])); ?>
                                                </span>
                                            </div>
                                            <div class="comment-body">
                                                <?php echo nl2br(htmlspecialchars($comment["comment"])); ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                <!-- Administrator/Facilitator/Counselor View -->
                <?php elseif (in_array($role, ["Administrator", "Facilitator", "Counselor"], true)): ?>
                    <!-- Event Management Actions -->
                    <section class="event-detail-admin-actions">
                        <a href="/campuscare-api/php-frontend/pages/events/events.php?edit=<?php echo $eventId; ?>" class="btn btn-outline">
                            <?php echo sidebarIconSvg("edit"); ?>
                            Edit Event
                        </a>
                    </section>

                    <!-- Participants List -->
                    <section class="event-detail-participants">
                        <h2>Event Participants</h2>

                        <?php if (empty($participants)): ?>
                            <div class="no-participants">
                                No participants yet.
                            </div>
                        <?php else: ?>
                            <div class="participants-table">
                                <div class="participants-header">
                                    <span>Name</span>
                                    <span>ID</span>
                                    <span>Joined</span>
                                    <span>Check-in Time</span>
                                    <span>Status</span>
                                </div>
                                <?php foreach ($participants as $participant): ?>
                                    <div class="participants-row">
                                        <span class="participant-name">
                                            <?php echo htmlspecialchars($participant["full_name"]); ?>
                                        </span>
                                        <span class="participant-id">
                                            <?php echo htmlspecialchars($participant["student_id"]); ?>
                                        </span>
                                        <span class="participant-joined">
                                            <?php echo date("M j, Y", strtotime($participant["joined_at"])); ?>
                                        </span>
                                        <span class="participant-checkin-time">
                                            <?php if ($participant["checked_in_at"]): ?>
                                                <?php echo date("M j, Y g:i A", strtotime($participant["checked_in_at"])); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </span>
                                        <span class="participant-status">
                                            <?php if ($participant["checked_in_at"]): ?>
                                                <span class="status-badge status-checked-in">Checked In</span>
                                            <?php else: ?>
                                                <span class="status-badge status-not-checked-in">Not Checked In</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- Event Feedback Summary -->
                    <?php if ($hasEventEnded): ?>
                        <section class="event-detail-feedback-summary">
                            <div class="section-heading">
                                <h2>Event Feedback Summary</h2>
                            </div>
                            <div class="feedback-stats">
                                <div class="feedback-stat">
                                    <span class="feedback-stat-value"><?php echo number_format($avgRating, 1); ?></span>
                                    <span class="feedback-stat-label">Average Rating</span>
                                </div>
                                <div class="feedback-stat">
                                    <span class="feedback-stat-value"><?php echo $totalRatings; ?></span>
                                    <span class="feedback-stat-label">Total Ratings</span>
                                </div>
                            </div>

                            <!-- View All Feedback Button -->
                            <a href="/campuscare-api/php-frontend/pages/events/event_feedback.php?id=<?php echo $eventId; ?>" class="btn btn-outline">
                                <?php echo sidebarIconSvg("message"); ?>
                                View All Feedback
                            </a>
                        </section>
                    <?php endif; ?>

                    <!-- Comments Section -->
                    <section class="event-detail-comments">
                        <h2>Comments & Discussion</h2>

                        <!-- Add Comment Form -->
                        <form method="POST" class="comment-form">
                            <input type="hidden" name="action" value="submit_comment">
                            <div class="form-group">
                                <textarea
                                    name="comment"
                                    rows="3"
                                    placeholder="Share announcements or respond to questions..."
                                    required
                                ></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-small">
                                Post Comment
                            </button>
                        </form>

                        <!-- Comments List -->
                        <div class="comments-list">
                            <?php if (empty($comments)): ?>
                                <div class="comment-item">
                                    <p class="comment-content">No comments yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="comment-item">
                                        <div class="comment-header">
                                            <span class="comment-author">
                                                <?php echo htmlspecialchars($comment["full_name"]); ?>
                                            </span>
                                            <span class="comment-date">
                                                <?php echo date("F j, Y \a\t g:i A", strtotime($comment["created_at"])); ?>
                                            </span>
                                        </div>
                                        <div class="comment-content">
                                            <?php echo nl2br(htmlspecialchars($comment["comment"])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                <!-- Instructor View -->
                <?php elseif ($role === "Instructor"): ?>
                    <!-- Similar to Student but with additional monitoring features -->
                    <section class="event-detail-actions">
                        <?php if (!$isJoined): ?>
                            <?php if (!$hasEventStarted): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="join">
                                    <button type="submit" class="btn btn-primary btn-large">
                                        <?php echo sidebarIconSvg("user-plus"); ?>
                                        Join Event
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-disabled btn-large" disabled>
                                    Registration Closed
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if (!$hasEventStarted): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="unjoin">
                                    <button type="submit" class="btn btn-outline btn-large">
                                        <?php echo sidebarIconSvg("user-minus"); ?>
                                        Unjoin Event
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-disabled btn-large" disabled>
                                    <?php echo sidebarIconSvg("user"); ?>
                                    Joined
                                </button>
                            <?php endif; ?>

                            <?php if ($isCheckInWindow && !$isCheckedIn): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="checkin">
                                    <button type="submit" class="btn btn-primary btn-large">
                                        <?php echo sidebarIconSvg("check-circle"); ?>
                                        Check In Now
                                    </button>
                                </form>
                            <?php elseif ($isCheckedIn): ?>
                                <div class="checkin-success">
                                    <?php echo sidebarIconSvg("check-circle"); ?>
                                    <span>Checked In</span>
                                </div>
                            <?php elseif ($hasEventStarted && !$hasEventEnded): ?>
                                <div class="checkin-reminder">
                                    <?php echo sidebarIconSvg("bell"); ?>
                                    <span>Remember to check in for attendance!</span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>

                    <!-- Comments Section -->
                    <?php if ($isJoined): ?>
                        <section class="event-detail-comments">
                            <h2>Comments & Discussion</h2>

                            <!-- Add Comment Form -->
                            <form method="POST" class="comment-form">
                                <input type="hidden" name="action" value="submit_comment">
                                <div class="form-group">
                                    <textarea
                                        name="comment"
                                        rows="3"
                                        placeholder="Share your thoughts or ask a question..."
                                        required
                                    ></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-small">
                                    Post Comment
                                </button>
                            </form>

                            <!-- Comments List -->
                            <div class="comments-list">
                                <?php if (empty($comments)): ?>
                                    <div class="no-comments">
                                        No comments yet. Be the first to share your thoughts!
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($comments as $comment): ?>
                                        <article class="comment-card">
                                            <div class="comment-header">
                                                <span class="comment-author">
                                                    <?php echo htmlspecialchars($comment["full_name"]); ?>
                                                </span>
                                                <span class="comment-date">
                                                    <?php echo date("F j, Y \a\t g:i A", strtotime($comment["created_at"])); ?>
                                                </span>
                                            </div>
                                            <div class="comment-body">
                                                <?php echo nl2br(htmlspecialchars($comment["comment"])); ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- Feedback Section (After Event Ends) -->
                    <?php if ($hasEventEnded && $isJoined): ?>
                        <section class="event-detail-feedback">
                            <h2>Event Feedback</h2>

                            <?php if (!$hasFeedback): ?>
                                <form method="POST" class="feedback-form">
                                    <input type="hidden" name="action" value="submit_feedback">

                                    <div class="form-group">
                                        <label>How would you rate this event?</label>
                                        <div class="rating-input">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" required>
                                                <label for="rating<?php echo $i; ?>" class="rating-star">
                                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px; max-width: 20px; max-height: 20px;">
                                                        <path d="M12 3.5L14.9 9.1L21.1 10L16.6 14.3L17.7 20.5L12 17.5L6.3 20.5L7.4 14.3L2.9 10L9.1 9.1L12 3.5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                    </svg>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="feedback">Your Feedback</label>
                                        <textarea
                                            id="feedback"
                                            name="feedback"
                                            rows="5"
                                            placeholder="Share your thoughts about the event..."
                                            required
                                        ></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="is_anonymous">
                                            <span>Submit anonymously</span>
                                        </label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        Submit Feedback
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="feedback-submitted" style="margin-top: 1rem;!important">
                                    <?php echo sidebarIconSvg("check-circle"); ?>
                                    <span>Thank you for your feedback!</span>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Update check-in reminder every minute
        setInterval(() => {
            const currentTime = Date.now();
            const eventStart = new Date("<?php echo date("c", $eventStart); ?>").getTime();
            const eventEnd = new Date("<?php echo date("c", $eventEnd); ?>").getTime();
            const checkInStart = eventStart - (20 * 60 * 1000);
            const checkInEnd = eventEnd;
            const isCheckInWindow = currentTime >= checkInStart && currentTime <= checkInEnd;
            const hasEventStarted = currentTime >= eventStart;
            const hasEventEnded = currentTime >= eventEnd;

            const checkInReminder = document.querySelector('.checkin-reminder');
            const checkInBtn = document.querySelector('button[type="submit"][value="checkin"]');

            if (hasEventStarted && !hasEventEnded && !checkInBtn) {
                if (!checkInReminder) {
                    const actionsSection = document.querySelector('.event-detail-actions');
                    if (actionsSection) {
                        const reminder = document.createElement('div');
                        reminder.className = 'checkin-reminder';
                        reminder.innerHTML = `
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <span>Remember to check in for attendance!</span>
                        `;
                        actionsSection.appendChild(reminder);
                    }
                }
            }
        }, 60000);

        // Star Rating Interaction
        document.addEventListener('DOMContentLoaded', function() {
            const ratingInputs = document.querySelectorAll('.rating-input input[type="radio"]');
            const ratingLabels = document.querySelectorAll('.rating-input label');

            ratingLabels.forEach(label => {
                label.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.getAttribute('for').replace('rating', ''));
                    highlightStars(rating);
                });

                label.addEventListener('mouseleave', function() {
                    const checkedInput = document.querySelector('.rating-input input[type="radio"]:checked');
                    const checkedRating = checkedInput ? parseInt(checkedInput.value) : 0;
                    highlightStars(checkedRating);
                });
            });

            ratingInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const rating = parseInt(this.value);
                    highlightStars(rating);
                    
                    // Add animation to selected star
                    const selectedLabel = document.querySelector(`label[for="rating${rating}"]`);
                    if (selectedLabel) {
                        selectedLabel.style.animation = 'none';
                        selectedLabel.offsetHeight; // Trigger reflow
                        selectedLabel.style.animation = 'starPulse 0.3s ease';
                    }
                });
            });

            function highlightStars(rating) {
                ratingLabels.forEach(label => {
                    const starRating = parseInt(label.getAttribute('for').replace('rating', ''));
                    const svg = label.querySelector('svg');
                    
                    if (svg) {
                        if (starRating <= rating) {
                            svg.style.color = 'var(--primary)';
                            svg.style.transform = 'scale(1.1)';
                        } else {
                            svg.style.color = 'var(--border)';
                            svg.style.transform = 'scale(1)';
                        }
                    }
                });
            }
        });
    </script>

    <style>
        @keyframes starPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1.1); }
        }
    </style>
</main>
