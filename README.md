# Registro de Servicios JJP

Herramienta interna de Jardines de Juan Pablo para capturar y dar seguimiento a servicios operativos desde el Portal Interno.

## Estado actual

### Capillas
Producción activa.

Flujo principal:

```text
Portal Interno JJP
  -> Registro de Servicios
      -> Capillas
          -> Formulario web
          -> SharePoint: Eventos Capillas
          -> Calendario
          -> Correo operativo
          -> Generación de placa / carta / imágenes / esquela
          -> TellMeBye
```

El flujo anterior de Power Automate de Eventos Capillas debe permanecer desactivado para evitar duplicidad de registros, correos y automatizaciones.

### Parque
Visible en el selector como **En desarrollo**. No tiene captura productiva habilitada todavía.

## Modo normal y modo prueba

- Producción es el modo predeterminado.
- `ModoPrueba = No` en registros normales.
- Solo `sistemas@juanpablo.com.mx` y `gabriel.guerra@juanpablo.com.mx` pueden activar el modo prueba desde el formulario.
- En modo prueba:
  - `ModoPrueba = Sí`
  - correo y calendario quedan identificados como prueba
  - TellMeBye usa el modo de prueba configurado

## Destinatarios

Los destinatarios productivos del correo están centralizados en `registro-servicio.php` con soporte para sobreescribirlos desde la configuración privada del portal.

Claves soportadas:

- `registro_servicios_email_recipients`
- `registro_servicios_plate_email_recipients`
- `registro_servicios_test_email_recipients`

Siguiente mejora recomendada: administrar destinatarios desde SharePoint o desde un módulo restringido del Portal Interno.

## Estructura principal

```text
Registro-Servicios-JJP/
├── index.php
├── capillas.php
├── mis-servicios.php
├── api/
│   ├── registro-servicio.php
│   ├── guardar-borrador.php
│   └── mis-servicios-api.php
├── includes/
│   ├── registro-sharepoint.php
│   ├── registro-calendario.php
│   ├── registro-imagenes.php
│   ├── registro-esquela.php
│   ├── registro-placa.php
│   ├── registro-placa-template.php
│   ├── registro-carta.php
│   ├── registro-storage.php
│   └── registro-tellmebye.php
├── assets/
│   ├── css/
│   ├── js/
│   └── esquelas/
└── docs/
```

Las páginas visibles permanecen en la raíz. Los endpoints se concentran en `api/`, la lógica interna en `includes/` y los recursos web en `assets/`.

## Seguridad

- No almacenar contraseñas, tokens ni secretos en este repositorio.
- Las credenciales privadas permanecen fuera del repo, en la configuración del Portal.
- Los endpoints temporales de diagnóstico fueron retirados al cerrar la fase de Capillas.
