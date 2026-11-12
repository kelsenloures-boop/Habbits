<?php
/**
 * API AJAX — /api/preferencias.php
 *
 *  GET   → preferências de notificações do utilizador autenticado.
 *  POST  → atualiza apenas os campos enviados (JSON no corpo).
 *
 * Resposta: { ok: true, preferencias: { canal, sons, ... } }
 */

require_once __DIR__ . '/../inc/bootstrap.php';
exigir_login_api();
$uid = uid_atual();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function resposta(array $payload, int $codigo = 200): never {
    http_response_code($codigo);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    resposta(['ok' => true, 'preferencias' => preferencias_obter($uid)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        resposta(['ok' => false, 'erro' => 'Corpo inválido.'], 422);
    }

    try {
        $prefs = preferencias_guardar($uid, $body);
    } catch (Throwable $e) {
        resposta(['ok' => false, 'erro' => 'Não foi possível guardar as preferências.'], 500);
    }

    resposta(['ok' => true, 'preferencias' => $prefs]);
}

resposta(['ok' => false, 'erro' => 'Método não suportado.'], 405);
