<?php
/*
============================================================
 ESP-SWITCH5 REMOTE - index.php
============================================================

 TWO MODES

 1. ADMINISTRATOR
    /
    Administrator logs in and can select controllers.

 2. CUSTOMER
    /c/ESP0001?t=CUSTOMER_TOKEN

    Customer can access ONLY the controller for which
    the customer token is valid.

============================================================
*/

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

date_default_timezone_set("Asia/Kolkata");

session_start();


/* =========================================================
   BASIC VARIABLES
========================================================= */

$login_error = "";
$message = "";
$message_type = "";

$customer_mode = false;
$customer_token = trim($_GET["t"] ?? "");

$selected_controller =
    trim($_GET["controller_id"] ?? "");


/* =========================================================
   DETERMINE CUSTOMER MODE
=========================================================

   A customer request must contain:

       controller_id
       customer token

   Administrator mode has no customer token.

========================================================= */

if (
    $selected_controller !== "" &&
    $customer_token !== ""
) {
    $customer_mode = true;
}


/* =========================================================
   CUSTOMER TOKEN VALIDATION
========================================================= */

if ($customer_mode) {

    $stmt = $conn->prepare("
        SELECT
            id,
            controller_id,
            device_token,
            customer_name,
            customer_token,
            active,
            last_seen,
            start_time,
            end_time
        FROM controllers
        WHERE controller_id = ?
          AND customer_token = ?
        LIMIT 1
    ");

    if (!$stmt) {

        die("Customer authentication preparation failed.");

    }

    $stmt->bind_param(
        "ss",
        $selected_controller,
        $customer_token
    );

    if (!$stmt->execute()) {

        $stmt->close();

        die("Customer authentication failed.");

    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        http_response_code(403);

        die("
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport'
                      content='width=device-width, initial-scale=1.0'>
                <title>Access Denied</title>

                <style>
                    body {
                        margin: 0;
                        padding: 30px;
                        font-family: Arial, Helvetica, sans-serif;
                        background: #f2f2f2;
                    }

                    .box {
                        max-width: 500px;
                        margin: 80px auto;
                        padding: 30px;
                        background: white;
                        border-radius: 12px;
                        box-shadow:
                            0 3px 15px
                            rgba(0,0,0,0.15);
                        text-align: center;
                    }

                    h1 {
                        color: #dc3545;
                    }

                    p {
                        color: #555;
                    }
                </style>
            </head>

            <body>

            <div class='box'>

                <h1>ACCESS DENIED</h1>

                <p>
                    Invalid controller access token.
                </p>

                <p>
                    This controller is not authorized
                    for this customer link.
                </p>

            </div>

            </body>
            </html>
        ");

    }

    $customer_controller =
        $result->fetch_assoc();

    $stmt->close();


    /*
       IMPORTANT:

       Customer is permanently locked to the
       controller authenticated above.

       Never accept another controller ID from
       POST data later.
    */

}


/* =========================================================
   LOGOUT
========================================================= */

if (
    !$customer_mode &&
    isset($_GET["logout"])
) {

    $_SESSION = [];

    session_destroy();

    header("Location: index.php");

    exit;
}


/* =========================================================
   ADMIN LOGIN
========================================================= */

if (
    !$customer_mode &&
    isset($_POST["login"])
) {

    $password =
        $_POST["password"] ?? "";

    if (
        $admin_password !== "" &&
        hash_equals(
            $admin_password,
            $password
        )
    ) {

        $_SESSION["esp_admin"] = true;

        header("Location: index.php");

        exit;

    } else {

        $login_error =
            "Invalid password.";

    }
}


/* =========================================================
   ADMIN LOGIN PAGE
========================================================= */

if (
    !$customer_mode &&
    (
        !isset($_SESSION["esp_admin"]) ||
        $_SESSION["esp_admin"] !== true
    )
) {
?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>ESP-SWITCH5 REMOTE - Login</title>

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

    max-width: 420px;

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

input[type="password"] {

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

    background: #007bff;

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
ESP-SWITCH5 REMOTE
</h1>

<p>
Administrator Login
</p>

<?php

if ($login_error !== "") {

    echo '<div class="error">' .
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
    name="password"
    placeholder="Enter administrator password"
    required
    autofocus
>

<button
    type="submit"
    name="login"
>
LOGIN
</button>

</form>

<div class="small">
Remote ESP8266 Control System
</div>

</div>

</body>

</html>

<?php

exit;

}


/* =========================================================
   ADMIN MODE
========================================================= */

if (!$customer_mode) {

    /*
       Administrator-selected controller.
    */

    $selected_controller =
        trim($_GET["controller_id"] ?? "");

}


/* =========================================================
   SAVE START TIME
========================================================= */

if (isset($_POST["save_start"])) {

    /*
       Customer mode:
       controller comes ONLY from authenticated
       customer controller.

       Administrator mode:
       controller comes from POST.
    */

    if ($customer_mode) {

        $controller_id =
            $customer_controller["controller_id"];

    } else {

        $controller_id =
            trim(
                $_POST["controller_id"] ?? ""
            );

    }

    $start_time =
        trim(
            $_POST["start_time"] ?? ""
        );


    if ($controller_id === "") {

        $message =
            "Controller ID missing.";

        $message_type =
            "error";

    }

    elseif ($start_time === "") {

        $message =
            "Start date and time missing.";

        $message_type =
            "error";

    }

    else {

        $start_datetime =
            str_replace(
                "T",
                " ",
                $start_time
            );

        if (
            strlen($start_datetime) === 16
        ) {

            $start_datetime .= ":00";
        }


        /*
           Verify controller exists.
        */

        $stmt = $conn->prepare("
            UPDATE controllers
            SET start_time = ?
            WHERE controller_id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ss",
                $start_datetime,
                $controller_id
            );

            if ($stmt->execute()) {

                $message =
                    "Start time saved successfully.";

                $message_type =
                    "success";

            } else {

                $message =
                    "Could not save start time.";

                $message_type =
                    "error";
            }

            $stmt->close();

        } else {

            $message =
                "Start time preparation failed.";

            $message_type =
                "error";
        }
    }

    $selected_controller =
        $controller_id;
}


/* =========================================================
   SAVE END TIME
========================================================= */

if (isset($_POST["save_end"])) {

    if ($customer_mode) {

        $controller_id =
            $customer_controller["controller_id"];

    } else {

        $controller_id =
            trim(
                $_POST["controller_id"] ?? ""
            );

    }

    $end_time =
        trim(
            $_POST["end_time"] ?? ""
        );


    if ($controller_id === "") {

        $message =
            "Controller ID missing.";

        $message_type =
            "error";

    }

    elseif ($end_time === "") {

        $message =
            "End date and time missing.";

        $message_type =
            "error";

    }

    else {

        $end_datetime =
            str_replace(
                "T",
                " ",
                $end_time
            );

        if (
            strlen($end_datetime) === 16
        ) {

            $end_datetime .= ":00";
        }


        $stmt = $conn->prepare("
            UPDATE controllers
            SET end_time = ?
            WHERE controller_id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ss",
                $end_datetime,
                $controller_id
            );

            if ($stmt->execute()) {

                $message =
                    "End time saved successfully.";

                $message_type =
                    "success";

            } else {

                $message =
                    "Could not save end time.";

                $message_type =
                    "error";
            }

            $stmt->close();

        } else {

            $message =
                "End time preparation failed.";

            $message_type =
                "error";
        }
    }

    $selected_controller =
        $controller_id;
}


/* =========================================================
   SET PIN
========================================================= */

if (isset($_POST["set_pin"])) {

    /*
       Customer mode:
       NEVER accept controller_id from POST.
    */

    if ($customer_mode) {

        $controller_id =
            $customer_controller["controller_id"];

    } else {

        $controller_id =
            trim(
                $_POST["controller_id"] ?? ""
            );
    }


    $pin =
        strtoupper(
            trim(
                $_POST["pin"] ?? ""
            )
        );

    $value =
        isset($_POST["value"])
            ? (int)$_POST["value"]
            : -1;


    if ($controller_id === "") {

        $message =
            "Controller ID missing.";

        $message_type =
            "error";
    }

    elseif (
        !preg_match(
            '/^D[1-8]$/',
            $pin
        )
    ) {

        $message =
            "Invalid pin.";

        $message_type =
            "error";
    }

    elseif (
        $value !== 0 &&
        $value !== 1
    ) {

        $message =
            "Invalid value.";

        $message_type =
            "error";
    }

    else {

        /*
           Get controller information.
        */

        $stmt = $conn->prepare("
            SELECT
                active,
                start_time,
                end_time
            FROM controllers
            WHERE controller_id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $message =
                "Controller query failed.";

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


            if ($result->num_rows === 0) {

                $message =
                    "Controller not found.";

                $message_type =
                    "error";

            } else {

                $controller =
                    $result->fetch_assoc();


                /*
                   Controller active?
                */

                if (
                    (int)$controller["active"] !== 1
                ) {

                    $message =
                        "Controller is inactive.";

                    $message_type =
                        "error";

                } else {

                    /*
                       Calendar check.
                    */

                    $calendar_allowed =
                        true;

                    $start_time =
                        $controller["start_time"] ?? null;

                    $end_time =
                        $controller["end_time"] ?? null;


                    if (
                        !empty($start_time) &&
                        !empty($end_time)
                    ) {

                        try {

                            $now =
                                new DateTime(
                                    "now",
                                    new DateTimeZone(
                                        "Asia/Kolkata"
                                    )
                                );

                            $start =
                                new DateTime(
                                    $start_time,
                                    new DateTimeZone(
                                        "Asia/Kolkata"
                                    )
                                );

                            $end =
                                new DateTime(
                                    $end_time,
                                    new DateTimeZone(
                                        "Asia/Kolkata"
                                    )
                                );


                            if (
                                $now < $start ||
                                $now > $end
                            ) {

                                $calendar_allowed =
                                    false;
                            }

                        }
                        catch (Exception $e) {

                            $calendar_allowed =
                                false;
                        }
                    }


                    if (!$calendar_allowed) {

                        $message =
                            "Controller is outside its permitted calendar time.";

                        $message_type =
                            "error";

                    } else {

                        /*
                           Calendar is active.
                           Now update the output.
                        */

                        $sql = "
                            UPDATE esp_control
                            SET `$pin` = ?
                            WHERE controller_id = ?
                        ";

                        $update =
                            $conn->prepare(
                                $sql
                            );


                        if (!$update) {

                            $message =
                                "Pin update preparation failed.";

                            $message_type =
                                "error";

                        } else {

                            $update->bind_param(
                                "is",
                                $value,
                                $controller_id
                            );


                            if (
                                $update->execute()
                            ) {

                                $message =
                                    $pin .
                                    " changed to " .
                                    (
                                        $value
                                            ? "ON"
                                            : "OFF"
                                    );

                                $message_type =
                                    "success";

                            } else {

                                $message =
                                    "Pin update failed.";

                                $message_type =
                                    "error";
                            }


                            $update->close();
                        }
                    }
                }
            }

            $stmt->close();
        }
    }

    $selected_controller =
        $controller_id;
}


/* =========================================================
   READ CONTROLLERS FOR ADMINISTRATOR
========================================================= */

$controllers = [];


if (!$customer_mode) {

    $result =
        $conn->query("
            SELECT
                controller_id,
                customer_name,
                active
            FROM controllers
            WHERE active = 1
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
}


/* =========================================================
   CONTROLLER INFORMATION
========================================================= */

$selected_customer = "";
$selected_active = 0;
$selected_last_seen = "";
$selected_start_time = "";
$selected_end_time = "";


if ($selected_controller !== "") {

    if ($customer_mode) {

        /*
           Use the authenticated controller.
        */

        $row =
            $customer_controller;

    } else {

        $stmt = $conn->prepare("
            SELECT
                controller_id,
                customer_name,
                active,
                last_seen,
                start_time,
                end_time
            FROM controllers
            WHERE controller_id = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "s",
                $selected_controller
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            if (
                $result->num_rows > 0
            ) {

                $row =
                    $result->fetch_assoc();

            } else {

                $row = null;
            }

            $stmt->close();

        } else {

            $row = null;
        }
    }


    if (!empty($row)) {

        $selected_customer =
            $row["customer_name"] ?? "";

        $selected_active =
            (int)(
                $row["active"] ?? 0
            );


        if (
            isset($row["last_seen"]) &&
            $row["last_seen"] !== null &&
            $row["last_seen"] !== ""
        ) {

            $selected_last_seen =
                $row["last_seen"];

        } else {

            $selected_last_seen =
                "Not yet seen";
        }


        $selected_start_time =
            $row["start_time"] ?? "";

        $selected_end_time =
            $row["end_time"] ?? "";
    }
}


/* =========================================================
   FORMAT DATETIME FOR HTML
========================================================= */

$start_input_value = "";
$end_input_value = "";


if ($selected_start_time !== "") {

    $timestamp =
        strtotime(
            $selected_start_time
        );

    if ($timestamp !== false) {

        $start_input_value =
            date(
                "Y-m-d\TH:i",
                $timestamp
            );
    }
}


if ($selected_end_time !== "") {

    $timestamp =
        strtotime(
            $selected_end_time
        );

    if ($timestamp !== false) {

        $end_input_value =
            date(
                "Y-m-d\TH:i",
                $timestamp
            );
    }
}


/* =========================================================
   READ D1-D8
========================================================= */

$pin_values = [

    "D1" => 0,
    "D2" => 0,
    "D3" => 0,
    "D4" => 0,
    "D5" => 0,
    "D6" => 0,
    "D7" => 0,
    "D8" => 0
];


if ($selected_controller !== "") {

    $stmt = $conn->prepare("
        SELECT
            D1,
            D2,
            D3,
            D4,
            D5,
            D6,
            D7,
            D8
        FROM esp_control
        WHERE controller_id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "s",
            $selected_controller
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        if (
            $result->num_rows > 0
        ) {

            $row =
                $result->fetch_assoc();

            for (
                $i = 1;
                $i <= 8;
                $i++
            ) {

                $pin =
                    "D" . $i;

                $pin_values[$pin] =
                    (int)(
                        $row[$pin] ?? 0
                    );
            }
        }

        $stmt->close();
    }
}


/* =========================================================
   CUSTOMER URL
========================================================= */

$customer_url = "";


if ($customer_mode) {

    $customer_url =
        "https://" .
        ($_SERVER["HTTP_HOST"] ?? "") .
        "/c/" .
        rawurlencode(
            $selected_controller
        ) .
        "?t=" .
        rawurlencode(
            $customer_token
        );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
ESP-SWITCH5 REMOTE
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

    max-width: 950px;

    margin: auto;

    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,0.15);
}

.header {

    position: relative;

    text-align: center;

    margin-bottom: 25px;
}

h1 {

    margin: 0 0 5px 0;

    color: #333;
}

.subtitle {

    color: #666;
}

.logout {

    position: absolute;

    right: 0;

    top: 0;

    text-decoration: none;

    background: #6c757d;

    color: white;

    padding: 8px 12px;

    border-radius: 5px;

    font-size: 13px;
}

.logout:hover {

    opacity: 0.85;
}


/* CUSTOMER CONTROLLER */

.customer-controller {

    background: #eef6ff;

    border: 1px solid #b8d8f5;

    border-radius: 10px;

    padding: 15px;

    text-align: center;

    margin-bottom: 20px;
}

.customer-controller .id {

    font-size: 24px;

    font-weight: bold;
}

.customer-controller .customer {

    margin-top: 5px;

    color: #555;
}


/* CONTROLLER SELECTION */

.controller-box {

    background: #f7f7f7;

    border: 1px solid #ddd;

    border-radius: 10px;

    padding: 20px;

    margin-bottom: 20px;
}

.controller-box label {

    display: block;

    font-weight: bold;

    margin-bottom: 8px;
}

.controller-box select {

    width: 100%;

    padding: 12px;

    font-size: 16px;

    border: 1px solid #aaa;

    border-radius: 6px;
}


/* CALENDAR */

.time-control {

    background: #eef6ff;

    border: 1px solid #b8d8f5;

    border-radius: 10px;

    padding: 20px;

    margin-bottom: 25px;

    text-align: center;
}

.time-control h2 {

    margin-top: 0;

    margin-bottom: 5px;

    color: #333;
}

.timezone {

    color: #555;

    font-size: 14px;

    margin-bottom: 20px;
}

.time-row {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;

    margin-bottom: 15px;
}

.time-box {

    background: white;

    border: 1px solid #ccc;

    border-radius: 8px;

    padding: 18px;
}

.time-box label {

    display: block;

    font-weight: bold;

    margin-bottom: 10px;

    font-size: 16px;
}

.time-box input[type="datetime-local"] {

    width: 100%;

    min-height: 52px;

    padding: 12px;

    border: 2px solid #aaa;

    border-radius: 8px;

    font-size: 18px;

    background: white;

    cursor: pointer;
}

.time-box input[type="datetime-local"]:focus {

    border-color: #007bff;

    outline: none;

    box-shadow:
        0 0 5px
        rgba(0,123,255,0.35);
}

.save-button {

    margin-top: 12px;

    width: 100%;

    background: #007bff;

    color: white;

    border: none;

    border-radius: 6px;

    padding: 12px;

    font-size: 16px;

    cursor: pointer;
}


/* CURRENT TIME */

.current-time-box {

    margin-top: 18px;

    background: #fff;

    border: 2px solid #28a745;

    border-radius: 8px;

    padding: 15px;
}

.current-time-title {

    font-size: 14px;

    color: #555;

    margin-bottom: 5px;
}

.current-time {

    font-size: 22px;

    font-weight: bold;

    color: #155724;
}


/* INFORMATION */

.info {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(170px, 1fr)
        );

    gap: 12px;

    margin-bottom: 25px;
}

.info-card {

    background: #fafafa;

    border: 1px solid #ddd;

    border-radius: 8px;

    padding: 12px;

    text-align: center;
}

.info-title {

    font-size: 13px;

    color: #666;

    margin-bottom: 5px;
}

.info-value {

    font-weight: bold;

    font-size: 16px;
}


/* ONLINE */

.online {

    color: #198754;

    font-weight: bold;
}

.offline {

    color: #dc3545;

    font-weight: bold;
}

.status-dot {

    display: inline-block;

    width: 12px;

    height: 12px;

    border-radius: 50%;

    margin-right: 6px;
}

.status-online {

    background: #28a745;
}

.status-offline {

    background: #dc3545;
}


/* PIN GRID */

.pin-grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(180px, 1fr)
        );

    gap: 15px;
}

