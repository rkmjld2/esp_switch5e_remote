/*
  ============================================================
  ESP-SWITCH5 REMOTE - ESP8266 CONTROLLER
  ============================================================

  Remote server:
      esp-switch5-remote.onrender.com

  Calendar Time Control:
      Controlled by remote api.php

  Timezone:
      Asia/Kolkata (IST)

  Controller identity:
      CONTROLLER_ID + DEVICE_TOKEN

  IMPORTANT:
      The ESP8266 does NOT calculate START/END time.

      The remote server is the authority for:
          NOT_STARTED
          ACTIVE
          EXPIRED

      The server also returns D1-D8.

  ============================================================
*/

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecureBearSSL.h>
#include <UrlEncode.h>


/* ============================================================
   WIFI
   ============================================================ */

const char* WIFI_SSID =
    "Airtel_56";

const char* WIFI_PASSWORD =
    "Raviuma5658";


/* ============================================================
   CONTROLLER IDENTITY
   ============================================================ */

const char* CONTROLLER_ID =
    "ESP0001";

const char* DEVICE_TOKEN =
    "ESP0001-TOKEN-2026-A7K9X2";


/* ============================================================
   REMOTE SERVER
   ============================================================ */

const char* serverURL =
    "https://esp-switch5-remote.onrender.com/api.php";


/* ============================================================
   ESP8266 PINS D1-D8
   ============================================================ */

const uint8_t pins[8] = {

  D1,
  D2,
  D3,
  D4,
  D5,
  D6,
  D7,
  D8

};


/* ============================================================
   TIMING
   ============================================================ */

const unsigned long POLL_INTERVAL =
    3000UL;

const unsigned long WIFI_RETRY_INTERVAL =
    5000UL;

/*
   Restart ESP8266 only if Wi-Fi remains unavailable
   for 2 minutes.
*/
const unsigned long MAX_WIFI_FAILURE_TIME =
    120000UL;


unsigned long lastPoll = 0;

unsigned long lastWiFiAttempt = 0;

unsigned long wifiFailureStarted = 0;


/* ============================================================
   START WIFI
   ============================================================ */

void startWiFi()
{
  Serial.println();
  Serial.print("Connecting to WiFi: ");
  Serial.println(WIFI_SSID);

  WiFi.mode(WIFI_STA);

  WiFi.setAutoReconnect(true);

  WiFi.persistent(false);

  WiFi.begin(
    WIFI_SSID,
    WIFI_PASSWORD
  );
}


/* ============================================================
   AUTOMATIC WIFI RECOVERY
   ============================================================ */

void maintainWiFi()
{
  if (WiFi.status() == WL_CONNECTED)
  {
    if (wifiFailureStarted != 0)
    {
      Serial.println();
      Serial.println(
        "WiFi connection restored."
      );

      wifiFailureStarted = 0;
    }

    return;
  }


  unsigned long now =
      millis();


  if (wifiFailureStarted == 0)
  {
    wifiFailureStarted = now;

    Serial.println();
    Serial.println(
      "WiFi disconnected."
    );

    Serial.println(
      "Automatic reconnection started..."
    );
  }


  if (
    now - lastWiFiAttempt
    >= WIFI_RETRY_INTERVAL
  )
  {
    lastWiFiAttempt = now;

    Serial.println(
      "Retrying WiFi..."
    );

    WiFi.disconnect();

    WiFi.begin(
      WIFI_SSID,
      WIFI_PASSWORD
    );
  }


  /*
     Restart ESP8266 after prolonged
     Wi-Fi failure.
  */

  if (
    now - wifiFailureStarted
    >= MAX_WIFI_FAILURE_TIME
  )
  {
    Serial.println();

    Serial.println(
      "WiFi unavailable for too long."
    );

    Serial.println(
      "Automatically restarting ESP8266..."
    );

    delay(1000);

    ESP.restart();
  }
}


/* ============================================================
   TURN ALL OUTPUTS OFF
   ============================================================ */

void turnAllOutputsOff()
{
  for (int i = 0; i < 8; i++)
  {
    digitalWrite(
      pins[i],
      LOW
    );
  }

  Serial.println(
    "All outputs OFF."
  );
}


