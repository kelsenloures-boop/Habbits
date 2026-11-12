<?php
/**
 * Habbits — Partials de UI partilhados entre as páginas.
 * Identidade visual inspirada no projeto Lovable "taskflow-motion-studio".
 */

require_once __DIR__ . '/bootstrap.php';

$paginas_nav = [
    'dashboard'     => ['index.php',        'Visão geral'],
    'tarefas'       => ['tarefas.php',      'As minhas tarefas'],
    'calendario'    => ['calendario.php',   'Calendário'],
    'notificacoes'  => ['notificacoes.php', 'Notificações'],
];

$rotulos_nav = [
    'dashboard'     => 'Visão geral',
    'tarefas'       => 'As minhas tarefas',
    'calendario'    => 'Calendário',
    'notificacoes'  => 'Notificações',
    'definicoes'    => 'Definições',
];

/* ------------------------------------------------------------------
 * Ícones (Lucide-like, SVG inline)
 * ---------------------------------------------------------------- */
function icone(string $nome, string $classe = ''): string
{
    $corpo = match ($nome) {
        'check'        => '<path d="M20 6 9 17l-5-5"/>',
        'dashboard'    => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'tarefas'      => '<path d="M9 11l3 3 8-8"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>',
        'calendario'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'notificacoes' => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'definicoes'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'menu'         => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'chevron'      => '<path d="m9 18 6-6-6-6"/>',
        'search'       => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'sparkles'     => '<path d="M12 3l1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15z"/>',
        'zap'          => '<path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/>',
        'plus'         => '<path d="M12 5v14M5 12h14"/>',
        'arrow'        => '<path d="M7 17 17 7M7 7h10v10"/>',
        'logout'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'clock'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'target'       => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'alert'        => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
        'editar'       => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'apagar'       => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/>',
        default        => '',
    };

    $cl = $classe !== '' ? ' class="' . htmlspecialchars($classe) . '"' : '';
    return '<svg' . $cl . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $corpo . '</svg>';
}

/* ------------------------------------------------------------------
 * Início da página: <head>, sidebar, abertura do conteúdo
 * ---------------------------------------------------------------- */
