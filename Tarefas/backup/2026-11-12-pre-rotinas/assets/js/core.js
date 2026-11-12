/* ============================================================
   Habbits — core.js (motor partilhado por todas as páginas)
   CRUD, modal, toasts, notificações e menu.
   As páginas definem hooks ANTES de carregar core.js:
     window.onEstadoAtualizado  → chamado após cada carga do estado
   O arranque por página usa body[data-pagina].
   ============================================================ */

const API = 'api/tarefas.php';
const API_NOTIFICACOES = 'api/notificacoes.php';
const API_PREFERENCIAS = 'api/preferencias.php';
const INTERVALO_BUSCA = 15 * 1000;

/* Estado global da sessão */
window.estado = {
    hoje: window.ESTADO_INICIAL?.hoje || new Date().toISOString().slice(0, 10),
    amanha: window.ESTADO_INICIAL?.amanha || null,
    tarefas: window.ESTADO_INICIAL?.tarefas || [],
    stats: window.ESTADO_INICIAL?.stats || {},
    notificacoes: [],
    naoLidas: 0,
    preferencias: {
        canal: 'interno',
        sons: 1,
        som_alarme: 0,
        volume: 80,
    },
};

/* ===================== UTILITÁRIOS ===================== */

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
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

/* --- toast + som (a notificação Windows chega pelo processo principal) --- */
function avisar(titulo, texto, som = false) {
    const canal = estado.preferencias?.canal ?? 'interno';
    if (canal === 'desligado') return;

    // O toast nativo é mostrado pelo processo principal (desktop/main.js),
    // que lê a mesma base de dados. Aqui fica apenas o aviso dentro da app.
    toast(texto, 'aviso');

    if (som) tocarSomAlarme();
}

/* --- som de alarme: loop curto com limite de segurança e sem duplicação --- */
let somAlarmeAudio = null;
let somAlarmeTimer = null;
let ultimoSomEm = 0;

function tocarSomAlarme() {
    const prefs = estado.preferencias ?? {};
    if (!Number(prefs.sons) || !Number(prefs.som_alarme)) return;

    const agora = Date.now();
    if (agora - ultimoSomEm < 5000) return; // evita duplicação entre ciclos
    ultimoSomEm = agora;

    try {
        if (!somAlarmeAudio) {
            somAlarmeAudio = new Audio('assets/audio/alarme.wav');
            somAlarmeAudio.loop = true;
        }
        somAlarmeAudio.volume = Math.max(0, Math.min(1, Number(prefs.volume ?? 80) / 100));

        clearTimeout(somAlarmeTimer);
        const limite = Math.max(10, Math.min(600, Number(prefs.limite_som_seg) || 120));
        somAlarmeTimer = setTimeout(pararSomAlarme, limite * 1000);

        somAlarmeAudio.currentTime = 0;
        somAlarmeAudio.play().catch(() => { /* autoplay bloqueado até haver interação */ });
    } catch { /* silencioso */ }
}

function pararSomAlarme() {
    clearTimeout(somAlarmeTimer);
    somAlarmeTimer = null;
    if (!somAlarmeAudio) return;
    try {
        somAlarmeAudio.pause();
        somAlarmeAudio.currentTime = 0;
    } catch { /* silencioso */ }
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
    estado.carregado = true;
    atualizarBadgeTarefas();
    // Mantém a lista de notificações em sincronia sem repetir avisos do próprio utilizador.
    cicloNotificacoes(false);
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
        checkbox.closest('.tarefa')?.classList.toggle('task-done', concluida);
        toast(concluida ? 'Tarefa concluída ✅' : 'Tarefa reaberta', concluida ? 'ok' : 'aviso');
    } catch (erro) {
        checkbox.checked = !concluida;
        toast(erro.message, 'erro');
    }
}

async function apagarTarefa(id, tituloFornecido = '') {
    const t = estado.tarefas.find(x => String(x.id) === String(id));
    const titulo = tituloFornecido || t?.titulo || '';
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
        b.addEventListener('click', () => {
            const linha = b.closest('.tarefa');
            apagarTarefa(linha.dataset.id, linha.dataset.titulo || '');
        }));
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
        alarme_som: el.dataset.alarmeSom,
    });
}

/* ===================== MODAL ===================== */

