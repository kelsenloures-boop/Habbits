# Habbits — app desktop (Electron)

Este diretório contém apenas o **host desktop**. A aplicação continua a ser PHP + SQLite;
não há reescrita nem segunda base de dados.

## Arrancar

```powershell
cd C:\Projetos\Tarefas\desktop
npm start
```

O Electron arranca um servidor PHP local (`127.0.0.1:8030`, com fallback de portas),
abre a janela e mantém um ícone na bandeja. Requer `php` no PATH.

### Variáveis úteis

| Variável | Efeito |
|---|---|
| `HABBITS_RAIZ` | pasta da app PHP a servir (por omissão, a pasta pai) |
| `HABBITS_PORTA` | primeira porta a tentar (por omissão `8030`) |
| `HABBITS_PERFIL` | pasta de perfil Electron isolada (cookies, cache, lock) |
| `HABBITS_PHP` | executável PHP (por omissão `php`) |

## O que o host faz

- Arranca/termina o servidor PHP e abre a janela principal.
- Bandeja: abrir, iniciar com o Windows, abrir pasta de dados, sair.
- Fechar a janela pode mantê-la na bandeja (preferência).
- A cada 15 s executa `bin/desktop.php` (sem sessão HTTP) para:
  - processar lembretes de tarefas;
  - processar alarmes e ocorrências;
  - mostrar notificações nativas do Windows;
  - abrir a janela de alarme em ecrã inteiro quando uma ocorrência está a tocar.
- A janela de alarme partilha a sessão/cookies da janela principal.

## Limitações reais (não simuladas)

1. **App totalmente encerrada:** nada toca. Não existe serviço Windows nem tarefa agendada.
   Os alarmes só disparam enquanto o processo Electron estiver vivo (janela ou bandeja).
2. **Sessão:** a janela do alarme precisa de sessão iniciada. Se a sessão expirar,
   o ecrã do alarme redireciona para o login. O cookie dura 30 dias.
3. **Arranque automático:** usa `setLoginItemSettings`; em desenvolvimento aponta para o
   `electron.exe`. Só o instalador empacotado dá um arranque "de produção".
4. **Empacotamento (electron-builder)** ainda não foi validado. Para distribuir é preciso
   decidir como levar o PHP (binário embutido) e configurar `extraResources`.
5. **Som:** depende das políticas de autoplay do motor. Na app desktop o som é iniciado
   pela própria janela do alarme; no navegador pode exigir uma interação.
6. **Vários alarmes simultâneos:** cada ocorrência abre a sua própria janela de ecrã inteiro.

## Dados e segurança

- Base de dados: `..\data\habbits.sqlite` (SQLite é a fonte de verdade).
- Nenhuma notificação ou alarme é guardado em `localStorage`.
- O renderer não tem acesso a Node (`contextIsolation: true`, `nodeIntegration: false`);
  a ponte exposta está em `preload.js`.
