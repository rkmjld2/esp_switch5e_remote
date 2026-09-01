<?php
/*
============================================================
 ESP-SWITCH5 REMOTE - api.php
============================================================

Remote server:
    Render.com

Database:
    TiDB Cloud

Timezone:
    Asia/Kolkata (IST)

Tables:
    controllers
    esp_control

controllers:
    id
    controller_id
    device_token
    customer_name
    active
    last_seen
    start_time
    end_time

esp_control:
    id
    controller_id
    D1
    D2
    D3
    D4
    D5
    D6
    D7
    D8

============================================================
*/


/* =========================================================
   DATABASE
========================================================= */
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";


/* =========================================================
   JSON HEADER
========================================================= */

header(
    "Content-Type: application/json; charset=UTF-8"
);


/* =========================================================
   GET PARAMETERS
========================================================= */

$action =
    trim($_GET["action"] ?? "");

$controller_id =
    trim($_GET["controller_id"] ?? "");

$device_token =
    trim($_GET["device_token"] ?? "");


/* =========================================================
   VALIDATE CONTROLLER ID
========================================================= */

if ($controller_id === "")
{
    echo json_encode([
        "status" => "error",
        "message" => "controller_id missing"
    ]);

    exit;
}


/* =========================================================
   VALIDATE DEVICE TOKEN
========================================================= */

if ($device_token === "")
{
    echo json_encode([
        "status" => "error",
        "message" => "device_token missing"
    ]);

    exit;
}


/* =========================================================
   FIND CONTROLLER
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        controller_id,
        device_token,
        customer_name,
        active,
        last_seen,
        start_time,
        end_time
    FROM controllers
    WHERE controller_id = ?
      AND device_token = ?
    LIMIT 1
");


if (!$stmt)
{
    echo json_encode([
        "status" => "error",
        "message" => "Controller prepare failed"
    ]);

    exit;
}


$stmt->bind_param(
    "ss",
    $controller_id,
    $device_token
);


if (!$stmt->execute())
{
    echo json_encode([
        "status" => "error",
        "message" => "Controller query failed"
    ]);

    $stmt->close();

    exit;
}


$result =
    $stmt->get_result();


/* =========================================================
   CONTROLLER NOT FOUND
========================================================= */

if ($result->num_rows === 0)
{
    echo json_encode([
        "status" => "error",
        "message" =>
            "Invalid controller_id or device_token"
    ]);

    $stmt->close();

    exit;
}


$controller =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   CURRENT INDIA TIME
========================================================= */

$current_time =
    new DateTime(
        "now",
        new DateTimeZone("Asia/Kolkata")
    );


$current_time_string =
    $current_time->format(
        "Y-m-d H:i:s"
    );


/* =========================================================
   CALENDAR TIME CONTROL
=========================================================

   Logic:

   1. Controller must be ACTIVE.
   2. START TIME must be reached.
   3. END TIME must not have passed.

   If start_time or end_time is NULL/empty,
   calendar restriction is not applied.

========================================================= */

$calendar_allowed = true;

$calendar_status = "NO_SCHEDULE";


$start_time =
    $controller["start_time"] ?? null;

$end_time =
    $controller["end_time"] ?? null;


/* ---------------------------------------------------------
   If both START and END are present
--------------------------------------------------------- */

if (
    !empty($start_time) &&
    !empty($end_time)
)
{

    try
    {

        $start_datetime =
            new DateTime(
                $start_time,
                new DateTimeZone("Asia/Kolkata")
            );


        $end_datetime =
            new DateTime(
                $end_time,
                new DateTimeZone("Asia/Kolkata")
            );


        if (
            $current_time < $start_datetime
        )
        {

            $calendar_allowed = false;

            $calendar_status =
                "NOT_STARTED";
        }
        elseif (
            $current_time > $end_datetime
        )
        {

            $calendar_allowed = false;

            $calendar_status =
                "EXPIRED";
        }
        else
        {

            $calendar_allowed = true;

            $calendar_status =
                "ACTIVE";
        }

    }
    catch (Exception $e)
    {

        $calendar_allowed = false;

        $calendar_status =
            "INVALID_SCHEDULE";
    }
}


/* =========================================================
   CONTROLLER ACTIVE CHECK
========================================================= */

if (
    (int)$controller["active"] !== 1
)
{

    echo json_encode([

        "status" => "error",

        "message" =>
            "Controller is inactive",

        "controller_id" =>
            $controller_id,

        "calendar_status" =>
            $calendar_status,

        "current_time" =>
            $current_time_string,

        "start_time" =>
            $start_time,

        "end_time" =>
            $end_time

    ]);

    exit;
}


/* =========================================================
   UPDATE LAST_SEEN
========================================================= */

