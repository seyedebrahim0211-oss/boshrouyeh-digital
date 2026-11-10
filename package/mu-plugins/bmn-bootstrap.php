<?php
/**
 * Plugin Name: Boshrouyeh Bootstrap (one-time setup)
 * Description: پس از نصب وردپرس، قالب و افزونه‌های شهرداری را فعال و تنظیمات اولیه و محتوای نمونه را ایجاد می‌کند. بعد از اجرا بی‌اثر است و می‌توانید حذفش کنید.
 * Version:     1.0.0
 * License:     GPL-2.0-or-later
 *
 * @package Boshrouyeh
 */

defined( 'ABSPATH' ) || exit;

/**
 * Run once, on the first admin request after WordPress is installed.
 */
function bmn_bootstrap_run() {
	if ( wp_installing() || get_option( 'bmn_bootstrap_done' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	// Lock first so concurrent requests don't run it twice.
	if ( ! add_option( 'bmn_bootstrap_done', 'running', '', false ) ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	$log     = array();
	$plugins = array(
		'boshrouyeh-core/boshrouyeh-core.php',
		'boshrouyeh-eservices/boshrouyeh-eservices.php',
		'khesht-builder/khesht-builder.php',
	);
	foreach ( $plugins as $plugin ) {
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			$log[] = 'missing: ' . $plugin;
			continue;
		}
		if ( ! is_plugin_active( $plugin ) ) {
			$r = activate_plugin( $plugin );
			$log[] = is_wp_error( $r ) ? $plugin . ': ' . $r->get_error_message() : 'activated: ' . $plugin;
		}
	}

	$theme = wp_get_theme( 'boshrouyeh-khesht' );
	if ( $theme->exists() && ! $theme->errors() ) {
		switch_theme( 'boshrouyeh-khesht' );
		$log[] = 'theme: boshrouyeh-khesht';
	}

	update_option( 'timezone_string', 'Asia/Tehran' );
	update_option( 'date_format', 'j F Y' );
	update_option( 'time_format', 'H:i' );
	update_option( 'start_of_week', 6 );
	update_option( 'default_comment_status', 'closed' );
	update_option( 'default_ping_status', 'closed' );
	update_option( 'users_can_register', 0 );
	if ( in_array( get_option( 'blogname' ), array( '', 'My WordPress Website', 'My Blog' ), true ) ) {
		update_option( 'blogname', 'شهرداری بشرویه' );
	}
	update_option( 'blogdescription', 'درگاه خدمات الکترونیک شهروندی' );
	update_option( 'WPLANG', 'fa_IR' );

	// Sample content shipped by the core plugin (services, news, pages, menus).
	if ( class_exists( 'BMN_Demo' ) && method_exists( 'BMN_Demo', 'run' ) ) {
		$log[] = 'demo items: ' . (int) BMN_Demo::run();
	}

	// Remove stock sample content.
	$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
	if ( $hello ) {
		wp_delete_post( $hello->ID, true );
	}
	$sample = get_page_by_path( 'sample-page', OBJECT, 'page' );
	if ( $sample ) {
		wp_delete_post( $sample->ID, true );
	}

	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '/%postname%/' );
	update_option( 'bmn_flush_rewrite', 1 );

	update_option( 'bmn_bootstrap_done', gmdate( 'c' ), false );
	update_option( 'bmn_bootstrap_log', $log, false );
	set_transient( 'bmn_bootstrap_notice', 1, HOUR_IN_SECONDS );
}
add_action( 'admin_init', 'bmn_bootstrap_run', 1 );

/**
 * Flush rewrites on the request after setup (CPTs are registered by then).
 */
add_action(
	'init',
	function () {
		if ( get_option( 'bmn_flush_rewrite' ) ) {
			delete_option( 'bmn_flush_rewrite' );
			flush_rewrite_rules();
		}
	},
	99
);

add_action(
	'admin_notices',
	function () {
		if ( ! get_transient( 'bmn_bootstrap_notice' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_transient( 'bmn_bootstrap_notice' );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'راه‌اندازی شهرداری بشرویه انجام شد: قالب خشت و افزونه‌ها فعال و محتوای نمونه ساخته شد. کلیدهای پیامک و درگاه پرداخت را از منوی «شهرداری» تنظیم کنید.', 'boshrouyeh-core' ) . '</p></div>';
	}
);
