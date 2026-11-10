/* ============================================================
   Habbits — app.js (página Dashboard)
   Convene core.js; fica só com a renderização do dashboard.
   ============================================================ */

const PRIO_ROTULO = { alta: 'Alta', media: 'Média', baixa: 'Baixa' };

function templateTarefa(t) {
    return `
    <li class="tarefa ${t.estado === 'concluida' ? 'feita' : ''}" data-id="${t.id}">
        <label class="check">
            <input type="checkbox" ${t.estado === 'concluida' ? 'checked' : ''} data-id="${t.id}">
            <span></span>
        </label>
        <div class="tarefa-info">
            <span class="tarefa-titulo">${esc(t.titulo)}</span>
            <div class="tarefa-meta">
                <span class="hora">🕐 ${esc(t.hora)}</span>
                <span class="cat">${esc(t.cat)}</span>
            </div>
        </div>
        <span class="prio ${t.prioridade}">${PRIO_ROTULO[t.prioridade] || t.prioridade}</span>
        <div class="tarefa-acoes">
            <button class="btn-min editar" title="Editar">✎</button>
            <button class="btn-min apagar" title="Apagar">🗑</button>
        </div>
    </li>`;
}

function templateTarefaAmanha(t) {
    return `
    <li class="tarefa ${t.estado === 'concluida' ? 'feita' : ''}" data-id="${t.id}">
        <span class="hora-inline">${esc(t.hora)}</span>
        <div class="tarefa-info">
            <span class="tarefa-titulo">${esc(t.titulo)}</span>
            <div class="tarefa-meta"><span class="cat">${esc(t.cat)}</span></div>
        </div>
        <span class="prio ${t.prioridade}">${PRIO_ROTULO[t.prioridade] || t.prioridade}</span>
        <div class="tarefa-acoes">
            <button class="btn-min editar" title="Editar">✎</button>
            <button class="btn-min apagar" title="Apagar">🗑</button>
        </div>
    </li>`;
}

const filtroBusca = (t) =>
    !estado.termoBusca || t.titulo.toLowerCase().includes(estado.termoBusca);

function renderListaHoje() {
    const lista = $('#listaHoje');
    const deHoje = estado.tarefas
        .filter(t => t.data === estado.hoje && filtroBusca(t))
        .sort((a, b) => a.hora.localeCompare(b.hora));

    if (!deHoje.length) {
        lista.innerHTML = `<div class="vazio">${estado.tarefas.some(t => t.data === estado.hoje)
            ? '🔒 Nenhuma corresponde à pesquisa.'
            : '🎉 Sem tarefas para hoje. Aproveite!'}</div>`;
        return;
    }
    lista.innerHTML = deHoje.map(templateTarefa).join('');
    ligarEventosTarefa(lista);
}

function renderListaAmanha() {
    const lista = $('#listaAmanha');
    const deAmanha = estado.tarefas
        .filter(t => t.data === estado.amanha && filtroBusca(t))
        .sort((a, b) => a.hora.localeCompare(b.hora));

    $('#nAmanha').textContent = `${deAmanha.length} tarefa${deAmanha.length === 1 ? '' : 's'} agendada${deAmanha.length === 1 ? '' : 's'}`;

    if (!deAmanha.length) {
        lista.innerHTML = `<div class="vazio">Nada planeado para amanhã.</div>`;
        return;
    }
    lista.innerHTML = deAmanha.map(templateTarefaAmanha).join('');
    ligarEventosTarefa(lista);
}

function renderStats(u) {
    const s = u?.stats ?? estado.stats;
    $('#stPendentes').textContent = s.pendentes ?? 0;
    $('#stConcluidasHoje').textContent = s.concluidas_hoje ?? 0;
    $('#stAtrasadas').textContent = s.atrasadas ?? 0;
    $('#stTaxa').textContent = (s.taxa_conclusao ?? 0) + '%';
    $('#anelProgresso').style.setProperty('--pct', s.taxa_conclusao ?? 0);
    $('#anelPct').textContent = s.taxa_conclusao ?? 0;
    $('#legConcluidas').textContent = s.concluidas ?? 0;
    $('#legPendentes').textContent = s.pendentes ?? 0;
    $('#nHoje').textContent = estado.tarefas.filter(t => t.data === estado.hoje).length;
}

function render(u) {
    renderStats(u);
    renderListaHoje();
    renderListaAmanha();
}

/* hook chamado pelo core após cada carregamento do estado */
window.onEstadoAtualizado = (u) => render(u);

/* Atualizações iniciais vêm do PHP (ESTADO_INICIAL) + carregarTudo() do core */

/* Pesquisa em tempo real */
$('#pesquisaInput')?.addEventListener('input', (e) => {
    estado.termoBusca = e.target.value.toLowerCase().trim();
    renderListaHoje();
    renderListaAmanha();
});
