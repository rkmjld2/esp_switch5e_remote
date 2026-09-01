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

IMPORTANT:
    This file starts session before ANY output.
============================================================
*/

/* =========================================================
   START SESSION BEFORE ANY OUTPUT
========================================================= */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =========================================================
   REQUIRED FILES
========================================================= */

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

/* =========================================================
   TIMEZONE
========================================================= */

date_default_timezone_set("Asia/Kolkata");

/* =========================================================
   OWNER LOGIN CHECK
========================================================= */

if (
    !isset($_SESSION["owner_logged_in"]) ||
    $_SESSION["owner_logged_in"] !== true
) {
    header("Location: index.php");
    exit;
}

/* =========================================================
   DATABASE CHECK
========================================================= */

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

/* =========================================================
   HTML ESCAPE FUNCTION
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =========================================================
   MESSAGE VARIABLES
========================================================= */

$message = "";
$error = "";

/* =========================================================
   ADD CONTROLLER
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "add"
) {
    $controller_id =
        trim($_POST["controller_id"] ?? "");

    $customer_token =
        trim($_POST["customer_token"] ?? "");

    $device_token =
        trim($_POST["device_token"] ?? "");

    if ($controller_id === "") {

        $error = "Controller ID is required.";

    } elseif ($customer_token === "") {

        $error = "Customer Token is required.";

    } elseif ($device_token === "") {

        $error = "Device Token is required.";

    } else {

        /* Check duplicate Controller ID */

        $stmt = $conn->prepare(
            "SELECT id
             FROM controllers
             WHERE controller_id = ?
             LIMIT 1"
        );

        if (!$stmt) {

            $error =
                "Database error: " .
                $conn->error;

        } else {

            $stmt->bind_param(
                "s",
                $controller_id
            );

            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {

                $error =
                    "Controller ID already exists.";

                $stmt->close();

            } else {

                $stmt->close();

                /*
                ------------------------------------------------
                Add controller
                ------------------------------------------------
                */

                $stmt = $conn->prepare(
                    "INSERT INTO controllers
                    (
                        controller_id,
                        customer_token,
                        device_token,
                        active
                    )
                    VALUES (?, ?, ?, 1)"
                );

                if (!$stmt) {

                    $error =
                        "Database error: " .
                        $conn->error;

                } else {

                    $stmt->bind_param(
                        "sss",
                        $controller_id,
                        $customer_token,
                        $device_token
                    );

                    if ($stmt->execute()) {

                        $message =
                            "Controller added successfully.";

                    } else {

                        $error =
                            "Unable to add controller: " .
                            $stmt->error;
                    }

                    $stmt->close();
                }
            }
        }
    }
}

