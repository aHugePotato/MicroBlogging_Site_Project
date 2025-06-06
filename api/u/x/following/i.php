<?php
include $_SERVER['DOCUMENT_ROOT'] . "/api/vars.php";
session_start();
include $_SERVER['DOCUMENT_ROOT'] . "/api/session_exp.php";

header("Content-Type: application/json", true);

$params = preg_split("#/|\?#", $_SERVER["REQUEST_URI"]);

try {
    $conn = new PDO("mysql:host=$servername;dbname=blog", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] == "POST") {
        if (!isset($_SESSION["uid"]) || $_SESSION["uid"] != $params[3]) {
            http_response_code(401);
            exit;
        }
        
        $reqBody = json_decode(file_get_contents('php://input'), true);
        if (!isset($reqBody["uid"])) {
            http_response_code(400);
            exit;
        }

        $str = "INSERT INTO follows(user,target) VALUES(:user,:target)";
        $stmt = $conn->prepare($str);

        $stmt->execute([":user" => $_SESSION["uid"], ":target" => $reqBody["uid"]]);
    } else if ($_SERVER['REQUEST_METHOD'] == "DELETE") {
        if (!isset($_SESSION["uid"]) || $_SESSION["uid"] != $params[3]) {
            http_response_code(401);
            exit;
        }
        
        if (!isset($params[5])) {
            http_response_code(400);
            exit;
        }

        $str = "DELETE FROM follows WHERE user=:user && target=:target";
        $stmt = $conn->prepare($str);
        $stmt->execute([":user" => $_SESSION["uid"], ":target" => $params[5]]);
    }
} catch (Exception $e) {
    if ($e instanceof PDOException && $e->getCode() == "23000") {
        http_response_code(403);
        exit;
    }
    echo $e->getMessage(); ///
    http_response_code(500);
}
