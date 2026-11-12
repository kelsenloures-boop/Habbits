/* ============================================================
   Habbits — alarmes.js
   - mostra/esconde os campos de data e dias consoante a repetição
   - confirma a eliminação de um alarme
   ============================================================ */

const formAlarme = document.querySelector('#formAlarmeCampos');
const seletorRecorrencia = document.querySelector('#aRecorrencia');

function sincronizarBloqueAlarme() {
    const valor = seletorRecorrencia?.value ?? 'diario';
    document.querySelectorAll('[data-bloco="data"]').forEach((bloco) => {
        bloco.hidden = valor !== 'unico';
    });
    document.querySelectorAll('[data-bloco="dias"]').forEach((bloco) => {
        bloco.hidden = valor !== 'semanal' && valor !== 'dias';
    });
}

seletorRecorrencia?.addEventListener('change', sincronizarBloqueAlarme);
sincronizarBloqueAlarme();

/* Confirmação antes de eliminar */
document.querySelectorAll('form[data-confirmar]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (!window.confirm(form.dataset.confirmar)) e.preventDefault();
    });
});

/* Se a página abriu em modo de edição, leva o foco ao formulário. */
if (formAlarme && new URLSearchParams(location.search).has('editar')) {
    document.querySelector('#aTitulo')?.focus({ preventScroll: true });
}
