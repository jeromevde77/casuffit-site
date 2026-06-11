-- Suivi de l'e-mail de remerciement envoyé au donateur lorsqu'un don est confirmé.
-- Idempotence : un don n'est jamais remercié deux fois.
ALTER TABLE `member_dons`
  ADD COLUMN IF NOT EXISTS `merci_envoye` TINYINT(1) NOT NULL DEFAULT 0 AFTER `statut`,
  ADD COLUMN IF NOT EXISTS `merci_date`   DATETIME     DEFAULT NULL    AFTER `merci_envoye`;
