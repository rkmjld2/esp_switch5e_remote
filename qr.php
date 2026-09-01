<?php

/*
============================================================
 ESP-SWITCH5 REMOTE
 CUSTOMER QR CODE
============================================================

 IMPORTANT:

 Only administrator can generate/view a customer QR code.

 The QR code contains:

 /c/ESP0001?t=CUSTOMER_TOKEN

============================================================
*/

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

date_default_timezone_set("Asia/Kolkata");

session_start();


/* =========================================================
   ADMINISTRATOR LOGIN REQUIRED
========================================================= */

if (
    !isset($_SESSION["esp_admin"]) ||
    $_SESSION["esp_admin"] !== true
) {

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

            font-family:
                Arial,
                Helvetica,
                sans-serif;

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

        </style>

        </head>

        <body>

        <div class='box'>

        <h1>ACCESS DENIED</h1>

        <p>
        Administrator login is required.
        </p>

        </div>

        </body>

        </html>
    ");

}


/* =========================================================
   CONTROLLER ID
========================================================= */

$controller_id =
    trim(
        $_GET["controller_id"] ?? ""
    );


/* =========================================================
   VALIDATE CONTROLLER ID
========================================================= */

if ($controller_id === "") {

    die("Controller ID missing.");
}


if (
    !preg_match(
        '/^[A-Za-z0-9_-]+$/',
        $controller_id
    )
) {

    die("Invalid Controller ID.");
}


/* =========================================================
   GET CONTROLLER
========================================================= */

