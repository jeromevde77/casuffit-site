-- Migration : tracking des visites depuis flyers / QR codes
-- À exécuter une fois dans phpMyAdmin

CREATE TABLE IF NOT EXISTS landing_stats (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source VARCHAR(64) NOT NULL DEFAULT 'direct',
  campaign VARCHAR(64) DEFAULT NULL,
  lang ENUM('fr','nl') NOT NULL DEFAULT 'fr',
  visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash VARCHAR(64) DEFAULT NULL,
  INDEX idx_source (source),
  INDEX idx_campaign (campaign),
  INDEX idx_date (visited_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vue agrégée pour le dashboard admin
-- (Décommenter si tu veux une vue persistante en BDD)
-- CREATE OR REPLACE VIEW landing_stats_daily AS
-- SELECT
--   DATE(visited_at) AS jour,
--   source,
--   campaign,
--   lang,
--   COUNT(*) AS visites,
--   COUNT(DISTINCT ip_hash) AS visiteurs_uniques
-- FROM landing_stats
-- GROUP BY DATE(visited_at), source, campaign, lang;
