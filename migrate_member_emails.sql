-- Historique des e-mails envoyés à un membre depuis l'outil admin.
CREATE TABLE IF NOT EXISTS `member_emails` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`  INT UNSIGNED NOT NULL,
  `sujet`      VARCHAR(255) NOT NULL,
  `message`    TEXT,
  `envoye_par` VARCHAR(100) DEFAULT NULL,
  `statut`     ENUM('envoye','echec') NOT NULL DEFAULT 'envoye',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
