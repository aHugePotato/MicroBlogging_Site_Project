<?php
include "../vars.php";
session_start();
include "../session_exp.php";

header("Content-Type: application/json", true);

try {
    $conn = new PDO("mysql:host=$servername;dbname=blog", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_AUTOCOMMIT, false);

    if ($_SERVER['REQUEST_METHOD'] == "GET") {
        $result = ["posts" => []];

        $str = "SELECT posts.id AS pid, users.id AS uid, name, title, 
        LEFT(text,200) AS text, LENGTH(text) > LENGTH(LEFT(text,200)) AS oversized, coalesce(linfo.likes,0) AS likes, coalesce(cinfo.comments,0) AS comments ";
        if (isset($_SESSION["uid"]))
            $str .= ", coalesce(linfo.liked,0) AS liked ";
        $str .= "FROM posts LEFT JOIN users ON (posts.user=users.id) LEFT JOIN (SELECT COUNT(*) AS likes ";
        if (isset($_SESSION["uid"]))
            $str .= ", SUM(IF(user=:cur_user,1,0))>0 AS liked";
        $str .= ", post FROM likes GROUP BY post) AS linfo ON (linfo.post=posts.id) 
            LEFT JOIN (SELECT COUNT(*) AS comments, post FROM comments GROUP BY post) AS cinfo ON (cinfo.post=posts.id)";
        if (isset($_GET["following"]) && $_GET["following"] == "true") {
            if (isset($_SESSION["uid"]))
                $str .= "RIGHT JOIN (SELECT user, target FROM follows WHERE user=:cur_user) AS follows ON (follows.target=posts.user)";
            else {
                http_response_code(401);
                exit;
            }
        }
        $str .= "WHERE posts.id < :lastId ";
        if (isset($_GET["uid"]))
            $str .= "&& users.id = :uid ";
        if (isset($_GET["search"]))
            $str .= "&& posts.text LIKE :searchParam ";
        $str .= "ORDER BY posts.id DESC LIMIT 11";

        $stmt = $conn->prepare($str);

        $bindArr[":lastId"] = isset($_GET["lastId"]) ? $_GET["lastId"] : 2147483647;
        if (isset($_GET["uid"]))
            $bindArr[":uid"] = $_GET["uid"];
        if (isset($_SESSION["uid"]))
            $bindArr[":cur_user"] = $_SESSION["uid"];
        if (isset($_GET["search"]))
            $bindArr[":searchParam"] = "%".urldecode($_GET["search"])."%";

        $stmt->execute($bindArr);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row["imgs"] = [];
            $imgDir =  "/static/posts/" . $row["pid"] . "/";
            $imgFDir = $_SERVER['DOCUMENT_ROOT'] . $imgDir;
            if (is_dir($imgFDir) &&  $fileList = scandir($imgFDir))
                foreach ($fileList as $i)
                    if (preg_match("#^\d.#", $i))
                        array_push($row["imgs"], $imgDir . $i);

            $result["prof_pic"] = null;
            foreach (scandir($_SERVER['DOCUMENT_ROOT'] . "/static/profPics/") as $i)
                if (preg_match("#^{$row['uid']}.#", $i)) {
                    $row["prof_pic"] = "/static/profPics/" . $i;
                    break;
                }
            array_push($result["posts"], $row);
        }

        echo json_encode($result);
    } else if ($_SERVER['REQUEST_METHOD'] == "POST") {
        if (!isset($_SESSION["uid"])) {
            http_response_code(401);
            exit;
        }
        $reqBody = json_decode(file_get_contents('php://input'), true);
        if (
            !isset($reqBody["text"]) || strlen($reqBody["text"]) < 1 || strlen($reqBody["text"]) > 10000
            || !isset($reqBody["title"]) || strlen($reqBody["title"]) > 250 || !isset($reqBody["imgs"]) || gettype($reqBody["imgs"]) != "array"
        ) {
            http_response_code(400);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO posts(title,text,user) values(:title,:text,:user)");
        $stmt->execute(["text" => htmlspecialchars($reqBody["text"]), "title" => htmlspecialchars($reqBody["title"]), "user" => $_SESSION["uid"]]);
        $lid = $conn->lastInsertId();

        for ($i = 0; $i < count($reqBody["imgs"]); ++$i) {
            try {
                $imgStr = base64_decode($reqBody["imgs"][$i]);
                $imgSize = getimagesizefromstring($imgStr);
                if (!$imgSize || strlen($imgStr) > 75000000)
                    throw new Exception();
                $dir = $_SERVER['DOCUMENT_ROOT'] . "/static/posts/" . $lid;
                mkdir($dir);
                $fName = $dir . "/" . $i . "." . explode("/", $imgSize["mime"])[1];
                file_put_contents($fName, $imgStr);
            } catch (Exception $e) {
                $imgErr = true;
                continue;
            }
        }
        if (isset($imgErr)) {
            http_response_code(202);
            exit;
        }
    }
    $conn->commit();
} catch (Exception $e) {
    if ($e instanceof PDOException) {
        if ($e->getCode() == "23000")
            http_response_code(403);
        else
            http_response_code(400);
        exit;
    }
    echo $e->getMessage(); ///
    http_response_code(500);
}
