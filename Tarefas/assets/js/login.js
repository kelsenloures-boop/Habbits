/* login.js — alternância entre as abas Entrar / Criar conta */

document.addEventListener('DOMContentLoaded', () => {
    const botaoEntrar = document.getElementById('tabEntrar');
    const botaoRegistrar = document.getElementById('tabRegistar');
    const form_Entrar = document.getElementById('formEntrar');
    const form_Regista = document.getElementById('formRegistar');

    function escolher(aba) {
        botaoEntrar.classList.toggle('ativo', aba === 'entrar');
        botaoRegistrar.classList.toggle('ativo', aba === 'registar');
        form_Entrar.classList.toggle('visivel', aba === 'entrar');
        form_Regista.classList.toggle('visivel', aba === 'registar');
    }

    botaoEntrar.addEventListener('click', () => escolher('entrar'));
    botaoRegistrar.addEventListener('click', () => escolher('registar'));

    // Se o formulário de registar estava ativo, mantém-se
    if (form_Regista.classList.contains('visivel')) escolher('registar');
});
