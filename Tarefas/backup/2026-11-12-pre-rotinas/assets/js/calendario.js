/* ============================================================
   Habbits — calendario.js
   - botão "+ Neste dia" abre o modal com a data do dia selecionado
   - duplo-clique num dia abre o modal desse dia
   - clique simples navega (com um pequeno atraso para distinguir)
   ============================================================ */

/* --- "+ Neste dia" --- */
const btnDia = document.querySelector('.add-tarefa-dia');
if (btnDia) {
    btnDia.addEventListener('click', () => modalAbrir(null, btnDia.dataset.dia));
}

/* --- grelha: distinguir clique simples de duplo clique --- */
const temporizadoresDia = new WeakMap();
const ATRASO_CLIQUE = 260;

document.querySelectorAll('.cal-dia .cal-link').forEach((link) => {
    link.addEventListener('click', (e) => {
        // Teclado, teclas modificadoras e outros botões mantêm o comportamento normal.
        if (e.detail === 0 || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        e.preventDefault();
        clearTimeout(temporizadoresDia.get(link));

        // Segundo clique de um duplo clique: cancela a navegação pendente.
        if (e.detail >= 2) return;

        temporizadoresDia.set(link, setTimeout(() => {
            window.location.assign(link.href);
        }, ATRASO_CLIQUE));
    });

    link.addEventListener('dblclick', (e) => {
        e.preventDefault();
        clearTimeout(temporizadoresDia.get(link));

        const dia = link.dataset.dia || new URLSearchParams(link.search).get('dia');
        if (dia) modalAbrir(null, dia);
    });
});
