<?php
/**
 * In-app update info: GET /bmn/v1/app/version
 *
 * Source of truth, in order: manual settings (app_latest + app_apk) → latest GitHub Release of the repo.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * App update endpoint.
 */
class BMN_App_Update {

	const REPO = 'seyedebrahim0211-oss/boshrouyeh-digital';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'bmn_min_app_version', array( __CLASS__, 'min_version' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_bmn_app_upload', array( __CLASS__, 'upload' ) );
		add_action( 'admin_post_bmn_app_remove', array( __CLASS__, 'remove' ) );
		add_action( 'admin_post_bmn_app_save', array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_bmn_apk_chunk', array( __CLASS__, 'ajax_chunk' ) );
		add_action( 'wp_ajax_bmn_apk_finish', array( __CLASS__, 'ajax_finish' ) );
		add_action( 'wp_ajax_bmn_apk_cancel', array( __CLASS__, 'ajax_cancel' ) );
		add_shortcode( 'bmn_app_download', array( __CLASS__, 'shortcode' ) );
	}

	/* ------------------------------------------------ APK hosted on this site */

	/**
	 * Upload folder [path, url] (https forced when the site is https).
	 *
	 * @return array{0:string,1:string}
	 */
	public static function dir() {
		$u    = wp_upload_dir();
		$base = (string) $u['baseurl'];
		if ( str_starts_with( home_url(), 'https://' ) && str_starts_with( $base, 'http://' ) ) {
			$base = 'https://' . substr( $base, 7 );
		}
		return array( trailingslashit( $u['basedir'] ) . 'bmn-app', trailingslashit( $base ) . 'bmn-app' );
	}

	/**
	 * Locally hosted APK info (or null when missing).
	 *
	 * @return array|null
	 */
	public static function local() {
		$l = get_option( 'bmn_app_local' );
		if ( ! is_array( $l ) || empty( $l['file'] ) ) {
			return null;
		}
		list( $dir, $url ) = self::dir();
		if ( ! is_file( $dir . '/' . $l['file'] ) ) {
			return null;
		}
		$l['url'] = $url . '/' . $l['file'];
		return $l;
	}

	/**
	 * Read package, versionCode and versionName from the binary AndroidManifest.xml in an APK.
	 *
	 * @param string $path APK path.
	 * @return array|null
	 */
	public static function manifest( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return null;
		}
		$z = new ZipArchive();
		if ( true !== $z->open( $path ) ) {
			return null;
		}
		$st = $z->statName( 'AndroidManifest.xml' );
		if ( ! $st || $st['size'] > 4 * MB_IN_BYTES ) {
			$z->close();
			return null;
		}
		$x = (string) $z->getFromName( 'AndroidManifest.xml' );
		$z->close();
		$len = strlen( $x );
		if ( $len < 16 ) {
			return null;
		}
		$pos = 8;
		$str = array();
		while ( $pos + 8 <= $len ) {
			$h = unpack( 'vtype/vhsize/Vsize', substr( $x, $pos, 8 ) );
			if ( $h['size'] < 8 || $pos + $h['size'] > $len ) {
				break;
			}
			if ( 0x0001 === $h['type'] ) { // String pool.
				$sp   = unpack( 'Vcount/Vstyles/Vflags/Vstart/Vsstart', substr( $x, $pos + 8, 20 ) );
				$utf8 = 0 !== ( $sp['flags'] & 0x100 );
				$cnt  = min( $sp['count'], 20000 );
				$offs = $cnt ? array_values( unpack( 'V' . $cnt, substr( $x, $pos + $h['hsize'], 4 * $cnt ) ) ) : array();
				$base = $pos + $sp['start'];
				foreach ( $offs as $i => $o ) {
					$p = $base + $o;
					if ( $p >= $len ) {
						$str[ $i ] = '';
						continue;
					}
					if ( $utf8 ) {
						$c = ord( $x[ $p++ ] );
						if ( $c & 0x80 ) {
							++$p;
						}
						$b = ord( $x[ $p++ ] );
						if ( $b & 0x80 ) {
							$b = ( ( $b & 0x7f ) << 8 ) | ord( $x[ $p++ ] );
						}
						$str[ $i ] = substr( $x, $p, $b );
					} else {
						$l  = unpack( 'v', substr( $x, $p, 2 ) )[1];
						$p += 2;
						if ( $l & 0x8000 ) {
							$l  = ( ( $l & 0x7fff ) << 16 ) | unpack( 'v', substr( $x, $p, 2 ) )[1];
							$p += 2;
						}
						$str[ $i ] = mb_convert_encoding( substr( $x, $p, $l * 2 ), 'UTF-8', 'UTF-16LE' );
					}
				}
			} elseif ( 0x0102 === $h['type'] ) { // Start tag.
				$e = unpack( 'Vline/Vcomment/Vns/Vname/vastart/vasize/vacount', substr( $x, $pos + 8, 22 ) );
				if ( 'manifest' === ( $str[ $e['name'] ] ?? '' ) ) {
					$out = array( 'package' => '', 'versionCode' => 0, 'versionName' => '' );
					$ap  = $pos + 16 + $e['astart'];
					for ( $i = 0; $i < min( $e['acount'], 50 ); $i++ ) {
						$a   = unpack( 'Vns/Vname/Vraw/vsz/Cres0/Ctype/Vdata', substr( $x, $ap + $i * max( 20, $e['asize'] ), 20 ) );
						$an  = $str[ $a['name'] ] ?? '';
						$raw = 0xFFFFFFFF !== $a['raw'] ? ( $str[ $a['raw'] ] ?? '' ) : '';
						if ( 'versionCode' === $an ) {
							$out['versionCode'] = 0x03 === $a['type'] ? (int) $raw : (int) $a['data'];
						} elseif ( 'versionName' === $an ) {
							$out['versionName'] = $raw;
						} elseif ( 'package' === $an ) {
							$out['package'] = $raw;
						}
					}
					return $out;
				}
			}
			$pos += $h['size'];
		}
		return null;
	}

	/**
	 * Admin page under «شهرداری بشرویه».
	 */
	public static function menu() {
		add_menu_page( 'اپ موبایل', 'اپ موبایل', 'manage_options', 'bmn-app', array( __CLASS__, 'page' ), 'dashicons-smartphone', 4 );
	}

