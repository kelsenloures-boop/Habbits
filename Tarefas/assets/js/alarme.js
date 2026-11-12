/* ============================================================
   Habbits — alarme.js
   Relógio ao vivo, som em loop com limite, adiamento animado
   com contagem baseada em tempo real e desligar.

   O SQLite continua a ser a fonte de verdade: o adiamento só
   acontece depois da resposta da API e a reactivação é pedida
   ao servidor quando a contagem chega a zero.
   ============================================================ */

'use strict';

const corpoAlarme   = document.body;
const ocorrenciaId  = Number(corpoAlarme.dataset.ocorrencia || 0);
const somDisponivel = corpoAlarme.dataset.som === '1';
const volumeAlarme  = Math.max(0, Math.min(1, Number(corpoAlarme.dataset.volume || 80) / 100));
const limiteSeg     = Math.max(10, Math.min(600, Number(corpoAlarme.dataset.limite || 120)));
const minutosAdiar  = Math.max(1, Math.min(60, Number(corpoAlarme.dataset.minutos || 10)));
const maxAdiamentos = 5;

const elRelogio  = document.querySelector('#relogioAlarme');
const elContagem = document.querySelector('#contagemAlarme');
const elTempo    = document.querySelector('#contagemTempo');
const elLegenda  = document.querySelector('#contagemLegenda');
const elEstado   = document.querySelector('#estadoAlarme');
const btnAdiar   = document.querySelector('#btnAdiar');
const btnDesligar = document.querySelector('#btnDesligar');

let audio = null;
let limiteTimer = null;
let relogioTimer = null;
let contagemTimer = null;
let alvoAdiamento = null;
let adiamentos = Number(corpoAlarme.dataset.adiados || 0);
let adiado = corpoAlarme.dataset.estado === 'adiado';
let aProcessar = false;
let aTerminar = false;

/* ------------------------------------------------------------------
 * Relógio ao vivo (hora local do sistema = Africa/Maputo)
 * ---------------------------------------------------------------- */
function atualizarRelogio() {
    if (!elRelogio) return;
    elRelogio.textContent = new Date().toLocaleTimeString('pt-PT', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function iniciarRelogio() {
    atualizarRelogio();
    clearInterval(relogioTimer);
    relogioTimer = setInterval(atualizarRelogio, 1000);
}

/* ------------------------------------------------------------------
 * Som
 * ---------------------------------------------------------------- */
async function tocarSom() {
    if (!somDisponivel || adiado || aTerminar) return;

    try {
        if (!audio) {
            audio = new Audio('assets/audio/alarme.wav');
            audio.loop = true;
            audio.volume = volumeAlarme;
        }

        clearTimeout(limiteTimer);
        limiteTimer = setTimeout(pararSom, limiteSeg * 1000);

        await audio.play();
    } catch {
        if (elEstado) {
            elEstado.textContent = 'O som foi bloqueado pelo sistema. Interage com a janela para o ouvir.';
        }
    }
}

function pararSom() {
    clearTimeout(limiteTimer);
    limiteTimer = null;
    if (!audio) return;
    try {
        audio.pause();
        audio.currentTime = 0;
    } catch { /* silencioso */ }
}

/* ------------------------------------------------------------------
 * Contagem decrescente
 * ---------------------------------------------------------------- */
function dataLocal(texto) {
    if (!texto) return null;
    const d = new Date(String(texto).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? null : d;
}

function doisDigitos(n) {
    return String(n).padStart(2, '0');
}

function pintarContagem() {
    if (!alvoAdiamento) return;

    const restante = alvoAdiamento.getTime() - Date.now();

    if (restante <= 0) {
        finalizarAdiamento();
        return;
    }

    // Sempre a partir do relógio real — o intervalo só actualiza o ecrã.
    const total = Math.ceil(restante / 1000);
    if (elTempo) {
        elTempo.textContent = doisDigitos(Math.floor(total / 60)) + ':' + doisDigitos(total % 60);
    }
}

function iniciarAdiamento(para) {
    const alvo = dataLocal(para);
    if (!alvo) return;

    alvoAdiamento = alvo;
    adiado = true;

    if (elContagem) {
        elContagem.setAttribute('aria-hidden', 'false');
        if (elLegenda) {
            elLegenda.textContent = 'Adiado · volta às ' + alvo.toLocaleTimeString('pt-PT', {
                hour: '2-digit',
                minute: '2-digit',
            });
        }
    }

    corpoAlarme.classList.add('adiado');
    atualizarBotoes();

    pintarContagem();
    clearInterval(contagemTimer);
    contagemTimer = setInterval(pintarContagem, 250);
}

async function finalizarAdiamento() {
    if (aProcessar) return;
    aProcessar = true;

    clearInterval(contagemTimer);
    contagemTimer = null;
    alvoAdiamento = null;
    adiado = false;

    // 1. Animação de saída: a contagem desaparece e o relógio regressa.
    corpoAlarme.classList.remove('adiado');
    if (elContagem) elContagem.setAttribute('aria-hidden', 'true');
    if (elEstado) elEstado.textContent = '';

    // 2. O servidor reactiva a ocorrência (adiado → a_tocar) no GET.
    const reactivado = await confirmarReativacao();
    aProcessar = false;

    if (reactivado) {
        atualizarBotoes();
        tocarSom();
    }
}

async function confirmarReativacao() {
    // Pequena margem para o relógio do servidor atingir o mesmo segundo.
    for (let tentativa = 0; tentativa < 4; tentativa++) {
        try {
            const resposta = await fetch('api/alarmes.php', { cache: 'no-store' });
            if (!resposta.ok) throw new Error('http');

            const dados = await resposta.json();
            const ativa = (dados.ativas || []).some((o) => Number(o.id) === ocorrenciaId);

            if (ativa) {
                corpoAlarme.dataset.estado = 'a_tocar';
                return true;
            }
        } catch { /* tenta novamente */ }

        await new Promise((r) => setTimeout(r, 900));
    }

    mostrarInativo('O alarme já não está ativo.');
    return false;
}

function mostrarInativo(mensagem) {
    pararSom();
    if (elEstado) elEstado.textContent = mensagem;
    if (btnAdiar) btnAdiar.disabled = true;
    if (btnDesligar) btnDesligar.hidden = true;
}

/* ------------------------------------------------------------------
 * Botões
 * ---------------------------------------------------------------- */
function atualizarBotoes() {
    if (btnAdiar) {
        btnAdiar.disabled = adiado || adiamentos >= maxAdiamentos;
        btnAdiar.setAttribute('aria-hidden', adiado ? 'true' : 'false');
    }
    if (btnDesligar) btnDesligar.disabled = aTerminar;
}

async function acaoAlarme(acao, minutos = null) {
    const resposta = await fetch('api/alarmes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            acao,
            ocorrencia_id: ocorrenciaId,
            ...(minutos ? { minutos } : {}),
        }),
    });

    const dados = await resposta.json().catch(() => ({}));
    if (!resposta.ok || dados.ok === false) {
        throw new Error(dados.erro || 'Não foi possível concluir a ação.');
    }
    return dados;
}

