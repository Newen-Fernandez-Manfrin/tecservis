<?php
/**
 * TEC SERVIS - Conexión a la base de datos y funciones comunes.
 *
 * Este archivo se incluye en todas las páginas del sitio.
 * Modificá únicamente el bloque de CONFIGURACIÓN.
 */

declare(strict_types=1);

/* ========================= CONFIGURACIÓN ========================= */

const DB_HOST = 'localhost';
const DB_NOMBRE = 'tecservis';
const DB_USUARIO = 'root';
const DB_PASSWORD = '';

/** Mostrar errores detallados. Poné false cuando el sitio esté publicado. */
const MODO_DESARROLLO = true;

/** Nombre del rol con permisos de administración. */
const ROL_ADMIN = 'Super Usuario';

/** Datos de contacto del negocio (se usan en el pie y en la sección de atención). */
const NEGOCIO = [
    'nombre'    => 'TEC SERVIS',
    'lema'      => 'Hardware · Gaming · Servicio técnico',
    'whatsapp'  => '+54 9 223 339-2549',
    'telefono'  => '2233392549',
    'ubicacion' => 'Mar del Plata, Buenos Aires',
    'horario'   => 'Lunes a sábados de 9 a 19 h',
];

/* ======================= FIN CONFIGURACIÓN ======================= */

ini_set('display_errors', MODO_DESARROLLO ? '1' : '0');
error_reporting(MODO_DESARROLLO ? E_ALL : 0);
date_default_timezone_set('America/Argentina/Buenos_Aires');

/* --------------------------- Sesión ------------------------------ */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ------------------------- Conexión PDO -------------------------- */

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NOMBRE . ';charset=utf8mb4',
        DB_USUARIO,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(503);
    $detalle = MODO_DESARROLLO ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') : '';
    exit(
        '<!doctype html><html lang="es"><meta charset="utf-8">'
        . '<title>Error de conexión</title>'
        . '<div style="font-family:system-ui,Arial,sans-serif;max-width:620px;margin:80px auto;padding:28px;'
        . 'border:1px solid #e3e8ef;border-radius:12px;background:#fff">'
        . '<h1 style="color:#10151c;font-size:20px;margin:0 0 10px">No se pudo conectar con la base de datos</h1>'
        . '<p style="color:#5b6775;line-height:1.6;margin:0">Verificá que el servidor MySQL esté iniciado y que la base '
        . '<strong>' . DB_NOMBRE . '</strong> haya sido importada desde <code>sql/tecservis.sql</code>.</p>'
        . ($detalle ? '<pre style="margin-top:14px;background:#f5f7fa;padding:12px;border-radius:8px;'
            . 'font-size:12px;white-space:pre-wrap;color:#a92323">' . $detalle . '</pre>' : '')
        . '</div>'
    );
}

/* ------------------------ Funciones útiles ----------------------- */

/** Escapa texto para imprimirlo en HTML de forma segura. */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Formatea un importe en pesos argentinos. */
function moneda(mixed $monto): string
{
    $monto = (float) $monto;
    $decimales = fmod($monto, 1.0) === 0.0 ? 0 : 2;
    return '$' . number_format($monto, $decimales, ',', '.');
}

/** Devuelve el usuario logueado o null. */
function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/** Indica si el usuario logueado es administrador. */
function es_admin(): bool
{
    return (usuario_actual()['rol'] ?? '') === ROL_ADMIN;
}

/** Corta la ejecución y manda al login si no hay un administrador logueado. */
function requiere_admin(): void
{
    if (!es_admin()) {
        header('Location: login.php?requerido=1');
        exit;
    }
}

