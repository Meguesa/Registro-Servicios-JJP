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
  <title>Registro de Servicios | Jardines de Juan Pablo</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="solicitud-topbar">
  <div class="solicitud-topbar-inner">
    <div class="solicitud-topbar-left">
      <img class="solicitud-topbar-logo" src="/mapa/assets/logo.jpg" alt="Jardines de Juan Pablo">
      <div class="solicitud-topbar-title">
        <strong>Registro de Servicios</strong>
        <span>Portal Interno JdJP · Jardines de Juan Pablo</span>
      </div>
    </div>

    <div class="solicitud-topbar-context">Captura y seguimiento de servicios operativos</div>

    <div class="solicitud-topbar-actions">
      <a class="solicitud-topbar-back" href="/">Regresar al portal</a>
      <a class="account-trigger-static" href="/logout.php" title="<?= $name ?> · Cerrar sesión" aria-label="Cerrar sesión">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="8" r="4" fill="currentColor"/>
          <path d="M4 20c0-4.1 3.6-6 8-6s8 1.9 8 6v1H4z" fill="currentColor"/>
        </svg>
      </a>
    </div>
  </div>
</header>

<main class="page-shell selector-home">
  <section class="form-banner selector-banner">
    <div>
      <span class="status-pill">REGISTRO DE SERVICIOS</span>
      <h1>Selecciona el área</h1>
      <p>Elige el módulo operativo que deseas utilizar.</p>
    </div>
    <div class="selector-banner-meta">
      <span class="preview-pill">Producción · conectado a SharePoint</span>
    </div>
  </section>

  <section class="selector-notice">
    <strong>Conexión a procesos operativos reales</strong>
    <span>Los servicios de Capillas pueden registrar información en SharePoint y activar calendario, correo y automatizaciones asociadas. Verifica la información antes de publicar.</span>
  </section>

  <section class="selector-grid" aria-label="Áreas de Registro de Servicios">
    <article class="selector-card">
      <div class="selector-card-top">
        <span class="selector-card-kicker">CAPTURA ACTUAL</span>
        <span class="selector-card-status available">Disponible</span>
      </div>
      <div class="selector-card-icon">▤</div>
      <h2>Capillas</h2>
      <p>Captura nuevos servicios, continúa borradores y consulta los servicios publicados desde este módulo.</p>
      <a class="primary-button selector-action" href="mis-servicios.php">Seleccionar Capillas</a>
    </article>

    <article class="selector-card selector-card-disabled">
      <div class="selector-card-top">
        <span class="selector-card-kicker">PRÓXIMAMENTE</span>
        <span class="selector-card-status development">En desarrollo</span>
      </div>
      <div class="selector-card-icon">⌖</div>
      <h2>Parque</h2>
      <p>El módulo para captura y seguimiento de servicios de Parque se encuentra actualmente en desarrollo.</p>
      <span class="secondary-button selector-action disabled-action" aria-disabled="true">Servicio Parque · En desarrollo</span>
    </article>
  </section>

  <p class="selector-account-note">Sesión activa: <?= $email !== '' ? $email : 'Usuario autenticado' ?>.</p>
</main>
</body>
</html>
