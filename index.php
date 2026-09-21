<?php
declare(strict_types=1);
?><!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registro de Servicios | Jardines de Juan Pablo</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="solicitud-topbar">
  <div class="solicitud-topbar-inner">
    <div class="solicitud-topbar-left">
      <div class="brand-mark">JdJP</div>
      <div class="solicitud-topbar-title">
        <strong>Registro de Servicios</strong>
        <span>Portal Interno JdJP · Jardines de Juan Pablo</span>
      </div>
    </div>
    <div class="solicitud-topbar-context">Captura de servicios de Capillas</div>
    <div class="solicitud-topbar-actions">
      <span class="preview-pill">Piloto · sin envío a SharePoint</span>
    </div>
  </div>
</header>

<main class="page-shell">
  <section class="form-banner">
    <div>
      <span class="status-pill">Capillas</span>
      <h1>Registro de servicio</h1>
      <p>Captura la información por etapas. Los cálculos y campos condicionales conservan la lógica actual.</p>
    </div>
    <div class="step-counter">
      <span>Paso</span>
      <strong><span id="currentStepNumber">1</span> de 5</strong>
    </div>
  </section>

  <nav class="wizard-steps" aria-label="Progreso del formulario">
    <button type="button" class="wizard-step active" data-step-target="0"><span>1</span><strong>Servicio</strong><small>Datos principales</small></button>
    <button type="button" class="wizard-step" data-step-target="1"><span>2</span><strong>Fallecido</strong><small>Datos personales</small></button>
    <button type="button" class="wizard-step" data-step-target="2"><span>3</span><strong>Operación</strong><small>Crematorio / Inhumación</small></button>
    <button type="button" class="wizard-step" data-step-target="3"><span>4</span><strong>Venta</strong><small>Precio y adicionales</small></button>
    <button type="button" class="wizard-step" data-step-target="4"><span>5</span><strong>Esquela</strong><small>Archivo final</small></button>
  </nav>

  <form id="capillasForm" novalidate class="form-shell">
    <section class="form-section wizard-panel active" data-step="0">
      <div class="section-title">
        <span>1</span>
        <div><h2>Información del servicio</h2><p>Define el tipo de servicio, ubicación, tiempos y referencia operativa.</p></div>
      </div>
      <div class="form-grid grid-2">
        <label>Número de Referencia<input name="numeroReferencia" placeholder="Ej. 1957, AF2054" required></label>
        <label>Tipo de Servicio<select name="servicio" id="servicio" required></select></label>
        <label>Ubicación Servicio Capillas<select name="ubicacion" id="ubicacion" required></select></label>
        <label id="wrapSala">Sala<select name="sala" id="sala"></select></label>
        <label id="wrapInicio">Fecha y Hora Inicio<input type="datetime-local" name="inicio" id="inicio" class="datetime-picker" step="300"></label>
        <label id="wrapTermino">Fecha y Hora Término<input type="datetime-local" name="termino" id="termino" class="datetime-picker" step="300"></label>
        <label id="wrapTiempo">Tiempo de Capillas<select name="tiempoCapillas" id="tiempoCapillas"></select></label>
        <label>Previsión/Uso Inmediato<select name="prevision" id="prevision" required></select></label>
        <label id="wrapTipoAtaud">Tipo de Ataúd/Urna<select name="tipoAtaud" id="tipoAtaud"></select></label>
        <label>Número de Servicio<input name="numeroServicio" id="numeroServicio" placeholder="Ej. 4225, 15783AF" required></label>
      </div>
      <div class="option-row">
        <label id="wrapExequiaToggle" class="switch-card"><div><strong>Lleva exequia?</strong><small>Actívalo para capturar fecha y hora.</small></div><input type="checkbox" name="llevaExequia" id="llevaExequia"></label>
        <label class="switch-card"><div><strong>Requiere Placa de Urna?</strong><small>Conserva el comportamiento actual del registro.</small></div><input type="checkbox" name="requierePlaca" id="requierePlaca"></label>
      </div>
      <div id="wrapExequia" class="conditional-box"><label>Fecha y Hora Exequia<input type="datetime-local" name="horaExequia" id="horaExequia" class="datetime-picker" step="300"></label></div>
      <div class="reference-summary">
        <div><span>Código de Servicio</span><strong id="codigoServicio">—</strong></div>
        <div><span>Ataúd/Urna</span><strong id="codigoAtaud">—</strong></div>
        <div class="span-2"><span>Referencia</span><strong id="referenciaPreview">—</strong></div>
      </div>
    </section>

    <section class="form-section wizard-panel" data-step="1">
      <div class="section-title"><span>2</span><div><h2>Fallecido y responsable</h2><p>Datos personales, destino final y personal que participa en el servicio.</p></div></div>
      <div class="form-grid grid-2">
        <label>Titular Responsable<input name="titular" placeholder="Ej. Juan Pérez González" required></label>
        <label>Nombre de Fallecido(a)<input name="fallecido" placeholder="Ej. María López García" required></label>
      </div>
      <div class="form-grid grid-3">
        <label>Fecha Nacimiento<input type="date" name="fechaNacimiento" id="fechaNacimiento" class="date-only-picker" required></label>
        <label>Fecha y Hora Defunción<input type="datetime-local" name="fechaDefuncion" id="fechaDefuncion" class="datetime-picker" step="300" required></label>
        <label>Edad<input name="edad" id="edad" placeholder="Se calcula automáticamente" readonly></label>
      </div>
      <div class="form-grid grid-2">
        <label>Ubicación Destino Final<input name="destinoFinal" placeholder="Ej. Parque JdJP, Crematorio" required></label>
        <label>Embalsamador<select name="embalsamador" id="embalsamador" required></select></label>
        <label>Personal Rescate 1<input name="rescate1" placeholder="Ej. José Martínez" required></label>
        <label>Personal Rescate 2<input name="rescate2" placeholder="Ej. Pedro Ramírez"></label>
        <label>Sexo<select name="sexo" id="sexo"><option value="">Seleccionar</option><option>Masculino</option><option>Femenino</option></select></label>
        <label>Ubicación de Rescate<input name="ubicacionRescate" placeholder="Ej. Domicilio, C33, C67" required></label>
        <label class="span-2">Motivo de Fallecimiento<input name="motivo" placeholder="Ej. Infarto, cáncer" required></label>
      </div>
    </section>

    <section class="form-section wizard-panel" data-step="2">
      <div class="section-title"><span>3</span><div><h2>Datos de operación</h2><p>Solo se muestran los bloques que correspondan al tipo de servicio seleccionado.</p></div></div>
      <div id="crematorioSection" class="conditional-card">
        <div class="subsection-title"><strong>Crematorio</strong><span>Visible únicamente para servicios de cremación.</span></div>
        <div class="form-grid grid-2">
          <label>Referencia Crematorio<input name="referenciaCrematorio" id="referenciaCrematorio" placeholder="Ej. C-123, C587"></label>
          <label>Fecha y Hora Inicio Crematorio<input type="datetime-local" name="inicioCrematorio" id="inicioCrematorio" class="datetime-picker" step="300"></label>
          <label class="span-2">Personal de Crematorio<input name="personalCrematorio" id="personalCrematorio" placeholder="Ej. Nombre del responsable"></label>
        </div>
      </div>
      <div id="inhumacionSection" class="conditional-card">
        <div class="subsection-title"><strong>Inhumación</strong><span>Visible únicamente cuando el servicio es Inhumación.</span></div>
        <label>Fecha y Hora Inhumación<input type="datetime-local" name="fechaHoraInhumacion" id="fechaHoraInhumacion" class="datetime-picker" step="300"></label>
      </div>
      <div id="operationEmpty" class="empty-state"><strong>Sin datos adicionales para este servicio</strong><span>Continúa al siguiente paso.</span></div>
    </section>

    <section class="form-section wizard-panel" data-step="3">
      <div class="section-title"><span>4</span><div><h2>Venta y servicios adicionales</h2><p>Captura el precio base y agrega únicamente los conceptos que correspondan.</p></div></div>
      <div class="form-grid grid-2">
        <label>Fecha Compra<input type="date" name="fechaCompra" id="fechaCompra" class="date-only-picker"></label>
        <label>Personal Venta<input name="personalVenta" placeholder="Ej. Nombre del asesor"></label>
        <label>Precio de Venta<input type="number" min="0" step="0.01" name="precioVenta" id="precioVenta" placeholder="Ej. 30000.00" required></label>
        <label>Servicios Adicionales
          <div class="services-list" id="extrasMenu" aria-label="Servicios adicionales"></div>
          <select name="serviciosExtra[]" id="serviciosExtra" multiple class="native-multi-hidden" aria-hidden="true" tabindex="-1"></select>
        </label>
      </div>
      <div id="extrasMontos" class="extras"></div>
      <div class="total-card"><span>Venta Total Servicio</span><strong id="ventaTotal">$0.00</strong></div>
    </section>

    <section class="form-section wizard-panel" data-step="4">
      <div class="section-title"><span>5</span><div><h2>Imagen de esquela</h2><p>Último paso del registro. La carga real seguirá deshabilitada durante el piloto.</p></div></div>
      <div class="upload-card image-upload-card">
        <div class="upload-heading">
          <div><strong>Imagen Esquela</strong><p class="hint">Selecciona una fotografía y ajusta el encuadre antes de continuar.</p></div>
          <label class="image-upload-button" for="esquelaInput"><span>＋</span> Seleccionar imagen</label>
        </div>
        <input type="file" name="esquela" id="esquelaInput" accept="image/*" class="file-input-hidden">
        <div id="imageEditor" class="image-editor hidden">
          <div class="crop-stage"><img id="cropImage" alt="Vista previa de la esquela"></div>
          <div class="crop-toolbar">
            <button type="button" class="secondary-button" id="zoomOutBtn">− Zoom</button>
            <button type="button" class="secondary-button" id="zoomInBtn">＋ Zoom</button>
            <button type="button" class="secondary-button" id="rotateBtn">↻ Girar</button>
            <button type="button" class="secondary-button" id="resetCropBtn">Restablecer</button>
            <button type="button" class="primary-button" id="applyCropBtn">Aplicar ajuste</button>
          </div>
        </div>
        <div id="imageResult" class="image-result hidden">
          <img id="croppedPreview" alt="Imagen ajustada">
          <div><strong>Imagen lista</strong><span>El encuadre procesado será el que se envíe cuando habilitemos SharePoint.</span>
          <button type="button" class="secondary-button" id="editCropBtn">Volver a ajustar</button></div>
        </div>
        <input type="hidden" name="esquelaProcesada" id="esquelaProcesada">
      </div>

      <div class="documents-card">
        <div class="subsection-title">
          <div><strong>Documentos del servicio</strong><span>Adjunta los documentos necesarios para el expediente.</span></div>
        </div>
        <div class="document-upload-grid">
          <label class="document-upload-box" for="certificadoDefuncion">
            <span class="document-icon">PDF</span>
            <span class="document-copy"><strong>Certificado de Defunción</strong><small id="certificadoDefuncionName">PDF o imagen</small></span>
            <span class="document-action">Seleccionar archivo</span>
          </label>
          <input type="file" name="certificadoDefuncion" id="certificadoDefuncion" accept=".pdf,image/*" class="file-input-hidden">

          <label class="document-upload-box" for="ordenInhumacionCremacion">
            <span class="document-icon">DOC</span>
            <span class="document-copy"><strong>Orden de Inhumación/Cremación</strong><small id="ordenInhumacionCremacionName">PDF o imagen</small></span>
            <span class="document-action">Seleccionar archivo</span>
          </label>
          <input type="file" name="ordenInhumacionCremacion" id="ordenInhumacionCremacion" accept=".pdf,image/*" class="file-input-hidden">
        </div>
      </div>

      <div class="preview-warning"><strong>Modo piloto</strong><span>Esta versión no escribe datos en SharePoint ni dispara Power Automate.</span></div>
    </section>

    <div class="wizard-actions">
      <div><button type="button" class="secondary-button" id="resetBtn">Limpiar</button></div>
      <div>
        <button type="button" class="secondary-button" id="prevStep">Anterior</button>
        <button type="button" class="primary-button" id="nextStep">Siguiente</button>
        <button type="submit" class="primary-button" id="submitBtn" disabled>Registrar servicio</button>
      </div>
    </div>
    <p id="status" class="status">Esta versión no escribe datos en SharePoint.</p>
  </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script src="capillas.js"></script>
</body>
</html>