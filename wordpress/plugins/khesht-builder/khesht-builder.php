<?php
/**
 * Plugin Name:       Khesht Builder — صفحه‌ساز خشت
 * Plugin URI:        https://boshrooyeh.ir
 * Description:       صفحه‌ساز بومی و کشیدنی (Drag & Drop) هماهنگ با هویت بصری شهرداری بشرویه: بخش‌ها و ستون‌ها، ۴۰ ویجت برند (سربرگ خط آسمان، میانبر خدمات، اخبار، آمار، مراحل، پرسش‌ها، فرم‌های خدمات الکترونیک و…)، قالب‌های آماده، پیش‌نمایش زنده موبایل/تبلت/دسکتاپ، واگرد/ازنو و رندر امن سمت سرور.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Boshrouyeh Municipality Digital Team
 * License:           GPL-2.0-or-later
 * Text Domain:       khesht-builder
 *
 * @package KheshtBuilder
 */

defined( 'ABSPATH' ) || exit;

define( 'KB_VERSION', '1.0.0' );
define( 'KB_FILE', __FILE__ );
define( 'KB_DIR', plugin_dir_path( __FILE__ ) );
define( 'KB_URL', plugin_dir_url( __FILE__ ) );
define( 'KB_META', '_kb_layout' );

require_once KB_DIR . 'includes/class-kb-widgets.php';
require_once KB_DIR . 'includes/class-kb-sanitizer.php';
require_once KB_DIR . 'includes/class-kb-render.php';
require_once KB_DIR . 'includes/class-kb-templates.php';
require_once KB_DIR . 'includes/class-kb-rest.php';
require_once KB_DIR . 'includes/class-kb-admin.php';
require_once KB_DIR . 'includes/class-kb-front.php';

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'khesht-builder', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		KB_Rest::init();
		KB_Admin::init();
		KB_Front::init();
	},
	30
);

/**
 * Is the builder enabled for a post?
 *
 * @param int $post_id Post.
 * @return bool
 */
function kb_enabled( $post_id ) {
	return (bool) get_post_meta( $post_id, '_kb_enabled', true );
}

/**
 * Get stored (sanitized) layout.
 *
 * @param int $post_id Post.
 * @return array
 */
function kb_get_layout( $post_id ) {
	$raw = get_post_meta( $post_id, KB_META, true );
	$arr = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	return is_array( $arr ) ? KB_Sanitizer::layout( $arr ) : array( 'version' => 1, 'sections' => array() );
}

/**
 * Post types the builder supports.
 *
 * @return string[]
 */
function kb_post_types() {
	return apply_filters( 'kb_post_types', array( 'page', 'post', 'bmn_service', 'bmn_place' ) );
}
