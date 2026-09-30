<?php
/**
 * Salvar (nova imagem ou substituir a original) e restaurar o original.
 *
 * "Substituir" nunca apaga nada: o resultado é gravado num arquivo novo, o
 * anexo passa a apontar para ele e o arquivo/metadados antigos ficam guardados
 * em _croppress_original para "Restaurar original". Posts que já usavam a
 * imagem antiga continuam funcionando (o arquivo antigo segue na pasta).
 *
 * @package croppress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX: salva o resultado do editor.
 */
function croppress_ajax_save() {
	check_ajax_referer( 'croppress_save', 'nonce' );

	$id   = isset( $_POST['attachment'] ) ? absint( $_POST['attachment'] ) : 0;
	$mode = isset( $_POST['mode'] ) && 'replace' === $_POST['mode'] ? 'replace' : 'new';
	if ( ! current_user_can( 'upload_files' ) || ! $id || ! current_user_can( 'edit_post', $id ) || ! croppress_is_editable( $id ) ) {
		wp_send_json_error( array( 'message' => __( 'Sem permissão para editar esta imagem.', 'croppress' ) ), 403 );
	}

	// A imagem do editor chega como PNG (sem perdas).
	$file = isset( $_FILES['image'] ) ? $_FILES['image'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $file || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		wp_send_json_error( array( 'message' => __( 'A imagem não chegou ao servidor (verifique o limite de upload).', 'croppress' ) ), 400 );
	}
	$info = wp_getimagesize( $file['tmp_name'] );
	if ( ! $info || IMAGETYPE_PNG !== $info[2] || $info[0] < 1 || $info[1] < 1 || $info[0] > 20000 || $info[1] > 20000 ) {
		wp_send_json_error( array( 'message' => __( 'Imagem inválida.', 'croppress' ) ), 400 );
	}

	$formats = croppress_formats();
	$format  = isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : 'jpeg';
	if ( empty( $formats[ $format ]['supported'] ) ) {
		wp_send_json_error( array( 'message' => $formats[ $format ]['reason'] ?? __( 'Formato inválido.', 'croppress' ) ), 400 );
	}
	$quality     = isset( $_POST['quality'] ) ? absint( $_POST['quality'] ) : 82;
	$compression = isset( $_POST['compression'] ) ? absint( $_POST['compression'] ) : 6;
	$keep_meta   = ! empty( $_POST['keepMeta'] );
	$name        = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';
	$name        = preg_replace( '/\.(jpe?g|png|webp|avif|gif)$/i', '', $name );
	$name        = $name ? $name : 'imagem-editada';

	// Pasta: a mesma da original ao substituir; a pasta do mês atual para uma nova imagem.
	$original = get_attached_file( $id );
	$source   = function_exists( 'wp_get_original_image_path' ) && wp_get_original_image_path( $id ) ? wp_get_original_image_path( $id ) : $original;
	$uploads = wp_upload_dir();
	if ( 'replace' === $mode ) {
		$dir = dirname( $original );
	} else {
		if ( ! empty( $uploads['error'] ) ) {
			wp_send_json_error( array( 'message' => $uploads['error'] ), 500 );
		}
		$dir = $uploads['path'];
	}
	$dest = trailingslashit( $dir ) . wp_unique_filename( $dir, $name . '.' . $formats[ $format ]['ext'] );

	// EXIF só de JPEG para JPEG (nos outros formatos os metadados são removidos).
	$exif_from = $keep_meta && 'jpeg' === $format && 'image/jpeg' === wp_get_image_mime( $source ) ? $source : '';
	$result    = croppress_encode( $file['tmp_name'], $dest, $format, $quality, $compression, $exif_from );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	$mime = $formats[ $format ]['mime'];

	if ( 'replace' === $mode ) {
		if ( ! get_post_meta( $id, '_croppress_original', true ) ) {
			update_post_meta(
				$id,
				'_croppress_original',
				array(
					'file' => get_post_meta( $id, '_wp_attached_file', true ),
					'meta' => wp_get_attachment_metadata( $id ),
					'mime' => get_post_mime_type( $id ),
				)
			);
		}
		update_attached_file( $id, $dest );
		wp_update_post( array( 'ID' => $id, 'post_mime_type' => $mime ) );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dest ) );
		clean_attachment_cache( $id );
		$result_id = $id;
	} else {
		$post      = get_post( $id );
		$result_id = wp_insert_attachment(
			array(
				/* translators: %s: título da imagem original */
				'post_title'     => sprintf( __( '%s (editada)', 'croppress' ), $post->post_title ),
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				'post_mime_type' => $mime,
				'post_parent'    => $post->post_parent,
				'guid'           => trailingslashit( $uploads['url'] ) . wp_basename( $dest ),
			),
			$dest,
			$post->post_parent,
			true
		);
		if ( is_wp_error( $result_id ) ) {
			wp_delete_file( $dest );
			wp_send_json_error( array( 'message' => $result_id->get_error_message() ), 500 );
		}
		wp_update_attachment_metadata( $result_id, wp_generate_attachment_metadata( $result_id, $dest ) );
		$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
		if ( $alt ) {
			update_post_meta( $result_id, '_wp_attachment_image_alt', $alt );
		}
		update_post_meta( $result_id, '_croppress_source', $id );
	}

	$final = wp_getimagesize( $dest );
	wp_send_json_success(
		array(
			'id'       => (int) $result_id,
			'mode'     => $mode,
			'url'      => wp_get_attachment_url( $result_id ),
			'editLink' => get_edit_post_link( $result_id, 'raw' ),
			'width'    => $final ? (int) $final[0] : 0,
			'height'   => $final ? (int) $final[1] : 0,
			'bytes'    => filesize( $dest ),
			'filename' => wp_basename( $dest ),
			'exif'     => (bool) $exif_from,
		)
	);
}
add_action( 'wp_ajax_croppress_save', 'croppress_ajax_save' );

/**
 * AJAX: volta a usar o arquivo original guardado antes de "Substituir".
 */
function croppress_ajax_restore() {
	$id = isset( $_POST['attachment'] ) ? absint( $_POST['attachment'] ) : 0;
	check_ajax_referer( 'croppress_restore_' . $id, 'nonce' );
	if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
		wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'croppress' ) ), 403 );
	}
	$backup = get_post_meta( $id, '_croppress_original', true );
	if ( ! is_array( $backup ) || empty( $backup['file'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Não há original guardado para esta imagem.', 'croppress' ) ), 404 );
	}
	update_post_meta( $id, '_wp_attached_file', $backup['file'] );
	wp_update_attachment_metadata( $id, $backup['meta'] );
	wp_update_post( array( 'ID' => $id, 'post_mime_type' => $backup['mime'] ) );
	delete_post_meta( $id, '_croppress_original' );
	clean_attachment_cache( $id );
	wp_send_json_success( array( 'url' => wp_get_attachment_url( $id ) ) );
}
add_action( 'wp_ajax_croppress_restore', 'croppress_ajax_restore' );
