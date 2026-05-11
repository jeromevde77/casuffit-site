-- ═══════════════════════════════════════════════════════════════════════
--  install.sql — ça suffit ! ASBL — Installation complète from scratch
--  1. Créer une base de données vide dans phpMyAdmin
--  2. Exécuter ce fichier
--  3. Exécuter inject_content.sql
--  Dernière mise à jour : 2026-05-06
-- ═══════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── PAGES (CMS) ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pages` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`             VARCHAR(100) NOT NULL,
  `titre`            VARCHAR(255) NOT NULL,
  `contenu`          LONGTEXT,
  `meta_description` VARCHAR(255) DEFAULT NULL,
  `icone`            VARCHAR(10)  DEFAULT NULL,
  `css_class`        VARCHAR(50)  DEFAULT NULL,
  `lien_url`         VARCHAR(255) DEFAULT NULL,
  `dans_menu`        TINYINT(1)   DEFAULT 1,
  `menu_position`    VARCHAR(20)  DEFAULT 'all',
  `affichage_menu`   VARCHAR(20)  DEFAULT 'texte',
  `visible`          TINYINT(1)   DEFAULT 1,
  `ordre`            INT          DEFAULT 10,
  `updated_by`       VARCHAR(100) DEFAULT NULL,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── WIDGETS ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `widgets` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(50)  NOT NULL,
  `titre`       VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `icone`       VARCHAR(10)  DEFAULT NULL,
  `actif`       TINYINT(1)   DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── WIDGETS PAR PAGE ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `page_widgets` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_slug`   VARCHAR(100) NOT NULL,
  `widget_slug` VARCHAR(50)  NOT NULL,
  `ordre`       INT          DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_widget` (`page_slug`, `widget_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ACTUALITÉS ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `news` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titre`            VARCHAR(255) NOT NULL,
  `accroche`       TEXT         DEFAULT NULL,
  `contenu`          LONGTEXT,
  `image_url`        VARCHAR(500) DEFAULT NULL,
  `statut`           ENUM('brouillon','publie','archive') DEFAULT 'brouillon',
  `epingle`          TINYINT(1)   DEFAULT 0,
  `date_publication` DATETIME     DEFAULT NULL,
  `date_creation`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`       VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CONFIGURATION SITE ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `site_config` (
  `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cle`    VARCHAR(100) NOT NULL,
  `valeur` LONGTEXT,
  `label`  VARCHAR(255) DEFAULT NULL,
  `groupe` VARCHAR(50)  DEFAULT 'general',
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── MÉDIAS ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `medias` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(255) NOT NULL,
  `fichier`     VARCHAR(255) NOT NULL,
  `type`        VARCHAR(50)  DEFAULT NULL,
  `taille`      INT          DEFAULT 0,
  `uploaded_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ABONNÉS NEWSLETTER ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `subscribers` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`                   VARCHAR(255) NOT NULL,
  `prenom`                  VARCHAR(100) DEFAULT NULL,
  `nom`                     VARCHAR(100) DEFAULT NULL,
  `adresse`                 VARCHAR(255) DEFAULT NULL,
  `commune`                 VARCHAR(100) DEFAULT NULL,
  `telephone`               VARCHAR(30)  DEFAULT NULL,
  `benevole`                TINYINT(1)   DEFAULT 0,
  `soutien_action`    TINYINT(1)   DEFAULT 0,
  `date_naissance`          DATE         DEFAULT NULL,
  `notes`                   TEXT         DEFAULT NULL,
  `rgpd_accepte`            TINYINT(1)   DEFAULT 0,
  `statut`                  ENUM('en_attente','actif','desabonne') DEFAULT 'en_attente',
  `source`                  VARCHAR(50)  DEFAULT NULL,
  `source_import`           VARCHAR(50)  DEFAULT NULL,
  `email_bienvenue_envoye`  TINYINT(1)   DEFAULT 0,
  `email_bienvenue_date`    DATETIME     DEFAULT NULL,
  `token_confirm`           VARCHAR(64)  DEFAULT NULL,
  `token_unsub`             VARCHAR(64)  DEFAULT NULL,
  `date_inscription`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── NEWSLETTERS ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `newsletters` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sujet`        VARCHAR(255) NOT NULL,
  `contenu_html` LONGTEXT,
  `contenu_text` LONGTEXT,
  `statut`       ENUM('brouillon','envoi','envoye') DEFAULT 'brouillon',
  `nb_envoyes`   INT      DEFAULT 0,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`      DATETIME  DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── FILE D'ENVOI NEWSLETTER ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `send_queue` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `newsletter_id` INT UNSIGNED NOT NULL,
  `subscriber_id` INT UNSIGNED NOT NULL,
  `statut`        ENUM('en_attente','envoye','erreur') DEFAULT 'en_attente',
  `tentatives`    INT      DEFAULT 0,
  `envoye_at`     DATETIME DEFAULT NULL,
  `erreur_msg`    TEXT     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `newsletter_id` (`newsletter_id`),
  KEY `statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── MEMBRES ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `members` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`               VARCHAR(255) NOT NULL,
  `prenom`              VARCHAR(100) DEFAULT NULL,
  `nom`                 VARCHAR(100) DEFAULT NULL,
  `adresse`             VARCHAR(255) DEFAULT NULL,
  `commune`             VARCHAR(100) DEFAULT NULL,
  `telephone`           VARCHAR(30)  DEFAULT NULL,
  `code_membre`         VARCHAR(20)  DEFAULT NULL,
  `ogm`                 VARCHAR(20)  DEFAULT NULL,
  `newsletter`          TINYINT(1)   DEFAULT 1,
  `statut`              ENUM('actif','inactif','banni') DEFAULT 'actif',
  `token_magic`         VARCHAR(64)  DEFAULT NULL,
  `token_magic_expiry`  DATETIME     DEFAULT NULL,
  `token_unsub`         VARCHAR(64)  DEFAULT NULL,
  `subscriber_id`       INT UNSIGNED DEFAULT NULL,
  `derniere_connexion`  DATETIME     DEFAULT NULL,
  `date_inscription`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `code_membre` (`code_membre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── DONS MEMBRES ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `member_dons` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `member_id`     INT UNSIGNED  NOT NULL,
  `montant`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `ogm_don`       VARCHAR(20)   DEFAULT NULL,
  `communication` VARCHAR(100)  DEFAULT NULL,
  `statut`        ENUM('en_attente','confirme','annule') DEFAULT 'en_attente',
  `date_don`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── IMPORTS CODA ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `coda_imports` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename`        VARCHAR(255) NOT NULL,
  `date_import`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nb_transactions` INT DEFAULT 0,
  `nb_matches`      INT DEFAULT 0,
  `importe_par`     VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── IMPORTS WIX ───────────────────────────────────────────────────────────
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

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════
-- DONNÉES INITIALES
-- ═══════════════════════════════════════════════════════════════════════

-- ── Configuration site ────────────────────────────────────────────────────
INSERT INTO `site_config` (`cle`, `valeur`, `label`, `groupe`) VALUES
('iban',             'BE41 0689 0149 6910',          'IBAN',              'don'),
('bic',              'GKCCBEBB',                      'BIC',               'don'),
('beneficiaire',     'ca suffit ! ASBL',              'Bénéficiaire',      'don'),
('montant_objectif', '15000',                         'Objectif dons (€)', 'don'),
('montant_initial',  '0',                         'Montant initial (avant site)', 'don'),
('montant_recolte',  '0',                             'Récolté (€)',       'don'),
('don_texte',        'Frais judiciaires — Action en référé', 'Texte don',  'don'),
('annonce_active',   '1',                             'Annonce active',    'general'),
('annonce_texte',    'Piste 01 & UBCNA s unissent !', 'Texte annonce',     'general'),
('urgence_texte',    '',                              'Texte urgence',     'general')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);

