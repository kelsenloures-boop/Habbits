'use strict';

const { app, BrowserWindow, Tray, Menu, Notification, nativeImage, ipcMain, shell } = require('electron');
const { spawn, execFile } = require('child_process');
const path = require('path');
const net = require('net');
const http = require('http');

/* ------------------------------------------------------------------
 * Configuração
 * ---------------------------------------------------------------- */
const RAIZ = process.env.HABBITS_RAIZ
    ? path.resolve(process.env.HABBITS_RAIZ)
    : path.resolve(__dirname, '..');

// Permite um perfil isolado (testes ou vários utilizadores do Windows),
// sem partilhar cookies, cache nem o lock de instância única.
if (process.env.HABBITS_PERFIL) {
    app.setPath('userData', path.resolve(process.env.HABBITS_PERFIL));
}
const PHP = process.env.HABBITS_PHP || 'php';
const PORTA_INICIAL = parseInt(process.env.HABBITS_PORTA || '8030', 10);
const TENTATIVAS_PORTA = 6;
const INTERVALO_LEMBRETES = 15 * 1000;

let janela = null;
let bandeja = null;
let servidorPhp = null;
let urlBase = null;
let uidAtual = null;                 // utilizador com sessão na janela
let temporizador = null;
let aSair = false;
let prefs = { canal: 'interno', sons: 1, fechar_para_bandeja: 1 };

const cursores = new Map();          // uid → último id de notificação visto
const prefsPorUid = new Map();       // uid → preferências lidas do SQLite
const janelasAlarme = new Map();     // ocorrência → BrowserWindow
const alarmesFechados = new Set();   // ocorrências fechadas pelo utilizador
const alarmesAvisados = new Set();   // ocorrências já notificadas sem janela

/* ------------------------------------------------------------------
 * Servidor PHP local
 * ---------------------------------------------------------------- */
function portaDisponivel(porta) {
    return new Promise((resolve) => {
        const s = net.createServer();
        s.once('error', () => resolve(false));
        s.once('listening', () => s.close(() => resolve(true)));
        s.listen(porta, '127.0.0.1');
    });
}

async function escolherPorta() {
    for (let p = PORTA_INICIAL; p < PORTA_INICIAL + TENTATIVAS_PORTA; p++) {
        if (await portaDisponivel(p)) return p;
    }
    throw new Error('Sem porta livre para o servidor PHP local.');
}

function arrancarPhp(porta) {
    const processo = spawn(PHP, ['-S', `127.0.0.1:${porta}`, '-t', RAIZ], {
        cwd: RAIZ,
        windowsHide: true,
        env: { ...process.env },
        stdio: ['ignore', 'ignore', 'pipe'],
    });

    processo.stderr.on('data', (dados) => {
        const texto = String(dados).trim();
        // O servidor embutido escreve o arranque e os pedidos no stderr.
        if (texto && !/Development Server|Accepted|Closing/i.test(texto)) {
            console.error('[php]', texto);
        }
    });

    return processo;
}

function esperarServidor(url, limiteMs = 12000) {
    const inicio = Date.now();
    return new Promise((resolve, reject) => {
        const tentar = () => {
            const pedido = http.get(url + '/login.php', (Resposta) => {
                Resposta.resume();
                resolve(true);
            });
            pedido.on('error', () => {
                if (Date.now() - inicio > limiteMs) return reject(new Error('O servidor PHP não respondeu.'));
                setTimeout(tentar, 250);
            });
            pedido.setTimeout(1500, () => pedido.destroy());
        };
        tentar();
    });
}

function pararPhp() {
    if (!servidorPhp || servidorPhp.killed) return;
    try {
        if (process.platform === 'win32') {
            spawn('taskkill', ['/pid', String(servidorPhp.pid), '/t', '/f'], { windowsHide: true });
        } else {
            servidorPhp.kill('SIGTERM');
        }
    } catch { /* silencioso */ }
    servidorPhp = null;
}

/* ------------------------------------------------------------------
 * Ponte PHP (CLI) — mesmas funções, sem sessão HTTP
 * ---------------------------------------------------------------- */
function phpJson(argumentos) {
    return new Promise((resolve, reject) => {
        execFile(
            PHP,
            [path.join(RAIZ, 'bin', 'desktop.php'), ...argumentos],
            { cwd: RAIZ, windowsHide: true, timeout: 20000, maxBuffer: 4 * 1024 * 1024 },
            (erro, stdout) => {
                if (erro) return reject(erro);
                try {
                    resolve(JSON.parse(String(stdout).trim()));
                } catch (e) {
                    reject(e);
                }
            }
        );
    });
}

