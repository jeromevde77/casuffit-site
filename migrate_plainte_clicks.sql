-- Migration : tracking des clics sur le bouton plainte
CREATE TABLE IF NOT EXISTS plainte_clicks (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  clicked_at  DATETIME NOT NULL DEFAULT NOW(),
  member_id   INT NULL,
  is_membre   TINYINT(1) NOT NULL DEFAULT 0,
  source      VARCHAR(30) NOT NULL DEFAULT 'piste_meteo',
  alert_level VARCHAR(20) NULL COMMENT 'hors_prs | dans_prs',
  ip_hash     VARCHAR(64) NULL COMMENT 'SHA-256 de l IP pour dédoublonnage (RGPD-safe)',
  INDEX idx_date   (clicked_at),
  INDEX idx_source (source),
  INDEX idx_membre (is_membre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