	/**
	 * Save app options (notes, forced update, store links) into the municipality settings.
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		check_admin_referer( 'bmn_app_save' );
		$o = get_option( 'bmn_settings', array() );
		$o = is_array( $o ) ? $o : array();
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		$o['app_notes']  = isset( $_POST['app_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['app_notes'] ) ) : '';
		$o['app_bazaar'] = isset( $_POST['app_bazaar'] ) ? esc_url_raw( wp_unslash( $_POST['app_bazaar'] ), array( 'https', 'http', 'bazaar' ) ) : '';
		$o['app_myket']  = isset( $_POST['app_myket'] ) ? esc_url_raw( wp_unslash( $_POST['app_myket'] ), array( 'https', 'http', 'myket' ) ) : '';
		// phpcs:enable
		// Forced update = minimum allowed version is the current release.
		$rel           = self::release();
		$o['app_min']  = ! empty( $_POST['force'] ) && $rel['latest'] ? $rel['latest'] : '';
		remove_all_filters( 'sanitize_option_bmn_settings' ); // Keep exact values (notes are multi-line).
		update_option( 'bmn_settings', $o );
		wp_safe_redirect( add_query_arg( 'm', 'saved', admin_url( 'admin.php?page=bmn-app' ) ) );
		exit;
	}

	/**
	 * Handle APK upload.
	 */
	public static function upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		check_admin_referer( 'bmn_app_upload' );
		$f   = $_FILES['apk'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$msg = 'ok';
		if ( ! $f || ! empty( $f['error'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			$msg = ( $f && UPLOAD_ERR_INI_SIZE === (int) $f['error'] ) ? 'حجم فایل از سقف آپلود هاست (' . ini_get( 'upload_max_filesize' ) . ') بیشتر است.' : 'فایلی دریافت نشد.';
		} elseif ( 'apk' !== strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) ) || $f['size'] > 150 * MB_IN_BYTES ) {
			$msg = 'فقط فایل .apk تا ۱۵۰ مگابایت.';
		} else {
			$m = self::manifest( $f['tmp_name'] );
			$l = get_option( 'bmn_app_local' );
			list( $dir, $url ) = self::dir();
			if ( ! $m || ! $m['versionCode'] ) {
				$msg = 'فایل APK معتبر نیست یا خوانده نشد.';
			} elseif ( is_array( $l ) && ! empty( $l['package'] ) && $l['package'] !== $m['package'] ) {
				$msg = 'این فایل مربوط به اپ دیگری است (' . $m['package'] . ').';
			} elseif ( is_array( $l ) && (int) $m['versionCode'] <= (int) ( $l['code'] ?? 0 ) ) {
				$msg = 'شمارهٔ ساخت APK باید از نسخهٔ قبلی (' . (int) ( $l['code'] ?? 0 ) . ') بزرگ‌تر باشد.';
			} elseif ( ! wp_mkdir_p( $dir ) ) {
				$msg = 'پوشهٔ آپلود قابل نوشتن نیست.';
			} else {
				if ( ! is_file( $dir . '/.htaccess' ) ) {
					file_put_contents( $dir . '/.htaccess', "AddType application/vnd.android.package-archive .apk\nOptions -Indexes\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
				if ( ! is_file( $dir . '/index.html' ) ) {
					file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
				$name = 'boshrouyeh-' . (int) $m['versionCode'] . '-' . strtolower( wp_generate_password( 6, false ) ) . '.apk';
				if ( ! move_uploaded_file( $f['tmp_name'], $dir . '/' . $name ) ) {
					$msg = 'ذخیرهٔ فایل ممکن نشد.';
				} else {
					chmod( $dir . '/' . $name, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					update_option(
						'bmn_app_local',
						array(
							'package' => (string) $m['package'],
							'code'    => (int) $m['versionCode'],
							'name'    => self::clean_version( (string) $m['versionName'] ) ? self::clean_version( (string) $m['versionName'] ) : '1.' . (int) $m['versionCode'],
							'file'    => $name,
							'sha'     => hash_file( 'sha256', $dir . '/' . $name ),
							'size'    => (int) filesize( $dir . '/' . $name ),
							'at'      => time(),
						),
						false
					);
					// Keep only the new file and the previous one.
					foreach ( (array) glob( $dir . '/boshrouyeh-*.apk' ) as $old ) {
						if ( basename( $old ) !== $name && ( ! is_array( $l ) || basename( $old ) !== ( $l['file'] ?? '' ) ) ) {
							wp_delete_file( $old );
						}
					}
				}
			}
		}
		wp_safe_redirect( add_query_arg( 'm', rawurlencode( $msg ), admin_url( 'admin.php?page=bmn-app' ) ) );
		exit;
	}

	/* ------------------------------------------------ Chunked upload (fast, resumable per chunk) */

	/**
	 * Temp folder of one chunked upload (web access denied).
	 *
	 * @param string $id Upload id.
	 * @return string
	 */
	private static function tmp_dir( $id ) {
		list( $dir ) = self::dir();
		$root = $dir . '/tmp';
		if ( wp_mkdir_p( $root ) && ! is_file( $root . '/.htaccess' ) ) {
			file_put_contents( $root . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $root . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		// Drop abandoned uploads older than a day.
		foreach ( (array) glob( $root . '/*', GLOB_ONLYDIR ) as $old ) {
			if ( filemtime( $old ) < time() - DAY_IN_SECONDS ) {
				array_map( 'wp_delete_file', (array) glob( $old . '/*' ) );
				@rmdir( $old ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			}
		}
		return $root . '/' . $id;
	}

	/**
	 * Common checks for the AJAX upload calls; returns the upload id.
	 *
	 * @return string
	 */
	private static function ajax_guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
		}
		check_ajax_referer( 'bmn_apk_chunk', 'nonce' );
		$id = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9]{16,32}$/', $id ) ) {
			wp_send_json_error( array( 'message' => 'شناسهٔ بارگذاری نامعتبر است.' ), 400 );
		}
		return $id;
	}

	/**
	 * AJAX: receive one chunk (≤ 8 MB).
	 */
	public static function ajax_chunk() {
		$id    = self::ajax_guard();
		$index = isset( $_POST['index'] ) && ctype_digit( (string) $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
		$total = isset( $_POST['total'] ) ? absint( $_POST['total'] ) : 0;
		$f     = $_FILES['chunk'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( $total < 1 || $total > 100 || $index < 0 || $index >= $total ) {
			wp_send_json_error( array( 'message' => 'شمارهٔ قطعه نامعتبر است.' ), 400 );
		}
		if ( ! $f || ! empty( $f['error'] ) || ! is_uploaded_file( $f['tmp_name'] ) || $f['size'] > 8 * MB_IN_BYTES ) {
			wp_send_json_error( array( 'message' => 'قطعه دریافت نشد.' ), 400 );
		}
		$dir = self::tmp_dir( $id );
		if ( ! wp_mkdir_p( $dir ) || ! move_uploaded_file( $f['tmp_name'], $dir . '/' . $index . '.part' ) ) {
			wp_send_json_error( array( 'message' => 'ذخیرهٔ قطعه ممکن نشد.' ), 500 );
		}
		wp_send_json_success( array( 'index' => $index ) );
	}

	/**
	 * AJAX: join the chunks, check the APK and publish it.
	 */
	public static function ajax_finish() {
		$id    = self::ajax_guard();
		$total = isset( $_POST['total'] ) ? absint( $_POST['total'] ) : 0;
		$dir   = self::tmp_dir( $id );
		if ( $total < 1 || $total > 100 ) {
			wp_send_json_error( array( 'message' => 'تعداد قطعه نامعتبر است.' ), 400 );
		}
		$total_size = 0;
		for ( $i = 0; $i < $total; $i++ ) {
			$part = $dir . '/' . $i . '.part';
			if ( ! is_file( $part ) || ! filesize( $part ) ) {
				wp_send_json_error( array( 'message' => 'قطعهٔ ' . ( $i + 1 ) . ' نرسیده است؛ دوباره تلاش کنید.' ), 400 );
			}
			$total_size += filesize( $part );
			if ( $total_size > 150 * MB_IN_BYTES ) {
				wp_send_json_error( array( 'message' => 'حجم فایل APK بیشتر از ۱۵۰ مگابایت است.' ), 413 );
			}
		}
		$joined = $dir . '/joined.apk';
		$out    = @fopen( $joined, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( false === $out ) {
			wp_send_json_error( array( 'message' => 'فضای موقت برای ساخت APK قابل نوشتن نیست.' ), 500 );
		}
		$joined_ok = true;
		for ( $i = 0; $i < $total; $i++ ) {
			$part = $dir . '/' . $i . '.part';
			$in   = @fopen( $part, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			if ( false === $in ) {
				$joined_ok = false;
				break;
			}
			$copied = stream_copy_to_stream( $in, $out );
			fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $copied || (int) $copied !== (int) filesize( $part ) ) {
				$joined_ok = false;
				break;
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $joined_ok || ! is_file( $joined ) || filesize( $joined ) !== $total_size ) {
			wp_delete_file( $joined );
			wp_send_json_error( array( 'message' => 'ساخت فایل نهایی کامل نشد؛ قطعات حفظ شدند. دوباره انتشار را بزنید.' ), 500 );
		}
		$r = self::store_apk( $joined );
		wp_delete_file( $joined );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ), 400 );
		}
		for ( $i = 0; $i < $total; $i++ ) {
			wp_delete_file( $dir . '/' . $i . '.part' );
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		wp_send_json_success( array( 'version' => $r['name'], 'code' => $r['code'] ) );
	}

	/**
	 * Cancel an unfinished upload and remove its temporary parts.
	 */
	public static function ajax_cancel() {
		$id = self::ajax_guard();
		$dir = self::tmp_dir( $id );
		foreach ( (array) glob( $dir . '/*.part' ) as $part ) {
			wp_delete_file( $part );
		}
		if ( is_file( $dir . '/joined.apk' ) ) {
			wp_delete_file( $dir . '/joined.apk' );
		}
		if ( is_dir( $dir ) ) {
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		}
		wp_send_json_success();
	}

	/**
	 * Publish an APK already on disk (same checks as a manual upload).
	 *
	 * @param string $path APK path.
	 * @return array|WP_Error
	 */
	public static function store_apk( $path ) {
		$m = self::manifest( $path );
		$l = get_option( 'bmn_app_local' );
		list( $dir ) = self::dir();
		if ( ! $m || ! $m['versionCode'] ) {
			return new WP_Error( 'bad', 'فایل APK معتبر نیست یا خوانده نشد.' );
		}
		if ( is_array( $l ) && ! empty( $l['package'] ) && $l['package'] !== $m['package'] ) {
			return new WP_Error( 'pkg', 'این فایل مربوط به اپ دیگری است (' . $m['package'] . ').' );
		}
		// Android cannot install a build with the same or a lower versionCode.
		// Reject it instead of silently publishing an APK that never triggers alerts.
		$current = self::release();
		if ( (int) $m['versionCode'] <= max( (int) ( $l['code'] ?? 0 ), (int) $current['code'] ) ) {
			return new WP_Error( 'version_code', 'شمارهٔ ساخت APK جدید باید بزرگ‌تر از نسخهٔ منتشرشده (' . max( (int) ( $l['code'] ?? 0 ), (int) $current['code'] ) . ') باشد.' );
		}
		if ( ! is_file( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "AddType application/vnd.android.package-archive .apk\nOptions -Indexes\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$name = 'boshrouyeh-' . (int) $m['versionCode'] . '-' . strtolower( wp_generate_password( 6, false ) ) . '.apk';
		if ( ! @rename( $path, $dir . '/' . $name ) && ! @copy( $path, $dir . '/' . $name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return new WP_Error( 'mv', 'ذخیرهٔ فایل ممکن نشد.' );
		}
		chmod( $dir . '/' . $name, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$info = array(
			'package' => (string) $m['package'],
			'code'    => (int) $m['versionCode'],
			'name'    => self::clean_version( (string) $m['versionName'] ) ? self::clean_version( (string) $m['versionName'] ) : '1.' . (int) $m['versionCode'],
			'file'    => $name,
			'sha'     => hash_file( 'sha256', $dir . '/' . $name ),
			'size'    => (int) filesize( $dir . '/' . $name ),
			'at'      => time(),
		);
		update_option( 'bmn_app_local', $info, false );
		foreach ( (array) glob( $dir . '/boshrouyeh-*.apk' ) as $old ) {
			if ( basename( $old ) !== $name && ( ! is_array( $l ) || basename( $old ) !== ( $l['file'] ?? '' ) ) ) {
				wp_delete_file( $old );
			}
		}
		return $info;
	}

	/**
	 * Remove the hosted APK.
	 */
	public static function remove() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		check_admin_referer( 'bmn_app_remove' );
		$l = self::local();
		if ( $l ) {
			wp_delete_file( self::dir()[0] . '/' . $l['file'] );
		}
		delete_option( 'bmn_app_local' );
		wp_safe_redirect( add_query_arg( 'm', 'ok', admin_url( 'admin.php?page=bmn-app' ) ) );
		exit;
	}

	/**
	 * Admin page.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$m     = isset( $_GET['m'] ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$rel   = self::release();
		$l     = self::local();
		$fa    = static fn( $s ) => esc_html( bmn_fa_digits( (string) $s ) );
		$min   = self::clean_version( (string) bmn_option( 'app_min', '' ) );
		$force = $min && $rel['latest'] && version_compare( $min, $rel['latest'], '>=' );
		$repo  = (string) bmn_option( 'app_github', '' );
		$rel_url = 'https://github.com/' . ( preg_match( '#^[\w.-]+/[\w.-]+$#', $repo ) ? $repo : self::REPO ) . '/releases';
		echo '<div class="wrap bmn-app">';
		echo '<div class="bmnm-hero"><h1>اپ موبایل «بشرویهٔ من»</h1><p>نسخهٔ جدید را اینجا بارگذاری کنید؛ کاربران داخل اپ پیام «نسخهٔ جدید آماده است» و دکمهٔ «بروزرسانی» را می‌بینند و صفحهٔ دانلود سایت هم خودکار به‌روز می‌شود.</p></div><hr class="wp-header-end">';
		if ( 'ok' === $m ) {
			echo '<div class="notice notice-success is-dismissible"><p>فایل بارگذاری شد؛ از همین حالا کاربران نسخهٔ جدید را دریافت می‌کنند.</p></div>';
		} elseif ( 'saved' === $m ) {
			echo '<div class="notice notice-success is-dismissible"><p>ذخیره شد.</p></div>';
		} elseif ( $m ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
		}

		// Status card.
		echo '<div class="bmn-app__now' . ( $rel['url'] ? '' : ' is-empty' ) . '"><div><small>نسخه‌ای که کاربران دریافت می‌کنند</small><b>' . ( $rel['latest'] ? $fa( $rel['latest'] ) : '—' ) . '</b>';
		if ( $rel['url'] ) {
			echo '<span>' . ( $rel['code'] ? 'شمارهٔ ساخت ' . $fa( $rel['code'] ) . ' · ' : '' ) . ( $rel['size'] ? esc_html( bmn_fa_digits( size_format( $rel['size'], 1 ) ) ) . ' · ' : '' ) . 'روی همین سایت' . ( $l ? ' · ' . esc_html( human_time_diff( (int) $l['at'] ) ) . ' پیش' : '' ) . '</span>';
		} else {
			echo '<span>هنوز نسخه‌ای منتشر نشده است؛ اولین فایل APK را پایین همین صفحه بارگذاری کنید.</span>';
		}
		echo '</div><div class="bmn-app__links"><a class="button" href="' . esc_url( home_url( '/app/download/' ) ) . '" target="_blank">صفحهٔ دانلود سایت ↗</a>' . ( $force ? '<span class="bmnm-badge err">بروزرسانی اجباری فعال</span>' : '' ) . '</div></div>';

		// Steps.
		echo '<ol class="bmn-app__steps"><li><b>انتخاب فایل</b>فایل نسخهٔ جدید (<code>.apk</code>) را در کادر زیر بیندازید.</li><li><b>انتشار</b>دکمهٔ «انتشار نسخه» را بزنید.</li><li><b>تمام!</b>کاربران با باز کردن اپ، پیام نسخهٔ جدید و دکمهٔ «بروزرسانی» را می‌بینند.</li></ol>';

		// Upload.
		echo '<form class="bmn-app__card" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bmn_app_upload">';
		wp_nonce_field( 'bmn_app_upload' );
		echo '<h2>انتشار نسخهٔ جدید</h2><label class="bmn-app__drop" id="bmn-drop"><input type="file" name="apk" id="bmn-apk" accept=".apk,application/vnd.android.package-archive" required><span class="dashicons dashicons-upload"></span><b id="bmn-apk-name">فایل APK را اینجا بکشید یا کلیک کنید</b><small>شمارهٔ نسخه خودکار از داخل فایل خوانده می‌شود · حداکثر ۱۵۰ مگابایت · ارسال چندبخشی سریع</small></label>';
		echo '<p><button class="button button-primary button-hero" type="submit" id="bmn-apk-go" disabled>بارگذاری و انتشار نسخه</button></p>';
		echo '<div class="bmn-app__progress" id="bmn-upload-ui" hidden><div class="bmn-app__progress-head"><strong id="bmn-upload-status" aria-live="polite">آمادهٔ ارسال</strong><b id="bmn-upload-pct">۰٪</b></div><div class="bmn-app__progress-track" id="bmn-upload-track" role="progressbar" aria-label="درصد بارگذاری APK" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="bmn-upload-bar"></span></div><p class="bmn-app__progress-detail" id="bmn-upload-detail">فایل انتخاب‌شده آماده است.</p><button class="button bmn-app__cancel" type="button" id="bmn-upload-cancel">لغو بارگذاری</button></div></form>';

		// Options.
		echo '<form class="bmn-app__card" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bmn_app_save">';
		wp_nonce_field( 'bmn_app_save' );
		echo '<h2>تنظیمات نسخه</h2>';
		echo '<p><label for="app_notes"><b>تازه‌های این نسخه</b> <small class="bmnm-muted">(در پنجرهٔ بروزرسانی اپ و صفحهٔ دانلود نمایش داده می‌شود)</small></label><textarea class="large-text" rows="4" id="app_notes" name="app_notes" placeholder="مثلاً: افزودن نوبت‌دهی واحدها، رفع اشکال پرداخت">' . esc_textarea( (string) bmn_option( 'app_notes', '' ) ) . '</textarea></p>';
		echo '<p class="bmn-app__switch"><label><input type="checkbox" name="force" value="1"' . checked( $force, true, false ) . ( $rel['latest'] ? '' : ' disabled' ) . '> <b>بروزرسانی اجباری</b></label><small class="bmnm-muted">اگر روشن باشد، کاربرانی که نسخهٔ قدیمی‌تر دارند بدون بروزرسانی نمی‌توانند از اپ استفاده کنند. فقط برای رفع اشکال‌های مهم.</small></p>';
		echo '<div class="bmn-app__grid"><p><label for="app_bazaar"><b>لینک کافه‌بازار</b> <small class="bmnm-muted">(اختیاری)</small></label><input type="url" dir="ltr" class="widefat" id="app_bazaar" name="app_bazaar" value="' . esc_attr( (string) bmn_option( 'app_bazaar', '' ) ) . '" placeholder="https://cafebazaar.ir/app/..."></p>';
		echo '<p><label for="app_myket"><b>لینک مایکت</b> <small class="bmnm-muted">(اختیاری)</small></label><input type="url" dir="ltr" class="widefat" id="app_myket" name="app_myket" value="' . esc_attr( (string) bmn_option( 'app_myket', '' ) ) . '" placeholder="https://myket.ir/app/..."></p></div>';
		echo '<p class="bmnm-muted">اپ نسخهٔ جدید را از این آدرس می‌خواند: <code dir="ltr">' . esc_html( rest_url( 'bmn/v1/app/version' ) ) . '</code></p>';
		echo '<p><button class="button button-primary">ذخیرهٔ تنظیمات</button></p></form>';

		if ( $l ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bmn-app__del"><input type="hidden" name="action" value="bmn_app_remove">';
			wp_nonce_field( 'bmn_app_remove' );
			echo '<button class="button-link button-link-delete" onclick="return confirm(\'فایل نسخهٔ ' . esc_attr( $l['name'] ) . ' از سایت حذف شود؟\')">حذف فایل APK از سایت</button></form>';
		}
		?>
		<style>
		.bmn-app{max-width:900px}
		.bmn-app__now{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px;padding:20px 22px;border-radius:16px;background:linear-gradient(135deg,var(--a,#0F766E),#1F2A44);color:#fff;margin-bottom:16px}
		.bmn-app__now small{display:block;opacity:.85}.bmn-app__now b{display:block;font-size:34px;line-height:1.3}.bmn-app__now span{opacity:.9;font-size:13px}
		.bmn-app__now.is-empty{background:#F3F4F6;color:var(--tx,#1F2937)}
		.bmn-app__links{display:flex;gap:8px;align-items:center}
		.bmn-app__steps{list-style:none;counter-reset:s;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;padding:0;margin:0 0 16px}
		.bmn-app__steps li{counter-increment:s;background:var(--sf,#fff);border:1px solid var(--ln,#E6E8EC);border-radius:14px;padding:14px;color:var(--mu,#6B7280);line-height:1.8;margin:0}
		.bmn-app__steps li::before{content:counter(s,persian);display:inline-grid;place-items:center;width:26px;height:26px;border-radius:50%;background:var(--a,#0F766E);color:#fff;font-weight:800;margin-inline-end:8px}
		.bmn-app__steps b{color:var(--tx,#1F2937)}.bmn-app__steps b::after{content:"";display:block}
		.bmn-app__card{background:var(--sf,#fff);border:1px solid var(--ln,#E6E8EC);border-radius:16px;padding:18px 20px;margin-bottom:16px}
		.bmn-app__card h2{margin-top:0;font-size:16px}
		.bmn-app__drop{position:relative;display:flex;flex-direction:column;align-items:center;gap:6px;padding:30px 16px;border:2px dashed var(--ln,#D1D5DB);border-radius:14px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s}
		.bmn-app__drop:hover,.bmn-app__drop.is-over{border-color:var(--a,#0F766E);background:var(--as,#E7F5F3)}
		.bmn-app__drop.is-ready{border-style:solid;border-color:var(--a,#0F766E);background:var(--as,#E7F5F3)}
		.bmn-app__drop input{position:absolute;inset:0;opacity:0;cursor:pointer}
		.bmn-app__drop .dashicons{font-size:36px;width:36px;height:36px;color:var(--a,#0F766E)}
			.bmn-app__drop small{color:var(--mu,#6B7280)}
			.bmn-app__progress{margin:16px 0 0;padding:17px 18px;border:1px solid #cde6df;border-radius:14px;background:#f4faf8}
			.bmn-app__progress[hidden]{display:none!important}
			.bmn-app__progress-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}
			.bmn-app__progress-head strong{color:#174b43;font-size:14px}
			.bmn-app__progress-head b{font-size:23px;line-height:1;font-variant-numeric:tabular-nums;color:#0f766e;direction:ltr}
			.bmn-app__progress-track{height:12px;background:#dceee9;border-radius:99px;overflow:hidden}
			.bmn-app__progress-track span{display:block;height:100%;width:0;background:linear-gradient(90deg,#0f766e,#26aa95);border-radius:99px;transition:width .18s ease}
			.bmn-app__progress-detail{color:#52736c;font-size:12px;line-height:1.8;margin:9px 0}
			.bmn-app__cancel{margin-top:3px}
			.bmn-app__progress.is-error{background:#fff6f3;border-color:#f2c9bb}
			.bmn-app__progress.is-error strong{color:#a43f27}
			.bmn-app__progress.is-done{background:#edf9f2;border-color:#bde6cc}
		.bmn-app__switch{display:flex;flex-direction:column;gap:4px;padding:12px 14px;border-radius:12px;background:#FFFBEB;border:1px solid #FDE68A}
		.bmn-app__grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
		.bmn-app__grid label,.bmn-app__card label{display:block;margin-bottom:4px}
		@media(max-width:782px){.bmn-app__grid{grid-template-columns:1fr}}
		</style>
			<script>
			(function () {
			  'use strict';
			  var fileInput = document.getElementById('bmn-apk');
			  var drop = document.getElementById('bmn-drop');
			  var nameEl = document.getElementById('bmn-apk-name');
			  var submit = document.getElementById('bmn-apk-go');
			  var panel = document.getElementById('bmn-upload-ui');
			  var statusEl = document.getElementById('bmn-upload-status');
			  var percentEl = document.getElementById('bmn-upload-pct');
			  var track = document.getElementById('bmn-upload-track');
			  var bar = document.getElementById('bmn-upload-bar');
			  var detail = document.getElementById('bmn-upload-detail');
			  var cancel = document.getElementById('bmn-upload-cancel');
			  if (!fileInput || !submit) return;
			  var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			  var nonce = <?php echo wp_json_encode( wp_create_nonce( 'bmn_apk_chunk' ) ); ?>;
			  var completeUrl = <?php echo wp_json_encode( admin_url( 'admin.php?page=bmn-app&m=ok' ) ); ?>;
			  var chunkBytes = 4 * 1024 * 1024;
			  var maxBytes = 150 * 1024 * 1024;
			  var running = false, session = null;
			  function fa(value) { return Number(value).toLocaleString('fa-IR'); }
			  function size(bytes) { return (bytes / 1048576).toFixed(1) + ' مگابایت'; }
			  function pick() {
			    var file = fileInput.files && fileInput.files[0];
			    var valid = !!file && /\.apk$/i.test(file.name) && file.size > 0 && file.size <= maxBytes;
			    nameEl.textContent = !file ? 'فایل APK را اینجا بکشید یا کلیک کنید'
			      : !/\.apk$/i.test(file.name) ? 'فقط فایل APK قابل انتخاب است'
			      : file.size > maxBytes ? 'حجم فایل از ۱۵۰ مگابایت بیشتر است'
			      : file.name + ' — ' + size(file.size);
			    drop.classList.toggle('is-ready', valid);
			    submit.disabled = running || !valid;
			    if (!running) { session = null; panel.hidden = true; }
			  }
			  fileInput.addEventListener('change', pick);
			  ['dragenter','dragover'].forEach(function(type) { drop.addEventListener(type, function() { drop.classList.add('is-over'); }); });
			  ['dragleave','drop'].forEach(function(type) { drop.addEventListener(type, function() { drop.classList.remove('is-over'); }); });
			  function paint(s, label, force100) {
			    var done = 0, inFlight = 0, count = 0;
			    for (var i = 0; i < s.total; i++) {
			      if (s.done[i]) { done += s.done[i]; count++; }
			      else { inFlight += s.loading[i] || 0; }
			    }
			    var bytes = Math.min(s.file.size, done + inFlight);
			    var pct = force100 ? 100 : Math.min(99, Math.floor(bytes * 100 / s.file.size));
			    statusEl.textContent = label || 'در حال ارسال همزمان قطعات فایل…';
			    percentEl.textContent = fa(pct) + '٪';
			    bar.style.width = pct + '%';
			    track.setAttribute('aria-valuenow', String(pct));
			    var elapsed = Math.max(1, (Date.now() - s.started) / 1000);
			    var speed = bytes / elapsed / 1048576;
			    detail.textContent = size(bytes) + ' از ' + size(s.file.size) + ' · ' +
			      fa(count) + ' از ' + fa(s.total) + ' بخش تأیید شده' +
			      (force100 ? '' : ' · سرعت میانگین ' + speed.toFixed(1) + ' مگابایت/ثانیه');
			  }
			  function fields(action, s) {
			    var data = new FormData();
			    data.append('action', 'bmn_apk_' + action);
			    data.append('nonce', nonce);
			    data.append('id', s.id);
			    data.append('total', s.total);
			    return data;
			  }
			  async function call(action, s) {
			    var response = await fetch(ajaxUrl, {method:'POST', credentials:'same-origin', cache:'no-store', body:fields(action,s)});
			    var result = await response.json().catch(function(){return null;});
			    if (!response.ok || !result || !result.success) {
			      throw new Error(result && result.data && result.data.message || 'ارتباط با سرور برقرار نشد؛ دوباره تلاش کنید.');
			    }
			    return result.data;
			  }
			  function send(s, index) {
			    return new Promise(function(resolve,reject) {
			      var xhr = new XMLHttpRequest();
			      var part = s.file.slice(index * chunkBytes, Math.min(s.file.size, (index + 1) * chunkBytes));
			      var data = fields('chunk',s);
			      data.append('index', String(index));
			      data.append('chunk',part,'part.bin');
			      s.active.add(xhr);
			      xhr.open('POST',ajaxUrl,true);
			      xhr.timeout = 120000;
			      xhr.responseType = 'json';
			      xhr.upload.onprogress = function(e) {
			        if (!s.cancelled && e.lengthComputable) { s.loading[index] = Math.min(part.size,e.loaded); paint(s); }
			      };
			      xhr.onload = function() {
			        s.active.delete(xhr);
			        var result = xhr.response;
			        if (xhr.status >= 200 && xhr.status < 300 && result && result.success) { resolve(part.size); }
			        else { reject(new Error(result && result.data && result.data.message || 'ارسال بخش ' + fa(index+1) + ' ناموفق بود (HTTP ' + xhr.status + ').')); }
			      };
			      xhr.onerror = function() { s.active.delete(xhr); reject(new Error('اتصال اینترنت یا سرور هنگام بارگذاری قطع شد.')); };
			      xhr.ontimeout = function() { s.active.delete(xhr); reject(new Error('مهلت دریافت پاسخ سرور به پایان رسید.')); };
			      xhr.onabort = function() { s.active.delete(xhr); reject(new Error('ارسال متوقف شد.')); };
			      xhr.send(data);
			    });
			  }
			  async function sendRetry(s,index) {
			    for (var attempt=0;attempt<3;attempt++) {
			      if (s.cancelled || s.stopped) throw new Error('بارگذاری متوقف شد.');
			      try {
			        var bytes = await send(s,index);
			        delete s.loading[index];
			        s.done[index] = bytes;
			        paint(s);
			        return;
			      } catch(err) {
			        delete s.loading[index];
			        if (s.cancelled || s.stopped || attempt === 2) throw err;
			        statusEl.textContent = 'تلاش مجدد برای بخش ' + fa(index+1) + '…';
			        await new Promise(function(resolve){setTimeout(resolve,750 * (attempt+1));});
			      }
			    }
			  }
			  async function parallel(s) {
			    var pending = [];
			    for (var i=0;i<s.total;i++) if (!s.done[i]) pending.push(i);
			    var next = 0, firstError = null;
			    await Promise.all(Array.from({length:Math.min(3,pending.length)},async function() {
			      while (next < pending.length && !s.cancelled && !s.stopped) {
			        var index = pending[next++];
			        try { await sendRetry(s,index); }
			        catch(err) {
			          if (!firstError) firstError = err;
			          s.stopped = true;
			          s.active.forEach(function(xhr){xhr.abort();});
			          break;
			        }
			      }
			    }));
			    if (firstError) throw firstError;
			  }
			  function toggleBusy(busy) {
			    running = busy;
			    fileInput.disabled = busy;
			    submit.disabled = busy;
			    submit.textContent = busy ? 'در حال بارگذاری و انتشار…' : 'بارگذاری و انتشار نسخه';
			    if (!busy) pick();
			  }
			  submit.form.addEventListener('submit',async function(e) {
			    var file = fileInput.files && fileInput.files[0];
			    if (!file || !/\.apk$/i.test(file.name) || file.size === 0 || file.size > maxBytes) { e.preventDefault(); pick(); return; }
			    if (!window.XMLHttpRequest || !window.fetch || !window.crypto || !window.crypto.getRandomValues) return;
			    e.preventDefault();
			    if (running) return;
			    if (!session || session.file !== file) {
			      var random = new Uint8Array(16);
			      window.crypto.getRandomValues(random);
			      session = {id:Array.from(random,function(b){return b.toString(16).padStart(2,'0');}).join(''),
			        file:file,total:Math.ceil(file.size/chunkBytes),done:{},loading:{},active:new Set(),started:Date.now(),cancelled:false};
			    }
			    var s = session;
			    s.stopped = false;
			    panel.hidden = false;
			    panel.classList.remove('is-error','is-done');
			    cancel.disabled = false;
			    cancel.hidden = false;
			    toggleBusy(true);
			    paint(s,'در حال ارسال همزمان ۳ بخش…');
			    try {
			      await parallel(s);
			      if (s.cancelled) return;
			      cancel.disabled = true;
			      paint(s,'ارسال کامل شد؛ در حال بررسی APK و انتشار نسخه…');
			      var published = await call('finish',s);
			      panel.classList.add('is-done');
			      cancel.hidden = true;
			      paint(s,'نسخه ' + published.version + ' با موفقیت منتشر شد.',true);
			      window.setTimeout(function(){window.location.assign(completeUrl);},1300);
			    } catch(err) {
			      if (s.cancelled) return;
			      s.active.forEach(function(xhr){xhr.abort();});
			      panel.classList.add('is-error');
			      statusEl.textContent = 'بارگذاری یا انتشار انجام نشد';
			      detail.textContent = err.message + ' — برای ادامه، دوباره دکمهٔ بارگذاری و انتشار را بزنید.';
			      cancel.hidden = true;
			      running = false;
			      fileInput.disabled = false;
			      submit.disabled = false;
			      submit.textContent = 'تلاش دوباره و انتشار نسخه';
			    }
			  });
			  cancel.addEventListener('click',async function() {
			    if (!session || !running || cancel.disabled) return;
			    var s = session;
			    s.cancelled = true;
			    cancel.disabled = true;
			    s.active.forEach(function(xhr){xhr.abort();});
			    try { await call('cancel',s); } catch(err) { /* Temporary parts expire automatically. */ }
			    if (session !== s) return;
			    session = null;
			    running = false;
			    fileInput.disabled = false;
			    submit.disabled = false;
			    submit.textContent = 'بارگذاری و انتشار نسخه';
			    panel.classList.add('is-error');
			    statusEl.textContent = 'بارگذاری لغو شد';
			    detail.textContent = 'فایل منتشر نشده است. در صورت نیاز دوباره شروع کنید.';
			    cancel.hidden = true;
			  });
			})();
			</script>
		<?php
		echo '</div>';
	}

	/**
	 * Previous admin screen (kept for reference; not registered).
	 */
	public static function page_legacy() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$m   = isset( $_GET['m'] ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$rel = self::release();
		$l   = self::local();
		$fa  = static fn( $s ) => esc_html( bmn_fa_digits( (string) $s ) );
		echo '<div class="wrap bmnm-wrap"><div class="bmnm-hero"><h1>نسخهٔ اپ و دانلود</h1><p>فایل APK را همین‌جا بارگذاری کنید؛ شمارهٔ نسخه خودکار از داخل فایل خوانده می‌شود. صفحهٔ دانلود سایت و دکمهٔ «بروزرسانی» داخل اپ از همین نسخه استفاده می‌کنند.</p></div>';
		if ( 'ok' === $m ) {
			echo '<div class="notice notice-success is-dismissible"><p>انجام شد.</p></div>';
		} elseif ( $m ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
		}
		echo '<div class="bmnm-cards"><div class="bmnm-panel"><h2>نسخه‌ای که کاربران دریافت می‌کنند</h2>';
		if ( $rel['url'] ) {
			echo '<p style="font-size:28px;font-weight:800;margin:.2em 0">' . $fa( $rel['latest'] ) . '</p><p class="bmnm-muted">' . ( $rel['code'] ? 'شمارهٔ ساخت: ' . $fa( $rel['code'] ) . ' · ' : '' ) . ( $rel['size'] ? esc_html( size_format( $rel['size'] ) ) . ' · ' : '' ) . esc_html( 'local' === $rel['source'] ? 'فایل روی همین سایت' : ( 'github' === $rel['source'] ? 'گیت‌هاب' : 'لینک دستی' ) ) . '</p><code dir="ltr" style="word-break:break-all">' . esc_html( $rel['url'] ) . '</code>';
		} else {
			echo '<p class="bmnm-muted">هنوز نسخه‌ای تنظیم نشده؛ کاربران فقط نسخهٔ وب (PWA) را می‌بینند.</p>';
		}
		echo '<p><a class="button" href="' . esc_url( home_url( '/app/download/' ) ) . '" target="_blank">مشاهدهٔ صفحهٔ دانلود ↗</a></p></div>';
		echo '<div class="bmnm-panel"><h2>بارگذاری نسخهٔ جدید (APK)</h2>';
		if ( $l ) {
			echo '<p><span class="bmnm-badge ok">روی سایت: ' . $fa( $l['name'] ) . ' (' . $fa( $l['code'] ) . ')</span> <span class="bmnm-muted">' . esc_html( size_format( $l['size'] ) . ' · ' . human_time_diff( $l['at'] ) ) . ' پیش</span></p>';
		}
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bmn_app_upload">';
		wp_nonce_field( 'bmn_app_upload' );
		echo '<p><input type="file" name="apk" accept=".apk,application/vnd.android.package-archive" required> <button class="button button-primary">بارگذاری</button></p><p class="description">سقف آپلود هاست: ' . esc_html( (string) ini_get( 'upload_max_filesize' ) ) . '. فقط دو نسخهٔ آخر نگه داشته می‌شود.</p></form>';
		if ( $l ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bmn_app_remove">';
			wp_nonce_field( 'bmn_app_remove' );
			echo '<button class="button-link button-link-delete" onclick="return confirm(\'فایل از سایت حذف شود؟\')">حذف فایل از سایت</button></form>';
		}
		echo '</div></div>';
		echo '<div class="bmnm-panel"><h2>تنظیمات تکمیلی</h2><p>تغییرات نسخه، حداقل نسخهٔ مجاز (به‌روزرسانی اجباری)، لینک کافه‌بازار و مایکت و مخزن گیت‌هاب در <a href="' . esc_url( admin_url( 'admin.php?page=bmn-settings' ) ) . '">تنظیمات شهرداری ← بروزرسانی اپ موبایل</a> است.</p>';
		echo '<p class="bmnm-muted">آدرس API برای اپ: <code dir="ltr">' . esc_html( rest_url( 'bmn/v1/app/version' ) ) . '</code></p></div></div>';
	}

	/**
	 * Final release info: the highest version wins among the hosted APK, manual settings and GitHub.
	 *
	 * @return array
	 */
	public static function release() {
		// Prefer a local verified APK; use the trusted stable GitHub release when newer.
		$out = array( 'latest' => '', 'code' => 0, 'url' => '', 'sha' => '', 'size' => 0, 'notes' => (string) bmn_option( 'app_notes', '' ), 'published' => '', 'source' => '' );
		$l   = self::local();
		if ( $l ) {
			$out = array_merge( $out, array( 'latest' => $l['name'], 'code' => (int) $l['code'], 'url' => $l['url'], 'sha' => $l['sha'], 'size' => (int) $l['size'], 'published' => gmdate( 'c', (int) $l['at'] ), 'source' => 'local' ) );
		}
		$gh = self::github();
		// Newer stable APKs require a verified GitHub SHA-256 digest.
		if ( is_array( $gh ) && ! empty( $gh['sha'] ) && ! empty( $gh['url'] ) && ( ! $out['latest'] || version_compare( $gh['version'], $out['latest'], '>' ) ) ) {
			$code = 0;
			if ( preg_match( '/^1\\.0\\.(\\d+)$/', $gh['version'], $parts ) ) {
				$code = (int) $parts[1];
			}
			$out = array_merge( $out, array(
				'latest' => $gh['version'], 'code' => $code,
				'url' => $gh['url'], 'sha' => $gh['sha'], 'size' => (int) $gh['size'],
				'notes' => $gh['notes'], 'published' => $gh['published'], 'source' => 'github',
			) );
		}
		return $out;
	}

	/**
	 * [bmn_app_download] — download card for the website.
	 *
	 * @return string
	 */
	public static function shortcode() {
		$r      = self::release();
		$fa     = static fn( $s ) => esc_html( bmn_fa_digits( (string) $s ) );
		$bazaar = (string) bmn_option( 'app_bazaar', '' );
		$myket  = (string) bmn_option( 'app_myket', '' );
		$and    = '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M6 18a1 1 0 001 1h1v3a1.5 1.5 0 003 0v-3h2v3a1.5 1.5 0 003 0v-3h1a1 1 0 001-1V8H6zm-2.5-10A1.5 1.5 0 002 9.5v7a1.5 1.5 0 003 0v-7A1.5 1.5 0 003.5 8zm17 0A1.5 1.5 0 0019 9.5v7a1.5 1.5 0 003 0v-7A1.5 1.5 0 0020.5 8zM15.5 2.2l1.3-1.3-.6-.6-1.5 1.5A6 6 0 0012 1a6 6 0 00-2.7.8L7.8.3l-.6.6 1.3 1.3A5.9 5.9 0 006 7h12a5.9 5.9 0 00-2.5-4.8zM10 5H9V4h1zm5 0h-1V4h1z"/></svg>';
		$h      = '<section class="bmn-dl"><div class="bmn-dl__card"><div class="bmn-dl__txt"><span class="bmn-dl__chip">اندروید · رایگان</span><h2>«بشرویهٔ من» همیشه در جیب شما</h2><ul>';
		foreach ( array( 'پرداخت عوارض نوسازی، خودرو و کسب‌وپیشه', 'گزارش ۱۳۷ با عکس و موقعیت روی نقشه', 'پیگیری درخواست‌ها و اعلان نتیجه', 'نوبت‌دهی واحدها و ملاقات با شهردار', 'مینی‌بوس آنلاین، زمان پسماند و اطلاعیه‌های محله', 'ورود پیامکی بدون رمز' ) as $f ) {
			$h .= '<li>' . esc_html( $f ) . '</li>';
		}
		$h .= '</ul><div class="bmn-dl__btns">';
		if ( $r['url'] ) {
			$h .= '<a class="bmn-dl__btn is-main" href="' . esc_url( $r['url'] ) . '" download>' . $and . '<span>دانلود مستقیم APK<small>' . ( $r['latest'] ? 'نسخهٔ ' . $fa( $r['latest'] ) : '' ) . ( $r['size'] ? ' · ' . esc_html( bmn_fa_digits( size_format( $r['size'], 1 ) ) ) : '' ) . '</small></span></a>';
		}
		if ( $bazaar ) {
			$h .= '<a class="bmn-dl__btn" href="' . esc_url( $bazaar ) . '" target="_blank" rel="noopener"><span>کافه‌بازار</span></a>';
		}
		if ( $myket ) {
			$h .= '<a class="bmn-dl__btn" href="' . esc_url( $myket ) . '" target="_blank" rel="noopener"><span>مایکت</span></a>';
		}
		$h .= '<a class="bmn-dl__btn" href="' . esc_url( home_url( '/app/' ) ) . '"><span>نسخهٔ وب (آیفون و همهٔ گوشی‌ها)</span></a></div>';
		if ( ! $r['url'] ) {
			$h .= '<p class="bmn-dl__soon">نسخهٔ اندروید به‌زودی منتشر می‌شود. تا آن زمان نسخهٔ وب را روی صفحهٔ اصلی گوشی نصب کنید.</p>';
		}
		if ( $r['notes'] ) {
			$h .= '<details class="bmn-dl__notes"><summary>تازه‌های این نسخه</summary><p>' . nl2br( esc_html( $r['notes'] ) ) . '</p></details>';
		}
		$h .= '</div><div class="bmn-dl__art" aria-hidden="true"><div class="bmn-dl__phone"><img src="' . esc_url( BMN_CORE_URL . 'assets/img/icon-512.png' ) . '" alt="" width="120" height="120" loading="lazy"><b>بشرویهٔ من</b><span>شهرداری بشرویه</span></div></div></div>';
		$h .= '<ol class="bmn-dl__how"><li><b>دانلود</b>دکمهٔ «دانلود مستقیم APK» را بزنید.</li><li><b>اجازهٔ نصب</b>اگر اندروید پرسید، «نصب از این منبع» را فعال کنید.</li><li><b>نصب و ورود</b>فایل را باز کنید و با شمارهٔ همراه وارد شوید.</li><li><b>بروزرسانی</b>از داخل اپ: منو ← «بروزرسانی»؛ نسخهٔ جدید خودکار دانلود می‌شود.</li></ol></section>';
		return $h;
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route(
			'bmn/v1',
			'/app/version',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'version' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'platform' => array(
						'type'    => 'string',
						'enum'    => array( 'android', 'ios' ),
						'default' => 'android',
					),
				),
			)
		);
	}

