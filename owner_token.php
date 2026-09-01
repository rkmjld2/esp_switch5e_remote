<?php
/*
============================================================
 ESP-SWITCH5 REMOTE
 OWNER-ONLY DEVICE TOKEN MANAGEMENT
============================================================

Purpose:
    Change the device_token of a controller.

IMPORTANT:
    This page is OWNER ONLY.

    Customers must NOT receive:
        owner_token.php

Authentication:
    TOKEN_PASSWORD environment variable

Database:
    TiDB Cloud

Database:
    esp_switch5

Table:
    controllers

Timezone:
    Asia/Kolkata

============================================================
*/


/* =========================================================
   CONFIGURATION
========================================================= */

require_once __DIR__ . "/config.php";


/* =========================================================
   DATABASE
========================================================= */

require_once __DIR__ . "/db.php";


/* =========================================================
   SESSION
========================================================= */

session_start();


/* =========================================================
   LOGOUT
========================================================= */

if (isset($_GET["logout"])) {

    $_SESSION = [];

    session_destroy();

    header(
        "Location: owner_token.php"
    );

    exit;
}


/* =========================================================
   LOGIN
========================================================= */

$login_error = "";

if (isset($_POST["owner_login"])) {

    $password =
        $_POST["owner_password"] ?? "";


    if (
        $token_password !== "" &&
        hash_equals(
            $token_password,
            $password
        )
    ) {

        $_SESSION["esp_owner"] = true;

        header(
            "Location: owner_token.php"
        );

        exit;

    } else {

        $login_error =
            "Invalid owner password.";
    }
}


/* =========================================================
   OWNER LOGIN PAGE
========================================================= */

if (
    !isset($_SESSION["esp_owner"]) ||
    $_SESSION["esp_owner"] !== true
) {

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>ESP-SWITCH5 - Owner Login</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;
    padding: 20px;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f2f2f2;
}

.login-box {

    max-width: 450px;

    margin: 80px auto;

    background: white;

    padding: 30px;

    border-radius: 12px;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,0.15);

    text-align: center;
}

h1 {

    margin-top: 0;

    color: #333;
}

.warning {

    background: #fff3cd;

    color: #856404;

    border: 1px solid #ffeeba;

    padding: 12px;

    border-radius: 6px;

    margin-bottom: 20px;

    font-size: 14px;
}

input {

    width: 100%;

    padding: 12px;

    font-size: 16px;

    border: 1px solid #aaa;

    border-radius: 6px;

    margin: 15px 0;
}

button {

    width: 100%;

    padding: 12px;

    border: none;

    border-radius: 6px;

    background: #343a40;

    color: white;

    font-size: 16px;

    cursor: pointer;
}

button:hover {

    opacity: 0.85;
}

.error {

    color: #dc3545;

    margin-bottom: 10px;

    font-weight: bold;
}

.small {

    margin-top: 15px;

    color: #777;

    font-size: 13px;
}

</style>

</head>

<body>

<div class="login-box">

<h1>
ESP-SWITCH5
</h1>

<h2>
OWNER ACCESS
</h2>

<div class="warning">

This page is for owner use only.<br>
Do not give this page or its password to customers.

</div>

<?php

if ($login_error !== "") {

    echo
        '<div class="error">' .
        htmlspecialchars(
            $login_error,
            ENT_QUOTES,
            "UTF-8"
        ) .
        '</div>';
}

?>

<form method="post">

<input
    type="password"
    name="owner_password"
    placeholder="Enter owner password"
    required
    autofocus
>

<button
    type="submit"
    name="owner_login"
>
OWNER LOGIN
</button>

</form>

<div class="small">

ESP-SWITCH5 Device Token Management

</div>

</div>

</body>

</html>

<?php

exit;

}


/* =========================================================
   MESSAGE
========================================================= */

$message = "";

$message_type = "";


/* =========================================================
   CHANGE DEVICE TOKEN
========================================================= */

