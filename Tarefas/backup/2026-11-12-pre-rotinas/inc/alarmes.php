<?php
/**
 * Habbits — domínio dos Alarmes.
 * Toda a lógica em hora local (SQLite localtime) e em SQLite como fonte de verdade.
 */

require_once __DIR__ . '/db.php';

const ALARMES_TOLERANCIA_MIN = 10;   // atraso máximo para ainda tocar ao arrancar
const ALARMES_MAX_ADIAMENTOS = 5;

/* ================================================================
 * CRUD
 * ============================================================== */

function alarmes_listar(int $uid): array {
    $st = db()->prepare("
        SELECT *
        FROM alarmes
        WHERE utilizador_id = :uid
        ORDER BY ativo DESC, hora ASC, id ASC
    ");
    $st->execute([':uid' => $uid]);
    return $st->fetchAll();
}

function alarmes_encontrar(int $id, int $uid): ?array {
    $st = db()->prepare("SELECT * FROM alarmes WHERE id = :id AND utilizador_id = :uid");
    $st->execute([':id' => $id, ':uid' => $uid]);
    $a = $st->fetch();
    return $a ?: null;
}

/** Normaliza e valida os campos de um alarme. Devolve [campos, erro]. */
function alarmes_validar(array $dados, ?array $atual = null): array {
    // Formulários HTML enviam dias_semana[]; a API envia uma string "1,3,5".
    $diasRecebidos = $dados['dias_semana'] ?? $atual['dias_semana'] ?? '';
    if (is_array($diasRecebidos)) {
        $diasRecebidos = implode(',', array_map('intval', $diasRecebidos));
    }

    $final = [
        'titulo'        => trim((string)($dados['titulo'] ?? $atual['titulo'] ?? '')),
        'mensagem'      => trim((string)($dados['mensagem'] ?? $atual['mensagem'] ?? '')),
        'hora'          => (string)($dados['hora'] ?? $atual['hora'] ?? ''),
        'data'          => $dados['data'] ?? $atual['data'] ?? null,
        'recorrencia'   => (string)($dados['recorrencia'] ?? $atual['recorrencia'] ?? 'diario'),
        'dias_semana'   => (string)$diasRecebidos,
        'som'           => array_key_exists('som', $dados) ? (!empty($dados['som']) ? 1 : 0) : (int)($atual['som'] ?? 1),
        'ativo'         => array_key_exists('ativo', $dados) ? (!empty($dados['ativo']) ? 1 : 0) : (int)($atual['ativo'] ?? 1),
        'adiamento_min' => max(1, min(60, (int)($dados['adiamento_min'] ?? $atual['adiamento_min'] ?? 10))),
    ];

    if ($final['titulo'] === '') {
        return [null, 'O título do alarme é obrigatório.'];
    }
    $final['titulo'] = mb_substr($final['titulo'], 0, 120);
    $final['mensagem'] = mb_substr($final['mensagem'], 0, 240);

    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $final['hora'])) {
        return [null, 'A hora tem de estar no formato HH:MM.'];
    }

    if (!in_array($final['recorrencia'], ['unico', 'diario', 'semanal', 'dias'], true)) {
        $final['recorrencia'] = 'diario';
    }

    if ($final['recorrencia'] === 'unico') {
        $data = (string)($final['data'] ?? '');
        $d = DateTime::createFromFormat('Y-m-d', $data);
        if (!$d || $d->format('Y-m-d') !== $data) {
            return [null, 'Escolhe uma data válida para o alarme único.'];
        }
        $final['data'] = $data;
        $final['dias_semana'] = '';
    } else {
        $final['data'] = null;
        if (in_array($final['recorrencia'], ['semanal', 'dias'], true)) {
            $dias = array_values(array_unique(array_filter(
                array_map('intval', explode(',', $final['dias_semana'])),
                fn($d) => $d >= 1 && $d <= 7
            )));
            sort($dias);
            if (!$dias) return [null, 'Escolhe pelo menos um dia da semana.'];
            $final['dias_semana'] = implode(',', $dias);
        } else {
            $final['dias_semana'] = '';
        }
    }

    return [$final, null];
}

