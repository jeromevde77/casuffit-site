#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════
#  deploy.sh — Déploiement automatique vers GitHub + OVH
#  Usage : ./deploy.sh "Description de la modification"
#  Exemple : ./deploy.sh "Ajout menu burger mobile"
# ═══════════════════════════════════════════════════════════════════════

set -e  # Arrêter si une commande échoue

# ── Configuration (à remplir une seule fois) ─────────────────────────────
GITHUB_USER="votre-username-github"
GITHUB_REPO="casuffit-site"
OVH_FTP_HOST="ftp.piste01casuffit.be"
OVH_FTP_USER="votre-login-ftp-ovh"
OVH_FTP_PASS="votre-mot-de-passe-ftp"
OVH_FTP_PATH="/www"

# ── Message de commit ─────────────────────────────────────────────────────
COMMIT_MSG="${1:-Mise à jour automatique}"
DATE=$(date '+%Y-%m-%d %H:%M')

echo ""
echo "═══════════════════════════════════════════════"
echo "  🚀 Déploiement ça suffit ! ASBL"
echo "  📝 $COMMIT_MSG"
echo "  📅 $DATE"
echo "═══════════════════════════════════════════════"
echo ""

# ── 1. Push vers GitHub ───────────────────────────────────────────────────
echo "📦 Envoi vers GitHub..."
git add -A
git commit -m "[$DATE] $COMMIT_MSG" || echo "  (rien à committer)"
git push origin main
echo "  ✓ GitHub mis à jour"
echo ""

# ── 2. Déploiement FTP vers OVH ───────────────────────────────────────────
echo "🌐 Déploiement sur OVH via FTP..."

# Liste des fichiers à exclure du déploiement
EXCLUDES="--exclude config.php --exclude .git --exclude .gitignore --exclude deploy.sh --exclude '*.log' --exclude logs/"

# Utiliser lftp si disponible (plus fiable que ftp)
if command -v lftp &> /dev/null; then
    lftp -c "
    set ftp:ssl-allow no;
    open ftp://$OVH_FTP_USER:$OVH_FTP_PASS@$OVH_FTP_HOST;
    mirror --reverse --delete --verbose \
           --exclude config.php \
           --exclude .git \
           --exclude .gitignore \
           --exclude deploy.sh \
           --exclude-glob '*.log' \
           . $OVH_FTP_PATH;
    quit"
    echo "  ✓ OVH mis à jour via lftp"
else
    echo "  ⚠ lftp non installé — déploiement FTP ignoré"
    echo "  → Installez lftp : brew install lftp (Mac) ou apt install lftp (Linux)"
    echo "  → Ou uploadez manuellement via Cyberduck"
fi

echo ""
echo "═══════════════════════════════════════════════"
echo "  ✅ Déploiement terminé !"
echo "  🔗 GitHub : https://github.com/$GITHUB_USER/$GITHUB_REPO"
echo "  🌐 Site   : https://new.piste01casuffit.be"
echo "═══════════════════════════════════════════════"
echo ""
