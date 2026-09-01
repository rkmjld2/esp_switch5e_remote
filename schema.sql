CREATE DATABASE IF NOT EXISTS esp_switch5;
USE esp_switch5;

CREATE TABLE IF NOT EXISTS controllers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    controller_id VARCHAR(50) NOT NULL UNIQUE,
    device_token VARCHAR(100) NOT NULL UNIQUE,
    customer_name VARCHAR(100) DEFAULT '',
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen DATETIME DEFAULT NULL
);

INSERT INTO controllers
(
    controller_id,
    device_token,
    customer_name,
    active
)
VALUES
(
    'ESP0001',
    'ESP0001-TOKEN-2026-A7K9X2',
    'Test Customer',
    1
);
/////////
use esp_switch5;
ALTER TABLE controllers
ADD COLUMN start_time DATETIME NULL,
ADD COLUMN end_time DATETIME NULL;


UPDATE controllers
SET
    start_time = '2026-08-19 13:30:00',
    end_time   = '2026-08-19 18:00:00'
WHERE controller_id = 'ESP0001';
///////////////
USE esp_switch5;

CREATE TABLE IF NOT EXISTS esp_control (
    id INT AUTO_INCREMENT PRIMARY KEY,
    controller_id VARCHAR(50) NOT NULL UNIQUE,

    D1 TINYINT(1) NOT NULL DEFAULT 0,
    D2 TINYINT(1) NOT NULL DEFAULT 0,
    D3 TINYINT(1) NOT NULL DEFAULT 0,
    D4 TINYINT(1) NOT NULL DEFAULT 0,
    D5 TINYINT(1) NOT NULL DEFAULT 0,
    D6 TINYINT(1) NOT NULL DEFAULT 0,
    D7 TINYINT(1) NOT NULL DEFAULT 0,
    D8 TINYINT(1) NOT NULL DEFAULT 0,

    CONSTRAINT fk_esp_control_controller
        FOREIGN KEY (controller_id)
        REFERENCES controllers(controller_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


/////////////////

INSERT INTO esp_control
(
    controller_id,
    D1,
    D2,
    D3,
    D4,
    D5,
    D6,
    D7,
    D8
)
VALUES
(
    'ESP0001',
    0,
    0,
    0,
    0,
    0,
    0,
    0,
    0
);


esp_switch5
│
├── controllers
│   ├── id
│   ├── controller_id
│   ├── device_token
│   ├── customer_name
│   ├── active
│   └── last_seen
│
└── esp_control
    ├── id
    ├── controller_id
    ├── D1
    ├── D2
    ├── D3
    ├── D4
    ├── D5
    ├── D6
    ├── D7
    └── D8
