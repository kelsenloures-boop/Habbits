<?php
require_once __DIR__ . '/inc/bootstrap.php';

// Já tem sessão? → direto para o dashboard
if (uid_atual()) { header('Location: index.php'); exit; }

$erro = null;
$aba = $_POST['aba'] ?? 'entrar';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $aba = $_POST['aba'] ?? 'entrar';

    /* ---------- Entrar ---------- */
    if ($aba === 'entrar') {
        $u = utilizador_por_email($_POST['email'] ?? '');
        if ($u && password_verify($_POST['passe'] ?? '', $u['palavra_passe'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            header('Location: index.php');
            exit;
        }
        $erro = 'Email ou palavra-passe incorretos.';
    }

    /* ---------- Criar conta ---------- */
    if ($aba === 'registar') {
        $nome  = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $passe = $_POST['passe'] ?? '';
        $conf  = $_POST['confirma'] ?? '';

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Indique um nome e um email válido.';
        } elseif (strlen($passe) < 6) {
            $erro = 'A palavra-passe precisa de pelo menos 6 caracteres.';
        } elseif ($passe !== $conf) {
            $erro = 'As palavras-passe não coincidem.';
        } elseif (utilizador_por_email($email)) {
            $erro = 'Já existe uma conta com esse email.';
        } else {
            $id = utilizador_criar($nome, $email, password_hash($passe, PASSWORD_DEFAULT));
            notificar($id, 'Bem-vindo ao Habbits 🎉', 'Conta criada. Comece por adicionar a sua primeira tarefa!', 'check');
            session_regenerate_id(true);
            $_SESSION['uid'] = $id;
            header('Location: index.php');
            exit;
        }
    }
}

$temConta = utilizadores_contar() > 0;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar — Habbits</title>
<link rel="stylesheet" href="assets/css/style.css?v=5">
</head>
<body class="pagina-login">

<div class="login-caixa">
    <div class="login-logo">
        <span class="logo-mark">✓</span>
        <span class="logo-text">Hab<span>bits</span></span>
    </div>
    <p class="login-slogan">Organize os seus dias, sem esforço.</p>

    <div class="login-aba" role="tablist">
        <button class="<?= $aba === 'entrar' ? 'ativo' : '' ?>" id="tabEntrar">Entrar</button>
        <button class="<?= $aba === 'registar' ? 'ativo' : '' ?>" id="tabRegistar">Criar conta</button>
    </div>

    <?php if ($erro): ?><div class="alerta erro">⚠️ <?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <?php if (isset($_GET['saistou'])): ?><div class="alerta ok">Sessão terminada. Até à próxima! 👋</div><?php endif; ?>

    <!-- Entrar -->
    <form method="post" class="<?= $aba === 'entrar' ? 'login-form visivel' : 'login-form' ?>" id="formEntrar">
        <input type="hidden" name="aba" value="entrar">
        <?= csrf_campo() ?>
        <div class="campo">
            <label for="eEmail">Email</label>
            <input type="email" id="eEmail" name="email" required autocomplete="email" placeholder="o.seu@email.com">
        </div>
        <div class="campo">
            <label for="ePasse">Palavra-passe</label>
            <input type="password" id="ePasse" name="passe" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <button type="submit" class="btn-primario login-btn">Entrar →</button>
    </form>

    <!-- Registar -->
    <form method="post" class="<?= $aba === 'registar' ? 'login-form visivel' : 'login-form' ?>" id="formRegistar">
        <input type="hidden" name="aba" value="registar">
        <?= csrf_campo() ?>
        <div class="campo">
            <label for="rNome">Nome</label>
            <input type="text" id="rNome" name="nome" required maxlength="60" placeholder="O seu nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="rEmail">Email</label>
            <input type="email" id="rEmail" name="email" required autocomplete="email" placeholder="o.seu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="linha-2">
            <div class="campo">
                <label for="rPasse">Palavra-passe</label>
                <input type="password" id="rPasse" name="passe" required minlength="6" autocomplete="new-password" placeholder="min. 6 caracteres">
            </div>
            <div class="campo">
                <label for="rConfima">Confirmar</label>
                <input type="password" id="rConfirm" name="confirma" required minlength="6" autocomplete="new-password" placeholder="repita">
            </div>
        </div>
        <button type="submit" class="btn-primario login-btn">Criar conta ✨</button>
    </form>

    <?php if ($temConta): ?>
        <p class="login-aviso">Esta instalação tem dados existentes. Para não perder as tarefas antigas, entre com a conta <b>kelsen@habbits.local</b> · passe <b>habbits123</b>.</p>
    <?php else: ?>
        <p class="login-aviso">Primeira utilização: crie a sua conta para começar.</p>
    <?php endif; ?>
</div>

<script src="assets/js/login.js?v=5"></script>
</body>
</html>
