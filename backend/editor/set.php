<?php
session_start();
/*
Endpoint:

action: ["register", "send", "check", "set", "logout"]
username: the username
password: the new password (for "set" action)
uuid: the uuid (for "check" and "set")
*/
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

include ( '../functions_new.php' );
include("../user/hash_functions.php");

function get_values($db, $names) {
    $output = [];
    foreach ($names as $name) {
        if(!$_REQUEST[$name])
            echo "ERROR: $name not provided.\n";
        if($name == "password")
            $output[] = mysqli_escape_string($db, phpbb_hash($_REQUEST["password"]));
        $output[] = mysqli_escape_string($db, $_REQUEST[$name]);
    }
    return $output;
}

function query_json($db, $query) {
    $result = mysqli_query($db, $query);
    $r = mysqli_fetch_assoc($result);
    print(json_encode($r, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));
}
function query_id($db, $query) {
    $result = mysqli_query($db, $query);
    $r = mysqli_fetch_assoc($result);
    return $r["id"];
}

function query_one($db, $query) {
    $result = mysqli_query($db, $query);
    return mysqli_fetch_assoc($result);
}

$db = database();



if( (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] == 0) && isset($_REQUEST['username'])) {
    //http_response_code(403);

    // try to login again
    list($username, , $password) = get_values($db, ['username', 'password']);
    $username = mysqli_escape_string($db, $_REQUEST["username"]);
    $user = mysqli_fetch_assoc(mysqli_query($db, "SELECT * FROM user WHERE username = '$username' AND activated = 1"));
    $hash = $user["password"];
    if(phpbb_check_hash($_REQUEST["password"], $hash)) {
        $_SESSION["user"] = $user;
    }
    unset($_REQUEST["password"]);
    unset($_REQUEST["username"]);
    unset($_POST["password"]);
    unset($_POST["username"]);
}

if(!isset($_SESSION["user"]) || $_SESSION["user"]["role"] == 0) {
    http_response_code(403);
    die();
}

$action = $_REQUEST['action'];
if($action == "import") {
    $id = intVal($_REQUEST['id']);
    $course_id = intVal($_REQUEST['course_id']);
    $data = query_one($db, "SELECT * FROM story WHERE id = $id;");
    unset($data["id"]);
    unset($data["change_date"]);
    unset($data["date"]);
    if(isset($_SESSION["user"]))
        $data["author"] = $_SESSION["user"];
    else
        $data["author"] = 2;
    $data["course_id"] = $course_id;

    $keys = ["id" => "int",
            "duo_id" => "string",
            "name" => "string",
    #        "name_base" => "string",
    #        "lang" => "string",
    #        "lang_base" => "string",
            "author" => "int",
            "change_date" => "string",
            "image" => "string",
    #        "image_done" => "string",
    #        "image_locked" => "string",
    #        "discussion" => "string",
    #        "xp" => "int",
    #        "cefr" => "string",
            "set_id" => "int",
            "set_index" => "int",
            "course_id" => "int",
            "text" => "string",
            "json" => "string",
            "api" => "int"];

    if((!isset($_POST["author"]) || $_POST["author"] == "") && isset($_SESSION["user"]))
        $_POST["author"] = $_SESSION["user"]["id"];

    $id = updateDatabase($keys, "story", $data, "id");
    echo $id;

    $data = query_one($db, 'SELECT text,name,course_id, id FROM story WHERE id = '.$id.';');

    $newfile = fopen($id.".txt", "w");
    $str = $data["text"];
    fwrite($newfile, $str);
    fclose($newfile);

    $output=null;
    $retval=null;
    if(isset($_SESSION["user"])) {
        $author_name = '"duolingo"';
        $message = '"added '.$data["name"].' in course '.$data["course_id"].'"';
    }
    exec("python3 upload_github.py $id $data[course_id] $author_name $message", $output, $retval);
    //print_r($output);
}
else if($action == "avatar") {
    $keys = ["id" => "int",
        "name" => "string",
        "speaker" => "string",
        "language_id" => "int",
        "avatar_id" => "int",
    ];
    $id = updateDatabase($keys, "avatar_mapping", $_POST, "id");
}
else if($action == "status") {
    $keys = ["id" => "int",
        "status" => "string",
    ];
    $id = updateDatabase($keys, "story", $_POST, "id");
}
else if($action == "story") {
    $keys = ["id" => "int",
        "duo_id" => "string",
        "name" => "string",
#        "name_base" => "string",
#        "lang" => "string",
#        "lang_base" => "string",
        "author" => "int",
        "change_date" => "string",
        "image" => "string",
#        "image_done" => "string",
#        "image_locked" => "string",
#        "discussion" => "string",
#        "xp" => "int",
#        "cefr" => "string",
        "set_id" => "int",
        "set_index" => "int",
        "course_id" => "int",
        "text" => "string",
        "json" => "string",
        "api" => "int"];
    $_POST["api"] = 2;
    if(!isset($_POST["id"])) {
        $_POST["id"] = query_id($db, 'SELECT id FROM story WHERE API = 2 AND duo_id = "'.mysqli_escape_string ($db, $_POST['duo_id']).'" AND course_id = '.intVal($_POST['course_id']).";");
    }

    if((!isset($_POST["author"]) || $_POST["author"] == "") && isset($_SESSION["user"]))
        $_POST["author"] = $_SESSION["user"]["id"];

    //unset($_POST["author"]);
    echo "send...\n";
    echo "\n";
    $id = updateDatabase($keys, "story", $_POST, "id");
    echo $id;

    $newfile = fopen($id.".txt", "w");
    $str = $_POST["text"];
    fwrite($newfile, $str);
    fclose($newfile);

    $output=null;
    $retval=null;
    if(isset($_SESSION["user"])) {
        $author_name = '"'.$_SESSION["user"]["username"].'"';
        $message = '"updated '.$_POST["name"].' in course '.$_POST["course_id"].'"';
        exec("python3 upload_github.py $id $_POST[course_id] $author_name $message", $output, $retval);
    }
}
else if($action == "story_delete") {
    $keys = ["id" => "int"];
    $id = intVal($_REQUEST['id']);
    query_one($db, "UPDATE story SET deleted = 1 WHERE id = $id;");
    //echo "UPDATE story SET deleted = 1 WHERE id = $id;";
    //query_one($db, "DELETE FROM story WHERE id = $id;");

    $newfile = fopen($id.".txt", "w");
    $str = $_POST["text"];
    fwrite($newfile, $str);
    fclose($newfile);

    $output=null;
    $retval=null;
    if(isset($_SESSION["user"])) {
        $author_name = '"'.$_SESSION["user"]["username"].'"';
        $message = '"delete '.$_POST["name"].' from course '.$_POST["course_id"].'"';
    }
    exec("python3 upload_github.py $id $_POST[course_id] $author_name $message delete", $output, $retval);
}
else {
    echo "unknown action";
}
