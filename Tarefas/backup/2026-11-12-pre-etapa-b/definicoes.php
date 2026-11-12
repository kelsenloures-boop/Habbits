<?php
require_once __DIR__ . '/inc/partials.php';

exigir_login();
$uid = uid_atual();
$util = utilizador_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'perfil') {
        $novoNome = trim($_POST['nome'] ?? '');
        if ($novoNome === '') {
            flash('erro', 'O nome não pode ficar vazio.');
        } else {
            utilizador_definir_nome($uid, $novoNome);
            flash('ok', 'Perfil atualizado. Olá, ' . $novoNome . '!');
        }
    }

    if ($acao === 'passe') {
        $atual = $_POST['atual'] ?? '';
        $nova = $_POST['nova'] ?? '';
        $conf = $_POST['confirma'] ?? '';

        $u = db()->prepare("SELECT palavra_passe FROM utilizadores WHERE id = :id");
        $u->execute([':id' => $uid]);
        $hash = $u->fetchColumn();

        if (!password_verify($atual, $hash)) {
            flash('erro', 'A palavra-passe atual está incorreta.');
        } elseif (strlen($nova) < 6) {
            flash('erro', 'A nova palavra-passe precisa de pelo menos 6 caracteres.');
        } elseif ($nova !== $conf) {
            flash('erro', 'A confirmação não coincide com a nova palavra-passe.');
        } else {
            utilizador_definir_passe($uid, password_hash($nova, PASSWORD_DEFAULT));
            flash('ok', 'Palavra-passe alterada com sucesso. 🔒');
        }
    }

    if ($acao === 'limpar_concluidas') {
        $n = tarefas_limpar_concluidas($uid);
        flash('ok', $n . ' tarefa' . ($n === 1 ? '' : 's') . ' concluída' . ($n === 1 ? '' : 's') . ' eliminada' . ($n === 1 ? '.' : 's.'));
    }

    if ($acao === 'limpar_notifs') {
        $n = notificacoes_limpar($uid);
        flash('ok', $n . ' notificação' . ($n === 1 ? '' : 'es') . ' eliminada' . ($n === 1 ? '.' : 's.'));
    }

    if ($acao === 'preferencias') {
        preferencias_guardar($uid, [
            'canal'                 => $_POST['canal'] ?? 'interno',
            'sons'                  => isset($_POST['sons']) ? 1 : 0,
            'som_alarme'            => isset($_POST['som_alarme']) ? 1 : 0,
            'volume'                => $_POST['volume'] ?? 80,
            'mostrar_segundo_plano' => isset($_POST['mostrar_segundo_plano']) ? 1 : 0,
            'abrir_ao_alarme'       => isset($_POST['abrir_ao_alarme']) ? 1 : 0,
            'fechar_para_bandeja'   => isset($_POST['fechar_para_bandeja']) ? 1 : 0,
            'snooze_min'            => $_POST['snooze_min'] ?? 10,
            'limite_som_seg'        => $_POST['limite_som_seg'] ?? 120,
            'iniciar_com_windows'   => isset($_POST['iniciar_com_windows']) ? 1 : 0,
        ]);
        flash('ok', 'Preferências de notificações guardadas.');
    }

    header('Location: definicoes.php');
    exit;
}

$flashMsg = flash_pegar();
$stats = estatisticas(repositorio_carregar($uid));
$prefs = preferencias_obter($uid);

html_inicio('Definições', 'definicoes');
html_topo(
    'Definições',
    'Perfil, segurança, aparência e gestão de dados <span class="date-separator">/</span> tudo num só lugar',
    false
);
?>

