<?php // includes/admin_sidebar_css.php ?>
/* ── SIDEBAR ADMIN ──────────────────────────────────────────── */
.sidebar {
  position: fixed; top: 0; left: 0;
  width: 240px; height: 100vh;
  background: #0e3d6b;
  display: flex; flex-direction: column;
  overflow-y: auto; z-index: 200;
  transition: transform .25s ease;
}
.sidebar-brand {
  padding: 18px 16px 14px;
  border-bottom: 1px solid rgba(255,255,255,.1);
  flex-shrink: 0;
}
.sidebar-brand h2 { color: #fff; font-size: .9rem; font-weight: 800; line-height: 1.2; }
.sidebar-brand h2 span { color: #FF9900; font-style: italic; }
.sidebar-brand p { color: rgba(255,255,255,.4); font-size: .6rem; margin-top: 3px; text-transform: uppercase; letter-spacing: .08em; }
.sidebar-close { display: none; background: none; border: none; color: rgba(255,255,255,.6); font-size: 1rem; cursor: pointer; padding: 4px 8px; }
.sidebar .nav-section { padding: 14px 16px 4px; font-size: .6rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: rgba(255,255,255,.3); }
.sidebar nav { flex: 1; overflow-y: auto; }
.sidebar nav a { display: block; padding: 10px 16px; color: rgba(255,255,255,.72); text-decoration: none; font-size: .82rem; transition: background .15s; border-left: 3px solid transparent; }
.sidebar nav a:hover { background: rgba(255,255,255,.1); color: #fff; }
.sidebar nav a.active { background: rgba(255,255,255,.12); color: #fff; border-left-color: #FF9900; font-weight: 600; }
.sidebar-footer { padding: 12px 16px; border-top: 1px solid rgba(255,255,255,.1); flex-shrink: 0; display: flex; flex-direction: column; gap: 6px; }
.sidebar-footer a { color: rgba(255,255,255,.45); font-size: .72rem; text-decoration: none; }
.sidebar-footer a:hover { color: #fff; }

/* Wrap principal */
.wrap { margin-left: 240px; min-height: 100vh; }

/* Éléments mobile cachés par défaut */
.mobile-topbar { display: none; }
.sidebar-overlay { display: none; }

/* ── MOBILE ──────────────────────────────────────────────────── */
@media (max-width: 768px) {

  /* Sidebar cachée par défaut, slide depuis la gauche */
  .sidebar {
    transform: translateX(-100%);
    width: 280px;
    z-index: 300;
  }
  .sidebar.open { transform: translateX(0); }
  .sidebar-close { display: block; }

  /* Overlay sombre derrière le sidebar */
  .sidebar-overlay {
    display: block;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 299;
    opacity: 0; pointer-events: none;
    transition: opacity .25s;
  }
  .sidebar-overlay.open { opacity: 1; pointer-events: all; }

  /* Barre top mobile */
  .mobile-topbar {
    display: flex; align-items: center; justify-content: space-between;
    position: fixed; top: 0; left: 0; right: 0; height: 52px;
    background: #0e3d6b; padding: 0 16px;
    z-index: 200; box-shadow: 0 2px 8px rgba(0,0,0,.2);
  }
  .burger-btn {
    background: none; border: none; color: #fff;
    font-size: 1.4rem; cursor: pointer; padding: 4px 8px;
    line-height: 1;
  }
  .mobile-title { color: #fff; font-size: .9rem; font-weight: 600; }
  .mobile-title strong { color: #FF9900; }

  /* Wrap prend toute la largeur, avec padding pour la topbar */
  .wrap { margin-left: 0; padding-top: 52px; }
}