-- ── Widgets disponibles ───────────────────────────────────────────────────
INSERT INTO `widgets` (`slug`, `titre`, `description`, `icone`, `actif`) VALUES
('donation_card',  'Carte de don',       'QR codes de paiement + devenir membre', '💳', 1),
('news',           'Actualités',         'Dernières actualités publiées',          '📰', 1),
('newsletter',     'Newsletter',         'Formulaire d inscription newsletter',    '✉',  1),
('progression',    'Barre de progression','Objectif et progression des dons',      '📊', 1)
ON DUPLICATE KEY UPDATE titre=VALUES(titre), description=VALUES(description);

-- ── Pages du menu ─────────────────────────────────────────────────────────
INSERT INTO `pages` (`slug`, `titre`, `icone`, `ordre`, `visible`, `dans_menu`, `menu_position`, `css_class`, `lien_url`, `affichage_menu`, `contenu`, `updated_by`) VALUES
('mobilisation',  'Mobilisation',          '📢', 10, 1, 1, 'all',    '',           NULL,               'texte',       NULL, NULL, NULL, 'admin'),
('pourquoi',      'Pourquoi la piste 01 ?','✈',  20, 1, 1, 'all',    '',           NULL,               'texte',       '', 'admin'),
('actualites',    'Actualités',            '📰', 25, 1, 1, 'all',    '',           NULL,               'texte',       '', 'admin'),
('informations',  'Informations',          '📋', 30, 1, 1, 'all',    '',           NULL,               'texte',       '', 'admin'),
('demandes',      'Nos demandes',          '🎯', 40, 1, 1, 'all',    '',           NULL,               'texte',       '', 'admin'),
('allies',        'Nos alliés',            '🤝', 50, 1, 1, 'all',    '',           NULL,               'texte',       '', 'admin'),
('soutenir',      'Nous soutenir',         '💶', 60, 1, 1, 'all',    'nav-cta',    '#don',             'texte',       '', 'admin'),
('newsletter',    'Newsletter',            '✉',  70, 1, 1, 'all',    '',           NULL,               'texte',       '', 'admin'),
('espace-membre', 'Mon espace',            '👤', 80, 1, 1, 'all',    'nav-membre', 'membre/login.php', 'icone_texte', '', 'admin'),
('admin',         'Administration',        '⚙',  99, 1, 1, 'header', 'nav-admin',  'admin/',           'icone',       '', 'admin')
ON DUPLICATE KEY UPDATE
  titre=VALUES(titre), icone=VALUES(icone), ordre=VALUES(ordre),
  dans_menu=VALUES(dans_menu), menu_position=VALUES(menu_position),
  css_class=VALUES(css_class), lien_url=VALUES(lien_url),
  affichage_menu=VALUES(affichage_menu);

