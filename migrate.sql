-- ═══════════════════════════════════════════════════════════════════════
--  migrate.sql — ça suffit ! ASBL
--  À exécuter sur une BDD EXISTANTE pour la mettre à jour
--  Compatible OVH MySQL (pas de IF NOT EXISTS sur ALTER TABLE)
--  Chaque ALTER est séparé — ignorer les erreurs "Duplicate column"
--  Dernière mise à jour : 2026-05-07
-- ═══════════════════════════════════════════════════════════════════════

-- ── TABLE pages ──────────────────────────────────────────────────────────
ALTER TABLE `pages` ADD COLUMN `show_news`       TINYINT(1)   DEFAULT 0      AFTER `contenu`;
ALTER TABLE `pages` ADD COLUMN `show_donation`   TINYINT(1)   DEFAULT 0      AFTER `show_news`;
ALTER TABLE `pages` ADD COLUMN `css_class`       VARCHAR(50)  DEFAULT NULL   AFTER `icone`;
ALTER TABLE `pages` ADD COLUMN `lien_url`        VARCHAR(255) DEFAULT NULL   AFTER `css_class`;
ALTER TABLE `pages` ADD COLUMN `menu_position`   VARCHAR(20)  DEFAULT 'all'  AFTER `dans_menu`;
ALTER TABLE `pages` ADD COLUMN `affichage_menu`  VARCHAR(20)  DEFAULT 'texte' AFTER `menu_position`;

-- ── TABLE subscribers ────────────────────────────────────────────────────
ALTER TABLE `subscribers` ADD COLUMN `adresse`                VARCHAR(255) DEFAULT NULL AFTER `commune`;
ALTER TABLE `subscribers` ADD COLUMN `source_import`          VARCHAR(50)  DEFAULT NULL AFTER `source`;
ALTER TABLE `subscribers` ADD COLUMN `soutien_action`         TINYINT(1)   DEFAULT 0   AFTER `benevole`;
ALTER TABLE `subscribers` ADD COLUMN `date_naissance`         DATE         DEFAULT NULL AFTER `telephone`;
ALTER TABLE `subscribers` ADD COLUMN `notes`                  TEXT         DEFAULT NULL AFTER `commune`;
ALTER TABLE `subscribers` ADD COLUMN `email_bienvenue_envoye` TINYINT(1)   DEFAULT 0   AFTER `source_import`;
ALTER TABLE `subscribers` ADD COLUMN `email_bienvenue_date`   DATETIME     DEFAULT NULL AFTER `email_bienvenue_envoye`;

-- ── TABLE members ────────────────────────────────────────────────────────
ALTER TABLE `members` ADD COLUMN `adresse`            VARCHAR(255) DEFAULT NULL AFTER `commune`;
ALTER TABLE `members` ADD COLUMN `code_membre`        VARCHAR(20)  DEFAULT NULL;
ALTER TABLE `members` ADD COLUMN `ogm`                VARCHAR(20)  DEFAULT NULL;
ALTER TABLE `members` ADD COLUMN `token_magic`        VARCHAR(64)  DEFAULT NULL;
ALTER TABLE `members` ADD COLUMN `token_magic_expiry` DATETIME     DEFAULT NULL;
ALTER TABLE `members` ADD COLUMN `token_unsub`        VARCHAR(64)  DEFAULT NULL;
ALTER TABLE `members` ADD COLUMN `subscriber_id`      INT UNSIGNED DEFAULT NULL;
ALTER TABLE `members` ADD COLUMN `derniere_connexion` DATETIME     DEFAULT NULL;

-- ── TABLE member_dons ────────────────────────────────────────────────────
ALTER TABLE `member_dons` ADD COLUMN `ogm_don` VARCHAR(20) DEFAULT NULL AFTER `communication`;

-- ── TABLE page_widgets ───────────────────────────────────────────────────
ALTER TABLE `page_widgets` ADD COLUMN `position` ENUM('droite','gauche') DEFAULT 'droite' AFTER `ordre`;

-- ── NOUVELLE TABLE imports_wix ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `imports_wix` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename`    VARCHAR(255) NOT NULL,
  `date_import` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nb_total`    INT DEFAULT 0,
  `nb_importes` INT DEFAULT 0,
  `nb_doublons` INT DEFAULT 0,
  `nb_erreurs`  INT DEFAULT 0,
  `importe_par` VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PAGES — données ──────────────────────────────────────────────────────
UPDATE `pages` SET `css_class` = 'nav-cta' WHERE `slug` = 'soutenir' AND (`css_class` IS NULL OR `css_class` = '');
UPDATE `pages` SET `show_news` = 1 WHERE `slug` = 'actualites';
UPDATE `pages` SET `show_donation` = 1 WHERE `slug` = 'soutenir';
UPDATE `pages` SET `lien_url` = '#don' WHERE `slug` = 'soutenir' AND (`lien_url` IS NULL OR `lien_url` = '');

INSERT INTO `pages` (slug, titre, icone, ordre, visible, dans_menu, menu_position, css_class, lien_url, affichage_menu, updated_by)
VALUES
  ('newsletter',    'Newsletter',     '✉',  70, 1, 1, 'all',    '',           NULL,               'texte',       'admin'),
  ('espace-membre', 'Mon espace',     '👤', 80, 1, 1, 'all',    'nav-membre', 'membre/login.php', 'icone_texte', 'admin'),
  ('admin',         'Administration', '⚙',  99, 1, 1, 'header', 'nav-admin',  'admin/',           'icone',       'admin')
ON DUPLICATE KEY UPDATE
  dans_menu      = VALUES(dans_menu),
  menu_position  = VALUES(menu_position),
  css_class      = VALUES(css_class),
  lien_url       = VALUES(lien_url),
  affichage_menu = VALUES(affichage_menu);

-- ── WIDGET FACEBOOK ──────────────────────────────────────────────────────
INSERT IGNORE INTO `widgets` (slug, titre, description, icone, actif)
VALUES ('facebook', 'Page Facebook', 'Timeline et bouton J\'aime de la page Facebook', '📘', 1);

INSERT IGNORE INTO `pages` (slug, titre, icone, dans_menu, menu_position, affichage_menu, visible, ordre)
VALUES ('facebook', 'Notre Facebook', '📘', 1, 'partout', 'texte', 1, 99);

INSERT IGNORE INTO `page_widgets` (page_slug, widget_slug, ordre, position)
VALUES ('facebook', 'facebook', 1, 'droite');
