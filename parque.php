<?php
declare(strict_types=1);

$root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
require_once $root . '/includes/bootstrap.php';
portal_require_authentication();

$user = portal_user();
$userEmail = mb_strtolower(trim((string)($user['email'] ?? '')), 'UTF-8');
$canUseTestMode = in_array($userEmail, [
    'sistemas@juanpablo.com.mx',
    'gabriel.guerra@juanpablo.com.mx',
], true);

$isPreview = str_contains(
    (string)($_SERVER['REQUEST_URI'] ?? ''),
    '/registro-servicios-preview/'
);
?><!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Servicios Parque | Registro de Servicios JdJP</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="assets/css/styles.css">
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
    <div class="solicitud-topbar-context">Servicios Parque</div>
    <div class="solicitud-topbar-actions">
      <a class="solicitud-topbar-back" href="index.php">Cambiar área</a>
      <a class="solicitud-topbar-back" href="/">Regresar al portal</a>
      <button class="account-trigger-static" type="button" aria-label="Usuario" title="Usuario">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="8" r="4" fill="currentColor"></circle>
          <path d="M4 20c0-4.1 3.6-6 8-6s8 1.9 8 6v1H4z" fill="currentColor"></path>
        </svg>
      </button>
    </div>
  </div>
</header>

<main class="page-shell">
  <section class="form-banner">
    <div>
      <span class="status-pill">Parque</span>
      <h1>Registro de servicio</h1>
      <p>Captura el servicio de Parque desde el portal. SharePoint se conserva como registro oficial mientras migramos los procesos al modulo.</p>
    </div>
    <div class="form-banner-meta">
      <span class="preview-pill" id="modePill"><?= $isPreview ? 'Prueba · conectado a SharePoint' : 'Producción · conectado a SharePoint' ?></span>
      <?php if ($canUseTestMode): ?>
      <label class="test-mode-toggle" for="modoPrueba">
        <span><strong>Modo prueba</strong><small>Solo Sistemas</small></span>
        <input type="checkbox" id="modoPrueba" name="modoPrueba" <?= $isPreview ? 'checked' : '' ?>>
      </label>
      <?php endif; ?>
      <div class="step-counter">
        <span>Paso</span>
        <strong><span id="currentStepNumber">1</span> de 5</strong>
      </div>
    </div>
  </section>

  <section class="selector-notice">
    <strong>Compatible con el proceso actual de Parque</strong>
    <span>Al publicar se crea el elemento en <b>Eventos Parque</b> y el calendario se genera directamente desde Registro de Servicios.</span>
  </section>

  <form id="parqueForm" novalidate class="form-shell">
    <nav class="wizard-steps" aria-label="Progreso del formulario">
      <button type="button" class="wizard-step active" data-step-target="0"><span>1</span><strong>Servicio</strong><small>Fechas y tipo</small></button>
      <button type="button" class="wizard-step" data-step-target="1"><span>2</span><strong>Propiedad</strong><small>Sección y placa</small></button>
      <button type="button" class="wizard-step" data-step-target="2"><span>3</span><strong>Fallecido</strong><small>Datos personales</small></button>
      <button type="button" class="wizard-step" data-step-target="3"><span>4</span><strong>Operación</strong><small>Liquidación y reubicación</small></button>
      <button type="button" class="wizard-step" data-step-target="4"><span>5</span><strong>Confirmar</strong><small>Resumen final</small></button>
    </nav>

    <section class="form-section wizard-panel active" data-step="0">
      <div class="section-title">
        <span>1</span>
        <div><h2>Información del servicio</h2><p>Datos principales usados por Eventos Parque y el calendario operativo.</p></div>
      </div>

      <div class="form-grid grid-2">
        <label>Fecha y Hora Inicio
          <input type="text" name="fechaHoraInicio" id="fechaHoraInicio" class="datetime-picker" placeholder="Ej. 29/09/2026 10:00" autocomplete="off" required>
        </label>
        <label>Fecha y Hora Fin
          <input type="text" name="fechaHoraFin" id="fechaHoraFin" class="datetime-picker" placeholder="Ej. 29/09/2026 12:00" autocomplete="off" required>
        </label>
        <label>Velación
          <select name="velacion" id="velacion" required></select>
        </label>
        <label>Previsión / Uso Inmediato
          <select name="previsionUsoInmediato" id="previsionUsoInmediato" required></select>
        </label>
        <label>Tipo de Servicio
          <select name="tipoServicio" id="tipoServicio" required></select>
        </label>
        <label>Servicio
          <select name="servicio" id="servicioParque" required></select>
        </label>
        <label>Asistente Funerario
          <input name="asistenteFunerarioTexto" id="asistenteFunerarioTexto" placeholder="Ej. Nombre del asistente" required>
        </label>
        <label>Número de Contrato
          <input name="numeroContrato" id="numeroContrato" placeholder="Ej. 12345">
        </label>
      </div>
    </section>

    <section class="form-section wizard-panel" data-step="1">
      <div class="section-title">
        <span>2</span>
        <div><h2>Propiedad y placas</h2><p>Ubicación de la propiedad y reglas actuales de Nicho, Urna y Granito.</p></div>
      </div>

      <div class="form-grid grid-3">
        <label>Sección
          <select name="seccion" id="seccion" required></select>
        </label>
        <label>Manzana
          <select name="manzana" id="manzana" required></select>
        </label>
        <label>Lote / Nicho
          <input type="number" min="0" step="1" name="numLoteNicho" id="numLoteNicho" placeholder="Ej. 236" required>
        </label>
      </div>

      <div class="reference-summary">
        <div class="span-2"><span>Ubicación</span><strong id="ubicacionPreview">—</strong></div>
      </div>

      <div class="form-grid grid-2">
        <label>Destape
          <select name="destape" id="destape" required></select>
        </label>
        <label>Tipo de Placa
          <select name="tipoPlaca" id="tipoPlaca" required></select>
        </label>
      </div>

      <div id="nichoFields" class="conditional-card hidden">
        <div class="subsection-title">
          <strong>Placa de Nicho</strong>
          <span>La placa de Nicho solo aplica cuando Destape = Primero.</span>
        </div>
        <label>Nombre de Familia
          <input name="nombreFamilia" id="nombreFamilia" placeholder="Ej. FAMILIA GONZÁLEZ">
        </label>
      </div>

      <div class="option-row">
        <label class="switch-card">
          <div>
            <strong>Requiere placa de urna adicional</strong>
            <small>Conserva la regla actual de Parque para generar una placa de urna adicional.</small>
          </div>
          <input type="checkbox" name="requiereCambioUrna" id="requiereCambioUrna">
        </label>
      </div>

      <div id="placaWarning" class="preview-warning hidden">
        <strong>Regla de Nicho</strong>
        <span>Nicho únicamente puede seleccionarse cuando el destape es Primero.</span>
      </div>
    </section>

    <section class="form-section wizard-panel" data-step="2">
      <div class="section-title">
        <span>3</span>
        <div><h2>Fallecido y titular</h2><p>Datos utilizados por Parque, cartas y procesos posteriores.</p></div>
      </div>

      <div class="form-grid grid-2">
        <label>Titular
          <input name="titular" id="titular" placeholder="Ej. Juan Pérez González" required>
        </label>
        <label>Fallecido(a)
          <input name="fallecido" id="fallecido" placeholder="Ej. María López García" required>
        </label>
        <label>Parentesco del Titular
          <select name="parentescoTitular" id="parentescoTitular" required></select>
        </label>
        <label>Frase
          <input name="frase" id="frase" placeholder="Frase o texto para el servicio">
        </label>
      </div>

      <div class="form-grid grid-2">
        <label>Fecha de Nacimiento
          <input type="text" name="fechaNacimiento" id="fechaNacimiento" class="date-only-picker" placeholder="Ej. 01/01/1950" autocomplete="off" required>
        </label>
        <label>Fecha de Defunción
          <input type="text" name="fechaDefuncion" id="fechaDefuncion" class="date-only-picker" placeholder="Ej. 28/09/2026" autocomplete="off" required>
        </label>
      </div>
    </section>

    <section class="form-section wizard-panel" data-step="3">
      <div class="section-title">
        <span>4</span>
        <div><h2>Información operativa</h2><p>Datos que determinan cartas y tratamientos especiales dentro del flujo existente.</p></div>
      </div>

      <div class="form-grid grid-2">
        <label>Estatus de Liquidación
          <select name="estatusLiquidacion" id="estatusLiquidacion" required></select>
        </label>
        <label class="span-2">Observaciones
          <textarea name="observaciones" id="observaciones" rows="4" placeholder="Observaciones del servicio"></textarea>
        </label>
      </div>

      <div class="option-row">
        <label class="switch-card">
          <div>
            <strong>Requiere Reubicación</strong>
            <small>Activa los datos que usa la Carta de Reubicación.</small>
          </div>
          <input type="checkbox" name="requiereReubicacion" id="requiereReubicacion">
        </label>
      </div>

      <div id="reubicacionFields" class="conditional-card hidden">
        <div class="subsection-title">
          <strong>Reubicación</strong>
          <span>Estos datos se enviarán a Eventos Parque únicamente cuando aplique.</span>
        </div>
        <div class="form-grid grid-2">
          <label>Ubicación Nueva
            <input name="ubicacionNueva" id="ubicacionNueva" placeholder="Ej. POR CONFIRMAR">
          </label>
          <label>Motivo de Reubicación
            <select name="motivoReubicacion" id="motivoReubicacion">
              <option value="">Seleccionar</option>
              <option value="Terreno no construido">Terreno no construido</option>
            </select>
          </label>
        </div>
      </div>

      <div class="preview-warning">
        <strong>Reglas actuales del flujo</strong>
        <span>No liquidado + sección SPN/PLN usa la rama de Retiro de Cenizas; otras secciones usan Exhumación. Requiere Reubicación activa la carta correspondiente.</span>
      </div>
    </section>

    <section class="form-section wizard-panel" data-step="4">
      <div class="section-title">
        <span>5</span>
        <div><h2>Confirmar servicio</h2><p>Revisa los datos principales antes de crear el elemento en SharePoint.</p></div>
      </div>

      <div class="reference-summary">
        <div><span>Fallecido(a)</span><strong id="summaryFallecido">—</strong></div>
        <div><span>Tipo de servicio</span><strong id="summaryTipoServicio">—</strong></div>
        <div><span>Ubicación</span><strong id="summaryUbicacion">—</strong></div>
        <div><span>Tipo de placa</span><strong id="summaryPlaca">—</strong></div>
        <div><span>Liquidación</span><strong id="summaryLiquidacion">—</strong></div>
        <div><span>Modo</span><strong id="summaryModo">Producción</strong></div>
      </div>

      <div id="testModeHelp" class="preview-warning" <?= $isPreview ? '' : 'hidden' ?>>
        <strong>Modo prueba</strong>
        <span>El registro se enviará a Eventos Parque con ModoPrueba activado para permitir la validación controlada del flujo.</span>
      </div>

      <div class="selector-notice">
        <strong>Después de publicar</strong>
        <span>Calendario ya se procesa directamente desde Registro de Servicios. Placas, cartas y correo se migraran en las siguientes fases.</span>
      </div>
    </section>

    <div class="wizard-actions">
      <div>
        <a class="secondary-button" href="index.php">Cancelar</a>
      </div>
      <div>
        <button type="button" class="secondary-button" id="prevStep">Anterior</button>
        <button type="button" class="primary-button" id="nextStep">Siguiente</button>
        <button type="submit" class="primary-button hidden" id="submitBtn">Registrar Servicio Parque</button>
      </div>
    </div>

    <p id="status" class="status">Listo para capturar un servicio de Parque.</p>
  </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/parque.js"></script>
</body>
</html>
