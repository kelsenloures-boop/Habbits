<?php
/**
 * Habbits — Bootstrap comum.
 * Inclui em TODAS as páginas/APIs: sessão, autoload de inc, sessões e helpers.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'httponly' => true]);
    session_start();
}

require_once __DIR__ . '/dados.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repositorio.php';
require_once __DIR__ . '/alarmes.php';

/* ================================================================
 * Sessão / autenticação
 * ============================================================== */

function uid_atual(): ?int {
    return isset($_SESSION['uid']) && is_int($_SESSION['uid']) ? $_SESSION['uid'] : null;
}

function utilizador_atual(): ?array {
    return uid_atual() ? utilizador_por_id(uid_atual()) : null;
}

/** Páginas: exige sessão; redireciona para o login */
function exigir_login(): void {
    if (!uid_atual()) {
        header('Location: login.php');
        exit;
    }
}

/** APIs: exige sessão ou devolve 401 */
function exigir_login_api(): void {
    if (!uid_atual()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'erro' => 'Sessão expirada. Inicie sessão novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* ================================================================
 * CSRF + mensagens flash (formulários POST)
 * ============================================================== */

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_campo(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_verificar(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? null)) {
        http_response_code(419);
        exit('Pedido inválido (CSRF). Volte atrás e tente de novo.');
    }
}

/** grava uma mensagem para a página seguinte */
function flash(string $tipo, string $msg): void {
    $_SESSION['flash'] = ['tipo' => $tipo, 'msg' => $msg];
}

function flash_pegar(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
