-- ──────────────────────────────────────────────────────────────────────
-- migrate_reseaux_sociaux.sql
-- Ajoute les clés Facebook / Instagram / WhatsApp dans site_config
-- À exécuter UNE fois dans phpMyAdmin sur la BDD pistecaskznew
-- Idempotent : si les clés existent déjà, mise à jour du label/groupe
-- (mais PAS écrasement de la valeur si elle est déjà saisie)
-- ──────────────────────────────────────────────────────────────────────

INSERT INTO `site_config` (`cle`, `valeur`, `label`, `groupe`) VALUES
  ('facebook_url',  'https://www.facebook.com/Piste01casuffit', 'URL page Facebook',  'reseaux'),
  ('instagram_url', '',                                          'URL Instagram',      'reseaux'),
  ('whatsapp_url',  '',                                          'Lien WhatsApp',      'reseaux')
ON DUPLICATE KEY UPDATE
  label  = VALUES(label),
  groupe = VALUES(groupe);

-- Vérification
SELECT cle, valeur, label, groupe
FROM site_config
WHERE cle IN ('facebook_url','instagram_url','whatsapp_url');
