# CropPress

Editor de imagens simples, dentro da biblioteca de mídia do WordPress: recorte, rotação, espelhamento, redimensionamento e exportação em JPEG, PNG, WebP ou AVIF. Salva como nova imagem ou substitui a original **sem perder o arquivo antigo**.

![Versão](https://img.shields.io/badge/vers%C3%A3o-1.0.0-blue) ![WordPress](https://img.shields.io/badge/WordPress-6.3%2B-21759b) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4) ![Licença](https://img.shields.io/badge/licen%C3%A7a-GPL--2.0--or--later-green)

```
┌──────────────────────────────────────────────┐
│ CropPress                      ↶  ↷  🖼   ×  │
├──────────────────────────────────────────────┤
│              ┌───────────────┐               │
│              │    IMAGEM     │  1200 × 630   │
│              └───────────────┘               │
├──────────────────────────────────────────────┤
│ Recortar | Girar e zoom | Redimensionar | Exportar │
├──────────────────────────────────────────────┤
│ Proporção: Livre 1:1 4:3 3:2 16:9 9:16 …     │
│ Final 1200 × 630 px · WebP 82%               │
│     [Redefinir] [Cancelar] [Visualizar] [Salvar] │
└──────────────────────────────────────────────┘
```

## Recursos

**Onde abrir**
- **Mídia > CropPress**, que abre a escolha de uma imagem da biblioteca.
- Botão **"Editar no CropPress"** nos detalhes da imagem, inclusive na janela da biblioteca.
- Link **"Editar no CropPress"** na biblioteca em modo lista.

**Recortar**
- Livre, Quadrado (1:1), 4:3, 3:2, 16:9, 9:16 e proporção personalizada.
- Dimensão exata (ex.: 1200 × 630 px). Com **Travar proporção**, o recorte fica preso à proporção e a saída tem exatamente esse tamanho.

**Girar e zoom**
- Girar 90° e ajuste fino (−45° a 45°), espelhar na horizontal e na vertical.
- Zoom com botões e controle deslizante, centralizar.
- **Desfazer e refazer** (`Ctrl+Z`, `Ctrl+Y` ou `Ctrl+Shift+Z`) e Redefinir.

**Redimensionar**
- Por largura, por altura, largura e altura (mantendo ou não a proporção) ou dimensão máxima (nunca amplia).
- O tamanho final aparece o tempo todo na barra inferior.

**Exportar**
- JPEG, PNG, WebP e AVIF (AVIF quando o servidor suporta).
- Qualidade (JPEG, WebP e AVIF), compressão (PNG) e nome do arquivo.
- Metadados:
  - **remover** (padrão, sem dados como a localização GPS);
  - **manter** o EXIF, de JPEG para JPEG, com a orientação corrigida.
- **Visualizar resultado** mostra a imagem final e o tamanho estimado do arquivo.

**Salvar sem medo**
- **Nova imagem** (padrão): a original fica intacta, e a nova registra de qual imagem veio.
- **Substituir a original:** grava um arquivo novo e guarda o antigo. **Restaurar original**, nos detalhes da imagem, volta a ele a qualquer momento.

## Requisitos

- WordPress 6.3 ou superior (testado até 7.1)
- PHP 7.4 ou superior com a extensão **GD** (JPEG e PNG; WebP quando o GD tiver suporte)
- **AVIF:** PHP 8.1+ com GD compilado com AVIF, **ou** a extensão Imagick com suporte a AVIF. Sem isso, a opção aparece desativada, com a explicação.

## Instalação

1. Baixe o repositório como `.zip` e envie em **Plugins > Adicionar novo > Enviar plugin**. Outra opção é copiar a pasta para `wp-content/plugins/croppress/`.
2. Ative o **CropPress**.
3. Abra uma imagem por **Mídia > CropPress** ou pelo botão **Editar no CropPress**.

## Como funciona

1. O navegador aplica o recorte, a rotação, o espelhamento e o redimensionamento com o [Cropper.js](https://fengyuanchen.github.io/cropperjs), exatamente como na tela.
2. O resultado vai para o servidor **sem perdas** (PNG).
3. O servidor grava o formato final **uma única vez**, com a qualidade escolhida. A imagem só é comprimida uma vez.

Em "Substituir", o anexo passa a usar o arquivo novo, e os dados antigos ficam no campo `_croppress_original`. Posts que já usavam a imagem continuam funcionando, porque o arquivo antigo segue na pasta de uploads.

## Estrutura

```
croppress.php              cabeçalho do plugin, pontos de entrada na biblioteca, carregamento do editor
includes/editor-page.php   tela do editor (Mídia > CropPress)
includes/encoder.php       formatos suportados, gravação JPEG/PNG/WebP/AVIF, cópia do EXIF
includes/save.php          salvar (nova / substituir) e restaurar o original via AJAX
assets/js/croppress.js     editor: recorte, rotação, zoom, desfazer/refazer, redimensionar, exportar
assets/js/media.js         botão "Restaurar original" na biblioteca
assets/css/croppress.css   visual do editor (segue o esquema de cores do painel)
assets/cropper/            Cropper.js 1.6.3
```

## Segurança

- Todas as ações exigem permissão de upload **e** de edição da imagem, além de um nonce válido.
- O servidor só aceita PNG, com dimensões de até 20.000 px, e grava o arquivo final ele mesmo. O arquivo enviado pelo navegador nunca é salvo como está.
- Nomes de arquivo são sanitizados, e arquivos com o mesmo nome não são sobrescritos.

## Limitações conhecidas

- GIF animado é editado só no primeiro quadro.
- Alguns textos do JavaScript ainda estão escritos direto em português e não passam pelo sistema de tradução.

## Roadmap

- **Ajustes:** brilho, contraste, saturação, exposição, temperatura, matiz, nitidez, desfoque e escala de cinza.
- **Anotações** para artigos técnicos: retângulo, círculo, seta, linha, texto, desfoque ou pixelização de uma região e destaque.

## Changelog

**1.0.0**
- Primeira versão (MVP): recorte, rotação, espelhamento, zoom, desfazer/refazer, redimensionamento, exportação e restaurar original.

## Créditos e licença

- Recorte: [Cropper.js](https://fengyuanchen.github.io/cropperjs) 1.6.3, licença MIT (`assets/cropper/LICENSE-cropperjs.txt`).
- CropPress: GPL-2.0-or-later. Autor: [Adriano Silva](https://adrianosilva.pro.br).
