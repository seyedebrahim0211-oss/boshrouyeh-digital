<?php
/**
 * Auto-publish approved news/reports to messenger channels: Bale, Eitaa (Eitaayar), Telegram, Rubika.
 *
 * @package BoshrouyehMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Social publisher.
 */
final class BMNM_Social {

	const OPT  = 'bmnm_social';
	const HOOK = 'bmnm_social_send';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_post_bmnm_social_save', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_bmnm_social_test', array( __CLASS__, 'test' ) );
		add_action( self::HOOK, array( __CLASS__, 'send' ), 10, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 20, 3 );
		add_action( 'bmnm_pr_approved', array( __CLASS__, 'maybe_queue' ) );
	}

	/**
	 * Networks.
	 *
	 * @return array<string,string>
	 */
	public static function networks() {
		return array(
			'bale'     => 'بله',
			'eitaa'    => 'ایتا',
			'telegram' => 'تلگرام',
			'rubika'   => 'روبیکا',
		);
	}

	/**
	 * Label.
	 *
	 * @param string $k Key.
	 * @return string
	 */
	public static function label( $k ) {
		return self::networks()[ $k ] ?? $k;
	}

	/**
	 * Status label.
	 *
	 * @param string $s Status.
	 * @return string
	 */
	public static function status_label( $s ) {
		return array(
			'queued' => 'در صف ارسال',
			'sent'   => 'ارسال شد',
			'failed' => 'ناموفق',
			'retry'  => 'تلاش دوباره',
		)[ $s ] ?? $s;
	}

	/**
	 * Settings.
	 *
	 * @return array
	 */
	public static function opts() {
		$o = get_option( self::OPT, array() );
		return is_array( $o ) ? $o : array();
	}

	/**
	 * Per-network setting.
	 *
	 * @param string $net Network.
	 * @param string $key Key.
	 * @return string
	 */
	public static function net( $net, $key ) {
		$o = self::opts();
		return isset( $o[ $net ][ $key ] ) ? (string) $o[ $net ][ $key ] : '';
	}

	/**
	 * Networks enabled for publishing.
	 *
	 * @return array<string,string>
	 */
	public static function enabled_networks() {
		$out = array();
		foreach ( self::networks() as $k => $l ) {
			if ( self::net( $k, 'on' ) && self::net( $k, 'token' ) && self::net( $k, 'chat' ) ) {
				$out[ $k ] = $l;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------ Queue */

	/**
	 * Scheduled → published.
	 *
	 * @param string  $new New.
	 * @param string  $old Old.
	 * @param WP_Post $p   Post.
	 */
	public static function on_transition( $new, $old, $p ) {
		if ( 'publish' === $new && 'publish' !== $old && in_array( $p->post_type, bmnm_types(), true ) ) {
			self::maybe_queue( $p->ID );
		}
	}

	/**
	 * Queue if published + approved and not yet sent.
	 *
	 * @param int $id Post.
	 */
	public static function maybe_queue( $id ) {
		if ( 'publish' === get_post_status( $id ) && BMNM_Content::approved( $id ) ) {
			self::queue( $id, false );
		}
	}

	/**
	 * Queue selected networks.
	 *
	 * @param int  $id    Post.
	 * @param bool $force Resend even if already sent.
	 */
	public static function queue( $id, $force = false ) {
		if ( 'publish' !== get_post_status( $id ) || ! BMNM_Content::approved( $id ) ) {
			return;
		}
		$nets = get_post_meta( $id, '_bmn_social_nets', true );
		$nets = is_array( $nets ) ? $nets : array_keys( self::enabled_networks() );
		$skip = (array) get_post_meta( $id, '_bmn_social_skip', false );
		$log  = get_post_meta( $id, '_bmn_social_log', true );
		$log  = is_array( $log ) ? $log : array();
		$i    = 0;
		foreach ( array_intersect( $nets, array_keys( self::enabled_networks() ) ) as $net ) {
			if ( in_array( $net, $skip, true ) && ! $force ) {
				continue;
			}
			$state = $log[ $net ]['s'] ?? '';
			if ( ! $force && in_array( $state, array( 'sent', 'queued', 'retry' ), true ) ) {
				continue;
			}
			$log[ $net ] = array( 's' => 'queued', 't' => time(), 'm' => '' );
			wp_schedule_single_event( time() + 5 + ( $i++ * 3 ), self::HOOK, array( (int) $id, $net, 1 ) );
		}
		update_post_meta( $id, '_bmn_social_log', $log );
		if ( $i ) {
			spawn_cron();
		}
	}

	/**
	 * Cron: send one post to one network.
	 *
	 * @param int    $id      Post.
	 * @param string $net     Network.
	 * @param int    $attempt Attempt.
	 */
	public static function send( $id, $net, $attempt = 1 ) {
		$p = get_post( $id );
		if ( ! $p || 'publish' !== $p->post_status || ! isset( self::enabled_networks()[ $net ] ) ) {
			return;
		}
		$r   = self::dispatch( $net, $p );
		$log = get_post_meta( $id, '_bmn_social_log', true );
		$log = is_array( $log ) ? $log : array();
		if ( true === $r ) {
			$log[ $net ] = array( 's' => 'sent', 't' => time(), 'm' => '' );
			bmnm_log( 'social', self::label( $net ) . ' ✔ ' . get_the_title( $p ) );
		} else {
			$msg = is_wp_error( $r ) ? $r->get_error_message() : 'خطای نامشخص';
			if ( $attempt < 3 ) {
				$log[ $net ] = array( 's' => 'retry', 't' => time(), 'm' => $msg );
				wp_schedule_single_event( time() + 300 * $attempt, self::HOOK, array( (int) $id, $net, $attempt + 1 ) );
			} else {
				$log[ $net ] = array( 's' => 'failed', 't' => time(), 'm' => $msg );
			}
			bmnm_log( 'social', self::label( $net ) . ' ✖ ' . get_the_title( $p ) . ' — ' . $msg );
		}
		update_post_meta( $id, '_bmn_social_log', $log );
	}

	/**
	 * Route to a sender.
	 *
	 * @param string       $net Network.
	 * @param WP_Post|null $p   Post (null = test message).
	 * @param string       $text Test text.
	 * @return true|WP_Error
	 */
	public static function dispatch( $net, $p, $text = '' ) {
		$token = self::net( $net, 'token' );
		$chat  = self::net( $net, 'chat' );
		$imgs  = array();
		if ( $p ) {
			foreach ( array_slice( bmnm_images( $p->ID ), 0, 10 ) as $att ) {
				$f = bmnm_attachment_file( $att );
				if ( $f ) {
					$imgs[] = $f;
				}
			}
		}
		$long  = $p ? bmnm_caption( $p, '', 4000 ) : $text;
		$short = $p ? bmnm_caption( $p, '', 1000 ) : $text;
		switch ( $net ) {
			case 'telegram':
				return self::botapi( 'https://api.telegram.org/bot' . $token . '/', $chat, $imgs, $short, $long );
			case 'bale':
				return self::botapi( 'https://tapi.bale.ai/bot' . $token . '/', $chat, $imgs, $short, $long );
			case 'eitaa':
				return self::eitaa( $token, $chat, $imgs, $short, $long );
			case 'rubika':
				return self::rubika( $token, $chat, $imgs, $long );
		}
		return bmnm_error( 'net', 'شبکهٔ نامعتبر' );
	}

	/**
	 * HTTP + JSON decode.
	 *
	 * @param string $url  URL.
	 * @param array  $args Args.
	 * @return array|WP_Error
	 */
	private static function http( $url, $args ) {
		$args += array( 'timeout' => 45 );
		$res   = wp_remote_post( $url, $args );
		if ( is_wp_error( $res ) ) {
			return bmnm_error( 'http', 'اتصال برقرار نشد: ' . $res->get_error_message() );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			return bmnm_error( 'http', 'پاسخ نامعتبر (HTTP ' . (int) wp_remote_retrieve_response_code( $res ) . ')' );
		}
		return $data;
	}

	/**
	 * Telegram-compatible Bot API (Telegram, Bale).
	 *
	 * @param string $base  Base URL ending with /.
	 * @param string $chat  Chat id / @channel.
	 * @param array  $imgs  Files.
	 * @param string $short Caption (≤1024).
	 * @param string $long  Text (≤4096).
	 * @return true|WP_Error
	 */
	private static function botapi( $base, $chat, $imgs, $short, $long ) {
		$ok = static fn( $d ) => is_array( $d ) && ! empty( $d['ok'] );
		if ( count( $imgs ) >= 2 ) {
			$media = array();
			$files = array();
			foreach ( $imgs as $i => $f ) {
				$m = array( 'type' => 'photo', 'media' => 'attach://f' . $i );
				if ( 0 === $i ) {
					$m['caption'] = $short;
				}
				$media[]        = $m;
				$files[ 'f' . $i ] = $f;
			}
			list( $body, $ct ) = bmnm_multipart( array( 'chat_id' => $chat, 'media' => wp_json_encode( $media, JSON_UNESCAPED_UNICODE ) ), $files );
			$d = self::http( $base . 'sendMediaGroup', array( 'body' => $body, 'headers' => array( 'Content-Type' => $ct ), 'timeout' => 90 ) );
			if ( $ok( $d ) ) {
				return true;
			}
			$imgs = array_slice( $imgs, 0, 1 ); // Fallback: single photo.
		}
		if ( $imgs ) {
			list( $body, $ct ) = bmnm_multipart( array( 'chat_id' => $chat, 'caption' => $short ), array( 'photo' => $imgs[0] ) );
			$d = self::http( $base . 'sendPhoto', array( 'body' => $body, 'headers' => array( 'Content-Type' => $ct ) ) );
		} else {
			$d = self::http( $base . 'sendMessage', array( 'body' => array( 'chat_id' => $chat, 'text' => $long ) ) );
		}
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		return $ok( $d ) ? true : bmnm_error( 'api', (string) ( $d['description'] ?? 'رد شد' ) );
	}

	/**
	 * Eitaa via Eitaayar API.
	 *
	 * @param string $token Token.
	 * @param string $chat  Channel id.
	 * @param array  $imgs  Files.
	 * @param string $short Caption.
	 * @param string $long  Text.
	 * @return true|WP_Error
	 */
	private static function eitaa( $token, $chat, $imgs, $short, $long ) {
		$base = 'https://eitaayar.ir/api/' . rawurlencode( $token ) . '/';
		if ( $imgs ) {
			list( $body, $ct ) = bmnm_multipart( array( 'chat_id' => $chat, 'caption' => $short ), array( 'file' => $imgs[0] ) );
			$d = self::http( $base . 'sendFile', array( 'body' => $body, 'headers' => array( 'Content-Type' => $ct ) ) );
		} else {
			$d = self::http( $base . 'sendMessage', array( 'body' => array( 'chat_id' => $chat, 'text' => $long ) ) );
		}
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		return ! empty( $d['ok'] ) ? true : bmnm_error( 'api', (string) ( $d['description'] ?? 'رد شد' ) );
	}

	/**
	 * Rubika Bot API v3.
	 *
	 * @param string $token Token.
	 * @param string $chat  Channel guid.
	 * @param array  $imgs  Files.
	 * @param string $text  Text.
	 * @return true|WP_Error
	 */
	private static function rubika( $token, $chat, $imgs, $text ) {
		$base = 'https://botapi.rubika.ir/v3/' . rawurlencode( $token ) . '/';
		$json = static fn( $method, $body ) => self::http( $base . $method, array( 'body' => wp_json_encode( $body, JSON_UNESCAPED_UNICODE ), 'headers' => array( 'Content-Type' => 'application/json' ) ) );
		$ok   = static fn( $d ) => is_array( $d ) && 'OK' === ( $d['status'] ?? '' );
		if ( $imgs ) {
			$r = $json( 'requestSendFile', array( 'type' => 'Image' ) );
			if ( $ok( $r ) && ! empty( $r['data']['upload_url'] ) ) {
				list( $body, $ct ) = bmnm_multipart( array(), array( 'file' => $imgs[0] ) );
				$u = self::http( (string) $r['data']['upload_url'], array( 'body' => $body, 'headers' => array( 'Content-Type' => $ct ), 'timeout' => 90 ) );
				if ( $ok( $u ) && ! empty( $u['data']['file_id'] ) ) {
					$d = $json( 'sendFile', array( 'chat_id' => $chat, 'file_id' => $u['data']['file_id'], 'text' => $text ) );
					if ( $ok( $d ) ) {
						return true;
					}
				}
			}
		}
		$d = $json( 'sendMessage', array( 'chat_id' => $chat, 'text' => $text ) );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		return $ok( $d ) ? true : bmnm_error( 'api', (string) ( $d['status'] ?? 'رد شد' ) );
	}

	/* ------------------------------------------------------------ Admin */

	/**
	 * Hub menu.
	 */
	public static function menu() {
		add_menu_page( 'رسانه و ارتباطات', 'رسانه و ارتباطات', 'edit_posts', 'bmnm-hub', array( __CLASS__, 'hub' ), 'dashicons-megaphone', 4 );
		add_submenu_page( 'bmnm-hub', 'میز روابط عمومی', 'میز روابط عمومی', 'edit_posts', 'bmnm-hub', array( __CLASS__, 'hub' ) );
		add_submenu_page( 'bmnm-hub', 'کانال‌های پیام‌رسان', 'کانال‌های پیام‌رسان', 'manage_options', 'bmnm-social', array( __CLASS__, 'settings' ) );
	}

	/**
	 * PR desk.
	 */
	public static function hub() {
		$wait = get_posts(
			array(
				'post_type'      => bmnm_types(),
				'post_status'    => array( 'publish', 'pending', 'draft', 'future' ),
				'posts_per_page' => 20,
				'meta_query'     => array( array( 'key' => '_bmn_pr', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		echo '<div class="wrap bmnm-wrap"><div class="bmnm-hero"><h1>میز روابط عمومی</h1><p>تأیید محتوا، انتشار خودکار در بله، ایتا، تلگرام و روبیکا، و دریافت خبر از پیام‌رسان‌ها.</p><div class="bmnm-actions">';
		echo '<a class="button button-primary button-hero" href="' . esc_url( admin_url( 'post-new.php?post_type=' . BMNM_Content::PT ) ) . '">گزارش تصویری تازه</a> ';
		echo '<a class="button button-hero" href="' . esc_url( admin_url( 'post-new.php' ) ) . '">خبر تازه</a> ';
		echo '<a class="button button-hero" href="' . esc_url( admin_url( 'admin.php?page=bmnm-forward' ) ) . '">انتقال از پیام‌رسان به سایت</a></div></div><hr class="wp-header-end">';
		echo '<div class="bmnm-cards"><div class="bmnm-panel"><h2>منتظر تأیید (' . esc_html( bmnm_fa( count( $wait ) ) ) . ')</h2>';
		if ( ! $wait ) {
			echo '<p class="bmnm-muted">همه‌چیز تأیید شده است.</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>عنوان</th><th>نوع</th><th>وضعیت</th><th>منبع</th><th></th></tr></thead><tbody>';
			foreach ( $wait as $p ) {
				$src = (string) get_post_meta( $p->ID, '_bmn_inbound', true );
				$act = current_user_can( 'bmnm_approve' ) ? '<a class="button button-small button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bmnm_approve&id=' . $p->ID ), 'bmnm_approve_' . $p->ID ) ) . '">تأیید و انتشار</a>' : '';
				printf(
					'<tr><td><a href="%1$s"><strong>%2$s</strong></a></td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$s</td></tr>',
					esc_url( (string) get_edit_post_link( $p->ID ) ),
					esc_html( get_the_title( $p ) ),
					esc_html( BMNM_Content::PT === $p->post_type ? 'گزارش تصویری' : 'خبر' ),
					esc_html( get_post_status_object( $p->post_status )->label ),
					esc_html( $src ? self::label( $src ) : 'سایت' ),
					$act // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_url.
				);
			}
			echo '</tbody></table>';
		}
		echo '</div><div class="bmnm-panel"><h2>رویدادهای اخیر</h2><ul class="bmnm-log">';
		$log = array_reverse( (array) get_option( 'bmnm_log', array() ) );
		foreach ( array_slice( array_filter( $log, static fn( $r ) => in_array( $r['c'], array( 'social', 'pr', 'inbound' ), true ) ), 0, 15 ) as $r ) {
			echo '<li><time>' . esc_html( bmnm_jdate( 'j F H:i', $r['t'] ) ) . '</time> ' . esc_html( $r['m'] ) . '</li>';
		}
		echo '</ul><p>کانال‌های فعال: ';
		$on = self::enabled_networks();
		echo $on ? esc_html( implode( '، ', $on ) ) : '<a href="' . esc_url( admin_url( 'admin.php?page=bmnm-social' ) ) . '">هیچ — تنظیم کنید</a>';
		echo '</p></div></div></div>';
	}

	/**
	 * Settings screen.
	 */
	public static function settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o    = self::opts();
		$in   = BMNM_Inbound::opts();
		$help = array(
			'telegram' => 'توکن از BotFather. ربات را مدیر کانال کنید. شناسهٔ کانال: ‎@channel یا ‎-100…',
			'bale'     => 'توکن از ‎@BotFather بله. ربات را مدیر کانال کنید. شناسه: ‎@channel یا شناسهٔ عددی.',
			'eitaa'    => 'توکن از سامانهٔ ایتایار (eitaayar.ir). شناسهٔ کانال: نام کاربری کانال بدون @ یا شناسهٔ عددی.',
			'rubika'   => 'توکن از ‎@BotFather روبیکا. ربات را مدیر کانال کنید. شناسهٔ کانال: guid کانال.',
		);
		echo '<div class="wrap bmnm-wrap"><div class="bmnm-hero"><h1>کانال‌های پیام‌رسان</h1><p>محتوای تأییدشدهٔ روابط عمومی پس از انتشار، با تصویر، کپشن، لینک ویدیو و لینک خبر به کانال‌ها ارسال می‌شود.</p></div><hr class="wp-header-end">';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success"><p>ذخیره شد.</p></div>';
		}
		if ( isset( $_GET['test'] ) ) {
			$res = get_transient( 'bmnm_test_' . get_current_user_id() );
			foreach ( is_array( $res ) ? $res : array() as $net => $msg ) {
				echo '<div class="notice ' . ( 'ok' === $msg ? 'notice-success' : 'notice-error' ) . '"><p><b>' . esc_html( self::label( $net ) ) . ':</b> ' . esc_html( 'ok' === $msg ? 'پیام آزمایشی ارسال شد.' : $msg ) . '</p></div>';
			}
		}
		// phpcs:enable
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bmnm_social_save">';
		wp_nonce_field( 'bmnm_social' );
		echo '<div class="bmnm-net-grid">';
		foreach ( self::networks() as $k => $l ) {
			$has = '' !== self::net( $k, 'token' );
			echo '<fieldset class="bmnm-panel bmnm-net n-' . esc_attr( $k ) . '"><legend>' . esc_html( $l ) . '</legend>';
			echo '<p><label><input type="checkbox" name="n[' . esc_attr( $k ) . '][on]" value="1" ' . checked( (bool) self::net( $k, 'on' ), true, false ) . '> انتشار خودکار در ' . esc_html( $l ) . '</label></p>';
			echo '<p><label>توکن ربات<br><input type="password" autocomplete="new-password" dir="ltr" class="widefat" name="n[' . esc_attr( $k ) . '][token]" placeholder="' . esc_attr( $has ? '•••••••• (ذخیره‌شده؛ برای تغییر وارد کنید)' : '' ) . '"></label></p>';
			echo '<p><label>شناسهٔ کانال انتشار<br><input type="text" dir="ltr" class="widefat" name="n[' . esc_attr( $k ) . '][chat]" value="' . esc_attr( self::net( $k, 'chat' ) ) . '"></label></p>';
			if ( 'eitaa' !== $k ) {
				echo '<p><label><input type="checkbox" name="in[' . esc_attr( $k ) . '][on]" value="1" ' . checked( ! empty( $in[ $k ]['on'] ), true, false ) . '> دریافت خودکار پست‌های کانال به سایت</label></p>';
				echo '<p><label>شناسهٔ کانال منبع (خالی = همان کانال انتشار)<br><input type="text" dir="ltr" class="widefat" name="in[' . esc_attr( $k ) . '][chat]" value="' . esc_attr( (string) ( $in[ $k ]['chat'] ?? '' ) ) . '"></label></p>';
			} else {
				echo '<p class="description">ایتا API دریافت پست کانال ندارد؛ از «انتقال از پیام‌رسان به سایت» استفاده کنید.</p>';
			}
			echo '<p class="description">' . esc_html( $help[ $k ] ) . '</p>';
			if ( $has ) {
				echo '<p><button class="button" name="clear" value="' . esc_attr( $k ) . '" onclick="return confirm(\'توکن حذف شود؟\')">حذف توکن</button></p>';
			}
			echo '</fieldset>';
		}
		echo '</div><div class="bmnm-panel"><h2>قالب کپشن</h2><p class="description">جای‌نگهدارها: <code>{title}</code> <code>{excerpt}</code> <code>{link}</code> <code>{video}</code> <code>{hashtags}</code></p>';
		echo '<textarea name="template" rows="7" class="large-text">' . esc_textarea( (string) ( $o['template'] ?? "📌 {title}\n\n{excerpt}\n\n{video}\n🔗 {link}\n\n{hashtags}" ) ) . '</textarea>';
		echo '<h2>دریافت از پیام‌رسان</h2><p><label>نوع محتوای دریافتی: <select name="in_type"><option value="post"' . selected( ( $in['type'] ?? 'post' ), 'post', false ) . '>خبر</option><option value="bmn_report"' . selected( ( $in['type'] ?? '' ), 'bmn_report', false ) . '>گزارش تصویری</option></select></label> — پست‌ها همیشه به‌صورت «در انتظار بررسی» ساخته می‌شوند تا روابط عمومی تأیید کند.</p>';
		echo '<p><button class="button button-primary button-large">ذخیرهٔ تنظیمات</button></p></div></form>';

		echo '<div class="bmnm-cards"><div class="bmnm-panel"><h2>آزمایش و وبهوک</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline"><input type="hidden" name="action" value="bmnm_social_test">';
		wp_nonce_field( 'bmnm_social_test' );
		echo '<button class="button">ارسال پیام آزمایشی به کانال‌های فعال</button></form> ';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline"><input type="hidden" name="action" value="bmnm_webhooks">';
		wp_nonce_field( 'bmnm_webhooks' );
		echo '<button class="button">ثبت وبهوک دریافت (بله/تلگرام/روبیکا)</button></form>';
		echo '<p class="description">اگر هاست در ایران است، دسترسی به api.telegram.org معمولاً مسدود است؛ برای تلگرام یک پراکسی معکوس خارج از کشور لازم است. بله، ایتایار و روبیکا داخلی‌اند.</p></div></div></div>';
	}

	/**
	 * Save settings.
	 */
	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		check_admin_referer( 'bmnm_social' );
		$old = self::opts();
		$new = array( 'template' => isset( $_POST['template'] ) ? sanitize_textarea_field( wp_unslash( $_POST['template'] ) ) : '' );
		$n   = isset( $_POST['n'] ) && is_array( $_POST['n'] ) ? wp_unslash( $_POST['n'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$clr = isset( $_POST['clear'] ) ? sanitize_key( $_POST['clear'] ) : '';
		foreach ( array_keys( self::networks() ) as $k ) {
			$tok       = isset( $n[ $k ]['token'] ) ? trim( preg_replace( '/[^A-Za-z0-9:_\-]/', '', (string) $n[ $k ]['token'] ) ) : '';
			$new[ $k ] = array(
				'on'    => ! empty( $n[ $k ]['on'] ) ? 1 : 0,
				'token' => $clr === $k ? '' : ( $tok ? $tok : (string) ( $old[ $k ]['token'] ?? '' ) ),
				'chat'  => isset( $n[ $k ]['chat'] ) ? preg_replace( '/[^A-Za-z0-9@_\-]/', '', (string) $n[ $k ]['chat'] ) : '',
			);
		}
		update_option( self::OPT, $new, false );
		$in_post = isset( $_POST['in'] ) && is_array( $_POST['in'] ) ? wp_unslash( $_POST['in'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$type    = isset( $_POST['in_type'] ) && 'bmn_report' === $_POST['in_type'] ? 'bmn_report' : 'post';
		BMNM_Inbound::save_opts( $in_post, $type );
		bmnm_log( 'social', 'تنظیمات کانال‌ها به‌روز شد.' );
		wp_safe_redirect( admin_url( 'admin.php?page=bmnm-social&saved=1' ) );
		exit;
	}

	/**
	 * Test messages.
	 */
	public static function test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		check_admin_referer( 'bmnm_social_test' );
		$out = array();
		foreach ( self::enabled_networks() as $k => $l ) {
			$r         = self::dispatch( $k, null, 'پیام آزمایشی اتصال سایت ' . get_bloginfo( 'name' ) . ' — ' . bmnm_jdate( 'j F Y H:i', time() ) );
			$out[ $k ] = true === $r ? 'ok' : $r->get_error_message();
		}
		if ( ! $out ) {
			$out['telegram'] = 'هیچ کانالی فعال نیست.';
		}
		set_transient( 'bmnm_test_' . get_current_user_id(), $out, 300 );
		wp_safe_redirect( admin_url( 'admin.php?page=bmnm-social&test=1' ) );
		exit;
	}
}
