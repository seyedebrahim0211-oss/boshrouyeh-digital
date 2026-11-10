<?php
/**
 * Render callbacks for dynamic blocks. Every value is escaped on output.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current singular post id (works inside templates and the builder preview).
 *
 * @return int
 */
function bmn_current_id() {
	$id = (int) get_queried_object_id();
	if ( ! $id || ! get_post( $id ) ) {
		$id = (int) get_the_ID();
	}
	return $id;
}

/**
 * JSON-LD helper.
 *
 * @param array $data Data.
 * @return string
 */
function bmn_json_ld( $data ) {
	return '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>';
}

/* ------------------------------------------------------------------ */
/* Breadcrumbs                                                         */
/* ------------------------------------------------------------------ */

/**
 * Breadcrumbs with BreadcrumbList schema.
 *
 * @return string
 */
function bmn_render_breadcrumbs() {
	if ( is_front_page() ) {
		return '';
	}
	$items = array( array( __( 'خانه', 'boshrouyeh-core' ), home_url( '/' ) ) );
	if ( is_singular() ) {
		$post = get_queried_object();
		$pto  = get_post_type_object( $post->post_type );
		if ( 'post' === $post->post_type ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			$items[]        = array( __( 'اخبار', 'boshrouyeh-core' ), $page_for_posts ? get_permalink( $page_for_posts ) : home_url( '/news/' ) );
		} elseif ( 'page' === $post->post_type ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $anc ) {
				$items[] = array( get_the_title( $anc ), get_permalink( $anc ) );
			}
		} elseif ( $pto && $pto->has_archive ) {
			$items[] = array( $pto->labels->name, get_post_type_archive_link( $post->post_type ) );
			if ( 'bmn_service' === $post->post_type ) {
				$terms = get_the_terms( $post, 'bmn_service_cat' );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$items[] = array( $terms[0]->name, get_term_link( $terms[0] ) );
				}
			}
		}
		$items[] = array( get_the_title( $post ), '' );
	} elseif ( is_post_type_archive() ) {
		$items[] = array( post_type_archive_title( '', false ), '' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		$tax  = get_taxonomy( $term->taxonomy );
		if ( $tax && ! empty( $tax->object_type[0] ) ) {
			$link = get_post_type_archive_link( $tax->object_type[0] );
			if ( $link ) {
				$items[] = array( get_post_type_object( $tax->object_type[0] )->labels->name, $link );
			}
		}
		$items[] = array( single_term_title( '', false ), '' );
	} elseif ( is_search() ) {
		$items[] = array( __( 'نتایج جست‌وجو', 'boshrouyeh-core' ), '' );
	} elseif ( is_home() ) {
		$items[] = array( __( 'اخبار', 'boshrouyeh-core' ), '' );
	} elseif ( is_404() ) {
		$items[] = array( __( 'یافت نشد', 'boshrouyeh-core' ), '' );
	} else {
		return '';
	}

	$html   = '<nav class="kh-breadcrumbs" aria-label="' . esc_attr__( 'مسیر راهنما', 'boshrouyeh-core' ) . '"><ol>';
	$schema = array();
	$last   = count( $items ) - 1;
	foreach ( $items as $i => $item ) {
		$label = wp_strip_all_tags( (string) $item[0] );
		$url   = is_string( $item[1] ) ? $item[1] : '';
		if ( $i === $last || ! $url ) {
			$html .= '<li><span aria-current="page">' . esc_html( $label ) . '</span></li>';
		} else {
			$html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
		}
		$schema[] = array_filter(
			array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $label,
				'item'     => $url ? $url : null,
			)
		);
	}
	$html .= '</ol></nav>';
	return $html . bmn_json_ld(
		array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $schema,
		)
	);
}

/* ------------------------------------------------------------------ */
/* Article helpers                                                     */
/* ------------------------------------------------------------------ */

/**
 * Reading time.
 *
 * @return string
 */
function bmn_render_reading_time() {
	$post = get_post( bmn_current_id() );
	if ( ! $post ) {
		return '';
	}
	$words = count( preg_split( '/\s+/u', trim( wp_strip_all_tags( $post->post_content ) ), -1, PREG_SPLIT_NO_EMPTY ) );
	$min   = max( 1, (int) ceil( $words / 200 ) );
	/* translators: %s: minutes. */
	return '<span class="kh-reading">' . bmn_icon( 'clock', 16 ) . ' ' . esc_html( sprintf( __( '%s دقیقه مطالعه', 'boshrouyeh-core' ), bmn_fa_digits( $min ) ) ) . '</span>';
}

/**
 * Share buttons (no third-party scripts).
 *
 * @return string
 */
function bmn_render_share() {
	$id = bmn_current_id();
	if ( ! $id ) {
		return '';
	}
	$url   = (string) wp_get_shortlink( $id );
	$url   = $url ? $url : (string) get_permalink( $id );
	$title = get_the_title( $id );
	$enc   = rawurlencode( $url );
	$t     = rawurlencode( wp_strip_all_tags( $title ) );
	$links = array(
		'تلگرام' => array( 'TG', 'https://t.me/share/url?url=' . $enc . '&text=' . $t ),
		'واتساپ' => array( 'WA', 'https://wa.me/?text=' . $t . '%20' . $enc ),
		'ایتا'   => array( 'ET', 'https://eitaa.com/share/url?url=' . $enc . '&text=' . $t ),
	);
	$html = '<div class="kh-share"><span class="kh-label">' . esc_html__( 'اشتراک‌گذاری:', 'boshrouyeh-core' ) . '</span>';
	foreach ( $links as $label => $l ) {
		$html .= '<a href="' . esc_url( $l[1] ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $label ) . '">' . esc_html( $l[0] ) . '</a>';
	}
	$html .= '<button type="button" data-kh-copy="' . esc_attr( $url ) . '" aria-label="' . esc_attr__( 'کپی پیوند کوتاه', 'boshrouyeh-core' ) . '">' . bmn_icon( 'external', 18 ) . '</button>';
	$html .= '<button type="button" data-kh-share aria-label="' . esc_attr__( 'اشتراک‌گذاری', 'boshrouyeh-core' ) . '">' . bmn_icon( 'upload', 18 ) . '</button></div>';
	return $html;
}

