<?php
/**
 * API AJAX — /api/alarmes.php
 *
 *  GET  → { ok, alarmes: [...], ativas: [...] }
 *  POST { acao: criar|atualizar|alternar|apagar|adiar|desligar, ... }
 *
 * Sessão como as restantes APIs. SQLite é a fonte de verdade.
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

function estado_alarmes(int $uid): array {
    alarmes_processar($uid);

    $lista = [];
    foreach (alarmes_listar($uid) as $a) {
        $a['proxima'] = alarme_proxima($a);
        $a['proxima_texto'] = alarme_proxima_texto($a);
        $a['recorrencia_rotulo'] = alarme_recorrencia_rotulo($a);
        $lista[] = $a;
    }

    return [
        'ok'      => true,
        'alarmes' => $lista,
        'ativas'  => alarmes_ativas($uid),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    resposta(estado_alarmes($uid));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = (string)($body['acao'] ?? '');
    $id = (int)($body['id'] ?? 0);

    switch ($acao) {
        case 'criar':
            $r = alarmes_criar($uid, $body);
            if (!$r['ok']) resposta($r + estado_alarmes($uid), 422);
            resposta(['ok' => true, 'id' => $r['id']] + estado_alarmes($uid), 201);

        case 'atualizar':
            $r = alarmes_atualizar($id, $uid, $body);
            if (!$r['ok']) resposta($r + estado_alarmes($uid), 422);
            resposta(estado_alarmes($uid));

        case 'alternar':
            $r = alarmes_alternar($id, $uid);
            if (!$r['ok']) resposta($r + estado_alarmes($uid), 404);
            resposta(estado_alarmes($uid));

        case 'apagar':
            if (!alarmes_apagar($id, $uid)) resposta(['ok' => false, 'erro' => 'Alarme não encontrado.'] + estado_alarmes($uid), 404);
            resposta(['ok' => true, 'apagado' => true] + estado_alarmes($uid));

        case 'adiar':
            $r = alarmes_adiar((int)($body['ocorrencia_id'] ?? 0), $uid, isset($body['minutos']) ? (int)$body['minutos'] : null);
            resposta($r + estado_alarmes($uid), $r['ok'] ? 200 : 422);

        case 'desligar':
            $r = alarmes_desligar((int)($body['ocorrencia_id'] ?? 0), $uid);
            resposta($r + estado_alarmes($uid), $r['ok'] ? 200 : 422);
    }

    resposta(['ok' => false, 'erro' => "Ação {$acao} desconhecida."] + estado_alarmes($uid), 422);
}

resposta(['ok' => false, 'erro' => 'Método não suportado.'], 405);
