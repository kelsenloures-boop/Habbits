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

/* Datas em PT */
$diasSemana = ['Monday' => 'Segunda', 'Tuesday' => 'Terça', 'Wednesday' => 'Quarta', 'Thursday' => 'Quinta',
               'Friday' => 'Sexta', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'];
$mesesAno = [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
             7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'];

html_inicio('Dashboard', 'dashboard');
html_topo(
    "Bom dia, " . htmlspecialchars($util['nome']) . " 👋",
    $diasSemana[date('l', strtotime($hojeLocal))] . ', ' . substr($hojeLocal, 8, 2) . ' de ' . $mesesAno[(int)substr($hojeLocal, 5, 2)] . ' — <b id="nHoje">' . $stats['pendentes'] . '</b> tarefas pendentes',
    true,
    '<button class="btn-primario" id="btnNovaTarefa">+ Nova tarefa</button>'
);
?>

<!-- Cartões de estatística -->
<section class="stats-grid">
    <article class="card stat">
        <div class="stat-icono pendente">📋</div>
        <div>
            <span class="stat-valor" id="stPendentes"><?= $stats['pendentes'] ?></span>
            <span class="stat-rotulo">Tarefas pendentes</span>
        </div>
    </article>
    <article class="card stat">
        <div class="stat-icono ok">✅</div>
        <div>
            <span class="stat-valor" id="stConcluidasHoje"><?= $stats['concluidas_hoje'] ?></span>
            <span class="stat-rotulo">Concluídas hoje</span>
        </div>
    </article>
    <article class="card stat">
        <div class="stat-icono atraso">⏰</div>
        <div>
            <span class="stat-valor" id="stAtrasadas"><?= $stats['atrasadas'] ?></span>
            <span class="stat-rotulo">Atrasadas</span>
        </div>
    </article>
    <article class="card stat">
        <div class="stat-icono taxa">📈</div>
        <div>
            <span class="stat-valor" id="stTaxa"><?= $stats['taxa_conclusao'] ?>%</span>
            <span class="stat-rotulo">Taxa de conclusão</span>
        </div>
    </article>
</section>

<div class="grade-principal">
    <!-- Coluna esquerda -->
    <div class="coluna-esq">
        <section class="card painel">
            <header class="painel-cab">
                <div>
                    <h2>Tarefas de hoje</h2>
                    <p><?= substr($hojeLocal, 8, 2) . '/' . substr($hojeLocal, 5, 2) . '/' . substr($hojeLocal, 0, 4) ?></p>
                </div>
                <a href="tarefas.php" class="link-ver">Ver todas →</a>
            </header>
            <ul class="lista-tarefas" id="listaHoje"></ul>
        </section>

        <section class="card painel">
            <header class="painel-cab">
                <div>
                    <h2>Amanhã</h2>
                    <p id="nAmanha"></p>
                </div>
            </header>
            <ul class="lista-tarefas compacta" id="listaAmanha"></ul>
        </section>
    </div>

    <!-- Coluna direita -->
    <div class="coluna-dir">
        <section class="card painel centro">
            <h2>Progresso</h2>
            <div class="anel" id="anelProgresso" style="--pct: <?= $stats['taxa_conclusao'] ?>">
                <div class="anel-interior"><b><span id="anelPct"><?= $stats['taxa_conclusao'] ?></span>%</b><small>concluído</small></div>
            </div>
            <div class="legenda">
                <span><i class="marca ok"></i> <b id="legConcluidas"><?= $stats['concluidas'] ?></b> concluídas</span>
                <span><i class="marca pen"></i> <b id="legPendentes"><?= $stats['pendentes'] ?></b> pendentes</span>
            </div>
        </section>

        <section class="card painel">
            <header class="painel-cab">
                <h2><?= $mesesAno[(int)substr($hojeLocal, 5, 2)] ?> <?= substr($hojeLocal, 0, 4) ?></h2>
                <a href="calendario.php" class="link-ver">Abrir →</a>
            </header>
            <table class="mini-cal">
                <thead><tr><th>S</th><th>T</th><th>Q</th><th>Q</th><th>S</th><th>S</th><th>D</th></tr></thead>
                <tbody>
                    <?php foreach ($cal as $semana): ?>
                    <tr>
                        <?php foreach ($semana as $dia): ?>
                        <td class="<?= $dia === null ? 'vazio' : '' ?> <?= $dia === $diaHoje ? 'hoje' : '' ?>"><?= $dia ?? '' ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="card painel">
            <header class="painel-cab">
                <h2>Notificações recentes</h2>
                <a href="notificacoes.php" class="link-ver">Ver todas →</a>
            </header>
            <ul class="lista-notif" id="listaNotif"></ul>
        </section>
    </div>
</div>

<footer class="rodape">Habbits · PHP puro · v0.5</footer>

<?php
echo "<script>window.ESTADO_INICIAL = " . json_encode([
    'hoje'    => $hojeLocal,
    'amanha'  => $diaAmanha,
    'tarefas' => array_values($tarefas),
    'stats'   => $stats,
], JSON_UNESCAPED_UNICODE) . ";</script>\n";
html_fim('app.js');
