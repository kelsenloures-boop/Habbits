<?php
require_once __DIR__ . '/inc/partials.php';

exigir_login();
$uid = uid_atual();

$hojeLocal = data_local();

/* Mês em visualização: ?mes=YYYY-MM (por defeito, o atual) */
$mes = $_GET['mes'] ?? substr($hojeLocal, 0, 7);
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = substr($hojeLocal, 0, 7);
$valido = (int)substr($mes, 0, 4) >= 2000;
if (!$valido) $mes = substr($hojeLocal, 0, 7);

/* Dia selecionado: ?dia=YYYY-MM-DD (por defeito, hoje se estiver no mês) */
$dia = $_GET['dia'] ?? ($mes === substr($hojeLocal, 0, 7) ? $hojeLocal : '');
if ($dia && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) $dia = '';
if ($dia && substr($dia, 0, 7) !== $mes) $dia = '';

/* navegação do mês */
$mesAnterior = date('Y-m', strtotime($mes . '-01 -1 month'));
$mesSeguinte = date('Y-m', strtotime($mes . '-01 +1 month'));

function strftime_mes_label(string $yyyyMm): string {
    $mesesAno = [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
                 7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'];
    $m = (int)substr($yyyyMm, 5, 2);
    $a = substr($yyyyMm, 0, 4);
    return $mesesAno[$m] . ' ' . $a;
}

$mesAnteriorLabel = strftime_mes_label($mesAnterior);
$mesSeguinteLabel = strftime_mes_label($mesSeguinte);
$mesAtualLabel    = strftime_mes_label($mes);

/* grelha do mês */
$primeiroDia  = mktime(0, 0, 0, (int)substr($mes, 5, 2), 1, (int)substr($mes, 0, 4));
$diasMes      = (int)date('t', $primeiroDia);
$inicioSemana = (int)date('N', $primeiroDia) - 1;

$semanas = [];
$semanaBase = array_fill(0, $inicioSemana, null);
for ($d = 1; $d <= $diasMes; $d++) {
    $semanaBase[] = $d;
    if (count($semanaBase) === 7) { $semanas[] = $semanaBase; $semanaBase = []; }
}
if ($semanaBase) $semanas[] = array_pad($semanaBase, 7, null);

/* tarefas do mês, agrupadas por dia */
$tarefasMes = tarefas_do_mes($uid, $mes);
$porDia = [];
foreach ($tarefasMes as $t) $porDia[$t['data']][] = $t;

/* resumo do dia selecionado */
$tarefasDia = $dia ? ($porDia[$dia] ?? []) : [];
usort($tarefasDia, fn($a, $b) => $a['hora'] <=> $b['hora']);

/* contagens por dia para os pontos no calendário */
$pontosPorDia = [];
foreach ($porDia as $d => $lista) {
    $pontosPorDia[$d] = [
        'total'     => count($lista),
        'pendentes' => count(array_filter($lista, fn($t) => $t['estado'] === 'pendente')),
    ];
}

$diasSemanaLongos = ['Monday'=>'segunda-feira','Tuesday'=>'terça-feira','Wednesday'=>'quarta-feira',
                     'Thursday'=>'quinta-feira','Friday'=>'sexta-feira','Saturday'=>'sábado','Sunday'=>'domingo'];
$mesesAno = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
             7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];

$diaLongo = $dia
    ? $diasSemanaLongos[date('l', strtotime($dia))] . ', ' . (int)substr($dia, 8, 2) . ' de ' . $mesesAno[(int)substr($dia, 5, 2)]
    : '';

$iconePlus  = icone('plus');
$iconeArrow = icone('arrow');
$iconeCal   = icone('calendario');

html_inicio('Calendário', 'calendario');
html_topo(
    'Calendário',
    $mesAtualLabel . ($dia ? ' <span class="date-separator">/</span> ' . $diaLongo : ''),
    false,
    '<button type="button" class="new-task-button" id="btnNovaTarefa">' . $iconePlus . '<span>Nova tarefa</span></button>'
);
?>

