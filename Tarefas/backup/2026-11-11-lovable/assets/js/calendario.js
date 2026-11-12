/* ============================================================
   Habbits — calendario.js
   - botão "+ Neste dia" abre o modal com a data do dia selecionado
   - duplo-clique num dia do calendário também abre o modal
   ============================================================ */

/* --- "+ Neste dia" --- */
const btnDia = document.querySelector('.add-tarefa-dia');
if (btnDia) {
    btnDia.addEventListener('click', () => modalAbrir(null, btnDia.dataset.dia));
}

/* --- duplo-clique na grelha → criar tarefa nesse dia --- */
document.querySelectorAll('.cal-dia .cal-link').forEach(link =>
    link.addEventListener('dblclick', () => {
        const dia = new URLSearchParams(link.search).get('dia');
        if (dia) modalAbrir(null, dia);
    }));
