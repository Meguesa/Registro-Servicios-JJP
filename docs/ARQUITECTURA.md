# Arquitectura actual

## Fuente de verdad

- Capillas: lista SharePoint `Eventos Capillas`.
- Parque: lista SharePoint `Eventos Parque` cuando se habilite su módulo.

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

## Modo prueba

El modo normal es el predeterminado. Solo Sistemas y Gabriel Guerra pueden activar el modo prueba desde el formulario.

El backend valida nuevamente el permiso, por lo que no depende únicamente del control visual del navegador.

## Portal

Rutas productivas:

- `/registro-servicios/` -> selector de área.
- `/registro-servicios/mis-servicios.php` -> servicios de Capillas.
- `/registro-servicios/capillas.php` -> captura de Capillas.

Parque permanece visible como **En desarrollo**.

## Despliegue

La publicación se controla desde `Portal-Interno-JJP` mediante GitHub Actions y FTPS.

Se conserva un workflow de preview para validaciones controladas, pero los endpoints PHP de diagnóstico temporal ya no forman parte del despliegue.


## Organización del repositorio

- Raíz: páginas navegables del módulo.
- `api/`: endpoints HTTP llamados por JavaScript.
- `includes/`: lógica PHP interna que no debe navegarse directamente desde la interfaz.
- `assets/css/`: hojas de estilo.
- `assets/js/`: JavaScript del frontend.
- `assets/esquelas/`: fondos y recursos visuales de esquela.
- `docs/`: documentación técnica.

Esta separación evita archivos monolíticos y mantiene cada responsabilidad aislada sin dejar decenas de archivos funcionales mezclados en la raíz.
