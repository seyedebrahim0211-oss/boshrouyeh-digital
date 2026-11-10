<?php
/**
 * Progressive Web App: manifest, root-scoped service worker and offline page.
 * Navigation responses are never cached (avoids leaking personalised pages); only static
 * assets are cached and one offline fallback page is precached.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * PWA.
 */
final class BMN_PWA {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 3 );
		add_action( 'wp_footer', array( __CLASS__, 'register_sw' ), 99 );
	}

	/**
	 * Rewrite rules.
	 */
	public static function rewrite() {
		add_rewrite_rule( '^manifest\.webmanifest$', 'index.php?bmn_pwa=manifest', 'top' );
		add_rewrite_rule( '^bmn-sw\.js$', 'index.php?bmn_pwa=sw', 'top' );
		add_rewrite_rule( '^offline/?$', 'index.php?bmn_pwa=offline', 'top' );
	}

	/**
	 * Query vars.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public static function vars( $vars ) {
		$vars[] = 'bmn_pwa';
		return $vars;
	}

	/**
	 * Head tags.
	 */
	public static function head() {
		printf( '<link rel="manifest" href="%s">' . "\n", esc_url( home_url( '/manifest.webmanifest' ) ) );
		printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( BMN_CORE_URL . 'assets/img/icon-192.png' ) );
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
		if ( ! has_site_icon() ) {
			printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( BMN_CORE_URL . 'assets/img/logo-brick.svg' ) );
			printf( '<link rel="alternate icon" href="%s" type="image/png">' . "\n", esc_url( BMN_CORE_URL . 'assets/img/favicon-64.png' ) );
		}
	}

	/**
	 * Register service worker.
	 */
	public static function register_sw() {
		$sw    = wp_json_encode( home_url( '/bmn-sw.js' ) );
		$scope = wp_json_encode( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		echo "<script>if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register(" . $sw . ',{scope:' . $scope . "}).catch(function(){});});}</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded values.
	}

	/**
	 * Serve virtual files.
	 */
	public static function serve() {
		$what = get_query_var( 'bmn_pwa' );
		if ( ! $what ) {
			return;
		}
		if ( 'manifest' === $what ) {
			self::manifest();
		} elseif ( 'sw' === $what ) {
			self::service_worker();
		} elseif ( 'offline' === $what ) {
			self::offline();
		}
		exit;
	}

	/**
	 * Manifest.
	 */
	private static function manifest() {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=86400' );
		$icons = array();
		foreach ( array( 192, 512 ) as $s ) {
			$icons[] = array( 'src' => BMN_CORE_URL . "assets/img/icon-{$s}.png", 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => 'any' );
		}
		$icons[] = array( 'src' => BMN_CORE_URL . 'assets/img/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' );
		echo wp_json_encode(
			array(
				'name'             => 'بشرویهٔ من — شهرداری بشرویه',
				'short_name'       => 'بشرویهٔ من',
				'description'      => 'خدمات الکترونیک شهرداری بشرویه: پرداخت عوارض، سامانه ۱۳۷، پیگیری و گردشگری',
				'lang'             => 'fa',
				'dir'              => 'rtl',
				'start_url'        => home_url( '/?source=pwa' ),
				'scope'            => home_url( '/' ),
				'display'          => 'standalone',
				'background_color' => '#1F2A44',
				'theme_color'      => '#1F2A44',
				'icons'            => $icons,
				'shortcuts'        => array(
					array( 'name' => 'پرداخت عوارض', 'url' => home_url( '/services/renovation-dues/' ) ),
					array( 'name' => 'گزارش ۱۳۷', 'url' => home_url( '/report-137/' ) ),
					array( 'name' => 'پیگیری', 'url' => home_url( '/track/' ) ),
				),
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * Service worker.
	 */
	private static function service_worker() {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		header( 'Cache-Control: no-cache' );
		$version = 'bmn-' . BMN_CORE_VERSION . '-' . ( defined( 'KHESHT_VERSION' ) ? KHESHT_VERSION : '0' );
		$offline = home_url( '/offline/' );
		?>
const CACHE = <?php echo wp_json_encode( $version ); ?>;
const OFFLINE = <?php echo wp_json_encode( $offline ); ?>;
self.addEventListener('install', (e) => {
	e.waitUntil(caches.open(CACHE).then((c) => c.add(new Request(OFFLINE, { cache: 'reload' }))).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
	e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
	const req = e.request;
	if (req.method !== 'GET') return;
	const url = new URL(req.url);
	if (url.origin !== self.location.origin) return;
	if (/\/wp-(admin|login|json)/.test(url.pathname) || url.search.includes('preview=')) return;
	if (req.mode === 'navigate') {
		e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
		return;
	}
	if (/\.(css|js|woff2|svg|png|jpg|jpeg|webp)$/.test(url.pathname)) {
		e.respondWith(caches.open(CACHE).then(async (c) => {
			const hit = await c.match(req);
			const net = fetch(req).then((res) => { if (res.ok && res.type === 'basic') c.put(req, res.clone()); return res; }).catch(() => hit);
			return hit || net;
		}));
	}
});
		<?php
	}

	/**
	 * Inline municipality logo (currentColor).
	 *
	 * @return string
	 */
	public static function logo_svg() {
		$svg = (string) file_get_contents( BMN_CORE_DIR . 'assets/img/logo.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		return str_starts_with( ltrim( $svg ), '<svg' ) ? $svg : '';
	}

	/**
	 * Offline page (static, self-contained).
	 */
	private static function offline() {
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php esc_html_e( 'اتصال برقرار نیست', 'boshrouyeh-core' ); ?></title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#1F2A44;color:#F6EEE1;font-family:Vazirmatn,Tahoma,sans-serif;text-align:center;padding:24px}h1{color:#D9A441;font-size:1.6rem}a{display:inline-block;margin-top:16px;background:#16737A;color:#fff;padding:12px 24px;border-radius:10px;text-decoration:none}.logo{color:#E2C9A3;width:120px;height:120px;margin:0 auto 12px}.logo svg{width:100%;height:100%}</style>
</head><body><main><div class="logo"><?php echo self::logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static plugin SVG. ?></div><h1><?php esc_html_e( 'اتصال اینترنت برقرار نیست', 'boshrouyeh-core' ); ?></h1><p><?php esc_html_e( 'پس از برقراری اتصال دوباره تلاش کنید. شماره‌های اضطراری: آتش‌نشانی ۱۲۵ · سامانه ۱۳۷', 'boshrouyeh-core' ); ?></p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'تلاش دوباره', 'boshrouyeh-core' ); ?></a></main></body></html>
		<?php
	}
}
