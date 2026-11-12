/* login.js — alternância entre as abas Entrar / Criar conta */

document.addEventListener('DOMContentLoaded', () => {
    const botaoEntrar = document.getElementById('tabEntrar');
    const botaoRegistar = document.getElementById('tabRegistar');
    const formEntrar = document.getElementById('formEntrar');
    const formRegistar = document.getElementById('formRegistar');

    if (!botaoEntrar || !botaoRegistar || !formEntrar || !formRegistar) return;

    function escolher(aba) {
        const entrar = aba === 'entrar';

        botaoEntrar.classList.toggle('ativo', entrar);
        botaoRegistar.classList.toggle('ativo', !entrar);
        botaoEntrar.setAttribute('aria-selected', entrar ? 'true' : 'false');
        botaoRegistar.setAttribute('aria-selected', entrar ? 'false' : 'true');

        formEntrar.classList.toggle('visivel', entrar);
        formRegistar.classList.toggle('visivel', !entrar);
    }

    botaoEntrar.addEventListener('click', () => escolher('entrar'));
    botaoRegistar.addEventListener('click', () => escolher('registar'));

    // Se o formulário de registar estava ativo, mantém-se
    if (formRegistar.classList.contains('visivel')) escolher('registar');
});