/* ============================================================
   GET CONTROLLER DATA
   ============================================================ */

void getControllerData()
{
  if (
    WiFi.status() != WL_CONNECTED
  )
  {
    Serial.println(
      "Server request skipped: "
      "WiFi not connected."
    );

    return;
  }


  /*
     HTTPS client.
     Certificate verification is disabled,
     same as the previous working version.
  */

  std::unique_ptr<
      BearSSL::WiFiClientSecure
  > client(
      new BearSSL::WiFiClientSecure
  );


  client->setInsecure();


  HTTPClient https;


  /* ========================================================
     BUILD URL
     ======================================================== */

  String url =
      String(serverURL);


  url += "?action=get";

  url += "&controller_id=";

  url += urlEncode(
      CONTROLLER_ID
  );

  url += "&device_token=";

  url += urlEncode(
      DEVICE_TOKEN
  );


  Serial.println();
  Serial.println(
    "=========================================="
  );

  Serial.println(
    "Requesting remote server..."
  );

  Serial.println(url);


  /* ========================================================
     START HTTPS
     ======================================================== */

  if (
    !https.begin(
      *client,
      url
    )
  )
  {
    Serial.println(
      "HTTPS connection could not be started."
    );

    return;
  }


  https.setTimeout(15000);


  int httpCode =
      https.GET();


  Serial.print(
    "HTTP Code: "
  );

  Serial.println(
    httpCode
  );


  /* ========================================================
     SERVER RESPONSE
     ======================================================== */

  if (httpCode > 0)
  {
    String response =
        https.getString();


    Serial.println(
      "Server response:"
    );

    Serial.println(
      response
    );


    /* ======================================================
       HTTP 200
       ====================================================== */

    if (
      httpCode == HTTP_CODE_OK
    )
    {

      /* ====================================================
         READ CALENDAR STATUS
         ==================================================== */

      bool calendarAllowed =
          false;


      /*
         Look for:

         "calendar_allowed":true

         or

         "calendar_allowed":false
      */

      if (
        response.indexOf(
          "\"calendar_allowed\":true"
        ) >= 0
      )
      {
        calendarAllowed = true;
      }


      /* ====================================================
         READ CALENDAR STATUS TEXT
         ==================================================== */

      String calendarStatus =
          "UNKNOWN";


      int statusPos =
          response.indexOf(
            "\"calendar_status\":\""
          );


      if (statusPos >= 0)
      {
        statusPos +=
            strlen(
              "\"calendar_status\":\""
            );


        int statusEnd =
            response.indexOf(
              "\"",
              statusPos
            );


        if (statusEnd > statusPos)
        {
          calendarStatus =
              response.substring(
                statusPos,
                statusEnd
              );
        }
      }


      /* ====================================================
         DISPLAY CALENDAR STATUS
         ==================================================== */

      Serial.println();

      Serial.print(
        "Calendar Allowed: "
      );

      Serial.println(
        calendarAllowed
          ? "YES"
          : "NO"
      );


      Serial.print(
        "Calendar Status: "
      );

      Serial.println(
        calendarStatus
      );


      /* ====================================================
         DISPLAY SERVER CURRENT TIME
         ==================================================== */

      int timePos =
          response.indexOf(
            "\"current_time\":\""
          );


      if (timePos >= 0)
      {
        timePos +=
            strlen(
              "\"current_time\":\""
            );


        int timeEnd =
            response.indexOf(
              "\"",
              timePos
            );


        if (timeEnd > timePos)
        {
          String currentTime =
              response.substring(
                timePos,
                timeEnd
              );


          Serial.print(
            "Server IST Time: "
          );

          Serial.println(
            currentTime
          );
        }
      }


      /* ====================================================
         CALENDAR NOT ACTIVE
         ==================================================== */

      if (!calendarAllowed)
      {
        Serial.println();

        Serial.println(
          "Calendar period is NOT ACTIVE."
        );


        if (
          calendarStatus ==
          "NOT_STARTED"
        )
        {
          Serial.println(
            "Controller has not reached START TIME."
          );
        }
        else if (
          calendarStatus ==
          "EXPIRED"
        )
        {
          Serial.println(
            "Controller has passed END TIME."
          );
        }
        else if (
          calendarStatus ==
          "INVALID_SCHEDULE"
        )
        {
          Serial.println(
            "Invalid calendar schedule."
          );
        }


        /*
           Safety action:
           all outputs OFF.
        */

        turnAllOutputsOff();


        https.end();

        return;
      }


      /* ====================================================
         CALENDAR ACTIVE
         ==================================================== */

      Serial.println();

      Serial.println(
        "Calendar period ACTIVE."
      );


      /* ====================================================
         READ D1-D8
         ==================================================== */

      int values[8];

      bool allFound = true;


      for (
        int i = 0;
        i < 8;
        i++
      )
      {

        String key =
            "\"D" +
            String(i + 1) +
            "\":";


        int pos =
            response.indexOf(
              key
            );


        if (pos < 0)
        {
          allFound = false;

          break;
        }


        pos +=
            key.length();


        /*
           Skip spaces.
        */

        while (
          pos <
            (int)response.length() &&
          response[pos] == ' '
        )
        {
          pos++;
        }


        /*
           Value must be 0 or 1.
        */

        if (
          pos >=
            (int)response.length()
          ||
          (
            response[pos] != '0'
            &&
            response[pos] != '1'
          )
        )
        {
          allFound = false;

          break;
        }


        values[i] =
            response[pos] - '0';
      }


      /* ====================================================
         UPDATE OUTPUTS
         ==================================================== */

      if (allFound)
      {

        Serial.println();

        Serial.println(
          "Pin status updated:"
        );


        for (
          int i = 0;
          i < 8;
          i++
        )
        {

          digitalWrite(
            pins[i],
            values[i] == 1
              ? HIGH
              : LOW
          );


          Serial.print(
            "D"
          );

          Serial.print(
            i + 1
          );

          Serial.print(
            " = "
          );

          Serial.println(
            values[i]
          );
        }
      }
      else
      {

        Serial.println();

        Serial.println(
          "ERROR: Could not read "
          "all D1-D8 values."
        );
      }

    }
    else
    {

      Serial.println();

      Serial.println(
        "Server returned an HTTP error."
      );

      /*
         Safety:
         If server returns an HTTP error,
         turn outputs OFF.
      */

      turnAllOutputsOff();
    }

  }
  else
  {

    Serial.print(
      "HTTP request failed: "
    );

    Serial.println(
      https.errorToString(
        httpCode
      )
    );

    /*
       Safety:
       If communication fails, turn outputs OFF.
    */

    turnAllOutputsOff();
  }


  https.end();
}