<div class="calendario-layout">
    <!-- Grelha do mês -->
    <section class="calendar-card" aria-labelledby="cal-titulo">
        <div class="calendar-nav">
            <div>
                <div class="eyebrow"><?= $iconeCal ?> Planeamento</div>
                <h2 id="cal-titulo"><?= htmlspecialchars($mesAtualLabel) ?></h2>
            </div>
            <div class="calendar-nav-buttons">
                <a class="calendar-nav-btn" href="calendario.php?mes=<?= $mesAnterior ?>" title="Mês anterior">◀ <?= htmlspecialchars($mesAnteriorLabel) ?></a>
                <a class="calendar-nav-btn" href="calendario.php" title="Mês atual">Hoje</a>
                <a class="calendar-nav-btn" href="calendario.php?mes=<?= $mesSeguinte ?>" title="Mês seguinte"><?= htmlspecialchars($mesSeguinteLabel) ?> ▶</a>
            </div>
        </div>

        <table class="cal-tabela">
            <thead>
                <tr><th>Seg</th><th>Ter</th><th>Qua</th><th>Qui</th><th>Sex</th><th>Sáb</th><th>Dom</th></tr>
            </thead>
            <tbody>
                <?php foreach ($semanas as $semana): ?>
                <tr>
                    <?php foreach ($semana as $d): ?>
                    <?php if ($d === null): ?>
                    <td class="cal-vazio"></td>
                    <?php else:
                        $dataDia = $mes . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
                        $ponto   = $pontosPorDia[$dataDia] ?? null;
                        $cl = [];
                        if ($dataDia === $hojeLocal) $cl[] = 'cal-hoje';
                        if ($dataDia === $dia) $cl[] = 'cal-selecionado';
                    ?>
                    <td class="cal-dia <?= implode(' ', $cl) ?>">
                        <a href="calendario.php?mes=<?= $mes ?>&dia=<?= $dataDia ?>" class="cal-link" data-dia="<?= $dataDia ?>"
                           aria-label="<?= $dataDia ?><?= $ponto ? ', ' . $ponto['total'] . ' tarefa(s)' : '' ?>">
                            <span class="cal-num"><?= $d ?></span>
                            <?php if ($ponto): ?>
                            <span class="cal-pontos" title="<?= $ponto['total'] ?> tarefa<?= $ponto['total'] === 1 ? '' : 's' ?> (<?= $ponto['pendentes'] ?> pendente<?= $ponto['pendentes'] === 1 ? '' : 's' ?>)">
                                <?php for ($i = 0; $i < min(3, max(1, $ponto['pendentes'])); $i++): ?><i></i><?php endfor; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </td>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="cal-dica">Dica: toque num dia para ver e criar tarefas. Duplo-clique para adicionar rapidamente.</p>
    </section>

    <!-- Dia selecionado -->
    <section class="day-panel" aria-labelledby="dia-titulo">
        <header class="day-panel-head">
            <div>
                <div class="eyebrow"><?= $iconeCal ?> O dia escolhido</div>
                <h2 id="dia-titulo"><?= $dia ? (int)substr($dia, 8, 2) . ' de ' . $mesesAno[(int)substr($dia, 5, 2)] : 'Seleciona um dia' ?></h2>
                <p><?= $dia ? htmlspecialchars($diaLongo) . ' · ' . count($tarefasDia) . ' tarefa' . (count($tarefasDia) === 1 ? '' : 's') : 'As tarefas do dia aparecem aqui.' ?></p>
            </div>
            <?php if ($dia): ?>
            <button type="button" class="btn-primario btn-dia add-tarefa-dia" data-dia="<?= $dia ?>"><?= $iconePlus ?> Neste dia</button>
            <?php endif; ?>
        </header>

        <?php if ($dia): ?>
        <ul class="task-list lista-tarefas" id="listaDia">
            <?php foreach ($tarefasDia as $t) echo linha_tarefa_html($t); ?>
            <?php if (!$tarefasDia): ?>
            <li class="empty-state">
                <span class="empty-icon"><?= icone('sparkles') ?></span>
                <strong>Dia livre</strong>
                <small>Sem tarefas neste dia. Um bom momento para respirar.</small>
            </li>
            <?php endif; ?>
        </ul>
        <?php else: ?>
        <div class="empty-state">
            <span class="empty-icon"><?= icone('calendario') ?></span>
            <strong>Escolhe um dia</strong>
            <small>Seleciona uma data na grelha para ver o plano.</small>
        </div>
        <?php endif; ?>
    </section>
</div>

<footer class="dashboard-footer">
    <span>Um mês bem vivido começa num dia de cada vez.</span>
    <span>Habbits <span class="footer-separator">·</span> <?= substr($hojeLocal, 0, 4) ?></span>
</footer>

<?php html_fim('calendario.js'); ?>
