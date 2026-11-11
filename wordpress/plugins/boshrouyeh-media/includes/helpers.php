<?php
/**
 * Helpers.
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Jalali date (core plugin when present).
 *
 * @param string $format Format.
 * @param int    $ts     Timestamp.
 * @return string
 */
function bmnm_jdate( $format, $ts ) {
	return function_exists( 'bmn_jdate' ) ? (string) bmn_jdate( $format, (int) $ts ) : (string) wp_date( $format, (int) $ts );
}

/**
 * Persian digits.
 *
 * @param mixed $v Value.
 * @return string
 */
function bmnm_fa( $v ) {
	return function_exists( 'bmn_fa_digits' ) ? bmn_fa_digits( (string) $v ) : (string) $v;
}

/**
 * Rate limit (core limiter).
 *
 * @param string $bucket Bucket.
 * @param int    $limit  Limit.
 * @param int    $window Seconds.
 * @return bool
 */
function bmnm_rate_limit( $bucket, $limit, $window ) {
	return function_exists( 'bmn_rate_limit' ) ? bmn_rate_limit( $bucket, $limit, $window ) : true;
}

/**
 * Client IP.
 *
 * @return string
 */
function bmnm_ip() {
	if ( function_exists( 'bmn_client_ip' ) ) {
		return bmn_client_ip();
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Normalised Iranian mobile or ''.
 *
 * @param string $p Phone.
 * @return string
 */
function bmnm_mobile( $p ) {
	if ( function_exists( 'bmn_normalize_mobile' ) ) {
		return bmn_normalize_mobile( $p );
	}
	$p = preg_replace( '/\D+/', '', (string) $p );
	return preg_match( '/^09\d{9}$/', $p ) ? $p : '';
}

/**
 * JSON error.
 *
 * @param string $code Code.
 * @param string $msg  Message.
 * @param int    $status HTTP status.
 * @return WP_Error
 */
function bmnm_error( $code, $msg, $status = 400 ) {
	return new WP_Error( 'bmnm_' . $code, $msg, array( 'status' => $status ) );
}

/**
 * Aparat video hash from URL or bare hash.
 *
 * @param string $url URL.
 * @return string
 */
function bmnm_aparat_hash( $url ) {
	$url = trim( (string) $url );
	if ( preg_match( '#^[A-Za-z0-9]{3,20}$#', $url ) ) {
		return $url;
	}
	if ( preg_match( '#^https?://(?:www\.)?aparat\.com/(?:v/|video/video/embed/videohash/)([A-Za-z0-9]{3,20})#i', $url, $m ) ) {
		return $m[1];
	}
	return '';
}

/**
 * Find the first Aparat link in a text.
 *
 * @param string $text Text.
 * @return string Hash or ''.
 */
function bmnm_find_aparat( $text ) {
	return preg_match( '#https?://(?:www\.)?aparat\.com/v/([A-Za-z0-9]{3,20})#i', (string) $text, $m ) ? $m[1] : '';
}

/**
 * Aparat embed URL.
 *
 * @param string $hash Hash.
 * @return string
 */
function bmnm_aparat_embed( $hash ) {
	return 'https://www.aparat.com/video/video/embed/videohash/' . rawurlencode( $hash ) . '/vt/frame';
}

/**
 * Responsive Aparat player.
 *
 * @param string $hash  Hash.
 * @param string $title Title.
 * @return string
 */
function bmnm_aparat_html( $hash, $title = '' ) {
	if ( ! $hash ) {
		return '';
	}
	return '<div class="bmnm-video"><iframe src="' . esc_url( bmnm_aparat_embed( $hash ) ) . '" title="' . esc_attr( $title ? $title : 'ویدیو' ) . '" loading="lazy" allowfullscreen allow="autoplay; fullscreen; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
}

/**
 * Media post types.
 *
 * @return string[]
 */
function bmnm_types() {
	return (array) apply_filters( 'bmnm_types', array( 'post', 'bmn_report' ) );
}

/**
 * Gallery attachment ids.
 *
 * @param int $post_id Post.
 * @return int[]
 */
function bmnm_gallery_ids( $post_id ) {
	$ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post_id, '_bmn_gallery', true ) ) ) );
	return array_values( array_filter( $ids, 'wp_attachment_is_image' ) );
}

/**
 * All images of a post (featured first, then gallery), unique.
 *
 * @param int $post_id Post.
 * @return int[]
 */
