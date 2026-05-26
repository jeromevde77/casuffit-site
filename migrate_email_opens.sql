-- migrate_email_opens.sql — Tracking d'ouverture des emails par campagne
-- À exécuter une fois dans phpMyAdmin

CREATE TABLE IF NOT EXISTS email_opens (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    campagne      VARCHAR(64)  NOT NULL,           -- slug du template ou nom de campagne
    email         VARCHAR(190) NOT NULL,           -- destinataire
    token         VARCHAR(64)  NOT NULL,           -- identifiant unique de l'envoi (anti-doublon)
    premiere_ouverture DATETIME DEFAULT NULL,      -- 1re ouverture détectée
    derniere_ouverture DATETIME DEFAULT NULL,      -- dernière ouverture
    nb_ouvertures INT NOT NULL DEFAULT 0,          -- nombre total de chargements du pixel
    cree_le       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_token (token),
    KEY idx_campagne (campagne),
    KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
