<?php
/**
 * Uninstall: keeps content and votes (municipal records); removes only transient settings.
 *
 * @package BoshrouyehMedia
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
wp_clear_scheduled_hook( 'bmnm_sms_run' );
delete_option( 'bmnm_log' );
delete_transient( 'bmnm_dash_stats' );