function html_inicio(string $titulo, string $ativo): void
{
    global $paginas_nav;

    $GLOBALS['pagina_atual'] = $ativo;

    $uid       = uid_atual();
    $util      = utilizador_atual();
    $stats     = $uid ? estatisticas(repositorio_carregar($uid)) : ['pendentes' => 0, 'concluidas' => 0, 'total' => 0];
    $nNaoLidas = $uid ? count(array_filter(notificacoes_carregar($uid, 20), fn($n) => empty($n['lida']))) : 0;
    $iniciais  = mb_strtoupper(mb_substr($util['nome'] ?? '?', 0, 1));

    echo "<!DOCTYPE html>\n<html lang=\"pt\">\n<head>\n<meta charset=\"UTF-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    echo "<title>" . htmlspecialchars($titulo) . " — Habbits</title>\n";
    echo "<meta name=\"description\" content=\"Organize as suas tarefas, acompanhe o seu progresso e dê ritmo ao seu dia com Habbits.\">\n";
    echo "<script>try{var t=localStorage.getItem('habbits-tema')||localStorage.getItem('habbits_tema');if(t!=='light'&&t!=='dark'&&t!=='violet')t='violet';document.documentElement.setAttribute('data-theme',t);}catch(e){document.documentElement.setAttribute('data-theme','violet');}</script>\n";
    echo "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
    echo "<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
    echo "<link rel=\"stylesheet\" href=\"https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Work+Sans:wght@300;400;500;600;700&display=swap\">\n";
    echo "<link rel=\"stylesheet\" href=\"assets/css/style.css?v=6\">\n";
    echo "<script src=\"assets/js/tema.js?v=6\" defer></script>\n";
    echo "</head>\n<body data-pagina=\"" . htmlspecialchars($ativo) . "\">\n";
    echo "<div class=\"workspace\">\n";

    /* ---------------- SIDEBAR ---------------- */
    echo "<aside class=\"app-sidebar\" id=\"sidebar\" aria-label=\"Navegação principal\">\n";
    echo "<a class=\"brand-mark\" href=\"index.php\" aria-label=\"Habbits — início\">";
    echo "<span class=\"brand-icon\">" . icone('check') . "</span>";
    echo "<span class=\"brand-name\">habbits<span class=\"brand-period\">.</span></span>";
    echo "</a>\n";

    echo "<div class=\"sidebar-section-label\">Espaço de trabalho</div>\n";
    echo "<nav class=\"sidebar-navigation\" aria-label=\"Secções\">\n";

    foreach ($paginas_nav as $chave => [$href, $rotulo]) {
        $class = $chave === $ativo ? 'nav-item nav-item-active' : 'nav-item';
        $aria  = $chave === $ativo ? ' aria-current="page"' : '';
        $badge = '';

        if ($chave === 'tarefas') {
            $vis = $stats['pendentes'] > 0 ? '' : ' style="display:none"';
            $badge = '<span class="nav-count" id="badgeTarefas"' . $vis . '>' . (int)$stats['pendentes'] . '</span>';
        } elseif ($chave === 'notificacoes') {
            $vis = $nNaoLidas > 0 ? '' : ' style="display:none"';
            $badge = '<span class="nav-count nav-count-alert" id="badgeNotifSide"' . $vis . '>' . (int)$nNaoLidas . '</span>';
        }

        echo '<a href="' . $href . '" class="' . $class . '"' . $aria . '>' . icone($chave) . '<span>' . $rotulo . '</span>' . $badge . "</a>\n";
    }

    echo "</nav>\n";

    echo "<div class=\"sidebar-section-label\" style=\"margin-top:20px\">Conta</div>\n";
    echo "<nav class=\"sidebar-navigation\" aria-label=\"Conta\">\n";
    $classDef = $ativo === 'definicoes' ? 'nav-item nav-item-active' : 'nav-item';
    echo '<a href="definicoes.php" class="' . $classDef . '">' . icone('definicoes') . '<span>Definições</span></a>' . "\n";
    echo "</nav>\n";

    $iconeZap    = icone('zap');
    $iconeLogout = icone('logout');

    echo <<<HTML
    <div class="sidebar-bottom">
        <div class="sidebar-focus">
            <span class="focus-icon">{$iconeZap}</span>
            <div>
                <span class="focus-title">Um passo de cada vez</span>
                <span class="focus-caption">Consistência também é progresso.</span>
            </div>
        </div>
        <div class="profile-row">
            <a href="definicoes.php" class="profile-avatar" title="O meu perfil">{$iniciais}</a>
            <span class="profile-copy">
                <strong>{$util['nome']}</strong>
                <small>{$util['email']}</small>
            </span>
            <a href="logout.php" class="profile-logout" title="Terminar sessão" aria-label="Terminar sessão">{$iconeLogout}</a>
        </div>
    </div>
</aside>
HTML;

    echo "<main class=\"dashboard-main\">\n";
}

/* ------------------------------------------------------------------
 * Topbar + introdução da página
 * ---------------------------------------------------------------- */
