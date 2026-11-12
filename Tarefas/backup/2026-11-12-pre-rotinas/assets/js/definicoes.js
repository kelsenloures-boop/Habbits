/* ============================================================
   Habbits — definicoes.js
   Cartão "Notificações": guardar preferências (SQLite via API),
   estado real do canal e teste do som de alarme.
   ============================================================ */

const formPrefs = document.querySelector('#formNotificacoes');

function modoDesktop() {
    // Só a ponte desktop (preload do Electron) define este objeto.
    // Não usar o userAgent: outros hosts Electron não são a app.
    return window.habbitsDesktop?.disponivel === true;
}

/* --- arranque com o Windows: estado real vem do sistema, não do HTML --- */
async function sincronizarAutostart() {
    const caixa = document.querySelector('#iniciar_com_windows');
    const estadoEl = document.querySelector('#estadoDesktop');
    if (!caixa) return;

    if (!modoDesktop()) {
        caixa.disabled = true;
        caixa.closest('.pref-linha')?.classList.add('desativado');
        if (estadoEl) estadoEl.textContent = 'Modo navegador: o arranque com o Windows só está disponível na app desktop.';
        return;
    }

    try {
        const info = await window.habbitsDesktop.estadoAutostart();
        caixa.checked = Boolean(info?.openAtLogin);
        if (estadoEl) estadoEl.textContent = 'App desktop ligada. O arranque automático é controlado pelo Windows.';
    } catch {
        if (estadoEl) estadoEl.textContent = 'Não foi possível ler o estado do arranque automático.';
    }
}

function textoEstadoCanal(canal) {
    if (canal === 'desligado') return 'Notificações desativadas. Os alarmes continuam a funcionar.';
    if (canal === 'windows') {
        return modoDesktop()
            ? 'Notificações nativas do Windows ativas.'
            : 'Indisponível neste modo: as notificações Windows só funcionam na app desktop.';
    }
    return 'Os avisos aparecem dentro da app.';
}

function atualizarEstadoCanal() {
    const canal = document.querySelector('#canal')?.value ?? 'interno';
    const estadoEl = document.querySelector('#estadoCanal');
    if (!estadoEl) return;

    estadoEl.textContent = textoEstadoCanal(canal);
    estadoEl.classList.toggle('aviso', canal === 'windows' && !modoDesktop());
    estadoEl.classList.toggle('desligado', canal === 'desligado');
}

function atualizarVolume() {
    const volume = document.querySelector('#volume');
    const saida = document.querySelector('#volumeSaida');
    if (volume && saida) saida.textContent = volume.value + '%';
}

document.querySelector('#canal')?.addEventListener('change', atualizarEstadoCanal);
document.querySelector('#volume')?.addEventListener('input', atualizarVolume);
atualizarVolume();
atualizarEstadoCanal();
sincronizarAutostart();

/* --- Guardar preferências --- */
formPrefs?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const dados = {
        canal: document.querySelector('#canal')?.value ?? 'interno',
        sons: document.querySelector('#sons')?.checked ? 1 : 0,
        som_alarme: document.querySelector('#som_alarme')?.checked ? 1 : 0,
        volume: Number(document.querySelector('#volume')?.value ?? 80),
        mostrar_segundo_plano: document.querySelector('#mostrar_segundo_plano')?.checked ? 1 : 0,
        abrir_ao_alarme: document.querySelector('#abrir_ao_alarme')?.checked ? 1 : 0,
        fechar_para_bandeja: document.querySelector('#fechar_para_bandeja')?.checked ? 1 : 0,
        snooze_min: Number(document.querySelector('#snooze_min')?.value ?? 10),
        limite_som_seg: Number(document.querySelector('#limite_som_seg')?.value ?? 120),
        iniciar_com_windows: document.querySelector('#iniciar_com_windows')?.checked ? 1 : 0,
    };

    const botao = formPrefs.querySelector('button[type="submit"]');
    if (botao) botao.disabled = true;

    try {
        const resposta = await apiPost(API_PREFERENCIAS, dados);
        if (resposta?.preferencias) {
            estado.preferencias = resposta.preferencias;
            aplicarPreferenciasUI();
        }

        if (modoDesktop() && window.habbitsDesktop?.definirAutostart) {
            const info = await window.habbitsDesktop.definirAutostart(Boolean(dados.iniciar_com_windows));
            const caixa = document.querySelector('#iniciar_com_windows');
            if (caixa && info) caixa.checked = Boolean(info.openAtLogin);
        }

        atualizarEstadoCanal();
        toast('Preferências de notificações guardadas.');
    } catch (erro) {
        toast(erro.message, 'erro');
    } finally {
        if (botao) botao.disabled = false;
    }
});

/* --- Testar som (uma reprodução, sem loop) --- */
document.querySelector('#btnTestarSom')?.addEventListener('click', async () => {
    try {
        const audio = new Audio('assets/audio/alarme.wav');
        audio.volume = Math.max(0, Math.min(1, Number(document.querySelector('#volume')?.value ?? 80) / 100));
        await audio.play();
        toast('A tocar o som de alarme.');
    } catch {
        toast('O navegador bloqueou a reprodução. Interage com a página e tenta novamente.', 'aviso');
    }
});
