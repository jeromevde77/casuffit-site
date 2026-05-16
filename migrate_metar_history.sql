-- ═══════════════════════════════════════════════════════════════
--  Migration : table metar_history — historique vent EBBR
--  À exécuter dans phpMyAdmin
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `metar_history` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `obs_time`      DATETIME        NOT NULL COMMENT 'Heure observation METAR (UTC)',
  `saved_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Heure enregistrement',
  `metar_raw`     VARCHAR(255)    NOT NULL DEFAULT '' COMMENT 'METAR brut complet',
  `wind_dir`      SMALLINT UNSIGNED NULL    COMMENT 'Direction vent (°), NULL si variable',
  `wind_speed`    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Vitesse vent (kt)',
  `wind_gust`     TINYINT UNSIGNED NULL    COMMENT 'Rafales (kt), NULL si pas de rafale',
  `wind_variable` TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 si vent variable',
  `temp`          TINYINT          NULL    COMMENT 'Température (°C)',
  `qnh`           SMALLINT UNSIGNED NULL   COMMENT 'QNH (hPa)',
  `visib_m`       SMALLINT UNSIGNED NULL   COMMENT 'Visibilité (m)',
  `ceiling_ft`    SMALLINT UNSIGNED NULL   COMMENT 'Plafond (ft), NULL si CAVOK',
  `runways`       VARCHAR(30)     NOT NULL DEFAULT '' COMMENT 'Pistes en service ex: 25R,25L',
  `prs_active`    TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 si schéma PRS actif (AIP actuel)',
  `prs_2013`      TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 si schéma PRS actif (AIP 2013)',
  `tw_25`         FLOAT           NULL    COMMENT 'Vent arrière piste 25 (kt)',
  `xw_25`         FLOAT           NULL    COMMENT 'Vent traversier piste 25 (kt)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obs_time` (`obs_time`),
  KEY `idx_saved_at` (`saved_at`),
  KEY `idx_prs` (`prs_active`, `obs_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historique METAR EBBR — enregistrement toutes les 30 min';

-- ── Ajout colonne IRM gust ────────────────────────────────────────────────
ALTER TABLE `metar_history`
  ADD COLUMN `irm_gust` FLOAT NULL COMMENT 'Rafale IRM (kt) — wind_peak_speed opendata.meteo.be'
  AFTER `wind_gust`;
