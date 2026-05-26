-- migrate_import_csv.sql  (version MySQL standard, sans IF NOT EXISTS)
-- Support de l'import CSV bancaire (Belfius) avec rapprochement + déduplication.
-- À lancer dans phpMyAdmin (base pistecaskznew). Lance les statements UN PAR UN.
--
--   ref_import : empreinte (md5) de la ligne bancaire -> anti-doublon au ré-import.
--   note       : trace de l'origine du don (déjà utilisée par l'import CODA).

-- 1) Colonne "note"
--    Si MySQL répond « Duplicate column name 'note' » -> la colonne existe déjà,
--    c'est OK : ignore cette ligne et passe à la 2).
ALTER TABLE `member_dons` ADD COLUMN `note` VARCHAR(255) DEFAULT NULL;

-- 2) Colonne empreinte anti-doublon (nouvelle, doit passer sans erreur)
ALTER TABLE `member_dons` ADD COLUMN `ref_import` VARCHAR(40) DEFAULT NULL;

-- 3) Index pour accélérer la déduplication (optionnel mais recommandé)
CREATE INDEX `idx_ref_import` ON `member_dons` (`ref_import`);