	/**
	 * Minimum supported version (forces update below it).
	 *
	 * @param string $v Default.
	 * @return string
	 */
	public static function min_version( $v ) {
		$min = self::clean_version( (string) bmn_option( 'app_min', '' ) );
		return $min ? $min : $v;
	}

	/**
	 * Normalize "v1.2.3" → "1.2.3".
	 *
	 * @param string $v Version.
	 * @return string
	 */
	public static function clean_version( $v ) {
		$v = ltrim( trim( bmn_en_digits( $v ) ), 'vV' );
		return preg_match( '/^\d+(\.\d+){0,3}$/', $v ) ? $v : '';
	}

	/**
	 * Latest GitHub release (cached 30 min).
	 *
	 * @return array{version:string,url:string,notes:string,published:string}|null
	 */
	private static function github() {
		$repo = (string) bmn_option( 'app_github', '' );
		$repo = preg_match( '#^[\w.-]+/[\w.-]+$#', $repo ) ? $repo : self::REPO;
		$key  = 'bmn_gh_rel_' . md5( $repo );
		$hit  = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit ? $hit : null;
		}
		$res  = wp_safe_remote_get(
			'https://api.github.com/repos/' . $repo . '/releases/latest',
			array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);
		$data = array();
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$j = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( is_array( $j ) ) {
				$url = '';
				$sha = '';
				$size = 0;
				foreach ( (array) ( $j['assets'] ?? array() ) as $a ) {
					if ( isset( $a['browser_download_url'] ) && str_ends_with( (string) $a['name'], '.apk' ) ) {
						$url = (string) $a['browser_download_url'];
						$digest = (string) ( $a['digest'] ?? '' );
						if ( preg_match( '/^sha256:([a-f0-9]{64})$/i', $digest, $m ) ) {
							$sha = strtolower( $m[1] );
						}
						$size = max( 0, (int) ( $a['size'] ?? 0 ) );
						break;
					}
				}
				$ver = self::clean_version( (string) ( $j['tag_name'] ?? '' ) );
				if ( $ver && $url ) {
					$data = array(
						'version'   => $ver,
						'url'       => $url,
						'sha'       => $sha,
						'size'      => $size,
						'notes'     => wp_strip_all_tags( (string) ( $j['body'] ?? '' ) ),
						'published' => (string) ( $j['published_at'] ?? '' ),
					);
				}
			}
		}
		// Limit stale app-version notifications after a new signed Android release.
		set_transient( $key, $data, $data ? 10 * MINUTE_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
		return $data ? $data : null;
	}

	/**
	 * GET /app/version
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function version( WP_REST_Request $req ) {
		$rel    = self::release();
		$latest = $rel['latest'];
		$url    = 'ios' === $req['platform'] ? '' : $rel['url'];
		$notes  = $rel['notes'];
		$pub    = $rel['published'];
		$stores = array_filter(
			array(
				'bazaar' => (string) bmn_option( 'app_bazaar', '' ),
				'myket'  => (string) bmn_option( 'app_myket', '' ),
			)
		);
		$res = rest_ensure_response(
			array(
				'latest'    => $latest,
				'min'       => self::min_version( '1.0.0' ),
				'url'       => esc_url_raw( $url ),
				'notes'     => mb_substr( $notes, 0, 1500 ),
				'published' => $pub,
				// Always emit a JSON object, including when no app-store links exist.
				// Older Android clients cast `stores` to Map and fail on an empty JSON array.
				'stores'    => (object) $stores,
				// Extra fields for in-app updating (same data, explicit names).
				'versionCode' => (int) $rel['code'],
				'versionName' => $latest,
				'apk'         => esc_url_raw( $url ),
				'sha256'      => (string) $rel['sha'],
				'size'        => (int) $rel['size'],
				'download_page' => home_url( '/app/download/' ),
			)