/** Token anti-CSRF de la sesión actual. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto con el token, para incluir en cada formulario POST. */
function csrf_campo(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** Valida el token recibido por POST. */
function csrf_valido(): bool
{
    return isset($_POST['_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string) $_POST['_token']);
}

/** Guarda un mensaje para mostrarlo después de una redirección. */
function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

/** Recupera y borra el mensaje flash pendiente. */
function flash_tomar(): ?array
{
    $mensaje = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $mensaje;
}

/** Ruta pública de la imagen de un producto (o null si no tiene). */
function imagen_producto(?string $archivo): ?string
{
    if ($archivo === null || trim($archivo) === '') {
        return null;
    }
    return 'imagenes/' . str_replace('%2F', '/', rawurlencode($archivo));
}

/* ------------- Esquema del módulo de reparaciones y avisos ------------- */

/**
 * Deja la base lista para las reparaciones aunque se haya importado con una
 * versión anterior de sql/tecservis.sql: tabla o columnas que faltan, y una
 * tabla `notificaciones` que no acepta avisos de reparación (id_pedido NOT NULL
 * o sin la columna id_solicitud_reparacion).
 *
 * Sólo ejecuta cambios si detecta algo faltante, se comprueba una vez por sesión
 * y nunca interrumpe la página: cualquier fallo queda en el log de errores.
 */
function asegurar_esquema(PDO $pdo): void
{
    if (($_SESSION['esquema_ok'] ?? 0) === 2) {
        return;
    }

    $todoOk = true;

    try {
        $existentes = [];
        $consulta = $pdo->query(
            "SELECT TABLE_NAME AS tabla, COLUMN_NAME AS columna,
                    COLUMN_TYPE AS tipo, IS_NULLABLE AS nulo
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('solicitudes_reparacion', 'notificaciones')"
        );
        foreach ($consulta->fetchAll() as $fila) {
            $existentes[$fila['tabla']][$fila['columna']] = $fila;
        }

        /* ---------------------- solicitudes_reparacion ---------------------- */
        $enumEstados = "ENUM('pendiente','en_revision','presupuestado','reparado','cancelado')";
        $columnas = [
            'id_usuario'          => 'INT UNSIGNED NULL',
            'cliente'             => "VARCHAR(120) NOT NULL DEFAULT ''",
            'email'               => "VARCHAR(120) NOT NULL DEFAULT ''",
            'telefono'            => "VARCHAR(40) NOT NULL DEFAULT ''",
            'tipo_dispositivo'    => "VARCHAR(50) NOT NULL DEFAULT ''",
            'marca_modelo'        => "VARCHAR(160) NOT NULL DEFAULT ''",
            'descripcion'         => 'TEXT NULL',
            'imagen'              => 'VARCHAR(255) NULL',
            'estado'              => $enumEstados . " NOT NULL DEFAULT 'pendiente'",
            'presupuesto'         => 'DECIMAL(12,2) NULL',
            'detalle_presupuesto' => 'TEXT NULL',
            'fecha'               => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'fecha_actualizacion' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];
        $reparaciones = $existentes['solicitudes_reparacion'] ?? [];

        if (!$reparaciones) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS solicitudes_reparacion (
                   id_solicitud        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                   id_usuario          INT UNSIGNED NULL,
                   cliente             VARCHAR(120) NOT NULL,
                   email               VARCHAR(120) NOT NULL,
                   telefono            VARCHAR(40) NOT NULL,
                   tipo_dispositivo    VARCHAR(50) NOT NULL,
                   marca_modelo        VARCHAR(160) NOT NULL,
                   descripcion         TEXT NOT NULL,
                   imagen              VARCHAR(255) NULL,
                   estado              {$columnas['estado']},
                   presupuesto         DECIMAL(12,2) NULL,
                   detalle_presupuesto TEXT NULL,
                   fecha               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                   fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                   INDEX idx_reparaciones_estado (estado),
                   INDEX idx_reparaciones_fecha (fecha)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            try {
                $pdo->exec(
                    'ALTER TABLE solicitudes_reparacion
                     ADD CONSTRAINT fk_reparaciones_usuario FOREIGN KEY (id_usuario)
                     REFERENCES usuarios(id_usuario) ON UPDATE CASCADE ON DELETE SET NULL'
                );
            } catch (PDOException $e) {
                /* La clave foránea es opcional: sin ella el módulo funciona igual. */
            }
        } else {
            foreach ($columnas as $columna => $definicion) {
                if (!isset($reparaciones[$columna])) {
                    $pdo->exec("ALTER TABLE solicitudes_reparacion ADD COLUMN {$columna} {$definicion}");
                }
            }

            $tipoEstado = strtolower((string) ($reparaciones['estado']['tipo'] ?? ''));
            if (str_starts_with($tipoEstado, 'enum(') && $tipoEstado !== strtolower($enumEstados)) {
                try {
                    $pdo->exec("ALTER TABLE solicitudes_reparacion MODIFY estado {$columnas['estado']}");
                } catch (PDOException $e) {
                    $todoOk = false;
                    error_log('TEC SERVIS - no se pudo ajustar el ENUM de estado (hay filas con estados viejos): ' . $e->getMessage());
                }
            }
        }

        /* --------------------------- notificaciones --------------------------- */
        $avisos = $existentes['notificaciones'] ?? [];

        if (!$avisos) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS notificaciones (
                   id_notificacion         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                   id_usuario              INT UNSIGNED NULL,
                   id_pedido               INT UNSIGNED NULL,
                   id_solicitud_reparacion INT UNSIGNED NULL,
                   titulo                  VARCHAR(120) NOT NULL,
                   mensaje                 TEXT NOT NULL,
                   leida                   TINYINT(1) NOT NULL DEFAULT 0,
                   fecha                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                   INDEX idx_notificaciones_leida (leida)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } else {
            if (!isset($avisos['id_solicitud_reparacion'])) {
                $pdo->exec('ALTER TABLE notificaciones ADD COLUMN id_solicitud_reparacion INT UNSIGNED NULL');
            }
            /* id_usuario identifica al cliente que debe recibir el aviso. NULL = aviso del panel admin. */
            if (!isset($avisos['id_usuario'])) {
                $pdo->exec('ALTER TABLE notificaciones ADD COLUMN id_usuario INT UNSIGNED NULL AFTER id_notificacion');
            }
            /* Una reparación no tiene pedido: id_pedido tiene que aceptar NULL. */
            if (isset($avisos['id_pedido']) && $avisos['id_pedido']['nulo'] === 'NO') {
                $pdo->exec('ALTER TABLE notificaciones MODIFY id_pedido ' . $avisos['id_pedido']['tipo'] . ' NULL');
            }
        }
    } catch (Throwable $e) {
        $todoOk = false;
        error_log('TEC SERVIS - no se pudo verificar el esquema de reparaciones: ' . $e->getMessage());
    }

    if ($todoOk) {
        $_SESSION['esquema_ok'] = 2;
    }
}

asegurar_esquema($pdo);
