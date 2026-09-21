# Registro de Servicios JJP

Herramienta interna de Jardines de Juan Pablo para capturar servicios de Capillas y Parque desde el Portal Interno JJP y registrar la información directamente en las listas existentes de SharePoint.

## Objetivo

Sustituir gradualmente la captura actual en Power Apps por formularios web integrados al Portal Interno, sin modificar:

- Las listas `Eventos Capillas` y `Eventos Parque`.
- Los flujos de Power Automate existentes.
- Los flujos del Dashboard de Dirección.
- Las automatizaciones posteriores que se disparan al crear un nuevo elemento en SharePoint.

## Arquitectura prevista

```text
Portal Interno JJP
  -> Registro de Servicios
      -> Capillas
          -> Formulario web
          -> Backend PHP
          -> SharePoint: Eventos Capillas
          -> Power Automate existente
      -> Parque
          -> Formulario web
          -> Backend PHP
          -> SharePoint: Eventos Parque
          -> Power Automate existente
```

## Fases

1. Crear repositorio y estructura base.
2. Levantar el esquema exacto de `Eventos Capillas`.
3. Replicar el formulario de Capillas.
4. Validar escritura en SharePoint sin alterar Power Automate.
5. Probar con un registro controlado.
6. Crear formulario de Parque.
7. Integrar la herramienta al Portal Interno.
8. Transición gradual desde Power Apps.

## Principio de compatibilidad

La herramienta debe crear un elemento nuevo con los mismos nombres internos de columnas, tipos de datos y valores que espera actualmente el proceso.

No se agregarán campos de control a las listas de producción durante esta primera fase.
