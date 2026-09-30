/**
 * CropPress — editor de imagens (Mídia > CropPress).
 *
 * O recorte, a rotação, o espelhamento e o redimensionamento são feitos no
 * navegador com o Cropper.js, exatamente como aparecem na tela. O resultado é
 * enviado ao servidor em PNG (sem perdas) e o servidor grava o formato final
 * (JPEG/PNG/WebP/AVIF) uma única vez — a imagem só é comprimida uma vez.
 */
( function ( $ ) {
	'use strict';

	var data = window.croppressData || {};
	var t = data.i18n || {};
	var image = data.image;
	var $app = $( '#croppress' );
	var img = document.getElementById( 'cp-image' );
	var cropper = null;
	var dirty = false;
	var restoring = false;

	// Estado do editor (tudo o que entra no desfazer/refazer, além do recorte).
	var state = {
		ratio: 'free', // free | 1:1 | 4:3 | 3:2 | 16:9 | 9:16 | custom
		custom: [ 21, 9 ],
		exact: null, // { w, h } — saída exata
		baseRotate: 0, // múltiplos de 90°
		fine: 0 // ajuste fino (-45° a 45°)
	};
	var resize = { mode: 'none', w: 0, h: 0, max: 1920, keep: true };
	var exportOpts = { format: 'jpeg', quality: 82, compression: 6, keepMeta: false, mode: 'new' };
	var history = { past: [], future: [] };

	/* ------------------------------------------------------------------ */
	/* Utilidades                                                          */
	/* ------------------------------------------------------------------ */

	function formatBytes( bytes ) {
		if ( ! bytes ) {
			return '0 KB';
		}
		return bytes >= 1048576 ? ( bytes / 1048576 ).toFixed( 1 ).replace( '.', ',' ) + ' MB' : Math.max( 1, Math.round( bytes / 1024 ) ) + ' KB';
	}

	function ratioValue( key, custom, exact ) {
		if ( exact ) {
			return exact.lock === false ? NaN : exact.w / exact.h;
		}
		if ( key === 'custom' ) {
			return custom[ 0 ] / custom[ 1 ];
		}
		if ( key === 'free' || ! key ) {
			return NaN;
		}
		var p = key.split( ':' );
		return parseFloat( p[ 0 ] ) / parseFloat( p[ 1 ] );
	}

	function markDirty() {
		dirty = true;
	}

	function clone( o ) {
		return JSON.parse( JSON.stringify( o ) );
	}

	/* ------------------------------------------------------------------ */
	/* Tamanho do recorte e tamanho final                                  */
	/* ------------------------------------------------------------------ */

	function cropSize() {
		var d = cropper.getData( true );
		return { w: Math.max( 1, d.width ), h: Math.max( 1, d.height ) };
	}

	function outputSize() {
		var c = cropSize();
		if ( state.exact ) {
			return { w: state.exact.w, h: state.exact.h };
		}
		var r = c.w / c.h;
		switch ( resize.mode ) {
			case 'width':
				return resize.w ? { w: resize.w, h: Math.max( 1, Math.round( resize.w / r ) ) } : c;
			case 'height':
				return resize.h ? { w: Math.max( 1, Math.round( resize.h * r ) ), h: resize.h } : c;
			case 'both':
				if ( ! resize.w || ! resize.h ) {
					return c;
				}
				if ( ! resize.keep ) {
					return { w: resize.w, h: resize.h };
				}
				// Mantendo a proporção: cabe dentro de largura × altura.
				var s = Math.min( resize.w / c.w, resize.h / c.h );
				return { w: Math.max( 1, Math.round( c.w * s ) ), h: Math.max( 1, Math.round( c.h * s ) ) };
			case 'max':
				var m = Math.max( c.w, c.h );
				if ( ! resize.max || m <= resize.max ) {
					return c; // nunca aumenta
				}
				var k = resize.max / m;
				return { w: Math.max( 1, Math.round( c.w * k ) ), h: Math.max( 1, Math.round( c.h * k ) ) };
			default:
				return c;
		}
	}

	function updateInfo() {
		if ( ! cropper ) {
			return;
		}
		var c = cropSize();
		var o = outputSize();
		var f = data.formats[ exportOpts.format ];
		$( '#cp-crop-size' ).text( c.w + ' × ' + c.h + ' px' );
		var same = o.w === c.w && o.h === c.h;
		$( '#cp-summary' ).html(
			'<span>' + ( same ? 'Tamanho final' : 'Recorte ' + c.w + ' × ' + c.h + ' → final' ) + ' <strong>' + o.w + ' × ' + o.h + ' px</strong></span>' +
			'<span class="cp-dot">·</span><span>' + f.label + ( f.lossy ? ' ' + exportOpts.quality + '%' : '' ) + '</span>'
		);

		// Textos de apoio das abas.
		if ( state.exact ) {
			$( '#cp-exact-hint' ).prop( 'hidden', false ).text( 'Saída exata: ' + state.exact.w + ' × ' + state.exact.h + ' px' + ( state.exact.lock === false ? ' — proporção livre: o recorte será esticado para esse tamanho e pode distorcer.' : ' — o recorte fica preso a essa proporção.' ) );
			$( '#cp-resize-hint' ).text( 'A dimensão exata (aba Recortar) está ativa e define o tamanho final: ' + o.w + ' × ' + o.h + ' px.' );
		} else {
			$( '#cp-exact-hint' ).prop( 'hidden', true );
			var up = o.w > c.w || o.h > c.h;
			$( '#cp-resize-hint' ).text( 'Tamanho final: ' + o.w + ' × ' + o.h + ' px' + ( up ? ' — maior que o recorte: a imagem será ampliada e pode perder nitidez.' : '.' ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Desfazer / refazer                                                  */
	/* ------------------------------------------------------------------ */

	function snapshot() {
		return {
			canvas: cropper.getCanvasData(),
			box: cropper.getCropBoxData(),
			data: cropper.getData(),
			state: clone( state )
		};
	}

	function pushHistory() {
		if ( restoring || ! cropper ) {
			return;
		}
		var snap = snapshot();
		var last = history.past[ history.past.length - 1 ];
		if ( last && JSON.stringify( last ) === JSON.stringify( snap ) ) {
			return;
		}
		history.past.push( snap );
		if ( history.past.length > 60 ) {
			history.past.shift();
		}
		history.future = [];
		if ( history.past.length > 1 ) {
			markDirty();
		}
		updateHistoryButtons();
	}

	function restore( snap ) {
		restoring = true;
		state = clone( snap.state );
		cropper.setAspectRatio( ratioValue( state.ratio, state.custom, state.exact ) );
		cropper.rotateTo( snap.data.rotate );
		cropper.scale( snap.data.scaleX, snap.data.scaleY );
		cropper.setCanvasData( snap.canvas );
		cropper.setCropBoxData( snap.box );
		syncControls();
		restoring = false;
		updateInfo();
	}

	function undo() {
		if ( history.past.length < 2 ) {
			return;
		}
		history.future.push( history.past.pop() );
		restore( history.past[ history.past.length - 1 ] );
		markDirty();
		updateHistoryButtons();
	}

	function redo() {
		if ( ! history.future.length ) {
			return;
		}
		var snap = history.future.pop();
		history.past.push( snap );
		restore( snap );
		markDirty();
		updateHistoryButtons();
	}

	function updateHistoryButtons() {
		$( '#cp-undo' ).prop( 'disabled', history.past.length < 2 );
		$( '#cp-redo' ).prop( 'disabled', ! history.future.length );
	}

	/* ------------------------------------------------------------------ */
	/* Controles ↔ estado                                                  */
	/* ------------------------------------------------------------------ */

	function syncControls() {
		$( '#cp-ratios .cp-chip' ).removeClass( 'is-active' );
		if ( ! state.exact ) {
			$( '#cp-ratios .cp-chip[data-ratio="' + state.ratio + '"]' ).addClass( 'is-active' );
		}
		$( '#cp-ratio-w' ).val( state.custom[ 0 ] );
		$( '#cp-ratio-h' ).val( state.custom[ 1 ] );
		$( '#cp-exact-clear' ).prop( 'hidden', ! state.exact );
		if ( state.exact ) {
			$( '#cp-exact-w' ).val( state.exact.w );
			$( '#cp-exact-h' ).val( state.exact.h );
			$( '#cp-exact-lock' ).prop( 'checked', state.exact.lock !== false );
		}
		$( '#cp-fine' ).val( state.fine );
		$( '#cp-fine-out' ).text( state.fine + '°' );
		syncZoom();
	}

	function syncZoom() {
		if ( ! cropper ) {
			return;
		}
		var c = cropper.getCanvasData();
		var pct = Math.round( ( c.width / c.naturalWidth ) * 100 );
		$( '#cp-zoom' ).val( pct );
		$( '#cp-zoom-out' ).text( pct + '%' );
	}

	function setRatio( key ) {
		state.ratio = key;
		state.exact = null;
		if ( key === 'custom' ) {
			state.custom = [ Math.max( 1, parseFloat( $( '#cp-ratio-w' ).val() ) || 1 ), Math.max( 1, parseFloat( $( '#cp-ratio-h' ).val() ) || 1 ) ];
		}
		cropper.setAspectRatio( ratioValue( state.ratio, state.custom, null ) );
		syncControls();
		updateInfo();
		pushHistory();
	}

	function applyRotation() {
		cropper.rotateTo( state.baseRotate + state.fine );
		updateInfo();
	}

	function setFormat( key ) {
		var f = data.formats[ key ];
		if ( ! f || ! f.supported ) {
			return;
		}
		exportOpts.format = key;
		$( '#cp-formats .cp-chip' ).removeClass( 'is-active' ).filter( '[data-format="' + key + '"]' ).addClass( 'is-active' );
		$( '#cp-quality-row' ).prop( 'hidden', ! f.lossy );
		$( '#cp-compression-row' ).prop( 'hidden', key !== 'png' );
		$( '#cp-ext' ).text( '.' + f.ext );
		// EXIF só pode ser mantido de JPEG para JPEG.
		var canKeep = key === 'jpeg' && image && image.mime === 'image/jpeg';
		$( '#cp-meta-keep-label' ).toggleClass( 'is-disabled', ! canKeep ).find( 'input' ).prop( 'disabled', ! canKeep );
		if ( ! canKeep ) {
			$( 'input[name="cp-meta"][value="0"]' ).prop( 'checked', true );
			exportOpts.keepMeta = false;
		}
		var warn = key === 'png' && image && image.mime === 'image/jpeg' ? 'Fotos em PNG ficam bem maiores que em JPEG ou WebP. PNG é indicado para capturas de tela, logos e imagens com transparência.' : '';
		warn = key === 'jpeg' && image && ( image.mime === 'image/png' || image.mime === 'image/webp' ) ? 'JPEG não tem transparência: áreas transparentes ficarão brancas.' : warn;
		$( '#cp-format-hint' ).prop( 'hidden', ! warn ).text( warn );
		updateInfo();
	}

	/* ------------------------------------------------------------------ */
	/* Geração da imagem final                                             */
	/* ------------------------------------------------------------------ */

	function resultCanvas() {
		var o = outputSize();
		return cropper.getCroppedCanvas( {
			width: o.w,
			height: o.h,
			fillColor: exportOpts.format === 'jpeg' ? '#ffffff' : 'transparent',
			imageSmoothingEnabled: true,
			imageSmoothingQuality: 'high',
			maxWidth: 20000,
			maxHeight: 20000
		} );
	}

	function toBlob( canvas, type, quality ) {
		return new Promise( function ( resolve ) {
			canvas.toBlob( resolve, type, quality );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Janela (visualizar / concluído)                                     */
	/* ------------------------------------------------------------------ */

	function openModal( title, body, foot ) {
		$( '#cp-modal-title' ).text( title );
		$( '#cp-modal-body' ).empty().append( body );
		$( '#cp-modal-foot' ).empty().append( foot );
		$( '#cp-modal' ).prop( 'hidden', false );
		$( '#cp-modal-close' ).trigger( 'focus' );
	}

	function closeModal() {
		$( '#cp-modal' ).prop( 'hidden', true );
		var url = $( '#cp-modal' ).data( 'objectUrl' );
		if ( url ) {
			URL.revokeObjectURL( url );
			$( '#cp-modal' ).removeData( 'objectUrl' );
		}
	}

	function preview() {
		var canvas = resultCanvas();
		var o = outputSize();
		var f = data.formats[ exportOpts.format ];
		var $body = $( '<div class="cp-preview"></div>' );
		var $img = $( '<img alt="">' );
		var $stats = $( '<dl class="cp-stats"></dl>' );
		$stats.append( '<div><dt>Original</dt><dd>' + image.width + ' × ' + image.height + ' px · ' + formatBytes( image.bytes ) + '</dd></div>' );
		var $result = $( '<div><dt>Resultado</dt><dd>' + o.w + ' × ' + o.h + ' px · ' + f.label + ' · <span class="cp-estimate">' + t.estimating + '</span></dd></div>' );
		$stats.append( $result );
		$body.append( $( '<div class="cp-preview__img"></div>' ).append( $img ), $stats );

		// Estimativa de tamanho feita pelo navegador (o servidor grava com qualidade equivalente).
		var type = { jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp' }[ exportOpts.format ];
		var pending = type ? toBlob( canvas, type, exportOpts.quality / 100 ) : toBlob( canvas, 'image/png' );
		pending.then( function ( blob ) {
			var url = URL.createObjectURL( blob );
			$( '#cp-modal' ).data( 'objectUrl', url );
			$img.attr( 'src', url );
			var sameType = type && blob && blob.type === type;
			$result.find( '.cp-estimate' ).text( sameType ? '≈ ' + formatBytes( blob.size ) : t.estimateAvif );
		} );

		var $foot = $( '<div></div>' ).append(
			$( '<button type="button" class="button"></button>' ).text( 'Voltar e ajustar' ).on( 'click', closeModal ),
			$( '<button type="button" class="button button-primary"></button>' ).text( 'Salvar' ).on( 'click', function () {
				closeModal();
				save();
			} )
		);
		openModal( 'Visualizar resultado', $body, $foot );
	}

	function save() {
		if ( ! cropper || $app.hasClass( 'is-saving' ) ) {
			return;
		}
		if ( exportOpts.mode === 'replace' && ! window.confirm( t.confirmReplace ) ) {
			return;
		}
		$app.addClass( 'is-saving' );
		$( '#cp-save' ).prop( 'disabled', true ).text( t.saving );

		toBlob( resultCanvas(), 'image/png' ).then( function ( blob ) {
			if ( ! blob ) {
				throw new Error( t.error );
			}
			if ( data.maxUpload && blob.size > data.maxUpload ) {
				throw new Error( t.tooBig.replace( '%s', formatBytes( data.maxUpload ) ) );
			}
			var form = new window.FormData();
			form.append( 'action', 'croppress_save' );
			form.append( 'nonce', data.nonce );
			form.append( 'attachment', image.id );
			form.append( 'mode', exportOpts.mode );
			form.append( 'format', exportOpts.format );
			form.append( 'quality', exportOpts.quality );
			form.append( 'compression', exportOpts.compression );
			form.append( 'keepMeta', exportOpts.keepMeta ? '1' : '' );
			form.append( 'filename', $( '#cp-filename' ).val() );
			form.append( 'image', blob, 'croppress.png' );
			return window.fetch( data.ajax, { method: 'POST', body: form, credentials: 'same-origin' } ).then( function ( r ) {
				return r.json();
			} );
		} ).then( function ( response ) {
			if ( ! response || ! response.success ) {
				throw new Error( ( response && response.data && response.data.message ) || t.error );
			}
			dirty = false;
			done( response.data );
		} ).catch( function ( error ) {
			window.alert( error.message || t.error );
		} ).then( function () {
			$app.removeClass( 'is-saving' );
			$( '#cp-save' ).prop( 'disabled', false ).text( 'Salvar' );
		} );
	}

	function done( result ) {
		var $body = $( '<div class="cp-preview"></div>' ).append(
			$( '<div class="cp-preview__img"></div>' ).append( $( '<img alt="">' ).attr( 'src', result.url + '?v=' + Date.now() ) ),
			$( '<p class="cp-done"></p>' ).text( result.mode === 'replace' ? t.savedReplace : t.savedNew ),
			$( '<dl class="cp-stats"></dl>' ).append(
				'<div><dt>Arquivo</dt><dd></dd></div>',
				'<div><dt>Tamanho</dt><dd>' + result.width + ' × ' + result.height + ' px · ' + formatBytes( result.bytes ) + '</dd></div>'
			)
		);
		$body.find( '.cp-stats dd' ).first().text( result.filename + ( result.exif ? ' (com EXIF)' : '' ) );
		var $foot = $( '<div></div>' ).append(
			$( '<a class="button"></a>' ).attr( 'href', data.libraryUrl ).text( 'Voltar à biblioteca' ),
			$( '<a class="button"></a>' ).attr( 'href', result.editLink ).text( 'Ver detalhes da imagem' ),
			$( '<a class="button button-primary"></a>' ).attr( 'href', data.editorUrl + '&attachment=' + result.id ).text( result.mode === 'replace' ? 'Continuar editando' : 'Editar a nova imagem' )
		);
		openModal( 'Imagem salva', $body, $foot );
	}

	/* ------------------------------------------------------------------ */
	/* Escolher imagem                                                     */
	/* ------------------------------------------------------------------ */

	function chooseImage() {
		if ( dirty && ! window.confirm( t.confirmLeave ) ) {
			return;
		}
		var frame = window.wp.media( {
			title: t.choose,
			library: { type: 'image' },
			button: { text: t.use },
			multiple: false
		} );
		frame.on( 'select', function () {
			var chosen = frame.state().get( 'selection' ).first().toJSON();
			dirty = false;
			window.location.href = data.editorUrl + '&attachment=' + chosen.id;
		} );
		frame.open();
	}

	/* ------------------------------------------------------------------ */
	/* Início                                                              */
	/* ------------------------------------------------------------------ */

	function start() {
		$( '#cp-choose, #cp-choose-empty' ).on( 'click', chooseImage );
		if ( ! image ) {
			return;
		}
		$app.attr( 'data-state', 'loading' );
		$( '#cp-file' ).text( image.title + ' · ' + image.width + ' × ' + image.height + ' px · ' + formatBytes( image.bytes ) );
		$( '#cp-filename' ).val( image.filename );

		// Formato inicial: o mesmo da imagem, quando o servidor consegue gravá-lo.
		var initial = { 'image/jpeg': 'jpeg', 'image/png': 'png', 'image/webp': 'webp', 'image/avif': 'avif' }[ image.mime ] || 'png';
		setFormat( data.formats[ initial ] && data.formats[ initial ].supported ? initial : ( data.formats.webp.supported ? 'webp' : 'jpeg' ) );

		var zoomTimer;
		img.onload = function () {
			cropper = new window.Cropper( img, {
				viewMode: 1,
				dragMode: 'move',
				autoCropArea: 1,
				background: false,
				responsive: true,
				restore: true,
				guides: true,
				center: true,
				toggleDragModeOnDblclick: false,
				ready: function () {
					$app.attr( 'data-state', 'editing' );
					syncControls();
					updateInfo();
					pushHistory();
				},
				crop: function () {
					if ( ! restoring ) {
						window.requestAnimationFrame( updateInfo );
					}
				},
				cropend: function () {
					pushHistory();
				},
				zoom: function () {
					window.setTimeout( syncZoom, 0 );
					window.clearTimeout( zoomTimer );
					zoomTimer = window.setTimeout( pushHistory, 350 );
				}
			} );
		};
		img.onerror = function () {
			$app.attr( 'data-state', 'empty' );
			window.alert( 'Não foi possível carregar a imagem.' );
		};
		img.src = image.url;
	}

	/* ------------------------------------------------------------------ */
	/* Eventos                                                             */
	/* ------------------------------------------------------------------ */

	// Abas.
	$( '.cp-tab' ).on( 'click', function () {
		var tab = $( this ).data( 'tab' );
		$( '.cp-tab' ).removeClass( 'is-active' ).attr( 'aria-selected', 'false' );
		$( this ).addClass( 'is-active' ).attr( 'aria-selected', 'true' );
		$( '.cp-panel' ).removeClass( 'is-active' ).prop( 'hidden', true );
		$( '#cp-panel-' + tab ).addClass( 'is-active' ).prop( 'hidden', false );
	} );

	// Recortar.
	$( '#cp-ratios' ).on( 'click', '.cp-chip', function () {
		if ( cropper ) {
			setRatio( $( this ).data( 'ratio' ) );
		}
	} );
	$( '#cp-ratio-w, #cp-ratio-h' ).on( 'change', function () {
		if ( cropper && state.ratio === 'custom' ) {
			setRatio( 'custom' );
		}
	} );

	// Dimensão exata: a saída é sempre exatamente largura × altura.
	// "Travar proporção" (padrão) prende o recorte à proporção L:A — nada distorce.
	// Desmarcado, o recorte fica livre e é esticado para L × A.
	$( '#cp-exact-apply' ).on( 'click', function () {
		var w = parseInt( $( '#cp-exact-w' ).val(), 10 );
		var h = parseInt( $( '#cp-exact-h' ).val(), 10 );
		if ( ! cropper || ! w || ! h ) {
			return;
		}
		var lock = $( '#cp-exact-lock' ).prop( 'checked' );
		state.exact = { w: Math.min( 20000, w ), h: Math.min( 20000, h ), lock: lock };
		cropper.setAspectRatio( lock ? w / h : NaN );
		syncControls();
		updateInfo();
		pushHistory();
	} );
	$( '#cp-exact-clear' ).on( 'click', function () {
		state.exact = null;
		setRatio( 'free' );
	} );

	// Girar, espelhar, zoom, centralizar.
	$( '#cp-panel-rotate' ).on( 'click', '[data-action]', function () {
		if ( ! cropper ) {
			return;
		}
		var action = $( this ).data( 'action' );
		var d = cropper.getData();
		switch ( action ) {
			case 'rotate-left':
				state.baseRotate -= 90;
				applyRotation();
				break;
			case 'rotate-right':
				state.baseRotate += 90;
				applyRotation();
				break;
			case 'flip-h':
				cropper.scaleX( -d.scaleX || -1 );
				break;
			case 'flip-v':
				cropper.scaleY( -d.scaleY || -1 );
				break;
			case 'zoom-in':
				cropper.zoom( 0.1 );
				return;
			case 'zoom-out':
				cropper.zoom( -0.1 );
				return;
			case 'center':
				var cont = cropper.getContainerData();
				var canv = cropper.getCanvasData();
				cropper.setCanvasData( { left: ( cont.width - canv.width ) / 2, top: ( cont.height - canv.height ) / 2 } );
				var box = cropper.getCropBoxData();
				cropper.setCropBoxData( { left: ( cont.width - box.width ) / 2, top: ( cont.height - box.height ) / 2 } );
				break;
		}
		updateInfo();
		pushHistory();
	} );
	$( '#cp-fine' ).on( 'input', function () {
		if ( ! cropper ) {
			return;
		}
		state.fine = parseFloat( $( this ).val() ) || 0;
		$( '#cp-fine-out' ).text( state.fine + '°' );
		applyRotation();
	} ).on( 'change', pushHistory );
	$( '#cp-zoom' ).on( 'input', function () {
		if ( cropper ) {
			cropper.zoomTo( ( parseInt( $( this ).val(), 10 ) || 100 ) / 100 );
		}
	} );

	// Redimensionar.
	$( '#cp-resize-modes' ).on( 'click', '.cp-chip', function () {
		resize.mode = $( this ).data( 'resize' );
		$( '#cp-resize-modes .cp-chip' ).removeClass( 'is-active' );
		$( this ).addClass( 'is-active' );
		var show = { w: [ 'width', 'both' ], h: [ 'height', 'both' ], max: [ 'max' ], keep: [ 'both' ] };
		$( '#cp-resize-fields' ).prop( 'hidden', resize.mode === 'none' );
		Object.keys( show ).forEach( function ( field ) {
			$( '#cp-resize-fields [data-field="' + field + '"]' ).prop( 'hidden', show[ field ].indexOf( resize.mode ) === -1 );
		} );
		if ( cropper && ! resize.w && ! resize.h ) {
			var c = cropSize();
			$( '#cp-resize-w' ).val( c.w );
			$( '#cp-resize-h' ).val( c.h );
			resize.w = c.w;
			resize.h = c.h;
		}
		markDirty();
		updateInfo();
	} );
	$( '#cp-resize-w, #cp-resize-h, #cp-resize-max, #cp-resize-keep' ).on( 'input change', function () {
		resize.w = parseInt( $( '#cp-resize-w' ).val(), 10 ) || 0;
		resize.h = parseInt( $( '#cp-resize-h' ).val(), 10 ) || 0;
		resize.max = parseInt( $( '#cp-resize-max' ).val(), 10 ) || 0;
		resize.keep = $( '#cp-resize-keep' ).prop( 'checked' );
		markDirty();
		updateInfo();
	} );

	// Exportar.
	$( '#cp-formats' ).on( 'click', '.cp-chip', function () {
		setFormat( $( this ).data( 'format' ) );
		markDirty();
	} );
	$( '#cp-quality' ).on( 'input', function () {
		exportOpts.quality = parseInt( $( this ).val(), 10 );
		$( '#cp-quality-out' ).text( exportOpts.quality );
		updateInfo();
	} );
	$( '#cp-compression' ).on( 'input', function () {
		exportOpts.compression = parseInt( $( this ).val(), 10 );
		$( '#cp-compression-out' ).text( exportOpts.compression );
	} );
	$( 'input[name="cp-meta"]' ).on( 'change', function () {
		exportOpts.keepMeta = $( 'input[name="cp-meta"]:checked' ).val() === '1';
	} );
	$( 'input[name="cp-mode"]' ).on( 'change', function () {
		exportOpts.mode = $( 'input[name="cp-mode"]:checked' ).val();
		$( '#cp-save' ).text( exportOpts.mode === 'replace' ? 'Salvar e substituir' : 'Salvar' );
	} );

	// Barra de ações.
	$( '#cp-undo' ).on( 'click', undo );
	$( '#cp-redo' ).on( 'click', redo );
	$( '#cp-reset' ).on( 'click', function () {
		if ( ! cropper ) {
			return;
		}
		state = { ratio: 'free', custom: state.custom, exact: null, baseRotate: 0, fine: 0 };
		cropper.reset();
		cropper.setAspectRatio( NaN );
		cropper.rotateTo( 0 );
		cropper.scale( 1, 1 );
		syncControls();
		updateInfo();
		pushHistory();
	} );
	$( '#cp-preview' ).on( 'click', function () {
		if ( cropper ) {
			preview();
		}
	} );
	$( '#cp-save' ).on( 'click', save );
	$( '#cp-modal-close' ).on( 'click', closeModal );
	$( '#cp-modal' ).on( 'click', function ( event ) {
		if ( event.target === this ) {
			closeModal();
		}
	} );

	// Sair com alterações não salvas.
	$( '#cp-close, #cp-cancel' ).on( 'click', function ( event ) {
		if ( dirty && ! window.confirm( t.confirmLeave ) ) {
			event.preventDefault();
		} else {
			dirty = false;
		}
	} );
	window.addEventListener( 'beforeunload', function ( event ) {
		if ( dirty ) {
			event.preventDefault();
			event.returnValue = '';
		}
	} );

	// Atalhos: Ctrl+Z desfaz; Ctrl+Y ou Ctrl+Shift+Z refaz; Esc fecha a janela.
	$( document ).on( 'keydown', function ( event ) {
		if ( event.key === 'Escape' && ! $( '#cp-modal' ).prop( 'hidden' ) ) {
			closeModal();
			return;
		}
		if ( ! ( event.ctrlKey || event.metaKey ) || $( event.target ).is( 'input, textarea' ) ) {
			return;
		}
		var key = event.key.toLowerCase();
		if ( key === 'z' && ! event.shiftKey ) {
			event.preventDefault();
			undo();
		} else if ( key === 'y' || ( key === 'z' && event.shiftKey ) ) {
			event.preventDefault();
			redo();
		}
	} );

	start();
}( jQuery ) );
