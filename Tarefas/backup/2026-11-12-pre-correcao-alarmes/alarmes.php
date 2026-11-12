<?php
/**
 * Habbits — Alarmes
 * CRUD com fallback por POST (funciona sem JavaScript) e melhorias em alarmes.js.
 */

require_once __DIR__ . '/inc/partials.php';

exigir_login();
$uid = uid_atual();

/* ---------------- Ações de formulário ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = (string)($_POST['acao'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);

    if ($acao === 'criar') {
        $r = alarmes_criar($uid, $_POST);
        flash($r['ok'] ? 'ok' : 'erro', $r['ok'] ? 'Alarme criado.' : $r['erro']);
    } elseif ($acao === 'atualizar' && $id > 0) {
        $r = alarmes_atualizar($id, $uid, $_POST);
        flash($r['ok'] ? 'ok' : 'erro', $r['ok'] ? 'Alarme atualizado.' : $r['erro']);
    } elseif ($acao === 'alternar' && $id > 0) {
        $r = alarmes_alternar($id, $uid);
        flash($r['ok'] ? 'ok' : 'erro', $r['ok'] ? 'Estado do alarme alterado.' : $r['erro']);
    } elseif ($acao === 'apagar' && $id > 0) {
        $ok = alarmes_apagar($id, $uid);
        flash($ok ? 'ok' : 'erro', $ok ? 'Alarme eliminado.' : 'Alarme não encontrado.');
    } else {
        flash('erro', 'Ação inválida.');
    }

    header('Location: alarmes.php');
    exit;
}

/* ---------------- Dados ---------------- */
alarmes_processar($uid);
$lista = alarmes_listar($uid);
$ativas = alarmes_ativas($uid);
$prefs = preferencias_obter($uid);

$editarId = (int)($_GET['editar'] ?? 0);
$emEdicao = $editarId > 0 ? alarmes_encontrar($editarId, $uid) : null;

$valores = [
    'titulo'        => $emEdicao['titulo'] ?? '',
    'mensagem'      => $emEdicao['mensagem'] ?? '',
    'hora'          => $emEdicao['hora'] ?? '08:00',
    'data'          => $emEdicao['data'] ?? '',
    'recorrencia'   => $emEdicao['recorrencia'] ?? 'diario',
    'dias_semana'   => $emEdicao['dias_semana'] ?? '',
    'som'           => (int)($emEdicao['som'] ?? 1),
    'ativo'         => (int)($emEdicao['ativo'] ?? 1),
    'adiamento_min' => (int)($emEdicao['adiamento_min'] ?? ($prefs['snooze_min'] ?? 10)),
];

$diasSemana = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
$diasAtivos = array_map('intval', array_filter(explode(',', (string)$valores['dias_semana'])));

$flashMsg = flash_pegar();
$total = count($lista);
$ligados = count(array_filter($lista, fn($a) => !empty($a['ativo'])));

$iconeAlarme = icone('alarmes');
$iconePlus = icone('plus');
$iconeEditar = icone('editar');

html_inicio('Alarmes', 'alarmes');

$acoesTopo = '<a href="#formAlarme" class="theme-button" id="btnNovoAlarme">' . $iconePlus . '<span>Novo alarme</span></a>';
html_topo(
    'Alarmes',
    '<b>' . $ligados . '</b> ativo' . ($ligados === 1 ? '' : 's')
        . ' <span class="date-separator">/</span> ' . $total . ' no total'
        . ' <span class="date-separator">/</span> Ecrã inteiro, adiar e desligar',
    false,
    $acoesTopo
);
?>

