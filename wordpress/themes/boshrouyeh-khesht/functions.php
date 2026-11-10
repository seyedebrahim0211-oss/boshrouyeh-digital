<?php
/**
 * Khesht theme bootstrap.
 *
 * @package BoshrouyehKhesht
 */

defined( 'ABSPATH' ) || exit;

define( 'KHESHT_VERSION', '1.0.0' );
define( 'KHESHT_DIR', get_template_directory() );
define( 'KHESHT_URI', get_template_directory_uri() );

require_once KHESHT_DIR . '/inc/icons.php';
require_once KHESHT_DIR . '/inc/setup.php';
require_once KHESHT_DIR . '/inc/block-styles.php';
require_once KHESHT_DIR . '/inc/template-tags.php';
