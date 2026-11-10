<?php
/**
 * Imports news from the official municipality site (RSS, with WP REST fallback) into local posts.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * News importer.
 */
class BMN_News_Import {

	const HOOK     = 'bmn_news_sync';
	const DEFAULT  = 'https://boshrooyeh.ir/feed/';
	const CATEGORY = 'اخبار شهرداری';
	const UA       = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
	const MAX      = 20;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'sync' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'admin_post_bmn_news_sync', array( __CLASS__, 'handle' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'the_content', array( __CLASS__, 'source_link' ), 20 );
	}

	/**
	 * Schedule hourly sync (unless disabled).
	 */
	public static function schedule() {
		$on = '0' !== (string) bmn_option( 'news_auto', '1' );
		$at = wp_next_scheduled( self::HOOK );
		if ( $on && ! $at ) {
			wp_schedule_event( time() + 120, 'hourly', self::HOOK );
		} elseif ( ! $on && $at ) {
			wp_clear_scheduled_hook( self::HOOK );
		}
	}

	/**
	 * Admin "sync now".
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'boshrouyeh-core' ), 403 );
		}
		check_admin_referer( 'bmn_news_sync' );
		$r = self::sync();
		$args = array(
			'page'         => 'bmn-settings',
			'bmn_news'     => (int) $r['created'],
			'bmn_news_err' => $r['error'] ? 1 : 0,
		);
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * REST: POST /bmn/v1/admin/news-sync (admins only).
	 */
	public static function routes() {
		register_rest_route(
			'bmn/v1',
			'/admin/news-sync',
			array(
				'methods'             => 'POST',
				'callback'            => function () {
					return rest_ensure_response( self::sync() );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Feed URL from settings.
	 *
	 * @return string
	 */
	public static function source() {
		$url = (string) bmn_option( 'news_feed', '' );
		return $url ? $url : self::DEFAULT;
	}

	/**
	 * Run a sync.
	 *
	 * @return array{created:int,skipped:int,source:string,error:string}
	 */
	public static function sync() {
		$out = array(
			'created' => 0,
			'skipped' => 0,
			'source'  => '',
			'error'   => '',
		);
		if ( get_transient( 'bmn_news_lock' ) ) {
			$out['error'] = 'locked';
			return $out;
		}
		set_transient( 'bmn_news_lock', 1, 5 * MINUTE_IN_SECONDS );

		$items = self::from_feed( self::source() );
		$out['source'] = 'rss';
		if ( is_wp_error( $items ) || ! $items ) {
			$err   = is_wp_error( $items ) ? $items->get_error_message() : 'empty feed';
			$items = self::from_rest( self::source() );
			$out['source'] = 'rest';
			if ( is_wp_error( $items ) ) {
				$out['error'] = $err . ' | ' . $items->get_error_message();
				$items        = array();
			}
		}

		$cat = term_exists( self::CATEGORY, 'category' );
		if ( ! $cat ) {
			$cat = wp_insert_term( self::CATEGORY, 'category', array( 'slug' => 'municipality-news' ) );
		}
		$cat_id = is_array( $cat ) ? (int) $cat['term_id'] : 0;

		foreach ( array_slice( $items, 0, self::MAX ) as $it ) {
			if ( self::exists( $it['guid'], $it['link'] ) ) {
				++$out['skipped'];
				continue;
			}
			if ( self::insert( $it, $cat_id ) ) {
				++$out['created'];
			}
		}

		delete_transient( 'bmn_news_lock' );
		update_option(
			'bmn_news_last',
			array(
				'time'   => time(),
				'result' => $out,
			),
			false
		);
		return $out;
	}

	/**
	 * Remote GET with a browser UA (the source sits behind LiteSpeed bot rules).
	 *
	 * @param string $url URL.
	 * @return string|WP_Error Body.
	 */
	private static function get( $url ) {
		$res = wp_safe_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => self::UA,
				'headers'    => array( 'Accept' => 'application/rss+xml, application/xml, application/json, text/html;q=0.8' ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			return new WP_Error( 'bmn_http', 'HTTP ' . $code . ' @ ' . $url );
		}
		return (string) wp_remote_retrieve_body( $res );
	}

	/**
	 * Parse RSS/Atom.
	 *
	 * @param string $url Feed URL.
	 * @return array|WP_Error
	 */
	private static function from_feed( $url ) {
		$body = self::get( $url );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		if ( false === stripos( $body, '<rss' ) && false === stripos( $body, '<feed' ) ) {
			return new WP_Error( 'bmn_feed', 'not a feed' );
		}
		require_once ABSPATH . WPINC . '/class-simplepie.php';
		$feed = new SimplePie();
		$feed->set_raw_data( $body );
		$feed->enable_cache( false );
		$feed->set_stupidly_fast( true );
		if ( ! $feed->init() ) {
			return new WP_Error( 'bmn_feed', (string) $feed->error() );
		}
		$items = array();
		foreach ( $feed->get_items( 0, self::MAX ) as $item ) {
			$content = (string) $item->get_content();
			$img     = '';
			$enc     = $item->get_enclosure();
			if ( $enc && $enc->get_link() && preg_match( '/\.(jpe?g|png|webp|gif)(\?|$)/i', (string) $enc->get_link() ) ) {
				$img = (string) $enc->get_link();
			}
			$items[] = array(
				'guid'    => (string) ( $item->get_id() ? $item->get_id() : $item->get_permalink() ),
				'link'    => (string) $item->get_permalink(),
				'title'   => html_entity_decode( (string) $item->get_title(), ENT_QUOTES, 'UTF-8' ),
				'content' => $content,
				'excerpt' => wp_strip_all_tags( (string) $item->get_description() ),
				'date'    => (string) $item->get_gmdate( 'Y-m-d H:i:s' ),
				'image'   => $img,
			);
		}
		return $items;
	}

	/**
	 * Fallback: source site's WP REST API.
	 *
	 * @param string $feed Feed URL (origin is derived from it).
	 * @return array|WP_Error
	 */
	private static function from_rest( $feed ) {
		$p = wp_parse_url( $feed );
		if ( empty( $p['host'] ) ) {
			return new WP_Error( 'bmn_feed', 'bad url' );
		}
		$url  = ( $p['scheme'] ?? 'https' ) . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . (int) $p['port'] : '' ) . '/wp-json/wp/v2/posts?per_page=' . self::MAX . '&_embed=wp:featuredmedia';
		$body = self::get( $url );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'bmn_feed', 'bad json' );
		}
		$items = array();
		foreach ( $data as $d ) {
			if ( empty( $d['link'] ) ) {
				continue;
			}
			$items[] = array(
				'guid'    => (string) ( $d['guid']['rendered'] ?? $d['link'] ),
				'link'    => (string) $d['link'],
				'title'   => html_entity_decode( wp_strip_all_tags( (string) ( $d['title']['rendered'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ),
				'content' => (string) ( $d['content']['rendered'] ?? '' ),
				'excerpt' => wp_strip_all_tags( (string) ( $d['excerpt']['rendered'] ?? '' ) ),
				'date'    => str_replace( 'T', ' ', (string) ( $d['date_gmt'] ?? '' ) ),
				'image'   => (string) ( $d['_embedded']['wp:featuredmedia'][0]['source_url'] ?? '' ),
			);
		}
		return $items;
	}

	/**
	 * Already imported?
	 *
	 * @param string $guid GUID.
	 * @param string $link Permalink.
	 * @return bool
	 */
	private static function exists( $guid, $link ) {
		$q = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => '_bmn_src_guid',
						'value' => md5( $guid ),
					),
					array(
						'key'   => '_bmn_src_url',
						'value' => esc_url_raw( $link ),
					),
				),
			)
		);
		return (bool) $q->posts;
	}

	/**
	 * Create the post.
	 *
	 * @param array $it     Item.
	 * @param int   $cat_id Category.
	 * @return int Post id or 0.
	 */
	private static function insert( $it, $cat_id ) {
		$title = trim( wp_strip_all_tags( $it['title'] ) );
		if ( '' === $title ) {
			return 0;
		}
		$content = self::clean( $it['content'], $it['link'] );
		$img     = $it['image'];
		if ( ! $img && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m ) ) {
			$img = $m[1];
		}
		$date = $it['date'] && strtotime( $it['date'] ) ? $it['date'] : gmdate( 'Y-m-d H:i:s' );
		$id   = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => $title,
				'post_content'  => $content,
				'post_excerpt'  => wp_trim_words( $it['excerpt'], 40, '…' ),
				'post_date_gmt' => $date,
				'post_date'     => get_date_from_gmt( $date ),
				'post_category' => $cat_id ? array( $cat_id ) : array(),
				'meta_input'    => array(
					'_bmn_src_guid' => md5( $it['guid'] ),
					'_bmn_src_url'  => esc_url_raw( $it['link'] ),
				),
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		if ( $img && wp_http_validate_url( $img ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$att = media_sideload_image( esc_url_raw( $img ), $id, $title, 'id' );
			if ( ! is_wp_error( $att ) ) {
				set_post_thumbnail( $id, (int) $att );
			}
		}
		return (int) $id;
	}

	/**
	 * Sanitize HTML and make relative URLs absolute.
	 *
	 * @param string $html HTML.
	 * @param string $base Source permalink.
	 * @return string
	 */
	private static function clean( $html, $base ) {
		$p      = wp_parse_url( $base );
		$origin = isset( $p['host'] ) ? ( $p['scheme'] ?? 'https' ) . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . (int) $p['port'] : '' ) : '';
		$html   = preg_replace( '#<(script|style|iframe)[^>]*>.*?</\1>#is', '', (string) $html );
		if ( $origin ) {
			$html = preg_replace( '#(src|href)=(["\'])/(?!/)#i', '$1=$2' . $origin . '/', $html );
		}
		return wp_kses_post( $html );
	}

	/**
	 * Append "source" link to imported news.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function source_link( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() ) {
			return $content;
		}
		$src = get_post_meta( get_the_ID(), '_bmn_src_url', true );
		if ( ! $src ) {
			return $content;
		}
		return $content . '<p class="bmn-news-source">' . esc_html__( 'منبع:', 'boshrouyeh-core' ) . ' <a href="' . esc_url( $src ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'پایگاه اطلاع‌رسانی شهرداری بشرویه', 'boshrouyeh-core' ) . '</a></p>';
	}
}
