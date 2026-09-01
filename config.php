<?php
/*
 * ============================================================
 * ESP-SWITCH5 REMOTE - config.php
 * ============================================================
 *
 * CENTRAL CONFIGURATION FILE
 *
 * Remote:
 *     esp-switch5-remote.onrender.com
 *
 * Database:
 *     TiDB Cloud
 *
 * IMPORTANT:
 *
 * Do NOT put actual passwords in this file.
 *
 * All sensitive information comes from
 * Render Environment Variables.
 *
 * Environment Variables:
 *
 *     DB_HOST
 *     DB_USER
 *     DB_PASSWORD
 *     DB_NAME
 *     DB_PORT
 *
 *     ADMIN_PASSWORD
 *     TOKEN_PASSWORD
 *
 * ============================================================
 */


/* =========================================================
   TIMEZONE
========================================================= */

date_default_timezone_set("Asia/Kolkata");


/* =========================================================
   APPLICATION
========================================================= */

define(
    "APP_NAME",
    "ESP-SWITCH5 REMOTE"
);


/* =========================================================
   DATABASE
========================================================= */

$db_host =
    getenv("DB_HOST") ?: "";

$db_user =
    getenv("DB_USER") ?: "";

$db_password =
    getenv("DB_PASSWORD") ?: "";

$db_name =
    getenv("DB_NAME") ?: "";

$db_port =
    getenv("DB_PORT") ?: "4000";


/* =========================================================
   ADMIN PASSWORD
========================================================= */

/*
 * Used by:
 *
 *     index.php
 *
 * Render Environment Variable:
 *
 *     ADMIN_PASSWORD
 */

$admin_password =
    getenv("ADMIN_PASSWORD") ?: "";


/* =========================================================
   OWNER TOKEN PASSWORD
========================================================= */

/*
 * Used by:
 *
 *     owner_token.php
 *
 * Render Environment Variable:
 *
 *     TOKEN_PASSWORD
 */

$token_password =
    getenv("TOKEN_PASSWORD") ?: "";


/* =========================================================
   API SETTINGS
========================================================= */

define(
    "API_TIMEOUT",
    15
);


define(
    "ESP_POLL_INTERVAL",
    3
);


/* =========================================================
   DEBUG
========================================================= */

define(
    "DEBUG_MODE",
    false
);


/* =========================================================
   CONFIGURATION VALIDATION
========================================================= */

/*
 * We deliberately do NOT display passwords.
 */

if (DEBUG_MODE) {

    $missing = [];

    if ($db_host === "") {
        $missing[] = "DB_HOST";
    }

    if ($db_user === "") {
        $missing[] = "DB_USER";
    }

    if ($db_name === "") {
        $missing[] = "DB_NAME";
    }

    if ($admin_password === "") {
        $missing[] = "ADMIN_PASSWORD";
    }

    if ($token_password === "") {
        $missing[] = "TOKEN_PASSWORD";
    }

    if (!empty($missing)) {

        die(
            "Missing environment variables: " .
            implode(", ", $missing)
        );
    }
}

?>