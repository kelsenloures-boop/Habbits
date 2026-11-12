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
$temFiltros = $f['estado'] || $f['prioridade'] || $f['cat'] || $f['busca'] !== '' || $f['vista'] !== 'todas';

/* Vista rápida → traduz para data ou estado */
$tipos = ['busca', 'estado', 'prioridade', 'cat', 'vista'];

$tarefas = tarefas_com_filtros($uid, $f);

/* listas para os selects */
$todasCats = [];
foreach (repositorio_carregar($uid) as $t) {
    if ($t['cat'] !== '' && !in_array($t['cat'], $todasCats, true)) $todasCats[] = $t['cat'];
}
sort($todasCats);

$contagem = [
    'todas'    => count(repositorio_carregar($uid)),
    'pendentes'=> count(array_filter(repositorio_carregar($uid), fn($t) => $t['estado'] === 'pendente')),
    'concluidas'=> count(array_filter(repositorio_carregar($uid), fn($t) => $t['estado'] === 'concluida')),
    'atrasadas'=> count(array_filter(repositorio_carregar($uid), fn($t) => $t['estado'] === 'pendente' && $t['data'] < data_local())),
];

/* agrupar por data para exibição */
$grupos = [];
foreach ($tarefas as $t) $grupos[$t['data']][] = $t;
krsort($grupos); // as mais recentes em cima

html_inicio('Minhas Tarefas', 'tarefas');
html_topo(
    'Minhas Tarefas',
    $contagem['pendentes'] . ' pendentes · ' . $contagem['concluidas'] . ' concluídas · ' . $contagem['atrasadas'] . ' atrasadas',
    true,
    '<button class="btn-primario" id="btnNovaTarefa">+ Nova tarefa</button>'
);
?>

<!-- Filtros -->
<form method="get" class="card filtros-bar" id="formFiltros">
    <div class="filtros-linha">
        <div class="filtro-chips" role="tablist">
            <a href="tarefas.php" class="chip <?= $f['vista'] === 'todas' ? 'ativo' : '' ?>">Todas <span><?= $contagem['todas'] ?></span></a>
            <button type="button" class="chip <?= $f['vista'] === 'pendentes' ? 'ativo' : '' ?>" data-vista="pendentes">Pendentes <span><?= $contagem['pendentes'] ?></span></button>
            <button type="button" class="chip <?= $f['vista'] === 'concluidas' ? 'ativo' : '' ?>" data-vista="concluidas">Concluídas <span><?= $contagem['concluidas'] ?></span></button>
            <button type="button" class="chip <?= $f['vista'] === 'atrasadas' ? 'ativo' : '' ?>" data-vista="atrasadas">Atrasadas <span><?= $contagem['atrasadas'] ?></span></button>
        </div>

        <div class="filtros-selects">
            <select name="estado" class="campo-filtro">
                <option value="">Estado: todos</option>
                <option value="pendente" <?= $f['estado'] === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                <option value="concluida" <?= $f['estado'] === 'concluida' ? 'selected' : '' ?>>Concluídas</option>
            </select>
            <select name="prioridade" class="campo-filtro">
                <option value="">Prioridade: todas</option>
                <option value="alta" <?= $f['prioridade'] === 'alta' ? 'selected' : '' ?>>Alta</option>
                <option value="media" <?= $f['prioridade'] === 'media' ? 'selected' : '' ?>>Média</option>
                <option value="baixa" <?= $f['prioridade'] === 'baixa' ? 'selected' : '' ?>>Baixa</option>
            </select>
            <select name="cat" class="campo-filtro">
                <option value="">Categoria: todas</option>
                <?php foreach ($todasCats as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $f['cat'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="vista" value="<?= htmlspecialchars($f['vista']) ?>" id="fVista">
            <input type="hidden" name="busca" value="<?= htmlspecialchars($f['busca']) ?>" id="fBuscaField">
            <button type="submit" class="btn-secondary-filtro" title="Aplicar filtros">Filtrar</button>
            <?php if ($temFiltros): ?>
            <a href="tarefas.php" class="btn-secondary-filtro link-erro-limpar" title="Limpar filtros">✕ Limpar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Lista agrupada por dia -->
<?php if (!$grupos): ?>
<section class="card painel">
    <div class="vazio">🔍 Nenhuma tarefa encontrada com estes filtros.<br><small>Experimente limpar os filtros ou criar uma nova tarefa.</small></div>
</section>
<?php else: ?>

<?php $mesesAno = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
$diasSemana = ['Monday'=>'Segunda','Tuesday'=>'Terça','Wednesday'=>'Quarta','Thursday'=>'Quinta','Friday'=>'Sexta','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$hojeLocal = data_local();
?>

<?php foreach ($grupos as $dia => $lista): ?>
<section class="card painel">
    <header class="painel-cab">
        <div>
            <h2>
                <?php
                if ($dia === $hojeLocal) echo 'Hoje';
                elseif ($dia === date('Y-m-d', strtotime($hojeLocal . ' +1 day'))) echo 'Amanhã';
                elseif ($dia === date('Y-m-d', strtotime($hojeLocal . ' -1 day'))) echo 'Ontem';
                else echo $diasSemana[date('l', strtotime($dia))] . ', ' . substr($dia, 8, 2) . ' de ' . $mesesAno[(int)substr($dia, 5, 2)];
                ?>
                <small class="grq-data"><?= substr($dia, 8, 2) . '/' . substr($dia, 5, 2) . '/' . substr($dia, 0, 4) ?></small>
            </h2>
            <span class="badge"><?= count($lista) ?> tarefa<?= count($lista) === 1 ? '' : 's' ?></span>
        </div>
    </header>
    <ul class="lista-tarefas">
        <?php foreach ($lista as $t) echo linha_tarefa_html($t); ?>
    </ul>
</section>
<?php endforeach; ?>
<?php endif; ?>

<footer class="rodape">Habbits · PHP puro · v0.5</footer>

<?php html_fim('tarefas.js'); ?>
