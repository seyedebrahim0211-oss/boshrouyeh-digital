<?php
/**
 * Municipality settings page.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
final class BMN_Settings {

	const OPTION = 'bmn_settings';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Fields definition: key => [label, type, section].
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'org_name'       => array( __( 'نام سازمان', 'boshrouyeh-core' ), 'text', 'contact' ),
			'phone'          => array( __( 'تلفن گویا', 'boshrouyeh-core' ), 'text', 'contact' ),
			'email'          => array( __( 'ایمیل', 'boshrouyeh-core' ), 'email', 'contact' ),
			'address'        => array( __( 'نشانی', 'boshrouyeh-core' ), 'text', 'contact' ),
			'postal_code'    => array( __( 'کد پستی', 'boshrouyeh-core' ), 'text', 'contact' ),
			'work_hours'     => array( __( 'ساعات کاری', 'boshrouyeh-core' ), 'text', 'contact' ),
			'lat'            => array( __( 'عرض جغرافیایی ساختمان', 'boshrouyeh-core' ), 'text', 'contact' ),
			'lng'            => array( __( 'طول جغرافیایی ساختمان', 'boshrouyeh-core' ), 'text', 'contact' ),
			'mayor_name'     => array( __( 'نام شهردار', 'boshrouyeh-core' ), 'text', 'contact' ),
			'english_url'    => array( __( 'نشانی نسخهٔ انگلیسی', 'boshrouyeh-core' ), 'url', 'links' ),
			'instagram'      => array( __( 'اینستاگرام', 'boshrouyeh-core' ), 'url', 'links' ),
			'telegram'       => array( __( 'تلگرام', 'boshrouyeh-core' ), 'url', 'links' ),
			'eitaa'          => array( __( 'ایتا', 'boshrouyeh-core' ), 'url', 'links' ),
			'bale'           => array( __( 'بله', 'boshrouyeh-core' ), 'url', 'links' ),
			'aparat'         => array( __( 'آپارات', 'boshrouyeh-core' ), 'url', 'links' ),
			'app_bazaar'     => array( __( 'اپ در کافه‌بازار', 'boshrouyeh-core' ), 'url', 'links' ),
			'app_myket'      => array( __( 'اپ در مایکت', 'boshrouyeh-core' ), 'url', 'links' ),
			'app_apk'        => array( __( 'دانلود مستقیم APK', 'boshrouyeh-core' ), 'url', 'links' ),
			'page_dashboard' => array( __( 'برگهٔ پیشخوان شهروند', 'boshrouyeh-core' ), 'page', 'pages' ),
			'page_137'       => array( __( 'برگهٔ سامانه ۱۳۷', 'boshrouyeh-core' ), 'page', 'pages' ),
			'page_dues'      => array( __( 'برگهٔ پرداخت عوارض', 'boshrouyeh-core' ), 'page', 'pages' ),
			'page_track'     => array( __( 'برگهٔ پیگیری', 'boshrouyeh-core' ), 'page', 'pages' ),
			'page_deceased'  => array( __( 'برگهٔ جست‌وجوی متوفیان', 'boshrouyeh-core' ), 'page', 'pages' ),
			'page_appointment' => array( __( 'برگهٔ نوبت ملاقات', 'boshrouyeh-core' ), 'page', 'pages' ),
			'page_feedback'  => array( __( 'برگهٔ انتقاد و پیشنهاد', 'boshrouyeh-core' ), 'page', 'pages' ),
		);
	}

	/**
	 * Admin menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'شهرداری بشرویه', 'boshrouyeh-core' ),
			__( 'شهرداری بشرویه', 'boshrouyeh-core' ),
			'manage_options',
			'bmn-settings',
			array( __CLASS__, 'render' ),
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="1.75"><path d="M12 2.5l2.7 3.6 4.5-.6-.6 4.5L21.5 12l-2.9 2.7.6 4.5-4.5-.6L12 21.5l-2.7-2.9-4.5.6.6-4.5L2.5 12l2.9-2.7-.6-4.5 4.5.6z"/></svg>' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			3
		);
	}

	/**
	 * Register setting.
	 */
	public static function register() {
		register_setting(
			'bmn_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
		$sections = array(
			'contact' => __( 'اطلاعات تماس', 'boshrouyeh-core' ),
			'links'   => __( 'شبکه‌های اجتماعی و اپ', 'boshrouyeh-core' ),
			'pages'   => __( 'برگه‌های خدمات', 'boshrouyeh-core' ),
		);
		foreach ( $sections as $id => $title ) {
			add_settings_section( 'bmn_' . $id, $title, '__return_false', 'bmn-settings' );
		}
		foreach ( self::fields() as $key => $f ) {
			add_settings_field(
				$key,
				$f[0],
				array( __CLASS__, 'field' ),
				'bmn-settings',
				'bmn_' . $f[2],
				array(
					'key'       => $key,
					'type'      => $f[1],
					'label_for' => 'bmn_' . $key,
				)
			);
		}
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$out = array();
		$input = is_array( $input ) ? $input : array();
		foreach ( self::fields() as $key => $f ) {
			$val = $input[ $key ] ?? '';
			switch ( $f[1] ) {
				case 'url':
					$out[ $key ] = esc_url_raw( (string) $val, array( 'http', 'https' ) );
					break;
				case 'email':
					$out[ $key ] = sanitize_email( (string) $val );
					break;
				case 'page':
					$out[ $key ] = absint( $val );
					break;
				default:
					$out[ $key ] = sanitize_text_field( (string) $val );
			}
		}
		return $out;
	}

	/**
	 * Render a field.
	 *
	 * @param array $args Args.
	 */
	public static function field( $args ) {
		$key  = $args['key'];
		$val  = bmn_option( $key, '' );
		$name = self::OPTION . '[' . $key . ']';
		if ( 'page' === $args['type'] ) {
			wp_dropdown_pages(
				array(
					'name'              => esc_attr( $name ),
					'id'                => esc_attr( 'bmn_' . $key ),
					'selected'          => absint( $val ),
					'show_option_none'  => esc_html__( '— انتخاب —', 'boshrouyeh-core' ),
					'option_none_value' => '0',
				)
			);
			return;
		}
		$type = in_array( $args['type'], array( 'url', 'email' ), true ) ? $args['type'] : 'text';
		printf( '<input class="regular-text" type="%s" id="%s" name="%s" value="%s">', esc_attr( $type ), esc_attr( 'bmn_' . $key ), esc_attr( $name ), esc_attr( (string) $val ) );
	}

	/**
	 * Render page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'تنظیمات شهرداری بشرویه', 'boshrouyeh-core' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'bmn_settings_group' );
				do_settings_sections( 'bmn-settings' );
				submit_button();
				?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'محتوای نمونه', 'boshrouyeh-core' ); ?></h2>
			<p><?php esc_html_e( 'دسته‌ها، ۳۸ خدمت، جاذبه‌ها، برگه‌ها و نمونهٔ اطلاعیه/مناقصه را می‌سازد (فقط مواردی که وجود ندارند).', 'boshrouyeh-core' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bmn_demo_import' ); ?>
				<input type="hidden" name="action" value="bmn_demo_import">
				<?php submit_button( __( 'درون‌ریزی محتوای پایه', 'boshrouyeh-core' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}
}