async function cicloLembretes() {
    let ids = [];
    try {
        const lista = await phpJson(['utilizadores']);
        ids = Array.isArray(lista.utilizadores) ? lista.utilizadores : [];
    } catch (erro) {
        console.error('[utilizadores]', erro.message || erro);
        return;
    }

    for (const id of ids) {
        try {
            const cursor = cursores.has(id) ? cursores.get(id) : null;
            const dados = await phpJson(['notificacoes', `--uid=${id}`, `--depois=${cursor ?? 0}`]);

            if (dados.preferencias) prefsPorUid.set(id, dados.preferencias);
            if (id === (uidAtual ?? ids[0]) && dados.preferencias) prefs = dados.preferencias;

            const novas = Array.isArray(dados.notificacoes) ? dados.notificacoes : [];
            const ultimo = Number(dados.ultimoId) || 0;

            // Primeira leitura de um utilizador: regista a posição, não repete avisos antigos.
            if (cursor === null) {
                cursores.set(id, ultimo);
                continue;
            }

            cursores.set(id, Math.max(cursor, ultimo));

            const prefsU = prefsPorUid.get(id) || prefs;

            // Alarmes tocam mesmo com o canal de notificações desligado.
            const ativos = Array.isArray(dados.alarmes) ? dados.alarmes : [];
            for (const ocorrencia of ativos) {
                if (alarmesFechados.has(ocorrencia.id)) continue;

                if (Number(prefsU.abrir_ao_alarme)) {
                    abrirJanelaAlarme(ocorrencia.id, id);
                } else if (!alarmesAvisados.has(ocorrencia.id)) {
                    // Preferência para não abrir a janela: avisa pelo Windows.
                    alarmesAvisados.add(ocorrencia.id);
                    mostrarNotificacao(
                        { id: ocorrencia.id, titulo: '⏰ ' + ocorrencia.titulo, texto: ocorrencia.mensagem || 'Alarme a tocar.' },
                        prefsU
                    );
                }
            }

            if (prefsU.canal === 'desligado') continue;

            for (const n of novas.slice(-3)) mostrarNotificacao(n, prefsU);
        } catch (erro) {
            console.error('[lembretes]', erro.message || erro);
        }
    }
}

function iniciarTemporizador() {
    clearInterval(temporizador);
    temporizador = setInterval(cicloLembretes, INTERVALO_LEMBRETES);
    cicloLembretes();
}

/* ------------------------------------------------------------------
 * Notificações nativas do Windows
 * ---------------------------------------------------------------- */
function mostrarNotificacao(n, preferencias = prefs) {
    if (!Notification.isSupported()) return;

    const notificacao = new Notification({
        title: 'Habbits — ' + (n.titulo || 'Lembrete'),
        body: n.texto || '',
        icon: path.join(__dirname, 'icon.png'),
        silent: !Number(preferencias.sons),
        timeoutType: 'default',
    });

    notificacao.on('click', () => mostrarJanela());
    notificacao.show();
    console.log('[notificacao]', n.id, n.titulo);
}

/* ------------------------------------------------------------------
 * Janela principal
 * ---------------------------------------------------------------- */
