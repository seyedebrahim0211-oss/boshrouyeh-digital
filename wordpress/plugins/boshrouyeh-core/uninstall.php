<?php
/**
 * Uninstall: remove settings only. Content (posts, terms) is preserved deliberately.
 *
 * @package BoshrouyehCore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'bmn_settings' );