.pin-card {

    border: 1px solid #ccc;

    border-radius: 10px;

    padding: 18px;

    text-align: center;

    background: #fafafa;
}

.pin-name {

    font-size: 20px;

    font-weight: bold;

    margin-bottom: 10px;
}

.state {

    font-size: 18px;

    font-weight: bold;

    margin-bottom: 12px;
}

.state-on {

    color: green;
}

.state-off {

    color: red;
}

.pin-form {

    display: inline-block;

    margin: 0;
}

button {

    border: none;

    border-radius: 6px;

    padding: 10px 16px;

    margin: 4px;

    font-size: 15px;

    cursor: pointer;
}

.on-btn {

    background: #28a745;

    color: white;
}

.off-btn {

    background: #dc3545;

    color: white;
}

button:hover {

    opacity: 0.85;
}


/* MESSAGE */

.message {

    text-align: center;

    margin: 20px 0;

    padding: 10px;

    border-radius: 6px;

    font-weight: bold;
}

.success {

    color: #155724;

    background: #d4edda;
}

.error {

    color: #721c24;

    background: #f8d7da;
}


/* MOBILE */

@media (max-width: 600px) {

    body {

        padding: 10px;
    }

    .container {

        padding: 15px;
    }

    .logout {

        position: static;

        display: inline-block;

        margin-top: 10px;
    }

    .pin-grid {

        grid-template-columns:
            1fr 1fr;
    }

    .time-row {

        grid-template-columns:
            1fr;
    }

    .time-box
    input[type="datetime-local"] {

        font-size: 17px;

        min-height: 55px;
    }
}