/* ------------------------------------------------------------------ */
/* Services                                                            */
/* ------------------------------------------------------------------ */

/**
 * URL of a service, honoring a mapped internal e-service page.
 *
 * @param int $id Service id.
 * @return string
 */
function bmn_service_url( $id ) {
	return (string) get_permalink( $id );
}

/**
 * Quick services strip.
 *
 * @param array $attrs Attributes.
 * @return string
 */
function bmn_render_quick_services( $attrs ) {
	$count = max( 3, min( 11, (int) ( $attrs['count'] ?? 7 ) ) );
	$posts = get_posts(
		array(
			'post_type'      => 'bmn_service',
			'posts_per_page' => $count,
			'meta_key'       => '_bmn_featured', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'no_found_rows'  => true,
		)
	);
	$html = '<nav class="kh-quick" aria-label="' . esc_attr__( 'خدمات پرکاربرد', 'boshrouyeh-core' ) . '"><ul class="kh-quick__list">';
	foreach ( $posts as $p ) {
		$label = bmn_meta( $p->ID, 'short_title', get_the_title( $p ) );
		$html .= '<li><a class="kh-quick__item" href="' . esc_url( bmn_service_url( $p->ID ) ) . '"><span class="kh-taqche">' . bmn_icon( (string) bmn_meta( $p->ID, 'icon', 'grid' ) ) . '</span><span>' . esc_html( $label ) . '</span></a></li>';
	}
	$html .= '<li><a class="kh-quick__item" href="' . esc_url( (string) get_post_type_archive_link( 'bmn_service' ) ) . '"><span class="kh-taqche">' . bmn_icon( 'grid' ) . '</span><span>' . esc_html__( 'همهٔ خدمات', 'boshrouyeh-core' ) . '</span></a></li>';
	return $html . '</ul></nav>';
}

/**
 * Service chips.
 *
 * @param int $id Service id.
 * @return string
 */
function bmn_service_chips( $id ) {
	$modes = bmn_service_modes();
	$mode  = (string) bmn_meta( $id, 'mode', 'online' );
	$cls   = array(
		'online' => 'kh-chip--online',
		'semi'   => 'kh-chip--onsite',
		'onsite' => 'kh-chip--onsite',
	);
	$html  = '<span class="kh-chip ' . esc_attr( $cls[ $mode ] ?? '' ) . '">' . esc_html( $modes[ $mode ] ?? '' ) . '</span>';
	$dur   = (string) bmn_meta( $id, 'duration' );
	if ( $dur ) {
		$html .= '<span class="kh-chip kh-chip--fast">' . esc_html( $dur ) . '</span>';
	}
	return $html;
}

/**
 * Service directory with category filter and live search.
 *
 * @return string
 */
function bmn_render_service_directory() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'bmn_service_cat',
			'hide_empty' => true,
			'orderby'    => 'term_order',
		)
	);
	$terms = is_wp_error( $terms ) ? array() : $terms;
	$posts = get_posts(
		array(
			'post_type'      => 'bmn_service',
			'posts_per_page' => 200,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'no_found_rows'  => true,
		)
	);
	$current = is_tax( 'bmn_service_cat' ) ? get_queried_object()->slug : '';
	$uid     = wp_unique_id( 'bmn-dir-' );

	$html  = '<div class="bmn-dir" data-bmn-dir id="' . esc_attr( $uid ) . '">';
	$html .= '<aside class="bmn-dir__side"><div class="kh-search-form bmn-dir__search"><label class="screen-reader-text" for="' . esc_attr( $uid ) . '-q">' . esc_html__( 'جست‌وجو در خدمات', 'boshrouyeh-core' ) . '</label>' . bmn_icon( 'search' ) . '<input id="' . esc_attr( $uid ) . '-q" type="search" data-bmn-dir-q placeholder="' . esc_attr( sprintf( /* translators: %s: number. */ __( 'جست‌وجو در %s خدمت…', 'boshrouyeh-core' ), bmn_fa_digits( count( $posts ) ) ) ) . '"></div>';
	$html .= '<ul class="bmn-dir__cats" role="list">';
	$html .= '<li><button type="button" data-bmn-cat="" aria-pressed="' . ( $current ? 'false' : 'true' ) . '"><span>' . bmn_icon( 'grid', 20 ) . esc_html__( 'همهٔ خدمات', 'boshrouyeh-core' ) . '</span><span class="bmn-count">' . esc_html( bmn_fa_digits( count( $posts ) ) ) . '</span></button></li>';
	foreach ( $terms as $t ) {
		$icon  = (string) get_term_meta( $t->term_id, '_bmn_icon', true );
		$html .= '<li><button type="button" data-bmn-cat="' . esc_attr( $t->slug ) . '" aria-pressed="' . ( $current === $t->slug ? 'true' : 'false' ) . '"><span>' . bmn_icon( $icon ? $icon : 'grid', 20 ) . esc_html( $t->name ) . '</span><span class="bmn-count">' . esc_html( bmn_fa_digits( $t->count ) ) . '</span></button></li>';
	}
	$html .= '</ul></aside><div class="bmn-dir__main"><ul class="bmn-dir__grid" role="list" aria-live="polite">';
	foreach ( $posts as $p ) {
		$cats  = wp_get_post_terms( $p->ID, 'bmn_service_cat', array( 'fields' => 'slugs' ) );
		$cats  = is_wp_error( $cats ) ? array() : $cats;
		$hide  = $current && ! in_array( $current, $cats, true );
		$text  = wp_strip_all_tags( get_the_title( $p ) . ' ' . get_the_excerpt( $p ) );
		$html .= '<li class="bmn-svc" data-cats="' . esc_attr( implode( ' ', $cats ) ) . '" data-text="' . esc_attr( $text ) . '"' . ( $hide ? ' hidden' : '' ) . '><a href="' . esc_url( bmn_service_url( $p->ID ) ) . '"><span class="kh-taqche">' . bmn_icon( (string) bmn_meta( $p->ID, 'icon', 'grid' ) ) . '</span><span class="bmn-svc__body"><strong>' . esc_html( get_the_title( $p ) ) . '</strong><small>' . esc_html( wp_trim_words( get_the_excerpt( $p ), 12 ) ) . '</small><span class="bmn-svc__chips">' . bmn_service_chips( $p->ID ) . '</span></span></a></li>';
	}
	$html .= '</ul><p class="kh-empty" data-bmn-dir-empty hidden>' . esc_html__( 'خدمتی با این عبارت پیدا نشد. از سامانه ۱۳۷ کمک بگیرید.', 'boshrouyeh-core' ) . '</p></div></div>';
	return $html;
}

