<?php
/**
 * API AJAX — /api/notificacoes.php (dados da sessão)
 *
 *  GET ?depois=N            → processa lembretes vencidos + lista de notificações.
 *                             'novas' = notificações com id > N (para o cliente avisar).
 *  POST {acao, id?}         → acao: 'marcar_todas_lidas' | 'marcar_lida'
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
    $nots = notificacoes_carregar($uid, 20);
    return [
        'ok'           => true,
        'notificacoes' => $nots,
        'naoLidas'     => count(array_filter($nots, fn($n) => empty($n['lida']))),
    ];
}

/* ============================================================
 * Lembretes vencidos → notificações (disparados 1 vez só)
 * ========================================================== */
function processar_lembretes(int $uid): void {
    foreach (tarefas_lembretes_vencidos($uid) as $t) {
        $minutos = (int)$t['minutos'];

        if ($minutos < 0) {
            $texto = 'Está atrasada! Era às ' . $t['hora'] . '.';
            $icon  = 'alert';
        } elseif ($minutos === 0) {
            $texto = 'Começa agora (' . $t['hora'] . ').';
            $icon  = 'clock';
        } else {
            $texto = 'Começa daqui a ' . $minutos . ' min (' . $t['hora'] . ').';
            $icon  = 'clock';
        }

        notificar($uid, 'Lembrete: ' . mb_substr($t['titulo'], 0, 60), $texto, $icon);
        tarefas_marcar_lembrete_enviado((int)$t['id']);
    }
}

/* ============================================================
 * GET — processar + listar
 * ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    processar_lembretes($uid);

    $estado = estado_notificacoes($uid);

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
    $acao = $body['acao'] ?? '';

    if ($acao === 'marcar_todas_lidas') {
        notificacoes_marcar_todas_lidas($uid);
        resposta(estado_notificacoes($uid));
    }

    if ($acao === 'marcar_lida' && !empty($body['id'])) {
        notificacoes_marcar_lida((int)$body['id'], $uid);
        resposta(estado_notificacoes($uid));
    }

    resposta(['ok' => false, 'erro' => "Ação {$acao} desconhecida."], 422);
}

/* ============================================================
 * DELETE — eliminar uma notificação
 * ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) notificacoes_apagar($id, $uid);
    resposta(estado_notificacoes($uid));
}

resposta(['ok' => false, 'erro' => 'Método não suportado.'], 405);
