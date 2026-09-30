=== CropPress ===
Contributors: adrianosilva
Tags: image editor, crop, resize, webp, media library
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recorte, gire, redimensione e converta imagens sem sair do WordPress.

== Description ==

O CropPress é um editor de imagens simples, dentro da biblioteca de mídia.

**Onde abrir**

* Mídia > CropPress (escolhe uma imagem da biblioteca).
* Botão "Editar no CropPress" nos detalhes da imagem (inclusive na janela da biblioteca).
* Link "Editar no CropPress" na biblioteca em modo lista.

**Recortar**

* Livre, Quadrado (1:1), 4:3, 3:2, 16:9, 9:16 e proporção personalizada.
* Dimensão exata (ex.: 1200 × 630 px) com "Travar proporção": o recorte fica preso à proporção e a saída tem exatamente esse tamanho.

**Girar e zoom**

* Girar 90° e ajuste fino (−45° a 45°), espelhar na horizontal e na vertical.
* Zoom com botões e controle deslizante, centralizar.
* Desfazer e refazer (Ctrl+Z, Ctrl+Y) e Redefinir.

**Redimensionar**

* Por largura, por altura, largura e altura (mantendo ou não a proporção) ou dimensão máxima (nunca amplia).
* O tamanho final aparece o tempo todo na barra inferior.

**Exportar**

* JPEG, PNG, WebP e AVIF (AVIF quando o servidor suporta).
* Qualidade (JPEG, WebP, AVIF) e compressão (PNG), nome do arquivo.
* Metadados: remover (padrão, sem dados como a localização GPS) ou manter o EXIF (JPEG → JPEG, com a orientação corrigida).
* "Visualizar resultado" mostra a imagem final e o tamanho estimado do arquivo.

**Salvar sem medo**

* "Nova imagem" (padrão): a original fica intacta.
* "Substituir a original": grava um arquivo novo e guarda o antigo; "Restaurar original" volta a ele a qualquer momento.

**Como funciona**

O navegador aplica o recorte, a rotação e o redimensionamento exatamente como na tela e envia o resultado sem perdas (PNG). O servidor grava o formato final uma única vez — a imagem só é comprimida uma vez.

== Frequently Asked Questions ==

= Por que o AVIF aparece desativado? =
O servidor precisa gerar AVIF: PHP 8.1+ com GD compilado com AVIF, ou a extensão Imagick com suporte a AVIF. Quando houver suporte, a opção é liberada sozinha.

= Posso editar GIF animado? =
O CropPress edita o primeiro quadro; o resultado é uma imagem estática.

= "Substituir a original" apaga a imagem antiga? =
Não. Posts que já usavam a imagem continuam funcionando, e o arquivo antigo pode ser restaurado nos detalhes da imagem.

== Changelog ==

= 1.0.0 =
* Primeira versão (MVP): recorte, rotação, espelhamento, zoom, desfazer/refazer, redimensionamento e exportação.

== Roadmap ==

* Ajustes: brilho, contraste, saturação, exposição, temperatura, matiz, nitidez, desfoque e escala de cinza.
* Anotações para artigos técnicos: retângulo, círculo, seta, linha, texto, desfoque/pixelização de uma região e destaque.

== Credits ==

Recorte por Cropper.js 1.6.3 (MIT License) — https://fengyuanchen.github.io/cropperjs — ver assets/cropper/LICENSE-cropperjs.txt.