function modalAbrir(tarefa = null, dataPreSelecionada = null) {
    if (!$('#modalFundo')) return;
    $('#modalTitulo').textContent = tarefa ? 'Editar tarefa' : 'Nova tarefa';
    $('#fId').value = tarefa?.id ?? '';
    $('#fTitulo').value = tarefa?.titulo ?? '';
    $('#fData').value = tarefa?.data ?? dataPreSelecionada ?? dataHoje();
    $('#fHora').value = tarefa?.hora ?? '';
    $('#fPrioridade').value = tarefa?.prioridade ?? 'media';
    $('#fLembrete').value = String(tarefa?.lembrete ?? 15);
    $('#fCat').value = tarefa?.cat ?? '';
    const som = $('#fSom');
    if (som) som.checked = String(tarefa?.alarme_som ?? '0') === '1';
    sincronizarSomModal();
    $('#modalFundo').classList.add('aberto');
    document.body.style.overflow = 'hidden';
    setTimeout(() => $('#fTitulo').focus(), 60);
}

/* O som só faz sentido em prioridade alta */
function sincronizarSomModal() {
    const som = $('#fSom');
    const prioridade = $('#fPrioridade');
    if (!som || !prioridade) return;

    const alta = prioridade.value === 'alta';
    som.disabled = !alta;
    if (!alta) som.checked = false;
    som.closest('.pref-linha')?.classList.toggle('desativado', !alta);
}

