<?php
/**
 * Plugin Name:       Boshrouyeh E-Services — خدمات الکترونیک شهرداری بشرویه
 * Plugin URI:        https://boshrooyeh.ir
 * Description:       ورود با کد پیامکی، پیشخوان شهروند، سامانه ۱۳۷ با عکس و موقعیت، پیگیری با کد، استعلام و پرداخت آنلاین عوارض با رسید، نوبت ملاقات، انتقاد و پیشنهاد، و API امن (JWT) برای اپ «بشرویهٔ من».
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Requires Plugins:  boshrouyeh-core
 * Author:            Boshrouyeh Municipality Digital Team
 * License:           GPL-2.0-or-later
 * Text Domain:       boshrouyeh-eservices
 *
 * @package BoshrouyehEServices
 */

defined( 'ABSPATH' ) || exit;

define( 'BMN_ES_VERSION', '1.1.0' );
define( 'BMN_ES_FILE', __FILE__ );
define( 'BMN_ES_DIR', plugin_dir_path( __FILE__ ) );
define( 'BMN_ES_URL', plugin_dir_url( __FILE__ ) );

require_once BMN_ES_DIR . 'includes/class-install.php';
require_once BMN_ES_DIR . 'includes/class-sms.php';
require_once BMN_ES_DIR . 'includes/class-jwt.php';
require_once BMN_ES_DIR . 'includes/class-auth.php';
require_once BMN_ES_DIR . 'includes/class-requests.php';
require_once BMN_ES_DIR . 'includes/class-dues.php';
require_once BMN_ES_DIR . 'includes/class-payments.php';
require_once BMN_ES_DIR . 'includes/class-blocks.php';
require_once BMN_ES_DIR . 'includes/class-admin.php';
require_once BMN_ES_DIR . 'includes/class-staff.php';
require_once BMN_ES_DIR . 'includes/class-visits.php';
require_once BMN_ES_DIR . 'includes/class-fleet.php';
require_once BMN_ES_DIR . 'includes/class-es-demo.php';

register_activation_hook( __FILE__, array( 'BMN_ES_Install', 'activate' ) );

add_action( 'plugins_loaded', 'bmn_es_boot', 20 );
/**
 * Boot.
 */
function bmn_es_boot() {
	if ( ! defined( 'BMN_CORE_VERSION' ) ) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'افزونهٔ خدمات الکترونیک به «Boshrouyeh Core» نیاز دارد.', 'boshrouyeh-eservices' ) . '</p></div>';
			}
		);
		return;
	}
	load_plugin_textdomain( 'boshrouyeh-eservices', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	BMN_Staff::init();
	BMN_Visits::init();
	BMN_Fleet::init();
	BMN_ES_Demo::init();
	add_action( 'init', array( 'BMN_ES_Install', 'maybe_upgrade' ), 20 );
	BMN_Auth::init();
	BMN_Requests::init();
	BMN_Dues::init();
	BMN_Payments::init();
	BMN_ES_Blocks::init();
	BMN_ES_Admin::init();
}

/**
 * E-services setting.
 *
 * @param string $key     Key.
 * @param mixed  $default Default.
 * @return mixed
 */
function bmn_es_option( $key, $default = '' ) {
	$opts = get_option( 'bmn_es_settings', array() );
	return ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) ? $opts[ $key ] : $default;
}

/**
 * Standard JSON error.
 *
 * @param string $code    Code.
 * @param string $message Message.
 * @param int    $status  HTTP status.
 * @return WP_Error
 */
function bmn_es_error( $code, $message, $status = 400 ) {
	return new WP_Error( 'bmn_' . $code, $message, array( 'status' => $status ) );
}
