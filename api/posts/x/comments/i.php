<?php
include $_SERVER['DOCUMENT_ROOT'] . "/api/vars.php";
session_start();
include $_SERVER['DOCUMENT_ROOT'] . "/api/session_exp.php";

header("Content-Type: application/json", true);

$params = preg_split("#/|\?#", $_SERVER["REQUEST_URI"]);

try {
    $conn = new PDO("mysql:host=$servername;dbname=blog", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] == "GET") {
        $result = ["comments" => []];

        $stmt = $conn->prepare("SELECT users.id AS uid, name, text, comments.id AS cid
        FROM comments LEFT JOIN users ON (comments.user=users.id)
        WHERE comments.post=:pid && comments.id < :lastId ORDER BY comments.id DESC LIMIT 15");

        $stmt->execute([":pid" => $params[3], ":lastId" => isset($_GET["lastId"]) ? $_GET["lastId"] : 2147483647]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result["prof_pic"] = null;
            foreach (scandir($_SERVER['DOCUMENT_ROOT'] . "/static/profPics/") as $i)
                if (preg_match("#^{$row['uid']}.#", $i)) {
                    $row["prof_pic"] = "/static/profPics/" . $i;
                    break;
                }
            array_push($result["comments"], $row);
        }

        echo json_encode($result);
    } else if ($_SERVER['REQUEST_METHOD'] == "POST") {
        if (!isset($_SESSION["uid"])) {
            http_response_code(401);
            exit;
        }

        $reqBody = json_decode(file_get_contents('php://input'), true);
        if (!isset($reqBody["text"]) || strlen($reqBody["text"]) < 1 || strlen($reqBody["text"]) > 500) {
            http_response_code(400);
            exit;
        }

        $str = "INSERT INTO comments(post,user,text) VALUES(:pid,:uid,:text)";
        $stmt = $conn->prepare($str);

        $stmt->execute([":pid" => $params[3], ":uid" => $_SESSION["uid"], ":text" => htmlspecialchars($reqBody["text"])]);
    }
} catch (Exception $e) {
    if ($e instanceof PDOException && $e->getCode() == "23000") {
        http_response_code(403);
        exit;
    }
    echo $e->getMessage(); ///
    http_response_code(500);
}
