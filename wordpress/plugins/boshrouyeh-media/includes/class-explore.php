<?php
/**
 * Explore: unified news + photo reports + videos feed for the website and the app.
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Explore.
 */
final class BMNM_Explore {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_shortcode( 'bmn_explore', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'bmn_media_strip', array( __CLASS__, 'strip' ) );
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route(
			'bmn/v1',
			'/explore',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'feed' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'kind'     => array( 'type' => 'string', 'default' => 'all', 'enum' => array( 'all', 'news', 'report', 'video' ) ),
					'q'        => array( 'type' => 'string', 'default' => '', 'maxLength' => 60, 'sanitize_callback' => 'sanitize_text_field' ),
					'cat'      => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0 ),
					'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 300 ),
					'per_page' => array( 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 30 ),
				),
			)
		);
		register_rest_route(
			'bmn/v1',
			'/explore/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'detail' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ) ),
			)
		);
		register_rest_route(
			'bmn/v1',
			'/explore/meta',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'meta' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Build the query args.
	 *
	 * @param string $kind     Kind.
	 * @param string $q        Search.
	 * @param int    $cat      Category.
	 * @param int    $page     Page.
	 * @param int    $per_page Per page.
	 * @return array
	 */
	public static function query_args( $kind, $q = '', $cat = 0, $page = 1, $per_page = 12 ) {
		$args = array(
			'post_type'      => 'news' === $kind ? 'post' : ( 'report' === $kind ? BMNM_Content::PT : bmnm_types() ),
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'has_password'   => false,
		);
		if ( 'video' === $kind ) {
			$args['meta_query'] = array( array( 'key' => '_bmn_aparat', 'value' => '', 'compare' => '!=' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		if ( $q ) {
			$args['s'] = $q;
		}
		if ( $cat ) {
			$args['cat'] = $cat;
		}
		return $args;
	}

	/**
	 * Card DTO.
	 *
	 * @param WP_Post $p Post.
	 * @return array
	 */
	public static function dto( $p ) {
		$imgs  = bmnm_images( $p->ID );
		$hash  = bmnm_aparat_hash( (string) get_post_meta( $p->ID, '_bmn_aparat', true ) );
		$image = $imgs ? (string) wp_get_attachment_image_url( $imgs[0], 'medium_large' ) : ( function_exists( 'bmn_illustration_url' ) ? bmn_illustration_url( $p->ID ) : '' );
		$kind  = $hash ? 'video' : ( BMNM_Content::PT === $p->post_type || count( $imgs ) > 2 ? 'report' : 'news' );
		$cats  = get_the_category( $p->ID );
		return array(
			'id'        => $p->ID,
			'kind'      => $kind,
			'kind_label' => array( 'video' => 'ویدیو', 'report' => 'گزارش تصویری', 'news' => 'خبر' )[ $kind ],
			'title'     => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'excerpt'   => html_entity_decode( wp_strip_all_tags( get_the_excerpt( $p ) ), ENT_QUOTES, 'UTF-8' ),
			'date'      => get_post_time( 'c', true, $p ),
			'date_fa'   => bmnm_jdate( 'j F Y', (int) get_post_timestamp( $p ) ),
			'image'     => $image,
			'photos'    => count( $imgs ),
			'has_video' => (bool) $hash,
			'category'  => $cats ? $cats[0]->name : '',
			'url'       => get_permalink( $p ),
		);
	}

	/**
	 * GET /explore
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function feed( $req ) {
		if ( $req['q'] && ! bmnm_rate_limit( 'explore_' . bmnm_ip(), 60, MINUTE_IN_SECONDS ) ) {
			return bmnm_error( 'rate', 'درخواست‌ها زیاد است.', 429 );
		}
		$q   = new WP_Query( self::query_args( (string) $req['kind'], (string) $req['q'], (int) $req['cat'], (int) $req['page'], (int) $req['per_page'] ) );
		$res = rest_ensure_response( array_map( array( __CLASS__, 'dto' ), $q->posts ) );
		$res->header( 'X-WP-Total', (string) (int) $q->found_posts );
		$res->header( 'X-WP-TotalPages', (string) (int) $q->max_num_pages );
		$res->header( 'Cache-Control', 'public, max-age=120' );
		return $res;
	}

	/**
	 * GET /explore/{id}
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function detail( $req ) {
		$p = get_post( (int) $req['id'] );
		if ( ! $p || ! in_array( $p->post_type, bmnm_types(), true ) || 'publish' !== $p->post_status || post_password_required( $p ) ) {
			return bmnm_error( 'not_found', 'یافت نشد.', 404 );
		}
		$hash    = bmnm_aparat_hash( (string) get_post_meta( $p->ID, '_bmn_aparat', true ) );
		$gallery = array();
		foreach ( bmnm_images( $p->ID ) as $att ) {
			$gallery[] = array(
				'full'    => (string) wp_get_attachment_image_url( $att, 'large' ),
				'thumb'   => (string) wp_get_attachment_image_url( $att, 'medium' ),
				'caption' => (string) wp_get_attachment_caption( $att ),
			);
		}
		$share = bmnm_caption( $p );
		$data  = self::dto( $p ) + array(
			'content'     => apply_filters( 'the_content', $p->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'gallery'     => $gallery,
			'aparat'      => $hash,
			'aparat_url'  => $hash ? 'https://www.aparat.com/v/' . $hash : '',
			'embed_url'   => $hash ? bmnm_aparat_embed( $hash ) : '',
			'share_text'  => $share,
			'share_links' => bmnm_share_links( get_permalink( $p ), html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ) ),
			'related'     => array_map( array( __CLASS__, 'dto' ), get_posts( array( 'post_type' => bmnm_types(), 'posts_per_page' => 4, 'post__not_in' => array( $p->ID ), 'category__in' => wp_get_post_categories( $p->ID ), 'no_found_rows' => true ) ) ),
		);
		$res   = rest_ensure_response( $data );
		$res->header( 'Cache-Control', 'public, max-age=300' );
		return $res;
	}

	/**
	 * GET /explore/meta — categories + counts.
	 *
	 * @return WP_REST_Response
	 */
	public static function meta() {
		$cats = get_categories( array( 'hide_empty' => true, 'number' => 20 ) );
		return rest_ensure_response(
			array(
				'kinds'      => array(
					array( 'key' => 'all', 'label' => 'همه' ),
					array( 'key' => 'news', 'label' => 'اخبار' ),
					array( 'key' => 'report', 'label' => 'گزارش تصویری' ),
					array( 'key' => 'video', 'label' => 'ویدیو' ),
				),
				'categories' => array_map( static fn( $c ) => array( 'id' => $c->term_id, 'name' => $c->name ), $cats ),
			)
		);
	}

	/**
	 * Card HTML (server side; same markup the JS builds).
	 *
	 * @param array $d DTO.
	 * @return string
	 */
	public static function card( $d ) {
		$badge = '<span class="bmnm-kind k-' . esc_attr( $d['kind'] ) . '">' . esc_html( $d['kind_label'] ) . '</span>';
		$meta  = $d['photos'] > 1 ? '<span class="bmnm-count">📷 ' . esc_html( bmnm_fa( $d['photos'] ) ) . '</span>' : '';
		$play  = $d['has_video'] ? '<span class="bmnm-play" aria-hidden="true"></span>' : '';
		return '<article class="bmnm-card k-' . esc_attr( $d['kind'] ) . '"><a href="' . esc_url( $d['url'] ) . '"><figure><img src="' . esc_url( $d['image'] ) . '" alt="" loading="lazy">' . $play . $badge . $meta . '</figure><div class="bmnm-body"><h3>' . esc_html( $d['title'] ) . '</h3><p>' . esc_html( wp_trim_words( $d['excerpt'], 18, '…' ) ) . '</p><time>' . esc_html( $d['date_fa'] ) . '</time></div></a></article>';
	}

	/**
	 * [bmn_explore kind="all" per_page="12"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$a     = shortcode_atts( array( 'kind' => 'all', 'per_page' => 12, 'title' => 'اکسپلور اخبار و گزارش‌ها' ), $atts );
		$kind  = in_array( $a['kind'], array( 'all', 'news', 'report', 'video' ), true ) ? $a['kind'] : 'all';
		$per   = min( 30, max( 4, (int) $a['per_page'] ) );
		$q     = new WP_Query( self::query_args( $kind, '', 0, 1, $per ) );
		$cards = '';
		foreach ( $q->posts as $p ) {
			$cards .= self::card( self::dto( $p ) );
		}
		$tabs = '';
		foreach ( array( 'all' => 'همه', 'news' => 'اخبار', 'report' => 'گزارش تصویری', 'video' => 'ویدیو' ) as $k => $l ) {
			$tabs .= '<button type="button" role="tab" class="bmnm-tab" data-kind="' . esc_attr( $k ) . '" aria-selected="' . ( $k === $kind ? 'true' : 'false' ) . '">' . esc_html( $l ) . '</button>';
		}
		$cats = '<option value="0">همهٔ موضوع‌ها</option>';
		foreach ( get_categories( array( 'hide_empty' => true, 'number' => 30 ) ) as $c ) {
			$cats .= '<option value="' . (int) $c->term_id . '">' . esc_html( $c->name ) . '</option>';
		}
		$more = $q->max_num_pages > 1 ? '' : ' hidden';
		return '<section class="bmnm-explore alignwide" data-kind="' . esc_attr( $kind ) . '" data-per="' . (int) $per . '">'
			. '<header class="bmnm-ex-head"><h2>' . esc_html( $a['title'] ) . '</h2>'
			. '<div class="bmnm-ex-tools"><div class="bmnm-tabs" role="tablist">' . $tabs . '</div>'
			. '<select class="bmnm-cat" aria-label="موضوع">' . $cats . '</select>'
			. '<input type="search" class="bmnm-q" placeholder="جست‌وجو در اخبار و گزارش‌ها…" aria-label="جست‌وجو"></div></header>'
			. '<div class="bmnm-feed" aria-live="polite">' . ( $cards ? $cards : '<p class="bmnm-empty">موردی یافت نشد.</p>' ) . '</div>'
			. '<div class="bmnm-more-wrap"><button type="button" class="bmnm-more"' . $more . '>نمایش بیشتر</button></div></section>';
	}

	/**
	 * [bmn_media_strip kind="report" count="6"] — horizontal strip for the home page.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function strip( $atts ) {
		$a    = shortcode_atts( array( 'kind' => 'report', 'count' => 6, 'title' => 'گزارش‌های تصویری' ), $atts );
		$kind = in_array( $a['kind'], array( 'all', 'news', 'report', 'video' ), true ) ? $a['kind'] : 'report';
		$q    = new WP_Query( self::query_args( $kind, '', 0, 1, min( 12, max( 3, (int) $a['count'] ) ) ) );
		if ( ! $q->posts ) {
			return '';
		}
		$page  = (int) get_option( 'bmnm_explore_page' );
		$out   = '<section class="bmnm-strip"><header><h2>' . esc_html( $a['title'] ) . '</h2>' . ( $page ? '<a href="' . esc_url( add_query_arg( 'kind', $kind, get_permalink( $page ) ) ) . '">همه ←</a>' : '' ) . '</header><div class="bmnm-strip-row">';
		foreach ( $q->posts as $p ) {
			$out .= self::card( self::dto( $p ) );
		}
		return $out . '</div></section>';
	}
}
