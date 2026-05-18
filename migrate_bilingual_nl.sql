-- ═══════════════════════════════════════════════════════════════════════
--  Migration : ajout du néerlandais (NL)
--  À exécuter dans phpMyAdmin sur la base pistecaskznew
--  Idempotent : les colonnes _nl ne sont ajoutées que si absentes
-- ═══════════════════════════════════════════════════════════════════════

-- ── Pages : titre, contenu, meta_description en NL ──────────────────────
ALTER TABLE `pages`
  ADD COLUMN `titre_nl`            VARCHAR(255) DEFAULT NULL AFTER `titre`,
  ADD COLUMN `contenu_nl`          LONGTEXT     DEFAULT NULL AFTER `contenu`,
  ADD COLUMN `meta_description_nl` VARCHAR(255) DEFAULT NULL AFTER `meta_description`,
  ADD COLUMN `nl_status`           ENUM('vide','auto','relu') NOT NULL DEFAULT 'vide'
                                                 COMMENT 'État traduction NL';

-- ── Widgets : titre + description en NL ─────────────────────────────────
ALTER TABLE `widgets`
  ADD COLUMN `titre_nl`       VARCHAR(100) DEFAULT NULL AFTER `titre`,
  ADD COLUMN `description_nl` VARCHAR(255) DEFAULT NULL AFTER `description`,
  ADD COLUMN `nl_status`      ENUM('vide','auto','relu') NOT NULL DEFAULT 'vide';

-- ── News : titre + accroche + contenu en NL ────────────────────────────
ALTER TABLE `news`
  ADD COLUMN `titre_nl`    VARCHAR(255) DEFAULT NULL AFTER `titre`,
  ADD COLUMN `accroche_nl` TEXT         DEFAULT NULL AFTER `accroche`,
  ADD COLUMN `contenu_nl`  LONGTEXT     DEFAULT NULL AFTER `contenu`,
  ADD COLUMN `nl_status`   ENUM('vide','auto','relu') NOT NULL DEFAULT 'vide';

-- ── Site_config : valeur NL pour les chaînes éditables ──────────────────
ALTER TABLE `site_config`
  ADD COLUMN `valeur_nl` TEXT DEFAULT NULL AFTER `valeur`;