/**
 * Service header.
 *
 * @return string
 */
function bmn_render_service_header() {
	$id = bmn_current_id();
	if ( 'bmn_service' !== get_post_type( $id ) ) {
		return '';
	}
	$modes = bmn_service_modes();
	$meta  = array();
	$meta[] = $modes[ (string) bmn_meta( $id, 'mode', 'online' ) ] ?? '';
	if ( bmn_meta( $id, 'duration' ) ) {
		/* translators: %s: duration. */
		$meta[] = sprintf( __( 'زمان انجام: %s', 'boshrouyeh-core' ), bmn_meta( $id, 'duration' ) );
	}
	if ( bmn_meta( $id, 'unit' ) ) {
		$meta[] = bmn_meta( $id, 'unit' );
	}
	return '<header class="bmn-svc-head"><span class="kh-taqche kh-taqche--action kh-taqche--lg">' . bmn_icon( (string) bmn_meta( $id, 'icon', 'grid' ), 30 ) . '</span><div><h1 class="kh-page-title">' . esc_html( get_the_title( $id ) ) . '</h1><p class="bmn-svc-head__meta">' . esc_html( implode( ' | ', array_filter( $meta ) ) ) . '</p></div></header>';
}

/**
 * Steps + documents + cost.
 *
 * @return string
 */
function bmn_render_service_details() {
	$id = bmn_current_id();
	if ( 'bmn_service' !== get_post_type( $id ) ) {
		return '';
	}
	$html  = '';
	$steps = bmn_lines( (string) bmn_meta( $id, 'steps' ) );
	if ( $steps ) {
		$html .= '<section class="kh-panel bmn-steps"><h2>' . esc_html__( 'مراحل', 'boshrouyeh-core' ) . '</h2><ol>';
		foreach ( $steps as $i => $s ) {
			$html .= '<li><span class="bmn-steps__n" aria-hidden="true">' . esc_html( bmn_fa_digits( $i + 1 ) ) . '</span><span>' . esc_html( $s ) . '</span></li>';
		}
		$html .= '</ol></section>';
	}
	$docs = bmn_lines( (string) bmn_meta( $id, 'documents' ) );
	if ( $docs ) {
		$html .= '<section class="kh-panel bmn-docs"><h2>' . esc_html__( 'مدارک لازم', 'boshrouyeh-core' ) . '</h2><ul class="wp-block-list is-style-kh-diamond">';
		foreach ( $docs as $d ) {
			$html .= '<li>' . esc_html( $d ) . '</li>';
		}
		$html .= '</ul></section>';
	}
	$facts = array_filter(
		array(
			__( 'هزینه', 'boshrouyeh-core' )       => (string) bmn_meta( $id, 'cost' ),
			__( 'واحد مسئول', 'boshrouyeh-core' )  => (string) bmn_meta( $id, 'unit' ),
			__( 'مبنای قانونی', 'boshrouyeh-core' ) => (string) bmn_meta( $id, 'legal_basis' ),
		)
	);
	if ( $facts ) {
		$html .= '<dl class="kh-panel bmn-facts">';
		foreach ( $facts as $k => $v ) {
			$html .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
		}
		$html .= '</dl>';
	}
	return $html;
}

/**
 * "Start service" box. E-Services plugin fills in internal flows via filter.
 *
 * @return string
 */
function bmn_render_service_start() {
	$id = bmn_current_id();
	if ( 'bmn_service' !== get_post_type( $id ) ) {
		return '';
	}
	$key = (string) bmn_meta( $id, 'eservice' );
	/**
	 * Inner HTML of the start box for an internal e-service.
	 *
	 * @param string $html Default ''.
	 * @param string $key  E-service key.
	 * @param int    $id   Service post id.
	 */
	$inner = (string) apply_filters( 'bmn_service_start_html', '', $key, $id );
	if ( '' === $inner ) {
		$ext = (string) bmn_meta( $id, 'external_url' );
		if ( $ext ) {
			$host  = (string) wp_parse_url( $ext, PHP_URL_HOST );
			$inner = '<p>' . esc_html__( 'این خدمت در سامانهٔ ملی/استانی ارائه می‌شود. مراحل را بالا بخوانید و سپس وارد سامانه شوید.', 'boshrouyeh-core' ) . '</p><a class="kh-btn kh-btn--action kh-btn--block" href="' . esc_url( $ext ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'ورود به سامانه', 'boshrouyeh-core' ) . ' ' . bmn_icon( 'external', 18 ) . '</a><p class="kh-help kh-ltr">' . esc_html( $host ) . '</p>';
		} else {
			$inner = '<p>' . esc_html__( 'برای این خدمت با مدارک لازم به واحد مربوط مراجعه کنید. برای کاهش انتظار، نوبت بگیرید.', 'boshrouyeh-core' ) . '</p>';
			$appt  = (int) bmn_option( 'page_appointment', 0 );
			$inner .= '<a class="kh-btn kh-btn--action kh-btn--block" href="' . esc_url( $appt ? get_permalink( $appt ) : home_url( '/contact/' ) ) . '">' . esc_html__( 'دریافت نوبت / تماس', 'boshrouyeh-core' ) . '</a>';
		}
	}
	return '<section class="bmn-start" aria-labelledby="bmn-start-title"><h2 id="bmn-start-title">' . esc_html__( 'شروع خدمت', 'boshrouyeh-core' ) . '</h2>' . $inner . '</section>';
}

/**
 * Service FAQ with FAQPage schema.
 *
 * @return string
 */
