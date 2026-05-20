CREATE TABLE IF NOT EXISTS `email_templates` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug`        VARCHAR(64)  NOT NULL UNIQUE,
  `label`       VARCHAR(200) NOT NULL,
  `sujet_fr`    VARCHAR(255) NOT NULL DEFAULT '',
  `sujet_nl`    VARCHAR(255) NOT NULL DEFAULT '',
  `contenu_fr`  MEDIUMTEXT,
  `contenu_nl`  MEDIUMTEXT,
  `variables`   TEXT COMMENT 'JSON : liste des variables disponibles',
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
