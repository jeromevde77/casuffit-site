-- Migration : champs manquants table members
-- rgpd_accepte existe déjà, on l'ignore

ALTER TABLE `members` ADD COLUMN `rgpd_date`            DATETIME     DEFAULT NULL AFTER `rgpd_accepte`;
ALTER TABLE `members` ADD COLUMN `donnees_verifiees_at` DATETIME     DEFAULT NULL AFTER `rgpd_date`;
ALTER TABLE `members` ADD COLUMN `compte_supprime`      TINYINT(1)   DEFAULT 0    AFTER `donnees_verifiees_at`;
ALTER TABLE `members` ADD COLUMN `rue`                  VARCHAR(200) DEFAULT NULL AFTER `adresse`;
ALTER TABLE `members` ADD COLUMN `numero`               VARCHAR(20)  DEFAULT NULL AFTER `rue`;
ALTER TABLE `members` ADD COLUMN `boite`                VARCHAR(20)  DEFAULT NULL AFTER `numero`;
ALTER TABLE `members` ADD COLUMN `code_postal`          VARCHAR(10)  DEFAULT NULL AFTER `boite`;
ALTER TABLE `members` ADD COLUMN `iban_membre`          VARCHAR(50)  DEFAULT NULL AFTER `code_membre`;
ALTER TABLE `members` ADD COLUMN `email_nouveau`        VARCHAR(255) DEFAULT NULL AFTER `email`;
ALTER TABLE `members` ADD COLUMN `token_email_change`   VARCHAR(64)  DEFAULT NULL AFTER `email_nouveau`;
