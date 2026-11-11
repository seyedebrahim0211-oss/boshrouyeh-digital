<?php
/**
 * Plugin Name:       Boshrouyeh Media — رسانه، مشارکت و دسترسی‌ها
 * Description:       اکسپلور اخبار و گزارش تصویری، ویدیوی آپارات، اشتراک‌گذاری، انتشار خودکار در بله/ایتا/تلگرام/روبیکا پس از تأیید روابط عمومی، دریافت خبر از پیام‌رسان، سامانهٔ پیامک گروهی، نظرسنجی و بودجهٔ مشارکتی، استعلام پروانه، نوبت‌دهی واحدها، مدیریت دسترسی‌ها و پوستهٔ سنتی پیشخوان.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Requires Plugins:  boshrouyeh-core
 * Author:            Boshrouyeh Municipality Digital Team
 * License:           GPL-2.0-or-later
 * Text Domain:       boshrouyeh-media
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

define( 'BMNM_VERSION', '1.0.0' );
define( 'BMNM_FILE', __FILE__ );
define( 'BMNM_DIR', plugin_dir_path( __FILE__ ) );
define( 'BMNM_URL', plugin_dir_url( __FILE__ ) );

foreach ( array( 'helpers', 'class-content', 'class-explore', 'class-social', 'class-inbound', 'class-sms-panel', 'class-access', 'class-admin-skin', 'class-participation', 'class-permits', 'class-appointments' ) as $bmnm_file ) {
	require_once BMNM_DIR . 'includes/' . $bmnm_file . '.php';
}

add_action(
	'plugins_loaded',
	static function () {
		BMNM_Content::init();
		BMNM_Explore::init();
		BMNM_Social::init();
		BMNM_Inbound::init();
		BMNM_SMS_Panel::init();
		BMNM_Access::init();
		BMNM_Admin_Skin::init();
		BMNM_Participation::init();
		BMNM_Permits::init();
		BMNM_Appointments::init();
	},
	20
);

add_action(
	'admin_init',
	static function () {
		if ( BMNM_VERSION !== get_option( 'bmnm_version' ) ) {
			bmnm_install();
		}
	}
);

/**
 * Tables, caps and pages (idempotent).
 */
function bmnm_install() {
	BMNM_SMS_Panel::install();
	BMNM_Participation::install();
	BMNM_Appointments::install();
	BMNM_Access::install_caps();
	$pages = array(
		'bmnm_explore_page' => array( 'explore', 'اخبار و گزارش‌های تصویری', '<!-- wp:shortcode -->[bmn_explore]<!-- /wp:shortcode -->' ),
		'bmnm_permit_page'  => array( 'permit', 'استعلام پروانهٔ ساختمانی', '<!-- wp:shortcode -->[bmn_permit_track]<!-- /wp:shortcode -->' ),
		'bmnm_appt_page'    => array( 'unit-appointment', 'نوبت‌دهی واحدهای شهرداری', '<!-- wp:shortcode -->[bmn_unit_appointment]<!-- /wp:shortcode -->' ),
		'bmnm_polls_page'   => array( 'citizen-participation', 'مشارکت شهروندی: نظرسنجی و بودجهٔ مشارکتی', '<!-- wp:shortcode -->[bmn_polls]<!-- /wp:shortcode -->' ),
	);
	foreach ( $pages as $opt => $p ) {
		$id = (int) get_option( $opt );
		if ( $id && get_post( $id ) ) {
			continue;
		}
		$ex = get_page_by_path( $p[0] );
		$id = $ex ? $ex->ID : wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $p[0],
				'post_title'   => $p[1],
				'post_content' => $p[2],
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( $opt, (int) $id, false );
		}
	}
	bmnm_seed_services();
	update_option( 'bmnm_version', BMNM_VERSION, false );
}

/**
 * Add the new e-services to the core service directory (once, as published services).
 */
function bmnm_seed_services() {
	if ( ! post_type_exists( 'bmn_service' ) ) {
		return;
	}
	$link = static fn( $opt ) => (string) get_permalink( (int) get_option( $opt ) );
	$defs = array(
		'permit-status'         => array( 'استعلام وضعیت پروندهٔ پروانهٔ ساختمانی', 'استعلام پروانه', 'doc-check', 'urban', 'bmnm_permit_page', 'با شمارهٔ پرونده و ۴ رقم آخر کد ملی مالک، مرحلهٔ پروندهٔ پروانه و تاریخچهٔ آن را آنلاین ببینید.', "ورود به صفحهٔ استعلام پروانه\nوارد کردن شمارهٔ پرونده\nوارد کردن ۴ رقم آخر کد ملی مالک\nمشاهدهٔ مرحله و تاریخچهٔ پرونده", "شمارهٔ پرونده (روی رسید پذیرش)\nکد ملی مالک" ),
		'unit-appointment'      => array( 'نوبت‌دهی اینترنتی واحدهای شهرداری', 'نوبت واحدها', 'calendar', 'city', 'bmnm_appt_page', 'برای مراجعه به شهرسازی، درآمد، خدمات شهری و سایر واحدها بدون صف نوبت بگیرید؛ کد نوبت پیامک می‌شود.', "ورود با شمارهٔ همراه و کد پیامکی\nانتخاب واحد، روز و ساعت\nثبت موضوع مراجعه\nدریافت کد نوبت با پیامک", "کارت ملی هنگام مراجعه" ),
		'participatory-budget'  => array( 'نظرسنجی و بودجه‌ریزی مشارکتی', 'مشارکت شهروندی', 'chart', 'participation', 'bmnm_polls_page', 'در نظرسنجی‌های شهری شرکت کنید و در بودجه‌ریزی مشارکتی، طرح‌های اولویت‌دار محلهٔ خود را انتخاب کنید.', "ورود با شمارهٔ همراه و کد پیامکی\nانتخاب نظرسنجی یا طرح مشارکتی\nانتخاب گزینه‌ها یا طرح‌ها تا سقف بودجه\nثبت رأی (یک رأی برای هر شهروند)", "شمارهٔ همراه" ),
	);
	foreach ( $defs as $slug => $d ) {
		if ( get_page_by_path( $slug, OBJECT, 'bmn_service' ) ) {
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'bmn_service',
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $d[0],
				'post_excerpt' => $d[5],
				'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $d[5] ) . '</p><!-- /wp:paragraph -->',
				'meta_input'   => array(
					'_bmn_short_title'  => $d[1],
					'_bmn_icon'         => $d[2],
					'_bmn_mode'         => 'online',
					'_bmn_duration'     => 'فوری',
					'_bmn_cost'         => 'رایگان',
					'_bmn_external_url' => $link( $d[4] ),
					'_bmn_steps'        => $d[6],
					'_bmn_documents'    => $d[7],
				),
			)
		);
		if ( $id && ! is_wp_error( $id ) && taxonomy_exists( 'bmn_service_cat' ) ) {
			wp_set_object_terms( $id, $d[3], 'bmn_service_cat' );
		}
	}
}

register_activation_hook(
	__FILE__,
	static function () {
		BMNM_Content::register();
		BMNM_Participation::register();
		BMNM_Permits::register();
		bmnm_install();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'bmnm_sms_run' );
		flush_rewrite_rules();
	}
);
