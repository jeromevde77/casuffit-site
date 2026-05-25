-- Ajoute un champ pour déployer une actualité par défaut, indépendamment de l'épinglage
ALTER TABLE `news` ADD COLUMN `deploye_defaut` TINYINT(1) NOT NULL DEFAULT 0 AFTER `epingle`;
