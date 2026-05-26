-- migrate_member_password.sql
-- Ajoute la possibilité pour un membre de définir un mot de passe,
-- en complément du lien magique (les deux méthodes de connexion cohabitent).
-- À lancer UNE FOIS dans phpMyAdmin (base pistecaskznew).
--
-- NULL = aucun mot de passe défini -> seul le lien magique fonctionne pour ce membre.

ALTER TABLE `members`
  ADD COLUMN `password_hash` VARCHAR(255) DEFAULT NULL;
