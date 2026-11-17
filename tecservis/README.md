# TEC SERVIS — Sitio de hardware y servicio técnico

Sitio web en PHP + MySQL con catálogo de productos, registro de pedidos y panel de administración.

## Acceso de administrador

| Campo | Valor |
|---|---|
| Usuario | `tecservisadmin` |
| Contraseña | `tecservis2026` |

El ingreso al sistema es **por usuario y contraseña**. El correo electrónico ya no se
usa para autenticarse: es sólo un dato de contacto opcional.

## Requisitos

- PHP 8.0 o superior (usa `str_contains`, `str_starts_with` y `match`/tipos modernos)
- MySQL 5.7 / MariaDB 10.4 o superior
- Extensiones `pdo_mysql`, `mbstring` y `gd` o `exif` para validar imágenes
- XAMPP, Laragon o WAMP sirven sin configuración adicional

## Instalación

1. Copiá la carpeta `tecservis` dentro de `htdocs` (XAMPP) o `www` (WAMP).
2. Iniciá Apache y MySQL.
3. Importá la base desde phpMyAdmin usando el archivo `sql/tecservis.sql`,
   o por consola: `mysql -u root -p < sql/tecservis.sql`.
   Es un único script (incluye el módulo de reparaciones ya integrado, no hace
   falta ningún otro archivo `.sql`).
   > Atención: el script hace `DROP DATABASE IF EXISTS tecservis`. Si ya tenías datos, sacá esa línea.
4. Si tu MySQL tiene usuario o contraseña distintos, editá el bloque **CONFIGURACIÓN** de `conexion.php`.
5. Entrá a `http://localhost/tecservis/`.
6. Verificá que la carpeta `imagenes/productos/` tenga permisos de escritura (las fotos que subas desde el panel se guardan ahí).

Antes de publicar el sitio, poné `MODO_DESARROLLO` en `false` dentro de `conexion.php`
y cambiá la contraseña del administrador desde la base de datos.

## Estructura

```
tecservis/
├── index.php              Portada: destacados, servicios y contacto
├── catalogo.php           Catálogo con buscador, filtro por rubro y orden
├── pedido.php             Registra el pedido en la base y descuenta stock
├── login.php              Acceso por usuario y contraseña
├── registro.php           Alta de cuentas de clientes
├── logout.php             Cierre de sesión
├── admin.php              Panel: productos, pedidos, rubros, marcas y usuarios
├── conexion.php           Configuración, conexión PDO y funciones comunes
├── partes/                Cabecera, pie, modal y tarjeta de producto reutilizables
├── css/tecservis.css      Hoja de estilos única y comentada
├── js/tecservis.js        Menú responsive y modal de compra
├── imagenes/              Fotos de productos (las nuevas van a imagenes/productos/)
└── sql/tecservis.sql      Base de datos completa con datos de ejemplo (script único)
```

## Compra y reparación sólo con sesión iniciada

Un visitante sin cuenta ve el botón como "Iniciar sesión para comprar" (en el catálogo)
o "Iniciar sesión para reparar" (en Servicio técnico) en vez del botón real, y si igual
llega a enviar alguno de los dos formularios a mano, `pedido.php` o
`solicitud_reparacion.php` lo rechazan y lo mandan al login. Al iniciar sesión vuelve
automáticamente a donde estaba (catálogo o la sección de servicio técnico).

## Notificaciones de pedidos en el panel

Cada pedido nuevo queda en la tabla `notificaciones`. En `admin.php` hay una campana 🔔
junto al usuario: muestra la cantidad de avisos sin leer y se actualiza sola cada 15
segundos (`js/admin.js` consulta `notificaciones.php`). Al abrir la campana se marcan
como leídos. No hace falta recargar la página para enterarse de una compra nueva.

## Cambiar la foto de fondo de la portada

Guardá tu imagen como `imagenes/fondo-hero.jpg` (1920 x 900 px aprox., menos de 500 KB).
Se aplica sola: no hay que tocar código. Si el archivo no existe, se muestra el degradado azul.
Para usar otro nombre o extensión, editá la línea `url("../imagenes/fondo-hero.jpg")`
dentro de la regla `.hero` en `css/tecservis.css`.

## Base de datos

