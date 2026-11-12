'use strict';

/* Teste da ponte preload ↔ processo principal, sem abrir a app real.
   Uso: node_modules/electron/dist/electron.exe tools/teste-ponte.js */

const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');

let uidRecebido = null;
let notificacaoRecebida = null;

ipcMain.on('utilizador', (_e, id) => { uidRecebido = id; });
ipcMain.on('notificar', (_e, dados) => { notificacaoRecebida = dados; });
ipcMain.handle('autostart:estado', () => ({ openAtLogin: false }));
ipcMain.handle('autostart:definir', (_e, ativo) => ({ openAtLogin: Boolean(ativo) }));

app.whenReady().then(async () => {
    const janela = new BrowserWindow({
        show: false,
        webPreferences: {
            preload: path.join(__dirname, '..', 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    await janela.loadURL('data:text/html,<html><body data-uid="7">teste</body></html>');

    const resultado = await janela.webContents.executeJavaScript(`(async () => {
        const ponte = window.habbitsDesktop;
        const estado = ponte ? await ponte.estadoAutostart() : null;
        if (ponte) ponte.notificar('Teste', 'Mensagem');
        return {
            existe: Boolean(ponte),
            disponivel: ponte?.disponivel === true,
            notificar: typeof ponte?.notificar,
            definirAutostart: typeof ponte?.definirAutostart,
            mostrarJanela: typeof ponte?.mostrarJanela,
            estado,
            nodeNoRenderer: typeof require,
        };
    })()`);

    // dá tempo ao IPC de chegar
    setTimeout(() => {
        console.log(JSON.stringify({
            resultado,
            uidRecebido,
            notificacaoRecebida,
        }, null, 2));
        app.exit(0);
    }, 300);
});
