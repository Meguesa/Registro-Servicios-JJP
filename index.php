<?php
declare(strict_types=1);

$root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
require_once $root . '/includes/bootstrap.php';
portal_require_authentication();

$user = portal_user();
$email = htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Registro de Servicios | Jardines de Juan Pablo</title>
  <style>
    :root{
      --green:#1f5b46;
      --green-dark:#234136;
      --text:#26352f;
      --muted:#66726d;
      --line:#d8dfdb;
      --bg:#f4f7f5;
      --card:#fff;
      --gold:#b88a2f;
      --gold-soft:#fff8e8;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:var(--text)}
    .topbar{height:80px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 24px}
    .brand{font-family:Georgia,serif;font-size:20px;font-weight:700;color:var(--green)}
    .pilot{font-size:12px;font-weight:700;letter-spacing:.08em;color:#76581d;background:#faf6ea;border:1px solid #dfd2ad;border-radius:7px;padding:9px 12px}
    .shell{max-width:1392px;margin:0 auto;padding:46px 24px 70px}
    .hero{display:flex;justify-content:space-between;align-items:center;gap:24px}
    .kicker{font-size:12px;letter-spacing:.14em;font-weight:700;color:#537064;margin:0 0 18px}
    h1{font-size:40px;margin:0 0 10px;line-height:1.05}
    .hero p{font-size:16px;color:var(--muted);margin:0}
    .back{display:inline-flex;align-items:center;padding:12px 18px;border:1px solid #b9ccc3;border-radius:8px;background:#fff;color:var(--green);text-decoration:none;font-weight:700}
    .notice{margin:28px 0 34px;background:var(--gold-soft);border:1px solid #e5cd91;border-left:4px solid var(--gold);border-radius:8px;padding:18px 20px}
    .notice strong{display:block;margin-bottom:10px}
    .section-title{font-size:22px;margin:0 0 8px}
    .section-copy{margin:0 0 28px;color:var(--muted)}
    .areas{display:grid;grid-template-columns:1fr 1fr;gap:24px}
    .area-card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:36px 30px;min-height:245px;display:flex;flex-direction:column;align-items:flex-start}
    .eyebrow{font-size:12px;letter-spacing:.16em;color:#5d786d;font-weight:700;margin-bottom:24px}
    .area-card h2{font-size:29px;margin:0 0 14px}
    .area-card p{color:var(--muted);margin:0 0 28px;font-size:16px}
    .area-action{margin-top:auto;display:inline-flex;border:0;border-radius:8px;background:#216449;color:#fff;padding:14px 20px;text-decoration:none;font-weight:700;font-size:16px}
    .area-card.disabled{background:#f8faf9}
    .area-card.disabled .area-action{background:#dfe6e2;color:#6d7772;cursor:not-allowed}
    .status-dev{display:inline-block;margin-left:8px;font-size:11px;text-transform:uppercase;letter-spacing:.1em;background:#f2ead7;color:#7d6226;border-radius:999px;padding:5px 8px;vertical-align:middle}
    footer{border-top:1px solid var(--line);margin-top:36px;padding-top:22px;color:#66726d;font-size:12px;line-height:1.9}
    @media(max-width:760px){
      .shell{padding:30px 16px 50px}.hero{align-items:flex-start;flex-direction:column}h1{font-size:34px}.areas{grid-template-columns:1fr}.topbar{padding:0 16px}.area-card{min-height:220px}
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="brand">Jardines de Juan Pablo</div>
    <div class="pilot">PORTAL INTERNO</div>
  </header>

  <main class="shell">
    <section class="hero">
      <div>
        <p class="kicker">PORTAL INTERNO</p>
        <h1>Registro de Servicios</h1>
        <p>Selecciona el área desde la que deseas capturar o consultar servicios.</p>
      </div>
      <a class="back" href="/">Volver al portal</a>
    </section>

    <section class="notice">
      <strong>Registro conectado a los procesos operativos reales.</strong>
      <span>Los servicios de Capillas se registran en SharePoint y pueden activar calendario, correo y automatizaciones asociadas. Verifica la información antes de publicar.</span>
    </section>

    <section>
      <h2 class="section-title">Selecciona el área</h2>
      <p class="section-copy">Primero elige Capillas o Parque. Parque permanece visible mientras se desarrolla su módulo.</p>

      <div class="areas">
        <article class="area-card">
          <div class="eyebrow">CAPTURA ACTUAL</div>
          <h2>Capillas</h2>
          <p>Captura nuevos servicios, continúa borradores y consulta los servicios publicados desde este módulo.</p>
          <a class="area-action" href="mis-servicios.php">Seleccionar Capillas</a>
        </article>

        <article class="area-card disabled">
          <div class="eyebrow">PRÓXIMAMENTE</div>
          <h2>Parque <span class="status-dev">En desarrollo</span></h2>
          <p>El módulo para captura y seguimiento de servicios de Parque se encuentra actualmente en desarrollo.</p>
          <span class="area-action" aria-disabled="true">Servicio Parque · En desarrollo</span>
        </article>
      </div>
    </section>

    <footer>
      <div>Cuenta del portal: <?= $email !== '' ? $email : 'Usuario autenticado' ?>.</div>
      <div>Registro de Servicios utiliza la autenticación y permisos del Portal Interno.</div>
    </footer>
  </main>
</body>
</html>