| Tabla | Para qué sirve |
|---|---|
| `roles` | Usuario / Super Usuario |
| `usuarios` | Cuentas del sistema (login por `usuario`, email opcional) |
| `rubros` | Categorías de productos |
| `marcas` | Marcas de productos |
| `productos` | Catálogo, con precio, stock, imagen y estado |
| `pedidos` | Pedidos recibidos desde el sitio, con su estado |
| `vista_productos` | Vista que une productos con su rubro y marca |

## Qué se corrigió respecto de la versión anterior

**Acceso**
- El login pasó de correo electrónico a **usuario y contraseña**; se agregó la columna `usuario` (única).
- Administrador inicial `tecservisadmin` / `tecservis2026`, con hash bcrypt válido para PHP
  (el hash anterior estaba generado en otro lenguaje y `password_verify()` fallaba).
- Mensaje de error genérico, bloqueo temporal tras 5 intentos fallidos,
  `session_regenerate_id()` al ingresar y borrado real de la cookie al salir.
- Contraseñas de 8 caracteres como mínimo y re-hash automático si cambia el algoritmo.

**Seguridad**
- Token anti-CSRF en todos los formularios POST.
- Validación real de imágenes por tipo MIME (antes alcanzaba con renombrar la extensión).
- Escapado consistente con la función `e()`: se eliminaron los `htmlspecialchars(null)`
  que en PHP 8.1 disparan advertencias.
- Se quitó el `onclick` con `addslashes` del botón Comprar; ahora se usan `data-*` y delegación de eventos.
- Cookies de sesión `HttpOnly` y `SameSite=Lax`.

**Pedidos**
- Ya no se envían a un servicio externo por correo: se guardan en la tabla `pedidos`.
- El stock se descuenta dentro de una transacción con bloqueo de fila, así dos pedidos
  simultáneos no pueden dejarlo en negativo.
- El panel permite seguir cada pedido (pendiente, confirmado, entregado, cancelado).

**Panel de administración**
- Se agregó edición de productos, rubros y marcas (antes sólo se podía crear y borrar).
- Se puede mostrar u ocultar un producto sin eliminarlo.
- No deja borrar un rubro o marca que tenga productos asociados.
- Nueva solapa de pedidos y de usuarios (activar/desactivar y cambiar rol).
- Patrón POST → Redirect → GET: al refrescar ya no se repite la última acción.

**Organización del proyecto**
- Un solo script de base de datos (`sql/tecservis.sql`): se eliminó `actualizar_reparaciones.sql`
  porque duplicaba la misma tabla que ya trae el script principal.
- Solicitar una reparación ahora exige sesión iniciada, igual que comprar.

**Catálogo y presentación**
- El buscador existía en el código pero no tenía formulario: ahora funciona,
  con filtro por categoría y orden por precio o antigüedad.
- Cabecera, pie, modal y tarjeta de producto se unificaron en `partes/` (menos código repetido).
- Menú responsive real y hoja de estilos reordenada y comentada.
- Se corrigió la referencia a una imagen de fondo inexistente y se unificó el logo entre páginas.


## Módulo de solicitudes de reparación

- Formulario público en Inicio → Servicio técnico.
- Endpoint: `solicitud_reparacion.php`.
- Solicitudes guardadas en `solicitudes_reparacion` con estado inicial `pendiente`.
- Fotos opcionales en `imagenes/reparaciones/`.
- Requiere sesión iniciada, igual que la compra (ver sección anterior).
- Las nuevas solicitudes generan una notificación visible en el panel admin.
- En `admin.php?tab=reparaciones` se pueden revisar los datos, abrir la foto y cambiar el estado.
- La tabla `solicitudes_reparacion` ya está incluida en `sql/tecservis.sql`: no hace falta ningún script aparte.

### Base de datos vieja y fotos pesadas

- `conexion.php` ejecuta `asegurar_esquema()`: si la base fue importada con una versión anterior,
  crea la tabla `solicitudes_reparacion` o le agrega las columnas que falten (`id_usuario`,
  `presupuesto`, `detalle_presupuesto`, `fecha_actualizacion`, estados nuevos) y deja
  `notificaciones` lista para avisos de reparación. No hace falta importar nada a mano.
- Si la foto falla (peso, permisos), la solicitud se guarda igual sin foto y se avisa el motivo.
- Si el formulario con foto es rechazado, revisá `upload_max_filesize` y `post_max_size` en `php.ini`
  (XAMPP trae 2M / 8M). El `.htaccess` los sube a 6M / 8M cuando PHP corre como módulo de Apache.
- Con `MODO_DESARROLLO = true`, un error de base al enviar la solicitud muestra el detalle técnico.
