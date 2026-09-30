<?php
/**
 * Plugin Name:       CropPress
 * Plugin URI:        https://github.com/adrianoleitedasilva/CropPress_WP
 * Description:       Editor de imagens dentro do WordPress: recorte livre ou com proporções (1:1, 4:3, 3:2, 16:9, 9:16, personalizada ou dimensão exata), zoom, rotação, espelhamento, desfazer/refazer, redimensionamento e exportação em JPEG, PNG, WebP ou AVIF — salvando como nova imagem ou substituindo a original sem perder o arquivo antigo.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Adriano Silva
 * Author URI:        https://adrianosilva.pro.br
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       croppress
 * Domain Path:       /languages
 *
 * @package croppress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CROPPRESS_VERSION', '1.0.0' );
define( 'CROPPRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CROPPRESS_URL', plugin_dir_url( __FILE__ ) );

require CROPPRESS_DIR . 'includes/encoder.php';
require CROPPRESS_DIR . 'includes/save.php';

/**
 * Tipos de imagem que o CropPress abre.
 *
 * @return string[]
 */
function croppress_editable_mimes() {
	return array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif' );
}

/**
 * O anexo é uma imagem que o CropPress consegue editar?
 *
 * @param int $id ID do anexo.
 * @return bool
 */
function croppress_is_editable( $id ) {
	return in_array( get_post_mime_type( $id ), croppress_editable_mimes(), true );
}

/**
 * Endereço do editor para um anexo.
 *
 * @param int $id ID do anexo (0 = escolher imagem).
 * @return string
 */
function croppress_editor_url( $id = 0 ) {
	return add_query_arg( array_filter( array( 'page' => 'croppress', 'attachment' => (int) $id ) ), admin_url( 'upload.php' ) );
}

/**
 * Mídia > CropPress.
 */
function croppress_menu() {
	add_media_page( __( 'CropPress', 'croppress' ), __( 'CropPress', 'croppress' ), 'upload_files', 'croppress', 'croppress_render_page' );
}
add_action( 'admin_menu', 'croppress_menu' );

/**
 * Link "Editar no CropPress" na lista da biblioteca (modo lista).
 *
 * @param array   $actions Ações.
 * @param WP_Post $post    Anexo.
 * @return array
 */
function croppress_row_action( $actions, $post ) {
	if ( croppress_is_editable( $post->ID ) && current_user_can( 'edit_post', $post->ID ) ) {
		$actions['croppress'] = '<a href="' . esc_url( croppress_editor_url( $post->ID ) ) . '">' . esc_html__( 'Editar no CropPress', 'croppress' ) . '</a>';
	}
	return $actions;
}
add_filter( 'media_row_actions', 'croppress_row_action', 10, 2 );

/**
 * Botão "Editar no CropPress" nos detalhes da imagem (janela da biblioteca e
 * tela de edição do anexo), com "Restaurar original" quando houver.
 *
 * @param array   $fields Campos.
 * @param WP_Post $post   Anexo.
 * @return array
 */
function croppress_attachment_field( $fields, $post ) {
	if ( ! croppress_is_editable( $post->ID ) || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $fields;
	}
	$html = '<a class="button button-primary croppress-open" href="' . esc_url( croppress_editor_url( $post->ID ) ) . '">' . esc_html__( 'Editar no CropPress', 'croppress' ) . '</a>';
	if ( get_post_meta( $post->ID, '_croppress_original', true ) ) {
		$html .= ' <button type="button" class="button croppress-restore" data-id="' . (int) $post->ID . '" data-nonce="' . esc_attr( wp_create_nonce( 'croppress_restore_' . $post->ID ) ) . '">' . esc_html__( 'Restaurar original', 'croppress' ) . '</button>';
	}
	$fields['croppress'] = array(
		'label' => __( 'CropPress', 'croppress' ),
		'input' => 'html',
		'html'  => $html,
		'helps' => __( 'Recortar, girar, redimensionar e exportar em outro formato.', 'croppress' ),
	);
	return $fields;
}
add_filter( 'attachment_fields_to_edit', 'croppress_attachment_field', 10, 2 );

/**
 * "Restaurar original" funciona também dentro da janela da biblioteca.
 */
function croppress_media_script() {
	if ( wp_script_is( 'croppress-media', 'enqueued' ) ) {
		return;
	}
	wp_enqueue_script( 'croppress-media', CROPPRESS_URL . 'assets/js/media.js', array( 'jquery' ), CROPPRESS_VERSION, true );
	wp_localize_script(
		'croppress-media',
		'croppressMedia',
		array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'confirm' => __( 'Voltar a usar a imagem original? A versão editada continua salva na pasta de uploads.', 'croppress' ),
			'done'    => __( 'Imagem original restaurada.', 'croppress' ),
		)
	);
}
add_action( 'wp_enqueue_media', 'croppress_media_script' );

/**
 * Telas que mostram o botão sem carregar a biblioteca de mídia (detalhes do anexo, lista).
 *
 * @param string $hook Página atual.
 */
