'use strict';

const { contextBridge, ipcRenderer } = require('electron');

/* API mínima exposta à página. Nada de Node no renderer. */
contextBridge.exposeInMainWorld('habbitsDesktop', {
    disponivel: true,
    notificar: (titulo, texto) => ipcRenderer.send('notificar', { titulo, texto }),
    estadoAutostart: () => ipcRenderer.invoke('autostart:estado'),
    definirAutostart: (ativo) => ipcRenderer.invoke('autostart:definir', Boolean(ativo)),
    mostrarJanela: () => ipcRenderer.send('janela:mostrar'),
    fecharJanela: () => ipcRenderer.send('janela:fechar'),
    abrirPastaDados: () => ipcRenderer.send('dados:abrir'),
});

/* Comunica ao processo principal qual o utilizador autenticado nesta página. */
window.addEventListener('DOMContentLoaded', () => {
    const id = Number(document.body?.dataset?.uid || 0);
    if (id > 0) ipcRenderer.send('utilizador', id);
});