/* ============================================================
   SETUP
   ============================================================ */

void setup()
{
  Serial.begin(
    115200
  );

  delay(300);


  Serial.println();

  Serial.println(
    "=========================================="
  );

  Serial.println(
    "ESP8266 ESP-SWITCH5 REMOTE CONTROLLER"
  );

  Serial.println(
    "=========================================="
  );


  Serial.print(
    "Controller ID: "
  );

  Serial.println(
    CONTROLLER_ID
  );


  Serial.print(
    "Device Token: "
  );

  Serial.println(
    DEVICE_TOKEN
  );


  /* ========================================================
     CONFIGURE D1-D8
     ======================================================== */

  for (
    int i = 0;
    i < 8;
    i++
  )
  {
    pinMode(
      pins[i],
      OUTPUT
    );

    /*
       Start safely OFF.
    */

    digitalWrite(
      pins[i],
      LOW
    );
  }


  /* ========================================================
     START WIFI
     ======================================================== */

  startWiFi();


  Serial.println();

  Serial.println(
    "Startup complete."
  );
}


/* ============================================================
   MAIN LOOP
   ============================================================ */

void loop()
{
  /* --------------------------------------------------------
     Maintain Wi-Fi automatically
     -------------------------------------------------------- */

  maintainWiFi();


  unsigned long now =
      millis();


  /* --------------------------------------------------------
     Poll remote server every 3 seconds
     -------------------------------------------------------- */

  if (
    WiFi.status() == WL_CONNECTED
    &&
    now - lastPoll >=
      POLL_INTERVAL
  )
  {

    lastPoll = now;

    getControllerData();
  }


  delay(10);
}
