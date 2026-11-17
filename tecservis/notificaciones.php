<?php
/**
 * Devuelve las notificaciones del panel en formato JSON.
 * GET  -> lista de notificaciones y cantidad de no leídas.
 * POST -> accion=marcar_leidas, marca todas como leídas (requiere token CSRF).
 */
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

if (!es_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado.']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'marcar_leidas') {
        if (!csrf_valido()) {
            http_response_code(403);
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }
        $pdo->exec("UPDATE notificaciones SET leida = 1 WHERE leida = 0 AND id_usuario IS NULL");
    }

    $noLeidas = (int) $pdo->query("SELECT COUNT(*) FROM notificaciones WHERE leida = 0 AND id_usuario IS NULL")->fetchColumn();
    $lista = $pdo->query(
        "SELECT id_notificacion, id_pedido, id_solicitud_reparacion, titulo, mensaje, leida, fecha
         FROM notificaciones WHERE id_usuario IS NULL ORDER BY id_notificacion DESC LIMIT 20"
    )->fetchAll();
} catch (PDOException $e) {
    /* Se responde vacío en vez de romper el panel, pero el motivo queda en el log. */
    error_log('TEC SERVIS - notificaciones.php: ' . $e->getMessage());
    $noLeidas = 0;
    $lista = [];
}

echo json_encode([
    'no_leidas'      => $noLeidas,
    'notificaciones' => $lista,
], JSON_UNESCAPED_UNICODE);