function bmn_render_service_faq() {
	$id = bmn_current_id();
	$qa = array();
	foreach ( bmn_lines( (string) bmn_meta( $id, 'faq' ) ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 === count( $parts ) && $parts[0] && $parts[1] ) {
			$qa[] = $parts;
		}
	}
	if ( ! $qa ) {
		return '';
	}
	$html   = '<section class="kh-panel bmn-faq"><h2>' . esc_html__( 'پرسش‌های پرتکرار', 'boshrouyeh-core' ) . '</h2>';
	$schema = array();
	foreach ( $qa as $item ) {
		$html    .= '<details><summary>' . esc_html( $item[0] ) . '</summary><p>' . esc_html( $item[1] ) . '</p></details>';
		$schema[] = array(
			'@type'          => 'Question',
			'name'           => $item[0],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $item[1] ),
		);
	}
	return $html . '</section>' . bmn_json_ld(
		array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $schema,
		)
	);
}

/* ------------------------------------------------------------------ */
/* Notices                                                             */
/* ------------------------------------------------------------------ */

/**
 * Notices list.
 *
 * @param array $attrs Attributes.
 * @return string
 */
function bmn_render_notices( $attrs ) {
	$count = max( 1, min( 50, (int) ( $attrs['count'] ?? 5 ) ) );
	$posts = get_posts(
		array(
			'post_type'      => 'bmn_notice',
			'posts_per_page' => $count,
			'meta_query'     => BMN_Post_Types::not_expired_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'no_found_rows'  => true,
		)
	);
	if ( ! $posts ) {
		return '<p class="kh-empty">' . esc_html__( 'اطلاعیهٔ فعالی وجود ندارد.', 'boshrouyeh-core' ) . '</p>';
	}
	$full = 'full' === ( $attrs['layout'] ?? '' );
	$html = '<ul class="kh-notice-list' . ( $full ? ' kh-notice-list--full kh-panel' : '' ) . '">';
	foreach ( $posts as $p ) {
		$urgent = 'urgent' === bmn_meta( $p->ID, 'urgency' );
		$html  .= '<li class="' . ( $urgent ? 'is-urgent' : '' ) . '"><a href="' . esc_url( get_permalink( $p ) ) . '"><span>' . esc_html( get_the_title( $p ) ) . ( $full ? '<time datetime="' . esc_attr( get_post_time( 'c', true, $p ) ) . '">' . esc_html( bmn_jdate( 'j F Y', (int) get_post_timestamp( $p ) ) ) . '</time>' : '' ) . '</span></a></li>';
	}
	$html .= '</ul>';
	if ( ! $full ) {
		$html .= '<p class="kh-more"><a href="' . esc_url( (string) get_post_type_archive_link( 'bmn_notice' ) ) . '">' . esc_html__( 'همهٔ اطلاعیه‌ها ←', 'boshrouyeh-core' ) . '</a></p>';
	}
	return $html;
}

/**
 * Notice details.
 *
 * @return string
 */
function bmn_render_notice_details() {
	$id = bmn_current_id();
	if ( 'bmn_notice' !== get_post_type( $id ) ) {
		return '';
	}
	$html = '<p class="kh-chips">';
	if ( 'urgent' === bmn_meta( $id, 'urgency' ) ) {
		$html .= '<span class="kh-chip kh-chip--need">' . esc_html__( 'فوری', 'boshrouyeh-core' ) . '</span>';
	}
	$html .= '<span class="kh-chip">' . esc_html( bmn_jdate( 'j F Y', (int) get_post_timestamp( $id ) ) ) . '</span>';
	$exp   = (int) bmn_meta( $id, 'expires', 0 );
	if ( $exp ) {
		/* translators: %s: date. */
		$html .= '<span class="kh-chip kh-chip--fast">' . esc_html( sprintf( __( 'معتبر تا %s', 'boshrouyeh-core' ), bmn_jdate( 'j F Y', $exp ) ) ) . '</span>';
	}
	$hoods = get_the_terms( $id, 'bmn_neighborhood' );
	if ( $hoods && ! is_wp_error( $hoods ) ) {
		foreach ( $hoods as $h ) {
			$html .= '<span class="kh-chip kh-chip--online">' . bmn_icon( 'pin', 14 ) . esc_html( $h->name ) . '</span>';
		}
	}
	return $html . '</p>';
}

/* ------------------------------------------------------------------ */
/* Tenders                                                             */
/* ------------------------------------------------------------------ */

/**
 * Tender status chip.
 *
 * @param int $id Post id.
 * @return string
 */
function bmn_tender_status_chip( $id ) {
	$status   = (string) bmn_meta( $id, 'status', 'open' );
	$deadline = (int) bmn_meta( $id, 'deadline', 0 );
	if ( 'open' === $status && $deadline && $deadline < time() ) {
		$status = 'evaluating';
	}
	$map = array(
		'open'       => array( __( 'در جریان', 'boshrouyeh-core' ), 'kh-chip--open' ),
		'evaluating' => array( __( 'در حال ارزیابی', 'boshrouyeh-core' ), 'kh-chip--review' ),
		'closed'     => array( __( 'پایان‌یافته', 'boshrouyeh-core' ), 'kh-chip--closed' ),
		'canceled'   => array( __( 'لغو شده', 'boshrouyeh-core' ), 'kh-chip--need' ),
	);
	$s = $map[ $status ] ?? $map['open'];
	return '<span class="kh-chip ' . esc_attr( $s[1] ) . '">' . esc_html( $s[0] ) . '</span>';
}

/**
 * Tender kinds.
 *
 * @return array
 */
function bmn_tender_kinds() {
	return array(
		'tender'  => __( 'مناقصه', 'boshrouyeh-core' ),
		'auction' => __( 'مزایده', 'boshrouyeh-core' ),
		'inquiry' => __( 'استعلام بها', 'boshrouyeh-core' ),
	);
}

/**
 * Tender list with tabs.
 *
 * @param array $attrs Attributes.
 * @return string
 */
