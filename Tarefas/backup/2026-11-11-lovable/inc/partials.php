<?php
/**
 * Habbits — Partials de UI partilhados entre as páginas.
 * (html_inicio/topo/fim, sidebar, modal de tarefa)
 */

require_once __DIR__ . '/bootstrap.php';

$paginas_nav = [
    'dashboard'     => ['index.php',        'Dashboard'],
    'tarefas'       => ['tarefas.php',      'Minhas Tarefas'],
    'calendario'    => ['calendario.php',   'Calendário'],
    'notificacoes'  => ['notificacoes.php', 'Notificações'],
    'definicoes'    => ['definicoes.php',   'Definições'],
];

/* ---------- início da página (doctype, estilo, sidebar, abre conteúdo) ---------- */
function html_inicio(string $titulo, string $ativo): void {
    global $paginas_nav;
    $uid = uid_atual();
    $util = utilizador_atual();
    $stats = $uid ? estatisticas(repositorio_carregar($uid)) : ['pendentes' => 0];
    $nNaoLidas = $uid ? count(array_filter(notificacoes_carregar($uid, 20), fn($n) => empty($n['lida']))) : 0;

    echo "<!DOCTYPE html>\n<html lang=\"pt\">\n<head>\n<meta charset=\"UTF-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    echo "<title>" . htmlspecialchars($titulo) . " — Habbits</title>\n";
    echo "<script>try{var t=localStorage.getItem('habbits_tema');if(t!=='light'&&t!=='purple'&&t!=='dark')t='dark';document.documentElement.setAttribute('data-tema',t);}catch(e){document.documentElement.setAttribute('data-tema','dark');}</script>";
    echo '<script src="assets/js/tema.js?v=5" defer></script>';

    echo "<link rel=\"stylesheet\" href=\"assets/css/style.css?v=5\">\n</head>\n<body>\n";
    echo "<div class=\"layout\">\n";

    /* ---------------- SIDEBAR ---------------- */
    echo "<aside class=\"sidebar\" id=\"sidebar\">\n";
    echo "<a class=\"sidebar-logo\" href=\"index.php\"><span class=\"logo-mark\">✓</span><span class=\"logo-text\">Hab<span>bits</span></span></a>\n";
    echo "<nav class=\"sidebar-nav\">\n<span class=\"nav-section\">Menu</span>\n";

    $icones = [
        'dashboard'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
        'tarefas'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3 8-8"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>',
        'calendario'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
        'notificacoes' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>',
        'definicoes'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>',
    ];

    foreach ($paginas_nav as $chave => [$href, $rotulo]) {
        $class = $chave === $ativo ? 'nav-item ativo' : 'nav-item';
        $badge = '';
        if ($chave === 'tarefas' && $stats['pendentes'] > 0) {
            $badge = '<span class="badge" id="badgeTarefas">' . $stats['pendentes'] . '</span>';
        } elseif ($chave === 'notificacoes') {
            $vis = $nNaoLidas > 0 ? '' : ' style="display:none"';
            $badge = '<span class="badge notif" id="badgeNotifSide"' . $vis . '>' . $nNaoLidas . '</span>';
        }
        echo "<a href=\"{$href}\" class=\"{$class}\">{$icones[$chave]}{$rotulo}{$badge}</a>\n";
    }

    $iniciais = mb_strtoupper(mb_substr($util['nome'] ?? '?', 0, 1));
    echo "</nav>\n";
    echo <<<HTML
    <div class="sidebar-footer">
        <a href="definicoes.php" class="avatar" title="Perfil">{$iniciais}</a>
        <div class="user-info" style="flex:1">
            <strong>{$util['nome']}</strong>
            <small>{$util['email']}</small>
        </div>
        <a href="logout.php" class="btn-sair" title="Terminar sessão">⎋</a>
    </div>
</aside>
HTML;
    echo "<main class=\"conteudo\">\n";
}

