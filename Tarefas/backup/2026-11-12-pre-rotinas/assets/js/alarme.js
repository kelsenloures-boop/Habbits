/* ============================================================
   Habbits — alarme.js
   Ecrã do alarme: som em loop com limite, adiar com contagem
   decrescente e desligar. SQLite é a fonte de verdade.
   ============================================================ */

const corpoAlarme = document.body;
const ocorrenciaId = Number(corpoAlarme.dataset.ocorrencia || 0);
const deveTocar = corpoAlarme.dataset.som === '1';
const volumeAlarme = Math.max(0, Math.min(1, Number(corpoAlarme.dataset.volume || 80) / 100));
const limiteSeg = Math.max(10, Math.min(600, Number(corpoAlarme.dataset.limite || 120)));

let audioAlarme = null;
let limiteTimer = null;
let relogioTimer = null;
let contagemTimer = null;
let aTerminar = false;

/* --- relógio ao vivo --- */
function atualizarRelogio() {
    const el = document.querySelector('#relogioAlarme');
    if (!el) return;
    el.textContent = new Date().toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
}

if (document.querySelector('#relogioAlarme')) {
    relogioTimer = setInterval(atualizarRelogio, 1000);
}

/* --- som em loop, com limite de segurança --- */
async function tocarAlarme() {
    if (!deveTocar) return;

    try {
        if (!audioAlarme) {
            audioAlarme = new Audio('assets/audio/alarme.wav');
            audioAlarme.loop = true;
            audioAlarme.volume = volumeAlarme;
        }

        clearTimeout(limiteTimer);
        limiteTimer = setTimeout(pararSom, limiteSeg * 1000);

        await audioAlarme.play();
    } catch {
        const estado = document.querySelector('#estadoAlarme');
        if (estado) estado.textContent = 'O navegador bloqueou o som. Clica em "Desligar" ou interage com a página para o ouvir.';
    }
}

function pararSom() {
    clearTimeout(limiteTimer);
    limiteTimer = null;
    if (!audioAlarme) return;
    try {
        audioAlarme.pause();
        audioAlarme.currentTime = 0;
    } catch { /* silencioso */ }
}

function terminarJanela(esperaMs = 0) {
    aTerminar = true;
    clearInterval(relogioTimer);
    clearInterval(contagemTimer);
    pararSom();

    const fechar = () => {
        if (window.habbitsDesktop?.fecharJanela) {
            window.habbitsDesktop.fecharJanela();
            return;
        }

        try { window.close(); } catch { /* alguns navegadores bloqueiam */ }

        // Se a janela não puder ser fechada (separador normal), volta à lista.
        setTimeout(() => {
            if (!document.hidden) window.location.href = 'alarmes.php';
        }, 450);
    };

    if (esperaMs > 0) setTimeout(fechar, esperaMs);
    else fechar();
}

/* --- data local "YYYY-MM-DD HH:MM:SS" → Date --- */
function dataLocal(texto) {
    if (!texto) return null;
    const d = new Date(String(texto).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? null : d;
}

/* --- contagem decrescente até o alarme voltar a tocar --- */
function iniciarContagem(adiadoPara) {
    const alvo = dataLocal(adiadoPara);
    const estado = document.querySelector('#estadoAlarme');
    if (!alvo || !estado) return;

    const caixa = document.querySelector('[data-caixa-alarme]');
    caixa?.classList.add('adiado');

    const icone = caixa?.querySelector('.alarme-icone');
    if (icone) icone.textContent = '⏳';

    const atualizar = () => {
        const restante = alvo.getTime() - Date.now();

        if (restante <= 0) {
            clearInterval(contagemTimer);
            estado.textContent = 'A voltar a tocar…';
            window.location.reload();
            return;
        }

        const total = Math.floor(restante / 1000);
        const min = String(Math.floor(total / 60)).padStart(2, '0');
        const seg = String(total % 60).padStart(2, '0');
        const hora = alvo.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
        estado.textContent = `Adiado — volta às ${hora} (${min}:${seg}).`;
    };

    clearInterval(contagemTimer);
    atualizar();
    contagemTimer = setInterval(atualizar, 1000);
}

/* --- ações --- */
async function acaoAlarme(acao, minutos = null) {
    if (aTerminar) return null;

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

document.querySelector('#btnAdiar')?.addEventListener('click', async () => {
    const estado = document.querySelector('#estadoAlarme');
    const minutos = Number(corpoAlarme.dataset.minutos || 10);
    const botao = document.querySelector('#btnAdiar');

    if (botao) botao.disabled = true;

    try {
        const dados = await acaoAlarme('adiar', minutos);
        pararSom();

        // Mantém o ecrã do alarme aberto com contagem decrescente.
        if (botao) botao.hidden = true;
        iniciarContagem(dados.adiado_para);
    } catch (erro) {
        if (estado) estado.textContent = erro.message;
        if (botao) botao.disabled = false;
    }
});

document.querySelector('#btnDesligar')?.addEventListener('click', async () => {
    const estado = document.querySelector('#estadoAlarme');

    // Para já pára o som; se a API falhar, o alarme continua na base de dados.
    pararSom();

    try {
        await acaoAlarme('desligar');
        if (estado) estado.textContent = 'Alarme desligado.';
        terminarJanela(600);
    } catch (erro) {
        if (estado) estado.textContent = erro.message;
    }
});

/* Se a página abriu num adiamento, retoma a contagem. */
const adiadoPara = corpoAlarme.dataset.adiadoPara || '';
if (adiadoPara) iniciarContagem(adiadoPara);

/* Arrancar o som assim que a página abre (apenas quando está a tocar). */
tocarAlarme();
