<?php
/*
============================================================
 ESP-SWITCH5 REMOTE
 OWNER-ONLY CONTROLLER MANAGEMENT
============================================================

File:
    owner_token.php

Purpose:
    Owner can ADD a new controller to the controllers table
    and EDIT an existing controller.

Database:
    esp_switch5

Table:
    controllers

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
    TOKEN_PASSWORD from config.php

IMPORTANT:
    This page is OWNER ONLY.

    Customers must NOT receive:
        owner_token.php
        TOKEN_PASSWORD

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
   OWNER AUTHENTICATION
=========================================================

   We deliberately DO NOT use PHP sessions here.

   This avoids:
       session_start()
       header errors
       session output problems

   Authentication is stored in a secure HTTP cookie.
========================================================= */

$owner_cookie_name = "esp_switch5_owner";


/* =========================================================
   LOGOUT
========================================================= */

if (isset($_GET["logout"])) {

    setcookie(
        $owner_cookie_name,
        "",
        [
            "expires"  => time() - 3600,
            "path"     => "/",
            "secure"   => (!empty($_SERVER["HTTPS"]) &&
                           $_SERVER["HTTPS"] !== "off"),
            "httponly" => true,
            "samesite" => "Lax"
        ]
    );

    header("Location: owner_token.php");
    exit;
}


/* =========================================================
   CHECK OWNER COOKIE
========================================================= */

$owner_logged_in = false;

