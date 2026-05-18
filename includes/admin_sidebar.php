<?php // includes/admin_sidebar.php ?>
<div class="sidebar" id="admin-sidebar">
  <div class="sidebar-brand">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <div>
        <h2>ça suffit ! <span>ASBL</span></h2>
        <p>Administration</p>
      </div>
      <button class="sidebar-close" onclick="toggleSidebar()" aria-label="Fermer">✕</button>
    </div>
  </div>
  <nav>
    <div class="nav-section">Contenu</div>
    <a href="dashboard.php"   <?= basename($_SERVER['PHP_SELF'])==='dashboard.php'   ?'class="active"':'' ?>>Dashboard</a>
    <a href="stats.php"      <?= basename($_SERVER['PHP_SELF'])==='stats.php'      ?'class="active"':'' ?>>Statistiques</a>
    <a href="pages.php"       <?= basename($_SERVER['PHP_SELF'])==='pages.php'       ?'class="active"':'' ?>>Pages</a>
    <a href="news.php"        <?= basename($_SERVER['PHP_SELF'])==='news.php'        ?'class="active"':'' ?>>Actualités</a>
    <a href="medias.php"      <?= basename($_SERVER['PHP_SELF'])==='medias.php'      ?'class="active"':'' ?>>Médias</a>
    <a href="site_config.php" <?= basename($_SERVER['PHP_SELF'])==='site_config.php' ?'class="active"':'' ?>>Paramètres</a>
    <div class="nav-section">Contenu & Widgets</div>
    <a href="widgets.php"     <?= basename($_SERVER['PHP_SELF'])==='widgets.php'     ?'class="active"':'' ?>>Widgets</a>
    <div class="nav-section">Membres & Dons</div>
    <a href="members.php"     <?= basename($_SERVER['PHP_SELF'])==='members.php'     ?'class="active"':'' ?>>Membres</a>
    <a href="dons_all.php"    <?= basename($_SERVER['PHP_SELF'])==='dons_all.php'    ?'class="active"':'' ?>>Tous les dons</a>
    <a href="coda.php"        <?= basename($_SERVER['PHP_SELF'])==='coda.php'        ?'class="active"':'' ?>>Import CODA</a>
    <a href="import_wix.php"  <?= basename($_SERVER['PHP_SELF'])==='import_wix.php'  ?'class="active"':'' ?>>Import Wix</a>
    <div class="nav-section">Outils</div>
    <a href="backup.php" <?= basename($_SERVER['PHP_SELF'])==='backup.php' ?'class="active"':'' ?>>Backup &amp; Export</a>
    <div class="nav-section">Newsletter</div>
    <a href="subscribers.php" <?= basename($_SERVER['PHP_SELF'])==='subscribers.php' ?'class="active"':'' ?>>Abonnés</a>
    <a href="compose.php"     <?= basename($_SERVER['PHP_SELF'])==='compose.php'     ?'class="active"':'' ?>>Rédaction</a>
    <a href="newsletters.php" <?= basename($_SERVER['PHP_SELF'])==='newsletters.php' ?'class="active"':'' ?>>Envoyer / Historique</a>
  </nav>
  <div class="sidebar-footer">
    <a href="<?= SITE_URL ?>" target="_blank">Voir le site</a>
    <a href="logout.php">Déconnexion</a>
  </div>
</div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="mobile-topbar" id="mobile-topbar">
  <button class="burger-btn" onclick="toggleSidebar()" aria-label="Menu">☰</button>
  <span class="mobile-title" id="mobile-page-title">ça suffit ! <strong>Admin</strong></span>
  <a href="<?= SITE_URL ?>" target="_blank" style="font-size:.75rem;color:#fff;text-decoration:none;opacity:.7">Site</a>
</div>

<script>
function toggleSidebar() {
  var s = document.getElementById('admin-sidebar');
  var o = document.getElementById('sidebar-overlay');
  var open = s.classList.toggle('open');
  o.classList.toggle('open', open);
}
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.sidebar nav a').forEach(function(a) {
    a.addEventListener('click', function() {
      if (window.innerWidth < 768) {
        document.getElementById('admin-sidebar').classList.remove('open');
        document.getElementById('sidebar-overlay').classList.remove('open');
      }
    });
  });
});
</script>