<div class="page-block">
    <?php if ($flashMsg): ?>
    <div class="alerta <?= $flashMsg['tipo'] ?>"><?= htmlspecialchars($flashMsg['msg']) ?></div>
    <?php endif; ?>

    <?php if ($ativas): ?>
    <div class="alarme-a-tocar" role="alert">
        <span class="alarme-a-tocar-icone"><?= $iconeAlarme ?></span>
        <div>
            <strong>A tocar agora: <?= htmlspecialchars($ativas[0]['titulo']) ?></strong>
            <small>Previsto para <?= htmlspecialchars(substr($ativas[0]['previsto_para'], 11, 5)) ?>.</small>
        </div>
        <a class="btn-primario" href="alarme.php?ocorrencia=<?= (int)$ativas[0]['id'] ?>">Abrir alarme</a>
    </div>
    <?php endif; ?>

    <section class="alarmes-secao">
        <header class="alarmes-secao-head">
            <div>
                <div class="eyebrow"><?= $iconeAlarme ?> Os teus alarmes</div>
                <h2>Rotina sem esquecimentos</h2>
                <p>Os alarmes tocam mesmo com as notificações desligadas.</p>
            </div>
        </header>

        <?php if (!$lista): ?>
        <div class="alarmes-vazio">
            <span class="empty-icon"><?= $iconeAlarme ?></span>
            <strong>Ainda sem alarmes</strong>
            <small>Cria o primeiro abaixo. Podes escolher todos os dias, dias da semana ou uma data única.</small>
        </div>
        <?php else: ?>
        <ul class="alarmes-lista">
            <?php foreach ($lista as $a): ?>
            <?php $proxima = alarme_proxima($a); ?>
            <li class="alarme-card<?= empty($a['ativo']) ? ' desligado' : '' ?>" data-id="<?= (int)$a['id'] ?>">
                <div class="alarme-hora">
                    <strong><?= htmlspecialchars(substr($a['hora'], 0, 5)) ?></strong>
                    <small><?= htmlspecialchars(alarme_recorrencia_rotulo($a)) ?></small>
                </div>
                <div class="alarme-corpo">
                    <strong><?= htmlspecialchars($a['titulo']) ?></strong>
                    <?php if ($a['mensagem'] !== ''): ?>
                    <p><?= htmlspecialchars($a['mensagem']) ?></p>
                    <?php endif; ?>
                    <small class="alarme-proxima">
                        <?= $proxima === null
                            ? (empty($a['ativo']) ? 'Desligado' : 'Sem próxima ocorrência')
                            : 'Próximo: ' . htmlspecialchars(alarme_proxima_texto($a)) ?>
                        <?= !empty($a['som']) ? ' · com som' : ' · sem som' ?>
                        <?= (int)$a['adiamento_min'] ? ' · adiar ' . (int)$a['adiamento_min'] . ' min' : '' ?>
                    </small>
                </div>
                <div class="alarme-acoes">
                    <form method="post" class="acao-alarme">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="alternar">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="btn-secundario btn-sm">
                            <?= empty($a['ativo']) ? 'Ligar' : 'Desligar' ?>
                        </button>
                    </form>
                    <a class="btn-secundario btn-sm" href="alarmes.php?editar=<?= (int)$a['id'] ?>#formAlarme">
                        <?= $iconeEditar ?> Editar
                    </a>
                    <form method="post" class="acao-alarme" data-confirmar="O alarme e o histórico de ocorrências vão ser eliminados. Esta ação não pode ser revertida." data-confirmar-titulo="Eliminar alarme?">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="apagar">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="btn-secundario btn-sm perigo" aria-label="Eliminar alarme">✕</button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <section class="settings-card" id="formAlarme">
        <div class="eyebrow"><?= $iconeAlarme ?> <?= $emEdicao ? 'Editar alarme' : 'Novo alarme' ?></div>
        <h2><?= $emEdicao ? htmlspecialchars($emEdicao['titulo']) : 'Criar um alarme' ?></h2>
        <p>O alarme dispara à hora local do computador. Na app desktop abre em ecrã inteiro.</p>

        <form method="post" class="defs-form" id="formAlarmeCampos">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="<?= $emEdicao ? 'atualizar' : 'criar' ?>">
            <?php if ($emEdicao): ?>
            <input type="hidden" name="id" value="<?= (int)$emEdicao['id'] ?>">
            <?php endif; ?>

            <div class="campo">
                <label for="aTitulo">Título *</label>
                <input type="text" id="aTitulo" name="titulo" required maxlength="120"
                       value="<?= htmlspecialchars($valores['titulo']) ?>" placeholder="Ex.: Tomar medicação">
            </div>

            <div class="campo">
                <label for="aMensagem">Mensagem</label>
                <input type="text" id="aMensagem" name="mensagem" maxlength="240"
                       value="<?= htmlspecialchars($valores['mensagem']) ?>" placeholder="Opcional">
            </div>

            <div class="form-row">
                <div class="campo">
                    <label for="aHora">Hora *</label>
                    <input type="time" id="aHora" name="hora" required value="<?= htmlspecialchars(substr($valores['hora'], 0, 5)) ?>">
                </div>
                <div class="campo">
                    <label for="aRecorrencia">Repetição</label>
                    <select id="aRecorrencia" name="recorrencia">
                        <option value="diario"   <?= $valores['recorrencia'] === 'diario' ? 'selected' : '' ?>>Todos os dias</option>
                        <option value="semanal"  <?= $valores['recorrencia'] === 'semanal' ? 'selected' : '' ?>>Todas as semanas</option>
                        <option value="dias"     <?= $valores['recorrencia'] === 'dias' ? 'selected' : '' ?>>Dias escolhidos</option>
                        <option value="unico"    <?= $valores['recorrencia'] === 'unico' ? 'selected' : '' ?>>Uma única vez</option>
                    </select>
                </div>
            </div>

            <div class="campo" data-bloco="data" <?= $valores['recorrencia'] === 'unico' ? '' : 'hidden' ?>>
                <label for="aData">Data do alarme único</label>
                <input type="date" id="aData" name="data" value="<?= htmlspecialchars($valores['data']) ?>">
            </div>

            <div class="campo" data-bloco="dias" <?= in_array($valores['recorrencia'], ['semanal', 'dias'], true) ? '' : 'hidden' ?>>
                <label>Dias da semana</label>
                <div class="dias-semana">
                    <?php foreach ($diasSemana as $n => $rotulo): ?>
                    <label class="dia-chip">
                        <input type="checkbox" name="dias_semana[]" value="<?= $n ?>" <?= in_array($n, $diasAtivos, true) ? 'checked' : '' ?>>
                        <span><?= $rotulo ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="campo">
                    <label for="aAdiamento">Adiar por (minutos)</label>
                    <input type="number" id="aAdiamento" name="adiamento_min" min="1" max="60" value="<?= (int)$valores['adiamento_min'] ?>">
                </div>
            </div>

            <div class="pref-grupo">
                <label class="pref-linha" for="aSom">
                    <input type="checkbox" id="aSom" name="som" <?= !empty($valores['som']) ? 'checked' : '' ?>>
                    <span><strong>Som de alarme</strong><small>Toca em loop até desligar, com o volume das preferências.</small></span>
                </label>
                <label class="pref-linha" for="aAtivo">
                    <input type="checkbox" id="aAtivo" name="ativo" <?= !empty($valores['ativo']) ? 'checked' : '' ?>>
                    <span><strong>Alarme ativo</strong><small>Se desmarcares, fica guardado mas não toca.</small></span>
                </label>
            </div>

            <div class="pref-acoes">
                <button type="submit" class="btn-primario"><?= $emEdicao ? 'Guardar alterações' : 'Criar alarme' ?></button>
                <?php if ($emEdicao): ?>
                <a href="alarmes.php" class="btn-secundario">Cancelar edição</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <footer class="dashboard-footer">
        <span>Um alarme a tempo, um dia sem correrias.</span>
        <span>Habbits <span class="footer-separator">·</span> <?= substr(data_local(), 0, 4) ?></span>
    </footer>
</div>

<?php html_fim('alarmes.js'); ?>
