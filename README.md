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
Producción activa.

Flujo principal:

```text
Portal Interno JJP
  -> Registro de Servicios
      -> Parque
          -> Formulario web
          -> SharePoint: Eventos Parque
          -> Calendario
          -> Correo operativo
          -> Imágenes informativas
          -> Flores / placas según reglas
          -> Cartas de Reubicación, Exhumación o Retiro de Cenizas cuando corresponda
```

Parque opera únicamente en modo productivo desde la ruta oficial `/registro-servicios/`. El flujo de preview fue retirado.

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
│   ├── registro-parque.php
│   ├── guardar-borrador.php
│   └── mis-servicios-api.php
├── includes/
│   ├── registro-sharepoint.php
│   ├── registro-calendario.php
│   ├── registro-parque-calendario.php
│   ├── registro-parque-correo.php
│   ├── registro-parque-documentos.php
│   ├── registro-parque-imagenes.php
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
│   ├── templates/parque/
│   └── esquelas/
└── docs/
```

Las páginas visibles permanecen en la raíz. Los endpoints se concentran en `api/`, la lógica interna en `includes/` y los recursos web en `assets/`.

## Seguridad

- No almacenar contraseñas, tokens ni secretos en este repositorio.
- Las credenciales privadas permanecen fuera del repo, en la configuración del Portal.
- Los endpoints temporales de diagnóstico fueron retirados al cerrar la fase de Capillas.
