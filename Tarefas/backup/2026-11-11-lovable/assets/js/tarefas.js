/* ============================================================
   Habbits — tarefas.js (página Minhas Tarefas)
   Filtros são server-side (SQL). A página reage a:
   - chips de vista (todas/pendentes/concluídas/atrasadas)
   - selects de estado/prioridade/categoria (auto-submit)
   - busca no topo (enche o campo busca e aplica)
   - linhas de tarefa renderizadas pelo servidor (edit/acões)
   ============================================================ */

/* --- reconstruir os filtros atuais (guest à partir da URL) --- */
const params = new URLSearchParams(location.search);
const filtrosAtuais = (extra = {}) => {
    const f = {
        vista: params.get('vista') ?? 'todas',
        estado: params.get('estado') ?? '',
        prioridade: params.get('prioridade') ?? '',
        cat: params.get('cat') ?? '',
        busca: params.get('busca') ?? '',
        ...extra,
    };
    Object.keys(f).forEach(k => { if (f[k] === '' || f[k] == null) delete f[k]; });
    if (f.vista === 'todas') delete f.vista;
    return f;
};

/* --- submits automáticos dos selects --- */
document.querySelectorAll('#formFiltros select').forEach(sel =>
    sel.addEventListener('change', () => {
        document.getElementById('fVista').value = filtrosAtuais().vista ?? 'todas';
        document.getElementById('formFiltros').submit();
    }));

/* --- chips de vista rápida --- */
document.querySelectorAll('.filtro-chips .chip[data-vista]').forEach(chip =>
    chip.addEventListener('click', () => {
        const url = new URL('tarefas.php', location.href);
        const f = filtrosAtuais({ vista: chip.dataset.vista });
        Object.entries(f).forEach(([k, v]) => url.searchParams.set(k, v));
        location.href = url;
    }));

/* --- busca (debounce → GET server-side) --- */
let timerBusca = null;
const inputBusca = document.getElementById('pesquisaInput');
if (inputBusca) {
    inputBusca.value = params.get('busca') ?? '';
    inputBusca.addEventListener('input', () => {
        clearTimeout(timerBusca);
        timerBusca = setTimeout(() => {
            const url = new URL('tarefas.php', location.href);
            const f = filtrosAtuais({ busca: inputBusca.value.trim() });
            Object.entries(f).forEach(([k, v]) => url.searchParams.set(k, v));
            location.href = url;
        }, 450);
    });
}

/* --- ligar as linhas (servidas em HTML pelo PHP) --- */
document.querySelectorAll('.lista-tarefas').forEach(l => ligarEventosTarefa(l));

/* Limite apenas na página: refetch tem reload via hook do core (onEstadoAtualizado) */
