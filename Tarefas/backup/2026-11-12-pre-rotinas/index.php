<?php
require_once __DIR__ . '/inc/partials.php';

exigir_login();
$uid = uid_atual();

$tarefas   = repositorio_carregar($uid);
$stats     = estatisticas($tarefas);
$hojeLocal = data_local();
$diaAmanha = date('Y-m-d', strtotime($hojeLocal . ' +1 day'));
$cal       = calendario_mes();
$diaHoje   = (int)substr($hojeLocal, 8, 2);
$util      = utilizador_atual();

$diasSemana = ['Monday' => 'segunda-feira', 'Tuesday' => 'terça-feira', 'Wednesday' => 'quarta-feira',
               'Thursday' => 'quinta-feira', 'Friday' => 'sexta-feira', 'Saturday' => 'sábado', 'Sunday' => 'domingo'];
$mesesAno = [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
             7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'];

$mesAno    = $mesesAno[(int)substr($hojeLocal, 5, 2)] . ' ' . substr($hojeLocal, 0, 4);
$dataLonga = $diasSemana[date('l', strtotime($hojeLocal))] . ', ' . (int)substr($hojeLocal, 8, 2) . ' de ' . $mesesAno[(int)substr($hojeLocal, 5, 2)];

/* pontos do mini-calendário */
$tarefasPorDia = [];
foreach ($tarefas as $t) {
    $tarefasPorDia[$t['data']] = ($tarefasPorDia[$t['data']] ?? 0) + 1;
}

$iconePlus  = icone('plus');
$iconeArrow = icone('arrow');
$iconeCal   = icone('calendario');
$iconeSpark = icone('sparkles');
$iconeZap   = icone('zap');
$iconeCheck = icone('check');
$iconeClock = icone('clock');
$iconeTarget = icone('target');
$iconeLista = icone('tarefas');

html_inicio('Visão geral', 'dashboard');

$sub = $dataLonga . ' <span class="date-separator">/</span> Que bom ver-te por aqui, ' . htmlspecialchars($util['nome']) . '. <span class="date-separator">/</span> <b id="nHoje">' . (int)$stats['pendentes'] . '</b> por concluir.';

html_topo(
    'O seu dia, em harmonia',
    $sub,
    true,
    '<button type="button" class="new-task-button" id="btnNovaTarefa">' . $iconePlus . '<span>Nova tarefa</span></button>'
);
?>

<section class="summary-grid" aria-label="Resumo das tarefas">
    <article class="summary-card summary-primary">
        <span class="summary-icon"><?= $iconeLista ?></span>
        <div class="summary-copy">
            <span class="summary-label">Por concluir</span>
            <strong id="stPendentes"><?= (int)$stats['pendentes'] ?></strong>
            <span class="summary-detail">tarefas em aberto</span>
        </div>
        <span class="summary-arrow"><?= $iconeArrow ?></span>
    </article>

    <article class="summary-card summary-success">
        <span class="summary-icon"><?= $iconeCheck ?></span>
        <div class="summary-copy">
            <span class="summary-label">Concluídas hoje</span>
            <strong id="stConcluidasHoje"><?= (int)$stats['concluidas_hoje'] ?></strong>
            <span class="summary-detail">pequenas vitórias</span>
        </div>
        <span class="summary-arrow"><?= $iconeArrow ?></span>
    </article>

    <article class="summary-card summary-warning">
        <span class="summary-icon"><?= $iconeClock ?></span>
        <div class="summary-copy">
            <span class="summary-label">A pedir atenção</span>
            <strong id="stAtrasadas"><?= (int)$stats['atrasadas'] ?></strong>
            <span class="summary-detail">tarefas atrasadas</span>
        </div>
        <span class="summary-arrow"><?= $iconeArrow ?></span>
    </article>

    <article class="summary-card summary-info">
        <span class="summary-icon"><?= $iconeTarget ?></span>
        <div class="summary-copy">
            <span class="summary-label">Ritmo de hoje</span>
            <strong id="stTaxa"><?= (int)$stats['taxa_conclusao'] ?>%</strong>
            <span class="summary-detail">do plano concluído</span>
        </div>
        <span class="summary-arrow"><?= $iconeArrow ?></span>
    </article>
</section>

<div class="content-grid">
    <!-- Coluna principal -->
    <section class="tasks-section" aria-labelledby="tasks-heading">
        <div class="section-heading">
            <div>
                <div class="eyebrow section-eyebrow"><span class="live-indicator"></span> O próximo passo</div>
                <h2 id="tasks-heading">A tua lista</h2>
                <p><span id="tarefasContagem">0</span> tarefas para o dia de hoje</p>
            </div>
            <a class="view-all-button" href="tarefas.php">Ver tarefas <?= $iconeArrow ?></a>
        </div>

        <div class="task-filters" id="filtrosDashboard" role="group" aria-label="Filtrar tarefas">
            <button type="button" class="filter-button filter-active" data-filtro="todas" aria-pressed="true">
                Todas <span class="filter-count" id="filtroTodas">0</span>
            </button>
            <button type="button" class="filter-button" data-filtro="pendentes" aria-pressed="false">Por fazer</button>
            <button type="button" class="filter-button" data-filtro="concluidas" aria-pressed="false">Concluídas</button>
        </div>

        <ul class="task-list" id="listaHoje"></ul>

        <button type="button" class="add-task-inline" data-abrir-modal>
            <?= $iconePlus ?> Adicionar uma tarefa
        </button>

        <!-- Amanhã -->
        <div class="panel-subsection">
            <div class="section-heading">
                <div>
                    <div class="eyebrow section-eyebrow">Plano seguinte</div>
                    <h2>Amanhã</h2>
                    <p id="nAmanha"></p>
                </div>
                <a class="view-all-button" href="tarefas.php">Ver todas <?= $iconeArrow ?></a>
            </div>
            <ul class="task-list" id="listaAmanha"></ul>
        </div>
    </section>

    <!-- Coluna de insights -->
    <aside class="insights-column" aria-label="O teu progresso">
        <section class="insight-panel progress-panel">
            <div class="panel-kicker">
                <span>O teu progresso</span>
                <span class="panel-spark"><?= $iconeSpark ?></span>
            </div>
            <div class="progress-content">
                <div class="progress-ring" id="anelProgresso" role="img" aria-label="<?= (int)$stats['taxa_conclusao'] ?>% das tarefas concluídas" style="--pct: <?= (int)$stats['taxa_conclusao'] ?>">
                    <svg viewBox="0 0 100 100" aria-hidden="true">
                        <circle class="progress-track" cx="50" cy="50" r="42"></circle>
                        <circle class="progress-value" id="anelCirculo" cx="50" cy="50" r="42" pathLength="100"
                                stroke-dasharray="<?= (int)$stats['taxa_conclusao'] ?> 100"></circle>
                    </svg>
                    <span class="ring-label"><strong><span id="anelPct"><?= (int)$stats['taxa_conclusao'] ?></span>%</strong><small>concluído</small></span>
                </div>
                <p class="progress-caption">Cada tarefa concluída<br>é um passo em frente.</p>
            </div>
            <div class="progress-footer">
                <span class="progress-dot"></span>
                <span><b id="legConcluidas"><?= (int)$stats['concluidas'] ?></b> concluídas</span>
                <span class="progress-divider"></span>
                <span><b id="legPendentes"><?= (int)$stats['pendentes'] ?></b> por fazer</span>
            </div>
        </section>

        <section class="insight-panel calendar-panel" aria-labelledby="calendar-title">
            <div class="calendar-heading">
                <div>
                    <div class="panel-kicker"><span>Planeamento</span></div>
                    <h2 id="calendar-title"><?= $mesAno ?></h2>
                </div>
                <?= $iconeCal ?>
            </div>
            <div class="calendar-grid" aria-label="Calendário do mês">
                <?php foreach (['S', 'T', 'Q', 'Q', 'S', 'S', 'D'] as $letra): ?>
                <span class="calendar-weekday"><?= $letra ?></span>
                <?php endforeach; ?>

                <?php foreach ($cal as $semana): ?>
                    <?php foreach ($semana as $dia): ?>
                        <?php if ($dia === null): ?>
                        <span aria-hidden="true"></span>
                        <?php else:
                            $dataDia = substr($hojeLocal, 0, 7) . '-' . str_pad((string)$dia, 2, '0', STR_PAD_LEFT);
                            $classes = 'calendar-day' . ($dia === $diaHoje ? ' calendar-today' : '');
                            $tem = isset($tarefasPorDia[$dataDia]) ? ' · ' . $tarefasPorDia[$dataDia] . ' tarefa(s)' : '';
                        ?>
                        <a class="<?= $classes ?>" href="calendario.php?mes=<?= substr($hojeLocal, 0, 7) ?>&dia=<?= $dataDia ?>"
                           title="<?= $dia ?> de <?= htmlspecialchars($mesAno) ?><?= $tem ?>"
                           aria-label="Abrir <?= $dataDia ?>"><?= $dia ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            <a class="calendar-footer" href="calendario.php">Hoje <?= $iconeArrow ?></a>
        </section>

        <section class="insight-panel">
            <div class="section-heading">
                <div>
                    <div class="panel-kicker"><span>Notificações</span></div>
                    <h2 style="font-size:13px">Recentes</h2>
                </div>
                <a class="view-all-button" href="notificacoes.php">Ver todas</a>
            </div>
            <ul class="notifs-list" id="listaNotif" data-lista-notif data-limite="4" style="margin-top:8px"></ul>
            <button type="button" class="btn-ghost btn-sm" id="btnMarcarLidas" style="margin-top:8px">Marcar todas como lidas</button>
        </section>

        <section class="focus-note" aria-label="Nota de motivação">
            <div class="focus-note-icon"><?= $iconeSpark ?></div>
            <div>
                <strong>O importante é continuar.</strong>
                <p>Não precisas de fazer tudo. Só o próximo passo.</p>
            </div>
        </section>
    </aside>
</div>

<footer class="dashboard-footer">
    <span>Feito com calma, para dias com intenção.</span>
    <span>Habbits <span class="footer-separator">·</span> <?= substr($hojeLocal, 0, 4) ?></span>
</footer>

<?php
echo "<script>window.ESTADO_INICIAL = " . json_encode([
    'hoje'    => $hojeLocal,
    'amanha'  => $diaAmanha,
    'tarefas' => array_values($tarefas),
    'stats'   => $stats,
], JSON_UNESCAPED_UNICODE) . ";</script>\n";
html_fim('app.js');
