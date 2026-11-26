# WebZero Leads

WebZero Leads recibe las solicitudes del formulario de contacto de WebZero y permite gestionarlas desde el administrador de WordPress. Las solicitudes y las notas se guardan en dos tablas propias de la base de datos de WordPress.

## Funcionalidades

- Listado de solicitudes con búsqueda por nombre, correo o teléfono y filtros por estado, servicio y fecha de recepción.
- Panel con métricas por periodo, actividad diaria y solicitudes por servicio.
- Tablero comercial con las etapas **Nuevo**, **En seguimiento** y **Finalizado**.
- Ficha de cada solicitud con fecha de próximo contacto, notas internas y eliminación manual.

## Requisitos

- WordPress 6.6 o posterior.
- PHP 7.4 o posterior.
- Una base de datos de WordPress con InnoDB y permisos para crear o actualizar las tablas del plugin.
- La capacidad `manage_options` para acceder al panel y gestionar solicitudes.

## Instalación

1. Copia la carpeta del plugin a `wp-content/plugins/webzero-leads`.
2. En el administrador de WordPress, abre **Plugins** y activa **WebZero Leads**. La activación prepara las tablas. En multisitio, actívalo en cada sitio que lo necesite.
3. Abre **WebZero Leads** en el menú de administración para consultar el panel y el tablero.

## Integración con el formulario de WebZero

Usa una versión del tema WebZero que emita el filtro `webzero_contact_submission`. El tema muestra el formulario cuando la página publicada con el slug `contacto` contiene el shortcode `[webzero_contact_form]`.

El tema valida cada envío y pasa al plugin el nombre, el correo, el teléfono, el servicio y el mensaje. El plugin vuelve a validar los datos, limita la frecuencia de los envíos y guarda la solicitud con el estado **Nuevo**. El formulario muestra la confirmación después de que el plugin confirme el guardado.
