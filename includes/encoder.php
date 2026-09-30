<?php
/**
 * Codificação da imagem final.
 *
 * O navegador envia o resultado do editor em PNG (sem perdas); aqui ele é
 * gravado uma única vez no formato escolhido, com a qualidade/compressão pedida.
 * Usa GD (disponível em praticamente toda hospedagem) e, para AVIF, Imagick
 * quando o GD não tiver suporte.
 *
 * @package croppress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formatos de saída e se o servidor consegue gravá-los.
 *
 * @return array formato => array( label, mime, ext, supported, lossy, reason ).
 */
function croppress_formats() {
	$gd       = function_exists( 'gd_info' ) ? gd_info() : array();
	$imagick  = extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
	$im_avif  = $imagick && in_array( 'AVIF', Imagick::queryFormats( 'AVIF' ), true );
	$gd_avif  = function_exists( 'imageavif' ) && ! empty( $gd['AVIF Support'] );
	$gd_webp  = function_exists( 'imagewebp' ) && ! empty( $gd['WebP Support'] );
	$no_avif  = __( 'O servidor não consegue gerar AVIF. É preciso PHP 8.1+ com GD compilado com AVIF, ou a extensão Imagick com suporte a AVIF.', 'croppress' );

	return array(
		'jpeg' => array( 'label' => 'JPEG', 'mime' => 'image/jpeg', 'ext' => 'jpg', 'supported' => function_exists( 'imagejpeg' ), 'lossy' => true, 'reason' => '' ),
		'png'  => array( 'label' => 'PNG', 'mime' => 'image/png', 'ext' => 'png', 'supported' => function_exists( 'imagepng' ), 'lossy' => false, 'reason' => '' ),
		'webp' => array( 'label' => 'WebP', 'mime' => 'image/webp', 'ext' => 'webp', 'supported' => $gd_webp, 'lossy' => true, 'reason' => $gd_webp ? '' : __( 'O servidor não consegue gerar WebP (GD sem suporte a WebP).', 'croppress' ) ),
		'avif' => array( 'label' => 'AVIF', 'mime' => 'image/avif', 'ext' => 'avif', 'supported' => $gd_avif || $im_avif, 'lossy' => true, 'reason' => $gd_avif || $im_avif ? '' : $no_avif ),
	);
}

/**
 * Grava a imagem (PNG vinda do editor) no formato final.
 *
 * @param string $source      Caminho do PNG enviado pelo editor.
 * @param string $dest        Caminho do arquivo final.
 * @param string $format      jpeg|png|webp|avif.
 * @param int    $quality     Qualidade 1–100 (JPEG, WebP, AVIF).
 * @param int    $compression Compressão PNG 0–9.
 * @param string $exif_from   JPEG original do qual copiar o EXIF (vazio = remover metadados).
 * @return true|WP_Error
 */
function croppress_encode( $source, $dest, $format, $quality, $compression, $exif_from = '' ) {
	$formats = croppress_formats();
	if ( empty( $formats[ $format ]['supported'] ) ) {
		return new WP_Error( 'croppress_format', __( 'Formato não suportado pelo servidor.', 'croppress' ) );
	}
	$quality     = min( 100, max( 1, (int) $quality ) );
	$compression = min( 9, max( 0, (int) $compression ) );

	// AVIF sem suporte no GD: tenta o Imagick.
	if ( 'avif' === $format && ! function_exists( 'imageavif' ) ) {
		try {
			$im = new Imagick( $source );
			$im->setImageFormat( 'AVIF' );
			$im->setImageCompressionQuality( $quality );
			$im->stripImage();
			$im->writeImage( $dest );
			$im->clear();
			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'croppress_avif', $e->getMessage() );
		}
	}

	$image = @imagecreatefrompng( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- arquivo inválido vira WP_Error abaixo.
	if ( ! $image ) {
		return new WP_Error( 'croppress_read', __( 'A imagem enviada pelo editor é inválida.', 'croppress' ) );
	}
	$width  = imagesx( $image );
	$height = imagesy( $image );

	if ( 'jpeg' === $format ) {
		// JPEG não tem transparência: o fundo (ex.: cantos de uma rotação) fica branco.
		$canvas = imagecreatetruecolor( $width, $height );
		imagefill( $canvas, 0, 0, imagecolorallocate( $canvas, 255, 255, 255 ) );
		imagecopy( $canvas, $image, 0, 0, 0, 0, $width, $height );
		imagedestroy( $image );
		imageinterlace( $canvas, true ); // JPEG progressivo: aparece mais rápido em conexões lentas.
		$ok = imagejpeg( $canvas, $dest, $quality );
		imagedestroy( $canvas );
		if ( $ok && $exif_from ) {
			croppress_copy_exif( $exif_from, $dest );
		}
	} else {
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		if ( 'png' === $format ) {
			$ok = imagepng( $image, $dest, $compression );
		} elseif ( 'webp' === $format ) {
			$ok = imagewebp( $image, $dest, $quality );
		} else {
			$ok = imageavif( $image, $dest, $quality );
		}
		imagedestroy( $image );
	}

	return $ok ? true : new WP_Error( 'croppress_write', __( 'Não foi possível gravar o arquivo final.', 'croppress' ) );
}

