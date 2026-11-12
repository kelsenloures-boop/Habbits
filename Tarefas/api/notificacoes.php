<?php
/**
 * API AJAX — /api/notificacoes.php (dados da sessão)
 *
 *  GET ?depois=N            → processa lembretes vencidos + lista de notificações.
 *                             'novas' = notificações com id > N (para o cliente avisar).
 *  POST {acao, id?}         → acao: 'marcar_todas_lidas' | 'marcar_lida' | 'apagar' | 'limpar_todas'
 *  DELETE ?id=N             → eliminar uma notificação
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

function estado_notificacoes(int $uid): array {
    $nots = notificacoes_carregar($uid, 100);
    return [
        'ok'           => true,
        'notificacoes' => $nots,
        'naoLidas'     => notificacoes_contar_nao_lidas($uid),
    ];
}

/* ============================================================
 * GET — processar + listar
 * ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    processar_lembretes($uid);

    $estado = estado_notificacoes($uid);
    $estado['alarmes'] = alarmes_ativas($uid);

    // 'novas' = criadas depois do último id conhecido pelo cliente
    $depois = (int)($_GET['depois'] ?? 0);
    $estado['novas'] = array_values(array_filter(
        $estado['notificacoes'],
        fn($n) => (int)$n['id'] > $depois
    ));

    resposta($estado);
}

/* ============================================================
 * POST — ações
 * ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = (string)($body['acao'] ?? '');

    if ($acao === 'marcar_todas_lidas') {
        $afetadas = notificacoes_marcar_todas_lidas($uid);
        resposta(['afetadas' => $afetadas] + estado_notificacoes($uid));
    }

    if ($acao === 'marcar_lida' && !empty($body['id'])) {
        $ok = notificacoes_marcar_lida((int)$body['id'], $uid);
        resposta(['marcada' => $ok] + estado_notificacoes($uid));
    }

    if ($acao === 'apagar' && !empty($body['id'])) {
        $ok = notificacoes_apagar((int)$body['id'], $uid);
        resposta(['apagada' => $ok] + estado_notificacoes($uid));
    }

    if ($acao === 'limpar_todas') {
        $total = notificacoes_limpar($uid);
        resposta(['eliminadas' => $total] + estado_notificacoes($uid));
    }

    resposta(['ok' => false, 'erro' => "Ação {$acao} desconhecida."] + estado_notificacoes($uid), 422);
}

/* ============================================================
 * DELETE — eliminar uma notificação
 * ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    $ok = $id > 0 ? notificacoes_apagar($id, $uid) : false;
    resposta(['apagada' => $ok] + estado_notificacoes($uid));
}

resposta(['ok' => false, 'erro' => 'Método não suportado.'], 405);
