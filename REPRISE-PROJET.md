# Reprise du projet casuffit.be

Document de passation, destiné à permettre à une autre personne (ou à un autre
compte Claude) de reprendre ce projet sans contexte préalable.

À lire **après** `CLAUDE.md`, qui décrit les conventions de code et d'architecture.
Ce fichier-ci couvre ce que `CLAUDE.md` ne dit pas : **les accès, l'état réel du
dépôt, la dette technique et les chantiers en cours**.

*Dernière mise à jour : 2 octobre 2026.*

---

## 1. Ce qu'il faut pour reprendre

Le projet dépend de **cinq accès distincts**. Sans eux, on peut lire le code mais
ni déployer, ni envoyer d'e-mail, ni consulter les données.

| Accès | À quoi il sert | Où |
|---|---|---|
| **GitHub** `jeromevde77/casuffit-site` | Code source, déploiement automatique | github.com |
| **FTP OVH** | Cible du déploiement (le site lui-même) | Espace client OVH |
| **phpMyAdmin OVH** | Base MySQL (migrations, vérifications) | Espace client OVH |
| **Brevo** | Envoi de tous les e-mails (API) | brevo.com |
| **Zone DNS du domaine** | SPF / DKIM / DMARC, délivrabilité | Registrar du domaine |

> Le compte Claude qui reprend doit avoir le dépôt GitHub attaché pour pouvoir
> lire et pousser du code.

---

## 2. Secrets et configuration

### `config.php` — hors dépôt, vit uniquement sur le serveur OVH

Il contient les identifiants base de données, `BREVO_API_KEY`, les constantes
SMTP, `CRON_SECRET`, `QUEUE_BATCH_SIZE`, ainsi que des fonctions partagées
(`cfg()`, `getDB()`, `requireAdmin()`, `log_msg()`…).

**Conséquence importante :** ce fichier n'est **jamais** visible depuis une session
Claude. `config.exemple.php` sert de modèle et documente les constantes attendues.
En cas de perte du serveur, il faut le reconstituer à partir de cet exemple.

### Secrets GitHub Actions

Quatre secrets, à recréer à l'identique en cas de transfert du dépôt :

- `FTP_HOST`
- `FTP_USER`
- `FTP_PASSWORD`
- `FTP_SERVER_DIR`

---

## 3. Déploiement

`git push origin main` déclenche `.github/workflows/deploy.yml`, qui synchronise
le dépôt vers OVH via `lftp`. Compter **40 à 60 secondes**.

Sont **exclus** du transfert : `config.php`, `.git`, `.github`, `*.sql`
(dont tous les `migrate_*.sql`), `README.md`, `INSTALL.md`, et le dossier
`medias/`.

### Trois pièges qui coûtent cher

1. **`lftp` saute les fichiers de taille identique**, malgré `--ignore-time`.
   Une correction qui ne change pas la taille du fichier (remplacer un caractère
   par un autre, par exemple une date « 4 » → « 7 ») **n'est jamais transférée**.
   *C'est arrivé en septembre 2026 et le bug a semblé persister côté serveur alors
   que le code était correct.* Parade : bumper un marqueur de version en tête de
   fichier (`/* fichier — v3 */`).

2. **Service Worker admin** : le SW met en cache `admin/dashboard.php`. Une
   opération ponctuelle en base ajoutée dans cette page peut ne jamais s'exécuter.
   Parade : créer un fichier `outils-xxx.php` **à la racine** (hors du scope `/admin/`
   du SW), protégé par `requireAdmin()`. `reset-sw.php` force la réinitialisation.

3. **`medias/` n'est pas déployé.** Les fichiers uploadés via Admin → Médias
   vivent uniquement sur le serveur. Pratique (ils ne transitent pas par le dépôt),
   mais ils sont absents de toute copie locale — ne jamais supposer leur présence.
   Les fichiers destinés à être déployés vont dans `assets/`.

---

## 4. Base de données

Tables principales : `members`, `member_dons`, `subscribers`, `newsletters`,
`send_queue`, `news`, `pages`, `widgets` / `page_widgets`, `site_config`,
`contacts`, `admin_users`, `email_opens`.

**Attention :** `install.sql` n'est **pas** le reflet fidèle de la base de
production. Des colonnes ont été ajoutées au fil des migrations
(`migrate_*.sql`, 19 fichiers, **non déployés** — à exécuter manuellement dans
phpMyAdmin). D'autres colonnes ont été créées directement par les pages admin.

En cas de doute sur un schéma, la source de vérité est
`SHOW COLUMNS FROM <table>` en production, pas `install.sql`.