/**
 * Copia o bloco EXIF (APP1) de um JPEG para outro, marcando a orientação como
 * "normal" (1): o editor já entrega a imagem na posição certa, e manter a
 * orientação antiga faria visualizadores girarem a foto de novo.
 *
 * @param string $from JPEG original.
 * @param string $to   JPEG gerado.
 * @return bool
 */
function croppress_copy_exif( $from, $to ) {
	$source = file_get_contents( $from ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$target = file_get_contents( $to ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $source || ! $target || "\xFF\xD8" !== substr( $source, 0, 2 ) || "\xFF\xD8" !== substr( $target, 0, 2 ) ) {
		return false;
	}

	// Procura o segmento APP1 "Exif" no original.
	$exif = '';
	$pos  = 2;
	$len  = strlen( $source );
	while ( $pos + 4 <= $len && "\xFF" === $source[ $pos ] ) {
		$marker = ord( $source[ $pos + 1 ] );
		$size   = unpack( 'n', substr( $source, $pos + 2, 2 ) )[1];
		if ( 0xE1 === $marker && 'Exif' === substr( $source, $pos + 4, 4 ) ) {
			$exif = substr( $source, $pos, $size + 2 );
			break;
		}
		if ( 0xDA === $marker ) {
			break; // começo dos dados da imagem: não há mais cabeçalhos
		}
		$pos += $size + 2;
	}
	if ( ! $exif ) {
		return false;
	}

	$exif = croppress_exif_reset_orientation( $exif );

	// Insere logo depois do SOI, tirando o APP0 (JFIF) que o GD grava — o EXIF deve vir primeiro.
	$body = substr( $target, 2 );
	if ( "\xFF\xE0" === substr( $body, 0, 2 ) ) {
		$app0 = unpack( 'n', substr( $body, 2, 2 ) )[1];
		$body = substr( $body, $app0 + 2 );
	}
	return false !== file_put_contents( $to, "\xFF\xD8" . $exif . $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/**
 * Define a tag Orientation (0x0112) do IFD0 como 1 dentro de um segmento APP1.
 *
 * @param string $segment Segmento APP1 completo (FFE1 + tamanho + "Exif\0\0" + TIFF).
 * @return string
 */
function croppress_exif_reset_orientation( $segment ) {
	$tiff = 10; // FFE1(2) + tamanho(2) + "Exif\0\0"(6)
	$le   = 'II' === substr( $segment, $tiff, 2 );
	$u16  = function ( $at ) use ( $segment, $le ) {
		return unpack( $le ? 'v' : 'n', substr( $segment, $at, 2 ) )[1];
	};
	$u32  = function ( $at ) use ( $segment, $le ) {
		return unpack( $le ? 'V' : 'N', substr( $segment, $at, 4 ) )[1];
	};
	if ( strlen( $segment ) < $tiff + 8 ) {
		return $segment;
	}
	$ifd     = $tiff + $u32( $tiff + 4 );
	$entries = $ifd + 2 <= strlen( $segment ) ? $u16( $ifd ) : 0;
	for ( $i = 0; $i < $entries; $i++ ) {
		$entry = $ifd + 2 + $i * 12;
		if ( $entry + 12 > strlen( $segment ) ) {
			break;
		}
		if ( 0x0112 === $u16( $entry ) ) {
			$segment = substr_replace( $segment, pack( $le ? 'v' : 'n', 1 ), $entry + 8, 2 );
			break;
		}
	}
	return $segment;
}