if (isset($_POST["change_token"])) {

    $controller_id =
        trim(
            $_POST["controller_id"] ?? ""
        );

    $new_token =
        trim(
            $_POST["new_token"] ?? ""
        );


    /* -----------------------------------------------------
       VALIDATE CONTROLLER ID
    ----------------------------------------------------- */

    if ($controller_id === "") {

        $message =
            "Controller ID is required.";

        $message_type =
            "error";
    }


    /* -----------------------------------------------------
       VALIDATE TOKEN
    ----------------------------------------------------- */

    elseif ($new_token === "") {

        $message =
            "New Device Token is required.";

        $message_type =
            "error";
    }


    elseif (
        !preg_match(
            '/^[A-Za-z0-9_-]{8,100}$/',
            $new_token
        )
    ) {

        $message =
            "Invalid Device Token. " .
            "Use 8-100 characters containing " .
            "letters, numbers, hyphen or underscore.";

        $message_type =
            "error";
    }


    else {

        /* -------------------------------------------------
           CHECK CONTROLLER
        ------------------------------------------------- */

        $stmt =
            $conn->prepare("
                SELECT controller_id
                FROM controllers
                WHERE controller_id = ?
                LIMIT 1
            ");


        if (!$stmt) {

            $message =
                "Controller query preparation failed.";

            $message_type =
                "error";

        } else {

            $stmt->bind_param(
                "s",
                $controller_id
            );


            if (!$stmt->execute()) {

                $message =
                    "Controller verification failed.";

                $message_type =
                    "error";

                $stmt->close();

            } else {

                $result =
                    $stmt->get_result();


                if ($result->num_rows === 0) {

                    $message =
                        "Controller not found.";

                    $message_type =
                        "error";

                    $stmt->close();

                } else {

                    $stmt->close();


                    /* -------------------------------------
                       UPDATE TOKEN
                    ------------------------------------- */

                    $update =
                        $conn->prepare("
                            UPDATE controllers
                            SET device_token = ?
                            WHERE controller_id = ?
                        ");


                    if (!$update) {

                        $message =
                            "Token update preparation failed.";

                        $message_type =
                            "error";

                    } else {

                        $update->bind_param(
                            "ss",
                            $new_token,
                            $controller_id
                        );


                        if ($update->execute()) {

                            $message =
                                "Device Token changed successfully " .
                                "for controller " .
                                $controller_id .
                                ".";

                            $message_type =
                                "success";

                        } else {

                            $message =
                                "Device Token update failed.";

                            $message_type =
                                "error";
                        }


                        $update->close();
                    }
                }
            }
        }
    }
}


/* =========================================================
   READ CONTROLLERS
========================================================= */

$controllers = [];

$result =
    $conn->query("
        SELECT
            controller_id,
            customer_name,
            active
        FROM controllers
        ORDER BY controller_id
    ");


if ($result) {

    while (
        $row =
            $result->fetch_assoc()
    ) {

        $controllers[] =
            $row;
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
ESP-SWITCH5 - Owner Token Management
</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;
    padding: 20px;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f2f2f2;

    color: #222;
}

.container {

    max-width: 700px;

    margin: 40px auto;

    background: white;

    padding: 30px;

    border-radius: 12px;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,0.15);
}

.header {

    text-align: center;

    margin-bottom: 25px;
}

.header h1 {

    margin: 0;

    color: #333;
}

.subtitle {

    color: #666;

    margin-top: 5px;
}

.owner-warning {

    background: #fff3cd;

    border: 1px solid #ffeeba;

    color: #856404;

    padding: 12px;

    border-radius: 6px;

    margin-bottom: 20px;

    text-align: center;

    font-weight: bold;
}

.form-box {

    background: #f8f9fa;

    border: 1px solid #ddd;

    border-radius: 10px;

    padding: 20px;
}

label {

    display: block;

    font-weight: bold;

    margin-bottom: 8px;
}

select,
input[type="text"] {

    width: 100%;

    padding: 12px;

    font-size: 16px;

    border: 1px solid #aaa;

    border-radius: 6px;

    margin-bottom: 18px;
}

.change-button {

    width: 100%;

    padding: 13px;

    border: none;

    border-radius: 6px;

    background: #dc3545;

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;
}

.change-button:hover {

    opacity: 0.85;
}

.message {

    padding: 12px;

    border-radius: 6px;

    margin-bottom: 20px;

    text-align: center;

    font-weight: bold;
}

.success {

    background: #d4edda;

    color: #155724;
}

.error {

    background: #f8d7da;

    color: #721c24;
}

.note {

    margin-top: 20px;

    padding: 15px;

    background: #eef6ff;

    border: 1px solid #b8d8f5;

    border-radius: 8px;

    font-size: 14px;

    line-height: 1.5;
}

.logout {

    display: block;

    text-align: center;

    margin-top: 20px;

    color: #555;

    text-decoration: none;
}

.logout:hover {

    text-decoration: underline;
}

</style>

</head>

<body>

<div class="container">

<div class="header">

<h1>
ESP-SWITCH5
</h1>

<div class="subtitle">
OWNER DEVICE TOKEN MANAGEMENT
</div>

</div>


<div class="owner-warning">

OWNER ONLY — DO NOT GIVE THIS PAGE TO CUSTOMERS

</div>


<?php

if ($message !== "") {

?>

<div
    class="message
    <?php
        echo $message_type === "success"
            ? "success"
            : "error";
    ?>"
>

<?php

echo htmlspecialchars(
    $message,
    ENT_QUOTES,
    "UTF-8"
);

?>

</div>

<?php

}

?>


<div class="form-box">

<form method="post">

<label for="controller_id">
Select Controller
</label>

<select
    name="controller_id"
    id="controller_id"
    required
>

<option value="">
-- Select Controller --
</option>

<?php

foreach (
    $controllers as $controller
) {

?>

<option
    value="<?php
        echo htmlspecialchars(
            $controller["controller_id"],
            ENT_QUOTES,
            "UTF-8"
        );
    ?>"
>

<?php

echo htmlspecialchars(
    $controller["controller_id"],
    ENT_QUOTES,
    "UTF-8"
);

if (
    !empty(
        $controller["customer_name"]
    )
) {

    echo
        " - " .
        htmlspecialchars(
            $controller["customer_name"],
            ENT_QUOTES,
            "UTF-8"
        );
}

?>

</option>

<?php

}

?>

</select>


<label for="new_token">
Enter New Device Token
</label>

<input
    type="text"
    name="new_token"
    id="new_token"
    placeholder="Example: ESP0001-TOKEN-2026-RAVI1"
    maxlength="100"
    required
    autocomplete="off"
>


<button
    type="submit"
    name="change_token"
    class="change-button"
    onclick="
        return confirm(
            'Are you sure you want to change the Device Token?'
        );
    "
>
CHANGE DEVICE TOKEN
</button>

</form>

</div>


<div class="note">

<strong>Important:</strong><br><br>

The new Device Token is written directly into the
<strong>controllers.device_token</strong> field.

The ESP8266 must be programmed with exactly the same
new token.

For example:

<br><br>

<strong>
ESP0001-TOKEN-2026-RAVI1
</strong>

<br><br>

If the token in the ESP8266 does not match the token
in the database, the server will reject the ESP8266.

Changing the token therefore immediately invalidates
the old token.

</div>


<a
    href="owner_token.php?logout=1"
    class="logout"
>
Owner Logout
</a>

</div>

</body>

</html>