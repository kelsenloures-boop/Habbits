<?php
/**
 * Habbits — Ecrã do alarme a tocar.
 * Página autónoma (sem sidebar): ecrã inteiro na app desktop, janela no navegador.
 */

require_once __DIR__ . '/inc/bootstrap.php';
exigir_login();

$uid = uid_atual();
$id = (int)($_GET['ocorrencia'] ?? 0);

alarmes_processar($uid);
$o = $id > 0 ? alarmes_ocorrencia($id, $uid) : null;
$prefs = preferencias_obter($uid);

$somDisponivel = $o && !empty($o['som']) && !empty($prefs['sons']);
$limite = max(10, min(600, (int)($prefs['limite_som_seg'] ?? 120)));
$volume = max(0, min(100, (int)($prefs['volume'] ?? 80)));
$minutosAdiar = max(1, min(60, (int)($o['adiamento_min'] ?? $prefs['snooze_min'] ?? 10)));
$podeAdiar = $o && (int)$o['adiamentos'] < 5 && $o['estado'] === 'a_tocar';

$adiadoPara = ($o && $o['estado'] === 'adiado' && !empty($o['adiado_para'])) ? $o['adiado_para'] : null;
$ativo = $o && ($o['estado'] === 'a_tocar' || $adiadoPara !== null);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alarme — Habbits</title>
<script>try{var t=localStorage.getItem('habbits-tema')||'violet';document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
<link rel="stylesheet" href="assets/css/style.css?v=9">
</head>
<body class="alarme-ecra"
      data-ocorrencia="<?= $o ? (int)$o['id'] : 0 ?>"
      data-estado="<?= $o ? htmlspecialchars($o['estado']) : '' ?>"
      data-som="<?= $somDisponivel && $adiadoPara === null ? '1' : '0' ?>"
      data-volume="<?= $volume ?>"
      data-limite="<?= $limite ?>"
      data-minutos="<?= $minutosAdiar ?>"
      data-adiado-para="<?= $adiadoPara ? htmlspecialchars($adiadoPara) : '' ?>">

<?php if (!$o): ?>
<main class="alarme-caixa">
    <div class="alarme-icone" aria-hidden="true">⏰</div>
    <h1>Alarme já não está ativo</h1>
    <p>Pode ter sido desligado, adiado ou já ter terminado.</p>
    <a class="btn-primario" href="alarmes.php">Voltar aos alarmes</a>
</main>
<?php elseif (!$ativo): ?>
<main class="alarme-caixa">
    <div class="alarme-icone" aria-hidden="true">⏰</div>
    <h1>Este alarme já foi tratado</h1>
    <p>Estado atual: <strong><?= htmlspecialchars($o['estado']) ?></strong>.</p>
    <a class="btn-primario" href="alarmes.php">Voltar aos alarmes</a>
</main>
<?php else: ?>
<main class="alarme-caixa" data-caixa-alarme>
    <div class="alarme-icone" aria-hidden="true"><?= $adiadoPara ? '⏳' : '⏰' ?></div>

    <div class="alarme-relogio" id="relogioAlarme"><?= htmlspecialchars(substr($o['previsto_para'], 11, 5)) ?></div>

    <h1><?= htmlspecialchars($o['titulo']) ?></h1>
    <?php if ($o['mensagem'] !== ''): ?>
    <p><?= htmlspecialchars($o['mensagem']) ?></p>
    <?php endif; ?>
    <small class="alarme-nota">
        Previsto para <?= htmlspecialchars(substr($o['previsto_para'], 0, 10)) ?> às <?= htmlspecialchars(substr($o['previsto_para'], 11, 5)) ?>
        <?= $o['adiamentos'] > 0 ? ' · adiado ' . (int)$o['adiamentos'] . '×' : '' ?>
    </small>

    <?php if (!$adiadoPara && !$somDisponivel): ?>
    <small class="alarme-nota alarme-nota-aviso">
        <?php if (empty($o['som'])): ?>
        Este alarme está sem som.
        <?php else: ?>
        Os sons estão desativados nas definições.
        <?php endif; ?>
    </small>
    <?php endif; ?>

    <div class="alarme-botoes">
        <button type="button" class="btn-primario alarme-adiar" id="btnAdiar"
                <?= $podeAdiar && !$adiadoPara ? '' : 'hidden' ?>>
            Adiar <?= $minutosAdiar ?> min
        </button>
        <button type="button" class="btn-secundario alarme-desligar" id="btnDesligar">Desligar</button>
    </div>

    <p class="alarme-estado" id="estadoAlarme" role="status" aria-live="polite"><?= $adiadoPara ? 'Adiado. Volta a tocar automaticamente.' : '' ?></p>

    <small class="alarme-nota">
        Fechar esta janela sem escolher equivale a desligar o alarme.
    </small>
</main>
<?php endif; ?>

<script src="assets/js/alarme.js?v=10" defer></script>
</body>
</html>