Deux pages admin **auto-provisionnent** leurs tables au premier chargement :
`admin/bilan.php` (`bilan_annuel`, `depenses`) et la colonne `news.ordre_une`
créée par `admin/news.php`.

---

## 5. Tâches planifiées

| Cron | Rôle |
|---|---|
| `cron/send_queue.php` | Vide la file d'envoi des newsletters, par lots |
| `cron/ebbr_tracks.php` | Récupère les traces de vol EBBR |
| `cron/save_metar.php` | Archive les observations météo |

`send_queue.php` accepte trois déclencheurs : CLI (cron OVH), `127.0.0.1`, ou une
URL avec `?secret=CRON_SECRET`. Le bouton **« Traiter la file maintenant »** de
l'admin passe par `/admin/send_now.php`, qui l'inclut en interne — nécessaire car
le `.htaccess` **interdit l'accès web direct à `/cron/`** (une requête y est
renvoyée vers la page d'accueil via `ErrorDocument 403`).

---

## 6. ⚠️ Dette à traiter en priorité

**Deux fichiers `outils-*.php` subsistent à la racine du dépôt et en production.**

```
outils-publier-communique.php    outils-publier-presse.php
```

Chacun exécute des opérations en base et n'est protégé que par
`$_SESSION['admin_logged_in']`. `CLAUDE.md` impose de les **supprimer après
usage** : ces deux-là sont encore à utiliser (voir §7), puis à supprimer.

Dix autres outils devenus obsolètes ont été supprimés le 2 octobre 2026
(migration Wix, brouillons de newsletters, diagnostics, publications ponctuelles).

---

## 7. Chantiers en cours

### À finir
- **`outils-publier-communique.php`** : publie le communiqué du 7 septembre et
  crée/met à jour le brouillon de newsletter. Idempotent.
- **`outils-publier-presse.php`** : publie 4 actualités (revue de presse +
  communiqué UBCNA du 1er octobre). Idempotent.
- **Newsletter « Survol : une décision est attendue le 1er octobre »** : brouillon
  à relire dans Admin → Rédaction, puis envoyer. Deux appels : rejoindre l'action
  **nominativement** (case à cocher de l'espace membre) et **don**.

### En attente d'une décision
- **Refonte de la sidebar admin** (sections repliables) et **du Bilan annuel**
  (onglets Rapport / Finances) : maquettes produites, **non validées**, rien n'a
  été codé.
- **Traduction NL des newsletters** : le bouton « Traduire NL » de l'éditeur
  ouvre DeepL avec le texte. Une version automatique via l'API DeepL est possible
  (nécessite une clé).

### Hors dépôt
- **Dossier de subvention communale (Kraainem)** : formulaire Annexe 2 + rapport
  de fonctionnement 2026, générés en session au format Word. **À déposer avant le
  9 octobre 2026.** Ces fichiers ne sont pas dans le dépôt.

---

## 8. Points de vigilance éditoriale

- **Ne pas réduire le combat à « la piste 01 ».** Le sujet est le survol en
  général et l'**usage excessif des pistes non préférentielles** (01 *et* 07).
  La demande constante est le retour aux **pistes préférentielles 25R/25L** et la
  correction des **normes de vent**. Cadrer sur la seule piste 01 enferme le
  propos dans un conflit entre communes, ce que l'association refuse explicitement.
- **Pas d'émoticônes** dans les contenus publiés (site, newsletters, réseaux) :
  considérées comme une signature d'IA. `og-news.php` retire d'ailleurs les emoji
  des titres avant de générer l'image de partage, car la police GD ne sait pas
  les rendre (mojibake `ð□□¢`).
- **Droit d'auteur presse** : les articles de journaux ne sont pas republiés
  intégralement. La page parue est reproduite **floutée**, seul le titre reste
  lisible, avec la source et un lien vers l'article original.

---

## 9. Délivrabilité des e-mails

Tout passe par **Brevo** dès que `BREVO_API_KEY` est défini (sinon repli SMTP OVH).

Pour éviter les spams, trois enregistrements DNS sont nécessaires sur le domaine :
**DKIM** (fourni par Brevo, le plus important), **SPF** — penser à y inclure
`include:spf.brevo.com`, et en **TXT**, pas en type `SPF` qui est obsolète — et
**DMARC**. Vérification possible via mail-tester.com.

Reste à faire : l'en-tête **`List-Unsubscribe`** (désinscription en un clic),
exigé par Gmail et Yahoo pour les envois en nombre, n'est pas encore implémenté.