</style>

</head>


<body>

<div class="container">


<div class="header">

<h1>
ESP-SWITCH5 REMOTE
</h1>

<div class="subtitle">

<?php

if ($customer_mode) {

    echo "Customer Controller";

} else {

    echo "Remote ESP8266 Control Panel";
}

?>

</div>


<?php

if (!$customer_mode) {

?>

<a
    class="logout"
    href="index.php?logout=1"
>
Logout
</a>

<?php

}

?>

</div>


<?php

/* =========================================================
   CUSTOMER HEADER
========================================================= */

if ($customer_mode) {

?>

<div class="customer-controller">

<div class="id">

<?php

echo htmlspecialchars(
    $selected_controller,
    ENT_QUOTES,
    "UTF-8"
);

?>

</div>

<div class="customer">

<?php

echo htmlspecialchars(
    $selected_customer,
    ENT_QUOTES,
    "UTF-8"
);

?>

</div>

</div>

<?php

}


/* =========================================================
   ADMIN CONTROLLER SELECTION
========================================================= */

if (!$customer_mode) {

?>

<div class="controller-box">

<label for="controller">

Select Controller

</label>

<select
    id="controller"
    onchange="selectController(this.value)"
>

<option value="">

-- Select Controller --

</option>

<?php

foreach (
    $controllers
    as $controller
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

<?php

if (
    $selected_controller ===
    $controller["controller_id"]
) {

    echo "selected";
}

?>

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

    echo " - ";

    echo htmlspecialchars(
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

</div>

<?php

}


/* =========================================================
   MESSAGE
========================================================= */

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


/* =========================================================
   CONTROLLER PANEL
========================================================= */

if ($selected_controller !== "") {

?>


<!-- CALENDAR -->

<div class="time-control">

<h2>
Calendar Time Control
</h2>

<div class="timezone">

Calendar:
Asia/Kolkata
(India Standard Time)

</div>


<div class="time-row">


<!-- START -->

<div class="time-box">

<form method="post">

<?php

if (!$customer_mode) {

?>

<input
    type="hidden"
    name="controller_id"
    value="<?php

    echo htmlspecialchars(
        $selected_controller,
        ENT_QUOTES,
        "UTF-8"
    );

    ?>"
>

<?php

}

?>

<label for="start_time">

START TIME

</label>

<input
    type="datetime-local"
    id="start_time"
    name="start_time"
    value="<?php

    echo htmlspecialchars(
        $start_input_value,
        ENT_QUOTES,
        "UTF-8"
    );

    ?>"
    required
>

<button
    type="submit"
    name="save_start"
    class="save-button"
>
SAVE START
</button>

</form>

</div>


<!-- END -->

<div class="time-box">

<form method="post">

<?php

if (!$customer_mode) {

?>

<input
    type="hidden"
    name="controller_id"
    value="<?php

    echo htmlspecialchars(
        $selected_controller,
        ENT_QUOTES,
        "UTF-8"
    );

    ?>"
>

<?php

}

?>

<label for="end_time">

END TIME

</label>

<input
    type="datetime-local"
    id="end_time"
    name="end_time"
    value="<?php

    echo htmlspecialchars(
        $end_input_value,
        ENT_QUOTES,
        "UTF-8"
    );

    ?>"
    required
>

<button
    type="submit"
    name="save_end"
    class="save-button"
>
SAVE END
</button>

</form>

</div>

</div>


<!-- CURRENT TIME -->

<div class="current-time-box">

<div class="current-time-title">

CURRENT TIME

</div>

<div
    class="current-time"
    id="currentTime"
>
Loading current time...
</div>

</div>

</div>


<!-- CONTROLLER INFORMATION -->

<div class="info">


<div class="info-card">

<div class="info-title">

Controller ID

</div>

<div class="info-value">

<?php

echo htmlspecialchars(
    $selected_controller,
    ENT_QUOTES,
    "UTF-8"
);

?>

</div>

</div>


<div class="info-card">

<div class="info-title">

Customer

</div>

<div class="info-value">

<?php

echo htmlspecialchars(
    $selected_customer,
    ENT_QUOTES,
    "UTF-8"
);

?>

</div>

</div>


<div class="info-card">

<div class="info-title">

Controller Status

</div>

<div
    class="info-value"
    id="onlineStatus"
>
Checking...
</div>

</div>


<div class="info-card">

<div class="info-title">

Last Seen

</div>

<div
    class="info-value"
    id="lastSeen"
>

<?php

echo htmlspecialchars(
    $selected_last_seen,
    ENT_QUOTES,
    "UTF-8"
);

?>

</div>

</div>


</div>


<!-- D1-D8 -->

<div class="pin-grid">

<?php

for (
    $i = 1;
    $i <= 8;
    $i++
) {

    $pin =
        "D" . $i;

    $value =
        $pin_values[$pin];

?>

<div class="pin-card">

<div class="pin-name">

<?php

echo $pin;

?>

</div>


<div
    class="state
    <?php

    echo
        $value
            ? "state-on"
            : "state-off";

    ?>"
>

<?php

echo
    $value
        ? "ON"
        : "OFF";

?>

</div>


<!-- ON -->

<form
    method="post"
    class="pin-form"
>

<?php

if (!$customer_mode) {

?>

<input
    type="hidden"
    name="controller_id"
    value="<?php

    echo htmlspecialchars(
        $selected_controller,
        ENT_QUOTES,
        "UTF-8"
    );

    ?>"
>

<?php

}

?>

<input
    type="hidden"
    name="pin"
    value="<?php

    echo $pin;

    ?>"
>

<input
    type="hidden"
    name="value"
    value="1"
>

<button
    type="submit"
    name="set_pin"
    class="on-btn"
>
ON
</button>

</form>


<!-- OFF -->

<form
    method="post"
    class="pin-form"
>

<?php

if (!$customer_mode) {

?>

<input
    type="hidden"
    name="controller_id"
    value="<?php

    echo htmlspecialchars(
        $selected_controller,
        ENT_QUOTES,
        "UTF-8"
    );

    ?>"
>

<?php

}

?>

<input
    type="hidden"
    name="pin"
    value="<?php

    echo $pin;

    ?>"
>

<input
    type="hidden"
    name="value"
    value="0"
>

<button
    type="submit"
    name="set_pin"
    class="off-btn"
>
OFF
</button>

</form>

</div>

<?php

}

?>

</div>


<?php

} else {

?>

<div class="message error">

Please select a controller.

</div>

<?php

}

?>

</div>


<script>

/* =========================================================
   ADMIN CONTROLLER SELECTION
========================================================= */

function selectController(id)
{

    if (id === "")
    {

        window.location.href =
            "index.php";

        return;
    }

    window.location.href =
        "index.php?controller_id=" +
        encodeURIComponent(id);
}


/* =========================================================
   CURRENT TIME
========================================================= */

function updateCurrentTime()
{

    const now =
        new Date();

    const options = {

        timeZone:
            "Asia/Kolkata",

        year:
            "numeric",

        month:
            "2-digit",

        day:
            "2-digit",

        hour:
            "2-digit",

        minute:
            "2-digit",

        second:
            "2-digit",

        hour12:
            false
    };


    const parts =
        new Intl.DateTimeFormat(
            "en-GB",
            options
        ).formatToParts(now);


    let data = {};


    parts.forEach(
        function(part)
        {

            if (
                part.type !==
                "literal"
            )
            {

                data[part.type] =
                    part.value;
            }

        }
    );


    const formatted =
        data.year +
        "-" +
        data.month +
        "-" +
        data.day +
        " " +
        data.hour +
        ":" +
        data.minute +
        ":" +
        data.second;


    const currentTime =
        document.getElementById(
            "currentTime"
        );


    if (currentTime)
    {

        currentTime.textContent =
            formatted +
            " IST";
    }
}


updateCurrentTime();


setInterval(
    updateCurrentTime,
    1000
);


/* =========================================================
   ONLINE / OFFLINE
========================================================= */

function updateOnlineStatus()
{

    const lastSeenElement =
        document.getElementById(
            "lastSeen"
        );

    const statusElement =
        document.getElementById(
            "onlineStatus"
        );


    if (
        !lastSeenElement ||
        !statusElement
    )
    {
        return;
    }


    const lastSeenText =
        lastSeenElement
        .textContent
        .trim();


    if (
        lastSeenText === "" ||
        lastSeenText ===
            "Not yet seen"
    )
    {

        statusElement.innerHTML =
            '<span class="status-dot status-offline"></span>OFFLINE';

        statusElement.className =
            "info-value offline";

        return;
    }


    const lastSeen =
        new Date(
            lastSeenText
            .replace(
                " ",
                "T"
            )
        );


    if (
        isNaN(
            lastSeen.getTime()
        )
    )
    {

        statusElement.innerHTML =
            '<span class="status-dot status-offline"></span>OFFLINE';

        statusElement.className =
            "info-value offline";

        return;
    }


    const now =
        new Date();


    const difference =
        (
            now.getTime() -
            lastSeen.getTime()
        ) / 1000;


    if (difference <= 10)
    {

        statusElement.innerHTML =
            '<span class="status-dot status-online"></span>ONLINE';

        statusElement.className =
            "info-value online";

    }
    else
    {

        statusElement.innerHTML =
            '<span class="status-dot status-offline"></span>OFFLINE';

        statusElement.className =
            "info-value offline";
    }
}


updateOnlineStatus();


/* =========================================================
   TIME EDITING PROTECTION
========================================================= */

let timeEditing = false;


const startTime =
    document.getElementById(
        "start_time"
    );


if (startTime)
{

    startTime.addEventListener(
        "focus",
        function()
        {
            timeEditing = true;
        }
    );

    startTime.addEventListener(
        "click",
        function()
        {
            timeEditing = true;
        }
    );

    startTime.addEventListener(
        "change",
        function()
        {
            timeEditing = true;
        }
    );
}


const endTime =
    document.getElementById(
        "end_time"
    );


if (endTime)
{

    endTime.addEventListener(
        "focus",
        function()
        {
            timeEditing = true;
        }
    );

    endTime.addEventListener(
        "click",
        function()
        {
            timeEditing = true;
        }
    );

    endTime.addEventListener(
        "change",
        function()
        {
            timeEditing = true;
        }
    );
}


document.addEventListener(
    "click",
    function(event)
    {

        const target =
            event.target;


        if (
            target !== startTime &&
            target !== endTime
        )
        {

            if (
                !target.closest(
                    ".time-box"
                )
            )
            {

                timeEditing =
                    false;
            }
        }
    }
);


/* =========================================================
   AUTO REFRESH
========================================================= */

setInterval(
    function()
    {

        if (timeEditing)
        {
            return;
        }


        <?php

        if (
            $selected_controller !== ""
        ) {

        ?>

        window.location.reload();

        <?php

        }

        ?>

    },
    3000
);

</script>

</body>

</html>