function html_topo(string $titulo, string $subtitulo = '', bool $comBusca = false, string $acoesHtml = '', string $eyebrow = ''): void
{
    global $rotulos_nav;

    $uid   = uid_atual();
    $ativo = $GLOBALS['pagina_atual'] ?? '';
    $rotulo = $rotulos_nav[$ativo] ?? $titulo;

    $buscaHtml = $comBusca
        ? '<label class="search-field">'
            . icone('search')
            . '<input type="search" id="pesquisaInput" aria-label="Pesquisar tarefas" placeholder="Pesquisar tarefas...">'
            . '<span class="search-shortcut" aria-hidden="true">⌘ K</span>'
          . '</label>'
        : '';

    $sininho = '<button type="button" class="btn-icono" id="btnNotif" title="Ativar notificações do sistema" aria-label="Ativar notificações do sistema">'
        . icone('notificacoes')
        . '<span class="ponto" id="pontoNotif" style="display:none"></span>'
        . '</button>';

    $botaoTema = '<button type="button" class="theme-button" id="btnTema" data-tema="violet" title="Tema atual: Púrpura. Clique para mudar." aria-label="Mudar tema">'
        . '<span class="tema-icone" aria-hidden="true">✦</span><span id="temaRotulo">Púrpura</span>'
        . '</button>';

    if ($eyebrow === '') {
        $dias = ['Monday' => 'Segunda-feira', 'Tuesday' => 'Terça-feira', 'Wednesday' => 'Quarta-feira',
                 'Thursday' => 'Quinta-feira', 'Friday' => 'Sexta-feira', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'];
        $diaSemana = $dias[date('l', strtotime(data_local()))] ?? '';
        $eyebrow = $ativo === 'dashboard'
            ? mb_strtoupper($diaSemana . ' bem encaminhada')
            : 'Organizar com calma';
    }

    $pendentes = $uid ? estatisticas(repositorio_carregar($uid))['pendentes'] : 0;
    $streakForte = $pendentes > 0 ? 'Bom ritmo!' : 'Tudo em dia';
    $streakTexto = $pendentes > 0
        ? $pendentes . ' tarefa' . ($pendentes === 1 ? '' : 's') . ' por concluir'
        : 'Sem tarefas em aberto';

    $iconeMenu     = icone('menu');
    $iconeChevron  = icone('chevron');
    $iconeSparkles = icone('sparkles');
    $iconeZap      = icone('zap');

    echo <<<HTML
<header class="topbar">
    <button type="button" class="mobile-menu-button" id="btnMenu" aria-label="Abrir menu">{$iconeMenu}</button>
    <div class="breadcrumb"><span>O meu espaço</span>{$iconeChevron}<span>{$rotulo}</span></div>
    <div class="topbar-actions">
        {$buscaHtml}
        {$botaoTema}
        {$sininho}
        {$acoesHtml}
    </div>
</header>

<section class="page-intro" aria-labelledby="page-title">
    <div class="intro-copy">
        <div class="eyebrow">{$iconeSparkles} {$eyebrow}</div>
        <h1 id="page-title">{$titulo}<span class="heading-dot">.</span></h1>
        <p class="intro-date">{$subtitulo}</p>
    </div>
    <div class="daily-streak">
        <span class="streak-spark">{$iconeZap}</span>
        <span><strong>{$streakForte}</strong><small>{$streakTexto}</small></span>
    </div>
</section>
HTML;
}

/* ------------------------------------------------------------------
 * Fim da página: modal, toasts, scripts (core primeiro!)
 * ---------------------------------------------------------------- */
function html_fim(string $jsAdicional = ''): void
{
    echo "</main>\n</div>\n";

    $iconeSparkles = icone('sparkles');

    echo <<<HTML
<div class="modal-fundo" id="modalFundo">
    <section class="modal task-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
        <header class="modal-heading">
            <div>
                <div class="eyebrow">{$iconeSparkles} Mais um passo</div>
                <h2 id="modalTitulo">Nova tarefa</h2>
                <p>Guarde espaço para o que importa.</p>
            </div>
            <button type="button" class="btn-fechar" id="btnFecharModal" aria-label="Fechar">✕</button>
        </header>
        <form id="formTarefa">
            <input type="hidden" id="fId">
            <div class="form-field">
                <label for="fTitulo">O que precisa de fazer? *</label>
                <input type="text" id="fTitulo" required maxlength="120" placeholder="Escreva uma tarefa...">
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label for="fData">Data *</label>
                    <input type="date" id="fData" required>
                </div>
                <div class="form-field">
                    <label for="fHora">Hora</label>
                    <input type="time" id="fHora">
                </div>
            </div>
            <div class="form-row">
                <div class="form-field">
                    <label for="fPrioridade">Prioridade</label>
                    <select id="fPrioridade">
                        <option value="alta">Alta</option>
                        <option value="media" selected>Média</option>
                        <option value="baixa">Baixa</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="fLembrete">Lembrete</label>
                    <select id="fLembrete">
                        <option value="-1">Sem lembrete</option>
                        <option value="0">No momento</option>
                        <option value="5">5 min antes</option>
                        <option value="10">10 min antes</option>
                        <option value="15" selected>15 min antes</option>
                        <option value="30">30 min antes</option>
                        <option value="60">1 hora antes</option>
                        <option value="120">2 horas antes</option>
                    </select>
                </div>
            </div>
            <div class="form-field">
                <label for="fCat">Categoria</label>
                <input type="text" id="fCat" maxlength="40" placeholder="Trabalho, Pessoal..." list="listaCats">
                <datalist id="listaCats">
                    <option value="Trabalho"><option value="Pessoal"><option value="Clientes"><option value="Saúde">
                </datalist>
            </div>
            <div class="modal-acoes">
                <button type="button" class="btn-secundario" id="btnCancelar">Cancelar</button>
                <button type="submit" class="btn-primario" id="btnGuardar">Guardar tarefa</button>
            </div>
        </form>
    </section>
</div>

<div class="toasts" id="toasts"></div>
<script src="assets/js/core.js?v=6"></script>
HTML;

    if ($jsAdicional !== '') {
        echo '<script src="assets/js/' . htmlspecialchars($jsAdicional) . '?v=6"></script>' . "\n";
    }
}

/* ------------------------------------------------------------------
 * Linha de tarefa (server-render: tarefas.php e calendário)
 * ---------------------------------------------------------------- */
function linha_tarefa_html(array $t): string
{
    $classe  = classe_prioridade($t['prioridade']);
    $rotulo  = rotulo_prioridade($t['prioridade']);
    $feita   = $t['estado'] === 'concluida' ? ' feita' : '';
    $marcado = $t['estado'] === 'concluida' ? ' checked' : '';
    $atrasada = $t['estado'] === 'pendente' && $t['data'] < data_local();

    $escTit = htmlspecialchars($t['titulo'], ENT_QUOTES);
    $escCat = htmlspecialchars($t['cat'], ENT_QUOTES);
    $hora   = htmlspecialchars($t['hora'], ENT_QUOTES);
    $data   = htmlspecialchars($t['data'], ENT_QUOTES);

    $iconeRelogio = icone('clock');
    $iconeEditar  = icone('editar');
    $iconeApagar  = icone('apagar');
    $etiquetaAtraso = $atrasada ? '<span class="late-label">Atrasada</span>' : '';

    return <<<HTML
<li class="tarefa task-row{$feita}" data-id="{$t['id']}" data-titulo="{$escTit}" data-data="{$data}"
    data-hora="{$hora}" data-prioridade="{$t['prioridade']}" data-cat="{$escCat}" data-lembrete="{$t['lembrete']}">
    <label class="check task-checkbox">
        <input type="checkbox"{$marcado} data-id="{$t['id']}" aria-label="Alternar estado da tarefa">
        <span></span>
    </label>
    <div class="task-copy tarefa-info">
        <span class="task-title tarefa-titulo">{$escTit}</span>
        <span class="task-metadata tarefa-meta">
            <span class="task-time">{$iconeRelogio}{$hora}</span>
            <span class="task-meta-separator"></span>
            <span class="task-category">{$escCat}</span>
            {$etiquetaAtraso}
        </span>
    </div>
    <span class="priority-badge tarefa-prio {$classe}">{$rotulo}</span>
    <div class="task-actions tarefa-acoes">
        <button type="button" class="btn-min editar" title="Editar" aria-label="Editar tarefa">{$iconeEditar}</button>
        <button type="button" class="btn-min apagar" title="Apagar" aria-label="Apagar tarefa">{$iconeApagar}</button>
    </div>
</li>
HTML;
}
