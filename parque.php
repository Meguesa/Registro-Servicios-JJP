<?php
declare(strict_types=1);

$root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
require_once $root . '/includes/bootstrap.php';
portal_require_authentication();

$initialDraftId = '';
$initialDraftPayload = null;
$draftError = '';

$requestedDraft = trim((string)($_GET['draft'] ?? ''));
if ($requestedDraft !== '') {
    try {
        require_once __DIR__ . '/includes/registro-storage.php';
        $storageCtx = rs_storage_bootstrap();
        $initialDraftId = rs_safe_id($requestedDraft);
        $draft = rs_read_draft($storageCtx, $initialDraftId);
        if (is_array($draft) && is_array($draft['payload'] ?? null)) {
            $initialDraftPayload = $draft['payload'];
        } else {
            $draftError = 'No se encontro la informacion del borrador.';
        }
    } catch (Throwable $draftLoadError) {
        $draftError = $draftLoadError->getMessage();
        error_log('Registro Servicios Parque carga borrador: ' . $draftLoadError->getMessage());
    }
}

$cssVersion = is_file(__DIR__ . '/assets/css/styles.css')
    ? (string)filemtime(__DIR__ . '/assets/css/styles.css')
    : '1';
$jsVersion = is_file(__DIR__ . '/assets/js/parque.js')
    ? (string)filemtime(__DIR__ . '/assets/js/parque.js')
    : '1';

?><!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Servicios Parque | Registro de Servicios JdJP</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= htmlspecialchars($cssVersion, ENT_QUOTES, 'UTF-8') ?>">
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
      <a class="solicitud-topbar-back" href="mis-servicios.php?area=parque">Mis servicios</a>
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
      <p>Captura la información por etapas. Los cálculos y campos condicionales conservan la lógica actual de Parque.</p>
    </div>
    <div class="form-banner-meta">
      <div class="step-counter">
        <span>Paso</span>
        <strong><span id="currentStepNumber">1</span> de 5</strong>
      </div>
    </div>
  </section>

  <nav class="wizard-steps" aria-label="Progreso del formulario">
      <button type="button" class="wizard-step active" data-step-target="0"><span>1</span><strong>Servicio</strong><small>Fechas y datos</small></button>
      <button type="button" class="wizard-step" data-step-target="1"><span>2</span><strong>Propiedad</strong><small>Servicio, sección y placa</small></button>
      <button type="button" class="wizard-step" data-step-target="2"><span>3</span><strong>Fallecido</strong><small>Datos personales</small></button>
      <button type="button" class="wizard-step" data-step-target="3"><span>4</span><strong>Operación</strong><small>Liquidación y reubicación</small></button>
      <button type="button" class="wizard-step" data-step-target="4"><span>5</span><strong>Confirmar</strong><small>Resumen final</small></button>
  </nav>

  <form id="parqueForm" novalidate class="form-shell">
    <section class="form-section wizard-panel active" data-step="0">
      <div class="section-title">
        <span>1</span>
        <div><h2>Información del servicio</h2><p>Datos principales usados por Eventos Parque y el calendario operativo.</p></div>
      </div>

      <div class="form-grid grid-4">
        <label>Fecha Inicio
          <input type="text" name="fechaInicio" id="fechaInicio" class="date-only-picker" placeholder="dd/mm/yyyy" autocomplete="off" required>
        </label>
        <label>Hora Inicio
          <input type="time" name="horaInicio" id="horaInicio" step="300" required>
        </label>
        <label>Fecha Fin
          <input type="text" name="fechaFin" id="fechaFin" class="date-only-picker" placeholder="dd/mm/yyyy" autocomplete="off" required>
        </label>
        <label>Hora Fin
          <input type="time" name="horaFin" id="horaFin" step="300" required>
        </label>
      </div>

      <div class="form-grid grid-2">
        <label>Velación
          <select name="velacion" id="velacion" required></select>
        </label>
        <label>Previsión / Uso Inmediato
          <select name="previsionUsoInmediato" id="previsionUsoInmediato" required></select>
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

      <div class="form-grid grid-2">
        <label>Tipo de Servicio
          <select name="tipoServicio" id="tipoServicio" required></select>
        </label>
        <label>Servicio
          <select name="servicio" id="servicioParque" required></select>
        </label>
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
        <label id="fraseWrap" class="hidden">Frase
          <input name="frase" id="frase" placeholder="Frase o texto para el servicio">
          <small class="hint">Solo aplica cuando Destape = Primero y la sección es VIP.</small>
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
          <span>Captura la nueva propiedad con el mismo formato de la ubicación anterior.</span>
        </div>

        <div class="form-grid grid-3">
          <label>Sección Nueva
            <select name="seccionNueva" id="seccionNueva"></select>
          </label>
          <label>Manzana Nueva
            <select name="manzanaNueva" id="manzanaNueva"></select>
          </label>
          <label>Lote / Nicho Nuevo
            <input type="number" min="0" step="1" name="numLoteNichoNuevo" id="numLoteNichoNuevo" placeholder="Ej. 236">
          </label>
        </div>

        <div class="reference-summary">
          <div class="span-2">
            <span>Ubicación nueva</span>
            <strong id="ubicacionNuevaPreview">—</strong>
          </div>
        </div>
        <input type="hidden" name="ubicacionNueva" id="ubicacionNueva" value="">

        <div class="form-grid grid-2">
          <label>Motivo de Reubicación
            <select name="motivoReubicacion" id="motivoReubicacion">
              <option value="">Seleccionar</option>
              <option value="Terreno no construido">Terreno no construido</option>
            </select>
          </label>
        </div>
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
      </div>

    </section>

    <div class="wizard-actions">
      <div>
        <button type="button" class="secondary-button" id="resetBtn">Limpiar</button>
        <button type="button" class="secondary-button draft-button" id="saveDraftBtn">Guardar borrador</button>
      </div>
      <div>
        <button type="button" class="secondary-button" id="prevStep">Anterior</button>
        <button type="button" class="primary-button" id="nextStep">Siguiente</button>
        <button type="submit" class="primary-button hidden" id="submitBtn">Registrar servicio</button>
      </div>
    </div>
    <input type="hidden" id="draftId" name="draftId" value="">
    <p id="status" class="status">Listo para registrar en SharePoint.</p>
  </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
window.JDJP_PARQUE_INITIAL_DRAFT = <?= json_encode([
    'id'=>$initialDraftId,
    'payload'=>$initialDraftPayload,
    'error'=>$draftError,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/js/parque.js?v=<?= htmlspecialchars($jsVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
