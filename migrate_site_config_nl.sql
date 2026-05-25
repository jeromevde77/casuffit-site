-- Ajoute la colonne valeur_nl à site_config pour les bandeaux/textes traduisibles en néerlandais
ALTER TABLE `site_config` ADD COLUMN `valeur_nl` TEXT NULL AFTER `valeur`;