function modalFechar() {
    $('#modalFundo')?.classList.remove('aberto');
    document.body.style.overflow = '';
    $('#formTarefa')?.reset();
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
        alarme_som: $('#fPrioridade').value === 'alta' && $('#fSom')?.checked ? 1 : 0,
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
$('#fPrioridade')?.addEventListener('change', sincronizarSomModal);
document.querySelectorAll('[data-abrir-modal]').forEach((botao) =>
    botao.addEventListener('click', () => modalAbrir(null, botao.dataset.dia || null)));
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
const alarmesAbertos = new Set();

function sincronizarUltimoId() {
    if (estado.notificacoes.length) {
        ultimoIdNotif = Math.max(...estado.notificacoes.map(n => Number(n.id)));
    }
}

/* --- alarmes ativos: o desktop abre a janela sozinho; no navegador abrimos uma janela --- */
function tratarAlarmesAtivos(ativos) {
    if (!Array.isArray(ativos) || !ativos.length) return;
    if (window.habbitsDesktop?.disponivel) return; // main.js trata da janela de ecrã inteiro

    for (const o of ativos) {
        const id = Number(o.id);
        if (!id || alarmesAbertos.has(id)) continue;
        alarmesAbertos.add(id);

        const janela = window.open(
            'alarme.php?ocorrencia=' + id,
            'habbits-alarme-' + id,
            'width=920,height=640'
        );

        if (!janela) {
            toast('Alarme a tocar: ' + o.titulo + '. Abre a secção Alarmes para o tratar.', 'aviso');
        }
    }
}

async function cicloNotificacoes(mostrarAvisos = true) {
    const url = primeiroBusque ? API_NOTIFICACOES : `${API_NOTIFICACOES}?depois=${ultimoIdNotif}`;
    try {
        const res = await fetch(url);
        if (res.status === 401) return; // em login
        const dados = await res.json();

        const novas = dados.novas ?? [];
        estado.notificacoes = dados.notificacoes ?? [];
        estado.naoLidas = dados.naoLidas ?? 0;
        sincronizarUltimoId();

        tratarAlarmesAtivos(dados.alarmes);

        renderNotificacoes();
        if (mostrarAvisos && !primeiroBusque && novas.length) {
            // Evita uma rajada de avisos se a app esteve muito tempo fechada.
            for (const n of novas.slice(-3)) avisar(n.titulo, n.texto, Number(n.som) === 1);
        }
        primeiroBusque = false;
    } catch { /* silencioso entre buscas */ }
}

/* --- preferências (interruptor único, persistido no SQLite) --- */
async function carregarPreferencias() {
    try {
        const res = await fetch(API_PREFERENCIAS);
        if (!res.ok) return;
        const dados = await res.json();
        if (dados?.preferencias) estado.preferencias = dados.preferencias;
        aplicarPreferenciasUI();
    } catch { /* mantém os valores por defeito */ }
}

function aplicarPreferenciasUI() {
    const botao = $('#btnNotif');
    if (!botao) return;

    const ligado = (estado.preferencias?.canal ?? 'interno') !== 'desligado';
    botao.classList.toggle('notif-off', !ligado);
    botao.setAttribute('aria-pressed', ligado ? 'true' : 'false');

    const titulo = ligado
        ? 'Notificações ativas. Clique para desativar.'
        : 'Notificações desativadas. Clique para ativar.';
    botao.title = titulo;
    botao.setAttribute('aria-label', titulo);
}

async function alternarNotificacoes() {
    const antes = { ...estado.preferencias };
    const novo = (antes.canal ?? 'interno') === 'desligado' ? 'interno' : 'desligado';

    estado.preferencias.canal = novo;
    aplicarPreferenciasUI();

    try {
        const dados = await apiPost(API_PREFERENCIAS, { canal: novo });
        estado.preferencias = dados.preferencias;
        aplicarPreferenciasUI();
        toast(novo === 'desligado' ? 'Notificações desativadas.' : 'Notificações ativadas.');
    } catch (erro) {
        estado.preferencias = antes;
        aplicarPreferenciasUI();
        toast(erro.message, 'erro');
    }
}

/* --- estado otimista: cópia / restauro --- */
function copiaEstadoNotif() {
    return {
        notificacoes: estado.notificacoes.map(n => ({ ...n })),
        naoLidas: estado.naoLidas,
    };
}

function restauraEstadoNotif(copia) {
    estado.notificacoes = copia.notificacoes;
    estado.naoLidas = copia.naoLidas;
    sincronizarUltimoId();
    renderNotificacoes();
}

function itemNotificacaoHTML(n, acoesCompletas) {
    const lida = Number(n.lida) === 1;
    return `
        <li class="notif-item ${lida ? 'lida' : 'nao-lida'}" data-id="${n.id}">
            <span class="notif-icone" aria-hidden="true">${ICONES_NOTIF[n.icon] ?? '🔔'}</span>
            <div class="notif-body">
                <strong>${esc(n.titulo)}</strong>
                <p>${esc(n.texto)}</p>
                <small>${esc(n.tempo)}</small>
            </div>
            <div class="notif-acoes-inline">
                ${(!lida && acoesCompletas)
                    ? '<button type="button" class="btn-secundario btn-sm notif-marcar">Marcar lida</button>'
                    : ''}
                <button type="button" class="btn-min notif-apagar" title="Remover" aria-label="Remover notificação">✕</button>
            </div>
        </li>`;
}

/** Renderiza todas as listas de notificações e sincroniza contadores */
function renderNotificacoes() {
    const listas = document.querySelectorAll('[data-lista-notif]');
    listas.forEach((lista) => {
        const limite = parseInt(lista.dataset.limite || '0', 10);
        const itens = limite > 0 ? estado.notificacoes.slice(0, limite) : estado.notificacoes;
        const acoesCompletas = lista.dataset.acoes === 'completas';

        lista.innerHTML = itens.length
            ? itens.map(n => itemNotificacaoHTML(n, acoesCompletas)).join('')
            : `<li class="empty-state"><span class="empty-icon" aria-hidden="true">🔔</span><strong>Sem notificações</strong><small>Os teus lembretes aparecem aqui.</small></li>`;
    });

    const comItens = estado.notificacoes.length > 0;
    document.querySelectorAll('[data-rodape-notif]').forEach((rodape) => {
        rodape.style.display = comItens ? '' : 'none';
    });

    const naoLidas = Math.max(0, Number(estado.naoLidas) || 0);
    const visivel = naoLidas > 0;

    ['#badgeNotifSide', '#badgeNotif'].forEach((sel) => {
        const el = $(sel);
        if (el) { el.textContent = naoLidas; el.style.display = visivel ? '' : 'none'; }
    });

    const ponto = $('#pontoNotif');
    if (ponto) ponto.style.display = visivel ? '' : 'none';

    const contador = $('#contadorNaoLidas');
    if (contador) contador.textContent = naoLidas;

    const btnTodas = $('#btnMarcarTodas');
    if (btnTodas) btnTodas.disabled = !visivel;
}

function atualizarBadgeTarefas() {
    const badge = $('#badgeTarefas');
    if (!badge) return;

    let pendentes;
    if (estado.carregado) {
        pendentes = estado.tarefas.filter(t => t.estado !== 'concluida').length;
    } else if (typeof estado.stats?.pendentes === 'number') {
        pendentes = estado.stats.pendentes;
    } else {
        return; // mantém o valor renderizado pelo PHP
    }

    badge.textContent = pendentes;
    badge.style.display = pendentes > 0 ? '' : 'none';
}

/* --- ação otimista partilhada (marcar, apagar, marcar todas, limpar) --- */
async function acaoNotificacao(acao, id, mensagem) {
    const antes = copiaEstadoNotif();
    const alvo = id ? estado.notificacoes.find(n => Number(n.id) === Number(id)) : null;

    if (acao === 'marcar_lida') {
        if (!alvo || Number(alvo.lida) === 1) return;
        alvo.lida = 1;
        estado.naoLidas = Math.max(0, estado.naoLidas - 1);
    } else if (acao === 'apagar') {
        if (!alvo) return;
        estado.notificacoes = estado.notificacoes.filter(n => Number(n.id) !== Number(id));
        if (Number(alvo.lida) === 0) estado.naoLidas = Math.max(0, estado.naoLidas - 1);
    } else if (acao === 'marcar_todas_lidas') {
        if (estado.naoLidas === 0) return;
        estado.notificacoes = estado.notificacoes.map(n => ({ ...n, lida: 1 }));
        estado.naoLidas = 0;
    } else if (acao === 'limpar_todas') {
        if (!estado.notificacoes.length) return;
        estado.notificacoes = [];
        estado.naoLidas = 0;
    }

    sincronizarUltimoId();
    renderNotificacoes();

    pararSomAlarme();

    try {
        const dados = await apiPost(API_NOTIFICACOES, { acao, id: id ? Number(id) : undefined });
        estado.notificacoes = dados.notificacoes ?? estado.notificacoes;
        estado.naoLidas = dados.naoLidas ?? estado.naoLidas;
        sincronizarUltimoId();
        renderNotificacoes();
        if (mensagem) toast(mensagem);
    } catch (erro) {
        restauraEstadoNotif(antes);
        toast(erro.message, 'erro');
    }
}

/* --- cliques nas listas (dashboard e página) --- */
document.querySelectorAll('[data-lista-notif]').forEach((lista) => {
    lista.addEventListener('click', (e) => {
        const li = e.target.closest('li[data-id]');
        if (!li) return;
        const id = Number(li.dataset.id);

        if (e.target.closest('.notif-apagar')) {
            acaoNotificacao('apagar', id, 'Notificação eliminada.');
            return;
        }
        if (e.target.closest('.notif-marcar')) {
            acaoNotificacao('marcar_lida', id, 'Notificação marcada como lida.');
            return;
        }
        if (!li.classList.contains('lida')) {
            acaoNotificacao('marcar_lida', id, 'Notificação marcada como lida.');
        }
    });
});

/* --- formulários de fallback da página (sem JavaScript) --- */
document.addEventListener('submit', (e) => {
    const form = e.target.closest?.('form.acao-notif');
    if (!form) return;
    e.preventDefault();

    const acao = form.dataset.acao;
    const id = form.dataset.id ? Number(form.dataset.id) : null;

    if (acao === 'limpar_todas') {
        if (!confirm('Eliminar todas as notificações? Esta ação não pode ser revertida.')) return;
        acaoNotificacao('limpar_todas', null, 'Notificações eliminadas.');
        return;
    }
    if (acao === 'marcar_todas') {
        acaoNotificacao('marcar_todas_lidas', null, 'Todas as notificações foram marcadas como lidas.');
        return;
    }
    if (acao === 'marcar_lida') {
        acaoNotificacao('marcar_lida', id, 'Notificação marcada como lida.');
        return;
    }
    if (acao === 'apagar') {
        acaoNotificacao('apagar', id, 'Notificação eliminada.');
    }
});

$('#formTodas')?.addEventListener('submit', (e) => {
    e.preventDefault();
    acaoNotificacao('marcar_todas_lidas', null, 'Todas as notificações foram marcadas como lidas.');
});

$('#btnMarcarLidas')?.addEventListener('click', (e) => {
    e.preventDefault();
    acaoNotificacao('marcar_todas_lidas', null, 'Todas as notificações foram marcadas como lidas.');
});

$('#btnNotif')?.addEventListener('click', alternarNotificacoes);

/* ===================== PESQUISA (Ctrl/Cmd + K) ===================== */

document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        const input = $('#pesquisaInput');
        if (!input) return;
        e.preventDefault();
        input.focus();
        input.select?.();
    }
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

atualizarBadgeTarefas();
aplicarPreferenciasUI();
carregarPreferencias();
cicloNotificacoes();
setInterval(() => cicloNotificacoes(), INTERVALO_BUSCA);
document.addEventListener('visibilitychange', () => { if (!document.hidden) cicloNotificacoes(); });
window.addEventListener('focus', () => cicloNotificacoes(false));

switch (document.body.dataset.pagina) {
    case 'dashboard':
        carregarTudo();
        break;
    case 'tarefas':
    case 'calendario':
        /* filtros/agrupamentos são renderizados no servidor: recarrega após alterações */
        window.onEstadoAtualizado = () => location.reload();
        break;
}
