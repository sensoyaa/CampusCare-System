<?php
session_start();
session_unset();
session_destroy();

header("Location: /campuscare-api/landingpage.php");
exit();
?>