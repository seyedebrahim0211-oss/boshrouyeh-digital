<?php
/**
 * Dynamic (server-rendered) blocks used by the Khesht theme and the page builder.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

require_once BMN_CORE_DIR . 'includes/block-renders.php';

/**
 * Blocks registry.
 */
final class BMN_Blocks {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_assets' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'category' ) );
		add_filter( 'bmn_service_start_html', array( __CLASS__, 'core_service_start' ), 5, 2 );
	}

	/**
	 * Block definitions: name => [title, icon, attributes, render callback].
	 *
	 * @return array
	 */
	public static function definitions() {
		$num  = fn( $d ) => array( 'type' => 'number', 'default' => $d );
		$str  = fn( $d ) => array( 'type' => 'string', 'default' => $d );
		return array(
			'breadcrumbs'          => array( __( 'مسیر راهنما', 'boshrouyeh-core' ), 'menu', array(), 'bmn_render_breadcrumbs' ),
			'reading-time'         => array( __( 'زمان مطالعه', 'boshrouyeh-core' ), 'clock', array(), 'bmn_render_reading_time' ),
			'share'                => array( __( 'اشتراک‌گذاری', 'boshrouyeh-core' ), 'share', array(), 'bmn_render_share' ),
			'quick-services'       => array( __( 'میانبر خدمات', 'boshrouyeh-core' ), 'grid-view', array( 'count' => $num( 7 ) ), 'bmn_render_quick_services' ),
			'notices'              => array( __( 'اطلاعیه‌ها', 'boshrouyeh-core' ), 'megaphone', array( 'count' => $num( 5 ), 'layout' => $str( 'compact' ) ), 'bmn_render_notices' ),
			'service-directory'    => array( __( 'فهرست خدمات با فیلتر', 'boshrouyeh-core' ), 'list-view', array(), 'bmn_render_service_directory' ),
			'service-header'       => array( __( 'سربرگ خدمت', 'boshrouyeh-core' ), 'heading', array(), 'bmn_render_service_header' ),
			'service-details'      => array( __( 'مراحل و مدارک خدمت', 'boshrouyeh-core' ), 'editor-ol', array(), 'bmn_render_service_details' ),
			'service-start'        => array( __( 'شروع خدمت', 'boshrouyeh-core' ), 'controls-play', array(), 'bmn_render_service_start' ),
			'service-faq'          => array( __( 'پرسش‌های پرتکرار خدمت', 'boshrouyeh-core' ), 'editor-help', array(), 'bmn_render_service_faq' ),
			'tender-list'          => array( __( 'فهرست مناقصه و مزایده', 'boshrouyeh-core' ), 'clipboard', array( 'count' => $num( 30 ) ), 'bmn_render_tender_list' ),
			'tender-details'       => array( __( 'جزئیات مناقصه', 'boshrouyeh-core' ), 'clipboard', array(), 'bmn_render_tender_details' ),
			'place-grid'           => array( __( 'جاذبه‌های گردشگری', 'boshrouyeh-core' ), 'location-alt', array( 'count' => $num( 24 ) ), 'bmn_render_place_grid' ),
			'place-details'        => array( __( 'جزئیات جاذبه', 'boshrouyeh-core' ), 'location', array(), 'bmn_render_place_details' ),
			'event-list'           => array( __( 'تقویم رویدادها', 'boshrouyeh-core' ), 'calendar-alt', array( 'count' => $num( 20 ) ), 'bmn_render_event_list' ),
			'event-details'        => array( __( 'جزئیات رویداد', 'boshrouyeh-core' ), 'calendar', array(), 'bmn_render_event_details' ),
			'notice-details'       => array( __( 'جزئیات اطلاعیه', 'boshrouyeh-core' ), 'megaphone', array(), 'bmn_render_notice_details' ),
			'transparency-list'    => array( __( 'اسناد شفافیت', 'boshrouyeh-core' ), 'chart-bar', array(), 'bmn_render_transparency_list' ),
			'transparency-details' => array( __( 'جزئیات سند شفافیت', 'boshrouyeh-core' ), 'media-document', array(), 'bmn_render_transparency_details' ),
			'waste-schedule'       => array( __( 'زمان‌بندی جمع‌آوری پسماند', 'boshrouyeh-core' ), 'trash', array(), 'bmn_render_waste_schedule' ),
			'deceased-search'      => array( __( 'جست‌وجوی متوفیان', 'boshrouyeh-core' ), 'search', array(), 'bmn_render_deceased_search' ),
			'contact-info'         => array( __( 'اطلاعات تماس', 'boshrouyeh-core' ), 'phone', array(), 'bmn_render_contact_info' ),
		);
	}

	/**
	 * Register blocks.
	 */
	public static function register() {
		foreach ( self::definitions() as $slug => $def ) {
			register_block_type(
				'boshrouyeh/' . $slug,
				array(
					'api_version'     => 3,
					'title'           => $def[0],
					'category'        => 'boshrouyeh',
					'attributes'      => array_merge(
						$def[2],
						array(
							'align'     => array( 'type' => 'string', 'default' => '' ),
							'className' => array( 'type' => 'string', 'default' => '' ),
						)
					),
					'supports'        => array(
						'align' => array( 'wide', 'full' ),
						'html'  => false,
					),
					'render_callback' => function ( $attrs, $content, $block ) use ( $def ) {
						$html = call_user_func( $def[3], $attrs, $block );
						if ( '' === $html ) {
							return '';
						}
						$class = trim( 'bmn-block ' . ( ! empty( $attrs['align'] ) ? 'align' . sanitize_html_class( $attrs['align'] ) : '' ) . ' ' . ( ! empty( $attrs['className'] ) ? implode( ' ', array_map( 'sanitize_html_class', explode( ' ', $attrs['className'] ) ) ) : '' ) );
						return '<div class="' . esc_attr( $class ) . '">' . $html . '</div>';
					},
				)
			);
		}
	}

	/**
	 * Start box for e-services fulfilled by this plugin (waste schedule, deceased search).
	 *
	 * @param string $html Html.
	 * @param string $key  E-service key.
	 * @return string
	 */
	public static function core_service_start( $html, $key ) {
		if ( 'waste' === $key ) {
			return bmn_render_waste_schedule();
		}
		if ( 'deceased' === $key ) {
			return bmn_render_deceased_search();
		}
		return $html;
	}

	/**
	 * Block category.
	 *
	 * @param array $cats Categories.
	 * @return array
	 */
	public static function category( $cats ) {
		array_unshift(
			$cats,
			array(
				'slug'  => 'boshrouyeh',
				'title' => __( 'شهرداری بشرویه', 'boshrouyeh-core' ),
			)
		);
		return $cats;
	}

	/**
	 * Editor script: client-side registration with ServerSideRender previews.
	 */
	public static function editor_assets() {
		$defs = array();
		foreach ( self::definitions() as $slug => $def ) {
			$defs[] = array(
				'name'       => 'boshrouyeh/' . $slug,
				'title'      => $def[0],
				'icon'       => $def[1],
				'attributes' => $def[2],
			);
		}
		wp_enqueue_script( 'bmn-blocks-editor', BMN_CORE_URL . 'assets/js/blocks-editor.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ), BMN_CORE_VERSION, true );
		wp_localize_script( 'bmn-blocks-editor', 'BMNBlocks', array( 'blocks' => $defs ) );
		wp_enqueue_style( 'bmn-components', BMN_CORE_URL . 'assets/css/components.css', array(), BMN_CORE_VERSION );
	}

	/**
	 * Front-end assets.
	 */
	public static function front_assets() {
		wp_enqueue_style( 'bmn-components', BMN_CORE_URL . 'assets/css/components.css', array(), BMN_CORE_VERSION );
		wp_enqueue_script(
			'bmn-front',
			BMN_CORE_URL . 'assets/js/frontend.js',
			array(),
			BMN_CORE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'bmn-front',
			'BMNFront',
			array(
				'rest'      => esc_url_raw( rest_url( 'bmn/v1/' ) ),
				'noResult'  => __( 'نتیجه‌ای یافت نشد.', 'boshrouyeh-core' ),
				'minChars'  => __( 'دست‌کم ۳ حرف وارد کنید.', 'boshrouyeh-core' ),
				'error'     => __( 'خطا در ارتباط؛ دوباره تلاش کنید.', 'boshrouyeh-core' ),
				'tooMany'   => __( 'درخواست‌ها زیاد است؛ کمی بعد تلاش کنید.', 'boshrouyeh-core' ),
				'plot'      => __( 'محل دفن', 'boshrouyeh-core' ),
				'death'     => __( 'تاریخ وفات', 'boshrouyeh-core' ),
				'father'    => __( 'نام پدر', 'boshrouyeh-core' ),
				'weekdays'  => bmn_weekdays(),
			)
		);
	}
}
