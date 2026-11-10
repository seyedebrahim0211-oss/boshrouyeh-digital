<?php
/**
 * E-service blocks (server-rendered markup, progressively enhanced by assets/js/eservices.js).
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Blocks.
 */
final class BMN_ES_Blocks {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor' ) );
		add_filter( 'bmn_service_start_html', array( __CLASS__, 'service_start' ), 10, 3 );
		add_shortcode( 'bmn_eservice', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Definitions.
	 *
	 * @return array
	 */
	public static function defs() {
		return array(
			'citizen-login'     => array( __( 'ورود شهروند', 'boshrouyeh-eservices' ), 'admin-users', array(), 'login' ),
			'citizen-dashboard' => array( __( 'پیشخوان شهروند', 'boshrouyeh-eservices' ), 'id-alt', array(), 'dashboard' ),
			'report-137'        => array( __( 'فرم گزارش ۱۳۷', 'boshrouyeh-eservices' ), 'megaphone', array(), 'report' ),
			'track'             => array( __( 'پیگیری درخواست', 'boshrouyeh-eservices' ), 'search', array(), 'track' ),
			'dues'              => array( __( 'استعلام و پرداخت عوارض', 'boshrouyeh-eservices' ), 'money-alt', array( 'type' => array( 'type' => 'string', 'default' => 'renovation' ) ), 'dues' ),
			'appointment'       => array( __( 'نوبت ملاقات با شهردار', 'boshrouyeh-eservices' ), 'calendar', array(), 'appointment' ),
			'feedback'          => array( __( 'انتقاد و پیشنهاد', 'boshrouyeh-eservices' ), 'format-chat', array(), 'feedback' ),
			'service-request'   => array( __( 'ثبت درخواست خدمت', 'boshrouyeh-eservices' ), 'clipboard', array( 'serviceId' => array( 'type' => 'number', 'default' => 0 ) ), 'request' ),
		);
	}

	/**
	 * Register blocks.
	 */
	public static function register() {
		foreach ( self::defs() as $slug => $d ) {
			register_block_type(
				'boshrouyeh/' . $slug,
				array(
					'api_version'     => 3,
					'title'           => $d[0],
					'category'        => 'boshrouyeh',
					'attributes'      => $d[2] + array( 'className' => array( 'type' => 'string', 'default' => '' ) ),
					'render_callback' => function ( $attrs ) use ( $d ) {
						return self::render( $d[3], $attrs );
					},
				)
			);
		}
	}

	/**
	 * Shortcode [bmn_eservice type="dues" dues="vehicle"].
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'type' => 'login', 'dues' => 'renovation', 'service' => 0 ), $atts );
		$map  = array( 'login', 'dashboard', 'report', 'track', 'dues', 'appointment', 'feedback', 'request' );
		$type = in_array( $atts['type'], $map, true ) ? $atts['type'] : 'login';
		return self::render( $type, array( 'type' => $atts['dues'], 'serviceId' => (int) $atts['service'] ) );
	}

	/**
	 * Register front assets (enqueued on demand).
	 */
	public static function register_assets() {
		wp_register_style( 'bmn-leaflet', BMN_ES_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'bmn-leaflet', BMN_ES_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
		wp_register_style( 'bmn-es', BMN_ES_URL . 'assets/css/eservices.css', array(), BMN_ES_VERSION );
		wp_register_script( 'bmn-es', BMN_ES_URL . 'assets/js/eservices.js', array(), BMN_ES_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/**
	 * Enqueue + config.
	 *
	 * @param bool $map Needs Leaflet.
	 */
	private static function enqueue( $map = false ) {
		if ( ! wp_script_is( 'bmn-es', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'bmn-es' );
		if ( $map ) {
			wp_enqueue_style( 'bmn-leaflet' );
			wp_enqueue_script( 'bmn-leaflet' );
		}
		wp_enqueue_script( 'bmn-es' );
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		$dash = (int) bmn_option( 'page_dashboard', 0 );
		wp_localize_script(
			'bmn-es',
			'BMNES',
			array(
				'rest'      => esc_url_raw( rest_url( 'bmn/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'loggedIn'  => is_user_logged_in(),
				'dashboard' => $dash ? get_permalink( $dash ) : home_url( '/' ),
				'tiles'     => apply_filters( 'bmn_map_tiles', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png' ),
				'center'    => array( (float) bmn_option( 'lat', 33.8680 ), (float) bmn_option( 'lng', 57.4290 ) ),
				'i18n'      => array(
					'sending'   => __( 'در حال ارسال…', 'boshrouyeh-eservices' ),
					'error'     => __( 'خطا در ارتباط؛ دوباره تلاش کنید.', 'boshrouyeh-eservices' ),
					'resend'    => __( 'ارسال دوباره کد', 'boshrouyeh-eservices' ),
					/* translators: %s: seconds left. */
					'resendIn'  => __( 'ارسال دوباره تا %s ثانیه', 'boshrouyeh-eservices' ),
					/* translators: %s: mobile number. */
					'codeSent'  => __( 'کد ۵ رقمی به %s پیامک شد.', 'boshrouyeh-eservices' ),
					'devCode'   => __( 'کد آزمایشی (فقط محیط توسعه):', 'boshrouyeh-eservices' ),
					'tracking'  => __( 'کد پیگیری شما:', 'boshrouyeh-eservices' ),
					'copy'      => __( 'کپی', 'boshrouyeh-eservices' ),
					'located'   => __( 'موقعیت ثبت شد.', 'boshrouyeh-eservices' ),
					'noGeo'     => __( 'دسترسی به موقعیت ممکن نشد؛ روی نقشه انتخاب کنید.', 'boshrouyeh-eservices' ),
					'toman'     => __( 'تومان', 'boshrouyeh-eservices' ),
					'payable'   => __( 'مبلغ قابل پرداخت', 'boshrouyeh-eservices' ),
					'payOnline' => __( 'پرداخت آنلاین', 'boshrouyeh-eservices' ),
					'demo'      => __( 'حالت آزمایشی: مبالغ نمونه‌اند.', 'boshrouyeh-eservices' ),
					'redirect'  => __( 'در حال انتقال به درگاه بانک…', 'boshrouyeh-eservices' ),
					'installment' => __( 'درخواست تقسیط', 'boshrouyeh-eservices' ),
					'saved'     => __( 'ذخیره شد.', 'boshrouyeh-eservices' ),
					'tooBig'    => __( 'حجم تصویر حداکثر ۶ مگابایت است.', 'boshrouyeh-eservices' ),
				),
			)
		);
	}

	/**
	 * Editor registration (static placeholders; real UI renders on the site).
	 */
	public static function editor() {
		$defs = array();
		foreach ( self::defs() as $slug => $d ) {
			$defs[] = array( 'name' => 'boshrouyeh/' . $slug, 'title' => $d[0], 'icon' => $d[1], 'attributes' => $d[2] );
		}
		wp_add_inline_script( 'bmn-blocks-editor', 'window.BMNBlocks && BMNBlocks.blocks.push.apply(BMNBlocks.blocks,' . wp_json_encode( $defs ) . ');', 'before' );
	}

	/**
	 * Render dispatcher.
	 *
	 * @param string $kind  Kind.
	 * @param array  $attrs Attrs.
	 * @return string
	 */
	public static function render( $kind, $attrs ) {
		$map = in_array( $kind, array( 'report' ), true );
		self::enqueue( $map );
		$method = 'render_' . $kind;
		return '<div class="bmn-es bmn-es--' . esc_attr( $kind ) . '">' . self::$method( $attrs ) . '</div>';
	}

	/**
	 * Login component.
	 *
	 * @param string $intro Intro text.
	 * @return string
	 */
	public static function login_html( $intro = '' ) {
		$uid = wp_unique_id( 'bmn-login-' );
		$intro = $intro ? $intro : __( 'بدون رمز عبور؛ فقط شمارهٔ همراه و کد پیامکی.', 'boshrouyeh-eservices' );
		return '<div class="bmn-login kh-panel" data-bmn-login>'
			. '<div class="bmn-login__head"><span class="bmn-login__logo">' . self::logo( 58 ) . '</span><div><h2>' . esc_html__( 'ورود شهروند', 'boshrouyeh-eservices' ) . '</h2><p class="kh-help">' . esc_html( $intro ) . '</p></div></div>'
			. '<form class="kh-form" data-step="phone" novalidate><div class="kh-field"><label for="' . esc_attr( $uid ) . '-p">' . esc_html__( 'شمارهٔ همراه', 'boshrouyeh-eservices' ) . '</label><input class="kh-input kh-ltr" id="' . esc_attr( $uid ) . '-p" name="phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="09xx xxx xxxx" maxlength="14" required></div><button class="kh-btn kh-btn--action kh-btn--block" type="submit">' . esc_html__( 'دریافت کد', 'boshrouyeh-eservices' ) . '</button></form>'
			. '<form class="kh-form" data-step="code" hidden novalidate><p class="kh-alert kh-alert--info" data-msg></p><div class="kh-field"><label for="' . esc_attr( $uid ) . '-c">' . esc_html__( 'کد تأیید', 'boshrouyeh-eservices' ) . '</label><input class="kh-input kh-ltr bmn-code" id="' . esc_attr( $uid ) . '-c" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9۰-۹]{5}" maxlength="5" required></div><button class="kh-btn kh-btn--action kh-btn--block" type="submit">' . esc_html__( 'ورود', 'boshrouyeh-eservices' ) . '</button><p class="bmn-login__alt"><button type="button" class="bmn-link" data-resend disabled></button> · <button type="button" class="bmn-link" data-edit>' . esc_html__( 'ویرایش شماره', 'boshrouyeh-eservices' ) . '</button></p></form>'
			. '<p class="bmn-err" role="alert" aria-live="assertive"></p>'
			. '<p class="kh-help bmn-login__legal">' . esc_html__( 'با ورود، شرایط استفاده و حریم خصوصی را می‌پذیرید. شماره شما فقط برای خدمات شهرداری استفاده می‌شود.', 'boshrouyeh-eservices' ) . '</p></div>';
	}

	/**
	 * Municipality vector logo (theme copy preferred, core copy as fallback).
	 *
	 * @param int $size Size.
	 * @return string
	 */
	public static function logo( $size = 56 ) {
		if ( function_exists( 'khesht_logo' ) ) {
			return khesht_logo( $size );
		}
		return '<img src="' . esc_url( BMN_CORE_URL . 'assets/img/logo-brick.svg' ) . '" width="' . (int) $size . '" height="' . (int) $size . '" alt="' . esc_attr__( 'نشان شهرداری بشرویه', 'boshrouyeh-eservices' ) . '">';
	}

	/**
	 * [login]
	 *
	 * @return string
	 */
	private static function render_login() {
		if ( is_user_logged_in() ) {
			$dash = (int) bmn_option( 'page_dashboard', 0 );
			return '<p class="kh-alert kh-alert--success">' . esc_html__( 'شما وارد شده‌اید.', 'boshrouyeh-eservices' ) . ' <a href="' . esc_url( $dash ? get_permalink( $dash ) : home_url( '/' ) ) . '">' . esc_html__( 'رفتن به پیشخوان', 'boshrouyeh-eservices' ) . '</a></p>';
		}
		return self::login_html();
	}

	/**
	 * [dashboard]
	 *
	 * @return string
	 */
	private static function render_dashboard() {
		if ( ! is_user_logged_in() ) {
			return '<div class="bmn-dash-guest"><div class="bmn-dash-guest__art" aria-hidden="true"></div>' . self::login_html( __( 'پرونده‌ها، پرداخت‌ها و گزارش‌های شما در یک جا.', 'boshrouyeh-eservices' ) ) . '</div>';
		}
		$uid   = get_current_user_id();
		$user  = wp_get_current_user();
		$phone = (string) get_user_meta( $uid, '_bmn_phone', true );
		$hood  = (string) get_user_meta( $uid, '_bmn_neighborhood', true );
		$reqs  = get_posts(
			array(
				'post_type'      => BMN_Requests::PT,
				'post_status'    => 'any',
				'author'         => $uid,
				'posts_per_page' => 20,
				'no_found_rows'  => true,
			)
		);
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$pays  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE user_id = %d AND status <> 'pending' ORDER BY id DESC LIMIT 20", BMN_Payments::table(), $uid ) );
		$n_req = count( array_filter( $reqs, fn( $p ) => 'report137' !== get_post_meta( $p->ID, '_bmn_type', true ) ) );
		$n_rep = count( $reqs ) - $n_req;
		$n_pay = count( array_filter( $pays, fn( $p ) => 'paid' === $p->status ) );

		$name  = 'شهروند' === $user->display_name ? __( 'شهروند گرامی', 'boshrouyeh-eservices' ) : $user->display_name;
		$h     = '<div class="bmn-dash">';
		$h    .= '<header class="bmn-dash__head"><span class="bmn-avatar" aria-hidden="true">' . bmn_icon( 'user', 28 ) . '</span><div><p class="kh-help">' . esc_html( bmn_jdate( 'l j F' ) ) . '</p><h1>' . esc_html( sprintf( /* translators: %s: name. */ __( 'سلام، %s', 'boshrouyeh-eservices' ), $name ) ) . '</h1><p class="kh-help kh-ltr">' . esc_html( bmn_fa_digits( $phone ) ) . '</p></div><a class="kh-btn kh-btn--ghost kh-btn--sm" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . bmn_icon( 'logout', 18 ) . esc_html__( 'خروج', 'boshrouyeh-eservices' ) . '</a></header>';
		$h    .= '<ul class="bmn-stats" role="list">'
			. '<li><b>' . esc_html( bmn_fa_digits( $n_req ) ) . '</b><span>' . esc_html__( 'پرونده', 'boshrouyeh-eservices' ) . '</span></li>'
			. '<li><b>' . esc_html( bmn_fa_digits( $n_pay ) ) . '</b><span>' . esc_html__( 'پرداخت', 'boshrouyeh-eservices' ) . '</span></li>'
			. '<li class="is-action"><b>' . esc_html( bmn_fa_digits( $n_rep ) ) . '</b><span>' . esc_html__( 'گزارش ۱۳۷', 'boshrouyeh-eservices' ) . '</span></li></ul>';
		$quick = array(
			array( 'payment', __( 'پرداخت عوارض', 'boshrouyeh-eservices' ), (int) bmn_option( 'page_dues', 0 ), '/dues/' ),
			array( '137', __( 'گزارش ۱۳۷', 'boshrouyeh-eservices' ), (int) bmn_option( 'page_137', 0 ), '/report-137/' ),
			array( 'search', __( 'پیگیری', 'boshrouyeh-eservices' ), (int) bmn_option( 'page_track', 0 ), '/track/' ),
			array( 'calendar', __( 'نوبت ملاقات', 'boshrouyeh-eservices' ), (int) bmn_option( 'page_appointment', 0 ), '/appointment/' ),
		);
		$h .= '<nav class="bmn-quick" aria-label="' . esc_attr__( 'دسترسی سریع', 'boshrouyeh-eservices' ) . '">';
		foreach ( $quick as $q ) {
			$h .= '<a href="' . esc_url( $q[2] ? get_permalink( $q[2] ) : home_url( $q[3] ) ) . '"><span class="kh-taqche">' . bmn_icon( $q[0] ) . '</span>' . esc_html( $q[1] ) . '</a>';
		}
		$h .= '</nav><div class="bmn-dash__grid"><section class="kh-panel"><h2>' . esc_html__( 'پرونده‌های من', 'boshrouyeh-eservices' ) . '</h2>';
		if ( ! $reqs ) {
			$h .= '<p class="kh-empty">' . esc_html__( 'هنوز درخواستی ثبت نکرده‌اید.', 'boshrouyeh-eservices' ) . '</p>';
		} else {
			$h .= '<ul class="bmn-files-list" role="list">';
			foreach ( $reqs as $p ) {
				$d   = BMN_Requests::dto( $p );
				$cls = BMN_Requests::statuses()[ $d['status'] ][2];
				$bar = '';
				for ( $i = 1; $i <= 4; $i++ ) {
					$bar .= '<span class="' . ( $i <= $d['progress'] ? 'on' : '' ) . '"></span>';
				}
				$h .= '<li class="bmn-file"><div class="bmn-file__top"><strong>' . esc_html( $d['type_label'] . ': ' . $d['title'] ) . '</strong><span class="kh-chip ' . esc_attr( $cls ) . '">' . esc_html( $d['status_label'] ) . '</span></div><p class="kh-help">' . esc_html( sprintf( /* translators: 1: code, 2: date. */ __( 'کد پیگیری %1$s | %2$s', 'boshrouyeh-eservices' ), bmn_fa_digits( $d['tracking'] ), $d['created_fa'] ) ) . '</p><div class="bmn-progress" role="img" aria-label="' . esc_attr( sprintf( /* translators: %s: step. */ __( 'مرحلهٔ %s از ۴', 'boshrouyeh-eservices' ), bmn_fa_digits( $d['progress'] ) ) ) . '">' . $bar . '</div></li>';
			}
			$h .= '</ul>';
		}
		$h .= '</section><section class="kh-panel"><h2>' . esc_html__( 'پرداخت‌های من', 'boshrouyeh-eservices' ) . '</h2>';
		if ( ! $pays ) {
			$h .= '<p class="kh-empty">' . esc_html__( 'پرداختی ثبت نشده است.', 'boshrouyeh-eservices' ) . '</p>';
		} else {
			$h .= '<ul class="bmn-pay-list" role="list">';
			foreach ( $pays as $p ) {
				$d  = BMN_Payments::dto( $p );
				$h .= '<li><div><strong>' . esc_html( $d['title'] ) . '</strong><small>' . esc_html( $d['date_fa'] ) . '</small></div><div class="bmn-pay-list__end"><b>' . esc_html( $d['amount_fa'] ) . '</b><span class="kh-chip ' . ( 'paid' === $d['status'] ? 'kh-chip--done' : 'kh-chip--need' ) . '">' . esc_html( $d['status_label'] ) . '</span>' . ( 'paid' === $d['status'] ? '<a href="' . esc_url( $d['receipt_url'] ) . '">' . esc_html__( 'رسید', 'boshrouyeh-eservices' ) . '</a>' : '' ) . '</div></li>';
			}
			$h .= '</ul>';
		}
		$h    .= '</section></div>';
		$terms = get_terms( array( 'taxonomy' => 'bmn_neighborhood', 'hide_empty' => false ) );
		$h    .= '<details class="kh-panel bmn-profile"><summary>' . esc_html__( 'تنظیمات حساب و محلهٔ من', 'boshrouyeh-eservices' ) . '</summary><form class="kh-form" data-bmn-profile><div class="kh-field"><label for="bmn-name">' . esc_html__( 'نام و نام خانوادگی', 'boshrouyeh-eservices' ) . '</label><input class="kh-input" id="bmn-name" name="name" maxlength="60" value="' . esc_attr( 'شهروند' === $user->display_name ? '' : $user->display_name ) . '"></div><div class="kh-field"><label for="bmn-hood">' . esc_html__( 'محلهٔ سکونت (برای اعلان‌های محله‌ای)', 'boshrouyeh-eservices' ) . '</label><select class="kh-input" id="bmn-hood" name="neighborhood"><option value="">—</option>';
		foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
			$h .= '<option value="' . esc_attr( $t->slug ) . '" ' . selected( $hood, $t->slug, false ) . '>' . esc_html( $t->name ) . '</option>';
		}
		$h .= '</select></div><button class="kh-btn kh-btn--action" type="submit">' . esc_html__( 'ذخیره', 'boshrouyeh-eservices' ) . '</button><p class="bmn-err" role="status"></p></form></details></div>';
		return $h;
	}

	/**
	 * Login gate helper.
	 *
	 * @param string $intro Intro.
	 * @return string
	 */
	private static function gate( $intro ) {
		return is_user_logged_in() ? '' : self::login_html( $intro );
	}

	/**
	 * Success template shared by forms.
	 *
	 * @return string
	 */
	private static function success_tpl() {
		return '<div class="bmn-success" hidden role="status" tabindex="-1"><span class="bmn-success__icon" aria-hidden="true">' . bmn_icon( 'check', 34 ) . '</span><h2>' . esc_html__( 'با موفقیت ثبت شد', 'boshrouyeh-eservices' ) . '</h2><p>' . esc_html__( 'کد پیگیری شما:', 'boshrouyeh-eservices' ) . '</p><p class="bmn-success__code" data-code></p><p class="kh-help">' . esc_html__( 'کد پیگیری پیامک هم شد. وضعیت را در پیشخوان یا صفحهٔ پیگیری ببینید.', 'boshrouyeh-eservices' ) . '</p></div>';
	}

	/**
	 * [report]
	 *
	 * @return string
	 */
	private static function render_report() {
		$gate = self::gate( __( 'برای ثبت گزارش ۱۳۷ وارد شوید؛ کد پیگیری پیامک می‌شود.', 'boshrouyeh-eservices' ) );
		if ( $gate ) {
			return $gate;
		}
		$h = '<form class="kh-form kh-panel bmn-report" data-bmn-request="report137" novalidate><div class="bmn-form-head"><span class="kh-taqche kh-taqche--action">' . bmn_icon( '137' ) . '</span><div><h2>' . esc_html__( 'گزارش به ۱۳۷', 'boshrouyeh-eservices' ) . '</h2><p class="kh-help">' . esc_html__( 'مشکل را با عکس و موقعیت ثبت کنید.', 'boshrouyeh-eservices' ) . '</p></div></div>';
		$h .= '<fieldset class="bmn-chips"><legend class="kh-label">' . esc_html__( 'موضوع', 'boshrouyeh-eservices' ) . '</legend>';
		foreach ( BMN_Requests::categories() as $k => $label ) {
			$h .= '<label><input type="radio" name="category" value="' . esc_attr( $k ) . '" required><span>' . esc_html( $label ) . '</span></label>';
		}
		$h .= '</fieldset><div class="bmn-map-wrap"><div class="bmn-map" data-bmn-map role="application" aria-label="' . esc_attr__( 'نقشه برای انتخاب محل', 'boshrouyeh-eservices' ) . '"></div><button type="button" class="kh-btn kh-btn--sun kh-btn--sm bmn-locate" data-bmn-locate>' . bmn_icon( 'pin', 18 ) . esc_html__( 'موقعیت فعلی من', 'boshrouyeh-eservices' ) . '</button></div><input type="hidden" name="lat"><input type="hidden" name="lng">';
		$h .= '<div class="kh-field"><label for="bmn-addr">' . esc_html__( 'نشانی یا نشانه (اختیاری)', 'boshrouyeh-eservices' ) . '</label><input class="kh-input" id="bmn-addr" name="address" maxlength="200" placeholder="' . esc_attr__( 'مثلاً: خیابان امام، نبش کوچهٔ ۱۲', 'boshrouyeh-eservices' ) . '"></div>';
		$h .= '<div class="bmn-photo-row"><label class="bmn-photo" for="bmn-photo">' . bmn_icon( 'camera', 28 ) . '<span>' . esc_html__( 'افزودن عکس', 'boshrouyeh-eservices' ) . '</span><img alt="" hidden></label><input class="screen-reader-text" type="file" id="bmn-photo" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment"><div class="kh-field"><label for="bmn-desc">' . esc_html__( 'شرح مشکل', 'boshrouyeh-eservices' ) . '</label><textarea class="kh-input" id="bmn-desc" name="description" minlength="5" maxlength="2000" required placeholder="' . esc_attr__( 'مثلاً: چراغ‌های کوچه از دیشب خاموش است…', 'boshrouyeh-eservices' ) . '"></textarea></div></div>';
		$h .= '<button class="kh-btn kh-btn--action kh-btn--block" type="submit">' . esc_html__( 'ثبت گزارش', 'boshrouyeh-eservices' ) . '</button><p class="bmn-err" role="alert"></p></form>' . self::success_tpl();
		return $h;
	}

	/**
	 * [track]
	 *
	 * @return string
	 */
	private static function render_track() {
		return '<form class="kh-form kh-panel bmn-track" data-bmn-track novalidate><div class="bmn-form-head"><span class="kh-taqche">' . bmn_icon( 'search' ) . '</span><div><h2>' . esc_html__( 'پیگیری درخواست', 'boshrouyeh-eservices' ) . '</h2><p class="kh-help">' . esc_html__( 'کد پیگیری ۱۰ رقمی پیامک‌شده را وارد کنید.', 'boshrouyeh-eservices' ) . '</p></div></div><div class="bmn-row"><div class="kh-field"><label for="bmn-tc">' . esc_html__( 'کد پیگیری', 'boshrouyeh-eservices' ) . '</label><input class="kh-input kh-ltr" id="bmn-tc" name="code" inputmode="numeric" maxlength="12" required></div>'
			. ( is_user_logged_in() ? '' : '<div class="kh-field"><label for="bmn-t4">' . esc_html__( '۴ رقم آخر شمارهٔ همراه', 'boshrouyeh-eservices' ) . '</label><input class="kh-input kh-ltr" id="bmn-t4" name="phone4" inputmode="numeric" maxlength="4" required></div>' )
			. '</div><button class="kh-btn kh-btn--action" type="submit">' . esc_html__( 'پیگیری', 'boshrouyeh-eservices' ) . '</button><p class="bmn-err" role="alert"></p></form><div class="bmn-track-result" aria-live="polite"></div>';
	}

	/**
	 * [dues]
	 *
	 * @param array $attrs Attrs.
	 * @return string
	 */
	private static function render_dues( $attrs ) {
		$types = BMN_Dues::types();
		$type  = isset( $types[ $attrs['type'] ?? '' ] ) ? $attrs['type'] : 'renovation';
		$gate  = self::gate( __( 'برای دیدن فیش و پرداخت، با شمارهٔ همراه وارد شوید.', 'boshrouyeh-eservices' ) );
		if ( $gate ) {
			return $gate;
		}
		$inst = get_page_by_path( 'clearance-installments', OBJECT, 'bmn_service' );
		return '<form class="kh-form bmn-dues" data-bmn-dues="' . esc_attr( $type ) . '" data-installment="' . esc_url( $inst ? get_permalink( $inst ) : '' ) . '" novalidate><div class="kh-field"><label for="bmn-due-' . esc_attr( $type ) . '">' . esc_html( $types[ $type ][1] ) . '</label><input class="kh-input kh-ltr" id="bmn-due-' . esc_attr( $type ) . '" name="identifier" maxlength="40" required placeholder="' . esc_attr( bmn_fa_digits( $types[ $type ][2] ) ) . '"></div><button class="kh-btn kh-btn--action kh-btn--block" type="submit">' . esc_html__( 'دریافت فیش', 'boshrouyeh-eservices' ) . '</button><p class="bmn-err" role="alert"></p></form><div class="bmn-bill" aria-live="polite"></div>';
	}

	/**
	 * [appointment]
	 *
	 * @return string
	 */
	private static function render_appointment() {
		$gate = self::gate( __( 'برای درخواست نوبت ملاقات وارد شوید.', 'boshrouyeh-eservices' ) );
		if ( $gate ) {
			return $gate;
		}
		return '<form class="kh-form kh-panel" data-bmn-request="appointment" novalidate><div class="bmn-form-head"><span class="kh-taqche">' . bmn_icon( 'calendar' ) . '</span><div><h2>' . esc_html__( 'درخواست نوبت ملاقات با شهردار', 'boshrouyeh-eservices' ) . '</h2><p class="kh-help">' . esc_html__( 'نتیجه و زمان قطعی ظرف ۳ روز کاری پیامک می‌شود.', 'boshrouyeh-eservices' ) . '</p></div></div><div class="kh-field"><label for="bmn-ap-s">' . esc_html__( 'موضوع', 'boshrouyeh-eservices' ) . '</label><input class="kh-input" id="bmn-ap-s" name="subject" maxlength="120" required></div><div class="kh-field"><label for="bmn-ap-d">' . esc_html__( 'تاریخ پیشنهادی (اختیاری)', 'boshrouyeh-eservices' ) . '</label><input class="kh-input kh-ltr" id="bmn-ap-d" name="preferred_date" placeholder="' . esc_attr( bmn_jdate( 'Y/m/d', time() + 3 * DAY_IN_SECONDS ) ) . '"></div><div class="kh-field"><label for="bmn-ap-t">' . esc_html__( 'شرح کوتاه', 'boshrouyeh-eservices' ) . '</label><textarea class="kh-input" id="bmn-ap-t" name="description" maxlength="2000" required></textarea></div><button class="kh-btn kh-btn--action" type="submit">' . esc_html__( 'ثبت درخواست', 'boshrouyeh-eservices' ) . '</button><p class="bmn-err" role="alert"></p></form>' . self::success_tpl();
	}

	/**
	 * [feedback] — guests allowed (honeypot + rate limit).
	 *
	 * @return string
	 */
	private static function render_feedback() {
		$h  = '<form class="kh-form kh-panel" data-bmn-request="feedback" novalidate><div class="bmn-form-head"><span class="kh-taqche">' . bmn_icon( 'chat' ) . '</span><div><h2>' . esc_html__( 'انتقاد، پیشنهاد، قدردانی', 'boshrouyeh-eservices' ) . '</h2><p class="kh-help">' . esc_html__( 'صدای شما مستقیم به مدیران شهرداری می‌رسد.', 'boshrouyeh-eservices' ) . '</p></div></div>';
		$h .= '<fieldset class="bmn-chips"><legend class="kh-label">' . esc_html__( 'نوع پیام', 'boshrouyeh-eservices' ) . '</legend><label><input type="radio" name="kind" value="critic"><span>' . esc_html__( 'انتقاد', 'boshrouyeh-eservices' ) . '</span></label><label><input type="radio" name="kind" value="suggest" checked><span>' . esc_html__( 'پیشنهاد', 'boshrouyeh-eservices' ) . '</span></label><label><input type="radio" name="kind" value="thanks"><span>' . esc_html__( 'قدردانی', 'boshrouyeh-eservices' ) . '</span></label></fieldset>';
		$h .= '<fieldset class="bmn-rating"><legend class="kh-label">' . esc_html__( 'رضایت از خدمات شهرداری', 'boshrouyeh-eservices' ) . '</legend>';
		for ( $i = 5; $i >= 1; $i-- ) {
			$h .= '<input type="radio" id="bmn-r' . $i . '" name="rating" value="' . $i . '"><label for="bmn-r' . $i . '" aria-label="' . esc_attr( bmn_fa_digits( $i ) ) . '">★</label>';
		}
		$h .= '</fieldset><div class="kh-field"><label for="bmn-fb">' . esc_html__( 'متن پیام', 'boshrouyeh-eservices' ) . '</label><textarea class="kh-input" id="bmn-fb" name="description" maxlength="2000" required></textarea></div>';
		if ( ! is_user_logged_in() ) {
			$h .= '<div class="kh-field"><label for="bmn-fbp">' . esc_html__( 'شمارهٔ همراه برای پاسخ (اختیاری)', 'boshrouyeh-eservices' ) . '</label><input class="kh-input kh-ltr" id="bmn-fbp" name="phone" type="tel" inputmode="numeric" maxlength="14"></div>';
		}
		$h .= '<div class="bmn-hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><button class="kh-btn kh-btn--action" type="submit">' . esc_html__( 'ارسال', 'boshrouyeh-eservices' ) . '</button><p class="bmn-err" role="alert"></p></form>' . self::success_tpl();
		return $h;
	}

	/**
	 * [request] generic service request.
	 *
	 * @param array $attrs Attrs.
	 * @return string
	 */
	private static function render_request( $attrs ) {
		$sid  = (int) ( $attrs['serviceId'] ?? 0 );
		$gate = self::gate( __( 'برای ثبت درخواست وارد شوید.', 'boshrouyeh-eservices' ) );
		if ( $gate ) {
			return $gate;
		}
		return '<form class="kh-form" data-bmn-request="request" novalidate><input type="hidden" name="service_id" value="' . esc_attr( (string) $sid ) . '"><div class="kh-field"><label for="bmn-rq-' . $sid . '">' . esc_html__( 'شرح درخواست', 'boshrouyeh-eservices' ) . '</label><textarea class="kh-input" id="bmn-rq-' . $sid . '" name="description" maxlength="2000" required></textarea></div><div class="kh-field"><label for="bmn-rqa-' . $sid . '">' . esc_html__( 'نشانی (اختیاری)', 'boshrouyeh-eservices' ) . '</label><input class="kh-input" id="bmn-rqa-' . $sid . '" name="address" maxlength="200"></div><div class="kh-field"><label for="bmn-rqf-' . $sid . '">' . esc_html__( 'تصویر مدرک/محل (اختیاری)', 'boshrouyeh-eservices' ) . '</label><input class="kh-input" id="bmn-rqf-' . $sid . '" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div><button class="kh-btn kh-btn--action kh-btn--block" type="submit">' . esc_html__( 'ثبت درخواست', 'boshrouyeh-eservices' ) . '</button><p class="bmn-err" role="alert"></p></form>' . self::success_tpl();
	}

	/**
	 * Fill the service page "start" box.
	 *
	 * @param string $html Default.
	 * @param string $key  E-service key.
	 * @param int    $id   Service id.
	 * @return string
	 */
	public static function service_start( $html, $key, $id ) {
		$link = function ( $opt, $path, $label ) {
			$pid = (int) bmn_option( $opt, 0 );
			return '<a class="kh-btn kh-btn--action kh-btn--block" href="' . esc_url( $pid ? get_permalink( $pid ) : home_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
		};
		switch ( $key ) {
			case 'dues_renovation':
				return self::render( 'dues', array( 'type' => 'renovation' ) );
			case 'dues_vehicle':
				return self::render( 'dues', array( 'type' => 'vehicle' ) );
			case 'dues_business':
				return self::render( 'dues', array( 'type' => 'business' ) );
			case 'report137':
				return '<p>' . esc_html__( 'گزارش با عکس و موقعیت؛ پاسخ کمتر از ۷۲ ساعت.', 'boshrouyeh-eservices' ) . '</p>' . $link( 'page_137', '/report-137/', __( 'ثبت گزارش ۱۳۷', 'boshrouyeh-eservices' ) );
			case 'tracking':
				return self::render( 'track', array() );
			case 'appointment':
				return '<p>' . esc_html__( 'درخواست نوبت را آنلاین ثبت کنید؛ زمان قطعی پیامک می‌شود.', 'boshrouyeh-eservices' ) . '</p>' . $link( 'page_appointment', '/appointment/', __( 'درخواست نوبت', 'boshrouyeh-eservices' ) );
			case 'feedback':
				return $link( 'page_feedback', '/feedback/', __( 'ارسال انتقاد و پیشنهاد', 'boshrouyeh-eservices' ) );
			case 'request':
				return self::render( 'request', array( 'serviceId' => $id ) );
			case 'complaint':
				return '<p>' . esc_html__( 'شکایت فقط به حراست و بازرسی شهرداری ارجاع می‌شود و محرمانه است.', 'boshrouyeh-eservices' ) . '</p>' . str_replace( 'data-bmn-request="request"', 'data-bmn-request="complaint"', self::render( 'request', array( 'serviceId' => $id ) ) );
			case 'visits':
				return '<p>' . esc_html__( 'دستورهای شهردار با واحد مسئول، مهلت، منبع اعتبار و وضعیت فعلی.', 'boshrouyeh-eservices' ) . '</p><a class="kh-btn kh-btn--action kh-btn--block" href="' . esc_url( home_url( '/directives/' ) ) . '">' . esc_html__( 'پیگیری دستورهای بازدید', 'boshrouyeh-eservices' ) . '</a>';
			case 'fleet':
				return '<a class="kh-btn kh-btn--action kh-btn--block" href="' . esc_url( home_url( '/minibus/' ) ) . '">' . esc_html__( 'نقشهٔ زندهٔ مینی‌بوس‌ها', 'boshrouyeh-eservices' ) . '</a>';
		}
		return $html;
	}
}
