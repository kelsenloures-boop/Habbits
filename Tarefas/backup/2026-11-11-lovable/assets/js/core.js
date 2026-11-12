/* ============================================================
   Habbits — core.js (motor partilhado por todas as páginas)
   CRUD, modal, toasts, notificações e menu.
   As páginas definem hooks ANTES de carregar core.js:
     window.onEstadoAtualizado  → chamado após cada carga do estado
   O arranque por página usa body[data-pagina].
   ============================================================ */

const API = 'api/tarefas.php';
const API_NOTIFICACOES = 'api/notificacoes.php';
const INTERVALO_BUSCA = 30 * 1000;

/* Estado global da sessão */
window.estado = {
    hoje: window.ESTADO_INICIAL?.hoje || new Date().toISOString().slice(0, 10),
    amanha: window.ESTADO_INICIAL?.amanha || null,
    tarefas: window.ESTADO_INICIAL?.tarefas || [],
    stats: window.ESTADO_INICIAL?.stats || {},
    notificacoes: [],
    naoLidas: 0,
};

/* ===================== UTILITÁRIOS ===================== */

const esc = (s) => String(s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const $ = (sel) => document.querySelector(sel);

/* --- toast --- */
function toast(texto, tipo = 'ok') {
    const t = document.createElement('div');
    t.className = `toast ${tipo}`;
    t.textContent = texto;
    $('#toasts')?.appendChild(t);
    setTimeout(() => t.classList.add('visivel'), 10);
    setTimeout(() => { t.classList.remove('visivel'); setTimeout(() => t.remove(), 300); }, 2600);
}

/* --- toast + notificação do sistema (desktop) --- */
function avisar(titulo, texto) {
    toast(texto, 'aviso');
    if ('Notification' in window && Notification.permission === 'granted') {
        try { new Notification('Habbits — ' + titulo, { body: texto }); } catch { /* silencioso */ }
    }
}

async function pedirPermissaoSistema() {
    if (!('Notification' in window)) return false;
    if (Notification.permission === 'granted') return true;
    if (Notification.permission === 'denied') return false;
    return (await Notification.requestPermission()) === 'granted';
}

/* ===================== API DE TAREFAS ===================== */

/* Pedidos HTTP à API de tarefas (sessão via cookie) */
async function apiTarefas(metodo, corpo = null) {
    const url = metodo === 'DELETE' && corpo?.id ? `${API}?id=${corpo.id}` : API;
    const opcoes = { method: metodo, headers: { 'Content-Type': 'application/json' } };
    if (metodo !== 'GET' && metodo !== 'DELETE' && corpo) opcoes.body = JSON.stringify(corpo);

    const res = await fetch(url, opcoes);
    if (res.status === 401) { location.href = 'login.php'; throw new Error('Sessão expirada.'); }
    const dados = await res.json();
    if (!res.ok) throw new Error(dados.erro || `Falha na comunicação (${res.status}).`);
    return dados;
}

/* Busca tudo à API e aplica ao estado; notifica as páginas */
async function carregarTudo(filtros = null, metodo = 'GET', corpo = null) {
    let dados;
    if (metodo === 'GET') {
        const qs = filtros ? '?' + new URLSearchParams(filtros) : '';
        const res = await fetch(API + qs);
        if (res.status === 401) { location.href = 'login.php'; throw new Error('Sessão expirada.'); }
        dados = await res.json();
    } else {
        dados = await apiTarefas(metodo, corpo);
    }

    estado.tarefas = dados.tarefas;
    estado.stats = dados.stats;
    window.onEstadoAtualizado?.(dados);
    return dados;
}

/* POST completo na API de tarefas com corpo JSON */
async function apiPost(url, corpo) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(corpo),
    });
    if (res.status === 401) { location.href = 'login.php'; throw new Error('Sessão expirada.'); }
    const dados = await res.json();
    if (!res.ok) throw new Error(dados.erro || 'Falha na comunicação.');
    return dados;
}

/* ===================== OPERAÇÕES DE TAREFA ===================== */

async function criarTarefa(dados) {
    await carregarTudo(null, 'POST', dados);
    modalFechar();
    toast('Tarefa criada com sucesso ✨');
}

async function atualizarTarefa(id, campos) {
    await carregarTudo(null, 'PATCH', { id: Number(id), ...campos });
}