btnAdiar?.addEventListener('click', async () => {
    if (aProcessar || adiado || !ocorrenciaId) return;

    aProcessar = true;
    btnAdiar.disabled = true;

    try {
        const dados = await acaoAlarme('adiar', minutosAdiar);

        pararSom();
        adiamentos += 1;
        iniciarAdiamento(dados.adiado_para);

        if (elEstado) {
            elEstado.textContent = 'Adiado ' + minutosAdiar + ' min. O alarme volta a tocar automaticamente.';
        }
    } catch (erro) {
        if (elEstado) elEstado.textContent = erro.message;
        btnAdiar.disabled = false;
    } finally {
        aProcessar = false;
    }
});

btnDesligar?.addEventListener('click', async () => {
    if (aTerminar) return;

    aTerminar = true;
    pararSom();
    btnDesligar.disabled = true;

    try {
        await acaoAlarme('desligar');

        if (elEstado) elEstado.textContent = 'Alarme desligado.';
        corpoAlarme.classList.add('a-sair');

        setTimeout(fecharJanela, 260);
    } catch (erro) {
        if (elEstado) elEstado.textContent = erro.message;
        aTerminar = false;
        btnDesligar.disabled = false;
    }
});

function fecharJanela() {
    if (window.habbitsDesktop?.fecharJanela) {
        window.habbitsDesktop.fecharJanela();
        return;
    }

    try { window.close(); } catch { /* alguns navegadores bloqueiam */ }

    // Se a janela não puder ser fechada (separador normal), volta à lista.
    setTimeout(() => {
        if (!document.hidden) window.location.href = 'alarmes.php';
    }, 450);
}

/* ------------------------------------------------------------------
 * Arranque
 * ---------------------------------------------------------------- */
iniciarRelogio();
atualizarBotoes();

const adiadoPara = corpoAlarme.dataset.adiadoPara || '';
if (adiadoPara) {
    // Janela reaberta durante um adiamento: retoma a contagem.
    iniciarAdiamento(adiadoPara);
} else {
    tocarSom();
}
