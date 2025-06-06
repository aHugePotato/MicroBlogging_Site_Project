<?php
include "../vars.php";

try {
    $reqBody = json_decode(file_get_contents('php://input'), true);
    if (!(isset($reqBody["name"]) && isset($reqBody["pwd"]))) {
        http_response_code(401);
        exit;
    }

    $conn = new PDO("mysql:host=$servername;dbname=blog", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("SELECT id, hash FROM users WHERE name = :uname");
    
    $stmt->execute([":uname" => $reqBody["name"]]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        http_response_code(401);
        exit;
    }
    if ($reqBody["pwd"] && $result["hash"] && password_verify($reqBody["pwd"], $result["hash"])
    || !$result["hash"] && !$reqBody["pwd"]) {
        session_start();
        session_unset();
        session_regenerate_id();
        $_SESSION["uid"]= $result["id"];
        $_SESSION['loginTime'] = time();
        exit;
    } 
    http_response_code(401);
    
} catch (Exception $e) {
    echo $e->getMessage(); ///
    http_response_code(500);
}
