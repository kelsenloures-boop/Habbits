/* ============================================================
   Habbits — app.js (Visão geral)
   Renderização do dashboard sobre o estado partilhado do core.js.
   ============================================================ */

const PRIO_ROTULO = { alta: 'Alta', media: 'Média', baixa: 'Baixa' };

const SVG_RELOGIO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
const SVG_EDITAR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
const SVG_APAGAR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>';
const SVG_CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';

/* filtro local do dashboard (Todas / Por fazer / Concluídas) */
if (!estado.filtroDashboard) estado.filtroDashboard = 'todas';
if (typeof estado.termoBusca !== 'string') estado.termoBusca = '';

function templateTarefa(t) {
    const feita = t.estado === 'concluida';
    const atrasada = !feita && t.data < estado.hoje;

    return `
    <li class="tarefa task-row${feita ? ' feita task-done' : ''}"
        data-id="${t.id}" data-titulo="${esc(t.titulo)}" data-data="${esc(t.data)}"
        data-hora="${esc(t.hora)}" data-prioridade="${esc(t.prioridade)}" data-cat="${esc(t.cat)}"
        data-lembrete="${esc(t.lembrete)}" data-alarme-som="${Number(t.alarme_som) ? 1 : 0}">
        <label class="check task-checkbox">
            <input type="checkbox" ${feita ? 'checked' : ''} data-id="${t.id}" aria-label="Alternar estado da tarefa">
            <span></span>
        </label>
        <div class="task-copy tarefa-info">
            <span class="task-title tarefa-titulo">${esc(t.titulo)}</span>
            <span class="task-metadata tarefa-meta">
                <span class="task-time">${SVG_RELOGIO}${esc(t.hora)}</span>
                <span class="task-meta-separator"></span>
                <span class="task-category">${esc(t.cat)}</span>
                ${atrasada ? '<span class="late-label">Atrasada</span>' : ''}
            </span>
        </div>
        <span class="priority-badge tarefa-prio ${esc(t.prioridade)}">${PRIO_ROTULO[t.prioridade] || esc(t.prioridade)}</span>
        <div class="task-actions tarefa-acoes">
            <button type="button" class="btn-min editar" title="Editar" aria-label="Editar tarefa">${SVG_EDITAR}</button>
            <button type="button" class="btn-min apagar" title="Apagar" aria-label="Apagar tarefa">${SVG_APAGAR}</button>
        </div>
    </li>`;
}

function estadoVazioHTML(titulo, texto) {
    return `<li class="empty-state">
        <span class="empty-icon">${SVG_CHECK}</span>
        <strong>${esc(titulo)}</strong>
        <small>${esc(texto)}</small>
    </li>`;
}

const filtroBusca = (t) => {
    if (!estado.termoBusca) return true;
    const alvo = `${t.titulo} ${t.cat}`.toLowerCase();
    return alvo.includes(estado.termoBusca);
};

function filtroEstado(t) {
    if (estado.filtroDashboard === 'pendentes') return t.estado !== 'concluida';
    if (estado.filtroDashboard === 'concluidas') return t.estado === 'concluida';
    return true;
}

function renderListaHoje() {
    const lista = $('#listaHoje');
    if (!lista) return;

    const todasHoje = estado.tarefas
        .filter((t) => t.data === estado.hoje)
        .sort((a, b) => a.hora.localeCompare(b.hora));

    const visiveis = todasHoje
        .filter(filtroBusca)
        .filter(filtroEstado)
        .sort((a, b) => a.hora.localeCompare(b.hora));

    const contagem = $('#filtroTodas');
    if (contagem) contagem.textContent = todasHoje.length;

    const textoContagem = $('#tarefasContagem');
    if (textoContagem) textoContagem.textContent = visiveis.length;

    if (!visiveis.length) {
        const mensagem = estado.termoBusca
            ? ['Não encontrámos tarefas', 'Experimenta outra pesquisa.']
            : estado.filtroDashboard === 'concluidas'
                ? ['Ainda nada concluído hoje', 'Conclui uma tarefa para a ver aqui.']
                : ['Tudo tranquilo por aqui', 'Ajusta os filtros ou aproveita este momento.'];
        lista.innerHTML = estadoVazioHTML(mensagem[0], mensagem[1]);
        return;
    }

    lista.innerHTML = visiveis.map(templateTarefa).join('');
    ligarEventosTarefa(lista);
}

function renderListaAmanha() {
    const lista = $('#listaAmanha');
    if (!lista) return;

    const deAmanha = estado.tarefas
        .filter((t) => t.data === estado.amanha && filtroBusca(t))
        .sort((a, b) => a.hora.localeCompare(b.hora));

    const resumo = $('#nAmanha');
    if (resumo) {
        resumo.textContent = `${deAmanha.length} tarefa${deAmanha.length === 1 ? '' : 's'} agendada${deAmanha.length === 1 ? '' : 's'}`;
    }

    if (!deAmanha.length) {
        lista.innerHTML = estadoVazioHTML('Nada planeado para amanhã', 'Um dia livre também é um bom plano.');
        return;
    }

    lista.innerHTML = deAmanha.map(templateTarefa).join('');
    ligarEventosTarefa(lista);
}

function renderStats(u) {
    const s = u?.stats ?? estado.stats ?? {};
    const taxa = Number(s.taxa_conclusao ?? 0);

    if ($('#stPendentes')) $('#stPendentes').textContent = s.pendentes ?? 0;
    if ($('#stConcluidasHoje')) $('#stConcluidasHoje').textContent = s.concluidas_hoje ?? 0;
    if ($('#stAtrasadas')) $('#stAtrasadas').textContent = s.atrasadas ?? 0;
    if ($('#stTaxa')) $('#stTaxa').textContent = taxa + '%';

    const anel = $('#anelProgresso');
    if (anel) {
        anel.style.setProperty('--pct', taxa);
        anel.setAttribute('aria-label', `${taxa}% das tarefas concluídas`);
    }

    const circulo = $('#anelCirculo');
    if (circulo) circulo.setAttribute('stroke-dasharray', `${taxa} 100`);

    if ($('#anelPct')) $('#anelPct').textContent = taxa;
    if ($('#legConcluidas')) $('#legConcluidas').textContent = s.concluidas ?? 0;
    if ($('#legPendentes')) $('#legPendentes').textContent = s.pendentes ?? 0;

    const nHoje = $('#nHoje');
    if (nHoje) nHoje.textContent = estado.tarefas.filter((t) => t.data === estado.hoje && t.estado !== 'concluida').length;
}

function render(u) {
    renderStats(u);
    renderListaHoje();
    renderListaAmanha();
}

/* hook chamado pelo core após cada carregamento do estado */
window.onEstadoAtualizado = (u) => render(u);

/* tabs do dashboard */
document.querySelectorAll('#filtrosDashboard .filter-button').forEach((botao) => {
    botao.addEventListener('click', () => {
        estado.filtroDashboard = botao.dataset.filtro || 'todas';

        document.querySelectorAll('#filtrosDashboard .filter-button').forEach((outro) => {
            const ativo = outro === botao;
            outro.classList.toggle('filter-active', ativo);
            outro.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        });

        renderListaHoje();
    });
});

/* pesquisa em tempo real */
$('#pesquisaInput')?.addEventListener('input', (evento) => {
    estado.termoBusca = evento.target.value.toLowerCase().trim();
    renderListaHoje();
    renderListaAmanha();
});

/* primeira pintura com os dados vindos do PHP (evita lista vazia) */
render();