function bmn_render_tender_list( $attrs ) {
	$posts = get_posts(
		array(
			'post_type'      => 'bmn_tender',
			'posts_per_page' => max( 1, min( 100, (int) ( $attrs['count'] ?? 30 ) ) ),
			'no_found_rows'  => true,
		)
	);
	$kinds = bmn_tender_kinds();
	$html  = '<div class="bmn-tenders" data-bmn-tabs><div class="bmn-tabs" role="tablist" aria-label="' . esc_attr__( 'نوع فراخوان', 'boshrouyeh-core' ) . '">';
	$html .= '<button type="button" role="tab" aria-selected="true" data-bmn-tab="">' . esc_html__( 'همه', 'boshrouyeh-core' ) . '</button>';
	foreach ( $kinds as $k => $label ) {
		$html .= '<button type="button" role="tab" aria-selected="false" data-bmn-tab="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</button>';
	}
	$html .= '</div>';
	if ( ! $posts ) {
		return $html . '<p class="kh-empty">' . esc_html__( 'فراخوانی منتشر نشده است.', 'boshrouyeh-core' ) . '</p></div>';
	}
	$html .= '<ul class="bmn-tender-grid" role="list">';
	foreach ( $posts as $p ) {
		$kind     = (string) bmn_meta( $p->ID, 'kind', 'tender' );
		$deadline = (int) bmn_meta( $p->ID, 'deadline', 0 );
		$html    .= '<li class="kh-card bmn-tender" data-kind="' . esc_attr( $kind ) . '"><a href="' . esc_url( get_permalink( $p ) ) . '"><div class="bmn-tender__top"><span class="kh-taqche">' . bmn_icon( 'tender' ) . '</span><span class="kh-chips"><span class="kh-chip">' . esc_html( $kinds[ $kind ] ?? '' ) . '</span>' . bmn_tender_status_chip( $p->ID ) . '</span></div><h3>' . esc_html( get_the_title( $p ) ) . '</h3>';
		if ( bmn_meta( $p->ID, 'number' ) ) {
			/* translators: %s: number. */
			$html .= '<p class="kh-help">' . esc_html( sprintf( __( 'شمارهٔ فراخوان: %s', 'boshrouyeh-core' ), bmn_fa_digits( (string) bmn_meta( $p->ID, 'number' ) ) ) ) . '</p>';
		}
		if ( $deadline ) {
			$html .= '<p class="bmn-tender__deadline">' . bmn_icon( 'clock', 16 ) . ' <span>' . esc_html__( 'مهلت:', 'boshrouyeh-core' ) . ' ' . esc_html( bmn_jdate( 'j F Y - H:i', $deadline ) ) . '</span></p>';
			if ( $deadline > time() ) {
				$html .= '<p class="bmn-countdown"><span data-kh-deadline="' . esc_attr( gmdate( 'c', $deadline ) ) . '"></span></p>';
			}
		}
		$html .= '</a></li>';
	}
	return $html . '</ul></div>';
}

/**
 * Tender details.
 *
 * @return string
 */
