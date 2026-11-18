# Reglas de negocio - Capillas

Documento derivado del comportamiento actual de Power Apps y de la configuración visible de la lista \`Eventos Capillas\`.

## Variables funcionales

- \`varSinVelacion\`: verdadero cuando Servicio = \`Cremación Directa (sin velación)\`.
- \`varTieneExequia\`: verdadero cuando el usuario activa \`Lleva exequia?\`.
- \`varEsRentaCapillas\`: verdadero cuando Servicio = \`Renta de Capillas\`.

## Reglas de visibilidad y obligatoriedad

### Sala
- Visible: \`!varSinVelacion\`
- Required: \`!varSinVelacion\`
- Si no aplica, se guarda vacío.

### Inhumación con ubicación Domicilio
- Servicio: `Inhumación` (no se modifica el tipo ni el código VI).
- Ubicación Servicio Capillas: opción `Domicilio` exclusiva para `Inhumación`.
- `Sala` y `Tiempo de Capillas`: ocultos, no obligatorios y se guardan vacíos.
- Fechas y horas `Inicio` / `Término`: siguen siendo obligatorias.
- `Fecha y Hora Inhumación`, destino final, exequia (si aplica), documentos, correos y calendario: mismos flujos existentes.
- **Requisito de SharePoint antes de usar la opción:** agregar `Domicilio` a la columna de opciones `Ubicación Servicio Capillas` (`field_39`) en la lista `Eventos Capillas` del sitio Operaciones. El despliegue de código no modifica las opciones de la columna.
- No se crea un campo de dirección postal nuevo con este cambio; `Ubicación de Rescate` y `Ubicación Destino Final` conservan su significado anterior.

### Fecha y Hora Inicio / Término
- Ocultas y no requeridas para Cremación Directa (sin velación).
- Si aplican, se construye un DateTime con fecha, hora y minuto.

### Lleva exequia? / Misa
- Oculto para Cremación Directa (sin velación).

### Fecha y Hora Exequia
- Visible/Required cuando: \`!varSinVelacion && varTieneExequia\`.

### Tiempo de Capillas
- Visible/Required cuando \`!varSinVelacion\`.

### Tipo de Ataud/Urna
- Visible/Required cuando \`!varEsRentaCapillas\`.

### Datos de crematorio
Referencia Crematorio, Fecha y Hora Inicio Crematorio y Personal de Crematorio son visibles/requeridos únicamente para:
- Cremación
- Cremación Directa (con velación)
- Cremación Directa (sin velación)

### Fecha y Hora Inhumación
Visible cuando Servicio = \`Inhumación\`.

## Códigos de servicio

| Servicio | Código |
|---|---|
| Inhumación | VI |
| Cremación | VC |
| Cremación Directa (con velación) | CD |
| Cremación Directa (sin velación) | CD |
| Inhumación Directa | ID |
| Renta de Capillas | RENTCAP |
| Velación a Domicilio | VD |

## Códigos Ataúd/Urna

| Tipo | Código |
|---|---|
| Ataud Madera Basico | ATMADBA |
| Ataud Madera de Lujo | ATMADLX |
| Ataud Madera Exclusivo | ATMADEX |
| Ataud Metalico Basico | ATMETBA |
| Ataud Metalico Exclusivo | ATMETEX |
| Ataud Metalico Intermedio | ATMETEX |
| Ataud Metalico Jumbo | ATMETEX |
| Urna Basica | URNABAS |
| Urna Marmol | URNABAS |
| Urna Madera | URNABAS |
| Urna Madera RA | URNABAS |

## Referencia final

- Renta de Capillas: CODIGO - NUMERO_SERVICIO
- Otros servicios: CODIGO - ATAUD_URNA - NUMERO_SERVICIO

## Edad

La edad se calcula entre Fecha Nacimiento y Fecha Defunción, restando 1 si el cumpleaños del año de defunción todavía no había ocurrido.

## Precio de Venta

El campo acepta formato monetario y se guarda como número eliminando símbolos y separadores.

## Servicios adicionales

El selector permite múltiples valores. La lista actual incluye:
- Misa y Coro
- Flores
- Cobro por Enfermedad
- Horas Extras
- Cambio de Ataud
- Cambio a Cremacion
- Cambio de Capilla
- Cambio de Urna
- Traslado
- Resguardo
- Retiro de Marcapasos
- Sobrepeso
- Destape
- Impuestos
- No Aplica

## Venta Total Servicio

La Power App calcula Precio de Venta + importes de todos los servicios adicionales seleccionados.
Solo suma el importe de un concepto si ese concepto fue seleccionado.

## Pendientes antes de escritura real

- Confirmar nombres internos de todos los campos POR_CONFIRMAR.
- Confirmar OnSelect completo del botón Guardar.
- Confirmar manejo de adjuntos/esquela.
- No habilitar POST a SharePoint hasta terminar estas validaciones.
