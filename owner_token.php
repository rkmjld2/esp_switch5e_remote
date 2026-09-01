
<?php

/*
============================================================
 ESP-SWITCH5 REMOTE
 OWNER-ONLY CONTROLLER MANAGEMENT
============================================================

Database:
    esp_switch5

Table:
    controllers

Functions:
    ADD
    EDIT
    DELETE
    ACTIVATE / DEACTIVATE

Fields:
    id
    controller_id
    customer_token
    device_token
    customer_name
    active
    last_seen
    start_time
    end_time

Authentication:
    TOKEN_PASSWORD from .env

Example:
    TOKEN_PASSWORD=EspSwitch5Owner@2026

Timezone:
    Asia/Kolkata

IMPORTANT:
    This page is OWNER ONLY.
============================================================
*/


/* =========================================================
   START SESSION FIRST
   IMPORTANT: NOTHING MUST BE OUTPUT BEFORE THIS
========================================================= */

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set("Asia/Kolkata");


/* =========================================================
   CONFIGURATION
========================================================= */

require_once __DIR__ . "/config.php";


/* =========================================================
   DATABASE
========================================================= */

require_once __DIR__ . "/db.php";


/* =========================================================
   OWNER PASSWORD
========================================================= */

$token_password = getenv("TOKEN_PASSWORD") ?: "";


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

$login_error = "";

if (isset($_POST["owner_login"])) {

    $password = $_POST["owner_password"] ?? "";

    if (
        $token_password !== "" &&
        hash_equals($token_password, $password)
    ) {

        $_SESSION["esp_owner"] = true;

        header("Location: owner_token.php");
        exit;

    } else {

        $login_error = "Invalid owner password.";
    }
}


/* =========================================================
   LOGIN PAGE
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
    font-family: Arial, Helvetica, sans-serif;
    background: #f2f2f2;
}

.login-box {
    max-width: 450px;
    margin: 80px auto;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.15);
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

<h1>ESP-SWITCH5</h1>

<h2>OWNER ACCESS</h2>

<div class="warning">

This page is for owner use only.<br>
Do not give this page or its password to customers.

</div>

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

exit;

}


/* =========================================================
   VARIABLES
========================================================= */

$message = "";
$message_type = "";

$edit_controller = null;


/* =========================================================
   DATETIME HELPER
========================================================= */

function datetime_for_input($value)
{
    if (
        $value === null ||
        $value === "" ||
        $value === "0000-00-00 00:00:00"
    ) {
        return "";
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return "";
    }

    return date("Y-m-d\TH:i", $timestamp);
}


/* =========================================================
   ADD NEW CONTROLLER
========================================================= */