$stmt = $conn->prepare("
    SELECT
        controller_id,
        customer_name,
        active,
        customer_token
    FROM controllers
    WHERE controller_id = ?
    LIMIT 1
");


if (!$stmt) {

    die("Controller query preparation failed.");
}


$stmt->bind_param(
    "s",
    $controller_id
);


if (!$stmt->execute()) {

    $stmt->close();

    die("Controller query failed.");
}


$result =
    $stmt->get_result();


if (
    $result->num_rows === 0
) {

    $stmt->close();

    die("Controller not found.");
}


$controller =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   CUSTOMER TOKEN REQUIRED
========================================================= */

$customer_token =
    trim(
        $controller["customer_token"] ?? ""
    );


if ($customer_token === "") {

    die("
        Customer token has not been assigned
        to this controller.
    ");
}


/* =========================================================
   CREATE CUSTOMER CONTROLLER URL
========================================================= */

$scheme =
    (
        isset($_SERVER["HTTPS"]) &&
        $_SERVER["HTTPS"] !== "off"
    )
    ? "https"
    : "https";


$host =
    $_SERVER["HTTP_HOST"]
    ?? "esp-switch5-remote.onrender.com";


$controller_url =
    $scheme .
    "://" .
    $host .
    "/c/" .
    rawurlencode(
        $controller_id
    ) .
    "?t=" .
    rawurlencode(
        $customer_token
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
Controller QR Code
</title>


<style>

* {

    box-sizing: border-box;
}

body {

    margin: 0;

    padding: 30px;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f2f2f2;

    text-align: center;
}


.box {

    max-width: 550px;

    margin: auto;

    padding: 30px;

    background: white;

    border-radius: 12px;

    border: 1px solid #ccc;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,0.15);
}


h1 {

    margin-top: 0;

    color: #333;
}


.controller {

    font-size: 22px;

    font-weight: bold;

    margin: 15px 0 5px 0;
}


.customer {

    color: #555;

    margin-bottom: 25px;
}


#qrcode {

    width: 300px;

    height: 300px;

    margin: 0 auto;
}


#qrcode img,
#qrcode canvas {

    display: block;

    margin: auto;
}


.url {

    margin-top: 20px;

    margin-bottom: 20px;

    font-size: 15px;

    word-break: break-all;
}


.url a {

    color: #007bff;

    text-decoration: none;
}


.url a:hover {

    text-decoration: underline;
}


.buttons {

    margin-top: 20px;
}


button,
.open-button {

    display: inline-block;

    border: none;

    border-radius: 6px;

    padding: 12px 20px;

    margin: 5px;

    font-size: 15px;

    cursor: pointer;

    text-decoration: none;
}


.download-button {

    background: #28a745;

    color: white;
}


.print-button {

    background: #007bff;

    color: white;
}


.open-button {

    background: #6c757d;

    color: white;
}


button:hover,
.open-button:hover {

    opacity: 0.85;
}


/* PRINT */

@media print {

    body {

        background: white;

        padding: 0;
    }

    .box {

        border: none;

        box-shadow: none;
    }

    .buttons,
    .url {

        display: none;
    }
}

</style>

</head>


<body>


<div class="box">


<h1>
ESP-SWITCH5 REMOTE
</h1>


<div class="controller">

Controller:

<?php

echo htmlspecialchars(
    $controller_id,
    ENT_QUOTES,
    "UTF-8"
);

?>

</div>


<div class="customer">

Customer:

<?php

echo htmlspecialchars(
    $controller["customer_name"] ?? "",
    ENT_QUOTES,
    "UTF-8"
);

?>

</div>


<!-- QR -->

<div id="qrcode"></div>


<!-- CUSTOMER URL -->

<div class="url">

<a
    href="<?php

    echo htmlspecialchars(
        $controller_url,
        ENT_QUOTES,
        "UTF-8"
    );

    ?>"
    target="_blank"
>

<?php

echo htmlspecialchars(
    $controller_url,
    ENT_QUOTES,
    "UTF-8"
);

?>

</a>

</div>


<!-- BUTTONS -->

<div class="buttons">


<button
    type="button"
    class="download-button"
    onclick="downloadQR()"
>
DOWNLOAD QR CODE
</button>


<button
    type="button"
    class="print-button"
    onclick="window.print()"
>
PRINT QR CODE
</button>


<a
    class="open-button"
    href="<?php

    echo htmlspecialchars(
        $controller_url,
        ENT_QUOTES,
        "UTF-8"
    );

    ?>"
    target="_blank"
>
OPEN CONTROLLER
</a>


</div>


</div>


<!-- QR CODE LIBRARY -->

<script
src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js">
</script>


<script>

/* =========================================================
   CUSTOMER URL
========================================================= */

const controllerURL =
    <?php

    echo json_encode(
        $controller_url
    );

    ?>;


const controllerID =
    <?php

    echo json_encode(
        $controller_id
    );

    ?>;


/* =========================================================
   GENERATE QR
========================================================= */

const qrContainer =
    document.getElementById(
        "qrcode"
    );


const qr =
    new QRCode(

        qrContainer,

        {

            text:
                controllerURL,

            width:
                300,

            height:
                300,

            correctLevel:
                QRCode.CorrectLevel.H

        }

    );


/* =========================================================
   DOWNLOAD QR
========================================================= */

function downloadQR()
{

    const canvas =
        qrContainer.querySelector(
            "canvas"
        );


    if (canvas)
    {

        const link =
            document.createElement(
                "a"
            );


        link.download =
            controllerID +
            "_QR.png";


        link.href =
            canvas.toDataURL(
                "image/png"
            );


        document.body.appendChild(
            link
        );


        link.click();


        document.body.removeChild(
            link
        );


        return;
    }


    const image =
        qrContainer.querySelector(
            "img"
        );


    if (image)
    {

        const canvas =
            document.createElement(
                "canvas"
            );


        canvas.width =
            300;

        canvas.height =
            300;


        const context =
            canvas.getContext(
                "2d"
            );


        context.drawImage(

            image,

            0,

            0,

            300,

            300

        );


        const link =
            document.createElement(
                "a"
            );


        link.download =
            controllerID +
            "_QR.png";


        link.href =
            canvas.toDataURL(
                "image/png"
            );


        document.body.appendChild(
            link
        );


        link.click();


        document.body.removeChild(
            link
        );


        return;
    }


    alert(
        "QR code is not ready. Please wait and try again."
    );
}

</script>


</body>

</html>