function croppress_media_script_screens( $hook ) {
	if ( in_array( $hook, array( 'post.php', 'upload.php' ), true ) ) {
		croppress_media_script();
	}
}
add_action( 'admin_enqueue_scripts', 'croppress_media_script_screens' );

/**
 * Dados da imagem que o editor precisa.
 *
 * @param int $id ID do anexo.
 * @return array|null
 */
function croppress_attachment_data( $id ) {
	$post = get_post( $id );
	if ( ! $post || 'attachment' !== $post->post_type || ! croppress_is_editable( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		return null;
	}

	// Usa o arquivo original quando o WordPress guardou uma versão reduzida ("-scaled").
	$url  = function_exists( 'wp_get_original_image_url' ) ? wp_get_original_image_url( $id ) : '';
	$path = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $id ) : '';
	$url  = $url ? $url : wp_get_attachment_url( $id );
	$path = $path ? $path : get_attached_file( $id );
	$size = $path && file_exists( $path ) ? wp_getimagesize( $path ) : false;
	$name = pathinfo( $path, PATHINFO_FILENAME );

	return array(
		'id'        => (int) $id,
		'url'       => $url . '?v=' . ( file_exists( $path ) ? filemtime( $path ) : 0 ), // evita cache de uma versão anterior
		'width'     => $size ? (int) $size[0] : 0,
		'height'    => $size ? (int) $size[1] : 0,
		'mime'      => get_post_mime_type( $id ),
		'title'     => get_the_title( $id ),
		'filename'  => preg_replace( '/-(scaled|croppress(-\d+)?)$/', '', $name ) . '-editada',
		'bytes'     => $path && file_exists( $path ) ? filesize( $path ) : 0,
		'editLink'  => get_edit_post_link( $id, 'raw' ),
		'hasBackup' => (bool) get_post_meta( $id, '_croppress_original', true ),
	);
}

/**
 * Scripts e estilos do editor (só na página do CropPress).
 *
 * @param string $hook Página atual.
 */
function croppress_assets( $hook ) {
	if ( 'media_page_croppress' !== $hook ) {
		return;
	}
	$id = isset( $_GET['attachment'] ) ? absint( $_GET['attachment'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

	wp_enqueue_media();
	wp_enqueue_style( 'cropperjs', CROPPRESS_URL . 'assets/cropper/cropper.min.css', array(), '1.6.3' );
	wp_enqueue_style( 'croppress', CROPPRESS_URL . 'assets/css/croppress.css', array( 'cropperjs' ), CROPPRESS_VERSION );
	wp_enqueue_script( 'cropperjs', CROPPRESS_URL . 'assets/cropper/cropper.min.js', array(), '1.6.3', true );
	wp_enqueue_script( 'croppress', CROPPRESS_URL . 'assets/js/croppress.js', array( 'cropperjs', 'jquery' ), CROPPRESS_VERSION, true );
	wp_localize_script(
		'croppress',
		'croppressData',
		array(
			'ajax'       => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'croppress_save' ),
			'image'      => $id ? croppress_attachment_data( $id ) : null,
			'formats'    => croppress_formats(),
			'maxUpload'  => wp_max_upload_size(),
			'editorUrl'  => croppress_editor_url(),
			'libraryUrl' => admin_url( 'upload.php' ),
			'i18n'       => array(
				'choose'        => __( 'Escolher imagem para editar', 'croppress' ),
				'use'           => __( 'Editar esta imagem', 'croppress' ),
				'free'          => __( 'Livre', 'croppress' ),
				'saving'        => __( 'Salvando…', 'croppress' ),
				'savedNew'      => __( 'Nova imagem salva na biblioteca.', 'croppress' ),
				'savedReplace'  => __( 'Imagem substituída. O arquivo original continua guardado (use "Restaurar original" nos detalhes da imagem).', 'croppress' ),
				'error'         => __( 'Não foi possível salvar a imagem.', 'croppress' ),
				'tooBig'        => __( 'A imagem final é grande demais para enviar ao servidor (limite: %s). Reduza as dimensões na aba Redimensionar.', 'croppress' ),
				'confirmLeave'  => __( 'Descartar as alterações desta imagem?', 'croppress' ),
				'confirmReplace' => __( 'Substituir a imagem original? Ela continua guardada e pode ser restaurada depois.', 'croppress' ),
				'estimating'    => __( 'calculando…', 'croppress' ),
				'estimateAvif'  => __( 'calculado ao salvar', 'croppress' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'croppress_assets' );

/**
 * Página do editor (Mídia > CropPress).
 */
function croppress_render_page() {
	if ( ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'croppress' ) );
	}
	require CROPPRESS_DIR . 'includes/editor-page.php';
}

/**
 * Link "Abrir o editor" na lista de plugins.
 *
 * @param array $links Links.
 * @return array
 */
function croppress_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( croppress_editor_url() ) . '">' . esc_html__( 'Abrir o editor', 'croppress' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'croppress_action_links' );

/**
 * Traduções.
 */
function croppress_load_textdomain() {
	load_plugin_textdomain( 'croppress', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'croppress_load_textdomain' );