async function mudarEstado(checkbox) {
    const id = checkbox.dataset.id;
    const concluida = checkbox.checked;
    checkbox.checked = !concluida;

    try {
        await atualizarTarefa(id, { estado: concluida ? 'concluida' : 'pendente' });
        checkbox.checked = concluida;
        checkbox.closest('.tarefa')?.classList.toggle('feita', concluida);
        toast(concluida ? 'Tarefa concluída ✅' : 'Tarefa reaberta', concluida ? 'ok' : 'aviso');
    } catch (erro) {
        checkbox.checked = !concluida;
        toast(erro.message, 'erro');
    }
}

async function apagarTarefa(id) {
    const t = estado.tarefas.find(x => String(x.id) === String(id));
    const titulo = t?.titulo || '';
    if (!confirm(`Apagar${titulo ? ` "${titulo}"` : ' esta tarefa'}? Esta ação não pode ser revertida.`)) return;

    try {
        await carregarTudo(null, 'DELETE', { id: Number(id) });
        toast('Tarefa apagada 🗑');
    } catch (erro) {
        toast(erro.message, 'erro');
    }
}

/* Liga os botões de uma linha de tarefa (server-render ou client-render) */
function ligarEventosTarefa(container) {
    container.querySelectorAll('.check input').forEach(cb =>
        cb.addEventListener('change', () => mudarEstado(cb)));
    container.querySelectorAll('.btn-min.editar').forEach(b =>
        b.addEventListener('click', () => abrirEdicaoDeElemento(b.closest('.tarefa'))));
    container.querySelectorAll('.btn-min.apagar').forEach(b =>
        b.addEventListener('click', () => apagarTarefa(b.closest('.tarefa').dataset.id)));
}

/* Preenche o modal a partir dos data-attributes da linha */
function abrirEdicaoDeElemento(el) {
    modalAbrir({
        id: el.dataset.id,
        titulo: el.dataset.titulo,
        data: el.dataset.data,
        hora: el.dataset.hora,
        prioridade: el.dataset.prioridade,
        cat: el.dataset.cat,
        lembrete: el.dataset.lembrete,
    });
}

/* ===================== MODAL ===================== */

function modalAbrir(tarefa = null, dataPreSelecionada = null) {
    $('#modalTitulo').textContent = tarefa ? 'Editar tarefa' : 'Nova tarefa';
    $('#fId').value = tarefa?.id ?? '';
    $('#fTitulo').value = tarefa?.titulo ?? '';
    $('#fData').value = tarefa?.data ?? dataPreSelecionada ?? dataHoje();
    $('#fHora').value = tarefa?.hora ?? '';
    $('#fPrioridade').value = tarefa?.prioridade ?? 'media';
    $('#fLembrete').value = String(tarefa?.lembrete ?? 15);
    $('#fCat').value = tarefa?.cat ?? '';
    $('#modalFundo').classList.add('aberto');
    setTimeout(() => $('#fTitulo').focus(), 50);
}

function modalFechar() {
    $('#modalFundo').classList.remove('aberto');
    $('#formTarefa').reset();
}

function dataHoje() {
    if (estado.hoje) return estado.hoje;
    return new Date().toLocaleDateString('sv'); // YYYY-MM-DD no fuso local
}

$('#formTarefa')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = $('#fId').value;
    const dados = {
        titulo: $('#fTitulo').value.trim(),
        data: $('#fData').value,
        hora: $('#fHora').value || '08:00',
        prioridade: $('#fPrioridade').value,
        lembrete: parseInt($('#fLembrete').value, 10),
        cat: $('#fCat').value.trim() || 'Pessoal',
    };

    if (!dados.titulo || !dados.data) { toast('Título e data são obrigatórios.', 'erro'); return; }

    $('#btnGuardar').disabled = true;
    try {
        if (id) {
            await atualizarTarefa(id, dados);
            modalFechar();
            toast('Tarefa atualizada ✏️');
        } else {
            await criarTarefa(dados);
        }
    } catch (erro) {
        toast(erro.message, 'erro');
    } finally {
        $('#btnGuardar').disabled = false;
    }
});

$('#btnNovaTarefa')?.addEventListener('click', () => modalAbrir());
$('#btnFecharModal')?.addEventListener('click', modalFechar);
$('#btnCancelar')?.addEventListener('click', modalFechar);
$('#modalFundo')?.addEventListener('mousedown', (e) => { if (e.target === e.currentTarget) modalFechar(); });
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && $('#modalFundo')?.classList.contains('aberto')) modalFechar();
});

/* ===================== NOTIFICAÇÕES E LEMBRETES ===================== */

