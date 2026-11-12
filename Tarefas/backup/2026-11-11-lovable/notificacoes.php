<?php
require_once __DIR__ . '/inc/partials.php';

exigir_login();
$uid = uid_atual();

/* Ações de formulário */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'marcar_lida' && !empty($_POST['id'])) {
        notificacoes_marcar_lida((int)$_POST['id'], $uid);
        flash('ok', 'Notificação marcada como lida.');
    }
    if ($acao === 'apagar' && !empty($_POST['id'])) {
        notificacoes_apagar((int)$_POST['id'], $uid);
        flash('ok', 'Notificação eliminada.');
    }
    if ($acao === 'marcar_todas') {
        notificacoes_marcar_todas_lidas($uid);
        flash('ok', 'Todas marcadas como lidas.');
    }
    if ($acao === 'limpar_todas') {
        $n = notificacoes_limpar($uid);
        flash('ok', $n . ' notificação' . ($n === 1 ? '' : 'es') . ' eliminada' . ($n === 1 ? '' : 's') . '.');
    }
    header('Location: notificacoes.php');
    exit;
}

$nots = notificacoes_carregar($uid, 100);
$nNaoLidas = count(array_filter($nots, fn($n) => empty($n['lida'])));
$iconeNot = fn(string $i) => ['alert' => '⚠️', 'check' => '✅', 'clock' => '🕒', 'bell' => '🔔'][$i] ?? '🔔';
$flashMsg = flash_pegar();

html_inicio('Notificações', 'notificacoes');
$acoes = '<button class="btn-secundario" form="formTodas" type="submit" ' . ($nNaoLidas ? '' : 'disabled') . '>Marcar todas como lidas</button>';
html_topo('Notificações', $nNaoLidas . ' não lida' . ($nNaoLidas === 1 ? '' : 's'), false, $acoes);

/* Formulário de "marcar todas" (fora do fluxo para o botão do topo) */
?>
<form method="post" id="formTodas" style="display:none">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="marcar_todas">
</form>

<?php if ($flashMsg): ?>
<div class="alerta <?= $flashMsg['tipo'] ?>"><?= htmlspecialchars($flashMsg['msg']) ?></div>
<?php endif; ?>

<section class="card painel">
    <?php if (!$nots): ?>
    <div class="vazio">📭 Sem notificações. Criar ou concluir tarefas gera avisos aqui.</div>
    <?php else: ?>
    <ul class="lista-notif notifs-pagina">
        <?php foreach ($nots as $n): ?>
        <li class="<?= $n['lida'] ? 'lida' : 'nao-lida' ?>">
            <span class="notif-icone"><?= $iconeNot($n['icon']) ?></span>
            <div>
                <strong><?= htmlspecialchars($n['titulo']) ?></strong>
                <p><?= htmlspecialchars($n['texto']) ?></p>
                <small><?= htmlspecialchars($n['tempo']) ?></small>
            </div>
            <div class="notif-acoes-inline">
                <?php if (empty($n['lida'])): ?>
                <form method="post">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                    <button class="btn-secundario btn-sm" name="acao" value="marcar_lida">Marcar lida</button>
                </form>
                <?php endif; ?>
                <form method="post" class="form-inline">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                    <button class="btn-secundario btn-sm perigo" name="acao" value="apagar" title="Eliminar">✕</button>
                </form>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <footer class="painel-rodape">
        <form method="post">
            <?= csrf_campo() ?>
            <button class="btn-secundario btn-sm perigo" name="acao" value="limpar_todas">🗑 Limpar todas</button>
        </form>
    </footer>
    <?php endif; ?>
</section>

<footer class="rodape">Habbits · PHP puro · v0.5</footer>

<?php html_fim(); ?>
