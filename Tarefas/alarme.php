<?php
/**
 * Habbits — Ecrã do alarme a tocar.
 * Página autónoma (sem sidebar): ecrã inteiro na app desktop, janela no navegador.
 *
 * Nota de tempo: todas as horas vêm do SQLite em hora local (Africa/Maputo),
 * a mesma que o Windows/Electron usa. Nenhum cálculo de agenda é feito aqui.
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
$podeAdiar = $o && (int)$o['adiamentos'] < 5;

$adiadoPara = ($o && $o['estado'] === 'adiado' && !empty($o['adiado_para'])) ? $o['adiado_para'] : null;
$ativo = $o && ($o['estado'] === 'a_tocar' || $adiadoPara !== null);
$aTocar = $o && $o['estado'] === 'a_tocar';

$previstoTexto = '';
if ($o) {
    $diaPrev  = substr($o['previsto_para'], 0, 10);
    $horaPrev = substr($o['previsto_para'], 11, 5);
    $quando   = $diaPrev === data_local()
        ? 'hoje'
        : ($diaPrev === dias_local_mais(1) ? 'amanhã' : substr($diaPrev, 8, 2) . '/' . substr($diaPrev, 5, 2));
    $previstoTexto = 'Previsto para ' . $quando . ' às ' . $horaPrev;
}

$estadoInicial = $adiadoPara ? 'Adiado até ' . substr($adiadoPara, 11, 5) . '.' : '';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alarme — Habbits</title>
<script>try{var t=localStorage.getItem('habbits-tema');if(t!=='light'&&t!=='dark'&&t!=='violet')t='violet';document.documentElement.setAttribute('data-theme',t);}catch(e){document.documentElement.setAttribute('data-theme','violet');}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Work+Sans:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/css/style.css?v=11">
</head>
<body class="alarme-ecra"
      data-ocorrencia="<?= $o ? (int)$o['id'] : 0 ?>"
      data-estado="<?= $o ? htmlspecialchars($o['estado']) : '' ?>"
      data-som="<?= $somDisponivel ? '1' : '0' ?>"
      data-volume="<?= $volume ?>"
      data-limite="<?= $limite ?>"
      data-minutos="<?= $minutosAdiar ?>"
      data-adiados="<?= $o ? (int)$o['adiamentos'] : 0 ?>"
      data-adiado-para="<?= $adiadoPara ? htmlspecialchars($adiadoPara) : '' ?>">

<header class="alarme-marca" aria-label="Habbits">
    <span class="alarme-marca-nome">habbits</span><span class="alarme-marca-ponto">.</span>
</header>

<?php if (!$o): ?>
<main class="alarme-conteudo">
    <h1 class="alarme-titulo">Alarme já não está ativo</h1>
    <p class="alarme-mensagem">Pode ter sido desligado, adiado ou já ter terminado.</p>
    <a class="btn-secundario alarme-voltar" href="alarmes.php">Voltar aos alarmes</a>
</main>
<?php elseif (!$ativo): ?>
<main class="alarme-conteudo">
    <h1 class="alarme-titulo">Este alarme já foi tratado</h1>
    <p class="alarme-mensagem">Estado atual: <strong><?= htmlspecialchars($o['estado']) ?></strong>.</p>
    <a class="btn-secundario alarme-voltar" href="alarmes.php">Voltar aos alarmes</a>
</main>
<?php else: ?>
<main class="alarme-conteudo">
    <div class="alarme-palco">
        <div class="alarme-relogio" id="relogioAlarme"><?= htmlspecialchars(hora_local()) ?></div>

        <div class="alarme-contagem" id="contagemAlarme" aria-hidden="true">
            <strong id="contagemTempo"><?= $adiadoPara ? htmlspecialchars(substr($adiadoPara, 11, 5)) : '00:00' ?></strong>
            <small id="contagemLegenda"><?= $adiadoPara ? 'Adiado · volta às ' . htmlspecialchars(substr($adiadoPara, 11, 5)) : 'Adiado' ?></small>
        </div>
    </div>

    <h1 class="alarme-titulo"><?= htmlspecialchars($o['titulo']) ?></h1>

    <?php if ($o['mensagem'] !== ''): ?>
    <p class="alarme-mensagem"><?= htmlspecialchars($o['mensagem']) ?></p>
    <?php endif; ?>

    <p class="alarme-previsto">
        <?= htmlspecialchars($previstoTexto) ?><?= $o['adiamentos'] > 0 ? ' · adiado ' . (int)$o['adiamentos'] . '×' : '' ?>
    </p>

    <?php if ($aTocar && !$somDisponivel): ?>
    <p class="alarme-aviso">
        <?= empty($o['som']) ? 'Este alarme está sem som.' : 'Os sons estão desativados nas definições.' ?>
    </p>
    <?php endif; ?>

    <div class="alarme-botoes">
        <button type="button" class="btn-primario alarme-adiar" id="btnAdiar" <?= $podeAdiar ? '' : 'disabled' ?>>
            Adiar <?= $minutosAdiar ?> min
        </button>
        <button type="button" class="btn-secundario alarme-desligar" id="btnDesligar">Desligar</button>
    </div>

    <p class="alarme-estado" id="estadoAlarme" role="status" aria-live="polite"><?= htmlspecialchars($estadoInicial) ?></p>
</main>
<?php endif; ?>

<script src="assets/js/alarme.js?v=11" defer></script>
</body>
</html>