function bmn_render_tender_details() {
	$id = bmn_current_id();
	if ( 'bmn_tender' !== get_post_type( $id ) ) {
		return '';
	}
	$kinds    = bmn_tender_kinds();
	$deadline = (int) bmn_meta( $id, 'deadline', 0 );
	$opening  = (int) bmn_meta( $id, 'opening', 0 );
	$rows     = array_filter(
		array(
			__( 'نوع', 'boshrouyeh-core' )                => $kinds[ (string) bmn_meta( $id, 'kind', 'tender' ) ] ?? '',
			__( 'شمارهٔ فراخوان', 'boshrouyeh-core' )      => bmn_fa_digits( (string) bmn_meta( $id, 'number' ) ),
			__( 'مهلت ارسال پیشنهاد', 'boshrouyeh-core' )  => $deadline ? bmn_jdate( 'l j F Y - ساعت H:i', $deadline ) : '',
			__( 'بازگشایی پاکات', 'boshrouyeh-core' )      => $opening ? bmn_jdate( 'l j F Y - ساعت H:i', $opening ) : '',
			__( 'تضمین شرکت', 'boshrouyeh-core' )         => bmn_meta( $id, 'guarantee' ) ? bmn_fa_digits( (string) bmn_meta( $id, 'guarantee' ) ) . ' ' . __( 'تومان', 'boshrouyeh-core' ) : '',
			__( 'برنده', 'boshrouyeh-core' )              => (string) bmn_meta( $id, 'winner' ),
		)
	);
	$html = '<div class="bmn-tender-detail"><p class="kh-chips">' . bmn_tender_status_chip( $id ) . '</p>';
	if ( $deadline > time() ) {
		$html .= '<div class="bmn-countdown bmn-countdown--lg">' . bmn_icon( 'clock' ) . '<span>' . esc_html__( 'زمان باقی‌مانده:', 'boshrouyeh-core' ) . '</span> <strong data-kh-deadline="' . esc_attr( gmdate( 'c', $deadline ) ) . '"></strong></div>';
	}
	$html .= '<dl class="kh-panel bmn-facts">';
	foreach ( $rows as $k => $v ) {
		$html .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	$html .= '</dl>';
	$files = bmn_lines( (string) bmn_meta( $id, 'files' ) );
	if ( $files ) {
		$html .= '<section class="kh-panel"><h2>' . esc_html__( 'اسناد', 'boshrouyeh-core' ) . '</h2><ul class="bmn-files">';
		foreach ( $files as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$url   = esc_url( $parts[1] ?? $parts[0], array( 'http', 'https' ) );
			if ( ! $url ) {
				continue;
			}
			$html .= '<li><a href="' . $url . '" rel="noopener">' . bmn_icon( 'download', 18 ) . esc_html( $parts[0] ) . '</a></li>';
		}
		$html .= '</ul></section>';
	}
	$setad = (string) bmn_meta( $id, 'setadiran' );
	if ( $setad ) {
		$html .= '<p><a class="kh-btn kh-btn--action" href="' . esc_url( $setad ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'مشاهده در سامانهٔ ستاد', 'boshrouyeh-core' ) . ' ' . bmn_icon( 'external', 18 ) . '</a></p>';
	}
	return $html . '</div>';
}

/* ------------------------------------------------------------------ */
/* Tourism                                                             */
/* ------------------------------------------------------------------ */

/**
 * Place grid.
 *
 * @param array $attrs Attributes.
 * @return string
 */
function bmn_render_place_grid( $attrs ) {
	$posts = get_posts(
		array(
			'post_type'      => 'bmn_place',
			'posts_per_page' => max( 1, min( 60, (int) ( $attrs['count'] ?? 24 ) ) ),
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
			'no_found_rows'  => true,
		)
	);
	if ( ! $posts ) {
		return '<p class="kh-empty">' . esc_html__( 'هنوز جاذبه‌ای ثبت نشده است.', 'boshrouyeh-core' ) . '</p>';
	}
	$html = '<ul class="bmn-places" role="list">';
	foreach ( $posts as $p ) {
		$html .= '<li class="kh-card bmn-place"><a href="' . esc_url( get_permalink( $p ) ) . '"><span class="bmn-place__media">' . bmn_thumb( $p->ID ) . '</span><span class="kh-card__body"><strong class="kh-card__title">' . esc_html( get_the_title( $p ) ) . '</strong><small class="kh-card__excerpt">' . esc_html( wp_trim_words( get_the_excerpt( $p ), 16 ) ) . '</small>';
		if ( bmn_meta( $p->ID, 'era' ) ) {
			$html .= '<span class="kh-chip">' . esc_html( (string) bmn_meta( $p->ID, 'era' ) ) . '</span>';
		}
		$html .= '</span></a></li>';
	}
	return $html . '</ul>';
}

/**
 * Place details.
 *
 * @return string
 */
function bmn_render_place_details() {
	$id = bmn_current_id();
	if ( 'bmn_place' !== get_post_type( $id ) ) {
		return '';
	}
	$chips = array_filter( array_merge( array( (string) bmn_meta( $id, 'era' ) ), array_map( 'trim', explode( ',', str_replace( '،', ',', (string) bmn_meta( $id, 'tags' ) ) ) ) ) );
	$html  = '<div class="bmn-place-detail"><p class="kh-chips">';
	foreach ( $chips as $c ) {
		$html .= '<span class="kh-chip">' . esc_html( $c ) . '</span>';
	}
	$html .= '</p>';
	if ( has_excerpt( $id ) ) {
		$html .= '<p class="kh-page-lead">' . esc_html( get_the_excerpt( $id ) ) . '</p>';
	}
	$facts = array_filter(
		array(
			'clock'  => (string) bmn_meta( $id, 'hours' ),
			'ticket' => (string) bmn_meta( $id, 'ticket' ),
			'pin'    => (string) bmn_meta( $id, 'address' ),
		)
	);
	if ( $facts ) {
		$html .= '<ul class="bmn-place-facts">';
		foreach ( $facts as $icon => $v ) {
			$html .= '<li>' . bmn_icon( $icon, 18 ) . '<span>' . esc_html( $v ) . '</span></li>';
		}
		$html .= '</ul>';
	}
	foreach ( array( 'audio' => __( 'راهنمای صوتی', 'boshrouyeh-core' ), 'audio_en' => 'Audio guide' ) as $key => $label ) {
		$aid = (int) bmn_meta( $id, $key, 0 );
		$url = $aid ? wp_get_attachment_url( $aid ) : '';
		if ( $url ) {
			$html .= '<div class="bmn-audio">' . bmn_icon( 'audio', 20 ) . '<span>' . esc_html( $label ) . '</span><audio controls preload="none" src="' . esc_url( $url ) . '"></audio></div>';
		}
	}
	$lat = (float) bmn_meta( $id, 'lat', 0 );
	$lng = (float) bmn_meta( $id, 'lng', 0 );
	if ( $lat && $lng && abs( $lat ) <= 90 && abs( $lng ) <= 180 ) {
		$route = sprintf( 'https://www.openstreetmap.org/directions?to=%F%%2C%F', $lat, $lng );
		$html .= '<p class="bmn-place-actions"><a class="kh-btn kh-btn--action" href="' . esc_url( $route ) . '" target="_blank" rel="noopener noreferrer">' . bmn_icon( 'route', 18 ) . esc_html__( 'مسیریابی', 'boshrouyeh-core' ) . '</a> <a class="kh-btn kh-btn--outline" href="' . esc_url( sprintf( 'geo:%F,%F', $lat, $lng ), array( 'geo' ) ) . '">' . bmn_icon( 'pin', 18 ) . esc_html__( 'باز کردن در نقشه', 'boshrouyeh-core' ) . '</a></p>';
		$bbox  = sprintf( '%F%%2C%F%%2C%F%%2C%F', $lng - 0.006, $lat - 0.004, $lng + 0.006, $lat + 0.004 );
		$html .= '<iframe class="bmn-map" title="' . esc_attr__( 'نقشه', 'boshrouyeh-core' ) . '" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin" src="' . esc_url( 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . rawurlencode( $lat . ',' . $lng ) ) . '"></iframe>';
	}
	return $html . '</div>';
}

/* ------------------------------------------------------------------ */
/* Events                                                              */
/* ------------------------------------------------------------------ */

/**
 * Event list (upcoming first).
 *
 * @param array $attrs Attributes.
 * @return string
 */
function bmn_render_event_list( $attrs ) {
	$posts = get_posts(
		array(
			'post_type'      => 'bmn_event',
			'posts_per_page' => max( 1, min( 60, (int) ( $attrs['count'] ?? 20 ) ) ),
			'meta_key'       => '_bmn_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_bmn_start',
					'value'   => time() - DAY_IN_SECONDS,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
			'no_found_rows'  => true,
		)
	);
	if ( ! $posts ) {
		return '<p class="kh-empty">' . esc_html__( 'رویداد پیش‌رویی ثبت نشده است.', 'boshrouyeh-core' ) . '</p>';
	}
	$html = '<ul class="bmn-events" role="list">';
	foreach ( $posts as $p ) {
		$start = (int) bmn_meta( $p->ID, 'start', 0 );
		$html .= '<li class="kh-card bmn-event"><a href="' . esc_url( get_permalink( $p ) ) . '"><span class="bmn-event__date"><strong>' . esc_html( bmn_jdate( 'j', $start ) ) . '</strong><small>' . esc_html( bmn_jdate( 'F', $start ) ) . '</small></span><span><strong>' . esc_html( get_the_title( $p ) ) . '</strong><small>' . esc_html( bmn_jdate( 'l - H:i', $start ) . ( bmn_meta( $p->ID, 'venue' ) ? ' | ' . bmn_meta( $p->ID, 'venue' ) : '' ) ) . '</small></span></a></li>';
	}
	return $html . '</ul>';
}

/**
 * Event details + Event schema.
 *
 * @return string
 */
function bmn_render_event_details() {
	$id = bmn_current_id();
	if ( 'bmn_event' !== get_post_type( $id ) ) {
		return '';
	}
	$start = (int) bmn_meta( $id, 'start', 0 );
	$end   = (int) bmn_meta( $id, 'end', 0 );
	$venue = (string) bmn_meta( $id, 'venue' );
	$html  = '<ul class="bmn-place-facts">';
	if ( $start ) {
		$html .= '<li>' . bmn_icon( 'calendar', 18 ) . '<span>' . esc_html( bmn_jdate( 'l j F Y - ساعت H:i', $start ) . ( $end ? ' تا ' . bmn_jdate( 'H:i', $end ) : '' ) ) . '</span></li>';
	}
	if ( $venue ) {
		$html .= '<li>' . bmn_icon( 'pin', 18 ) . '<span>' . esc_html( $venue ) . '</span></li>';
	}
	if ( bmn_meta( $id, 'capacity' ) ) {
		/* translators: %s: capacity. */
		$html .= '<li>' . bmn_icon( 'users', 18 ) . '<span>' . esc_html( sprintf( __( 'ظرفیت: %s نفر', 'boshrouyeh-core' ), bmn_fa_digits( (int) bmn_meta( $id, 'capacity' ) ) ) ) . '</span></li>';
	}
	$html .= '</ul>';
	if ( bmn_meta( $id, 'register' ) ) {
		$html .= '<p><a class="kh-btn kh-btn--action" href="' . esc_url( (string) bmn_meta( $id, 'register' ) ) . '" rel="noopener">' . esc_html__( 'ثبت‌نام / رزرو', 'boshrouyeh-core' ) . '</a></p>';
	}
	if ( $start ) {
		$html .= bmn_json_ld(
			array_filter(
				array(
					'@context'  => 'https://schema.org',
					'@type'     => 'Event',
					'name'      => wp_strip_all_tags( get_the_title( $id ) ),
					'startDate' => gmdate( 'c', $start ),
					'endDate'   => $end ? gmdate( 'c', $end ) : null,
					'location'  => array(
						'@type'   => 'Place',
						'name'    => $venue ? $venue : 'بشرویه',
						'address' => 'بشرویه، خراسان جنوبی، ایران',
					),
					'organizer' => array(
						'@type' => 'GovernmentOrganization',
						'name'  => get_bloginfo( 'name' ),
						'url'   => home_url( '/' ),
					),
				)
			)
		);
	}
	return $html;
}

/* ------------------------------------------------------------------ */
/* Transparency                                                        */
/* ------------------------------------------------------------------ */

/**
 * Transparency documents table with type filter.
 *
 * @return string
 */
function bmn_render_transparency_list() {
	$posts = get_posts(
		array(
			'post_type'      => 'bmn_transparency',
			'posts_per_page' => 200,
			'no_found_rows'  => true,
		)
	);
	$types = get_terms( array( 'taxonomy' => 'bmn_doc_type', 'hide_empty' => true ) );
	$types = is_wp_error( $types ) ? array() : $types;
	$html  = '<div class="bmn-transparency" data-bmn-tabs><div class="bmn-tabs" role="tablist">';
	$html .= '<button type="button" role="tab" aria-selected="true" data-bmn-tab="">' . esc_html__( 'همه', 'boshrouyeh-core' ) . '</button>';
	foreach ( $types as $t ) {
		$html .= '<button type="button" role="tab" aria-selected="false" data-bmn-tab="' . esc_attr( $t->slug ) . '">' . esc_html( $t->name ) . '</button>';
	}
	$html .= '</div>';
	if ( ! $posts ) {
		return $html . '<p class="kh-empty">' . esc_html__( 'سندی منتشر نشده است.', 'boshrouyeh-core' ) . '</p></div>';
	}
	$html .= '<div class="bmn-table-wrap"><table class="bmn-table"><thead><tr><th scope="col">' . esc_html__( 'عنوان', 'boshrouyeh-core' ) . '</th><th scope="col">' . esc_html__( 'نوع', 'boshrouyeh-core' ) . '</th><th scope="col">' . esc_html__( 'سال', 'boshrouyeh-core' ) . '</th><th scope="col">' . esc_html__( 'مبلغ (تومان)', 'boshrouyeh-core' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'دانلود', 'boshrouyeh-core' ) . '</span></th></tr></thead><tbody>';
	foreach ( $posts as $p ) {
		$terms = wp_get_post_terms( $p->ID, 'bmn_doc_type' );
		$terms = is_wp_error( $terms ) ? array() : $terms;
		$fid   = (int) bmn_meta( $p->ID, 'file', 0 );
		$furl  = $fid ? wp_get_attachment_url( $fid ) : '';
		$html .= '<tr data-kind="' . esc_attr( implode( ' ', wp_list_pluck( $terms, 'slug' ) ) ) . '"><td><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></td><td>' . esc_html( implode( '، ', wp_list_pluck( $terms, 'name' ) ) ) . '</td><td>' . esc_html( bmn_fa_digits( (string) bmn_meta( $p->ID, 'year' ) ) ) . '</td><td>' . esc_html( bmn_fa_digits( (string) bmn_meta( $p->ID, 'amount' ) ) ) . '</td><td>' . ( $furl ? '<a class="kh-icon-btn" href="' . esc_url( $furl ) . '" aria-label="' . esc_attr__( 'دانلود', 'boshrouyeh-core' ) . '">' . bmn_icon( 'download', 18 ) . '</a>' : '' ) . '</td></tr>';
	}
	return $html . '</tbody></table></div></div>';
}

/**
 * Transparency document details.
 *
 * @return string
 */
function bmn_render_transparency_details() {
	$id = bmn_current_id();
	if ( 'bmn_transparency' !== get_post_type( $id ) ) {
		return '';
	}
	$rows = array_filter(
		array(
			__( 'سال', 'boshrouyeh-core' )            => bmn_fa_digits( (string) bmn_meta( $id, 'year' ) ),
			__( 'مبلغ (تومان)', 'boshrouyeh-core' )   => bmn_fa_digits( (string) bmn_meta( $id, 'amount' ) ),
			__( 'طرف قرارداد/جلسه', 'boshrouyeh-core' ) => (string) bmn_meta( $id, 'party' ),
			__( 'شماره سند', 'boshrouyeh-core' )      => bmn_fa_digits( (string) bmn_meta( $id, 'doc_no' ) ),
		)
	);
	$html = '';
	if ( $rows ) {
		$html .= '<dl class="kh-panel bmn-facts">';
		foreach ( $rows as $k => $v ) {
			$html .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
		}
		$html .= '</dl>';
	}
	$fid = (int) bmn_meta( $id, 'file', 0 );
	$url = $fid ? wp_get_attachment_url( $fid ) : '';
	if ( $url ) {
		$html .= '<p><a class="kh-btn kh-btn--action" href="' . esc_url( $url ) . '">' . bmn_icon( 'download', 18 ) . esc_html__( 'دانلود سند', 'boshrouyeh-core' ) . '</a></p>';
	}
	return $html;
}

/* ------------------------------------------------------------------ */
/* City services                                                       */
/* ------------------------------------------------------------------ */

/**
 * Waste collection schedule per neighborhood.
 *
 * @return string
 */
function bmn_render_waste_schedule() {
	$terms = get_terms( array( 'taxonomy' => 'bmn_neighborhood', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '<p class="kh-empty">' . esc_html__( 'محله‌ها هنوز تعریف نشده‌اند.', 'boshrouyeh-core' ) . '</p>';
	}
	$uid  = wp_unique_id( 'bmn-waste-' );
	$data = array();
	foreach ( $terms as $t ) {
		$data[ $t->slug ] = array(
			'days'    => array_map( 'intval', array_filter( explode( ',', (string) get_term_meta( $t->term_id, '_bmn_waste_days', true ) ), 'strlen' ) ),
			'recycle' => array_map( 'intval', array_filter( explode( ',', (string) get_term_meta( $t->term_id, '_bmn_recycle_days', true ) ), 'strlen' ) ),
			'time'    => bmn_fa_digits( (string) get_term_meta( $t->term_id, '_bmn_waste_time', true ) ),
		);
	}
	$html = '<div class="bmn-waste kh-panel" data-bmn-waste="' . esc_attr( wp_json_encode( $data ) ) . '"><div class="kh-field"><label for="' . esc_attr( $uid ) . '">' . esc_html__( 'محلهٔ خود را انتخاب کنید', 'boshrouyeh-core' ) . '</label><select class="kh-input" id="' . esc_attr( $uid ) . '" data-bmn-waste-select><option value="">—</option>';
	foreach ( $terms as $t ) {
		$html .= '<option value="' . esc_attr( $t->slug ) . '">' . esc_html( $t->name ) . '</option>';
	}
	$html .= '</select></div><div class="bmn-week" data-bmn-waste-week aria-live="polite"></div><p class="kh-help">' . bmn_icon( 'waste', 16 ) . ' ' . esc_html__( 'فیروزه‌ای: پسماند عادی · کاهگلی: پسماند خشک. لطفاً کیسه را پیش از ساعت اعلام‌شده بیرون بگذارید.', 'boshrouyeh-core' ) . '</p></div>';
	return $html;
}

/**
 * Deceased search form (results fetched from the rate-limited REST endpoint).
 *
 * @return string
 */
function bmn_render_deceased_search() {
	$uid = wp_unique_id( 'bmn-dec-' );
	return '<div class="bmn-deceased kh-panel"><form class="kh-search-form" data-bmn-deceased role="search"><label class="screen-reader-text" for="' . esc_attr( $uid ) . '">' . esc_html__( 'نام و نام خانوادگی متوفی', 'boshrouyeh-core' ) . '</label>' . bmn_icon( 'search' ) . '<input id="' . esc_attr( $uid ) . '" type="search" name="q" minlength="3" maxlength="60" required placeholder="' . esc_attr__( 'نام و نام خانوادگی متوفی…', 'boshrouyeh-core' ) . '"><button type="submit" class="kh-btn kh-btn--action">' . esc_html__( 'جست‌وجو', 'boshrouyeh-core' ) . '</button></form><div class="bmn-deceased__results" aria-live="polite"></div><p class="kh-help">' . esc_html__( 'برای حفظ حریم خانواده‌ها، فقط نام، نام پدر، تاریخ وفات و محل دفن نمایش داده می‌شود.', 'boshrouyeh-core' ) . '</p></div>';
}

/**
 * Contact information card.
 *
 * @return string
 */
function bmn_render_contact_info() {
	$rows = array_filter(
		array(
			'pin'   => (string) bmn_option( 'address' ),
			'phone' => (string) bmn_option( 'phone' ),
			'mail'  => (string) bmn_option( 'email' ),
			'clock' => (string) bmn_option( 'work_hours' ),
		)
	);
	$html = '<div class="bmn-contact"><ul class="bmn-contact__list kh-panel">';
	foreach ( $rows as $icon => $val ) {
		$text = esc_html( $val );
		if ( 'phone' === $icon ) {
			$text = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', bmn_en_digits( $val ) ) ) . '">' . esc_html( bmn_fa_digits( $val ) ) . '</a>';
		} elseif ( 'mail' === $icon ) {
			$text = '<a href="mailto:' . esc_attr( antispambot( $val ) ) . '">' . esc_html( antispambot( $val ) ) . '</a>';
		}
		$html .= '<li><span class="kh-taqche">' . bmn_icon( $icon ) . '</span><span>' . $text . '</span></li>';
	}
	$html .= '<li><span class="kh-taqche">' . bmn_icon( 'fire' ) . '</span><span>' . esc_html__( 'آتش‌نشانی:', 'boshrouyeh-core' ) . ' <a href="tel:125">۱۲۵</a> · ' . esc_html__( 'سامانه شهرداری:', 'boshrouyeh-core' ) . ' <a href="tel:137">۱۳۷</a></span></li>';
	$html .= '</ul>';
	$lat = (float) bmn_option( 'lat', 0 );
	$lng = (float) bmn_option( 'lng', 0 );
	if ( $lat && $lng ) {
		$bbox  = sprintf( '%F%%2C%F%%2C%F%%2C%F', $lng - 0.004, $lat - 0.003, $lng + 0.004, $lat + 0.003 );
		$html .= '<iframe class="bmn-map" title="' . esc_attr__( 'نقشهٔ ساختمان شهرداری', 'boshrouyeh-core' ) . '" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin" src="' . esc_url( 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . rawurlencode( $lat . ',' . $lng ) ) . '"></iframe>';
	}
	return $html . '</div>';
}
