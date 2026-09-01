
<?php
ob_start();

session_start();

if (isset($_POST["login"])) {

    $password = $_POST["password"] ?? "";

    if (
        isset($token_password) &&
        $token_password !== "" &&
        hash_equals($token_password, $password)
    ) {
        $_SESSION["esp_owner"] = true;

        header("Location: owner_token.php");

        exit;
    }

    $error = "Invalid owner password.";

} else {

    $error = "";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>ESP-SWITCH5 Owner Login</title>

<style>

body {
    margin: 0;
    padding: 20px;
    font-family: Arial, sans-serif;
    background: #f2f2f2;
}

.box {
    max-width: 450px;
    margin: 80px auto;
    padding: 30px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.15);
    text-align: center;
}

input {
    width: 100%;
    box-sizing: border-box;
    padding: 12px;
    margin: 15px 0;
    font-size: 16px;
}

button {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 6px;
    background: #343a40;
    color: white;
    font-size: 16px;
}

.error {
    color: #dc3545;
    font-weight: bold;
    margin-bottom: 15px;
}

</style>

</head>

<body>

<div class="box">

<h1>ESP-SWITCH5</h1>

<h2>OWNER ACCESS</h2>

<?php

if ($error !== "") {

    echo
        '<div class="error">' .
        htmlspecialchars(
            $error,
            ENT_QUOTES,
            "UTF-8"
        ) .
        '</div>';
}

?>

<form method="post">

<input
    type="password"
    name="password"
    placeholder="Enter owner password"
    required
    autofocus
>

<button
    type="submit"
    name="login"
>
OWNER LOGIN
</button>

</form>

</div>

</body>

</html>

<?php
ob_end_flush();
?>

