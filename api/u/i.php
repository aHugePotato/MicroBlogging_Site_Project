<?php
include $_SERVER['DOCUMENT_ROOT'] . "/api/vars.php";
session_start();
include $_SERVER['DOCUMENT_ROOT'] . "/api/session_exp.php";

$params = preg_split("#/|\?#", $_SERVER["REQUEST_URI"]);
header("Content-Type: application/json", true);

try {
    $conn = new PDO("mysql:host=$servername;dbname=blog", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] == "GET") {
        $result = ["users" => []];

        $str = "SELECT users.id AS uid, name, description 
            FROM users ";
        if (isset($_GET["followersOf"]))
            $str .= " RIGHT JOIN follows ON (follows.user=users.id) ";
        else if (isset($_GET["followingOf"]))
            $str .= " RIGHT JOIN follows ON (follows.target=users.id) ";
        $str .= "WHERE users.id < :lastId ";
        if (isset($_GET["followersOf"]))
            $str .= "&& follows.target=:uid ";
        else if (isset($_GET["followingOf"]))
            $str .= "&& follows.user=:uid ";
        if (isset($_GET["search"]))
            $str .= "&& users.name LIKE :searchParam ";
        $str .= "ORDER BY users.id DESC LIMIT 20";
        $stmt = $conn->prepare($str);

        $bindArr = [];
        if (isset($_GET["followingOf"]))
            $bindArr[":uid"] = $_GET["followingOf"];
        else if (isset($_GET["followersOf"]))
            $bindArr[":uid"] = $_GET["followersOf"];
        if (isset($_GET["search"]))
            $bindArr[":searchParam"] = "%" . urldecode($_GET["search"]) . "%";
        $bindArr[":lastId"] = (isset($_GET["lastId"]) ? $_GET["lastId"] : 2147483647);
        $stmt->execute($bindArr);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row["prof_pic"] = null;
            foreach (scandir($_SERVER['DOCUMENT_ROOT'] . "/static/profPics/") as $i)
                if (preg_match("#^{$row['uid']}.#", $i)) {
                    $row["prof_pic"] = "/static/profPics/" . $i;
                    break;
                }
            array_push($result["users"], $row);
        }
        echo json_encode($result);
    } else if ($_SERVER['REQUEST_METHOD'] == "POST") {
        $reqBody = json_decode(file_get_contents('php://input'), true);
        if (
            !isset($reqBody) || !isset($reqBody["name"]) || !$reqBody["name"]
            || !isset($reqBody["email"]) || !filter_var($reqBody["email"], FILTER_VALIDATE_EMAIL)
        ) {
            http_response_code(400);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO users(name,email,hash) VALUES(:name,:email,:hash)");
        $stmt->execute([":name" => htmlspecialchars($reqBody["name"]), ":email" => htmlspecialchars($reqBody["email"]), ":hash" => $reqBody["password"] ? password_hash($reqBody["password"], PASSWORD_BCRYPT) : null]);
    }
} catch (Exception $e) {
    if ($e instanceof PDOException) {
        if ($e->getCode() == "23000")
            http_response_code(409);
        else
            http_response_code(400);
        exit;
    }
    echo $e->getMessage(); ///
    http_response_code(500);
}