/* ---------- topo do conteúdo (título + ações, por página) ---------- */
function html_topo(string $titulo, string $subtitulo = '', bool $comBusca = false, string $acoesHtml = ''): void {
    $buscaHtml = $comBusca
        ? '<div class="pesquisa"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg><input type="search" id="pesquisaInput" placeholder="Pesquisar tarefas…"></div>'
        : '';
    $sininho = '<button class="btn-icono notif-btn" id="btnNotif" title="Ativar notificações do sistema"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg><span class="ponto" id="pontoNotif" style="display:none"></span></button>';

    $temasHtml = '<div class="tema-switch" id="temaSwitch" role="group" aria-label="Tema">';
    foreach (['dark' => '◐', 'light' => '☀', 'purple' => '✦'] as $tema => $icone) {
        $sel = ($tema === 'dark') ? ' tema-btn ativo' : ' tema-btn';
        $temasHtml .= '<button type="button" class="' . $sel . '" data-tema="' . $tema . '" title="Tema ' . $tema . '">' . $icone . '</button>';
    }
    $temasHtml .= '</div>';

    echo <<<HTML
    echo <<<HTML
<header class="topbar">
    <button class="hamburger" id="btnMenu" aria-label="Abrir menu">☰</button>
    <div class="topbar-saudacao">
        <h1>{$titulo}</h1>
        <p>{$subtitulo}</p>
    </div>
    <div class="topbar-acoes">
        {$buscaHtml}
        {$sininho}
        {$temasHtml}
        {$acoesHtml}
    </div>
</header>
HTML;
}

/* ---------- corpo da página (as páginas imprimem os seus cartões) ---------- */
function html_fim(string $jsAdicional = ''): void {
    echo $jsAdicional ? "<script src=\"assets/js/{$jsAdicional}?v=5\"></script>\n" : "";
    echo "</main>\n</div>\n";
    echo <<<HTML
<!-- Modal de tarefa (partilhado) -->
<div class="modal-fundo" id="modalFundo">
    <div class="modal" role="dialog" aria-modal="true">
        <header class="modal-cab">
            <h2 id="modalTitulo">Nova tarefa</h2>
            <button class="btn-fechar" id="btnFecharModal" aria-label="Fechar">✕</button>
        </header>
        <form id="formTarefa">
            <input type="hidden" id="fId">
            <div class="campo">
                <label for="fTitulo">Título *</label>
                <input type="text" id="fTitulo" required maxlength="120" placeholder="O que precisa de fazer?">
            </div>
            <div class="linha-2">
                <div class="campo">
                    <label for="fData">Data *</label>
                    <input type="date" id="fData" required>
                </div>
                <div class="campo">
                    <label for="fHora">Hora</label>
                    <input type="time" id="fHora">
                </div>
            </div>
            <div class="linha-2">
                <div class="campo">
                    <label for="fPrioridade">Prioridade</label>
                    <select id="fPrioridade">
                        <option value="alta">Alta</option>
                        <option value="media" selected>Média</option>
                        <option value="baixa">Baixa</option>
                    </select>
                </div>
                <div class="campo">
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
            <div class="campo">
                <label for="fCat">Categoria</label>
                <input type="text" id="fCat" maxlength="40" placeholder="Trabalho, Pessoal…" list="listaCats">
                <datalist id="listaCats">
                    <option value="Trabalho"><option value="Pessoal"><option value="Clientes"><option value="Saúde">
                </datalist>
            </div>
            <div class="modal-acoes">
                <button type="button" class="btn-secundario" id="btnCancelar">Cancelar</button>
                <button type="submit" class="btn-primario" id="btnGuardar">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div class="toasts" id="toasts"></div>
<script src="assets/js/core.js?v=5"></script>
HTML;
}

/* ---------- linha de tarefa (server-render, para tarefas.php e calendário) ---------- */
function linha_tarefa_html(array $t): string {
    $classe = classe_prioridade($t['prioridade']);
    $rotulo = rotulo_prioridade($t['prioridade']);
    $feita  = $t['estado'] === 'concluida' ? ' feita' : '';
    $marcado = $t['estado'] === 'concluida' ? 'checked' : '';
    $escTit = htmlspecialchars($t['titulo']);
    $escCat = htmlspecialchars($t['cat']);

    return <<<HTML
<li class="tarefa{$feita}" data-id="{$t['id']}" data-titulo="{$escTit}" data-data="{$t['data']}"
    data-hora="{$t['hora']}" data-prioridade="{$t['prioridade']}" data-cat="{$escCat}" data-lembrete="{$t['lembrete']}">
    <label class="check">
        <input type="checkbox" {$marcado} data-id="{$t['id']}">
        <span></span>
    </label>
    <div class="tarefa-info">
        <span class="tarefa-titulo">{$escTit}</span>
        <div class="tarefa-meta">
            <span class="hora">🕐 {$t['hora']}</span>
            <span class="cat">{$escCat}</span>
        </div>
    </div>
    <span class="prio {$classe}">{$rotulo}</span>
    <div class="tarefa-acoes">
        <button class="btn-min editar" title="Editar">✎</button>
        <button class="btn-min apagar" title="Apagar">🗑</button>
    </div>
</li>
HTML;
}