const ICONES_NOTIF = { alert: '⚠️', check: '✅', clock: '🕒', bell: '🔔' };
let ultimoIdNotif = 0;
let primeiroBusque = true;

async function cicloNotificacoes() {
    const url = primeiroBusque ? API_NOTIFICACOES : `${API_NOTIFICACOES}?depois=${ultimoIdNotif}`;
    try {
        const res = await fetch(url);
        if (res.status === 401) return; // em login
        const dados = await res.json();

        const novas = dados.novas ?? [];
        estado.notificacoes = dados.notificacoes ?? [];
        estado.naoLidas = dados.naoLidas ?? 0;

        if (estado.notificacoes.length) {
            ultimoIdNotif = Math.max(...estado.notificacoes.map(n => Number(n.id)));
        }

        renderNotificacoes();
        if (!primeiroBusque && novas.length) {
            for (const n of novas) avisar(n.titulo, n.texto);
        }
        primeiroBusque = false;
    } catch { /* silencioso entre buscas */ }
}

/** Painel de notificações (só renderiza onde existir, ex.: dashboard) */
function renderNotificacoes() {
    const lista = $('#listaNotif');
    if (lista) {
        lista.innerHTML = estado.notificacoes.length
            ? estado.notificacoes.map(n => `
                <li class="${n.lida ? 'lida' : 'nao-lida'}" data-id="${n.id}">
                    <span class="notif-icone">${ICONES_NOTIF[n.icon] ?? '🔔'}</span>
                    <div>
                        <strong>${esc(n.titulo)}</strong>
                        <p>${esc(n.texto)}</p>
                        <small>${esc(n.tempo)}</small>
                    </div>
                    <button class="btn-min notif-apagar" title="Remover">✕</button>
                </li>`).join('')
            : `<div class="vazio">Sem notificações por agora.</div>`;
    }

    // badges
    const visivel = estado.naoLidas > 0;
    const badge = $('#badgeNotif');
    const badgeSide = $('#badgeNotifSide');
    const ponto = $('#pontoNotif');
    if (badge) { badge.textContent = estado.naoLidas; badge.style.display = visivel && !lista ? '' : (lista ? (visivel ? '' : 'none') : 'none'); }
    if (badgeSide) { badgeSide.textContent = estado.naoLidas; badgeSide.style.display = visivel ? '' : 'none'; }
    if (ponto) ponto.style.display = visivel ? '' : 'none';
}

$('#listaNotif')?.addEventListener('click', async (e) => {
    const li = e.target.closest('li[data-id]');
    if (!li) return;
    const id = Number(li.dataset.id);

    if (e.target.closest('.notif-apagar')) {
        await fetch(`${API_NOTIFICACOES}?id=${id}`, { method: 'DELETE' });
        return cicloNotificacoes();
    }
    if (!li.classList.contains('lida')) {
        await apiPost(API_NOTIFICACOES, { acao: 'marcar_lida', id });
        return cicloNotificacoes();
    }
});

$('#btnMarcarLidas')?.addEventListener('click', (e) => {
    e.preventDefault();
    apiPost(API_NOTIFICACOES, { acao: 'marcar_todas_lidas' })
        .then(() => { cicloNotificacoes(); toast('Notificações marcadas como lidas'); })
        .catch((err) => toast(err.message, 'erro'));
});

$('#btnNotif')?.addEventListener('click', async () => {
    const ok = await pedirPermissaoSistema();
    if (ok) toast('Notificações do sistema ativadas 🔔');
    else toast('Sem permissão — só os avisos dentro da app.', 'aviso');
});

/* ===================== MENU LATERAL (mobile) ===================== */

const overlay = document.createElement('div');
overlay.className = 'overlay';
document.body.appendChild(overlay);
const fecharMenu = () => { $('#sidebar')?.classList.remove('aberto'); overlay.classList.remove('ativo'); };

$('#btnMenu')?.addEventListener('click', () => {
    $('#sidebar')?.classList.toggle('aberto');
    overlay.classList.toggle('ativo');
});
overlay.addEventListener('click', fecharMenu);

/* ===================== ARRANQUE POR PÁGINA ===================== */

cicloNotificacoes();
setInterval(cicloNotificacoes, INTERVALO_BUSCA);
document.addEventListener('visibilitychange', () => { if (!document.hidden) cicloNotificacoes(); });

switch (document.body.dataset.pagina) {
    case 'dashboard':
        carregarTudo();
        break;
    case 'tarefas':
    case 'calendario':
        window.onEstadoAtualizado = () => location.reload();
        break;
}