if (isset($_POST["add_controller"])) {

    $controller_id =
        trim($_POST["controller_id"] ?? "");

    $customer_token =
        trim($_POST["customer_token"] ?? "");

    $device_token =
        trim($_POST["device_token"] ?? "");

    $customer_name =
        trim($_POST["customer_name"] ?? "");

    $active =
        isset($_POST["active"]) ? 1 : 0;

    $start_time =
        trim($_POST["start_time"] ?? "");

    $end_time =
        trim($_POST["end_time"] ?? "");


    if ($controller_id === "") {

        $message = "Controller ID is required.";
        $message_type = "error";

    } elseif (strlen($controller_id) > 50) {

        $message = "Controller ID cannot exceed 50 characters.";
        $message_type = "error";

    } elseif ($device_token === "") {

        $message = "Device Token is required.";
        $message_type = "error";

    } elseif (strlen($device_token) > 100) {

        $message = "Device Token cannot exceed 100 characters.";
        $message_type = "error";

    } else {

        /* -------------------------------------------------
           CHECK DUPLICATE CONTROLLER ID / DEVICE TOKEN
        ------------------------------------------------- */

        $check = $conn->prepare("
            SELECT controller_id, device_token
            FROM controllers
            WHERE controller_id = ?
               OR device_token = ?
            LIMIT 1
        ");

        if (!$check) {

            $message =
                "Database query preparation failed.";

            $message_type = "error";

        } else {

            $check->bind_param(
                "ss",
                $controller_id,
                $device_token
            );

            $check->execute();

            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $existing =
                    $result->fetch_assoc();

                if (
                    $existing["controller_id"] ===
                    $controller_id
                ) {

                    $message =
                        "Controller ID already exists.";

                } else {

                    $message =
                        "Device Token already exists.";
                }

                $message_type = "error";

                $check->close();

            } else {

                $check->close();


                /* -----------------------------------------
                   DATETIME CONVERSION
                ----------------------------------------- */

                $start_db =
                    $start_time !== ""
                    ? date(
                        "Y-m-d H:i:s",
                        strtotime($start_time)
                    )
                    : null;

                $end_db =
                    $end_time !== ""
                    ? date(
                        "Y-m-d H:i:s",
                        strtotime($end_time)
                    )
                    : null;


                /* -----------------------------------------
                   INSERT NEW CONTROLLER
                ----------------------------------------- */

                $stmt = $conn->prepare("
                    INSERT INTO controllers
                    (
                        controller_id,
                        customer_token,
                        device_token,
                        customer_name,
                        active,
                        start_time,
                        end_time
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?)
                ");

                if (!$stmt) {

                    $message =
                        "Insert preparation failed.";

                    $message_type = "error";

                } else {

                    $stmt->bind_param(
                        "ssssiss",
                        $controller_id,
                        $customer_token,
                        $device_token,
                        $customer_name,
                        $active,
                        $start_db,
                        $end_db
                    );

                    if ($stmt->execute()) {

                        $message =
                            "Controller " .
                            $controller_id .
                            " added successfully.";

                        $message_type = "success";

                    } else {

                        $message =
                            "Controller could not be added. " .
                            $stmt->error;

                        $message_type = "error";
                    }

                    $stmt->close();
                }
            }
        }
    }
}


/* =========================================================
   DELETE CONTROLLER
========================================================= */

if (isset($_POST["delete_controller"])) {

    $controller_id =
        trim(
            $_POST["delete_controller_id"] ?? ""
        );

    if ($controller_id === "") {

        $message =
            "Invalid Controller ID.";

        $message_type = "error";

    } else {

        $stmt = $conn->prepare("
            DELETE FROM controllers
            WHERE controller_id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $message =
                "Delete preparation failed.";

            $message_type = "error";

        } else {

            $stmt->bind_param(
                "s",
                $controller_id
            );

            if ($stmt->execute()) {

                if ($stmt->affected_rows > 0) {

                    $message =
                        "Controller " .
                        $controller_id .
                        " deleted successfully.";

                    $message_type = "success";

                } else {

                    $message =
                        "Controller not found.";

                    $message_type = "error";
                }

            } else {

                $message =
                    "Controller deletion failed.";

                $message_type = "error";
            }

            $stmt->close();
        }
    }
}


/* =========================================================
   ACTIVATE / DEACTIVATE
========================================================= */

if (isset($_POST["toggle_active"])) {

    $controller_id =
        trim(
            $_POST["toggle_controller_id"] ?? ""
        );

    $new_active =
        isset($_POST["new_active"])
        ? (int)$_POST["new_active"]
        : 0;

    if ($controller_id === "") {

        $message =
            "Invalid Controller ID.";

        $message_type = "error";

    } else {

        $stmt = $conn->prepare("
            UPDATE controllers
            SET active = ?
            WHERE controller_id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $message =
                "Active status update preparation failed.";

            $message_type = "error";

        } else {

            $stmt->bind_param(
                "is",
                $new_active,
                $controller_id
            );

            if ($stmt->execute()) {

                $message =
                    $new_active
                    ? "Controller activated."
                    : "Controller deactivated.";

                $message_type = "success";

            } else {

                $message =
                    "Active status update failed.";

                $message_type = "error";
            }

            $stmt->close();
        }
    }
}


/* =========================================================
   SAVE EDITED CONTROLLER
========================================================= */

if (isset($_POST["save_edit"])) {

    $original_controller_id =
        trim(
            $_POST["original_controller_id"] ?? ""
        );

    $controller_id =
        trim(
            $_POST["controller_id"] ?? ""
        );

    $customer_token =
        trim(
            $_POST["customer_token"] ?? ""
        );

    $device_token =
        trim(
            $_POST["device_token"] ?? ""
        );

    $customer_name =
        trim(
            $_POST["customer_name"] ?? ""
        );

    $active =
        isset($_POST["active"]) ? 1 : 0;

    $start_time =
        trim(
            $_POST["start_time"] ?? ""
        );

    $end_time =
        trim(
            $_POST["end_time"] ?? ""
        );


    if ($original_controller_id === "") {

        $message =
            "Original Controller ID is missing.";

        $message_type = "error";

    } elseif ($controller_id === "") {

        $message =
            "Controller ID is required.";

        $message_type = "error";

    } elseif (strlen($controller_id) > 50) {

        $message =
            "Controller ID cannot exceed 50 characters.";

        $message_type = "error";

    } elseif ($device_token === "") {

        $message =
            "Device Token is required.";

        $message_type = "error";

    } elseif (strlen($device_token) > 100) {

        $message =
            "Device Token cannot exceed 100 characters.";

        $message_type = "error";

    } else {

        /* -------------------------------------------------
           CHECK DUPLICATES
        ------------------------------------------------- */

        $check = $conn->prepare("
            SELECT controller_id, device_token
            FROM controllers
            WHERE
                (controller_id = ? OR device_token = ?)
                AND controller_id <> ?
            LIMIT 1
        ");

        if (!$check) {

            $message =
                "Duplicate check preparation failed.";

            $message_type = "error";

        } else {

            $check->bind_param(
                "sss",
                $controller_id,
                $device_token,
                $original_controller_id
            );

            $check->execute();

            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $existing =
                    $result->fetch_assoc();

                if (
                    $existing["controller_id"] ===
                    $controller_id
                ) {

                    $message =
                        "Controller ID already exists.";

                } else {

                    $message =
                        "Device Token already exists.";
                }

                $message_type = "error";

                $check->close();

            } else {

                $check->close();


                $start_db =
                    $start_time !== ""
                    ? date(
                        "Y-m-d H:i:s",
                        strtotime($start_time)
                    )
                    : null;

                $end_db =
                    $end_time !== ""
                    ? date(
                        "Y-m-d H:i:s",
                        strtotime($end_time)
                    )
                    : null;


                /* -----------------------------------------
                   UPDATE CONTROLLER
                ----------------------------------------- */

                $stmt = $conn->prepare("
                    UPDATE controllers
                    SET
                        controller_id = ?,
                        customer_token = ?,
                        device_token = ?,
                        customer_name = ?,
                        active = ?,
                        start_time = ?,
                        end_time = ?
                    WHERE controller_id = ?
                    LIMIT 1
                ");

                if (!$stmt) {

                    $message =
                        "Edit preparation failed.";

                    $message_type = "error";

                } else {

                    $stmt->bind_param(
                        "ssssisss",
                        $controller_id,
                        $customer_token,
                        $device_token,
                        $customer_name,
                        $active,
                        $start_db,
                        $end_db,
                        $original_controller_id
                    );

                    if ($stmt->execute()) {

                        $message =
                            "Controller " .
                            $controller_id .
                            " updated successfully.";

                        $message_type = "success";

                    } else {

                        $message =
                            "Controller update failed. " .
                            $stmt->error;

                        $message_type = "error";
                    }

                    $stmt->close();
                }
            }
        }
    }
}


/* =========================================================
   LOAD CONTROLLER FOR EDIT
========================================================= */

if (isset($_GET["edit"])) {

    $edit_id =
        trim($_GET["edit"]);

    if ($edit_id !== "") {

        $stmt = $conn->prepare("
            SELECT
                id,
                controller_id,
                customer_token,
                device_token,
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
                $edit_id
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            if ($result->num_rows > 0) {

                $edit_controller =
                    $result->fetch_assoc();
            }

            $stmt->close();
        }
    }
}


/* =========================================================
   READ ALL CONTROLLERS
========================================================= */

$controllers = [];

$result = $conn->query("
    SELECT
        id,
        controller_id,
        customer_token,
        device_token,
        customer_name,
        active,
        last_seen,
        start_time,
        end_time
    FROM controllers
    ORDER BY controller_id
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $controllers[] = $row;
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
    font-family: Arial, Helvetica, sans-serif;
    background: #f2f2f2;
    color: #222;
}

.container {
    max-width: 1250px;
    margin: 30px auto;
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.15);
}

.header {
    text-align: center;
    margin-bottom: 20px;
}

.header h1 {
    margin: 0;
    color: #333;
}

.subtitle {
    color: #666;
    margin-top: 6px;
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

.section {
    margin-top: 25px;
    margin-bottom: 25px;
}

.section-title {
    background: #343a40;
    color: white;
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 15px;
    font-size: 18px;
    font-weight: bold;
}

.form-box {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 20px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.field {
    width: 100%;
}

.field-full {
    grid-column: 1 / -1;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

input[type="text"],
input[type="datetime-local"] {
    width: 100%;
    padding: 11px;
    font-size: 15px;
    border: 1px solid #aaa;
    border-radius: 6px;
}

.checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
}

.checkbox input {
    width: 20px;
    height: 20px;
}

.button-row {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
}

button,
.button-link {
    padding: 11px 18px;
    border: none;
    border-radius: 6px;
    color: white;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.add-button {
    background: #198754;
}

.update-button {
    background: #0d6efd;
}

.cancel-button {
    background: #6c757d;
}

.delete-button {
    background: #dc3545;
}

.activate-button {
    background: #198754;
}

.deactivate-button {
    background: #fd7e14;
}

.logout {
    background: #343a40;
    margin-top: 20px;
}

button:hover,
.button-link:hover {
    opacity: 0.85;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1150px;
}

th,
td {
    border: 1px solid #ddd;
    padding: 9px;
    text-align: left;
    vertical-align: middle;
}

th {
    background: #343a40;
    color: white;
    white-space: nowrap;
}

tr:nth-child(even) {
    background: #f8f9fa;
}

.status-active {
    color: #198754;
    font-weight: bold;
}

.status-inactive {
    color: #dc3545;
    font-weight: bold;
}

.actions {
    white-space: nowrap;
}

.actions form {
    display: inline;
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

@media (max-width: 700px) {

    .grid {
        grid-template-columns: 1fr;
    }

    .field-full {
        grid-column: auto;
    }

    .container {
        padding: 15px;
    }
}

</style>

</head>

<body>

<div class="container">

<div class="header">

<h1>ESP-SWITCH5</h1>

<div class="subtitle">
OWNER CONTROLLER MANAGEMENT
</div>

</div>


<div class="owner-warning">

OWNER ONLY — DO NOT GIVE THIS PAGE OR PASSWORD TO CUSTOMERS

</div>


<?php

if ($message !== "") {

?>

<div class="message
<?php
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


<?php

$is_edit =
    ($edit_controller !== null);

?>


<div class="section">

<div class="section-title">

<?php

echo $is_edit
    ? "EDIT CONTROLLER"
    : "ADD NEW CONTROLLER";

?>

</div>


<div class="form-box">

<form method="post">

<?php

if ($is_edit) {

?>

<input
    type="hidden"
    name="original_controller_id"
    value="<?php
        echo htmlspecialchars(
            $edit_controller["controller_id"],
            ENT_QUOTES,
            "UTF-8"
        );
    ?>"
>

<?php

}

?>


<div class="grid">


<div class="field">

<label>
Controller ID
</label>

<input
    type="text"
    name="controller_id"
    maxlength="50"
    required
    value="<?php
        echo $is_edit
            ? htmlspecialchars(
                $edit_controller["controller_id"],
                ENT_QUOTES,
                "UTF-8"
            )
            : "";
    ?>"
    placeholder="Example: ESP0001"
>

</div>


<div class="field">

<label>
Customer Name
</label>

<input
    type="text"
    name="customer_name"
    maxlength="100"
    value="<?php
        echo $is_edit
            ? htmlspecialchars(
                $edit_controller["customer_name"] ?? "",
                ENT_QUOTES,
                "UTF-8"
            )
            : "";
    ?>"
    placeholder="Example: Ravi"
>

</div>


<div class="field">

<label>
Customer Token
</label>

<input
    type="text"
    name="customer_token"
    maxlength="100"
    value="<?php
        echo $is_edit
            ? htmlspecialchars(
                $edit_controller["customer_token"] ?? "",
                ENT_QUOTES,
                "UTF-8"
            )
            : "";
    ?>"
    placeholder="Customer token"
>

</div>


<div class="field">

<label>
Device Token
</label>

<input
    type="text"
    name="device_token"
    maxlength="100"
    required
    value="<?php
        echo $is_edit
            ? htmlspecialchars(
                $edit_controller["device_token"],
                ENT_QUOTES,
                "UTF-8"
            )
            : "";
    ?>"
    placeholder="Example: ESP0001-TOKEN-2026"
>

</div>


<div class="field">

<label>
Start Time
</label>

<input
    type="datetime-local"
    name="start_time"
    value="<?php
        echo $is_edit
            ? datetime_for_input(
                $edit_controller["start_time"]
            )
            : "";
    ?>"
>

</div>


<div class="field">

<label>
End Time
</label>

<input
    type="datetime-local"
    name="end_time"
    value="<?php
        echo $is_edit
            ? datetime_for_input(
                $edit_controller["end_time"]
            )
            : "";
    ?>"
>

</div>


<div class="field-full">

<label class="checkbox">

<input
    type="checkbox"
    name="active"
    value="1"

<?php

if (
    !$is_edit ||
    (int)$edit_controller["active"] === 1
) {

    echo " checked";
}

?>

>

Active Controller

</label>

</div>


</div>


<div class="button-row">

<?php

if ($is_edit) {

?>

<button
    type="submit"
    name="save_edit"
    class="update-button"
    onclick="
        return confirm(
            'Save changes to this controller?'
        );
    "
>
SAVE CHANGES
</button>

<a
    href="owner_token.php"
    class="button-link cancel-button"
>
CANCEL EDIT
</a>

<?php

} else {

?>

<button
    type="submit"
    name="add_controller"
    class="add-button"
    onclick="
        return confirm(
            'Add this new controller?'
        );
    "
>
ADD CONTROLLER
</button>

<?php

}

?>

</div>

</form>

</div>

</div>


<div class="section">

<div class="section-title">
ALL CONTROLLERS
</div>


<div class="table-wrapper">

<table>

<thead>

<tr>

<th>ID</th>
<th>Controller ID</th>
<th>Customer Name</th>
<th>Customer Token</th>
<th>Device Token</th>
<th>Active</th>
<th>Last Seen</th>
<th>Start Time</th>
<th>End Time</th>
<th>Actions</th>

</tr>

</thead>


<tbody>

<?php

if (count($controllers) === 0) {

?>

<tr>

<td colspan="10"
    style="text-align:center;">

No controllers found.

</td>

</tr>

<?php

} else {

foreach ($controllers as $controller) {

?>

<tr>


<td>

<?php

echo (int)$controller["id"];

?>

</td>


<td>

<strong>

<?php

echo htmlspecialchars(
    $controller["controller_id"],
    ENT_QUOTES,
    "UTF-8"
);

?>

</strong>

</td>


<td>

<?php

echo htmlspecialchars(
    $controller["customer_name"] ?? "",
    ENT_QUOTES,
    "UTF-8"
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $controller["customer_token"] ?? "",
    ENT_QUOTES,
    "UTF-8"
);

?>

</td>


<td>

<?php

echo htmlspecialchars(
    $controller["device_token"],
    ENT_QUOTES,
    "UTF-8"
);

?>

</td>


<td>

<?php

if ((int)$controller["active"] === 1) {

?>

<span class="status-active">
ACTIVE
</span>

<?php

} else {

?>

<span class="status-inactive">
INACTIVE
</span>

<?php

}

?>

</td>


<td>

<?php

echo !empty($controller["last_seen"])
    ? htmlspecialchars(
        $controller["last_seen"],
        ENT_QUOTES,
        "UTF-8"
    )
    : "-";

?>

</td>


<td>

<?php

echo !empty($controller["start_time"])
    ? htmlspecialchars(
        $controller["start_time"],
        ENT_QUOTES,
        "UTF-8"
    )
    : "-";

?>

</td>


<td>

<?php

echo !empty($controller["end_time"])
    ? htmlspecialchars(
        $controller["end_time"],
        ENT_QUOTES,
        "UTF-8"
    )
    : "-";

?>

</td>


<td class="actions">


<a
    href="owner_token.php?edit=<?php
        echo urlencode(
            $controller["controller_id"]
        );
    ?>"
    class="button-link update-button"
>
EDIT
</a>


<form
    method="post"
    onsubmit="
        return confirm(
            'WARNING: Delete this controller permanently?'
        );
    "
>

<input
    type="hidden"
    name="delete_controller_id"
    value="<?php
        echo htmlspecialchars(
            $controller["controller_id"],
            ENT_QUOTES,
            "UTF-8"
        );
    ?>"
>

<button
    type="submit"
    name="delete_controller"
    class="delete-button"
>
DELETE
</button>

</form>


<form method="post">

<input
    type="hidden"
    name="toggle_controller_id"
    value="<?php
        echo htmlspecialchars(
            $controller["controller_id"],
            ENT_QUOTES,
            "UTF-8"
        );
    ?>"
>

<input
    type="hidden"
    name="new_active"
    value="<?php
        echo (int)$controller["active"] === 1
            ? "0"
            : "1";
    ?>"
>

<button
    type="submit"
    name="toggle_active"
    class="<?php
        echo (int)$controller["active"] === 1
            ? "deactivate-button"
            : "activate-button";
    ?>"
>

<?php

echo (int)$controller["active"] === 1
    ? "DEACTIVATE"
    : "ACTIVATE";

?>

</button>

</form>


</td>

</tr>

<?php

}

}

?>

</tbody>

</table>

</div>

</div>


<div class="note">

<strong>Important:</strong><br><br>

• Controller ID must be unique.<br>

• Device Token must be unique.<br>

• last_seen is displayed but is not manually changed.<br>

• last_seen should continue to be updated by the ESP/API.<br>

• Active controls whether the controller is enabled or disabled.<br>

• Changing Device Token requires the ESP firmware to use the same token.<br>

• DELETE permanently removes the controller row from the database.

</div>


<div style="text-align:center;">

<a
    href="owner_token.php?logout=1"
    class="button-link logout"
>
OWNER LOGOUT
</a>

</div>


</div>

</body>

</html>

<?php

ob_end_flush();

?>

