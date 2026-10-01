# Arquitectura actual

## Fuente de verdad

- Capillas: lista SharePoint `Eventos Capillas`.
- Parque: lista SharePoint `Eventos Parque`.

## Capillas

Registro de Servicios ya ejecuta directamente el proceso operativo que anteriormente dependía del flujo de Power Automate.

Responsabilidades del módulo:

1. Validar la captura.
2. Crear un único elemento en `Eventos Capillas`.
3. Adjuntar documentos e imágenes.
4. Crear calendario cuando corresponde.
5. Generar placa, carta, tablas informativas y esquela.
6. Enviar los correos operativos.
7. Disparar TellMeBye de forma independiente.
8. Registrar borradores y publicaciones en el almacenamiento del módulo.

El flujo antiguo de Power Automate de Eventos Capillas debe estar desactivado para evitar duplicidades.

## Modos de operación

- Capillas conserva su modo de prueba restringido para validaciones controladas.
- Parque opera únicamente en producción desde la herramienta oficial.

## Portal

Rutas productivas:

- `/registro-servicios/` -> selector de área.
- `/registro-servicios/mis-servicios.php` -> servicios de Capillas.
- `/registro-servicios/capillas.php` -> captura de Capillas.
- `/registro-servicios/mis-servicios.php?area=parque` -> servicios de Parque.
- `/registro-servicios/parque.php` -> captura oficial de Parque.

## Despliegue

La publicación se controla desde `Portal-Interno-JJP` mediante GitHub Actions y FTPS.

El despliegue oficial publica Registro de Servicios en `/registro-servicios/`. El workflow aislado de preview fue retirado al liberar Parque a producción.


## Organización del repositorio

- Raíz: páginas navegables del módulo.
- `api/`: endpoints HTTP llamados por JavaScript.
- `includes/`: lógica PHP interna que no debe navegarse directamente desde la interfaz.
- `assets/css/`: hojas de estilo.
- `assets/js/`: JavaScript del frontend.
- `assets/esquelas/`: fondos y recursos visuales de esquela.
- `docs/`: documentación técnica.

Esta separación evita archivos monolíticos y mantiene cada responsabilidad aislada sin dejar decenas de archivos funcionales mezclados en la raíz.
