# Esquema de Eventos Capillas

Fuente: configuración visible de la lista SharePoint `Eventos Capillas` compartida el 2026-09-21.

> Importante: los nombres internos de SharePoint solo se registran cuando ya están confirmados por código existente. Los demás quedan como `POR_CONFIRMAR`; no deben inferirse por el nombre visible.

## 1. Campos de captura del servicio

| Nombre visible | Tipo SharePoint | Requerido | Nombre interno confirmado | Uso inicial |
|---|---|---:|---|---|
| Numero de Referencia | Single line of text | Sí | `field_1` | Formulario |
| Nombre de Fallecido (a) | Single line of text | Sí | `field_2` | Formulario |
| Edad | Number | Sí | `field_3` | Formulario |
| Fecha Nacimiento | Date and Time | Sí | `field_4` | Formulario |
| Titular Responsable | Single line of text | Sí | POR_CONFIRMAR | Formulario |
| Prevision/Uso Inmediato | Choice | Sí | `field_9` | Formulario |
| Fecha Compra | Date and Time | No | POR_CONFIRMAR | Formulario condicional |
| Personal Venta | Single line of text | No | POR_CONFIRMAR | Formulario condicional |
| Precio de Venta | Currency | Sí | POR_CONFIRMAR | Formulario |
| Misa y Coro | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Flores | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Cobro por Enfermedad | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Horas Extras | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Cambio de Ataud | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Sobrepeso | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Cambio de Capilla | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Cambio a Cremacion | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Cambio de Urna | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Retiro de Marcapasos | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Resguardo | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Traslado | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Destape | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Impuestos | Currency | No | POR_CONFIRMAR | Formulario / cargos |
| Venta Total Servicio | Currency | No | POR_CONFIRMAR | Formulario / cálculo por confirmar |
| Personal Rescate 1 | Single line of text | Sí | POR_CONFIRMAR | Formulario |
| Personal Rescate 2 | Single line of text | No | POR_CONFIRMAR | Formulario |
| Motivo de Fallecimiento | Single line of text | Sí | `field_30` | Formulario |
| Ubicación de Rescate | Single line of text | Sí | `field_31` | Formulario |
| Servicio | Choice | Sí | `field_32` | Formulario |
| Fecha y Hora Inicio | Date and Time | No | `field_36` | Formulario |
| Fecha y Hora Termino | Date and Time | No | `field_37` | Formulario |
| Ubicación Servicio Capillas | Choice | Sí | `field_39` | Formulario |
| Sala | Choice | No | `field_40` | Formulario |
| Hora Misa | Date and Time | No | `field_44` | Formulario condicional |
| Tiempo de Capillas | Choice | No | POR_CONFIRMAR | Formulario |
| Codigo | Single line of text | Sí | POR_CONFIRMAR | Formulario |
| Ataud/Urna | Single line of text | No | POR_CONFIRMAR | Formulario |
| Numero de Servicio | Single line of text | Sí | `field_48` | Formulario |
| Tipo de Ataud/Urna | Choice | No | POR_CONFIRMAR | Formulario |
| Ubicación Post Capillas | Single line of text | Sí | `field_52` | Formulario |
| Referencia Crematorio | Single line of text | No | POR_CONFIRMAR | Formulario condicional |
| Personal de Crematorio | Single line of text | No | POR_CONFIRMAR | Formulario condicional |
| Embalsamador | Choice | Sí | `field_69` | Formulario |
| Fecha y Hora Defuncion | Date and Time | Sí | `FechaDefuncion` | Formulario |
| Referencia | Single line of text | No | POR_CONFIRMAR | Revisar diferencia vs Numero de Referencia |
| Fecha y Hora Inicio Crematorio | Date and Time | No | `FechayHoraInicioCrematorio` | Formulario condicional |
| Servicios Extra | Choice | Sí | POR_CONFIRMAR | Formulario |
| Misa | Yes/No | No | `Misa` | Formulario |
| Requiere Placa | Yes/No | No | POR_CONFIRMAR | Formulario |
| Placa Granito | Yes/No | No | POR_CONFIRMAR | Formulario |
| Fecha y Hora Inhumación | Date and Time | No | POR_CONFIRMAR | Formulario condicional |
| Sexo | Choice | No | POR_CONFIRMAR | Formulario |

