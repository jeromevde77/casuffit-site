-- Migration : mode maintenance
INSERT INTO site_config (cle, valeur, label, groupe) VALUES
('maintenance_mode',    '0',                          'Mode maintenance actif',   'maintenance'),
('maintenance_code',    'casuffit2026',               'Code de bypass secret',    'maintenance'),
('maintenance_titre',   'Site en construction',       'Titre page maintenance',   'maintenance'),
('maintenance_message', 'Nous travaillons à l\'amélioration du site.\nRevenez bientôt !', 'Message page maintenance', 'maintenance')
ON DUPLICATE KEY UPDATE label=VALUES(label), groupe=VALUES(groupe);
