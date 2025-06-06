<?php
include $_SERVER['DOCUMENT_ROOT'] . "/api/vars.php";
session_start();
include $_SERVER['DOCUMENT_ROOT'] . "/api/session_exp.php";

header("Content-Type: application/json", true);

$params = preg_split("#/|\?#", $_SERVER["REQUEST_URI"]);

if (!isset($_SESSION["uid"])) {
    http_response_code(401);
    exit;
}

try {
    $conn = new PDO("mysql:host=$servername;dbname=blog", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] == "POST") {
        $str = "INSERT INTO likes(post,user) VALUES(:pid,:uid)";
        $stmt = $conn->prepare($str);

        $stmt->execute([":pid" => $params[3], ":uid" => $_SESSION["uid"]]);
    } else if ($_SERVER['REQUEST_METHOD'] == "DELETE") {
        $str = "DELETE FROM likes WHERE post=:pid && user=:uid";
        $stmt = $conn->prepare($str);

        $stmt->execute([":pid" => $params[3], ":uid" => $_SESSION["uid"]]);
    }
} catch (Exception $e) {
    if ($e instanceof PDOException && $e->getCode() == "23000") {
        http_response_code(403);
        exit;
    }
    echo $e->getMessage(); ///
    http_response_code(500);
}