function criarJanela() {
    janela = new BrowserWindow({
        width: 1320,
        height: 840,
        minWidth: 1000,
        minHeight: 640,
        title: 'Habbits',
        icon: path.join(__dirname, 'icon.png'),
        backgroundColor: '#120f1c',
        show: false,
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    janela.loadURL(urlBase + '/login.php');
    janela.once('ready-to-show', () => janela.show());

    janela.webContents.setWindowOpenHandler(({ url }) => {
        shell.openExternal(url);
        return { action: 'deny' };
    });

    // Fechar a janela → bandeja, se a preferência estiver ligada.
    janela.on('close', (evento) => {
        if (!aSair && Number(prefs.fechar_para_bandeja)) {
            evento.preventDefault();
            janela.hide();
        }
    });

    janela.on('closed', () => { janela = null; });
}

function mostrarJanela() {
    if (!janela) return criarJanela();
    if (janela.isMinimized()) janela.restore();
    janela.show();
    janela.focus();
}

/* ------------------------------------------------------------------
 * Janela do alarme (ecrã inteiro, sempre no topo)
 * ---------------------------------------------------------------- */
function abrirJanelaAlarme(ocorrenciaId, uidDono) {
    const existente = janelasAlarme.get(ocorrenciaId);
    if (existente) {
        existente.show();
        existente.focus();
        return;
    }

    const alarme = new BrowserWindow({
        width: 900,
        height: 620,
        fullscreen: true,
        alwaysOnTop: true,
        title: 'Habbits — Alarme',
        icon: path.join(__dirname, 'icon.png'),
        backgroundColor: '#120f1c',
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    janelasAlarme.set(ocorrenciaId, alarme);
    alarme.loadURL(`${urlBase}/alarme.php?ocorrencia=${ocorrenciaId}`);
    alarme.setAlwaysOnTop(true, 'screen-saver');

    alarme.webContents.once('did-finish-load', () => {
        console.log('[alarme] janela pronta', ocorrenciaId, alarme.webContents.getURL());
    });

    alarme.on('closed', () => {
        janelasAlarme.delete(ocorrenciaId);
        alarmesFechados.add(ocorrenciaId);
        phpJson(['ocorrencia-fechar', `--uid=${uidDono}`, `--ocorrencia=${ocorrenciaId}`])
            .catch(() => { /* se já não existir, nada a fazer */ });
    });
}

/* ------------------------------------------------------------------
 * Bandeja
 * ---------------------------------------------------------------- */
function menuBandeja() {
    return Menu.buildFromTemplate([
        { label: 'Abrir Habbits', click: mostrarJanela },
        { type: 'separator' },
        {
            label: 'Iniciar com o Windows',
            type: 'checkbox',
            checked: app.getLoginItemSettings().openAtLogin,
            click: (item) => definirAutostart(item.checked),
        },
        { label: 'Abrir pasta de dados', click: () => shell.openPath(path.join(RAIZ, 'data')) },
        { type: 'separator' },
        { label: 'Sair completamente', click: () => { aSair = true; app.quit(); } },
    ]);
}

function criarBandeja() {
    const icone = nativeImage.createFromPath(path.join(__dirname, 'tray.png'));
    bandeja = new Tray(icone);
    bandeja.setToolTip('Habbits — assistente pessoal');
    bandeja.setContextMenu(menuBandeja());

    bandeja.on('click', () => {
        if (janela && janela.isVisible()) janela.hide();
        else mostrarJanela();
    });
}

/* ------------------------------------------------------------------
 * Arranque automático
 * ---------------------------------------------------------------- */
function definirAutostart(ativo) {
    const opcoes = { openAtLogin: Boolean(ativo) };

    // Em desenvolvimento o executável é o do Electron e precisa do caminho da app.
    if (process.defaultApp) {
        opcoes.path = process.execPath;
        opcoes.args = [path.resolve(__dirname)];
    }

    app.setLoginItemSettings(opcoes);
    const estado = app.getLoginItemSettings();
    if (bandeja) bandeja.setContextMenu(menuBandeja());
    return { openAtLogin: Boolean(estado.openAtLogin) };
}

/* ------------------------------------------------------------------
 * IPC (preload → processo principal)
 * ---------------------------------------------------------------- */
ipcMain.on('utilizador', (_evento, id) => {
    uidAtual = Number(id) || null;
    if (uidAtual && prefsPorUid.has(uidAtual)) prefs = prefsPorUid.get(uidAtual);
});

ipcMain.on('janela:mostrar', mostrarJanela);
ipcMain.on('janela:fechar', (evento) => {
    require('electron').BrowserWindow.fromWebContents(evento.sender)?.close();
});
ipcMain.on('dados:abrir', () => shell.openPath(path.join(RAIZ, 'data')));

ipcMain.handle('autostart:estado', () => ({ openAtLogin: Boolean(app.getLoginItemSettings().openAtLogin) }));
ipcMain.handle('autostart:definir', (_evento, ativo) => definirAutostart(ativo));

ipcMain.on('notificar', (_evento, dados = {}) => {
    // A página já mostra o toast; aqui só se o canal Windows estiver ativo.
    if (prefs.canal !== 'windows') return;
    mostrarNotificacao({ titulo: dados.titulo, texto: dados.texto });
});

/* ------------------------------------------------------------------
 * Ciclo de vida
 * ---------------------------------------------------------------- */
const instanciaUnica = app.requestSingleInstanceLock();

if (!instanciaUnica) {
    app.quit();
} else {
    app.on('second-instance', mostrarJanela);

    app.whenReady().then(async () => {
        app.setAppUserModelId('com.habbits.desktop');
        Menu.setApplicationMenu(null);

        try {
            const porta = await escolherPorta();
            urlBase = `http://127.0.0.1:${porta}`;
            servidorPhp = arrancarPhp(porta);
            await esperarServidor(urlBase);

            criarBandeja();
            criarJanela();
            iniciarTemporizador();
        } catch (erro) {
            const { dialog } = require('electron');
            dialog.showErrorBox('Habbits não arrancou', String(erro.message || erro));
            app.quit();
        }
    });

    app.on('window-all-closed', () => {
        // Com bandeja ativa, a app continua viva.
        if (!bandeja) app.quit();
    });

    app.on('before-quit', () => { aSair = true; });
    app.on('will-quit', () => {
        clearInterval(temporizador);
        pararPhp();
    });
}