## 2. Columnas calculadas

Estas columnas no deben capturarse manualmente desde el formulario.

| Nombre visible | Tipo |
|---|---|
| FechaHoraInicio_Mostrar | Calculated |
| FechaHoraTermino_Mostrar | Calculated |
| FechaHoraMisa_Mostrar | Calculated |
| FechaHoraDefuncion_Mostrar | Calculated |
| FechaHoraCremacion_Mostrar | Calculated |

## 3. Columnas técnicas de automatización

Estas columnas son estados/resultados de procesos existentes. La nueva herramienta de captura no debe asignarlas durante el alta, salvo que una revisión posterior demuestre que el flujo actual exige un valor inicial explícito.

| Nombre visible | Tipo |
|---|---|
| ModoPrueba | Yes/No |
| TellmebyeEstatus | Choice |
| TellmebyeModoBot | Choice |
| TellmebyeError | Multiple lines of text |
| TellmebyeUltimaEjecucion | Date and Time |
| TellmebyeRunId | Single line of text |
| TellmebyePreviewUrl | Single line of text |
| TellmebyeId | Single line of text |
| TellmebyePdfVerticalNombre | Single line of text |
| TellmebyeJpgVerticalNombre | Single line of text |
| TellmebyeArchivoCorreoListo | Single line of text |
| PlacaEstatus | Single line of text |
| PlacaError | Multiple lines of text |
| PlacaPngNombre | Single line of text |
| PlacaArchivoCorreoListo | Single line of text |
| PlacaUltimaEjecucion | Date and Time |

## 4. Columnas de sistema

No forman parte del formulario:

- Title
- Modified
- Created
- Created By
- Modified By

## 5. Nombres internos ya confirmados por el bot Tellmebye

El bot existente utiliza estos campos de la lista:

- `field_1` → Numero de Referencia
- `field_2` → Nombre de Fallecido (a)
- `field_3` → Edad
- `field_4` → Fecha Nacimiento
- `field_9` → Prevision/Uso Inmediato
- `field_30` → Motivo de Fallecimiento
- `field_31` → Ubicación de Rescate
- `field_32` → Servicio
- `field_36` → Fecha y Hora Inicio
- `field_37` → Fecha y Hora Termino
- `field_39` → Ubicación Servicio Capillas
- `field_40` → Sala
- `field_44` → Hora Misa
- `field_48` → Numero de Servicio
- `field_52` → Ubicación Post Capillas
- `field_69` → Embalsamador
- `FechaDefuncion` → Fecha y Hora Defuncion
- `FechayHoraInicioCrematorio` → Fecha y Hora Inicio Crematorio
- `Misa` → Misa

## 6. Pendientes antes de escribir a producción

1. Obtener el nombre interno de todos los campos marcados `POR_CONFIRMAR`.
2. Obtener todas las opciones de cada columna Choice.
3. Revisar si `Venta Total Servicio` se captura o se calcula en Power Apps.
4. Revisar la diferencia funcional entre `Numero de Referencia` y `Referencia`.
5. Revisar qué campos aparecen/son obligatorios condicionalmente según:
   - Previsión vs Uso Inmediato.
   - Cremación vs Inhumación.
   - Misa sí/no.
   - Ubicación de servicio.
   - Sala.
   - Servicios extra.
6. Confirmar cómo se maneja actualmente la esquela/imagen y cualquier archivo asociado.
7. No realizar una creación real en `Eventos Capillas` hasta completar estas validaciones.
