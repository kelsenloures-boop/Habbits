<?php
/**
 * API AJAX — /api/tarefas.php  (SQLite via PDO, dados da sessão)
 *
 *  GET  ?estado=&prioridade=&cat=&busca=&de=&ate=&vista=    → lista + estatísticas
 *  POST           → criar tarefa        (JSON no corpo)
 *  PATCH          → atualizar tarefa    (JSON no corpo: id + campos)
 *  DELETE ?id=N   → apagar tarefa
 *
 * Todas as respostas: { ok: bool, erro?: string, tarefas: [...], stats: {...} }
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

/* ---------- entrada ---------- */
$metodo = $_SERVER['REQUEST_METHOD'];
$body = [];
if (in_array($metodo, ['POST', 'PATCH', 'PUT'], true)) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
}

/* Utilizado por todas as respostas */
/* 'lembrete' em minutos antes (−1 desliga); valores fora do intervalo → 15 */
function lembrete_valido(mixed $valor): int {
    $valor = (int)$valor;
    return ($valor >= -1 && $valor <= 1440) ? $valor : 15;
}

function estado_atual(int $uid): array {
    $tarefas = repositorio_carregar($uid);
    return ['ok' => true, 'tarefas' => $tarefas, 'stats' => estatisticas($tarefas)];
}

/* ============================================================
 * GET — listar (com filtros opcionais)
 * ========================================================== */
if ($metodo === 'GET') {
    if (isset($_GET['busca']) || isset($_GET['estado']) || isset($_GET['prioridade']) || isset($_GET['cat']) || isset($_GET['vista'])) {
        $tarefasFiltradas = tarefas_com_filtros($uid, $_GET);
    } elseif (isset($_GET['mes'])) {
        $tarefasFiltradas = tarefas_do_mes($uid, (string)$_GET['mes']);
    } else {
        $tarefasFiltradas = repositorio_carregar($uid);
    }
    resposta(['ok' => true, 'tarefas' => $tarefasFiltradas, 'stats' => estatisticas(repositorio_carregar($uid))]);
}

/* ============================================================
 * POST — criar
 * ========================================================== */
if ($metodo === 'POST') {
    $titulo = trim((string)($body['titulo'] ?? ''));
    $data   = trim((string)($body['data'] ?? ''));

    if ($titulo === '' || $data === '') {
        resposta(['ok' => false, 'erro' => 'Título e data são obrigatórios.'] + estado_atual($uid), 422);
    }

    $id = tarefas_criar([
        'titulo'     => mb_substr($titulo, 0, 120),
        'data'       => $data,
        'hora'       => preg_match('/^\d{2}:\d{2}$/', (string)($body['hora'] ?? '')) ? $body['hora'] : '08:00',
        'prioridade' => in_array($body['prioridade'] ?? '', prioridades_validas(), true) ? $body['prioridade'] : 'media',
        'cat'        => mb_substr(trim((string)($body['cat'] ?? '')) ?: 'Pessoal', 0, 40),
        'lembrete'   => lembrete_valido($body['lembrete'] ?? 15),
    ], $uid);

    notificar($uid, 'Nova tarefa criada', '"' . mb_substr($titulo, 0, 60) . '" agendada para ' . date('d/m', strtotime($data)), 'check');
    resposta(estado_atual($uid), 201);
}

/* ============================================================
 * PATCH — atualizar (editar, concluir/desmarcar, prioridade…)
 * ========================================================== */
if ($metodo === 'PATCH') {
    $id = (int)($body['id'] ?? 0);
    $tarefa = tarefas_encontrar($id, $uid);

    if (!$tarefa) {
        resposta(['ok' => false, 'erro' => "Tarefa #{$id} não encontrada."] + estado_atual($uid), 404);
    }

    $campos = [];

    foreach (['titulo', 'data', 'hora', 'prioridade', 'cat', 'estado', 'lembrete'] as $campo) {
        if (!array_key_exists($campo, $body)) continue;
        $valor = $body[$campo];

        switch ($campo) {
            case 'titulo':
                $valor = trim((string)$valor);
                if ($valor !== '') $campos['titulo'] = mb_substr($valor, 0, 120);
                break;

            case 'data':
                $d = DateTime::createFromFormat('Y-m-d', (string)$valor);
                if ($d && $d->format('Y-m-d') === $valor) $campos['data'] = $valor;
                break;

            case 'hora':
                if (preg_match('/^\d{2}:\d{2}$/', (string)$valor)) $campos['hora'] = $valor;
                break;

            case 'prioridade':
                if (in_array($valor, prioridades_validas(), true)) $campos['prioridade'] = $valor;
                break;

            case 'cat':
                $campos['cat'] = mb_substr(trim((string)$valor) ?: 'Pessoal', 0, 40);
                break;

            case 'estado':
                if (in_array($valor, ['pendente', 'concluida'], true)) $campos['estado'] = $valor;
                break;

            case 'lembrete':
                $campos['lembrete'] = lembrete_valido($valor);
                break;
        }
    }

    // Mudança de data/hora/lembrete → o lembrete volta a poder disparar
    if (isset($campos['data']) || isset($campos['hora']) || isset($campos['lembrete'])) {
        $campos['lembrete_enviado'] = 0;
    }

    if ($campos) {
        tarefas_atualizar($id, $uid, $campos);

        if (isset($campos['estado']) && $campos['estado'] !== $tarefa['estado']) {
            notificar(
                $uid,
                $campos['estado'] === 'concluida' ? 'Tarefa concluída' : 'Tarefa reaberta',
                '"' . mb_substr($tarefa['titulo'], 0, 60) . '"',
                $campos['estado'] === 'concluida' ? 'check' : 'clock'
            );
        }
    }

    resposta(estado_atual($uid));
}

/* ============================================================
 * DELETE — apagar
 * ========================================================== */
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    $titulo = tarefas_apagar($id, $uid);

    if ($titulo === null) {
        resposta(['ok' => false, 'erro' => "Tarefa #{$id} não encontrada."] + estado_atual($uid), 404);
    }
    notificar($uid, 'Tarefa apagada', '"' . mb_substr($titulo, 0, 60) . '"', 'alert');
    resposta(estado_atual($uid));
}

/* método não suportado */
resposta(['ok' => false, 'erro' => "Método {$metodo} não suportado."] + estado_atual($uid), 405);
