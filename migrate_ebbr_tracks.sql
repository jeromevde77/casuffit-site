CREATE TABLE IF NOT EXISTS `ebbr_runway_tracks` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `track_date`    DATE         NOT NULL,
  `callsign`      VARCHAR(16)  NOT NULL DEFAULT '',
  `icao24`        VARCHAR(8)   NOT NULL,
  `runway`        ENUM('01','07') NOT NULL,
  `waypoints`     MEDIUMTEXT   NOT NULL COMMENT 'JSON [{lat,lon,alt,gnd},...]',
  `arr_timestamp` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_flight` (`track_date`, `icao24`),
  INDEX `idx_date`   (`track_date`),
  INDEX `idx_runway` (`runway`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Traces radar atterrissages EBBR pistes 01 et 07';
