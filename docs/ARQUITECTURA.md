# Arquitectura inicial

## Fuente de verdad

Se conservarán como fuente de verdad las listas existentes:

- Capillas: `Eventos Capillas`
- Parque: `Eventos Parque`

## Regla de integración

La nueva herramienta no ejecutará Power Automate directamente.

Su responsabilidad será:

1. Validar la captura.
2. Crear UN nuevo elemento completo en la lista correspondiente.
3. Confirmar al usuario si SharePoint aceptó el registro.

Power Automate continuará disparándose por el mecanismo que ya existe al crear un nuevo elemento en SharePoint.

## Patrón técnico

La herramienta seguirá el mismo enfoque ya utilizado en herramientas internas como Reportes:

- Autenticación mediante la sesión del Portal Interno.
- PHP en backend.
- Credenciales de Microsoft almacenadas fuera del repositorio.
- Escritura server-side a SharePoint.
- Protección CSRF para formularios POST.
- Ningún secreto dentro de JavaScript o HTML.

## Primera implementación

Se desarrollará primero Capillas.

Antes de crear el formulario visual se levantará el esquema real de la lista `Eventos Capillas`:

- Nombre visible.
- Nombre interno.
- Tipo de columna.
- Campos requeridos.
- Opciones de Choice.
- Lookup / Persona.
- Fecha y hora.
- Sí/No.
- Adjuntos.
- Campos utilizados por Power Automate.
- Campos utilizados por Tellmebye y otros bots.

Parque permanecerá sin cambios hasta completar la validación de Capillas.