function alarmes_criar(int $uid, array $dados): array {
    [$campos, $erro] = alarmes_validar($dados);
    if ($erro) return ['ok' => false, 'erro' => $erro];

    $st = db()->prepare("
        INSERT INTO alarmes
            (utilizador_id, titulo, mensagem, hora, data, recorrencia, dias_semana,
             som, ativo, adiamento_min, criado_em, atualizado_em)
        VALUES
            (:uid, :titulo, :mensagem, :hora, :data, :recorrencia, :dias,
             :som, :ativo, :adiamento, :agora, :agora)
    ");
    $st->execute([
        ':uid'         => $uid,
        ':titulo'      => $campos['titulo'],
        ':mensagem'    => $campos['mensagem'],
        ':hora'        => $campos['hora'],
        ':data'        => $campos['data'],
        ':recorrencia' => $campos['recorrencia'],
        ':dias'        => $campos['dias_semana'],
        ':som'         => $campos['som'],
        ':ativo'       => $campos['ativo'],
        ':adiamento'   => $campos['adiamento_min'],
        ':agora'       => momento_local(),
    ]);

    return ['ok' => true, 'id' => (int)db()->lastInsertId()];
}

function alarmes_atualizar(int $id, int $uid, array $dados): array {
    $atual = alarmes_encontrar($id, $uid);
    if (!$atual) return ['ok' => false, 'erro' => 'Alarme não encontrado.'];

    [$campos, $erro] = alarmes_validar($dados, $atual);
    if ($erro) return ['ok' => false, 'erro' => $erro];

    $st = db()->prepare("
        UPDATE alarmes SET
            titulo = :titulo,
            mensagem = :mensagem,
            hora = :hora,
            data = :data,
            recorrencia = :recorrencia,
            dias_semana = :dias,
            som = :som,
            ativo = :ativo,
            adiamento_min = :adiamento,
            atualizado_em = :agora
        WHERE id = :id AND utilizador_id = :uid
    ");
    $st->execute([
        ':titulo'      => $campos['titulo'],
        ':mensagem'    => $campos['mensagem'],
        ':hora'        => $campos['hora'],
        ':data'        => $campos['data'],
        ':recorrencia' => $campos['recorrencia'],
        ':dias'        => $campos['dias_semana'],
        ':som'         => $campos['som'],
        ':ativo'       => $campos['ativo'],
        ':adiamento'   => $campos['adiamento_min'],
        ':agora'       => momento_local(),
        ':id'          => $id,
        ':uid'         => $uid,
    ]);

    return ['ok' => true];
}

function alarmes_apagar(int $id, int $uid): bool {
    $st = db()->prepare("DELETE FROM alarmes WHERE id = :id AND utilizador_id = :uid");
    $st->execute([':id' => $id, ':uid' => $uid]);
    if ($st->rowCount() === 0) return false;

    $st = db()->prepare("DELETE FROM alarme_ocorrencias WHERE alarme_id = :id AND utilizador_id = :uid");
    $st->execute([':id' => $id, ':uid' => $uid]);
    return true;
}

function alarmes_alternar(int $id, int $uid): array {
    $a = alarmes_encontrar($id, $uid);
    if (!$a) return ['ok' => false, 'erro' => 'Alarme não encontrado.'];
    return alarmes_atualizar($id, $uid, ['ativo' => empty($a['ativo']) ? 1 : 0]);
}

/* ================================================================
 * Recorrência / ocorrências
 * ============================================================== */

/** O alarme aplica-se a este dia? (1=segunda … 7=domingo) */
function alarme_aplica_no_dia(array $a, string $dia): bool {
    switch ($a['recorrencia']) {
        case 'unico':
            return (string)($a['data'] ?? '') === $dia;
        case 'diario':
            return true;
        case 'semanal':
        case 'dias':
            $dias = array_map('intval', array_filter(explode(',', (string)$a['dias_semana'])));
            return in_array((int)date('N', strtotime($dia)), $dias, true);
        default:
            return false;
    }
}

/** SQLite local: hoje + N dias, em hora local. */
function dias_local_mais(int $dias): string {
    $st = db()->prepare("SELECT date('now','localtime', :mod)");
    $st->execute([':mod' => sprintf('%+d days', $dias)]);
    return (string)$st->fetchColumn();
}

/** Próxima ocorrência a partir de agora no formato Y-m-d H:i:s, ou null. */
function alarme_proxima(array $a): ?string {
    if (empty($a['ativo'])) return null;

    $agora = momento_local();

    // 15 dias é suficiente para qualquer recorrência semanal.
    for ($i = 0; $i <= 15; $i++) {
        $dia = dias_local_mais($i);
        if (!alarme_aplica_no_dia($a, $dia)) continue;

        $candidato = $dia . ' ' . $a['hora'] . ':00';
        if ($candidato >= $agora) return $candidato;
    }

    return null;
}

/** Texto curto da próxima ocorrência. */
function alarme_proxima_texto(array $a): string {
    $proxima = alarme_proxima($a);
    if ($proxima === null) return empty($a['ativo']) ? 'Desligado' : 'Sem próxima ocorrência';

    $hoje = data_local();
    $amanha = dias_local_mais(1);
    $dia = substr($proxima, 0, 10);
    $hora = substr($proxima, 11, 5);

    if ($dia === $hoje) return 'Hoje às ' . $hora;
    if ($dia === $amanha) return 'Amanhã às ' . $hora;

    return (int)substr($proxima, 8, 2) . '/' . (int)substr($proxima, 5, 2) . ' às ' . $hora;
}

function alarme_recorrencia_rotulo(array $a): string {
    return match ($a['recorrencia']) {
        'unico'   => 'Uma vez',
        'semanal' => 'Semanal',
        'dias'    => 'Dias escolhidos',
        default   => 'Todos os dias',
    };
}

/** SQLite local: agora + N minutos, em hora local. */
function momento_local_mais(int $minutos): string {
    $st = db()->prepare("SELECT strftime('%Y-%m-%d %H:%M:%S','now','localtime', :mod)");
    $st->execute([':mod' => sprintf('%+d minutes', $minutos)]);
    return (string)$st->fetchColumn();
}

/**
 * Processa alarmes vencidos:
 *  - cria ocorrências para os disparos dentro da tolerância;
 *  - acorda ocorrências adiadas cujo prazo chegou;
 *  - marca como perdidas as ocorrências antigas;
 *  - devolve as ocorrências a tocar.
 */
function alarmes_processar(int $uid, int $tolerancia = ALARMES_TOLERANCIA_MIN): array {
    $agora = momento_local();
    $limite = momento_local_mais(-$tolerancia);
    $hoje = data_local();
    $ontem = dias_local_mais(-1);

    $inserir = db()->prepare("
        INSERT OR IGNORE INTO alarme_ocorrencias
            (alarme_id, utilizador_id, previsto_para, estado, criado_em)
        VALUES (:alarme, :uid, :previsto, 'a_tocar', :agora)
    ");

    foreach (alarmes_listar($uid) as $a) {
        if (empty($a['ativo'])) continue;
        if ($a['hora'] < '00:00' || $a['hora'] > '23:59') continue;

        foreach ([$hoje, $ontem] as $dia) {
            if (!alarme_aplica_no_dia($a, $dia)) continue;

            $previsto = $dia . ' ' . $a['hora'] . ':00';
            if ($previsto > $agora || $previsto < $limite) continue;

            $inserir->execute([
                ':alarme'   => (int)$a['id'],
                ':uid'      => $uid,
                ':previsto' => $previsto,
                ':agora'    => $agora,
            ]);
        }
    }

    // Adiamentos cujo prazo chegou voltam a tocar.
    $st = db()->prepare("
        UPDATE alarme_ocorrencias
        SET estado = 'a_tocar', adiado_para = NULL
        WHERE utilizador_id = :uid AND estado = 'adiado' AND adiado_para <= :agora
    ");
    $st->execute([':uid' => $uid, ':agora' => $agora]);

    // Disparos antigos sem adiamento ficam perdidos (não tocam fora de horas).
    $st = db()->prepare("
        UPDATE alarme_ocorrencias
        SET estado = 'perdido'
        WHERE utilizador_id = :uid AND estado = 'a_tocar'
          AND adiamentos = 0 AND previsto_para < :limite
    ");
    $st->execute([':uid' => $uid, ':limite' => $limite]);

    return ['ok' => true, 'alarmes' => alarmes_ativas($uid)];
}

/** Ocorrências a tocar, com os dados do alarme. */
function alarmes_ativas(int $uid): array {
    $st = db()->prepare("
        SELECT o.*, a.titulo, a.mensagem, a.som, a.adiamento_min, a.recorrencia
        FROM alarme_ocorrencias o
        JOIN alarmes a ON a.id = o.alarme_id
        WHERE o.utilizador_id = :uid AND o.estado = 'a_tocar'
        ORDER BY o.previsto_para ASC, o.id ASC
    ");
    $st->execute([':uid' => $uid]);
    return $st->fetchAll();
}

function alarmes_ocorrencia(int $id, int $uid): ?array {
    $st = db()->prepare("
        SELECT o.*, a.titulo, a.mensagem, a.som, a.adiamento_min, a.recorrencia
        FROM alarme_ocorrencias o
        JOIN alarmes a ON a.id = o.alarme_id
        WHERE o.id = :id AND o.utilizador_id = :uid
    ");
    $st->execute([':id' => $id, ':uid' => $uid]);
    $o = $st->fetch();
    return $o ?: null;
}

/** Adiar; devolve o estado e a nova hora. */
function alarmes_adiar(int $id, int $uid, ?int $minutos = null): array {
    $o = alarmes_ocorrencia($id, $uid);
    if (!$o) return ['ok' => false, 'erro' => 'Ocorrência não encontrada.'];
    if ($o['estado'] !== 'a_tocar') return ['ok' => false, 'erro' => 'Este alarme já não está a tocar.'];
    if ((int)$o['adiamentos'] >= ALARMES_MAX_ADIAMENTOS) {
        return ['ok' => false, 'erro' => 'Limite de adiamentos atingido. Desliga o alarme.'];
    }

    if ($minutos === null) $minutos = ((int)$o['adiamento_min']) ?: 10;
    $minutos = max(1, min(60, $minutos));
    $adiadoPara = momento_local_mais($minutos);

    $st = db()->prepare("
        UPDATE alarme_ocorrencias
        SET estado = 'adiado', adiado_para = :para, adiamentos = adiamentos + 1
        WHERE id = :id AND utilizador_id = :uid
    ");
    $st->execute([':para' => $adiadoPara, ':id' => $id, ':uid' => $uid]);

    return ['ok' => true, 'estado' => 'adiado', 'adiado_para' => $adiadoPara, 'minutos' => $minutos];
}

/** Desligar; devolve o estado final. */
function alarmes_desligar(int $id, int $uid): array {
    $o = alarmes_ocorrencia($id, $uid);
    if (!$o) return ['ok' => false, 'erro' => 'Ocorrência não encontrada.'];

    $agora = momento_local();
    $st = db()->prepare("
        UPDATE alarme_ocorrencias
        SET estado = 'desligado', adiado_para = NULL, desligado_em = :agora
        WHERE id = :id AND utilizador_id = :uid AND estado IN ('a_tocar','adiado')
    ");
    $st->execute([':agora' => $agora, ':id' => $id, ':uid' => $uid]);

    // Um alarme único desliga-se de vez depois de cumprido.
    $alarme = alarmes_encontrar((int)$o['alarme_id'], $uid);
    if ($alarme && $alarme['recorrencia'] === 'unico') {
        $st = db()->prepare("UPDATE alarmes SET ativo = 0, atualizado_em = :agora WHERE id = :id AND utilizador_id = :uid");
        $st->execute([':agora' => $agora, ':id' => (int)$o['alarme_id'], ':uid' => $uid]);
    }

    return ['ok' => true, 'estado' => 'desligado'];
}
