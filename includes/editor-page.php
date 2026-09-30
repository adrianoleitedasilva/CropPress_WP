<?php
/**
 * Tela do editor CropPress (Mídia > CropPress).
 *
 * @package croppress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cp_formats = croppress_formats();
$cp_ratios  = array(
	'free' => array( __( 'Livre', 'croppress' ), '' ),
	'1:1'  => array( __( 'Quadrado', 'croppress' ), '1:1' ),
	'4:3'  => array( '4:3', '' ),
	'3:2'  => array( '3:2', '' ),
	'16:9' => array( '16:9', '' ),
	'9:16' => array( '9:16', __( 'vertical', 'croppress' ) ),
);
?>
<div class="wrap croppress-wrap">
	<h1 class="screen-reader-text"><?php esc_html_e( 'CropPress', 'croppress' ); ?></h1>

	<div id="croppress" class="cp-app" data-state="empty">
		<header class="cp-top">
			<span class="cp-brand"><span class="dashicons dashicons-image-crop" aria-hidden="true"></span> CropPress</span>
			<span class="cp-file" id="cp-file"></span>
			<span class="cp-top-actions">
				<button type="button" class="cp-icon-btn" id="cp-undo" disabled aria-label="<?php esc_attr_e( 'Desfazer (Ctrl+Z)', 'croppress' ); ?>" title="<?php esc_attr_e( 'Desfazer (Ctrl+Z)', 'croppress' ); ?>"><span class="dashicons dashicons-undo"></span></button>
				<button type="button" class="cp-icon-btn" id="cp-redo" disabled aria-label="<?php esc_attr_e( 'Refazer (Ctrl+Y)', 'croppress' ); ?>" title="<?php esc_attr_e( 'Refazer (Ctrl+Y)', 'croppress' ); ?>"><span class="dashicons dashicons-redo"></span></button>
				<button type="button" class="cp-icon-btn" id="cp-choose" aria-label="<?php esc_attr_e( 'Escolher outra imagem', 'croppress' ); ?>" title="<?php esc_attr_e( 'Escolher outra imagem', 'croppress' ); ?>"><span class="dashicons dashicons-format-image"></span></button>
			</span>
			<a class="cp-close" id="cp-close" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" aria-label="<?php esc_attr_e( 'Fechar e voltar à biblioteca', 'croppress' ); ?>" title="<?php esc_attr_e( 'Fechar', 'croppress' ); ?>">&times;</a>
		</header>

		<div class="cp-stage" id="cp-stage">
			<div class="cp-empty">
				<span class="dashicons dashicons-image-crop" aria-hidden="true"></span>
				<p><?php esc_html_e( 'Escolha uma imagem da biblioteca para recortar, girar, redimensionar ou converter.', 'croppress' ); ?></p>
				<button type="button" class="button button-primary button-hero" id="cp-choose-empty"><?php esc_html_e( 'Escolher imagem', 'croppress' ); ?></button>
			</div>
			<div class="cp-canvas"><img id="cp-image" alt=""></div>
			<div class="cp-badge" id="cp-crop-size" aria-live="polite"></div>
		</div>

		<nav class="cp-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Ferramentas', 'croppress' ); ?>">
			<button type="button" role="tab" class="cp-tab is-active" aria-selected="true" aria-controls="cp-panel-crop" data-tab="crop"><span class="dashicons dashicons-image-crop"></span> <?php esc_html_e( 'Recortar', 'croppress' ); ?></button>
			<button type="button" role="tab" class="cp-tab" aria-selected="false" aria-controls="cp-panel-rotate" data-tab="rotate"><span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e( 'Girar e zoom', 'croppress' ); ?></button>
			<button type="button" role="tab" class="cp-tab" aria-selected="false" aria-controls="cp-panel-resize" data-tab="resize"><span class="dashicons dashicons-editor-expand"></span> <?php esc_html_e( 'Redimensionar', 'croppress' ); ?></button>
			<button type="button" role="tab" class="cp-tab" aria-selected="false" aria-controls="cp-panel-export" data-tab="export"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Exportar', 'croppress' ); ?></button>
		</nav>

		<div class="cp-panels">
			<!-- Recortar -->
			<section class="cp-panel is-active" id="cp-panel-crop" role="tabpanel">
				<div class="cp-row">
					<span class="cp-label"><?php esc_html_e( 'Proporção', 'croppress' ); ?></span>
					<div class="cp-chips" id="cp-ratios">
						<?php foreach ( $cp_ratios as $key => $ratio ) : ?>
							<button type="button" class="cp-chip<?php echo 'free' === $key ? ' is-active' : ''; ?>" data-ratio="<?php echo esc_attr( $key ); ?>">
								<?php echo esc_html( $ratio[0] ); ?><?php echo $ratio[1] ? ' <small>' . esc_html( $ratio[1] ) . '</small>' : ''; ?>
							</button>
						<?php endforeach; ?>
						<span class="cp-custom">
							<input type="number" min="1" step="1" id="cp-ratio-w" value="21" aria-label="<?php esc_attr_e( 'Proporção personalizada: largura', 'croppress' ); ?>">
							<span>:</span>
							<input type="number" min="1" step="1" id="cp-ratio-h" value="9" aria-label="<?php esc_attr_e( 'Proporção personalizada: altura', 'croppress' ); ?>">
							<button type="button" class="cp-chip" data-ratio="custom"><?php esc_html_e( 'Personalizada', 'croppress' ); ?></button>
						</span>
					</div>
				</div>
				<div class="cp-row">
					<span class="cp-label"><?php esc_html_e( 'Dimensão exata', 'croppress' ); ?></span>
					<div class="cp-inline">
						<label><?php esc_html_e( 'Largura', 'croppress' ); ?> <input type="number" min="1" id="cp-exact-w" placeholder="1200"> px</label>
						<label><?php esc_html_e( 'Altura', 'croppress' ); ?> <input type="number" min="1" id="cp-exact-h" placeholder="630"> px</label>
						<label class="cp-check"><input type="checkbox" id="cp-exact-lock" checked> <?php esc_html_e( 'Travar proporção', 'croppress' ); ?></label>
						<button type="button" class="button" id="cp-exact-apply"><?php esc_html_e( 'Aplicar', 'croppress' ); ?></button>
						<button type="button" class="button-link" id="cp-exact-clear" hidden><?php esc_html_e( 'Remover dimensão exata', 'croppress' ); ?></button>
					</div>
				</div>
				<p class="cp-hint" id="cp-exact-hint" hidden></p>
			</section>

			<!-- Girar e zoom -->
			<section class="cp-panel" id="cp-panel-rotate" role="tabpanel" hidden>
				<div class="cp-row">
					<span class="cp-label"><?php esc_html_e( 'Girar', 'croppress' ); ?></span>
					<div class="cp-inline">
						<button type="button" class="button" data-action="rotate-left"><span class="dashicons dashicons-image-rotate-left"></span> 90°</button>
						<button type="button" class="button" data-action="rotate-right"><span class="dashicons dashicons-image-rotate-right"></span> 90°</button>
						<label class="cp-range"><?php esc_html_e( 'Ajuste fino', 'croppress' ); ?> <input type="range" id="cp-fine" min="-45" max="45" step="0.5" value="0"> <output id="cp-fine-out">0°</output></label>
					</div>
				</div>
				<div class="cp-row">
					<span class="cp-label"><?php esc_html_e( 'Espelhar', 'croppress' ); ?></span>
					<div class="cp-inline">
						<button type="button" class="button" data-action="flip-h"><span class="dashicons dashicons-image-flip-horizontal"></span> <?php esc_html_e( 'Horizontal', 'croppress' ); ?></button>
						<button type="button" class="button" data-action="flip-v"><span class="dashicons dashicons-image-flip-vertical"></span> <?php esc_html_e( 'Vertical', 'croppress' ); ?></button>
					</div>
				</div>
				<div class="cp-row">
					<span class="cp-label"><?php esc_html_e( 'Zoom', 'croppress' ); ?></span>
					<div class="cp-inline">
						<button type="button" class="button" data-action="zoom-out" aria-label="<?php esc_attr_e( 'Diminuir zoom', 'croppress' ); ?>"><span class="dashicons dashicons-minus"></span></button>
						<input type="range" id="cp-zoom" min="5" max="400" step="1" value="100" aria-label="<?php esc_attr_e( 'Zoom', 'croppress' ); ?>">
						<button type="button" class="button" data-action="zoom-in" aria-label="<?php esc_attr_e( 'Aumentar zoom', 'croppress' ); ?>"><span class="dashicons dashicons-plus"></span></button>
						<output id="cp-zoom-out">100%</output>
						<button type="button" class="button" data-action="center"><span class="dashicons dashicons-align-center"></span> <?php esc_html_e( 'Centralizar', 'croppress' ); ?></button>
					</div>
				</div>
			</section>

			<!-- Redimensionar -->
			<section class="cp-panel" id="cp-panel-resize" role="tabpanel" hidden>
				<div class="cp-row">
					<span class="cp-label"><?php esc_html_e( 'Tamanho final', 'croppress' ); ?></span>
					<div class="cp-chips" id="cp-resize-modes">
						<button type="button" class="cp-chip is-active" data-resize="none"><?php esc_html_e( 'Igual ao recorte', 'croppress' ); ?></button>
						<button type="button" class="cp-chip" data-resize="width"><?php esc_html_e( 'Por largura', 'croppress' ); ?></button>
						<button type="button" class="cp-chip" data-resize="height"><?php esc_html_e( 'Por altura', 'croppress' ); ?></button>
						<button type="button" class="cp-chip" data-resize="both"><?php esc_html_e( 'Largura e altura', 'croppress' ); ?></button>
						<button type="button" class="cp-chip" data-resize="max"><?php esc_html_e( 'Dimensão máxima', 'croppress' ); ?></button>
					</div>
				</div>
				<div class="cp-row" id="cp-resize-fields" hidden>
					<span class="cp-label"></span>
					<div class="cp-inline">
						<label data-field="w"><?php esc_html_e( 'Largura', 'croppress' ); ?> <input type="number" min="1" id="cp-resize-w"> px</label>
						<label data-field="h"><?php esc_html_e( 'Altura', 'croppress' ); ?> <input type="number" min="1" id="cp-resize-h"> px</label>
						<label data-field="max"><?php esc_html_e( 'Lado maior até', 'croppress' ); ?> <input type="number" min="1" id="cp-resize-max" value="1920"> px</label>
						<label class="cp-check" data-field="keep"><input type="checkbox" id="cp-resize-keep" checked> <?php esc_html_e( 'Manter proporção', 'croppress' ); ?></label>
					</div>
				</div>
				<p class="cp-hint" id="cp-resize-hint"></p>
			</section>

			<!-- Exportar -->
			<section class="cp-panel" id="cp-panel-export" role="tabpanel" hidden>
				<div class="cp-row">
					<span class="cp-label"><?php esc_html_e( 'Formato', 'croppress' ); ?></span>
					<div class="cp-chips" id="cp-formats">
						<?php foreach ( $cp_formats as $key => $format ) : ?>
							<button type="button" class="cp-chip" data-format="<?php echo esc_attr( $key ); ?>" <?php disabled( ! $format['supported'] ); ?> title="<?php echo esc_attr( $format['reason'] ); ?>"><?php echo esc_html( $format['label'] ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
				<p class="cp-hint cp-hint--warn" id="cp-format-hint" hidden></p>
				<div class="cp-row" id="cp-quality-row">
					<span class="cp-label"><?php esc_html_e( 'Qualidade', 'croppress' ); ?></span>
					<div class="cp-inline">
						<input type="range" id="cp-quality" min="10" max="100" value="82">
						<output id="cp-quality-out">82</output>
						<span class="cp-muted"><?php esc_html_e( '82 é um bom equilíbrio entre qualidade e tamanho.', 'croppress' ); ?></span>
					</div>
				</div>
				<div class="cp-row" id="cp-compression-row" hidden>
					<span class="cp-label"><?php esc_html_e( 'Compressão', 'croppress' ); ?></span>
					<div class="cp-inline">
						<input type="range" id="cp-compression" min="0" max="9" value="6">
						<output id="cp-compression-out">6</output>
						<span class="cp-muted"><?php esc_html_e( 'PNG não perde qualidade: mais compressão = arquivo menor, salvamento mais lento.', 'croppress' ); ?></span>
					</div>
				</div>
				<div class="cp-row">
					<span class="cp-label"><label for="cp-filename"><?php esc_html_e( 'Nome do arquivo', 'croppress' ); ?></label></span>
					<div class="cp-inline cp-filename"><input type="text" id="cp-filename"><span id="cp-ext">.jpg</span></div>
				</div>
				<div class="cp-row">
					<span class="cp-label"><?php esc_html_e( 'Metadados', 'croppress' ); ?></span>
					<div class="cp-inline">
						<label class="cp-check"><input type="radio" name="cp-meta" value="0" checked> <?php esc_html_e( 'Remover (recomendado: arquivo menor e sem dados como a localização GPS)', 'croppress' ); ?></label>
						<label class="cp-check" id="cp-meta-keep-label"><input type="radio" name="cp-meta" value="1"> <?php esc_html_e( 'Manter (EXIF de JPEG para JPEG)', 'croppress' ); ?></label>
					</div>
				</div>
				<div class="cp-row">
					<span class="cp-label"><?php esc_html_e( 'Salvar como', 'croppress' ); ?></span>
					<div class="cp-inline">
						<label class="cp-check"><input type="radio" name="cp-mode" value="new" checked> <strong><?php esc_html_e( 'Nova imagem', 'croppress' ); ?></strong> <?php esc_html_e( '(recomendado: a original fica intacta)', 'croppress' ); ?></label>
						<label class="cp-check"><input type="radio" name="cp-mode" value="replace"> <?php esc_html_e( 'Substituir a original (ela fica guardada e pode ser restaurada)', 'croppress' ); ?></label>
					</div>
				</div>
			</section>
		</div>

		<footer class="cp-bottom">
			<span class="cp-summary" id="cp-summary"></span>
			<span class="cp-bottom-actions">
				<button type="button" class="button" id="cp-reset"><?php esc_html_e( 'Redefinir', 'croppress' ); ?></button>
				<a class="button" id="cp-cancel" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'Cancelar', 'croppress' ); ?></a>
				<button type="button" class="button" id="cp-preview"><?php esc_html_e( 'Visualizar resultado', 'croppress' ); ?></button>
				<button type="button" class="button button-primary" id="cp-save"><?php esc_html_e( 'Salvar', 'croppress' ); ?></button>
			</span>
		</footer>
	</div>

	<!-- Visualizar resultado / concluído -->
	<div class="cp-modal" id="cp-modal" hidden>
		<div class="cp-modal__box" role="dialog" aria-modal="true" aria-labelledby="cp-modal-title">
			<header class="cp-modal__head">
				<h2 id="cp-modal-title"></h2>
				<button type="button" class="cp-close" id="cp-modal-close" aria-label="<?php esc_attr_e( 'Fechar', 'croppress' ); ?>">&times;</button>
			</header>
			<div class="cp-modal__body" id="cp-modal-body"></div>
			<footer class="cp-modal__foot" id="cp-modal-foot"></footer>
		</div>
	</div>
</div>
