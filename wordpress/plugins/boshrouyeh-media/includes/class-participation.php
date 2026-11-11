<?php
/**
 * Citizen participation: polls and participatory budgeting (one vote per citizen account).
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Participation.
 */
final class BMNM_Participation {

	const PT = 'bmn_poll';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'boxes' ) );
		add_action( 'save_post_' . self::PT, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_shortcode( 'bmn_poll', array( __CLASS__, 'sc_poll' ) );
		add_shortcode( 'bmn_polls', array( __CLASS__, 'sc_polls' ) );
		add_filter( 'the_content', array( __CLASS__, 'single' ), 16 );
	}

	/**
	 * Votes table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bmnm_votes';
	}

	/**
	 * Install table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		dbDelta(
			"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			poll_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			choices varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY one_vote (poll_id, user_id)
			) " . $wpdb->get_charset_collate() . ';'
		);
	}

	/**
	 * CPT.
	 */
	public static function register() {
		register_post_type(
			self::PT,
			array(
				'labels'          => array(
					'name'          => 'نظرسنجی و بودجهٔ مشارکتی',
					'singular_name' => 'نظرسنجی',
					'add_new_item'  => 'نظرسنجی / طرح مشارکتی تازه',
					'edit_item'     => 'ویرایش نظرسنجی',
					'menu_name'     => 'مشارکت شهروندی',
				),
				'public'          => true,
				'show_in_rest'    => true,
				'has_archive'     => 'participate',
				'rewrite'         => array( 'slug' => 'poll', 'with_front' => false ),
				'menu_icon'       => 'dashicons-chart-pie',
				'menu_position'   => 7,
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'capability_type' => array( 'bmn_poll', 'bmn_polls' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Poll config.
	 *
	 * @param int $id Poll.
	 * @return array
	 */
	public static function cfg( $id ) {
		$opts = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $id, '_bmnm_options', true ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$p      = array_map( 'trim', explode( '|', $line, 3 ) );
			$opts[] = array(
				'title' => $p[0],
				'cost'  => isset( $p[1] ) ? (int) preg_replace( '/\D/', '', function_exists( 'bmn_en_digits' ) ? bmn_en_digits( $p[1] ) : $p[1] ) : 0,
				'desc'  => $p[2] ?? '',
			);
		}
		$end = (int) get_post_meta( $id, '_bmnm_end', true );
		return array(
			'kind'   => 'budget' === get_post_meta( $id, '_bmnm_kind', true ) ? 'budget' : 'poll',
			'multi'  => (bool) get_post_meta( $id, '_bmnm_multi', true ),
			'budget' => (int) get_post_meta( $id, '_bmnm_budget', true ),
			'end'    => $end,
			'open'   => 'publish' === get_post_status( $id ) && ( ! $end || $end > time() ),
			'show'   => (string) ( get_post_meta( $id, '_bmnm_show', true ) ? get_post_meta( $id, '_bmnm_show', true ) : 'after' ),
			'hood'   => (string) get_post_meta( $id, '_bmnm_hood', true ),
			'opts'   => $opts,
		);
	}

	/**
	 * Results.
	 *
	 * @param int $id Poll.
	 * @return array{total:int,counts:int[]}
	 */
	public static function results( $id ) {
		global $wpdb;
		$c    = self::cfg( $id );
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT choices FROM %i WHERE poll_id = %d', self::table(), $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$cnt  = array_fill( 0, count( $c['opts'] ), 0 );
		foreach ( (array) $rows as $r ) {
			foreach ( array_filter( explode( ',', (string) $r ), 'strlen' ) as $i ) {
				if ( isset( $cnt[ (int) $i ] ) ) {
					++$cnt[ (int) $i ];
				}
			}
		}
		return array( 'total' => count( (array) $rows ), 'counts' => $cnt );
	}

	/**
	 * Current user's vote or null.
	 *
	 * @param int $id Poll.
	 * @return int[]|null
	 */
	public static function my_vote( $id ) {
		global $wpdb;
		$uid = get_current_user_id();
		if ( ! $uid ) {
			return null;
		}
		$v = $wpdb->get_var( $wpdb->prepare( 'SELECT choices FROM %i WHERE poll_id = %d AND user_id = %d', self::table(), $id, $uid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return null === $v ? null : array_map( 'intval', array_filter( explode( ',', (string) $v ), 'strlen' ) );
	}

	/**
	 * DTO.
	 *
	 * @param WP_Post $p    Poll.
	 * @param bool    $full Include results/options.
	 * @return array
	 */
	public static function dto( $p, $full = false ) {
		$c   = self::cfg( $p->ID );
		$dto = array(
			'id'      => $p->ID,
			'title'   => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
			'excerpt' => html_entity_decode( wp_strip_all_tags( get_the_excerpt( $p ) ), ENT_QUOTES, 'UTF-8' ),
			'kind'    => $c['kind'],
			'open'    => $c['open'],
			'end_fa'  => $c['end'] ? bmnm_jdate( 'j F Y', $c['end'] ) : '',
			'image'   => (string) get_the_post_thumbnail_url( $p, 'medium_large' ),
			'url'     => get_permalink( $p ),
		);
		if ( $full ) {
			$mine = self::my_vote( $p->ID );
			$show = 'always' === $c['show'] || ( 'after' === $c['show'] && ( null !== $mine || ! $c['open'] ) ) || ( 'end' === $c['show'] && ! $c['open'] ) || current_user_can( 'edit_post', $p->ID );
			$res  = $show ? self::results( $p->ID ) : null;
			$dto += array(
				'content'  => apply_filters( 'the_content', $p->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				'multi'    => $c['multi'],
				'budget'   => $c['budget'],
				'options'  => array_map(
					static fn( $o, $i ) => $o + array( 'index' => $i, 'votes' => $res ? $res['counts'][ $i ] : null ),
					$c['opts'],
					array_keys( $c['opts'] )
				),
				'total'    => $res ? $res['total'] : null,
				'my_vote'  => $mine,
				'can_vote' => $c['open'] && is_user_logged_in() && null === $mine,
			);
		}
		return $dto;
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route( 'bmn/v1', '/polls', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_list' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'bmn/v1', '/polls/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_get' ), 'permission_callback' => '__return_true' ) );
		register_rest_route(
			'bmn/v1',
			'/polls/(?P<id>\d+)/vote',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'r_vote' ),
				'permission_callback' => static fn() => is_user_logged_in() ? true : bmnm_error( 'auth', 'برای شرکت ابتدا وارد شوید.', 401 ),
				'args'                => array( 'choices' => array( 'type' => 'array', 'required' => true, 'items' => array( 'type' => 'integer', 'minimum' => 0 ), 'maxItems' => 50 ) ),
			)
		);
	}

	/**
	 * GET /polls
	 *
	 * @return WP_REST_Response
	 */
	public static function r_list() {
		$posts = get_posts( array( 'post_type' => self::PT, 'posts_per_page' => 30, 'no_found_rows' => true ) );
		return rest_ensure_response( array_map( array( __CLASS__, 'dto' ), $posts ) );
	}

	/**
	 * GET /polls/{id}
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function r_get( $req ) {
		$p = get_post( (int) $req['id'] );
		if ( ! $p || self::PT !== $p->post_type || 'publish' !== $p->post_status ) {
			return bmnm_error( 'not_found', 'یافت نشد.', 404 );
		}
		$res = rest_ensure_response( self::dto( $p, true ) );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/**
	 * POST /polls/{id}/vote
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function r_vote( $req ) {
		global $wpdb;
		$id  = (int) $req['id'];
		$p   = get_post( $id );
		$uid = get_current_user_id();
		if ( ! $p || self::PT !== $p->post_type ) {
			return bmnm_error( 'not_found', 'یافت نشد.', 404 );
		}
		if ( ! bmnm_rate_limit( 'vote_' . $uid, 20, HOUR_IN_SECONDS ) ) {
			return bmnm_error( 'rate', 'درخواست‌ها زیاد است.', 429 );
		}
		$c = self::cfg( $id );
		if ( ! $c['open'] ) {
			return bmnm_error( 'closed', 'مهلت شرکت به پایان رسیده است.', 403 );
		}
		if ( $c['hood'] && (string) get_user_meta( $uid, '_bmn_neighborhood', true ) !== $c['hood'] ) {
			return bmnm_error( 'hood', 'این نظرسنجی ویژهٔ ساکنان محلهٔ مشخص‌شده است؛ محلهٔ خود را در پیشخوان ثبت کنید.', 403 );
		}
		$ch = array_values( array_unique( array_map( 'intval', (array) $req['choices'] ) ) );
		$ch = array_values( array_filter( $ch, static fn( $i ) => isset( $c['opts'][ $i ] ) ) );
		if ( ! $ch ) {
			return bmnm_error( 'empty', 'گزینه‌ای انتخاب نشده است.' );
		}
		if ( 'poll' === $c['kind'] && ! $c['multi'] && count( $ch ) > 1 ) {
			return bmnm_error( 'one', 'فقط یک گزینه انتخاب کنید.' );
		}
		if ( 'budget' === $c['kind'] && $c['budget'] ) {
			$sum = array_sum( array_map( static fn( $i ) => $c['opts'][ $i ]['cost'], $ch ) );
			if ( $sum > $c['budget'] ) {
				return bmnm_error( 'budget', 'جمع هزینهٔ طرح‌های انتخابی از سقف بودجه بیشتر است.' );
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (poll_id, user_id, choices, created_at) VALUES (%d, %d, %s, %s)', self::table(), $id, $uid, implode( ',', $ch ), gmdate( 'Y-m-d H:i:s' ) ) );
		if ( ! $ok ) {
			return bmnm_error( 'dup', 'شما قبلاً در این نظرسنجی شرکت کرده‌اید.', 409 );
		}
		return rest_ensure_response( self::dto( $p, true ) );
	}

	/**
	 * Admin boxes.
	 */
	public static function boxes() {
		add_meta_box( 'bmnm_poll', 'تنظیمات نظرسنجی / بودجهٔ مشارکتی', array( __CLASS__, 'box' ), self::PT, 'normal', 'high' );
		add_meta_box( 'bmnm_poll_res', 'نتایج', array( __CLASS__, 'box_results' ), self::PT, 'side' );
	}

	/**
	 * Settings box.
	 *
	 * @param WP_Post $p Post.
	 */
	public static function box( $p ) {
		wp_nonce_field( 'bmnm_poll', 'bmnm_poll_nonce' );
		$c     = self::cfg( $p->ID );
		$hoods = get_terms( array( 'taxonomy' => 'bmn_neighborhood', 'hide_empty' => false ) );
		echo '<p><label><input type="radio" name="kind" value="poll"' . checked( $c['kind'], 'poll', false ) . '> نظرسنجی</label> &nbsp; <label><input type="radio" name="kind" value="budget"' . checked( $c['kind'], 'budget', false ) . '> بودجه‌ریزی مشارکتی (انتخاب طرح‌ها تا سقف بودجه)</label></p>';
		echo '<p><label><strong>گزینه‌ها / طرح‌ها</strong> — هر خط یک گزینه: <code>عنوان | هزینه به تومان | توضیح</code> (هزینه و توضیح اختیاری)<textarea name="options" rows="8" class="large-text">' . esc_textarea( (string) get_post_meta( $p->ID, '_bmnm_options', true ) ) . '</textarea></label></p>';
		echo '<p class="bmnm-inline"><label><input type="checkbox" name="multi" value="1"' . checked( $c['multi'], true, false ) . '> چند گزینه‌ای</label> <label>سقف بودجه (تومان) <input type="number" min="0" name="budget" value="' . esc_attr( (string) $c['budget'] ) . '"></label> <label>پایان (میلادی) <input type="date" name="end" value="' . esc_attr( $c['end'] ? wp_date( 'Y-m-d', $c['end'] ) : '' ) . '"></label></p>';
		echo '<p class="bmnm-inline"><label>نمایش نتایج <select name="show"><option value="after"' . selected( $c['show'], 'after', false ) . '>پس از رأی دادن</option><option value="always"' . selected( $c['show'], 'always', false ) . '>همیشه</option><option value="end"' . selected( $c['show'], 'end', false ) . '>پس از پایان</option></select></label> <label>محدود به محله <select name="hood"><option value="">همهٔ شهر</option>';
		foreach ( is_array( $hoods ) ? $hoods : array() as $h ) {
			echo '<option value="' . esc_attr( $h->slug ) . '"' . selected( $c['hood'], $h->slug, false ) . '>' . esc_html( $h->name ) . '</option>';
		}
		echo '</select></label></p><p class="description">رأی‌دهی فقط با حساب شهروندی (ورود پیامکی) و یک رأی برای هر حساب ممکن است. نمایش در سایت خودکار است؛ در هر برگه هم می‌توانید <code>[bmn_poll id="' . (int) $p->ID . '"]</code> بگذارید.</p>';
	}

	/**
	 * Results box.
	 *
	 * @param WP_Post $p Post.
	 */
	public static function box_results( $p ) {
		$c = self::cfg( $p->ID );
		$r = self::results( $p->ID );
		echo '<p>شرکت‌کنندگان: <b>' . esc_html( bmnm_fa( $r['total'] ) ) . '</b></p>';
		foreach ( $c['opts'] as $i => $o ) {
			$pct = $r['total'] ? round( $r['counts'][ $i ] * 100 / $r['total'] ) : 0;
			echo '<div class="bmnm-res"><span>' . esc_html( $o['title'] ) . '</span><div class="bmnm-bar"><i style="width:' . (int) $pct . '%"></i></div><small>' . esc_html( bmnm_fa( $r['counts'][ $i ] ) . ' رأی · ' . bmnm_fa( $pct ) . '٪' ) . '</small></div>';
		}
	}

	/**
	 * Save.
	 *
	 * @param int     $id Post.
	 * @param WP_Post $p  Post.
	 */
	public static function save( $id, $p ) {
		unset( $p );
		if ( ! isset( $_POST['bmnm_poll_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmnm_poll_nonce'] ) ), 'bmnm_poll' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		update_post_meta( $id, '_bmnm_kind', isset( $_POST['kind'] ) && 'budget' === $_POST['kind'] ? 'budget' : 'poll' );
		update_post_meta( $id, '_bmnm_options', isset( $_POST['options'] ) ? sanitize_textarea_field( wp_unslash( $_POST['options'] ) ) : '' );
		update_post_meta( $id, '_bmnm_multi', ! empty( $_POST['multi'] ) ? 1 : 0 );
		update_post_meta( $id, '_bmnm_budget', isset( $_POST['budget'] ) ? absint( $_POST['budget'] ) : 0 );
		$end = isset( $_POST['end'] ) ? sanitize_text_field( wp_unslash( $_POST['end'] ) ) : '';
		update_post_meta( $id, '_bmnm_end', $end ? ( new DateTimeImmutable( $end . ' 23:59:59', wp_timezone() ) )->getTimestamp() : 0 );
		update_post_meta( $id, '_bmnm_show', isset( $_POST['show'] ) && in_array( $_POST['show'], array( 'after', 'always', 'end' ), true ) ? sanitize_key( $_POST['show'] ) : 'after' );
		update_post_meta( $id, '_bmnm_hood', isset( $_POST['hood'] ) ? sanitize_title( wp_unslash( $_POST['hood'] ) ) : '' );
	}

	/**
	 * Single view.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function single( $content ) {
		if ( is_singular( self::PT ) && in_the_loop() && is_main_query() ) {
			$content .= self::sc_poll( array( 'id' => get_the_ID() ) );
		}
		return $content;
	}

	/**
	 * [bmn_poll id=""] — JS-driven widget (server provides the initial state).
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public static function sc_poll( $atts ) {
		$a = shortcode_atts( array( 'id' => 0 ), $atts );
		$p = get_post( (int) $a['id'] );
		if ( ! $p || self::PT !== $p->post_type || 'publish' !== $p->post_status ) {
			return '';
		}
		return '<div class="bmnm-poll" data-id="' . (int) $p->ID . '" data-state="' . esc_attr( wp_json_encode( self::dto( $p, true ) ) ) . '"><noscript>برای شرکت، جاوااسکریپت را فعال کنید.</noscript></div>';
	}

	/**
	 * [bmn_polls] — list.
	 *
	 * @return string
	 */
	public static function sc_polls() {
		$posts = get_posts( array( 'post_type' => self::PT, 'posts_per_page' => 20, 'no_found_rows' => true ) );
		if ( ! $posts ) {
			return '<p class="bmnm-empty">فعلاً نظرسنجی فعالی نیست.</p>';
		}
		$out = '<div class="bmnm-polls alignwide">';
		foreach ( $posts as $p ) {
			$d    = self::dto( $p );
			$out .= '<a class="bmnm-pollcard' . ( $d['open'] ? ' is-open' : '' ) . '" href="' . esc_url( $d['url'] ) . '"><span class="bmnm-kind">' . esc_html( 'budget' === $d['kind'] ? 'بودجهٔ مشارکتی' : 'نظرسنجی' ) . '</span><h3>' . esc_html( $d['title'] ) . '</h3><p>' . esc_html( wp_trim_words( $d['excerpt'], 20, '…' ) ) . '</p><small>' . esc_html( $d['open'] ? ( $d['end_fa'] ? 'تا ' . $d['end_fa'] : 'باز' ) : 'پایان‌یافته' ) . '</small></a>';
		}
		return $out . '</div>';
	}
}