<div class="page-block">
    <?php if ($flashMsg): ?>
    <div class="alerta <?= $flashMsg['tipo'] ?>"><?= htmlspecialchars($flashMsg['msg']) ?></div>
    <?php endif; ?>

    <div class="defs-grid">
        <!-- Perfil -->
        <section class="settings-card">
            <div class="eyebrow"><?= icone('sparkles') ?> A tua identidade</div>
            <h2>Perfil</h2>
            <p>Como queres ser tratado dentro do Habbits.</p>
            <form method="post" class="defs-form">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="perfil">
                <div class="campo">
                    <label for="nome">Nome</label>
                    <input type="text" id="nome" name="nome" required maxlength="60" value="<?= htmlspecialchars($util['nome']) ?>">
                </div>
                <div class="campo">
                    <label for="email-perfil">Email (imutável)</label>
                    <input type="email" id="email-perfil" value="<?= htmlspecialchars($util['email']) ?>" disabled>
                </div>
                <button type="submit" class="btn-primario">Guardar alterações</button>
            </form>
        </section>

        <!-- Aparência -->
        <section class="settings-card">
            <div class="eyebrow"><?= icone('sparkles') ?> Aparência</div>
            <h2>Tema</h2>
            <p>A mudança é imediata e fica guardada neste dispositivo.</p>
            <div class="tema-opcoes" role="group" aria-label="Escolher tema">
                <button type="button" class="tema-opcao" data-escolher-tema="light" aria-pressed="false">
                    <span class="tema-swatch claro" aria-hidden="true">☀</span> Claro
                </button>
                <button type="button" class="tema-opcao" data-escolher-tema="dark" aria-pressed="false">
                    <span class="tema-swatch escuro" aria-hidden="true">◐</span> Escuro
                </button>
                <button type="button" class="tema-opcao" data-escolher-tema="violet" aria-pressed="false">
                    <span class="tema-swatch purpura" aria-hidden="true">✦</span> Púrpura
                </button>
            </div>
        </section>

        <!-- Notificações -->
        <section class="settings-card" id="cardNotificacoes">
            <div class="eyebrow"><?= icone('notificacoes') ?> Avisos</div>
            <h2>Notificações</h2>
            <p>Escolhe como e onde queres ser avisado. Fica guardado na tua conta.</p>
            <form method="post" class="defs-form" id="formNotificacoes">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="preferencias">

                <div class="campo">
                    <label for="canal">Canal de aviso</label>
                    <select id="canal" name="canal">
                        <option value="interno" <?= $prefs['canal'] === 'interno' ? 'selected' : '' ?>>Dentro da app</option>
                        <option value="windows" <?= $prefs['canal'] === 'windows' ? 'selected' : '' ?>>Windows (app desktop)</option>
                        <option value="desligado" <?= $prefs['canal'] === 'desligado' ? 'selected' : '' ?>>Desligadas</option>
                    </select>
                </div>
                <p class="pref-estado" id="estadoCanal" role="status"></p>

                <div class="pref-grupo">
                    <label class="pref-linha" for="sons">
                        <input type="checkbox" id="sons" name="sons" <?= !empty($prefs['sons']) ? 'checked' : '' ?>>
                        <span>
                            <strong>Sons de notificação</strong>
                            <small>Avisos sonoros dentro da app, se o dispositivo permitir.</small>
                        </span>
                    </label>
                    <label class="pref-linha" for="som_alarme">
                        <input type="checkbox" id="som_alarme" name="som_alarme" <?= !empty($prefs['som_alarme']) ? 'checked' : '' ?>>
                        <span>
                            <strong>Som de alarme em tarefas de prioridade alta</strong>
                            <small>Só toca quando a própria tarefa tem esta opção ativada.</small>
                        </span>
                    </label>
                </div>

                <div class="campo">
                    <label for="volume">Volume dos avisos <output id="volumeSaida"><?= (int)$prefs['volume'] ?>%</output></label>
                    <input type="range" id="volume" name="volume" min="0" max="100" step="5" value="<?= (int)$prefs['volume'] ?>">
                </div>

                <div class="form-row">
                    <div class="campo">
                        <label for="snooze_min">Adiar alarme (minutos)</label>
                        <input type="number" id="snooze_min" name="snooze_min" min="1" max="60" value="<?= (int)$prefs['snooze_min'] ?>">
                    </div>
                    <div class="campo">
                        <label for="limite_som_seg">Limite do som (segundos)</label>
                        <input type="number" id="limite_som_seg" name="limite_som_seg" min="10" max="600" step="10" value="<?= (int)$prefs['limite_som_seg'] ?>">
                    </div>
                </div>

                <div class="pref-grupo">
                    <label class="pref-linha" for="mostrar_segundo_plano">
                        <input type="checkbox" id="mostrar_segundo_plano" name="mostrar_segundo_plano" <?= !empty($prefs['mostrar_segundo_plano']) ? 'checked' : '' ?>>
                        <span><strong>Continuar em segundo plano</strong><small>Manter avisos ativos quando a janela está minimizada.</small></span>
                    </label>
                    <label class="pref-linha" for="abrir_ao_alarme">
                        <input type="checkbox" id="abrir_ao_alarme" name="abrir_ao_alarme" <?= !empty($prefs['abrir_ao_alarme']) ? 'checked' : '' ?>>
                        <span><strong>Abrir a janela do alarme</strong><small>Mostrar o ecrã do alarme quando chega a hora.</small></span>
                    </label>
                    <label class="pref-linha" for="fechar_para_bandeja">
                        <input type="checkbox" id="fechar_para_bandeja" name="fechar_para_bandeja" <?= !empty($prefs['fechar_para_bandeja']) ? 'checked' : '' ?>>
                        <span><strong>Fechar para a bandeja</strong><small>Ao fechar a janela, continua a correr em segundo plano.</small></span>
                    </label>
                    <label class="pref-linha" for="iniciar_com_windows">
                        <input type="checkbox" id="iniciar_com_windows" name="iniciar_com_windows" <?= !empty($prefs['iniciar_com_windows']) ? 'checked' : '' ?>>
                        <span><strong>Iniciar com o Windows</strong><small>Disponível apenas na app desktop instalada.</small></span>
                    </label>
                </div>
                <p class="pref-estado" id="estadoDesktop" role="status"></p>

                <div class="pref-acoes">
                    <button type="submit" class="btn-primario">Guardar preferências</button>
                    <button type="button" class="btn-secundario" id="btnTestarSom">Testar som</button>
                </div>
            </form>
        </section>

        <!-- Palavra-passe -->
        <section class="settings-card">
            <div class="eyebrow"><?= icone('sparkles') ?> Segurança</div>
            <h2>Palavra-passe</h2>
            <p>Mantém a tua conta protegida com uma palavra-passe forte.</p>
            <form method="post" class="defs-form">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="passe">
                <div class="campo">
                    <label for="atual">Palavra-passe atual</label>
                    <input type="password" id="atual" name="atual" required autocomplete="current-password">
                </div>
                <div class="form-row">
                    <div class="campo">
                        <label for="nova">Nova</label>
                        <input type="password" id="nova" name="nova" required minlength="6" autocomplete="new-password">
                    </div>
                    <div class="campo">
                        <label for="confirma">Confirmar</label>
                        <input type="password" id="confirma" name="confirma" required minlength="6" autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="btn-primario">Alterar palavra-passe</button>
            </form>
        </section>

        <!-- Dados -->
        <section class="settings-card">
            <div class="eyebrow"><?= icone('sparkles') ?> Manutenção</div>
            <h2>Gestão de dados</h2>
            <p>Uma visão rápida e ações de limpeza.</p>
            <div class="defs-resumo">
                <div><span class="defs-num"><?= (int)$stats['pendentes'] ?></span> pendentes</div>
                <div><span class="defs-num"><?= (int)$stats['concluidas'] ?></span> concluídas</div>
                <div><span class="defs-num"><?= (int)$stats['total'] ?></span> no total</div>
            </div>
            <p class="defs-aviso">As ações abaixo apagam dados. Não é possível recuperar.</p>
            <form method="post" class="defs-acoes">
                <?= csrf_campo() ?>
                <button type="submit" class="btn-secundario" name="acao" value="limpar_concluidas">🧹 Eliminar tarefas concluídas</button>
                <button type="submit" class="btn-secundario perigo" name="acao" value="limpar_notifs">🗑 Eliminar notificações</button>
            </form>
        </section>
    </div>

    <footer class="dashboard-footer">
        <span>Pequenos ajustes, grandes diferenças.</span>
        <span>Habbits <span class="footer-separator">·</span> <?= substr(data_local(), 0, 4) ?></span>
    </footer>
</div>

<?php html_fim('definicoes.js'); ?>
