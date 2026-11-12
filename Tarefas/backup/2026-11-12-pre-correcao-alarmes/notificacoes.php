<?php
require_once __DIR__ . '/inc/partials.php';

exigir_login();
$uid = uid_atual();

/* Ações de formulário (fallback sem JavaScript) */
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
        flash('ok', 'Todas as notificações foram marcadas como lidas.');
    }
    if ($acao === 'limpar_todas') {
        $n = notificacoes_limpar($uid);
        flash('ok', $n . ' notificação' . ($n === 1 ? '' : 'es') . ' eliminada' . ($n === 1 ? '' : 's') . '.');
    }
    header('Location: notificacoes.php');
    exit;
}

$nots = notificacoes_carregar($uid, 100);
$nNaoLidas = notificacoes_contar_nao_lidas($uid);
$iconeNot = fn(string $i) => ['alert' => '⚠️', 'check' => '✅', 'clock' => '🕒', 'bell' => '🔔'][$i] ?? '🔔';
$flashMsg = flash_pegar();

$iconeSino = icone('notificacoes');
$iconeCheck = icone('check');

html_inicio('Notificações', 'notificacoes');
$acoes = '<button type="submit" form="formTodas" class="theme-button" id="btnMarcarTodas" ' . ($nNaoLidas ? '' : 'disabled') . '>'
    . $iconeCheck . '<span>Marcar todas como lidas</span></button>';
html_topo(
    'Notificações',
    '<b id="contadorNaoLidas">' . $nNaoLidas . '</b> não lida' . ($nNaoLidas === 1 ? '' : 's')
        . ' <span class="date-separator">/</span> lembretes e novidades',
    false,
    $acoes
);

/* Formulário de "marcar todas" (fora do fluxo para o botão do topo) */
?>
<form method="post" id="formTodas" style="display:none">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="marcar_todas">
</form>

<div class="page-block">
    <?php if ($flashMsg): ?>
    <div class="alerta <?= $flashMsg['tipo'] ?>"><?= htmlspecialchars($flashMsg['msg']) ?></div>
    <?php endif; ?>

    <section class="notifs-card">
        <ul class="notifs-list notifs-pagina" id="listaNotifPagina"
            data-lista-notif data-acoes="completas" data-limite="0">
            <?php if (!$nots): ?>
            <li class="empty-state">
                <span class="empty-icon"><?= $iconeSino ?></span>
                <strong>Tudo em silêncio</strong>
                <small>Criar ou concluir tarefas gera avisos aqui.</small>
            </li>
            <?php else: ?>
            <?php foreach ($nots as $n): ?>
            <li class="notif-item <?= $n['lida'] ? 'lida' : 'nao-lida' ?>" data-id="<?= (int)$n['id'] ?>">
                <span class="notif-icone"><?= $iconeNot($n['icon']) ?></span>
                <div class="notif-body">
                    <strong><?= htmlspecialchars($n['titulo']) ?></strong>
                    <p><?= htmlspecialchars($n['texto']) ?></p>
                    <small><?= htmlspecialchars($n['tempo']) ?></small>
                </div>
                <div class="notif-acoes-inline">
                    <?php if (empty($n['lida'])): ?>
                    <form method="post" class="acao-notif" data-acao="marcar_lida" data-id="<?= (int)$n['id'] ?>">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <input type="hidden" name="acao" value="marcar_lida">
                        <button type="submit" class="btn-secundario btn-sm">Marcar lida</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" class="acao-notif" data-acao="apagar" data-id="<?= (int)$n['id'] ?>">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <input type="hidden" name="acao" value="apagar">
                        <button type="submit" class="btn-secundario btn-sm perigo" title="Eliminar" aria-label="Eliminar notificação">✕</button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        <footer class="notifs-footer" data-rodape-notif <?= $nots ? '' : 'style="display:none"' ?>>
            <form method="post" class="acao-notif" data-acao="limpar_todas">
                <?= csrf_campo() ?>
                <button type="submit" class="btn-secundario btn-sm perigo">🗑 Limpar todas</button>
            </form>
        </footer>
    </section>

    <footer class="dashboard-footer">
        <span>Menos ruído, mais foco.</span>
        <span>Habbits <span class="footer-separator">·</span> <?= substr(data_local(), 0, 4) ?></span>
    </footer>
</div>

<?php html_fim(); ?>
