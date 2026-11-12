/* Gera os ícones da app desktop sem dependências externas.
   Uso: node tools/gerar-icone.js
   Produz desktop/tray.png (32x32) e desktop/icon.png (256x256). */
'use strict';

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

function crc32(buf) {
    let c, crc = 0xffffffff;
    for (let n = 0; n < buf.length; n++) {
        c = (crc ^ buf[n]) & 0xff;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        crc = (crc >>> 8) ^ c;
    }
    return (crc ^ 0xffffffff) >>> 0;
}

function chunk(tipo, dados) {
    const nome = Buffer.from(tipo, 'ascii');
    const tamanho = Buffer.alloc(4);
    tamanho.writeUInt32BE(dados.length);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(Buffer.concat([nome, dados])));
    return Buffer.concat([tamanho, nome, dados, crc]);
}

function png(tamanho, cor) {
    const linhas = [];
    for (let y = 0; y < tamanho; y++) {
        const linha = Buffer.alloc(1 + tamanho * 4);
        for (let x = 0; x < tamanho; x++) {
            const [r, g, b, a] = cor((x + 0.5) / tamanho, (y + 0.5) / tamanho, 1 / tamanho);
            const i = 1 + x * 4;
            linha[i] = r; linha[i + 1] = g; linha[i + 2] = b; linha[i + 3] = a;
        }
        linhas.push(linha);
    }

    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(tamanho, 0);
    ihdr.writeUInt32BE(tamanho, 4);
    ihdr[8] = 8;   // 8 bits por canal
    ihdr[9] = 6;   // RGBA
    ihdr[10] = 0; ihdr[11] = 0; ihdr[12] = 0;

    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        chunk('IHDR', ihdr),
        chunk('IDAT', zlib.deflateSync(Buffer.concat(linhas), { level: 9 })),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}

/* Amostragem 4x4 para suavizar as bordas */
function cor(u, v, pixel) {
    const amostras = 4;
    let dentroCirculo = 0;
    let dentroH = 0;

    for (let sy = 0; sy < amostras; sy++) {
        for (let sx = 0; sx < amostras; sx++) {
            const x = u + ((sx + 0.5) / amostras - 0.5) * pixel;
            const y = v + ((sy + 0.5) / amostras - 0.5) * pixel;
            const dx = x - 0.5, dy = y - 0.5;
            if (dx * dx + dy * dy <= 0.47 * 0.47) dentroCirculo++;

            const noH = (x >= 0.33 && x <= 0.42 && y >= 0.28 && y <= 0.72) ||
                        (x >= 0.58 && x <= 0.67 && y >= 0.28 && y <= 0.72) ||
                        (x >= 0.33 && x <= 0.67 && y >= 0.455 && y <= 0.545);
            if (noH) dentroH++;
        }
    }

    const total = amostras * amostras;
    if (dentroCirculo === 0) return [0, 0, 0, 0];

    // H branco sobre círculo púrpura; bordas com alfa parcial
    const alpha = Math.round((dentroCirculo / total) * 255);
    if (dentroH > 0) return [255, 255, 255, alpha];
    return [124, 58, 237, alpha];
}

const destino = path.resolve(__dirname, '..');
fs.writeFileSync(path.join(destino, 'tray.png'), png(32, cor));
fs.writeFileSync(path.join(destino, 'icon.png'), png(256, cor));
console.log('Ícones gerados: tray.png (32x32) e icon.png (256x256)');