if (
    isset($_COOKIE[$owner_cookie_name]) &&
    $_COOKIE[$owner_cookie_name] === "1"
) {

    $owner_logged_in = true;
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

        setcookie(
            $owner_cookie_name,
            "1",
            [
                "expires"  => time() + (8 * 60 * 60),
                "path"     => "/",
                "secure"   => (!empty($_SERVER["HTTPS"]) &&
                               $_SERVER["HTTPS"] !== "off"),
                "httponly" => true,
                "samesite" => "Lax"
            ]
        );

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

if (!$owner_logged_in) {

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
   ADD NEW CONTROLLER
========================================================= */

if (isset($_POST["add_controller"])) {

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
        isset($_POST["active"])
            ? 1
            : 0;


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
            "Invalid Device Token. Use 8-100 characters containing letters, numbers, hyphen or underscore.";

        $message_type =
            "error";
    }


    /* -----------------------------------------------------
       INSERT
    ----------------------------------------------------- */

    else {

        $stmt =
            $conn->prepare("
                INSERT INTO controllers
                (
                    controller_id,
                    customer_token,
                    device_token,
                    customer_name,
                    active
                )
                VALUES
                (?, ?, ?, ?, ?)
            ");


        if (!$stmt) {

            $message =
                "Controller insert preparation failed.";

            $message_type =
                "error";

        } else {

            $stmt->bind_param(
                "ssssi",
                $controller_id,
                $customer_token,
                $device_token,
                $customer_name,
                $active
            );


            try {

                if ($stmt->execute()) {

                    $message =
                        "Controller " .
                        $controller_id .
                        " added successfully.";

                    $message_type =
                        "success";
                }

            }
            catch (mysqli_sql_exception $e) {

                if (
                    $e->getCode() == 1062
                ) {

                    $message =
                        "Controller ID or Device Token already exists.";

                } else {

                    $message =
                        "Could not add controller.";
                }

                $message_type =
                    "error";
            }

            $stmt->close();
        }
    }
}


/* =========================================================
   SELECT CONTROLLER FOR EDIT
========================================================= */

if (isset($_GET["edit"])) {

    $controller_id =
        trim(
            $_GET["edit"] ?? ""
        );

    if ($controller_id !== "") {

        $stmt =
            $conn->prepare("
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
                $controller_id
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            if (
                $result->num_rows > 0
            ) {

                $edit_controller =
                    $result->fetch_assoc();
            }

            $stmt->close();
        }
    }
}


/* =========================================================
   UPDATE EXISTING CONTROLLER
========================================================= */

if (isset($_POST["update_controller"])) {

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
        isset($_POST["active"])
            ? 1
            : 0;


    /* -----------------------------------------------------
       VALIDATION
    ----------------------------------------------------- */

    if ($original_controller_id === "") {

        $message =
            "Original Controller ID is missing.";

        $message_type =
            "error";
    }

    elseif ($controller_id === "") {

        $message =
            "Controller ID is required.";

        $message_type =
            "error";
    }

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
            "Invalid Device Token. Use 8-100 characters containing letters, numbers, hyphen or underscore.";

        $message_type =
            "error";
    }

    else {
$stmt =
    $conn->prepare("
        UPDATE controllers
        SET
            controller_id = ?,
            customer_token = ?,
            device_token = ?,
            customer_name = ?,
            active = ?
        WHERE controller_id = ?
    ");

if (!$stmt) {

    $message =
        "Controller update preparation failed.";

    $message_type =
        "error";

} else {

    $stmt->bind_param(
        "ssssis",
        $controller_id,
        $customer_token,
        $device_token,
        $customer_name,
        $active,
        $original_controller_id
    );

    try {

        if ($stmt->execute()) {

            $message =
                "Controller " .
                $controller_id .
                " updated successfully.";

            $message_type =
                "success";

            $edit_controller = null;

        }

    }
    catch (mysqli_sql_exception $e) {

        if (
            $e->getCode() == 1062
        ) {

            $message =
                "Controller ID or Device Token already exists.";

        } else {

            $message =
                "Could not update controller.";
        }

        $message_type =
            "error";
    }

    $stmt->close();
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

    max-width: 950px;

    margin: 30px auto;

    background: white;

    padding: 25px;

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

.warning {

    background: #fff3cd;

    border: 1px solid #ffeeba;

    color: #856404;

    padding: 12px;

    border-radius: 7px;

    text-align: center;

    font-weight: bold;

    margin-bottom: 20px;
}

.message {

    padding: 12px;

    border-radius: 7px;

    text-align: center;

    font-weight: bold;

    margin-bottom: 20px;
}

.success {

    background: #d4edda;

    color: #155724;
}

.error {

    background: #f8d7da;

    color: #721c24;
}

.form-box {

    background: #f8f9fa;

    border: 1px solid #ddd;

    border-radius: 10px;

    padding: 20px;

    margin-bottom: 25px;
}

.form-box h2 {

    margin-top: 0;

    color: #333;
}

label {

    display: block;

    font-weight: bold;

    margin-bottom: 7px;
}

input[type="text"] {

    width: 100%;

    padding: 12px;

    font-size: 16px;

    border: 1px solid #aaa;

    border-radius: 6px;

    margin-bottom: 15px;
}

.checkbox-row {

    margin: 10px 0 18px 0;

}

.checkbox-row input {

    transform: scale(1.2);

    margin-right: 8px;
}

.button {

    display: inline-block;

    padding: 11px 18px;

    border: none;

    border-radius: 6px;

    color: white;

    font-size: 15px;

    cursor: pointer;

    text-decoration: none;
}

.add-button {

    background: #28a745;
}

.update-button {

    background: #007bff;
}

.edit-button {

    background: #ffc107;

    color: #222;
}

.logout-button {

    background: #6c757d;
}

.button:hover {

    opacity: 0.85;
}

.cancel-button {

    margin-left: 8px;

    background: #6c757d;
}

.table-box {

    overflow-x: auto;

    margin-top: 20px;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 800px;
}

th,
td {

    border: 1px solid #ddd;

    padding: 10px;

    text-align: left;

    vertical-align: middle;
}

th {

    background: #343a40;

    color: white;
}

.active {

    color: #198754;

    font-weight: bold;
}

.inactive {

    color: #dc3545;

    font-weight: bold;
}

.note {

    margin-top: 25px;

    padding: 15px;

    background: #eef6ff;

    border: 1px solid #b8d8f5;

    border-radius: 8px;

    font-size: 14px;

    line-height: 1.5;
}

.top-buttons {

    text-align: right;

    margin-bottom: 15px;
}

@media (max-width: 600px) {

    body {

        padding: 10px;
    }

    .container {

        padding: 15px;
    }

    table {

        font-size: 13px;
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
OWNER CONTROLLER MANAGEMENT
</div>

</div>


<div class="warning">

OWNER ONLY — DO NOT GIVE THIS PAGE OR TOKEN PASSWORD TO CUSTOMERS

</div>


<div class="top-buttons">

<a
    href="owner_token.php?logout=1"
    class="button logout-button"
>
OWNER LOGOUT
</a>

</div>


<?php

if ($message !== "") {

?>

<div class="message
<?php

echo
    $message_type === "success"
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


/* =========================================================
   EDIT FORM
========================================================= */

if ($edit_controller !== null) {

?>

<div class="form-box">

<h2>
EDIT CONTROLLER
</h2>

<form method="post">

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


<label>
Controller ID
</label>

<input
    type="text"
    name="controller_id"
    maxlength="50"
    required
    value="<?php

echo htmlspecialchars(
    $edit_controller["controller_id"],
    ENT_QUOTES,
    "UTF-8"
);

?>"
>


<label>
Customer Token
</label>

<input
    type="text"
    name="customer_token"
    maxlength="100"
    value="<?php

echo htmlspecialchars(
    $edit_controller["customer_token"] ?? "",
    ENT_QUOTES,
    "UTF-8"
);

?>"
>


<label>
Device Token
</label>

<input
    type="text"
    name="device_token"
    maxlength="100"
    required
    value="<?php

echo htmlspecialchars(
    $edit_controller["device_token"],
    ENT_QUOTES,
    "UTF-8"
);

?>"
>


<label>
Customer Name
</label>

<input
    type="text"
    name="customer_name"
    maxlength="100"
    value="<?php

echo htmlspecialchars(
    $edit_controller["customer_name"] ?? "",
    ENT_QUOTES,
    "UTF-8"
);

?>"
>


<div class="checkbox-row">

<label>

<input
    type="checkbox"
    name="active"
    value="1"

<?php

if (
    (int)$edit_controller["active"] === 1
) {

    echo " checked";
}

?>

>

Controller Active

</label>

</div>


<button
    type="submit"
    name="update_controller"
    class="button update-button"
    onclick="
        return confirm(
            'Are you sure you want to update this controller?'
        );
    "
>
UPDATE CONTROLLER
</button>


<a
    href="owner_token.php"
    class="button cancel-button"
>
CANCEL
</a>

</form>

</div>

<?php

}


/* =========================================================
   ADD FORM
========================================================= */

if ($edit_controller === null) {

?>

<div class="form-box">

<h2>
ADD NEW CONTROLLER
</h2>

<form method="post">


<label>
Controller ID
</label>

<input
    type="text"
    name="controller_id"
    maxlength="50"
    placeholder="Example: ESP0001"
    required
>


<label>
Customer Token
</label>

<input
    type="text"
    name="customer_token"
    maxlength="100"
    placeholder="Customer access token"
>


<label>
Device Token
</label>

<input
    type="text"
    name="device_token"
    maxlength="100"
    placeholder="Example: ESP0001-TOKEN-2026-RAVI1"
    required
>


<label>
Customer Name
</label>

<input
    type="text"
    name="customer_name"
    maxlength="100"
    placeholder="Customer name"
>


<div class="checkbox-row">

<label>

<input
    type="checkbox"
    name="active"
    value="1"
    checked
>

Controller Active

</label>

</div>


<button
    type="submit"
    name="add_controller"
    class="button add-button"
    onclick="
        return confirm(
            'Add this controller to the database?'
        );
    "
>
ADD CONTROLLER
</button>

</form>

</div>

<?php

}


/* =========================================================
   CONTROLLER LIST
========================================================= */

?>

<div class="form-box">

<h2>
CURRENT CONTROLLERS
</h2>

<div class="table-box">

<table>

<thead>

<tr>

<th>
ID
</th>

<th>
Controller ID
</th>

<th>
Customer Name
</th>

<th>
Customer Token
</th>

<th>
Device Token
</th>

<th>
Status
</th>

<th>
Action
</th>

</tr>

</thead>

<tbody>

<?php

if (empty($controllers)) {

?>

<tr>

<td
    colspan="7"
    style="text-align:center;"
>
No controllers found.
</td>

</tr>

<?php

} else {

    foreach (
        $controllers
        as $controller
    ) {

?>

<tr>

<td>

<?php

echo htmlspecialchars(
    $controller["id"],
    ENT_QUOTES,
    "UTF-8"
);

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

</td>


<td>

<a
    href="owner_token.php?edit=<?php

    echo rawurlencode(
        $controller["controller_id"]
    );

    ?>"
    class="button edit-button"
>
EDIT
</a>

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

This owner page changes only the
<strong>controllers</strong> table.

The following fields are managed here:

<br><br>

<strong>
controller_id<br>
customer_token<br>
device_token<br>
customer_name<br>
active
</strong>

<br><br>

The fields
<strong>last_seen</strong>,
<strong>start_time</strong> and
<strong>end_time</strong>
are not changed by this page.

<br><br>

The ESP8266 must contain the same
<strong>controller_id</strong> and
<strong>device_token</strong> that are stored in the database.

</div>


</div>

</body>

</html>
