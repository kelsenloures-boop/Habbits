<?php
/**
 * Habbits — Repositório SQLite (PDO), multi-utilizador.
 * Todas as consultas usam prepared statements e são filtradas por utilizador.
 */

require_once __DIR__ . '/db.php';

/* ================================================================
 * TAREFAS
 * ============================================================== */

/** Todas as tarefas do utilizador, ordenadas por data e hora */
function repositorio_carregar(int $uid): array {
    $st = db()->prepare("
        SELECT id, titulo, data, hora, prioridade, estado, cat, lembrete, lembrete_enviado
        FROM tarefas
        WHERE utilizador_id = :uid
        ORDER BY data ASC, hora ASC, id ASC
    ");
    $st->execute([':uid' => $uid]);
    return $st->fetchAll();
}

/** Filtros suportados: estado, prioridade, cat, busca, de, ate, vista (atrasadas) */
function tarefas_com_filtros(int $uid, array $f): array {
    $where = ['utilizador_id = :uid'];
    $bind  = [':uid' => $uid];

    if (!empty($f['estado']) && in_array($f['estado'], ['pendente', 'concluida'], true)) {
        $where[] = 'estado = :estado';
        $bind[':estado'] = $f['estado'];
    }
    if (!empty($f['prioridade']) && in_array($f['prioridade'], prioridades_validas(), true)) {
        $where[] = 'prioridade = :prioridade';
        $bind[':prioridade'] = $f['prioridade'];
    }
    if (!empty($f['cat'])) {
        $where[] = 'cat = :cat';
        $bind[':cat'] = (string)$f['cat'];
    }
    if (!empty($f['busca'])) {
        $where[] = '(titulo LIKE :busca OR cat LIKE :busca)';
        $bind[':busca'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string)$f['busca']) . '%';
    }
    if (!empty($f['de']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['de'])) {
        $where[] = 'data >= :de';
        $bind[':de'] = $f['de'];
    }
    if (!empty($f['ate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['ate'])) {
        $where[] = 'data <= :ate';
        $bind[':ate'] = $f['ate'];
    }
    if (($f['vista'] ?? '') === 'atrasadas') {
        $where[] = "estado = 'pendente' AND data < '" . data_local() . "'";
    }

    $st = db()->prepare("
        SELECT id, titulo, data, hora, prioridade, estado, cat, lembrete, lembrete_enviado
        FROM tarefas
        WHERE " . implode(' AND ', $where) . "
        ORDER BY data ASC, hora ASC, id ASC
    ");
    $st->execute($bind);
    return $st->fetchAll();
}

/** Tarefas de um mês (YYYY-MM), para o calendário */
function tarefas_do_mes(int $uid, string $mes): array {
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = substr(data_local(), 0, 7);
    $st = db()->prepare("
        SELECT id, titulo, data, hora, prioridade, estado, cat
        FROM tarefas
        WHERE utilizador_id = :uid AND substr(data, 1, 7) = :mes
        ORDER BY data ASC, hora ASC, id ASC
    ");
    $st->execute([':uid' => $uid, ':mes' => $mes]);
    return $st->fetchAll();
}

/** Uma tarefa do utilizador pelo id, ou null */
function tarefas_encontrar(int $id, int $uid): ?array {
    $st = db()->prepare("SELECT * FROM tarefas WHERE id = :id AND utilizador_id = :uid");
    $st->execute([':id' => $id, ':uid' => $uid]);
    $t = $st->fetch();
    return $t ?: null;
}

/** Criar; devolve o id gerado */
function tarefas_criar(array $dados, int $uid): int {
    $st = db()->prepare("
        INSERT INTO tarefas (titulo, data, hora, prioridade, estado, cat, lembrete, lembrete_enviado, utilizador_id, criada_em, atualizada_em)
        VALUES (:titulo, :data, :hora, :prioridade, 'pendente', :cat, :lembrete, 0, :uid, :agora, :agora)
    ");
    $st->execute([
        ':titulo'     => $dados['titulo'],
        ':data'       => $dados['data'],
        ':hora'       => $dados['hora'] ?? '08:00',
        ':prioridade' => $dados['prioridade'] ?? 'media',
        ':cat'        => $dados['cat'] ?? 'Pessoal',
        ':lembrete'   => $dados['lembrete'] ?? 15,
        ':uid'        => $uid,
        ':agora'      => momento_local(),
    ]);
    return (int)db()->lastInsertId();
}

/** Atualizar campos permitidos; devolve true se a tarefa existir (e for do utilizador) */
function tarefas_atualizar(int $id, int $uid, array $campos): bool {
    if (!tarefas_encontrar($id, $uid)) return false;

    $permitidos = ['titulo', 'data', 'hora', 'prioridade', 'cat', 'estado', 'lembrete', 'lembrete_enviado'];
    $sets = ['atualizada_em = :agora'];
    $bind = [':id' => $id, ':agora' => momento_local()];

    foreach ($campos as $campo => $valor) {
        if (!in_array($campo, $permitidos, true)) continue;
        $sets[] = "{$campo} = :{$campo}";
        $bind[":{$campo}"] = $valor;
    }

    $st = db()->prepare("UPDATE tarefas SET " . implode(', ', $sets) . " WHERE id = :id AND utilizador_id = :uid");
    $bind[':uid'] = $uid;
    $st->execute($bind);
    return true;
}

/** Apagar; devolve o título da tarefa removida, ou null */
function tarefas_apagar(int $id, int $uid): ?string {
    $t = tarefas_encontrar($id, $uid);
    if (!$t) return null;

    $st = db()->prepare("DELETE FROM tarefas WHERE id = :id AND utilizador_id = :uid");
    $st->execute([':id' => $id, ':uid' => $uid]);
    return $t['titulo'];
}

/** Apaga todas as concluídas; devolve o total */
function tarefas_limpar_concluidas(int $uid): int {
    $st = db()->prepare("DELETE FROM tarefas WHERE utilizador_id = :uid AND estado = 'concluida'");
    $st->execute([':uid' => $uid]);
    return $st->rowCount();
}

/* ================================================================
 * LEMBRETES
 * ============================================================== */

/** Tarefas pendentes cujo momento do lembrete já chegou, com minutos calculados em SQL */
function tarefas_lembretes_vencidos(int $uid): array {
    $st = db()->prepare("
        SELECT id, titulo, data, hora, lembrete,
               CAST(ROUND((julianday(data || ' ' || hora) - julianday('now', 'localtime')) * 1440) AS INTEGER) AS minutos
        FROM tarefas
        WHERE utilizador_id = :uid
          AND estado = 'pendente'
          AND lembrete >= 0
          AND lembrete_enviado = 0
          AND datetime(data || ' ' || hora) <= datetime('now', 'localtime', '+' || lembrete || ' minutes')
        ORDER BY data ASC, hora ASC
    ");
    $st->execute([':uid' => $uid]);
    return $st->fetchAll();
}

/** Marca como enviado para não voltar a disparar */
function tarefas_marcar_lembrete_enviado(int $id): void {
    $st = db()->prepare("UPDATE tarefas SET lembrete_enviado = 1 WHERE id = :id");
    $st->execute([':id' => $id]);
}

/* ================================================================
 * NOTIFICAÇÕES
 * ============================================================== */

function notificacoes_carregar(int $uid, int $limite = 20): array {
    $st = db()->prepare("
        SELECT id, icon, titulo, texto, tempo, lida
        FROM notificacoes
        WHERE utilizador_id = :uid
        ORDER BY id DESC
        LIMIT :limite
    ");
    $st->bindValue(':uid', $uid, PDO::PARAM_INT);
    $st->bindValue(':limite', $limite, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/** Registra uma notificação (mantém no máximo 20 por utilizador) */
function notificar(int $uid, string $titulo, string $texto, string $icon = 'bell'): void {
    $st = db()->prepare("
        INSERT INTO notificacoes (icon, titulo, texto, tempo, lida, utilizador_id, criada_em)
        VALUES (:icon, :titulo, :texto, :tempo, 0, :uid, :agora)
    ");
    $st->execute([
        ':icon'   => $icon,
        ':titulo' => $titulo,
        ':texto'  => $texto,
        ':tempo'  => hora_local(),
        ':uid'    => $uid,
        ':agora'  => momento_local(),
    ]);

    $st = db()->prepare("
        DELETE FROM notificacoes
        WHERE utilizador_id = :uid
          AND id NOT IN (
              SELECT id FROM notificacoes WHERE utilizador_id = :uid2 ORDER BY id DESC LIMIT 20
          )
    ");
    $st->execute([':uid' => $uid, ':uid2' => $uid]);
}

/** Marca todas como lidas; devolve as afetadas */
function notificacoes_marcar_todas_lidas(int $uid): int {
    $st = db()->prepare("UPDATE notificacoes SET lida = 1 WHERE utilizador_id = :uid AND lida = 0");
    $st->execute([':uid' => $uid]);
    return $st->rowCount();
}

/** Marca uma como lida */
function notificacoes_marcar_lida(int $id, int $uid): void {
    $st = db()->prepare("UPDATE notificacoes SET lida = 1 WHERE id = :id AND utilizador_id = :uid");
    $st->execute([':id' => $id, ':uid' => $uid]);
}

/** Elimina uma notificação */
function notificacoes_apagar(int $id, int $uid): void {
    $st = db()->prepare("DELETE FROM notificacoes WHERE id = :id AND utilizador_id = :uid");
    $st->execute([':id' => $id, ':uid' => $uid]);
}

/** Elimina todas as notificações; devolve o total */
function notificacoes_limpar(int $uid): int {
    $st = db()->prepare("DELETE FROM notificacoes WHERE utilizador_id = :uid");
    $st->execute([':uid' => $uid]);
    return $st->rowCount();
}

/* ================================================================
 * UTILIZADORES
 * ============================================================== */

function utilizador_por_id(int $id): ?array {
    $st = db()->prepare("SELECT id, nome, email, criada_em FROM utilizadores WHERE id = :id");
    $st->execute([':id' => $id]);
    $u = $st->fetch();
    return $u ?: null;
}

function utilizador_por_email(string $email): ?array {
    $st = db()->prepare("SELECT * FROM utilizadores WHERE email = :email");
    $st->execute([':email' => mb_strtolower(trim($email))]);
    $u = $st->fetch();
    return $u ?: null;
}

function utilizadores_contar(): int {
    return (int)db()->query("SELECT COUNT(*) FROM utilizadores")->fetchColumn();
}

function utilizador_criar(string $nome, string $email, string $hash): int {
    $st = db()->prepare("
        INSERT INTO utilizadores (nome, email, palavra_passe, criada_em)
        VALUES (:nome, :email, :hash, :agora)
    ");
    $st->execute([
        ':nome'  => mb_substr(trim($nome), 0, 60),
        ':email' => mb_strtolower(trim($email)),
        ':hash'  => $hash,
        ':agora' => momento_local(),
    ]);
    return (int)db()->lastInsertId();
}

function utilizador_definir_nome(int $uid, string $nome): void {
    $st = db()->prepare("UPDATE utilizadores SET nome = :nome WHERE id = :id");
    $st->execute([':nome' => mb_substr(trim($nome), 0, 60), ':id' => $uid]);
}

function utilizador_definir_passe(int $uid, string $hash): void {
    $st = db()->prepare("UPDATE utilizadores SET palavra_passe = :hash WHERE id = :id");
    $st->execute([':hash' => $hash, ':id' => $uid]);
}