/* =========================================================
   EDIT CONTROLLER
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "edit"
) {
    $id =
        intval($_POST["id"] ?? 0);

    $controller_id =
        trim($_POST["controller_id"] ?? "");

    $customer_token =
        trim($_POST["customer_token"] ?? "");

    $device_token =
        trim($_POST["device_token"] ?? "");

    if ($id <= 0) {

        $error = "Invalid controller.";

    } elseif ($controller_id === "") {

        $error = "Controller ID is required.";

    } elseif ($customer_token === "") {

        $error = "Customer Token is required.";

    } elseif ($device_token === "") {

        $error = "Device Token is required.";

    } else {

        /*
        --------------------------------------------------------
        Check whether another controller has same ID
        --------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "SELECT id
             FROM controllers
             WHERE controller_id = ?
             AND id <> ?
             LIMIT 1"
        );

        if (!$stmt) {

            $error =
                "Database error: " .
                $conn->error;

        } else {

            $stmt->bind_param(
                "si",
                $controller_id,
                $id
            );

            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {

                $error =
                    "Another controller already uses this Controller ID.";

                $stmt->close();

            } else {

                $stmt->close();

                /*
                ------------------------------------------------
                Update controller
                ------------------------------------------------
                */

                $stmt = $conn->prepare(
                    "UPDATE controllers
                     SET
                        controller_id = ?,
                        customer_token = ?,
                        device_token = ?,
                        updated_at = NOW()
                     WHERE id = ?"
                );

                if (!$stmt) {

                    $error =
                        "Database error: " .
                        $conn->error;

                } else {

                    $stmt->bind_param(
                        "sssi",
                        $controller_id,
                        $customer_token,
                        $device_token,
                        $id
                    );

                    if ($stmt->execute()) {

                        $message =
                            "Controller updated successfully.";

                    } else {

                        $error =
                            "Unable to update controller: " .
                            $stmt->error;
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

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "delete"
) {
    $id =
        intval($_POST["id"] ?? 0);

    if ($id <= 0) {

        $error = "Invalid controller.";

    } else {

        $stmt = $conn->prepare(
            "DELETE FROM controllers
             WHERE id = ?"
        );

        if (!$stmt) {

            $error =
                "Database error: " .
                $conn->error;

        } else {

            $stmt->bind_param(
                "i",
                $id
            );

            if ($stmt->execute()) {

                $message =
                    "Controller deleted successfully.";

            } else {

                $error =
                    "Unable to delete controller: " .
                    $stmt->error;
            }

            $stmt->close();
        }
    }
}

/* =========================================================
   ACTIVATE / DEACTIVATE
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "toggle"
) {
    $id =
        intval($_POST["id"] ?? 0);

    if ($id <= 0) {

        $error = "Invalid controller.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE controllers
             SET
                active = IF(active = 1, 0, 1),
                updated_at = NOW()
             WHERE id = ?"
        );

        if (!$stmt) {

            $error =
                "Database error: " .
                $conn->error;

        } else {

            $stmt->bind_param(
                "i",
                $id
            );

            if ($stmt->execute()) {

                $message =
                    "Controller status changed successfully.";

            } else {

                $error =
                    "Unable to change controller status: " .
                    $stmt->error;
            }

            $stmt->close();
        }
    }
}

/* =========================================================
   READ CONTROLLERS
========================================================= */

$result = $conn->query(
    "SELECT
        id,
        controller_id,
        customer_token,
        device_token,
        active,
        created_at,
        updated_at
     FROM controllers
     ORDER BY id DESC"
);

if (!$result) {

    $error =
        "Unable to read controllers: " .
        $conn->error;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Owner Controller Management</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 20px;
    background: #f2f4f7;
    font-family: Arial, sans-serif;
}

.container {
    max-width: 1200px;
    margin: auto;
}

h1 {
    text-align: center;
    margin-bottom: 25px;
}

.card {
    background: #ffffff;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.10);
}

.message {
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 6px;
    background: #d4edda;
    color: #155724;
}

.error {
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 6px;
    background: #f8d7da;
    color: #721c24;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 6px;
}

input {
    width: 100%;
    padding: 10px;
    border: 1px solid #bbb;
    border-radius: 5px;
}

button {
    padding: 9px 14px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
}

.add-btn {
    background: #198754;
    color: white;
    margin-top: 15px;
}

.edit-btn {
    background: #0d6efd;
    color: white;
}

.delete-btn {
    background: #dc3545;
    color: white;
}

.toggle-btn {
    background: #6c757d;
    color: white;
}

.back-btn {
    background: #343a40;
    color: white;
    margin-bottom: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th,
td {
    border: 1px solid #ddd;
    padding: 9px;
    text-align: center;
    vertical-align: middle;
}

th {
    background: #343a40;
    color: white;
}

.active {
    color: green;
    font-weight: bold;
}

.inactive {
    color: red;
    font-weight: bold;
}

.actions {
    display: flex;
    gap: 5px;
    justify-content: center;
    flex-wrap: wrap;
}

@media (max-width: 800px) {

    .form-row {
        grid-template-columns: 1fr;
    }

    table {
        font-size: 12px;
    }

    th,
    td {
        padding: 6px;
    }
}

</style>

</head>

<body>

<div class="container">

<h1>Owner Controller Management</h1>

<button
    type="button"
    class="back-btn"
    onclick="window.location.href='index.php'">
    ← Back
</button>

<?php if ($message !== ""): ?>

<div class="message">
    <?= h($message) ?>
</div>

<?php endif; ?>

<?php if ($error !== ""): ?>

<div class="error">
    <?= h($error) ?>
</div>

<?php endif; ?>

<!-- =====================================================
     ADD NEW CONTROLLER
====================================================== -->

<div class="card">

<h2>Add New Controller</h2>

<form method="post">

<input
    type="hidden"
    name="action"
    value="add">

<div class="form-row">

<div>

<label>Controller ID</label>

<input
    type="text"
    name="controller_id"
    placeholder="ESP0001"
    required>

</div>

<div>

<label>Customer Token</label>

<input
    type="text"
    name="customer_token"
    required>

</div>

<div>

<label>Device Token</label>

<input
    type="text"
    name="device_token"
    required>

</div>

</div>

<button
    type="submit"
    class="add-btn">
    Add Controller
</button>

</form>

</div>

<!-- =====================================================
     CONTROLLER LIST
====================================================== -->

<div class="card">

<h2>Controllers</h2>

<div style="overflow-x:auto;">

<table>

<thead>

<tr>

<th>ID</th>
<th>Controller ID</th>
<th>Customer Token</th>
<th>Device Token</th>
<th>Status</th>
<th>Created</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php if ($result && $result->num_rows > 0): ?>

<?php while ($row = $result->fetch_assoc()): ?>

<tr>

<td>
<?= h($row["id"]) ?>
</td>

<td>
<strong>
<?= h($row["controller_id"]) ?>
</strong>
</td>

<td>
<?= h($row["customer_token"]) ?>
</td>

<td>
<?= h($row["device_token"]) ?>
</td>

<td>

<?php if ((int)$row["active"] === 1): ?>

<span class="active">
ACTIVE
</span>

<?php else: ?>

<span class="inactive">
INACTIVE
</span>

<?php endif; ?>

</td>

<td>
<?= h($row["created_at"] ?? "") ?>
</td>

<td>

<div class="actions">

<!-- EDIT -->

<form
    method="post"
    onsubmit="return editController(this);">

<input
    type="hidden"
    name="action"
    value="edit">

<input
    type="hidden"
    name="id"
    value="<?= h($row["id"]) ?>">

<input
    type="hidden"
    name="controller_id"
    value="<?= h($row["controller_id"]) ?>">

<input
    type="hidden"
    name="customer_token"
    value="<?= h($row["customer_token"]) ?>">

<input
    type="hidden"
    name="device_token"
    value="<?= h($row["device_token"]) ?>">

<button
    type="submit"
    class="edit-btn">
    Edit
</button>

</form>

<!-- ACTIVATE / DEACTIVATE -->

<form method="post">

<input
    type="hidden"
    name="action"
    value="toggle">

<input
    type="hidden"
    name="id"
    value="<?= h($row["id"]) ?>">

<button
    type="submit"
    class="toggle-btn">

<?php
echo ((int)$row["active"] === 1)
    ? "Deactivate"
    : "Activate";
?>

</button>

</form>

<!-- DELETE -->

<form method="post">

<input
    type="hidden"
    name="action"
    value="delete">

<input
    type="hidden"
    name="id"
    value="<?= h($row["id"]) ?>">

<button
    type="submit"
    class="delete-btn"
    onclick="
        return confirm(
            'Are you sure you want to delete this controller?'
        );
    ">
    Delete
</button>

</form>

</div>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="7">
No controllers found.
</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

<script>

function editController(form)
{
    let controllerId = prompt(
        "Controller ID:",
        form.controller_id.value
    );

    if (controllerId === null) {
        return false;
    }

    controllerId = controllerId.trim();

    if (controllerId === "") {
        alert("Controller ID cannot be empty.");
        return false;
    }

    let customerToken = prompt(
        "Customer Token:",
        form.customer_token.value
    );

    if (customerToken === null) {
        return false;
    }

    customerToken = customerToken.trim();

    if (customerToken === "") {
        alert("Customer Token cannot be empty.");
        return false;
    }

    let deviceToken = prompt(
        "Device Token:",
        form.device_token.value
    );

    if (deviceToken === null) {
        return false;
    }

    deviceToken = deviceToken.trim();

    if (deviceToken === "") {
        alert("Device Token cannot be empty.");
        return false;
    }

    form.controller_id.value = controllerId;
    form.customer_token.value = customerToken;
    form.device_token.value = deviceToken;

    return true;
}

</script>

</body>

</html>

