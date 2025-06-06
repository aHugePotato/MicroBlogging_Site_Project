<?php
include $_SERVER['DOCUMENT_ROOT'] . "/api/vars.php";
session_start();
include $_SERVER['DOCUMENT_ROOT'] . "/api/session_exp.php";

header("Content-Type: application/json", true);

$params = preg_split("#/|\?#", $_SERVER["REQUEST_URI"]);

try {
    $conn = new PDO("mysql:host=$servername;dbname=blog", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "GET") {
        $str = "SELECT name ";
        if (!isset($_GET["mode"]) || $_GET["mode"] != "basic") {
            if (isset($_GET["mode"]) && $_GET["mode"] == "full")
                if (!isset($_SESSION["uid"]) || $_SESSION["uid"] != $params[3]) {
                    http_response_code(401);
                    exit;
                } else
                    $str .= ",email ";
            $str .= ", description, coalesce(folwngs.followings,0) AS followings, coalesce(folwrs.followers,0) AS followers";
        }
        if (isset($_SESSION["uid"]))
            $str .= ", IF(follows.target,1,0) AS is_following ";
        $str .= " FROM users ";
        if (!isset($_GET["mode"]) || $_GET["mode"] != "basic")
            $str .= "LEFT JOIN (SELECT COUNT(*) AS followings, user FROM follows GROUP BY user) AS folwngs ON (users.id=folwngs.user) 
                    LEFT JOIN (SELECT COUNT(*) AS followers, target FROM follows GROUP BY target) AS folwrs ON (users.id=folwrs.target) ";
        if (isset($_SESSION["uid"]))
            $str .= "LEFT JOIN (SELECT target FROM follows WHERE user=:curUser) AS follows ON (users.id=follows.target)";
        $str .= "WHERE id = :uid";
        $stmt = $conn->prepare($str);

        $bindArr[":uid"] = $params[3];
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
            if (preg_match("#^{$bindArr[':uid']}.#", $i)) {
                $result["prof_pic"] = "/static/profPics/" . $i;
                break;
            }

        echo json_encode($result);
    } else if ($_SERVER["REQUEST_METHOD"] == "PATCH") {
        if (!isset($_SESSION["uid"])) {
            http_response_code(401);
            exit;
        }
        $reqBody = json_decode(file_get_contents('php://input'), true);

        $stmt = $conn->prepare("SELECT hash FROM users WHERE id=:uid");
        $stmt->execute([":uid" => $params[3]]);
        $Ohash = $stmt->fetch(PDO::FETCH_ASSOC)["hash"];

        if (!isset($reqBody["password"]) || $_SESSION["uid"] != $params[3] || $Ohash && !password_verify($reqBody["password"], $Ohash)) {
            http_response_code(403);
            exit;
        }
        if (!isset($reqBody["user"])) {
            http_response_code(400);
            exit;
        }

        $str = "";
        if (isset($reqBody["user"]["name"])) $str .= ",name=:name";
        if (isset($reqBody["user"]["description"])) $str .= ",description=:description";
        if (isset($reqBody["user"]["email"])) $str .= ",email=:email";
        if (isset($reqBody["user"]["password"])) $str .= ",hash=:hash ";
        if ($str) {
            $str .= " WHERE users.id=:uid";
            $str = "UPDATE users SET " . substr($str, 1);

            $stmt = $conn->prepare($str);

            $bindArr = [];
            if (isset($reqBody["user"]["name"])) $bindArr[":name"] = htmlspecialchars($reqBody["user"]["name"]);
            if (isset($reqBody["user"]["description"])) $bindArr[":description"] = htmlspecialchars($reqBody["user"]["description"]);
            if (isset($reqBody["user"]["email"])) $bindArr[":email"] = htmlspecialchars($reqBody["user"]["email"]);
            if (isset($reqBody["user"]["password"])) $bindArr[":hash"]
                = $reqBody["user"]["password"] ? password_hash($reqBody["user"]["password"], PASSWORD_BCRYPT) : null;
            $bindArr[":uid"] = $params[3];

            $stmt->execute($bindArr);
        }
        if (isset($reqBody["user"]["prof_pic"])) {
            try {
                $imgStr = base64_decode($reqBody["user"]["prof_pic"]);
                $imgSize = getimagesizefromstring($imgStr);
                if (!$imgSize || strlen($imgStr) > 20000000)
                    throw new Exception();
                $fPath = $_SERVER['DOCUMENT_ROOT'] . "/static/profPics/" . $params[3] . ".";
                array_map("unlink", glob($fPath . "*"));
                $fName = $fPath . explode("/", $imgSize["mime"])[1];
                file_put_contents($fName, $imgStr);
            } catch (Exception $e) {
                http_response_code(202);
                exit;
            }
        }
    }
} catch (Exception $e) {
    if ($e instanceof PDOException) {
        if ($e->getCode() == "23000")
            http_response_code(409);
        else
            http_response_code(400);
        echo $e->getMessage(); ///
        exit;
    }
    echo $e->getMessage(); ///
    http_response_code(500);
}
