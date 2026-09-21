<?php
declare(strict_types=1);
?><!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registro de Servicios | Jardines de Juan Pablo</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
  <div>
    <p class="eyebrow">Jardines de Juan Pablo</p>
    <h1>Registro de Servicios</h1>
  </div>
  <span class="badge">Piloto - sin envío a SharePoint</span>
</header>
<main class="shell">
  <section class="panel">
    <div class="panel-head"><h2>Capillas</h2><p>Primera versión para validar interfaz y reglas antes de conectar producción.</p></div>
    <form id="capillasForm" novalidate>
      <fieldset><legend>Servicio</legend><div class="grid">
        <label>Número de Referencia<input name="numeroReferencia" required></label>
        <label>Tipo de Servicio<select name="servicio" id="servicio" required></select></label>
        <label>Ubicación Servicio Capillas<select name="ubicacion" id="ubicacion" required></select></label>
        <label id="wrapSala">Sala<select name="sala" id="sala"></select></label>
        <label id="wrapInicio">Fecha y Hora Inicio<input type="datetime-local" name="inicio" id="inicio"></label>
        <label id="wrapTermino">Fecha y Hora Término<input type="datetime-local" name="termino" id="termino"></label>
        <label id="wrapExequiaToggle" class="toggle-row">Lleva exequia?<input type="checkbox" name="llevaExequia" id="llevaExequia"></label>
        <label id="wrapTiempo">Tiempo de Capillas<select name="tiempoCapillas" id="tiempoCapillas"></select></label>
        <label id="wrapExequia">Fecha y Hora Exequia<input type="datetime-local" name="horaExequia" id="horaExequia"></label>
        <label>Previsión/Uso Inmediato<select name="prevision" id="prevision" required></select></label>
        <label id="wrapTipoAtaud">Tipo de Ataúd/Urna<select name="tipoAtaud" id="tipoAtaud"></select></label>
        <label>Número de Servicio<input name="numeroServicio" id="numeroServicio" required></label>
      </div>
      <div class="summary-grid">
        <div><span>Código de Servicio</span><strong id="codigoServicio">—</strong></div>
        <div><span>Ataúd/Urna</span><strong id="codigoAtaud">—</strong></div>
        <div class="wide"><span>Referencia</span><strong id="referenciaPreview">—</strong></div>
      </div>
      <label class="toggle-row">Requiere Placa de Urna?<input type="checkbox" name="requierePlaca" id="requierePlaca"></label>
      </fieldset>

      <fieldset><legend>Fallecido y operación</legend><div class="grid">
        <label>Titular Responsable<input name="titular" required></label>
        <label>Nombre de Fallecido(a)<input name="fallecido" required></label>
        <label>Fecha Nacimiento<input type="date" name="fechaNacimiento" id="fechaNacimiento" required></label>
        <label>Fecha y Hora Defunción<input type="datetime-local" name="fechaDefuncion" id="fechaDefuncion" required></label>
        <label>Sexo<select name="sexo" id="sexo"><option value="">Seleccionar</option><option>Masculino</option><option>Femenino</option></select></label>
        <label>Edad<input name="edad" id="edad" readonly></label>
        <label>Ubicación Destino Final<input name="destinoFinal" required></label>
        <label>Embalsamador<select name="embalsamador" id="embalsamador" required></select></label>
        <label>Personal Rescate 1<input name="rescate1" required></label>
        <label>Personal Rescate 2<input name="rescate2"></label>
        <label>Ubicación de Rescate<input name="ubicacionRescate" required></label>
        <label>Motivo de Fallecimiento<input name="motivo" required></label>
      </div></fieldset>

      <fieldset id="crematorioSection"><legend>Crematorio</legend><div class="grid">
        <label>Referencia Crematorio<input name="referenciaCrematorio" id="referenciaCrematorio"></label>
        <label>Fecha y Hora Inicio Crematorio<input type="datetime-local" name="inicioCrematorio" id="inicioCrematorio"></label>
        <label>Personal de Crematorio<input name="personalCrematorio" id="personalCrematorio"></label>
      </div></fieldset>

      <fieldset><legend>Venta y servicios adicionales</legend><div class="grid">
        <label>Fecha Compra<input type="date" name="fechaCompra"></label>
        <label>Personal Venta<input name="personalVenta"></label>
        <label>Precio de Venta<input type="number" min="0" step="0.01" name="precioVenta" id="precioVenta" required></label>
        <label>Servicios Adicionales<select name="serviciosExtra[]" id="serviciosExtra" multiple size="7"></select></label>
      </div>
      <div id="extrasMontos" class="extras"></div>
      <div class="total-card"><span>Venta Total Servicio</span><strong id="ventaTotal">$0.00</strong></div>
      </fieldset>

      <fieldset id="inhumacionSection"><legend>Inhumación</legend>
        <label>Fecha y Hora Inhumación<input type="datetime-local" name="fechaHoraInhumacion" id="fechaHoraInhumacion"></label>
      </fieldset>

      <fieldset><legend>Imagen Esquela</legend>
        <input type="file" name="esquela" accept="image/*">
        <p class="hint">La carga real se conectará en una fase posterior.</p>
      </fieldset>

      <div class="actions">
        <button type="button" class="secondary" id="resetBtn">Limpiar</button>
        <button type="submit" class="primary" disabled>Registrar servicio (pendiente conexión)</button>
      </div>
      <p id="status" class="status">Esta versión no escribe datos en SharePoint.</p>
    </form>
  </section>
</main>
<script src="capillas.js"></script>
</body></html>