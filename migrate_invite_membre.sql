-- Migration : ajout colonne invitation membre dans subscribers
ALTER TABLE `subscribers`
  ADD COLUMN IF NOT EXISTS `invite_membre_token`    VARCHAR(64)  DEFAULT NULL AFTER `token_unsub`,
  ADD COLUMN IF NOT EXISTS `invite_membre_sent_at`  DATETIME     DEFAULT NULL AFTER `invite_membre_token`,
  ADD COLUMN IF NOT EXISTS `invite_membre_accepted` TINYINT(1)   DEFAULT 0    AFTER `invite_membre_sent_at`;