$stmt =
    $conn->prepare("
        UPDATE controllers
        SET last_seen = ?
        WHERE controller_id = ?
          AND device_token = ?
    ");


if (!$stmt)
{
    echo json_encode([
        "status" => "error",
        "message" => "last_seen prepare failed"
    ]);

    exit;
}


$stmt->bind_param(
    "sss",
    $current_time_string,
    $controller_id,
    $device_token
);


if (!$stmt->execute())
{
    echo json_encode([
        "status" => "error",
        "message" =>
            "Could not update last_seen"
    ]);

    $stmt->close();

    exit;
}


$stmt->close();


/* =========================================================
   CALENDAR TIME NOT ACTIVE
=========================================================

   IMPORTANT:

   The ESP must receive a clear response when the
   controller is outside its permitted calendar period.

   We return all D1-D8 as OFF.

========================================================= */

if (!$calendar_allowed)
{

    echo json_encode([

        "status" => "ok",

        "controller_id" =>
            $controller_id,

        "calendar_allowed" =>
            false,

        "calendar_status" =>
            $calendar_status,

        "current_time" =>
            $current_time_string,

        "start_time" =>
            $start_time,

        "end_time" =>
            $end_time,

        "D1" => 0,
        "D2" => 0,
        "D3" => 0,
        "D4" => 0,
        "D5" => 0,
        "D6" => 0,
        "D7" => 0,
        "D8" => 0,

        "last_seen" =>
            $current_time_string

    ]);

    exit;
}


/* =========================================================
   ACTION = GET
========================================================= */

if ($action === "get")
{

    $stmt =
        $conn->prepare("
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


    if (!$stmt)
    {
        echo json_encode([
            "status" => "error",
            "message" =>
                "esp_control prepare failed"
        ]);

        exit;
    }


    $stmt->bind_param(
        "s",
        $controller_id
    );


    if (!$stmt->execute())
    {
        echo json_encode([
            "status" => "error",
            "message" =>
                "esp_control query failed"
        ]);

        $stmt->close();

        exit;
    }


    $result =
        $stmt->get_result();


    if ($result->num_rows === 0)
    {
        echo json_encode([
            "status" => "error",
            "message" =>
                "No esp_control record found"
        ]);

        $stmt->close();

        exit;
    }


    $row =
        $result->fetch_assoc();


    $stmt->close();


    /* -----------------------------------------------------
       RETURN D1-D8
    ----------------------------------------------------- */

    echo json_encode([

        "status" => "ok",

        "controller_id" =>
            $controller_id,

        "calendar_allowed" =>
            true,

        "calendar_status" =>
            "ACTIVE",

        "current_time" =>
            $current_time_string,

        "start_time" =>
            $start_time,

        "end_time" =>
            $end_time,

        "D1" => (int)$row["D1"],
        "D2" => (int)$row["D2"],
        "D3" => (int)$row["D3"],
        "D4" => (int)$row["D4"],
        "D5" => (int)$row["D5"],
        "D6" => (int)$row["D6"],
        "D7" => (int)$row["D7"],
        "D8" => (int)$row["D8"],

        "last_seen" =>
            $current_time_string

    ]);

    exit;
}


/* =========================================================
   ACTION = SET
=========================================================

   Example:

   action=set
   controller_id=ESP0001
   device_token=ESP0001-TOKEN-2026-A7K9X2
   pin=D1
   value=1

========================================================= */

if ($action === "set")
{

    $pin =
        strtoupper(
            trim($_GET["pin"] ?? "")
        );


    $value =
        isset($_GET["value"])
            ? (int)$_GET["value"]
            : -1;


    /* -----------------------------------------------------
       VALIDATE PIN
    ----------------------------------------------------- */

    if (
        !preg_match(
            '/^D[1-8]$/',
            $pin
        )
    )
    {

        echo json_encode([
            "status" => "error",
            "message" => "Invalid pin"
        ]);

        exit;
    }


    /* -----------------------------------------------------
       VALIDATE VALUE
    ----------------------------------------------------- */

    if (
        $value !== 0 &&
        $value !== 1
    )
    {

        echo json_encode([
            "status" => "error",
            "message" => "Invalid value"
        ]);

        exit;
    }


    /* -----------------------------------------------------
       UPDATE PIN
    ----------------------------------------------------- */

    $sql = "
        UPDATE esp_control
        SET `$pin` = ?
        WHERE controller_id = ?
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt)
    {

        echo json_encode([
            "status" => "error",
            "message" =>
                "Pin update prepare failed"
        ]);

        exit;
    }


    $stmt->bind_param(
        "is",
        $value,
        $controller_id
    );


    if (!$stmt->execute())
    {

        echo json_encode([
            "status" => "error",
            "message" =>
                "Pin update failed"
        ]);

        $stmt->close();

        exit;
    }


    $stmt->close();


    echo json_encode([

        "status" => "ok",

        "controller_id" =>
            $controller_id,

        "pin" =>
            $pin,

        "value" =>
            $value,

        "calendar_allowed" =>
            true,

        "calendar_status" =>
            "ACTIVE",

        "current_time" =>
            $current_time_string,

        "start_time" =>
            $start_time,

        "end_time" =>
            $end_time,

        "last_seen" =>
            $current_time_string

    ]);

    exit;
}


/* =========================================================
   UNKNOWN ACTION
========================================================= */

echo json_encode([

    "status" => "error",

    "message" =>
        "Unknown action. Use action=get or action=set"

]);

exit;

?>
