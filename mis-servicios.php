<?php
declare(strict_types=1);
$root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
require_once $root . '/includes/bootstrap.php';
portal_require_authentication();
$user = portal_user();
$name = htmlspecialchars((string)($user['name'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="es-MX">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mis Servicios | Jardines de Juan Pablo</title>
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="mis-servicios.css">
</head>
<body>
<header class="solicitud-topbar">
  <div class="solicitud-topbar-inner">
    <div class="solicitud-topbar-left">
      <img class="solicitud-topbar-logo" src="/mapa/assets/logo.jpg" alt="Jardines de Juan Pablo">
      <div class="solicitud-topbar-title"><strong>Registro de Servicios</strong><span>Portal Interno JdJP · Jardines de Juan Pablo</span></div>
    </div>
    <div class="solicitud-topbar-context">Captura y seguimiento de servicios operativos</div>
    <div class="solicitud-topbar-actions">
      <a class="solicitud-topbar-back" href="/">Regresar al portal</a>
      <a class="account-trigger-static" href="/logout.php" title="<?= $name ?> · Cerrar sesión" aria-label="Cerrar sesión">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" fill="currentColor"/><path d="M4 20c0-4.1 3.6-6 8-6s8 1.9 8 6v1H4z" fill="currentColor"/></svg>
      </a>
    </div>
  </div>
</header>

<main class="page-shell services-home">
  <section class="form-banner">
    <div><span class="status-pill">CAPILLAS</span><h1>Mis servicios</h1><p>Continúa borradores y consulta los servicios publicados desde este módulo.</p></div>
    <a class="primary-button button-link" href="./?nuevo=1">＋ Nuevo servicio</a>
  </section>

  <section class="service-menu-grid">
    <button type="button" class="service-menu-card active" data-view="drafts"><span>✎</span><div><strong>Borradores</strong><small><b id="countDrafts">0</b> guardado(s)</small></div></button>
    <button type="button" class="service-menu-card" data-view="published"><span>✓</span><div><strong>Publicados</strong><small><b id="countPublished">0</b> publicado(s)</small></div></button>
  </section>

  <div id="servicesMessage" class="service-message" role="status"></div>
  <section class="services-panel">
    <div class="services-panel-heading"><div><h2 id="servicesTitle">Borradores</h2><p id="servicesSubtitle">Cargando...</p></div><button id="refreshServices" class="secondary-button" type="button">Actualizar</button></div>
    <div id="servicesList" class="services-list-cards"></div>
    <div id="servicesEmpty" class="empty-state" hidden>No hay registros en esta sección.</div>
  </section>
</main>
<script src="mis-servicios.js"></script>
</body>
</html>