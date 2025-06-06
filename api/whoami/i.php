<?php

header("Content-Type: application/json", true);

session_start();
include "../session_exp.php";

if (isset($_SESSION["uid"])) {
    echo '{"id":"' . $_SESSION["uid"] . '"}';
    exit;
} else
    http_response_code(401);
