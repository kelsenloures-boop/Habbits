<?php
require_once __DIR__ . '/inc/partials.php';

exigir_login();
$uid = uid_atual();
$util = utilizador_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'perfil') {
        $novoNome = trim($_POST['nome'] ?? '');
        if ($novoNome === '') {
            flash('erro', 'O nome não pode ficar vazio.');
        } else {
            utilizador_definir_nome($uid, $novoNome);
            flash('ok', 'Perfil atualizado. Olá, ' . $novoNome . '!');
        }
    }

    if ($acao === 'passe') {
        $atual = $_POST['atual'] ?? '';
        $nova = $_POST['nova'] ?? '';
        $conf = $_POST['confirma'] ?? '';

        $u = db()->prepare("SELECT palavra_passe FROM utilizadores WHERE id = :id");
        $u->execute([':id' => $uid]);
        $hash = $u->fetchColumn();

        if (!password_verify($atual, $hash)) {
            flash('erro', 'A palavra-passe atual está incorreta.');
        } elseif (strlen($nova) < 6) {
            flash('erro', 'A nova palavra-passe precisa de pelo menos 6 caracteres.');
        } elseif ($nova !== $conf) {
            flash('erro', 'A confirmação não coincide com a nova palavra-passe.');
        } else {
            utilizador_definir_passe($uid, password_hash($nova, PASSWORD_DEFAULT));
            flash('ok', 'Palavra-passe alterada com sucesso. 🔒');
        }
    }

    if ($acao === 'limpar_concluidas') {
        $n = tarefas_limpar_concluidas($uid);
        flash('ok', $n . ' tarefa' . ($n === 1 ? '' : 's') . ' concluída' . ($n === 1 ? '' : 's') . ' eliminada' . ($n === 1 ? '.' : 's.'));
    }

    if ($acao === 'limpar_notifs') {
        $n = notificacoes_limpar($uid);
        flash('ok', $n . ' notificação' . ($n === 1 ? '' : 'es') . ' eliminada' . ($n === 1 ? '.' : 's.'));
    }

    header('Location: definicoes.php');
    exit;
}

$flashMsg = flash_pegar();
$stats = estatisticas(repositorio_carregar($uid));

html_inicio('Definições', 'definicoes');
html_topo('Definições', 'Ajuste o seu perfil e a gestão de dados', false);
?>

<?php if ($flashMsg): ?>
<div class="alerta <?= $flashMsg['tipo'] ?>"><?= htmlspecialchars($flashMsg['msg']) ?></div>
<?php endif; ?>

<div class="defs-grid">

    <!-- Perfil -->
    <section class="card painel">
        <header class="painel-cab"><h2>👤 Perfil</h2></header>
        <form method="post" class="defs-form">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="perfil">
            <div class="campo">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" required maxlength="60" value="<?= htmlspecialchars($util['nome']) ?>">
            </div>
            <div class="campo">
                <label>Email (imutável)</label>
                <input type="email" value="<?= htmlspecialchars($util['email']) ?>" disabled>
            </div>
            <button class="btn-primario">Guardar alterações</button>
        </form>
    </section>

    <!-- Palavra-passe -->
    <section class="card painel">
        <header class="painel-cab"><h2>🔒 Palavra-passe</h2></header>
        <form method="post" class="defs-form">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="passe">
            <div class="campo">
                <label for="atual">Palavra-passe atual</label>
                <input type="password" id="atual" name="atual" required autocomplete="current-password">
            </div>
            <div class="linha-2">
                <div class="campo">
                    <label for="nova">Nova</label>
                    <input type="password" id="nova" name="nova" required minlength="6" autocomplete="new-password">
                </div>
                <div class="campo">
                    <label for="confirma">Confirmar</label>
                    <input type="password" id="confirma" name="confirma" required minlength="6" autocomplete="new-password">
                </div>
            </div>
            <button class="btn-primario">Alterar palavra-passe</button>
        </form>
    </section>

    <!-- Dados -->
    <section class="card painel defs-dados">
        <header class="painel-cab"><h2>🗄️ Gestão de dados</h2></header>
        <div class="defs-resumo">
            <div><span class="defs-num"><?= $stats['pendentes'] ?></span> pendentes</div>
            <div><span class="defs-num"><?= $stats['concluidas'] ?></span> concluídas</div>
            <div><span class="defs-num"><?= $stats['total'] ?></span> no total</div>
        </div>
        <p class="defs-aviso">As ações abaixo apagam dados. Nunca é possível recuperar.</p>
        <form method="post" class="defs-acoes">
            <?= csrf_campo() ?>
            <button class="btn-secundario" name="acao" value="limpar_concluidas">🧹 Eliminar tarefas concluídas</button>
            <button class="btn-secundario perigo" name="acao" value="limpar_notifs">🗑 Eliminar notificações</button>
        </form>
    </section>

</div>

<footer class="rodape">Habbits · PHP puro · v0.5</footer>

<?php html_fim(); ?>
