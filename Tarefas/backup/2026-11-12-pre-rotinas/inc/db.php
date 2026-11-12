<?php
/**
 * Habbits — conexão e esquema SQLite (PDO).
 * Base de dados: data/habbits.sqlite (criada automáticamente na 1ª execução)
 */

const FICHEIRO_SQLITE = __DIR__ . '/../data/habbits.sqlite';
const FICHEIRO_DADOS  = __DIR__ . '/../data/tarefas.json';   // legado (migrado)
const FICHEIRO_NOTIF  = __DIR__ . '/../data/notificacoes.json'; // legado (migrado)

/* ---------------------------------------------------------------
 * Conexão (singleton)
 * ------------------------------------------------------------- */
function db(): PDO {
    static $pdo = null;

    if ($pdo !== null) return $pdo;

    $dir = dirname(FICHEIRO_SQLITE);
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $nova = !file_exists(FICHEIRO_SQLITE);

    $pdo = new PDO('sqlite:' . FICHEIRO_SQLITE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');   // leituras/escritas mais seguras
    db_criar_esquema($pdo);
    db_migrar_esquema($pdo);                    // colunas novas em BDs antigas

    if ($nova || db_conta($pdo, 'tarefas') === 0) {
        db_migrar_json($pdo);
    }

    return $pdo;
}

/* ---------------------------------------------------------------
 * Migração do esquema (idempotente) —Called a cada arranque para
 * adicionar colunas novas a bases de dados existentes.
 * ------------------------------------------------------------- */
function db_migrar_esquema(PDO $pdo): void {
    $colunas = db_colunas($pdo, 'tarefas');
    $aFalta = [
        'lembrete'         => "ALTER TABLE tarefas ADD COLUMN lembrete INTEGER NOT NULL DEFAULT 15",
        'lembrete_enviado' => "ALTER TABLE tarefas ADD COLUMN lembrete_enviado INTEGER NOT NULL DEFAULT 0",
        'utilizador_id'    => "ALTER TABLE tarefas ADD COLUMN utilizador_id INTEGER NOT NULL DEFAULT 1",
        'alarme_som'       => "ALTER TABLE tarefas ADD COLUMN alarme_som INTEGER NOT NULL DEFAULT 0",
    ];
    foreach ($aFalta as $coluna => $sql) {
        if (!in_array($coluna, $colunas, true)) $pdo->exec($sql);
    }

    // notificações ligadas ao utilizador e à origem (tarefa/alarme)
    $colsN = db_colunas($pdo, 'notificacoes');
    $novasNotif = [
        'utilizador_id' => "ALTER TABLE notificacoes ADD COLUMN utilizador_id INTEGER NOT NULL DEFAULT 1",
        'tarefa_id'     => "ALTER TABLE notificacoes ADD COLUMN tarefa_id INTEGER",
        'som'           => "ALTER TABLE notificacoes ADD COLUMN som INTEGER NOT NULL DEFAULT 0",
        'lida_em'       => "ALTER TABLE notificacoes ADD COLUMN lida_em TEXT",
    ];
    foreach ($novasNotif as $coluna => $sql) {
        if (!in_array($coluna, $colsN, true)) $pdo->exec($sql);
    }

    // Índices de leitura (depois das colunas garantidas)
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_notificacoes_utilizador_lida
            ON notificacoes (utilizador_id, lida);
        CREATE INDEX IF NOT EXISTS idx_notificacoes_utilizador_id
            ON notificacoes (utilizador_id, id DESC);
    ");

    // Preferências: arranque automático (coluna adicionada depois da tabela original)
    $colsP = db_colunas($pdo, 'preferencias');
    if (!in_array('iniciar_com_windows', $colsP, true)) {
        $pdo->exec("ALTER TABLE preferencias ADD COLUMN iniciar_com_windows INTEGER NOT NULL DEFAULT 0");
    }

    // Instalação existente (tem tarefas, ainda sem utilizadores) → conta demo
    if (db_conta($pdo, 'utilizadores') === 0 && db_conta($pdo, 'tarefas') > 0) {
        $st = $pdo->prepare("
            INSERT INTO utilizadores (nome, email, palavra_passe, criada_em)
            VALUES (:nome, :email, :hash, :agora)
        ");
        $st->execute([
            ':nome'  => 'Kelsen',
            ':email' => 'kelsen@habbits.local',
            ':hash'  => password_hash('habbits123', PASSWORD_DEFAULT),
            ':agora' => momento_local(),
        ]);
    }
}

function db_colunas(PDO $pdo, string $tabela): array {
    return array_column($pdo->query("PRAGMA table_info({$tabela})")->fetchAll(), 'name');
}

/* ---------------------------------------------------------------
 * Esquema
 * ------------------------------------------------------------- */
function db_criar_esquema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS utilizadores (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            nome           TEXT    NOT NULL,
            email          TEXT    NOT NULL UNIQUE,
            palavra_passe  TEXT    NOT NULL,
            criada_em      TEXT    NOT NULL
        );

        CREATE TABLE IF NOT EXISTS tarefas (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo            TEXT    NOT NULL,
            data              TEXT    NOT NULL,
            hora              TEXT    NOT NULL DEFAULT '08:00',
            prioridade        TEXT    NOT NULL DEFAULT 'media'
                              CHECK (prioridade IN ('alta','media','baixa')),
            estado            TEXT    NOT NULL DEFAULT 'pendente'
                              CHECK (estado IN ('pendente','concluida')),
            cat               TEXT    NOT NULL DEFAULT 'Pessoal',
            lembrete          INTEGER NOT NULL DEFAULT 15,
            lembrete_enviado  INTEGER NOT NULL DEFAULT 0,
            alarme_som        INTEGER NOT NULL DEFAULT 0,
            criada_em         TEXT    NOT NULL,
            atualizada_em     TEXT    NOT NULL
        );

        CREATE TABLE IF NOT EXISTS notificacoes (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            icon           TEXT    NOT NULL DEFAULT 'bell',
            titulo         TEXT    NOT NULL,
            texto          TEXT    NOT NULL,
            tempo          TEXT    NOT NULL,
            lida           INTEGER NOT NULL DEFAULT 0,
            criada_em      TEXT    NOT NULL,
            utilizador_id  INTEGER NOT NULL DEFAULT 1,
            tarefa_id      INTEGER,
            som            INTEGER NOT NULL DEFAULT 0,
            lida_em        TEXT
        );

        /* Preferências de notificações/sons por utilizador.
           Uma linha por utilizador; criada apenas quando é guardada. */
        CREATE TABLE IF NOT EXISTS preferencias (
            utilizador_id           INTEGER PRIMARY KEY,
            canal                   TEXT    NOT NULL DEFAULT 'interno'
                                    CHECK (canal IN ('interno','windows','desligado')),
            sons                    INTEGER NOT NULL DEFAULT 1,
            som_alarme              INTEGER NOT NULL DEFAULT 0,
            volume                  INTEGER NOT NULL DEFAULT 80,
            mostrar_segundo_plano   INTEGER NOT NULL DEFAULT 1,
            abrir_ao_alarme         INTEGER NOT NULL DEFAULT 1,
            fechar_para_bandeja     INTEGER NOT NULL DEFAULT 1,
            snooze_min              INTEGER NOT NULL DEFAULT 10,
            limite_som_seg          INTEGER NOT NULL DEFAULT 120,
            iniciar_com_windows     INTEGER NOT NULL DEFAULT 0,
            atualizada_em           TEXT    NOT NULL DEFAULT ''
        );

        CREATE INDEX IF NOT EXISTS idx_tarefas_utilizador_data
            ON tarefas (utilizador_id, data, hora);

        /* Alarmes: definição + ocorrências (uma linha por disparo previsto). */
        CREATE TABLE IF NOT EXISTS alarmes (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            utilizador_id  INTEGER NOT NULL DEFAULT 1,
            titulo         TEXT    NOT NULL,
            mensagem       TEXT    NOT NULL DEFAULT '',
            hora           TEXT    NOT NULL,
            data           TEXT,
            recorrencia    TEXT    NOT NULL DEFAULT 'diario'
                            CHECK (recorrencia IN ('unico','diario','semanal','dias')),
            dias_semana    TEXT    NOT NULL DEFAULT '',
            som            INTEGER NOT NULL DEFAULT 1,
            ativo          INTEGER NOT NULL DEFAULT 1,
            adiamento_min  INTEGER NOT NULL DEFAULT 10,
            criado_em      TEXT    NOT NULL,
            atualizado_em  TEXT    NOT NULL
        );

        CREATE TABLE IF NOT EXISTS alarme_ocorrencias (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            alarme_id      INTEGER NOT NULL,
            utilizador_id  INTEGER NOT NULL,
            previsto_para  TEXT    NOT NULL,
            estado         TEXT    NOT NULL DEFAULT 'a_tocar'
                            CHECK (estado IN ('a_tocar','adiado','desligado','perdido')),
            adiado_para    TEXT,
            adiamentos     INTEGER NOT NULL DEFAULT 0,
            desligado_em   TEXT,
            criado_em      TEXT    NOT NULL,
            UNIQUE (alarme_id, previsto_para)
        );

        CREATE INDEX IF NOT EXISTS idx_alarmes_utilizador
            ON alarmes (utilizador_id, ativo);
        CREATE INDEX IF NOT EXISTS idx_alarme_ocorrencias_utilizador
            ON alarme_ocorrencias (utilizador_id, estado, adiado_para);
    ");
}

function db_conta(PDO $pdo, string $tabela): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM {$tabela}")->fetchColumn();
}

