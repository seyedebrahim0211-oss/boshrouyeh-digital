<?php
/**
 * Plugin Name:       Boshrouyeh Core — هستهٔ شهرداری بشرویه
 * Plugin URI:        https://boshrooyeh.ir
 * Description:       انواع نوشتهٔ سفارشی (خدمت، اطلاعیه، مناقصه، جاذبه، رویداد، شفافیت، متوفیان)، تاریخ شمسی، بلوک‌های پویا، REST API اپ، داده‌های ساختاریافته، PWA و سخت‌سازی امنیتی.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Boshrouyeh Municipality Digital Team
 * License:           GPL-2.0-or-later
 * Text Domain:       boshrouyeh-core
 * Domain Path:       /languages
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

define( 'BMN_CORE_VERSION', '1.1.0' );
define( 'BMN_CORE_FILE', __FILE__ );
define( 'BMN_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BMN_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once BMN_CORE_DIR . 'includes/helpers.php';
require_once BMN_CORE_DIR . 'includes/class-jalali.php';
require_once BMN_CORE_DIR . 'includes/class-fields.php';
require_once BMN_CORE_DIR . 'includes/class-post-types.php';
require_once BMN_CORE_DIR . 'includes/class-settings.php';
require_once BMN_CORE_DIR . 'includes/class-blocks.php';
require_once BMN_CORE_DIR . 'includes/class-rest.php';
require_once BMN_CORE_DIR . 'includes/class-seo.php';
require_once BMN_CORE_DIR . 'includes/class-security.php';
require_once BMN_CORE_DIR . 'includes/class-pwa.php';
require_once BMN_CORE_DIR . 'includes/class-demo.php';
require_once BMN_CORE_DIR . 'includes/class-news-import.php';
require_once BMN_CORE_DIR . 'includes/class-app-update.php';

add_action( 'plugins_loaded', 'bmn_core_boot' );
/**
 * Boot all modules.
 */
function bmn_core_boot() {
	load_plugin_textdomain( 'boshrouyeh-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	BMN_Jalali::init();
	BMN_Post_Types::init();
	BMN_Fields::init();
	BMN_Settings::init();
	BMN_Blocks::init();
	BMN_Rest::init();
	BMN_SEO::init();
	BMN_Security::init();
	BMN_PWA::init();
	BMN_Demo::init();
	BMN_News_Import::init();
	BMN_App_Update::init();
}

register_activation_hook( __FILE__, 'bmn_core_activate' );
/**
 * Activation: register CPTs then flush rewrites.
 */
function bmn_core_activate() {
	BMN_Post_Types::register();
	BMN_PWA::rewrite();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'bmn_core_deactivate' );
/**
 * Deactivation: clear cron and rewrites.
 */
function bmn_core_deactivate() {
	wp_clear_scheduled_hook( 'bmn_news_sync' );
	flush_rewrite_rules();
}
