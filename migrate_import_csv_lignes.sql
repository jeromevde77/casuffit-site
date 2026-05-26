-- migrate_import_csv_lignes.sql
-- Table de staging persistante pour les paiements CSV non réconciliés.
-- Les paiements non confirmés lors d'un import y sont conservés entre les sessions.
-- Permet de les retravailler ultérieurement (après inscription d'un nouveau membre, etc.)
-- Lancer dans phpMyAdmin (base pistecaskznew). CREATE TABLE IF NOT EXISTS = MySQL standard.
--
-- Statuts :
--   en_attente  — non encore réconcilié, réapparaît dans les imports suivants
--   ignore      — écarté définitivement (frais bancaires, erreur…) — plus jamais affiché
--   reconcilie  — traité, enregistré dans member_dons

CREATE TABLE IF NOT EXISTS `import_csv_lignes` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `ref_import`          VARCHAR(40)     NOT NULL,
  `date_virement`       DATE            DEFAULT NULL,
  `montant`             DECIMAL(10,2)   NOT NULL,
  `contrepartie_iban`   VARCHAR(40)     DEFAULT NULL,
  `contrepartie_nom`    VARCHAR(200)    DEFAULT NULL,
  `communication`       VARCHAR(255)    DEFAULT NULL,
  `description`         TEXT            DEFAULT NULL,
  `tier`                VARCHAR(20)     DEFAULT 'aucun' COMMENT 'ogm / iban / nom / aucun',
  `suggested_member_id` INT             DEFAULT NULL,
  `statut`              ENUM('en_attente','ignore','reconcilie') NOT NULL DEFAULT 'en_attente',
  `nom_fichier`         VARCHAR(255)    DEFAULT NULL,
  `date_premier_vu`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_reconciliee`    DATETIME        DEFAULT NULL,
  UNIQUE KEY `uq_ref`    (`ref_import`),
  KEY       `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Paiements CSV non réconciliés — persistance inter-sessions';
