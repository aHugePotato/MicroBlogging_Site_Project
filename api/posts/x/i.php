<?php
include $_SERVER['DOCUMENT_ROOT'] . "/api/vars.php";
session_start();
include $_SERVER['DOCUMENT_ROOT'] . "/api/session_exp.php";

header("Content-Type: application/json", true);

try {
    $conn = new PDO("mysql:host=$servername;dbname=blog", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "GET") {
        $str = "SELECT users.id AS uid, name, title, text, coalesce(linfo.likes,0) AS likes, coalesce(cinfo.comments,0) AS comments ";
        if (isset($_SESSION["uid"]))
            $str .= ", coalesce(linfo.liked,0) AS liked ";
        $str .= "FROM posts LEFT JOIN users ON (posts.user=users.id) LEFT JOIN (SELECT COUNT(*) AS likes ";
        if (isset($_SESSION["uid"]))
            $str .= ", SUM(IF(user=:curUser,1,0))>0 AS liked";
        $str .= ", post FROM likes GROUP BY post) AS linfo ON (linfo.post=posts.id) 
            LEFT JOIN (SELECT COUNT(*) AS comments, post FROM comments GROUP BY post) AS cinfo ON (cinfo.post=posts.id) 
            WHERE posts.id = :pid";
        $stmt = $conn->prepare($str);

        $bindArr[":pid"] = preg_split("#/|\?#", $_SERVER["REQUEST_URI"])[3];
        if (isset($_SESSION["uid"]))
            $bindArr[":curUser"] = $_SESSION["uid"];
        $stmt->execute($bindArr);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            http_response_code(404);
            exit;
        }
        
        $result["prof_pic"] = null;
        foreach (scandir($_SERVER['DOCUMENT_ROOT'] . "/static/profPics/") as $i)
            if (preg_match("#^{$result['uid']}.#", $i)) {
                $result["prof_pic"] = "/static/profPics/" . $i;
                break;
            }

        $result["imgs"] = [];
        $imgDir =  "/static/posts/" . $bindArr[":pid"] . "/";
        $imgFDir = $_SERVER['DOCUMENT_ROOT'] . $imgDir;
        if (is_dir($imgFDir) &&  $fileList = scandir($imgFDir))
            foreach ($fileList as $i)
                if (preg_match("#^\d.#", $i))
                    array_push($result["imgs"], $imgDir . $i);

        echo json_encode($result);
    }
} catch (Exception $e) {
    if ($e instanceof PDOException) 
            http_response_code(400);
    echo $e->getMessage(); ///
    http_response_code(500);
}
