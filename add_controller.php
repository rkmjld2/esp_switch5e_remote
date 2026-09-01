<?php

session_start();

/*
============================================================
 ESP-SWITCH5 REMOTE
 ADD NEW CONTROLLER
============================================================

Database:
    esp_switch5

Table:
    controllers

Existing files required:
    config.php
    db.php

============================================================
*/

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

date_default_timezone_set("Asia/Kolkata");

/* =========================================================
   ADMIN LOGIN CHECK
========================================================= */

if (
    !isset($_SESSION["esp_admin"]) ||
    $_SESSION["esp_admin"] !== true
) {
    header("Location: index.php");
    exit;
}


/* =========================================================
   VARIABLES
========================================================= */

$message = "";
$message_type = "";

$controller_id  = "";
$customer_token = "";
$device_token   = "";
$customer_name  = "";


/* =========================================================
   SAVE CONTROLLER
========================================================= */

if (isset($_POST["save_controller"])) {

    $controller_id =
        trim($_POST["controller_id"] ?? "");

    $customer_token =
        trim($_POST["customer_token"] ?? "");

    $device_token =
        trim($_POST["device_token"] ?? "");

    $customer_name =
        trim($_POST["customer_name"] ?? "");


    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($controller_id === "") {

        $message = "Controller ID is required.";
        $message_type = "error";

    }
    elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $controller_id)) {

        $message =
            "Invalid Controller ID. Use only letters, numbers, _ or -.";

        $message_type = "error";

    }
    elseif ($device_token === "") {

        $message = "Device token is required.";
        $message_type = "error";

    }
    else {

        /* =================================================
           CHECK DUPLICATE CONTROLLER ID
        ================================================= */

        $stmt = $conn->prepare("
            SELECT id
            FROM controllers
            WHERE controller_id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $message =
                "Database preparation failed.";

            $message_type = "error";

        }
        else {

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

                $message_type = "error";
            }

            $stmt->close();
        }


        /* =================================================
           CHECK DUPLICATE DEVICE TOKEN
        ================================================= */

        if ($message === "") {

            $stmt = $conn->prepare("
                SELECT id
                FROM controllers
                WHERE device_token = ?
                LIMIT 1
            ");

            if (!$stmt) {

                $message =
                    "Database preparation failed.";

                $message_type = "error";

            }
            else {

                $stmt->bind_param(
                    "s",
                    $device_token
                );

                $stmt->execute();

                $result =
                    $stmt->get_result();

                if ($result->num_rows > 0) {

                    $message =
                        "Device token already exists.";

                    $message_type = "error";
                }

                $stmt->close();
            }
        }


        /* =================================================
           INSERT NEW CONTROLLER
        ================================================= */

        if ($message === "") {

            $stmt = $conn->prepare("
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
                    "Insert preparation failed.";

                $message_type = "error";

            }
            else {

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

                    $message_type = "success";

                    /* Clear form after successful insertion */

                    $controller_id  = "";
                    $customer_token = "";
                    $device_token   = "";
                    $customer_name  = "";

                }
                else {

                    $message =
                        "Could not add controller.";

                    $message_type = "error";
                }

                $stmt->close();
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
   content="width=device-width, initial-scale=1.0">

<title>Add Controller - ESP-SWITCH5</title>

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

.container {

    max-width: 650px;

    margin: 40px auto;

    background: white;

    padding: 30px;

    border-radius: 12px;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,0.15);
}

h1 {

    text-align: center;

    margin-top: 0;

    color: #333;
}

.subtitle {

    text-align: center;

    color: #666;

    margin-bottom: 25px;
}

.form-group {

    margin-bottom: 18px;
}

label {

    display: block;

    font-weight: bold;

    margin-bottom: 7px;
}

input {

    width: 100%;

    padding: 12px;

    font-size: 16px;

    border:
        1px solid #aaa;

    border-radius: 6px;
}

input:focus {

    border-color: #007bff;

    outline: none;
}

button {

    width: 100%;

    padding: 13px;

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

.back {

    display: block;

    text-align: center;

    margin-top: 20px;

    text-decoration: none;

    color: #007bff;
}

.note {

    margin-top: 20px;

    padding: 12px;

    background: #f7f7f7;

    border: 1px solid #ddd;

    border-radius: 6px;

    font-size: 14px;

    color: #555;
}

</style>

</head>

<body>

<div class="container">

<h1>
Add New Controller
</h1>

<div class="subtitle">
ESP-SWITCH5 REMOTE
</div>

<?php

if ($message !== "") {

?>

<div class="message <?php
    echo $message_type === "success"
        ? "success"
        : "error";
?>">

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

<form method="post">

<div class="form-group">

<label for="controller_id">
Controller ID
</label>

<input
type="text"
id="controller_id"
name="controller_id"
value="<?php
     echo htmlspecialchars(
         $controller_id,
         ENT_QUOTES,
         "UTF-8"
     );
 ?>"
placeholder="Example: ESP0002"
required

>

</div>

<div class="form-group">

<label for="customer_token">
Customer Token
</label>

<input
type="text"
id="customer_token"
name="customer_token"
value="<?php
     echo htmlspecialchars(
         $customer_token,
         ENT_QUOTES,
         "UTF-8"
     );
 ?>"
placeholder="Optional"

>

</div>

<div class="form-group">

<label for="device_token">
Device Token
</label>

<input
type="text"
id="device_token"
name="device_token"
value="<?php
     echo htmlspecialchars(
         $device_token,
         ENT_QUOTES,
         "UTF-8"
     );
 ?>"
placeholder="Example: ESP0002-CUST-4N8W6Z2K9P5R7M1Q"
required

>

</div>

<div class="form-group">

<label for="customer_name">
Customer Name
</label>

<input
type="text"
id="customer_name"
name="customer_name"
value="<?php
     echo htmlspecialchars(
         $customer_name,
         ENT_QUOTES,
         "UTF-8"
     );
 ?>"
placeholder="Example: ABC Customer"

>

</div>

<button
type="submit"
name="save_controller"

>

ADD CONTROLLER </button>

</form>

<div class="note">

<strong>New controller defaults:</strong>

<br><br>

Active: <strong>YES</strong><br>

Last Seen: <strong>NULL</strong><br>

Start Time: <strong>NULL</strong><br>

End Time: <strong>NULL</strong>

</div>

<a
class="back"
href="index.php"

>

← Back to Control Panel </a>

</div>

</body>

</html>
