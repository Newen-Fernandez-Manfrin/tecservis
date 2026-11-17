<?php
/**
 * Notificaciones del usuario sobre sus reparaciones.
 * GET  -> lista y cantidad de avisos sin leer.
 * POST -> marca los avisos del usuario como leídos.
 */
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$u = usuario_actual();
if (!$u) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado.']);
    exit;
}

try {
    $idUsuario = (int) $u['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'marcar_leidas') {
        if (!csrf_valido()) {
            http_response_code(403);
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }
        $stmt = $pdo->prepare('UPDATE notificaciones SET leida = 1 WHERE id_usuario = ? AND leida = 0');
        $stmt->execute([$idUsuario]);
    }

    $stmt = $pdo->prepare(
        'SELECT id_notificacion, id_solicitud_reparacion, titulo, mensaje, leida, fecha
         FROM notificaciones
         WHERE id_usuario = ?
         ORDER BY id_notificacion DESC LIMIT 20'
    );
    $stmt->execute([$idUsuario]);
    $lista = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notificaciones WHERE id_usuario = ? AND leida = 0');
    $stmt->execute([$idUsuario]);
    $noLeidas = (int) $stmt->fetchColumn();

    echo json_encode([
        'no_leidas' => $noLeidas,
        'notificaciones' => $lista
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('TEC SERVIS - notificaciones_usuario.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron cargar las notificaciones.']);
}
