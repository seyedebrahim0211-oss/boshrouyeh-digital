<?php
/**
 * Ready-made page templates (all built from the same widgets).
 *
 * @package KheshtBuilder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Templates.
 */
final class KB_Templates {

	/**
	 * Widget helper.
	 *
	 * @param string $type     Type.
	 * @param array  $settings Settings.
	 * @return array
	 */
	private static function w( $type, $settings = array() ) {
		return array( 'id' => substr( md5( uniqid( $type, true ) ), 0, 8 ), 'type' => $type, 'settings' => $settings );
	}

	/**
	 * Column helper.
	 *
	 * @param int   $span    Span.
	 * @param array $widgets Widgets.
	 * @return array
	 */
	private static function c( $span, $widgets ) {
		return array( 'id' => substr( md5( uniqid( 'c', true ) ), 0, 8 ), 'span' => $span, 'widgets' => $widgets );
	}

	/**
	 * Section helper.
	 *
	 * @param array $settings Settings.
	 * @param array $columns  Columns.
	 * @return array
	 */
	private static function s( $settings, $columns ) {
		return array( 'id' => substr( md5( uniqid( 's', true ) ), 0, 8 ), 'settings' => $settings, 'columns' => $columns );
	}

	/**
	 * All templates: key => [title, description, icon, layout].
	 *
	 * @return array
	 */
	public static function all() {
		$full = array( 'width' => 'full', 'pad' => 'none' );
		$t    = array();

		$t['home'] = array(
			'title' => 'صفحهٔ اصلی شهرداری',
			'desc'  => 'سربرگ خط آسمان، میانبر خدمات، اخبار و اطلاعیه‌ها، گردشگری و اپ',
			'icon'  => 'home',
			'sections' => array(
				self::s( $full, array( self::c( 12, array( self::w( 'hero', array( '_anim' => 'none', '_space' => 'none' ) ) ) ) ) ),
				self::s( array( 'pad' => 'none' ), array( self::c( 12, array( self::w( 'quick-services', array( '_class' => 'kb-overlap' ) ) ) ) ) ),
				self::s( array(), array(
					self::c( 8, array( self::w( 'heading', array( 'text' => 'تازه‌ترین اخبار', 'accent' => true, 'size' => 'md' ) ), self::w( 'news', array( 'count' => 3 ) ) ) ),
					self::c( 4, array( self::w( 'heading', array( 'text' => 'اطلاعیه‌ها', 'accent' => true, 'size' => 'md' ) ), self::w( 'notices', array( 'count' => 5 ) ) ) ),
				) ),
				self::s( array( 'bg' => 'night', 'round' => true ), array(
					self::c( 4, array( self::w( 'heading', array( 'text' => 'بشرویه را بگرد', 'tone' => 'sun' ) ), self::w( 'text', array( 'html' => '<p>جاذبه‌ها، مسیرها و راهنمای صوتی</p>', 'tone' => 'milk' ) ), self::w( 'buttons', array( 'items' => array( array( 'label' => 'همهٔ جاذبه‌ها', 'url' => '/places/', 'style' => 'sun', 'icon' => '', 'newtab' => false ) ) ) ) ) ),
					self::c( 8, array( self::w( 'places', array( 'count' => 4 ) ) ) ),
				) ),
				self::s( array(), array( self::c( 12, array( self::w( 'cta', array() ) ) ) ) ),
			),
		);

		$t['service-landing'] = array(
			'title' => 'معرفی یک خدمت / کمپین',
			'desc'  => 'تیتر، شمارش معکوس مهلت، مراحل، فرم پرداخت و پرسش‌ها',
			'icon'  => 'payment',
			'sections' => array(
				self::s( array( 'bg' => 'khesht', 'width' => 'wide' ), array( self::c( 12, array(
					self::w( 'heading', array( 'eyebrow' => 'خدمات الکترونیک', 'text' => 'پرداخت آنلاین عوارض نوسازی', 'level' => 'h1', 'size' => 'xl', 'tone' => 'brick' ) ),
					self::w( 'text', array( 'html' => '<p>بدون مراجعهٔ حضوری، در کمتر از ۳ دقیقه؛ با رسید الکترونیکی.</p>', 'size' => 'lg' ) ),
				) ) ) ),
				self::s( array(), array(
					self::c( 8, array( self::w( 'steps', array() ), self::w( 'alert', array() ), self::w( 'faq', array() ) ) ),
					self::c( 4, array( self::w( 'countdown', array() ), self::w( 'es-dues', array( 'type' => 'renovation' ) ) ) ),
				) ),
			),
		);

		$t['tourism'] = array(
			'title' => 'گردشگری «بشرویه را بگرد»',
			'desc'  => 'سربرگ شب کویر، آمار، جاذبه‌ها، نقشه و رویدادها',
			'icon'  => 'badgir',
			'sections' => array(
				self::s( $full, array( self::c( 12, array( self::w( 'hero', array( 'eyebrow' => 'شهرِ خشت، باد و قنات', 'title' => 'بشرویه را بگرد', 'text' => 'خانه‌های بادگیردار، قنات‌های زنده و آسمان پرستارهٔ کویر', 'search' => false, 'variant' => 'night', 'height' => 'md', 'buttons' => array( array( 'label' => 'جاذبه‌ها', 'url' => '#places', 'style' => 'sun' ) ), '_anim' => 'none' ) ) ) ) ) ),
				self::s( array(), array( self::c( 12, array( self::w( 'stats', array() ) ) ) ) ),
				self::s( array( 'anchor' => 'places' ), array( self::c( 12, array( self::w( 'heading', array( 'text' => 'جاذبه‌های شهر و شهرستان', 'accent' => true ) ), self::w( 'places', array( 'count' => 12 ) ) ) ) ) ),
				self::s( array( 'bg' => 'kahgel', 'top' => 'qanat' ), array(
					self::c( 6, array( self::w( 'heading', array( 'text' => 'رویدادهای پیش رو', 'size' => 'md' ) ), self::w( 'events', array() ) ) ),
					self::c( 6, array( self::w( 'map', array( 'zoom' => 'city' ) ) ) ),
				) ),
			),
		);

		$t['about'] = array(
			'title' => 'دربارهٔ بشرویه',
			'desc'  => 'چهار عنصر هویت، خط زمان تاریخی، نقل‌قول و گالری',
			'icon'  => 'building',
			'sections' => array(
				self::s( array( 'bg' => 'arches' ), array(
					self::c( 6, array( self::w( 'heading', array( 'eyebrow' => '۰۱ | ایدهٔ مرکزی', 'text' => 'بشرویه را از خاکش بشناسیم', 'level' => 'h1', 'size' => 'xl', 'tone' => 'brick' ) ), self::w( 'text', array( 'html' => '<p>بشرویه شهری است که با خشت و گل ساخته شده، با بادگیر نفس می‌کشد و با قنات زنده است.</p>', 'size' => 'lg' ) ) ) ),
					self::c( 6, array( self::w( 'image', array( 'frame' => 'taqche', 'ratio' => '4/3', 'illustration' => 'badgir-house' ) ) ) ),
				) ),
				self::s( array(), array( self::c( 12, array( self::w( 'cards', array() ) ) ) ) ),
				self::s( array( 'width' => 'content' ), array( self::c( 12, array( self::w( 'quote', array() ), self::w( 'timeline', array() ) ) ) ) ),
				self::s( array(), array( self::c( 12, array( self::w( 'heading', array( 'text' => 'نگاهی به شهر', 'accent' => true ) ), self::w( 'gallery', array() ) ) ) ) ),
			),
		);

		$t['contact'] = array(
			'title' => 'تماس و ارتباط',
			'desc'  => 'اطلاعات تماس، نقشه و فرم انتقاد و پیشنهاد',
			'icon'  => 'phone',
			'sections' => array(
				self::s( array(), array(
					self::c( 6, array( self::w( 'heading', array( 'text' => 'ارتباط با شهرداری', 'level' => 'h1', 'size' => 'xl', 'tone' => 'brick' ) ), self::w( 'contact', array() ), self::w( 'map', array() ) ) ),
					self::c( 6, array( self::w( 'es-feedback', array() ) ) ),
				) ),
			),
		);

		$t['blank-dark'] = array(
			'title' => 'بنر + سه ستون',
			'desc'  => 'شروعی سریع برای صفحات اطلاع‌رسانی',
			'icon'  => 'grid',
			'sections' => array(
				self::s( array( 'bg' => 'qanat', 'round' => true ), array( self::c( 12, array( self::w( 'heading', array( 'text' => 'عنوان صفحه', 'level' => 'h1', 'tone' => 'white', '_align' => 'center' ) ), self::w( 'text', array( 'html' => '<p>یک جملهٔ معرفی کوتاه.</p>', 'tone' => 'milk', '_align' => 'center' ) ) ) ) ) ),
				self::s( array(), array(
					self::c( 4, array( self::w( 'icon-box', array( 'icon' => 'building' ) ) ) ),
					self::c( 4, array( self::w( 'icon-box', array( 'icon' => 'drop' ) ) ) ),
					self::c( 4, array( self::w( 'icon-box', array( 'icon' => 'badgir' ) ) ) ),
				) ),
			),
		);

		return apply_filters( 'kb_templates', $t );
	}

	/**
	 * Template layout by key (sanitized).
	 *
	 * @param string $key Key.
	 * @return array|null
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? KB_Sanitizer::layout( array( 'sections' => $all[ $key ]['sections'] ) ) : null;
	}
}
