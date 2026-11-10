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

/* navegação do mês */
$mesAnterior = date('Y-m', strtotime($mes . '-01 -1 month'));
$mesSeguinte = date('Y-m', strtotime($mes . '-01 +1 month'));
$mesAnteriorLabel = strftime_mes_label($mesAnterior);
$mesSeguinteLabel = strftime_mes_label($mesSeguinte);
$mesAtualLabel = strftime_mes_label($mes);

function strftime_mes_label(string $yyyyMm): string {
    $mesesAno = [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
                 7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'];
    $m = (int)substr($yyyyMm, 5, 2);
    $a = substr($yyyyMm, 0, 4);
    return $mesesAno[$m] . ' ' . $a;
}

/* grelha do mês */
$primeiroDia = mktime(0, 0, 0, (int)substr($mes, 5, 2), 1, (int)substr($mes, 0, 4));
$diasMes = (int)date('t', $primeiroDia);
$inicioSemana = (int)date('N', $primeiroDia) - 1;

/* semana linhas */
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
$tarefasDia = $dia ? array_filter($porDia[$dia] ?? [], fn($t) => true) : [];
usort($tarefasDia, fn($a, $b) => $a['hora'] <=> $b['hora']);

/* contagens por dia para os pontos no calendário */
$pontosPorDia = [];
foreach ($porDia as $d => $lista) {
    $pontosPorDia[$d] = [
        'total' => count($lista),
        'pendentes' => count(array_filter($lista, fn($t) => $t['estado'] === 'pendente')),
    ];
}

html_inicio('Calendário', 'calendario');

$acoes = <<<HTML
<a class="btn-secundario" href="calendario.php?mes={$mesAnterior}" title="Mês anterior">◀ {$mesAnteriorLabel}</a>
<button class="btn-primario" id="btnNovaTarefa">+ Nova tarefa</button>
<a class="btn-secundario" href="calendario.php?mes={$mesSeguinte}" title="Mês seguinte">{$mesSeguinteLabel} ▶</a>
HTML;
html_topo('Calendário', $mesAtualLabel . (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) ? ' · dia selecionado: <b>' . substr($dia, 8, 2) . '/' . substr($dia, 5, 2) . '</b>' : ''), false, $acoes);
?>

<div class="cal-layout">
    <!-- grelha do mês -->
    <section class="card painel cal-grelha">
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
                        $ponto = $pontosPorDia[$dataDia] ?? null;
                        $cl = [];
                        if ($dataDia === $hojeLocal) $cl[] = 'cal-hoje';
                        if ($dataDia === $dia) $cl[] = 'cal-selecionado';
                    ?>
                    <td class="cal-dia <?= implode(' ', $cl) ?>">
                        <a href="calendario.php?mes=<?= $mes ?>&dia=<?= $dataDia ?>" class="cal-link">
                            <span class="cal-num"><?= $d ?></span>
                            <?php if ($ponto): ?>
                            <span class="cal-pontos" title="<?= $ponto['total'] ?> tarefa<?= $ponto['total'] === 1 ? '' : 's' ?> (= pendentes <?= $ponto['pendentes'] ?>)">
                                <?php for ($i = 0; $i < min(3, $ponto['pendentes']); $i++): ?><i></i><?php endfor; ?>
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
        <p class="cal-dica">💡 Toque num dia para ver e criar tarefas.</p>
    </section>

    <!-- dia selecionado -->
    <section class="card painel cal-dia-painel">
        <header class="painel-cab">
            <div>
                <h2><?= $dia ? 'Dia ' . substr($dia, 8, 2) . '/' . substr($dia, 5, 2) . '/' . substr($dia, 0, 4) : 'Dia' ?></h2>
                <p><?= count($tarefasDia) ?> tarefa<?= count($tarefasDia) === 1 ? '' : 's' ?></p>
            </div>
            <?php if ($dia): ?>
            <button class="btn-primario btn-dia add-tarefa-dia" data-dia="<?= $dia ?>">+ Neste dia</button>
            <?php endif; ?>
        </header>
        <?php if ($dia): ?>
        <ul class="lista-tarefas" id="listaDia">
            <?php foreach ($tarefasDia as $t) echo linha_tarefa_html($t); ?>
            <?php if (!$tarefasDia): ?><div class="vazio">Sem tarefas neste dia. Bora planejar? às vezes nada é bom demais. 😊</div><?php endif; ?>
        </ul>
        <?php else: ?>
        <div class="vazio">Selecione um dia na grelha para ver as tarefas.</div>
        <?php endif; ?>
    </section>
</div>

<footer class="rodape">Habbits · PHP puro · v0.5</footer>

<?php html_fim('calendario.js'); ?>