-- ── Widgets par défaut par page ───────────────────────────────────────────
-- La progression est un widget indépendant de la donation card
-- Chaque page peut choisir ses widgets via admin/pages.php
INSERT INTO `page_widgets` (`page_slug`, `widget_slug`, `ordre`) VALUES
-- Mobilisation : progression SEULEMENT (pas la donation card)
('mobilisation', 'progression',   1),
-- Nous soutenir : donation card + progression
('soutenir',     'progression',   1),
('soutenir',     'donation_card', 2),
-- Actualités : bloc news
('actualites',   'news',          1),
-- Newsletter : formulaire
('newsletter',   'newsletter',    1)
ON DUPLICATE KEY UPDATE ordre=VALUES(ordre);

-- Zone header : widgets affichés sous le header sur toutes les pages
-- Ajouter ici les widgets à afficher globalement
-- Ex: INSERT INTO page_widgets (page_slug, widget_slug, ordre) VALUES ('__header__', 'progression', 1);


-- ── Config GA4 (ajout post-session) ───────────────────────────────────────
INSERT INTO `site_config` (`cle`, `valeur`, `label`, `groupe`) VALUES
('ga_id',          'G-7LKP0KC1SD',  'ID Google Analytics 4', 'general'),
('facebook_url',   'https://www.facebook.com/casuffit', 'URL Facebook', 'general'),
('instagram_url',  '', 'URL Instagram', 'general'),
('whatsapp_url',   '', 'URL WhatsApp',  'general')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);

-- ── Widgets vent (conditions-vent) ────────────────────────────────────────
INSERT INTO `widgets` (`slug`, `titre`, `description`, `icone`, `actif`) VALUES
('piste_meteo',     'Météo piste',      'METAR + analyse PRS',               '🌤', 1),
('historique_vent', 'Historique vent',  'Données IRM + analyse historique',  '📊', 1),
('rose_vents',      'Rose des vents',   'Rose des vents IRM station 6451',   '🌬', 1),
('facebook',        'Page Facebook',    'Widget page Facebook',              '📘', 1)
ON DUPLICATE KEY UPDATE titre=VALUES(titre), description=VALUES(description);

-- ── Page conditions-vent ──────────────────────────────────────────────────
INSERT INTO `pages` (`slug`, `titre`, `icone`, `ordre`, `visible`, `dans_menu`, `menu_position`, `css_class`, `lien_url`, `affichage_menu`, `contenu`, `updated_by`) VALUES
('conditions-vent', 'Conditions de vent', '🌬', 15, 1, 1, 'all', '', NULL, 'texte', '', 'admin')
ON DUPLICATE KEY UPDATE titre=VALUES(titre), icone=VALUES(icone), ordre=VALUES(ordre);

-- ── Widgets de la page conditions-vent ───────────────────────────────────
INSERT INTO `page_widgets` (`page_slug`, `widget_slug`, `ordre`) VALUES
('conditions-vent', 'piste_meteo',     1),
('conditions-vent', 'historique_vent', 2),
('conditions-vent', 'rose_vents',      3)
ON DUPLICATE KEY UPDATE ordre=VALUES(ordre);

-- ── Colonnes membres v2 ───────────────────────────────────────────────────
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `rgpd_date`            DATETIME     DEFAULT NULL AFTER `rgpd_accepte`;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `donnees_verifiees_at` DATETIME     DEFAULT NULL AFTER `rgpd_date`;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `compte_supprime`      TINYINT(1)   DEFAULT 0    AFTER `donnees_verifiees_at`;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `rue`                  VARCHAR(200) DEFAULT NULL AFTER `adresse`;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `numero`               VARCHAR(20)  DEFAULT NULL AFTER `rue`;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `boite`                VARCHAR(20)  DEFAULT NULL AFTER `numero`;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `code_postal`          VARCHAR(10)  DEFAULT NULL AFTER `boite`;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `iban_membre`          VARCHAR(50)  DEFAULT NULL AFTER `code_membre`;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `email_nouveau`        VARCHAR(255) DEFAULT NULL AFTER `email`;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `token_email_change`   VARCHAR(64)  DEFAULT NULL AFTER `email_nouveau`;
