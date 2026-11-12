/* ============================================================
   Habbits — tema.js
   Três temas (Claro, Escuro, Púrpura) com persistência apenas
   da preferência visual em localStorage.
   ============================================================ */

(function () {
    'use strict';

    const TEMAS = [
        { id: 'light',  label: 'Claro',   icone: '☀' },
        { id: 'dark',   label: 'Escuro',  icone: '◐' },
        { id: 'violet', label: 'Púrpura', icone: '✦' },
    ];

    const CHAVE = 'habbits-tema';
    const CHAVE_ANTIGA = 'habbits_tema';

    const temaValido = (t) => TEMAS.some((tema) => tema.id === t);
    const temaAtual = () => {
        const atual = document.documentElement.getAttribute('data-theme');
        return temaValido(atual) ? atual : 'violet';
    };

    function guardar(tema) {
        try {
            localStorage.setItem(CHAVE, tema);
            localStorage.setItem(CHAVE_ANTIGA, tema);
        } catch (_) { /* modo privado / storage indisponível */ }
    }

    function atualizarBotoes(tema) {
        const definicao = TEMAS.find((t) => t.id === tema) || TEMAS[2];

        document.querySelectorAll('#btnTema').forEach((botao) => {
            botao.dataset.tema = tema;
            botao.title = `Tema atual: ${definicao.label}. Clique para mudar.`;
            botao.setAttribute('aria-label', `Mudar tema. Tema atual: ${definicao.label}`);

            const icone = botao.querySelector('.tema-icone');
            const rotulo = botao.querySelector('#temaRotulo');

            if (icone) icone.textContent = definicao.icone;
            if (rotulo) rotulo.textContent = definicao.label;
        });

        document.querySelectorAll('[data-escolher-tema]').forEach((opcao) => {
            const ativo = opcao.dataset.escolherTema === tema;
            opcao.classList.toggle('ativo', ativo);
            opcao.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        });
    }

    function aplicar(tema, persistir = true) {
        if (!temaValido(tema)) tema = 'violet';

        document.documentElement.setAttribute('data-theme', tema);
        if (persistir) guardar(tema);
        atualizarBotoes(tema);
    }

    function proximo(tema) {
        const indice = TEMAS.findIndex((t) => t.id === tema);
        return TEMAS[(indice + 1) % TEMAS.length].id;
    }

    function iniciar() {
        /* o tema já foi aplicado pelo script inline do <head> (evita flash) */
        aplicar(temaAtual(), false);

        document.querySelectorAll('#btnTema').forEach((botao) => {
            botao.addEventListener('click', () => aplicar(proximo(temaAtual())));
        });

        document.querySelectorAll('[data-escolher-tema]').forEach((opcao) => {
            opcao.addEventListener('click', () => aplicar(opcao.dataset.escolherTema));
        });

        /* sincroniza entre separadores abertos */
        window.addEventListener('storage', (evento) => {
            if (evento.key !== CHAVE && evento.key !== CHAVE_ANTIGA) return;
            const novoTema = evento.newValue;
            if (temaValido(novoTema)) aplicar(novoTema, false);
        });
    }

    window.definirTema = (tema) => aplicar(tema);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