function bmnm_images( $post_id ) {
	$ids   = bmnm_gallery_ids( $post_id );
	$thumb = (int) get_post_thumbnail_id( $post_id );
	if ( $thumb ) {
		array_unshift( $ids, $thumb );
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Share caption.
 *
 * @param WP_Post $p        Post.
 * @param string  $template Template ({title} {excerpt} {link} {video} {hashtags}).
 * @param int     $max      Max chars (0 = no limit).
 * @return string
 */
function bmnm_caption( $p, $template = '', $max = 0 ) {
	$opts     = get_option( 'bmnm_social', array() );
	$template = $template ? $template : ( ! empty( $opts['template'] ) ? (string) $opts['template'] : "📌 {title}\n\n{excerpt}\n\n{video}\n🔗 {link}\n\n{hashtags}" );
	$custom   = trim( (string) get_post_meta( $p->ID, '_bmn_caption', true ) );
	$hash     = bmnm_aparat_hash( (string) get_post_meta( $p->ID, '_bmn_aparat', true ) );
	$tags     = array( '#شهرداری_بشرویه' );
	$terms    = get_the_terms( $p, 'post_tag' );
	foreach ( is_array( $terms ) ? $terms : array() as $t ) {
		if ( count( $tags ) < 6 ) {
			$tags[] = '#' . preg_replace( '/[\s\-\x{200C}]+/u', '_', $t->name );
		}
	}
	$title   = html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' );
	$excerpt = $custom ? $custom : html_entity_decode( wp_strip_all_tags( has_excerpt( $p ) ? $p->post_excerpt : wp_trim_words( strip_shortcodes( $p->post_content ), 45, '…' ) ), ENT_QUOTES, 'UTF-8' );
	$link    = get_permalink( $p );
	$build   = static function ( $ex ) use ( $template, $title, $link, $hash, $tags ) {
		$out = strtr(
			$template,
			array(
				'{title}'    => $title,
				'{excerpt}'  => $ex,
				'{link}'     => $link,
				'{video}'    => $hash ? '🎬 https://www.aparat.com/v/' . $hash : '',
				'{hashtags}' => implode( ' ', $tags ),
			)
		);
		return trim( preg_replace( "/\n{3,}/", "\n\n", $out ) );
	};
	$text = $build( $excerpt );
	if ( $max && mb_strlen( $text ) > $max ) {
		$over = mb_strlen( $text ) - $max + 2;
		$text = $build( mb_strlen( $excerpt ) > $over ? mb_substr( $excerpt, 0, mb_strlen( $excerpt ) - $over ) . '…' : '' );
		$text = mb_substr( $text, 0, $max );
	}
	return $text;
}

/**
 * Share links.
 *
 * @param string $url  URL.
 * @param string $text Text.
 * @return array<string,array{label:string,url:string}>
 */
function bmnm_share_links( $url, $text ) {
	$u = rawurlencode( $url );
	$t = rawurlencode( $text );
	return array(
		'bale'     => array( 'label' => 'بله', 'url' => 'https://ble.ir/share/url?url=' . $u . '&text=' . $t ),
		'eitaa'    => array( 'label' => 'ایتا', 'url' => 'https://eitaa.com/share/url?url=' . $u . '&text=' . $t ),
		'telegram' => array( 'label' => 'تلگرام', 'url' => 'https://t.me/share/url?url=' . $u . '&text=' . $t ),
		'whatsapp' => array( 'label' => 'واتساپ', 'url' => 'https://wa.me/?text=' . rawurlencode( $text . "\n" . $url ) ),
	);
}

/**
 * Build a multipart/form-data body.
 *
 * @param array $fields Scalar fields.
 * @param array $files  name => [path, filename, mime].
 * @return array{0:string,1:string} Body and Content-Type.
 */
function bmnm_multipart( $fields, $files = array() ) {
	$b    = '----bmnm' . bin2hex( random_bytes( 8 ) );
	$body = '';
	foreach ( $fields as $k => $v ) {
		$body .= "--{$b}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
	}
	foreach ( $files as $k => $f ) {
		$data = is_readable( $f[0] ) ? (string) file_get_contents( $f[0] ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$name = str_replace( array( '"', "\r", "\n" ), '', (string) $f[1] );
		$body .= "--{$b}\r\nContent-Disposition: form-data; name=\"{$k}\"; filename=\"{$name}\"\r\nContent-Type: {$f[2]}\r\n\r\n{$data}\r\n";
	}
	$body .= "--{$b}--\r\n";
	return array( $body, 'multipart/form-data; boundary=' . $b );
}

/**
 * Local file of an attachment, resized to a messenger-friendly copy when large.
 *
 * @param int $att Attachment id.
 * @return array{0:string,1:string,2:string}|null [path, filename, mime]
 */
function bmnm_attachment_file( $att ) {
	$path = get_attached_file( $att );
	if ( ! $path || ! is_readable( $path ) ) {
		return null;
	}
	$meta = wp_get_attachment_metadata( $att );
	if ( ! empty( $meta['sizes']['large']['file'] ) ) {
		$large = trailingslashit( dirname( $path ) ) . $meta['sizes']['large']['file'];
		if ( is_readable( $large ) ) {
			$path = $large;
		}
	}
	$type = wp_check_filetype( $path );
	return array( $path, basename( $path ), $type['type'] ? $type['type'] : 'image/jpeg' );
}

/**
 * Log line for admins (kept small).
 *
 * @param string $channel Channel.
 * @param string $msg     Message.
 */
function bmnm_log( $channel, $msg ) {
	$log   = get_option( 'bmnm_log', array() );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		't' => time(),
		'c' => $channel,
		'm' => mb_substr( wp_strip_all_tags( (string) $msg ), 0, 300 ),
		'u' => get_current_user_id(),
	);
	update_option( 'bmnm_log', array_slice( $log, -300 ), false );
}
