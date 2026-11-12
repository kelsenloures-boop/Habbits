<?php
/**
 * Habbits — ponte CLI para a app desktop (Electron).
 * Executa a mesma lógica PHP/SQLite sem depender de sessão HTTP.
 *
 * Uso:
 *   php bin/desktop.php prefs        --uid=1
 *   php bin/desktop.php notificacoes --uid=1 [--depois=0]
 *   php bin/desktop.php processar    --uid=1
 *
 * Saída: JSON numa única linha (UTF-8).
 */

require_once __DIR__ . '/../inc/dados.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/repositorio.php';
if (is_file(__DIR__ . '/../inc/alarmes.php')) {
    require_once __DIR__ . '/../inc/alarmes.php';
}

header('Content-Type: application/json; charset=utf-8');

function argumento(array $argv, string $nome, string $padrao = ''): string {
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $nome . '=')) {
            return substr($arg, strlen($nome) + 3);
        }
    }
    return $padrao;
}

function sair(array $payload, int $codigo = 0): never {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit($codigo);
}

$comando = $argv[1] ?? '';
$uid = max(1, (int)argumento($argv, 'uid', '1'));

try {
    switch ($comando) {
        case 'utilizadores':
            $ids = db()->query("SELECT id FROM utilizadores ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
            sair(['ok' => true, 'utilizadores' => array_map('intval', $ids)]);

        case 'prefs':
            sair(['ok' => true, 'preferencias' => preferencias_obter($uid)]);

        case 'notificacoes':
            processar_lembretes($uid);
            $ativas = function_exists('alarmes_processar')
                ? (alarmes_processar($uid)['alarmes'] ?? [])
                : [];

            $depois = max(0, (int)argumento($argv, 'depois', '0'));
            $st = db()->prepare("
                SELECT id, icon, titulo, texto, tempo, lida, som, tarefa_id
                FROM notificacoes
                WHERE utilizador_id = :uid AND id > :depois
                ORDER BY id ASC
                LIMIT 50
            ");
            $st->execute([':uid' => $uid, ':depois' => $depois]);
            $novas = $st->fetchAll();

            $ultimo = $depois;
            foreach ($novas as $n) $ultimo = max($ultimo, (int)$n['id']);

            sair([
                'ok'             => true,
                'preferencias'   => preferencias_obter($uid),
                'notificacoes'   => $novas,
                'naoLidas'       => notificacoes_contar_nao_lidas($uid),
                'ultimoId'       => $ultimo,
                'alarmes'        => $ativas,
            ]);

        case 'processar':
            processar_lembretes($uid);
            $resumo = function_exists('alarmes_processar') ? alarmes_processar($uid) : ['ok' => true, 'alarmes' => []];
            sair($resumo);

        case 'ocorrencia-fechar':
            $ocorrencia = (int)argumento($argv, 'ocorrencia', '0');
            $atual = $ocorrencia > 0 && function_exists('alarmes_ocorrencia')
                ? alarmes_ocorrencia($ocorrencia, $uid)
                : null;

            if (!$atual) {
                sair(['ok' => false, 'erro' => 'Ocorrência inválida.']);
            }

            // Fechar a janela sem ação desliga o alarme, mas nunca cancela um adiamento.
            $resultado = ($atual['estado'] === 'a_tocar')
                ? alarmes_desligar($ocorrencia, $uid)
                : ['ok' => true, 'estado' => $atual['estado']];
            sair($resultado);

        default:
            sair(['ok' => false, 'erro' => 'Comando desconhecido. Usa: prefs | notificacoes | processar'], 2);
    }
} catch (Throwable $e) {
    sair(['ok' => false, 'erro' => $e->getMessage()], 1);
}
