```php
<?php
/*
============================================================
 ESP-SWITCH5 REMOTE
 OWNER-ONLY CONTROLLER MANAGEMENT
============================================================

Purpose:
    Add a new controller to the controllers table.

Authentication:
    TOKEN_PASSWORD environment variable

Database:
    esp_switch5

Table:
    controllers

This page does NOT modify:
    esp_control

Timezone:
    Asia/Kolkata
============================================================
*/


/* =========================================================
   START OUTPUT BUFFER
========================================================= */

ob_start();


/* =========================================================
   SESSION
========================================================= */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/* =========================================================
   CONFIGURATION
========================================================= */

require_once __DIR__ . "/config.php";


/* =========================================================
   DATABASE
========================================================= */

require_once __DIR__ . "/db.php";


/* =========================================================
   VARIABLES
========================================================= */

$login_error = "";

$message = "";

$message_type = "";


/* =========================================================
   LOGOUT
========================================================= */

if (isset($_GET["logout"])) {

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: owner_token.php");

    exit;
}


/* =========================================================
   OWNER LOGIN
========================================================= */

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

        header("Location: owner_token.php");

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

<title>
ESP-SWITCH5 - Owner Login
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

h2 {

    color: #555;

    margin-bottom: 20px;
}

.warning {

    background: #fff3cd;

    color: #856404;

    border: 1px solid #ffeeba;

    padding: 12px;

    border-radius: 6px;

    margin-bottom: 20px;

    font-size: 14px;

    line-height: 1.5;
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

ESP-SWITCH5 Controller Management

</div>

</div>

</body>

</html>

<?php

    ob_end_flush();

    exit;
}


/* =========================================================
   ADD NEW CONTROLLER
========================================================= */

if (isset($_POST["add_controller"])) {

    $controller_id =
        trim(
            $_POST["controller_id"] ?? ""
        );

    $customer_name =
        trim(
            $_POST["customer_name"] ?? ""
        );

    $customer_token =
        trim(
            $_POST["customer_token"] ?? ""
        );

    $device_token =
        trim(
            $_POST["device_token"] ?? ""
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


    elseif (
        !preg_match(
            '/^[A-Za-z0-9_-]{1,50}$/',
            $controller_id
        )
    ) {

        $message =
            "Invalid Controller ID. " .
            "Use only letters, numbers, hyphen or underscore.";

        $message_type =
            "error";
    }


    /* -----------------------------------------------------
       VALIDATE CUSTOMER NAME
    ----------------------------------------------------- */

    elseif (
        strlen($customer_name) > 100
    ) {

        $message =
            "Customer name cannot exceed 100 characters.";

        $message_type =
            "error";
    }


    /* -----------------------------------------------------
       VALIDATE CUSTOMER TOKEN
    ----------------------------------------------------- */

    elseif (
        strlen($customer_token) > 100
    ) {

        $message =
            "Customer token cannot exceed 100 characters.";

        $message_type =
            "error";
    }


    /* -----------------------------------------------------
       VALIDATE DEVICE TOKEN
    ----------------------------------------------------- */

    elseif ($device_token === "") {

        $message =
            "Device Token is required.";

        $message_type =
            "error";
    }


    elseif (
        !preg_match(
            '/^[A-Za-z0-9_-]{8,100}$/',
            $device_token
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
           CHECK DUPLICATE CONTROLLER ID
        ------------------------------------------------- */

        $stmt =
            $conn->prepare("
                SELECT id
                FROM controllers
                WHERE controller_id = ?
                LIMIT 1
            ");


        if (!$stmt) {

            $message =
                "Controller verification failed.";

            $message_type =
                "error";

        } else {

            $stmt->bind_param(
                "s",
                $controller_id
            );

            $stmt->execute();

            $result =
                $stmt->get_result();


            if ($result->num_rows > 0) {

                $message =
                    "Controller ID already exists.";

                $message_type =
                    "error";

                $stmt->close();

            } else {

                $stmt->close();


                /* -----------------------------------------
                   CHECK DUPLICATE DEVICE TOKEN
                ----------------------------------------- */

                $stmt =
                    $conn->prepare("
                        SELECT id
                        FROM controllers
                        WHERE device_token = ?
                        LIMIT 1
                    ");


                if (!$stmt) {

                    $message =
                        "Device Token verification failed.";

                    $message_type =
                        "error";

                } else {

                    $stmt->bind_param(
                        "s",
                        $device_token
                    );

                    $stmt->execute();

                    $result =
                        $stmt->get_result();


                    if ($result->num_rows > 0) {

                        $message =
                            "Device Token already exists. " .
                            "Please use a different Device Token.";

                        $message_type =
                            "error";

                        $stmt->close();

                    } else {

                        $stmt->close();


                        /* ---------------------------------
                           INSERT CONTROLLER
                        --------------------------------- */

                        $stmt =
                            $conn->prepare("
                                INSERT INTO controllers
                                (
                                    controller_id,
                                    customer_token,
                                    device_token,
                                    customer_name,
                                    active,
                                    last_seen,
                                    start_time,
                                    end_time
                                )
                                VALUES
                                (
                                    ?,
                                    ?,
                                    ?,
                                    ?,
                                    1,
                                    NULL,
                                    NULL,
                                    NULL
                                )
                            ");


                        if (!$stmt) {

                            $message =
                                "Controller insertion preparation failed.";

                            $message_type =
                                "error";

                        } else {

                            $stmt->bind_param(
                                "ssss",
                                $controller_id,
                                $customer_token,
                                $device_token,
                                $customer_name
                            );


                            if ($stmt->execute()) {

                                $message =
                                    "Controller " .
                                    $controller_id .
                                    " added successfully.";

                                $message_type =
                                    "success";


                                /*
                                 * Clear form values after
                                 * successful insertion.
                                 */

                                $controller_id = "";

                                $customer_name = "";

                                $customer_token = "";

                                $device_token = "";

                            } else {

                                $message =
                                    "Controller could not be added.";

                                $message_type =
                                    "error";
                            }


                            $stmt->close();
                        }
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
            active,
            last_seen
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
ESP-SWITCH5 - Owner Controller Management
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

    max-width: 750px;

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

.form-title {

    text-align: center;

    margin-top: 0;

    color: #333;
}

label {

    display: block;

    font-weight: bold;

    margin-bottom: 8px;
}

input[type="text"] {

    width: 100%;

    padding: 12px;

    font-size: 16px;

    border: 1px solid #aaa;

    border-radius: 6px;

    margin-bottom: 18px;
}

.add-button {

    width: 100%;

    padding: 13px;

    border: none;

    border-radius: 6px;

    background: #28a745;

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;
}

.add-button:hover {

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

.controller-list {

    margin-top: 25px;

    border-top: 1px solid #ddd;

    padding-top: 20px;
}

.controller {

    background: #fafafa;

    border: 1px solid #ddd;

    border-radius: 8px;

    padding: 12px;

    margin-bottom: 10px;
}

.controller-id {

    font-weight: bold;

    font-size: 17px;
}

.active {

    color: #198754;

    font-weight: bold;
}

.inactive {

    color: #dc3545;

    font-weight: bold;
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

.small-note {

    color: #777;

    font-size: 13px;

    margin-top: 5px;
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
OWNER CONTROLLER MANAGEMENT
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
        echo
            $message_type === "success"
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

<h2 class="form-title">
ADD NEW CONTROLLER
</h2>


<form method="post">


<label for="controller_id">
Controller ID
</label>

<input
    type="text"
    name="controller_id"
    id="controller_id"
    maxlength="50"
    placeholder="Example: ESP0002"
    value="<?php
        echo htmlspecialchars(
            $controller_id,
            ENT_QUOTES,
            "UTF-8"
        );
    ?>"
    required
    autocomplete="off"
>


<label for="customer_name">
Customer Name
</label>

<input
    type="text"
    name="customer_name"
    id="customer_name"
    maxlength="100"
    placeholder="Example: Ravi"
    value="<?php
        echo htmlspecialchars(
            $customer_name,
            ENT_QUOTES,
            "UTF-8"
        );
    ?>"
    autocomplete="off"
>


<label for="customer_token">
Customer Token
</label>

<input
    type="text"
    name="customer_token"
    id="customer_token"
    maxlength="100"
    placeholder="Enter customer token"
    value="<?php
        echo htmlspecialchars(
            $customer_token,
            ENT_QUOTES,
            "UTF-8"
        );
    ?>"
    autocomplete="off"
>


<label for="device_token">
Device Token
</label>

<input
    type="text"
    name="device_token"
    id="device_token"
    maxlength="100"
    placeholder="Example: ESP0002-TOKEN-2026-RAVI"
    value="<?php
        echo htmlspecialchars(
            $device_token,
            ENT_QUOTES,
            "UTF-8"
        );
    ?>"
    required
    autocomplete="off"
>


<button
    type="submit"
    name="add_controller"
    class="add-button"
    onclick="
        return confirm(
            'Are you sure you want to add this controller?'
        );
    "
>
ADD CONTROLLER
</button>


</form>

</div>


<div class="note">

<strong>What will be added:</strong>

<br><br>

<strong>controller_id</strong> —
Controller ID entered above.

<br>

<strong>customer_token</strong> —
Customer token entered above.

<br>

<strong>device_token</strong> —
Device token entered above.

<br>

<strong>customer_name</strong> —
Customer name entered above.

<br>

<strong>active</strong> —
Automatically set to <strong>1</strong>.

<br>

<strong>last_seen</strong> —
Initially <strong>NULL</strong>.

<br>

<strong>start_time</strong> —
Initially <strong>NULL</strong>.

<br>

<strong>end_time</strong> —
Initially <strong>NULL</strong>.

<br><br>

This page writes only to the
<strong>controllers</strong> table.

</div>


<?php

if (!empty($controllers)) {

?>

<div class="controller-list">

<h3>
Existing Controllers
</h3>

<?php

foreach (
    $controllers as $controller
) {

?>

<div class="controller">

<div class="controller-id">

<?php

echo htmlspecialchars(
    $controller["controller_id"],
    ENT_QUOTES,
    "UTF-8"
);

?>

</div>

<div>

<?php

if (!empty($controller["customer_name"])) {

    echo htmlspecialchars(
        $controller["customer_name"],
        ENT_QUOTES,
        "UTF-8"
    );

} else {

    echo "No customer name";
}

?>

</div>

<div>

Status:

<?php

if (
    (int)$controller["active"] === 1
) {

?>

<span class="active">
ACTIVE
</span>

<?php

} else {

?>

<span class="inactive">
INACTIVE
</span>

<?php

}

?>

</div>

</div>

<?php

}

?>

</div>

<?php

}

?>


<a
    href="owner_token.php?logout=1"
    class="logout"
>
Owner Logout
</a>


</div>

</body>

</html>

<?php

ob_end_flush();
?>
```
