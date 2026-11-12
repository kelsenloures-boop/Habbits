<?php
/**
 * Gera assets/audio/alarme.wav — som de alarme original (sem serviços externos).
 * Uso: php tools/gerar-som.php [destino]
 *
 * Formato: WAV PCM mono, 44,1 kHz, 16 bits, ~2,4 s (loop curto).
 */

$destino = $argv[1] ?? __DIR__ . '/../assets/audio/alarme.wav';
$dir = dirname($destino);
if (!is_dir($dir)) mkdir($dir, 0777, true);

$taxa = 44100;
$canais = 1;
$bits = 16;

/* Padrão: três pares de notas (beep curto + pausa), com decaimento suave. */
$notas = [
    [0.00, 0.18, 880.0, 0.42],
    [0.26, 0.18, 660.0, 0.42],
    [0.55, 0.24, 990.0, 0.45],
    [0.90, 0.18, 880.0, 0.40],
    [1.16, 0.18, 660.0, 0.40],
    [1.45, 0.30, 784.0, 0.44],
];
$duracao = 2.0;

$total = (int)round($taxa * $duracao);
$amostras = '';

for ($i = 0; $i < $total; $i++) {
    $t = $i / $taxa;
    $valor = 0.0;

    foreach ($notas as [$inicio, $dur, $freq, $amp]) {
        if ($t < $inicio || $t >= $inicio + $dur) continue;

        $local = ($t - $inicio) / $dur;
        // envelope: ataque rápido, decaimento exponencial
        $env = min(1.0, $local / 0.08) * exp(-3.2 * $local);

        // tom fundamental + harmónico suave (evita timbre agressivo)
        $valor += $amp * $env * (
            sin(2 * M_PI * $freq * $t) +
            0.25 * sin(2 * M_PI * $freq * 2 * $t)
        );
    }

    $valor = max(-1.0, min(1.0, $valor));
    $amostras .= pack('v', (int)round($valor * 32767));
}

$bytes = $total * $canais * ($bits / 8);
$header = 'RIFF' . pack('V', 36 + $bytes) . 'WAVE'
    . 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', $canais)
    . pack('V', $taxa) . pack('V', $taxa * $canais * ($bits / 8))
    . pack('v', $canais * ($bits / 8)) . pack('v', $bits)
    . 'data' . pack('V', $bytes);

file_put_contents($destino, $header . $amostras);

echo 'Som gerado: ' . $destino . ' (' . filesize($destino) . " bytes)\n";
