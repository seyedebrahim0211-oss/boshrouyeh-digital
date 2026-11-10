<?php
/**
 * Theme setup, assets and pattern categories.
 *
 * @package BoshrouyehKhesht
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'khesht_setup' );
/**
 * Theme supports.
 */
function khesht_setup() {
	load_theme_textdomain( 'boshrouyeh-khesht', KHESHT_DIR . '/languages' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_editor_style( 'assets/css/khesht.css' );

	add_image_size( 'khesht-card', 640, 480, true );   // 4:3 cards.
	add_image_size( 'khesht-hero', 1600, 900, true );  // 16:9 hero.
}

add_action( 'wp_enqueue_scripts', 'khesht_assets' );
/**
 * Front-end assets.
 */
function khesht_assets() {
	$css = KHESHT_DIR . '/assets/css/khesht.css';
	$js  = KHESHT_DIR . '/assets/js/khesht.js';
	wp_enqueue_style( 'khesht', KHESHT_URI . '/assets/css/khesht.css', array(), (string) filemtime( $css ) );
	wp_enqueue_script(
		'khesht',
		KHESHT_URI . '/assets/js/khesht.js',
		array(),
		(string) filemtime( $js ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_localize_script(
		'khesht',
		'KheshtI18n',
		array(
			'dark'      => __( 'حالت شب کویر', 'boshrouyeh-khesht' ),
			'light'     => __( 'حالت روز', 'boshrouyeh-khesht' ),
			'days'      => __( 'روز', 'boshrouyeh-khesht' ),
			'hours'     => __( 'ساعت', 'boshrouyeh-khesht' ),
			'minutes'   => __( 'دقیقه', 'boshrouyeh-khesht' ),
			'expired'   => __( 'مهلت به پایان رسید', 'boshrouyeh-khesht' ),
			'copied'    => __( 'کپی شد', 'boshrouyeh-khesht' ),
		)
	);
}

add_action( 'wp_head', 'khesht_head_meta', 1 );
/**
 * Preload primary fonts, theme colour and early colour-scheme script (prevents dark-mode flash).
 */
function khesht_head_meta() {
	$fonts = array( 'vazirmatn-arabic-400-normal.woff2', 'lalezar-arabic-400-normal.woff2' );
	foreach ( $fonts as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( KHESHT_URI . '/assets/fonts/' . $font )
		);
	}
	echo '<meta name="theme-color" content="#1F2A44">' . "\n";
	// Inline, static, no user input.
	echo "<script>(function(){try{var t=localStorage.getItem('kh-theme'),f=localStorage.getItem('kh-font');if(t==='dark'){document.documentElement.setAttribute('data-theme','dark');}if(f){document.documentElement.setAttribute('data-font',f);}}catch(e){}})();</script>\n";
}

add_action( 'init', 'khesht_pattern_categories' );
/**
 * Register pattern categories.
 */
function khesht_pattern_categories() {
	$cats = array(
		'khesht-home'     => __( 'خشت: صفحه اصلی', 'boshrouyeh-khesht' ),
		'khesht-services' => __( 'خشت: خدمات', 'boshrouyeh-khesht' ),
		'khesht-sections' => __( 'خشت: بخش‌ها', 'boshrouyeh-khesht' ),
		'khesht-pages'    => __( 'خشت: صفحات کامل', 'boshrouyeh-khesht' ),
	);
	foreach ( $cats as $slug => $label ) {
		register_block_pattern_category( $slug, array( 'label' => $label ) );
	}
}

add_action( 'admin_notices', 'khesht_plugin_notice' );
/**
 * Remind admins to activate companion plugins.
 */
function khesht_plugin_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$missing = array();
	if ( ! defined( 'BMN_CORE_VERSION' ) ) {
		$missing[] = 'Boshrouyeh Core';
	}
	if ( ! defined( 'BMN_ES_VERSION' ) ) {
		$missing[] = 'Boshrouyeh E-Services';
	}
	if ( ! $missing ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s <strong>%s</strong></p></div>',
		esc_html__( 'قالب خشت برای نمایش کامل خدمات به این افزونه‌ها نیاز دارد:', 'boshrouyeh-khesht' ),
		esc_html( implode( '، ', $missing ) )
	);
}

add_filter( 'body_class', 'khesht_body_class' );
/**
 * Body classes.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function khesht_body_class( $classes ) {
	$classes[] = 'khesht';
	return $classes;
}

/*
 * The site is Persian-first. If the fa_IR language pack is not installed yet, still render the
 * front end right-to-left with lang="fa-IR" (filterable for an English mirror site).
 */
if ( ! is_admin() && apply_filters( 'khesht_force_rtl', true ) ) {
	global $wp_locale;
	if ( isset( $wp_locale ) && $wp_locale instanceof WP_Locale ) {
		$wp_locale->text_direction = 'rtl';
	}
	add_filter(
		'language_attributes',
		static function ( $output ) {
			if ( str_starts_with( get_locale(), 'fa' ) ) {
				return $output;
			}
			return 'dir="rtl" lang="fa-IR"';
		}
	);
}