/* ---------------------------------------------------------------
 * Tempo local (do relógio do utilizador) via SQLite.
 * O PHP/CLI↓ não conhece o fuso do Windows; o SQLite sim (localtime).
 * ------------------------------------------------------------- */
function data_local(): string {            // YYYY-MM-DD
    return (string)db()->query("SELECT date('now','localtime')")->fetchColumn();
}

function hora_local(): string {            // HH:MM
    return (string)db()->query("SELECT strftime('%H:%M','now','localtime')")->fetchColumn();
}

function momento_local(): string {         // YYYY-MM-DD HH:MM:SS
    return (string)db()->query("SELECT strftime('%Y-%m-%d %H:%M:%S','now','localtime')")->fetchColumn();
}

/* ---------------------------------------------------------------
 * Migração do JSON legado → SQLite
 * Corre uma única vez: insere os dados, depois renomeia os ficheiros
 * para .migrado (backup).
 * ------------------------------------------------------------- */
function db_migrar_json(PDO $pdo): void {
    $agora = momento_local();

    // Sem JSON legado → primeira instalação: dados de demonstração
    $tarefas = is_file(FICHEIRO_DADOS)
        ? (json_decode(file_get_contents(FICHEIRO_DADOS), true) ?: [])
        : seed_tarefas();

    $stmt = $pdo->prepare("
        INSERT INTO tarefas (id, titulo, data, hora, prioridade, estado, cat, criada_em, atualizada_em)
        VALUES (:id, :titulo, :data, :hora, :prioridade, :estado, :cat, :criada_em, :criada_em)
    ");
    $pdo->beginTransaction();
    foreach ($tarefas as $t) {
        $stmt->execute([
            ':id'         => $t['id'] ?? null,
            ':titulo'     => $t['titulo'],
            ':data'       => $t['data'],
            ':hora'       => $t['hora'] ?? '08:00',
            ':prioridade' => in_array($t['prioridade'] ?? '', ['alta', 'media', 'baixa'], true) ? $t['prioridade'] : 'media',
            ':estado'     => in_array($t['estado'] ?? '', ['pendente', 'concluida'], true) ? $t['estado'] : 'pendente',
            ':cat'        => $t['cat'] ?? 'Pessoal',
            ':criada_em'  => $agora,
        ]);
    }
    $pdo->commit();

    // Notificações legadas
    if (is_file(FICHEIRO_NOTIF)) {
        $nots = json_decode(file_get_contents(FICHEIRO_NOTIF), true) ?: [];
        $stmtN = $pdo->prepare("
            INSERT INTO notificacoes (icon, titulo, texto, tempo, lida, criada_em)
            VALUES (:icon, :titulo, :texto, :tempo, :lida, :criada_em)
        ");
        $pdo->beginTransaction();
        foreach ($nots as $n) {
            $stmtN->execute([
                ':icon'     => $n['icon'] ?? 'bell',
                ':titulo'   => $n['titulo'],
                ':texto'    => $n['texto'],
                ':tempo'    => $n['tempo'] ?? date('H:i'),
                ':lida'     => !empty($n['lida']) ? 1 : 0,
                ':criada_em'=> $agora,
            ]);
        }
        $pdo->commit();
    }

    // Backup dos ficheiros legados
    foreach ([FICHEIRO_DADOS, FICHEIRO_NOTIF] as $f) {
        if (is_file($f)) rename($f, $f . '.migrado');
    }
}
