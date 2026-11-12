<?php
require_once __DIR__ . '/inc/partials.php';

exigir_login();
$uid = uid_atual();

/* Filtros atuais (GET → SQL) */
$f = [
    'vista'      => $_GET['vista'] ?? 'todas',
    'estado'     => $_GET['estado'] ?? '',
    'prioridade' => $_GET['prioridade'] ?? '',
    'cat'        => $_GET['cat'] ?? '',
    'busca'      => trim($_GET['busca'] ?? ''),
];
if (!in_array($f['vista'], ['todas', 'pendentes', 'concluidas', 'atrasadas'], true)) $f['vista'] = 'todas';
$temFiltros = $f['estado'] || $f['prioridade'] || $f['cat'] || $f['busca'] !== '' || $f['vista'] !== 'todas';

$tarefas = tarefas_com_filtros($uid, $f);

/* listas para os selects */
$todas = repositorio_carregar($uid);
$todasCats = [];
foreach ($todas as $t) {
    if ($t['cat'] !== '' && !in_array($t['cat'], $todasCats, true)) $todasCats[] = $t['cat'];
}
sort($todasCats);

$contagem = [
    'todas'     => count($todas),
    'pendentes' => count(array_filter($todas, fn($t) => $t['estado'] === 'pendente')),
    'concluidas'=> count(array_filter($todas, fn($t) => $t['estado'] === 'concluida')),
    'atrasadas' => count(array_filter($todas, fn($t) => $t['estado'] === 'pendente' && $t['data'] < data_local())),
];

/* agrupar por data para exibição */
$grupos = [];
foreach ($tarefas as $t) $grupos[$t['data']][] = $t;
krsort($grupos);

$iconePlus = icone('plus');
$iconeArrow = icone('arrow');

html_inicio('As minhas tarefas', 'tarefas');
html_topo(
    'As minhas tarefas',
    $contagem['pendentes'] . ' pendentes <span class="date-separator">/</span> '
        . $contagem['concluidas'] . ' concluídas <span class="date-separator">/</span> '
        . $contagem['atrasadas'] . ' atrasadas',
    true,
    '<button type="button" class="new-task-button" id="btnNovaTarefa">' . $iconePlus . '<span>Nova tarefa</span></button>'
);
?>

<div class="page-block">
    <!-- Filtros -->
    <form method="get" class="filters-shell" id="formFiltros">
        <div class="filtros-linha">
            <div class="filtro-chips" role="group" aria-label="Vistas rápidas">
                <a href="tarefas.php" class="chip <?= $f['vista'] === 'todas' ? 'ativo' : '' ?>">Todas <span><?= $contagem['todas'] ?></span></a>
                <button type="button" class="chip <?= $f['vista'] === 'pendentes' ? 'ativo' : '' ?>" data-vista="pendentes">Pendentes <span><?= $contagem['pendentes'] ?></span></button>
                <button type="button" class="chip <?= $f['vista'] === 'concluidas' ? 'ativo' : '' ?>" data-vista="concluidas">Concluídas <span><?= $contagem['concluidas'] ?></span></button>
                <button type="button" class="chip <?= $f['vista'] === 'atrasadas' ? 'ativo' : '' ?>" data-vista="atrasadas">Atrasadas <span><?= $contagem['atrasadas'] ?></span></button>
            </div>

            <div class="filtros-selects">
                <select name="estado" class="campo-filtro" aria-label="Filtrar por estado">
                    <option value="">Estado: todos</option>
                    <option value="pendente" <?= $f['estado'] === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                    <option value="concluida" <?= $f['estado'] === 'concluida' ? 'selected' : '' ?>>Concluídas</option>
                </select>
                <select name="prioridade" class="campo-filtro" aria-label="Filtrar por prioridade">
                    <option value="">Prioridade: todas</option>
                    <option value="alta" <?= $f['prioridade'] === 'alta' ? 'selected' : '' ?>>Alta</option>
                    <option value="media" <?= $f['prioridade'] === 'media' ? 'selected' : '' ?>>Média</option>
                    <option value="baixa" <?= $f['prioridade'] === 'baixa' ? 'selected' : '' ?>>Baixa</option>
                </select>
                <select name="cat" class="campo-filtro" aria-label="Filtrar por categoria">
                    <option value="">Categoria: todas</option>
                    <?php foreach ($todasCats as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $f['cat'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="vista" value="<?= htmlspecialchars($f['vista']) ?>" id="fVista">
                <input type="hidden" name="busca" value="<?= htmlspecialchars($f['busca']) ?>" id="fBuscaField">
                <button type="submit" class="btn-secondary-filtro" title="Aplicar filtros">Filtrar</button>
                <?php if ($temFiltros): ?>
                <a href="tarefas.php" class="btn-secondary-filtro perigo" title="Limpar filtros">✕ Limpar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if (!$grupos): ?>
    <section class="day-group">
        <div class="empty-state">
            <span class="empty-icon"><?= icone('search') ?></span>
            <strong>Nenhuma tarefa encontrada</strong>
            <small>Experimenta limpar os filtros ou criar uma nova tarefa.</small>
        </div>
    </section>
    <?php else: ?>

    <?php
    $mesesAno = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
    $diasSemana = ['Monday'=>'Segunda','Tuesday'=>'Terça','Wednesday'=>'Quarta','Thursday'=>'Quinta','Friday'=>'Sexta','Saturday'=>'Sábado','Sunday'=>'Domingo'];
    $hojeLocal = data_local();
    ?>

    <?php foreach ($grupos as $dia => $lista): ?>
    <section class="day-group">
        <header class="day-group-head">
            <h2>
                <?php
                if ($dia === $hojeLocal) echo 'Hoje';
                elseif ($dia === date('Y-m-d', strtotime($hojeLocal . ' +1 day'))) echo 'Amanhã';
                elseif ($dia === date('Y-m-d', strtotime($hojeLocal . ' -1 day'))) echo 'Ontem';
                else echo $diasSemana[date('l', strtotime($dia))] . ', ' . (int)substr($dia, 8, 2) . ' de ' . $mesesAno[(int)substr($dia, 5, 2)];
                ?>
                <span class="group-date"><?= substr($dia, 8, 2) . '/' . substr($dia, 5, 2) . '/' . substr($dia, 0, 4) ?></span>
            </h2>
            <span class="group-count"><?= count($lista) ?> tarefa<?= count($lista) === 1 ? '' : 's' ?></span>
        </header>
        <ul class="task-list lista-tarefas">
            <?php foreach ($lista as $t) echo linha_tarefa_html($t); ?>
        </ul>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>

    <footer class="dashboard-footer">
        <span>Organiza com calma. Conclui com intenção.</span>
        <span>Habbits <span class="footer-separator">·</span> <?= substr(data_local(), 0, 4) ?></span>
    </footer>
</div>

<?php html_fim('tarefas.js'); ?>
