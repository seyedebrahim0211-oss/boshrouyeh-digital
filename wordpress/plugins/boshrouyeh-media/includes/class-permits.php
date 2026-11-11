<?php
/**
 * Building permit inquiry (استعلام پروانهٔ ساختمانی): staff manage files, citizens track with file number + 4-digit code.
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Permits.
 */
final class BMNM_Permits {

	const PT = 'bmn_permit';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'boxes' ) );
		add_action( 'save_post_' . self::PT, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_shortcode( 'bmn_permit_track', array( __CLASS__, 'shortcode' ) );
		add_filter( 'manage_' . self::PT . '_posts_columns', array( __CLASS__, 'cols' ) );
		add_action( 'manage_' . self::PT . '_posts_custom_column', array( __CLASS__, 'col' ), 10, 2 );
	}

	/**
	 * Stages.
	 *
	 * @return array<string,string>
	 */
	public static function stages() {
		return array(
			'received'   => 'پذیرش پرونده',
			'docs'       => 'تکمیل مدارک',
			'field'      => 'بازدید کارشناس',
			'commission' => 'بررسی در کمیسیون',
			'fees'       => 'محاسبه و پرداخت عوارض',
			'map'        => 'تأیید نقشه‌ها',
			'issued'     => 'صدور پروانه',
			'completion' => 'گواهی پایان‌کار',
			'fix'        => 'نیاز به اصلاح / نقص پرونده',
		);
	}

	/**
	 * Permit kinds.
	 *
	 * @return string[]
	 */
	public static function kinds() {
		return array( 'پروانهٔ احداث', 'تمدید پروانه', 'پروانهٔ تعمیرات', 'اضافه‌بنا', 'تغییر کاربری', 'گواهی عدم خلاف', 'گواهی پایان‌کار' );
	}

	/**
	 * CPT.
	 */
	public static function register() {
		register_post_type(
			self::PT,
			array(
				'labels'          => array(
					'name'          => 'پرونده‌های پروانه',
					'singular_name' => 'پروندهٔ پروانه',
					'add_new_item'  => 'پروندهٔ تازه',
					'edit_item'     => 'ویرایش پرونده',
					'menu_name'     => 'پروانهٔ ساختمانی',
					'search_items'  => 'جست‌وجوی پرونده',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-building',
				'menu_position'   => 8,
				'supports'        => array( 'title' ),
				'capability_type' => array( 'bmn_permit', 'bmn_permits' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Meta getter.
	 *
	 * @param int    $id  Post.
	 * @param string $key Key.
	 * @return string
	 */
	private static function m( $id, $key ) {
		return (string) get_post_meta( $id, '_bmnm_' . $key, true );
	}

	/**
	 * Boxes.
	 */
	public static function boxes() {
		add_meta_box( 'bmnm_permit', 'مشخصات و وضعیت پرونده', array( __CLASS__, 'box' ), self::PT, 'normal', 'high' );
	}

	/**
	 * Box.
	 *
	 * @param WP_Post $p Post.
	 */
	public static function box( $p ) {
		wp_nonce_field( 'bmnm_permit', 'bmnm_permit_nonce' );
		$st = self::m( $p->ID, 'stage' );
		echo '<div class="bmnm-grid-form">';
		echo '<label>شمارهٔ پرونده<input name="number" dir="ltr" required value="' . esc_attr( self::m( $p->ID, 'number' ) ) . '"></label>';
		echo '<label>نوع<select name="kind">';
		foreach ( self::kinds() as $k ) {
			echo '<option' . selected( self::m( $p->ID, 'kind' ), $k, false ) . '>' . esc_html( $k ) . '</option>';
		}
		echo '</select></label><label>نام مالک<input name="owner" value="' . esc_attr( self::m( $p->ID, 'owner' ) ) . '"></label>';
		echo '<label>۴ رقم آخر کد ملی مالک (کد پیگیری)<input name="code" dir="ltr" maxlength="4" pattern="\d{4}" placeholder="' . ( self::m( $p->ID, 'code' ) ? '•••• (ثبت‌شده)' : '' ) . '"></label>';
		echo '<label>همراه مالک (برای پیامک)<input name="phone" dir="ltr" value="' . esc_attr( self::m( $p->ID, 'phone' ) ) . '"></label>';
		echo '<label>نشانی ملک<input name="address" value="' . esc_attr( self::m( $p->ID, 'address' ) ) . '"></label>';
		echo '<label>مرحلهٔ فعلی<select name="stage">';
		foreach ( self::stages() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $st, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label><label>توضیح برای شهروند (در تاریخچه ثبت می‌شود)<input name="note" maxlength="200"></label>';
		echo '<label><input type="checkbox" name="sms" value="1" checked> اطلاع‌رسانی پیامکی تغییر مرحله</label></div>';
		$tl = get_post_meta( $p->ID, '_bmnm_timeline', true );
		if ( is_array( $tl ) && $tl ) {
			echo '<h4>تاریخچه</h4><ol class="bmnm-timeline">';
			foreach ( array_reverse( $tl ) as $e ) {
				echo '<li><b>' . esc_html( self::stages()[ $e['s'] ] ?? $e['s'] ) . '</b> — ' . esc_html( bmnm_jdate( 'j F Y H:i', $e['t'] ) ) . ( $e['n'] ? '<br><small>' . esc_html( $e['n'] ) . '</small>' : '' ) . '</li>';
			}
			echo '</ol>';
		}
	}

	/**
	 * Save.
	 *
	 * @param int     $id Post.
	 * @param WP_Post $p  Post.
	 */
	public static function save( $id, $p ) {
		if ( ! isset( $_POST['bmnm_permit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmnm_permit_nonce'] ) ), 'bmnm_permit' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$num = isset( $_POST['number'] ) ? preg_replace( '/[^0-9A-Za-z\-\/]/', '', function_exists( 'bmn_en_digits' ) ? bmn_en_digits( sanitize_text_field( wp_unslash( $_POST['number'] ) ) ) : sanitize_text_field( wp_unslash( $_POST['number'] ) ) ) : '';
		update_post_meta( $id, '_bmnm_number', $num );
		foreach ( array( 'kind', 'owner', 'address' ) as $k ) {
			update_post_meta( $id, '_bmnm_' . $k, isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '' );
		}
		update_post_meta( $id, '_bmnm_phone', isset( $_POST['phone'] ) ? bmnm_mobile( sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '' );
		$code = isset( $_POST['code'] ) ? preg_replace( '/\D/', '', function_exists( 'bmn_en_digits' ) ? bmn_en_digits( sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : '';
		if ( 4 === strlen( $code ) ) {
			update_post_meta( $id, '_bmnm_code', wp_hash( $num . '|' . $code ) );
		}
		$new  = isset( $_POST['stage'] ) && isset( self::stages()[ sanitize_key( $_POST['stage'] ) ] ) ? sanitize_key( $_POST['stage'] ) : 'received';
		$old  = self::m( $id, 'stage' );
		$note = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		if ( $new !== $old || $note ) {
			$tl   = get_post_meta( $id, '_bmnm_timeline', true );
			$tl   = is_array( $tl ) ? $tl : array();
			$tl[] = array( 's' => $new, 't' => time(), 'n' => $note, 'u' => get_current_user_id() );
			update_post_meta( $id, '_bmnm_timeline', $tl );
			update_post_meta( $id, '_bmnm_stage', $new );
			$phone = self::m( $id, 'phone' );
			if ( $new !== $old && $old && $phone && ! empty( $_POST['sms'] ) && class_exists( 'BMN_SMS' ) ) {
				BMN_SMS::send( $phone, 'شهرداری بشرویه: پروندهٔ پروانهٔ ' . $num . ' به مرحلهٔ «' . self::stages()[ $new ] . '» رسید.' . ( $note ? "\n" . $note : '' ) );
			}
		}
		if ( '' === trim( $p->post_title ) || 'پیش‌نویس خودکار' === $p->post_title ) {
			remove_action( 'save_post_' . self::PT, array( __CLASS__, 'save' ), 10 );
			wp_update_post( array( 'ID' => $id, 'post_title' => 'پرونده ' . $num ) );
			add_action( 'save_post_' . self::PT, array( __CLASS__, 'save' ), 10, 2 );
		}
	}

	/**
	 * Columns.
	 *
	 * @param array $c Cols.
	 * @return array
	 */
	public static function cols( $c ) {
		return array_slice( $c, 0, 2, true ) + array( 'num' => 'شمارهٔ پرونده', 'kind' => 'نوع', 'stage' => 'مرحله' ) + $c;
	}

	/**
	 * Column.
	 *
	 * @param string $col Col.
	 * @param int    $id  Post.
	 */
	public static function col( $col, $id ) {
		if ( 'num' === $col ) {
			echo '<code>' . esc_html( self::m( $id, 'number' ) ) . '</code>';
		} elseif ( 'kind' === $col ) {
			echo esc_html( self::m( $id, 'kind' ) );
		} elseif ( 'stage' === $col ) {
			echo '<span class="bmnm-badge">' . esc_html( self::stages()[ self::m( $id, 'stage' ) ] ?? '—' ) . '</span>';
		}
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route(
			'bmn/v1',
			'/permits/track',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'track' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'number' => array( 'type' => 'string', 'required' => true, 'maxLength' => 30 ),
					'code'   => array( 'type' => 'string', 'required' => true, 'maxLength' => 8 ),
				),
			)
		);
	}

	/**
	 * GET /permits/track?number=&code=
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function track( $req ) {
		if ( ! bmnm_rate_limit( 'permit_' . bmnm_ip(), 10, 10 * MINUTE_IN_SECONDS ) ) {
			return bmnm_error( 'rate', 'تلاش‌ها زیاد است؛ ۱۰ دقیقهٔ دیگر امتحان کنید.', 429 );
		}
		$en   = static fn( $v ) => function_exists( 'bmn_en_digits' ) ? bmn_en_digits( (string) $v ) : (string) $v;
		$num  = preg_replace( '/[^0-9A-Za-z\-\/]/', '', $en( $req['number'] ) );
		$code = preg_replace( '/\D/', '', $en( $req['code'] ) );
		$ids  = $num ? get_posts( array( 'post_type' => self::PT, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => '_bmnm_number', 'meta_value' => $num ) ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
		$id   = $ids ? (int) $ids[0] : 0;
		if ( ! $id || 4 !== strlen( $code ) || ! hash_equals( self::m( $id, 'code' ), wp_hash( $num . '|' . $code ) ) ) {
			return bmnm_error( 'not_found', 'پرونده‌ای با این مشخصات یافت نشد.', 404 );
		}
		$owner = self::m( $id, 'owner' );
		$tl    = get_post_meta( $id, '_bmnm_timeline', true );
		$keys  = array_keys( self::stages() );
		$res   = rest_ensure_response(
			array(
				'number'      => $num,
				'kind'        => self::m( $id, 'kind' ),
				'owner'       => $owner ? mb_substr( $owner, 0, 2 ) . str_repeat( '•', max( 3, mb_strlen( $owner ) - 2 ) ) : '',
				'stage'       => self::m( $id, 'stage' ),
				'stage_label' => self::stages()[ self::m( $id, 'stage' ) ] ?? '',
				'stage_index' => (int) array_search( self::m( $id, 'stage' ), $keys, true ),
				'stages'      => array_values( array_slice( self::stages(), 0, 8 ) ),
				'timeline'    => array_map(
					static fn( $e ) => array(
						'label'   => self::stages()[ $e['s'] ] ?? $e['s'],
						'date_fa' => bmnm_jdate( 'j F Y', $e['t'] ),
						'note'    => $e['n'],
					),
					array_reverse( is_array( $tl ) ? $tl : array() )
				),
			)
		);
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/**
	 * [bmn_permit_track]
	 *
	 * @return string
	 */
	public static function shortcode() {
		return '<form class="bmnm-permit kh-card"><h3>استعلام وضعیت پروانهٔ ساختمانی</h3><div class="bmnm-row"><label>شمارهٔ پرونده<input name="number" required inputmode="numeric" dir="ltr"></label><label>۴ رقم آخر کد ملی مالک<input name="code" required maxlength="4" inputmode="numeric" dir="ltr"></label><button type="submit" class="wp-element-button">استعلام</button></div><div class="bmnm-permit-out" aria-live="polite"></div></form>';
	}
}
