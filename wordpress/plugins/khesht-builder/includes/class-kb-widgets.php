<?php
/**
 * Widget registry: schema (drives the editor inspector + server sanitizer) and PHP renderers.
 * Every renderer receives already-sanitized settings and must escape on output.
 *
 * @package KheshtBuilder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Widgets.
 */
final class KB_Widgets {

	/**
	 * Cached registry.
	 *
	 * @var array|null
	 */
	private static $registry = null;

	/**
	 * Option sets.
	 *
	 * @return array
	 */
	public static function opts() {
		return array(
			'tone'   => array(
				'auto'   => 'خودکار',
				'brick'  => 'آجری تیره',
				'night'  => 'لاجوردی',
				'qanat'  => 'فیروزه‌ای',
				'khesht' => 'خشتی',
				'sun'    => 'زرد خورشید',
				'milk'   => 'شیری',
				'white'  => 'سفید',
			),
			'btn'    => array(
				'action'  => 'فیروزه‌ای (اقدام)',
				'outline' => 'حاشیهٔ آجری',
				'sun'     => 'خورشیدی',
				'ghost'   => 'کاهگلی',
			),
			'align'  => array(
				'start'  => 'راست',
				'center' => 'وسط',
				'end'    => 'چپ',
			),
			'size'   => array(
				'sm' => 'کوچک',
				'md' => 'متوسط',
				'lg' => 'بزرگ',
				'xl' => 'خیلی بزرگ',
			),
			'cols'   => array(
				'2' => '۲',
				'3' => '۳',
				'4' => '۴',
			),
			'icons'  => array_combine( self::icon_names(), self::icon_names() ),
		);
	}

	/**
	 * Icon names (shared set).
	 *
	 * @return string[]
	 */
	public static function icon_names() {
		if ( function_exists( 'bmn_icon_names' ) ) {
			return bmn_icon_names();
		}
		return array( 'grid', 'payment', 'permit', '137', 'waste', 'building', 'badgir', 'drop', 'star8', 'user', 'phone', 'pin', 'calendar', 'check', 'chart', 'shield' );
	}

	/**
	 * Icon markup.
	 *
	 * @param string $name Icon.
	 * @param int    $size Size.
	 * @return string
	 */
	public static function icon( $name, $size = 24 ) {
		if ( function_exists( 'bmn_icon' ) ) {
			return bmn_icon( $name, $size );
		}
		return '<span class="kb-icon-fallback" aria-hidden="true">◆</span>';
	}

	/**
	 * Registry: type => [label, category, icon, fields, render].
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$registry ) {
			return self::$registry;
		}
		$o = self::opts();
		$w = array();

		/* ---------------- Basic ---------------- */
		$w['heading'] = array(
			'label'    => 'تیتر',
			'cat'      => 'basic',
			'icon'     => 'grid',
			'fields'   => array(
				'eyebrow' => array( 'type' => 'text', 'label' => 'برچسب بالای تیتر', 'default' => '' ),
				'text'    => array( 'type' => 'text', 'label' => 'متن تیتر', 'default' => 'تیتر بخش' ),
				'level'   => array( 'type' => 'select', 'label' => 'سطح', 'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4' ), 'default' => 'h2' ),
				'size'    => array( 'type' => 'select', 'label' => 'اندازه', 'options' => $o['size'], 'default' => 'lg' ),
				'font'    => array( 'type' => 'select', 'label' => 'قلم', 'options' => array( 'display' => 'لاله‌زار (تیتر)', 'body' => 'وزیرمتن', 'quote' => 'امیری (نسخ)' ), 'default' => 'display' ),
				'tone'    => array( 'type' => 'select', 'label' => 'رنگ', 'options' => $o['tone'], 'default' => 'auto' ),
				'accent'  => array( 'type' => 'toggle', 'label' => 'نشانهٔ طاقچه کنار تیتر', 'default' => false ),
			),
			'render'   => array( __CLASS__, 'r_heading' ),
		);
		$w['text'] = array(
			'label'  => 'متن',
			'cat'    => 'basic',
			'icon'   => 'permit',
			'fields' => array(
				'html'   => array( 'type' => 'richtext', 'label' => 'متن', 'default' => '<p>متن خود را اینجا بنویسید. می‌توانید <strong>پررنگ</strong> و پیوند هم اضافه کنید.</p>' ),
				'size'   => array( 'type' => 'select', 'label' => 'اندازه', 'options' => array( 'sm' => 'کوچک', 'md' => 'معمولی', 'lg' => 'بزرگ (لید)' ), 'default' => 'md' ),
				'tone'   => array( 'type' => 'select', 'label' => 'رنگ', 'options' => $o['tone'], 'default' => 'auto' ),
				'justify' => array( 'type' => 'toggle', 'label' => 'تراز دوطرفه', 'default' => false ),
			),
			'render' => array( __CLASS__, 'r_text' ),
		);
		$w['buttons'] = array(
			'label'  => 'دکمه‌ها',
			'cat'    => 'basic',
			'icon'   => 'check',
			'fields' => array(
				'items' => array(
					'type'    => 'repeater',
					'label'   => 'دکمه‌ها',
					'max'     => 4,
					'fields'  => array(
						'label' => array( 'type' => 'text', 'label' => 'عنوان', 'default' => 'شروع' ),
						'url'   => array( 'type' => 'url', 'label' => 'پیوند', 'default' => '#' ),
						'style' => array( 'type' => 'select', 'label' => 'سبک', 'options' => $o['btn'], 'default' => 'action' ),
						'icon'  => array( 'type' => 'icon', 'label' => 'آیکن', 'options' => array( '' => '—' ) + $o['icons'], 'default' => '' ),
						'newtab' => array( 'type' => 'toggle', 'label' => 'برگهٔ جدید', 'default' => false ),
					),
					'default' => array( array( 'label' => 'پرداخت آنلاین', 'url' => '/dues/', 'style' => 'action', 'icon' => 'payment', 'newtab' => false ) ),
				),
				'size'  => array( 'type' => 'select', 'label' => 'اندازه', 'options' => array( 'sm' => 'کوچک', 'md' => 'معمولی', 'lg' => 'بزرگ' ), 'default' => 'md' ),
				'block' => array( 'type' => 'toggle', 'label' => 'تمام‌عرض', 'default' => false ),
			),
			'render' => array( __CLASS__, 'r_buttons' ),
		);
		$w['image'] = array(
			'label'  => 'تصویر',
			'cat'    => 'basic',
			'icon'   => 'camera',
			'fields' => array(
				'image'   => array( 'type' => 'image', 'label' => 'تصویر', 'default' => 0 ),
				'alt'     => array( 'type' => 'text', 'label' => 'متن جایگزین', 'default' => '' ),
				'frame'   => array( 'type' => 'select', 'label' => 'قاب', 'options' => array( 'none' => 'ساده', 'rounded' => 'گوشه گرد', 'taqche' => 'طاقچه (قوس)', 'circle' => 'دایره' ), 'default' => 'rounded' ),
				'ratio'   => array( 'type' => 'select', 'label' => 'نسبت', 'options' => array( 'auto' => 'اصلی', '16/9' => '۱۶:۹', '4/3' => '۴:۳', '1/1' => 'مربع', '3/4' => 'عمودی' ), 'default' => 'auto' ),
				'caption' => array( 'type' => 'text', 'label' => 'زیرنویس', 'default' => '' ),
				'url'     => array( 'type' => 'url', 'label' => 'پیوند', 'default' => '' ),
				'illustration' => array( 'type' => 'select', 'label' => 'تصویرسازی پیش‌فرض (بدون عکس)', 'options' => array( 'badgir-house' => 'بادگیر', 'mosque' => 'مسجد', 'fortress' => 'قلعه', 'watermill' => 'آسیاب', 'ab-anbar' => 'آب‌انبار', 'garden' => 'باغ', 'desert' => 'کویر', 'village' => 'روستا', 'night' => 'شب کویر' ), 'default' => 'badgir-house' ),
			),
			'render' => array( __CLASS__, 'r_image' ),
		);
		$w['icon-box'] = array(
			'label'  => 'جعبهٔ آیکن',
			'cat'    => 'basic',
			'icon'   => 'star8',
			'fields' => array(
				'icon'   => array( 'type' => 'icon', 'label' => 'آیکن', 'options' => $o['icons'], 'default' => 'badgir' ),
				'title'  => array( 'type' => 'text', 'label' => 'عنوان', 'default' => 'عنوان کوتاه' ),
				'text'   => array( 'type' => 'textarea', 'label' => 'توضیح', 'default' => 'یک یا دو جملهٔ کوتاه دربارهٔ این مورد.' ),
				'url'    => array( 'type' => 'url', 'label' => 'پیوند', 'default' => '' ),
				'layout' => array( 'type' => 'select', 'label' => 'چیدمان', 'options' => array( 'top' => 'آیکن بالا', 'side' => 'آیکن کنار' ), 'default' => 'top' ),
				'card'   => array( 'type' => 'toggle', 'label' => 'کارت', 'default' => true ),
			),
			'render' => array( __CLASS__, 'r_icon_box' ),
		);
		$w['list'] = array(
			'label'  => 'فهرست',
			'cat'    => 'basic',
			'icon'   => 'check',
			'fields' => array(
				'items' => array( 'type' => 'textarea', 'label' => 'موارد (هر خط یک مورد)', 'default' => "مورد نخست\nمورد دوم\nمورد سوم" ),
				'style' => array( 'type' => 'select', 'label' => 'نشانه', 'options' => array( 'diamond' => 'لوزی خشتی', 'check' => 'تیک فیروزه‌ای', 'number' => 'شماره' ), 'default' => 'diamond' ),
			),
			'render' => array( __CLASS__, 'r_list' ),
		);
		$w['quote'] = array(
			'label'  => 'نقل‌قول',
			'cat'    => 'basic',
			'icon'   => 'chat',
			'fields' => array(
				'text'   => array( 'type' => 'textarea', 'label' => 'متن', 'default' => 'خانه‌ای از خشت، دلی از آب' ),
				'cite'   => array( 'type' => 'text', 'label' => 'گوینده', 'default' => '' ),
				'style'  => array( 'type' => 'select', 'label' => 'سبک', 'options' => array( 'line' => 'خط کناری', 'center' => 'وسط با گره' ), 'default' => 'center' ),
			),
			'render' => array( __CLASS__, 'r_quote' ),
		);
		$w['divider'] = array(
			'label'  => 'جداکننده',
			'cat'    => 'basic',
			'icon'   => 'drop',
			'fields' => array(
				'style'  => array( 'type' => 'select', 'label' => 'نوع', 'options' => array( 'qanat' => 'رشته‌چاه قنات', 'arches' => 'قوس بادگیر', 'line' => 'خط ساده', 'star' => 'ستارهٔ هشت‌پر' ), 'default' => 'qanat' ),
			),
			'render' => array( __CLASS__, 'r_divider' ),
		);
		$w['spacer'] = array(
			'label'  => 'فاصله',
			'cat'    => 'basic',
			'icon'   => 'menu',
			'fields' => array(
				'size' => array( 'type' => 'select', 'label' => 'ارتفاع', 'options' => array( '16' => '۱۶', '32' => '۳۲', '48' => '۴۸', '64' => '۶۴', '96' => '۹۶' ), 'default' => '32' ),
			),
			'render' => array( __CLASS__, 'r_spacer' ),
		);
		$w['video'] = array(
			'label'  => 'ویدئو',
			'cat'    => 'basic',
			'icon'   => 'play',
			'fields' => array(
				'url'    => array( 'type' => 'url', 'label' => 'پیوند آپارات/یوتیوب', 'default' => '' ),
				'file'   => array( 'type' => 'image', 'label' => 'یا فایل ویدئو (کتابخانه)', 'mime' => 'video', 'default' => 0 ),
				'title'  => array( 'type' => 'text', 'label' => 'عنوان (دسترس‌پذیری)', 'default' => 'ویدئو' ),
			),
			'render' => array( __CLASS__, 'r_video' ),
		);
		$w['map'] = array(
			'label'  => 'نقشه',
			'cat'    => 'basic',
			'icon'   => 'pin',
			'fields' => array(
				'lat'   => array( 'type' => 'number', 'label' => 'عرض جغرافیایی', 'default' => 33.868, 'step' => 'any' ),
				'lng'   => array( 'type' => 'number', 'label' => 'طول جغرافیایی', 'default' => 57.429, 'step' => 'any' ),
				'zoom'  => array( 'type' => 'select', 'label' => 'بزرگنمایی', 'options' => array( 'city' => 'شهر', 'street' => 'خیابان', 'building' => 'ساختمان' ), 'default' => 'street' ),
				'title' => array( 'type' => 'text', 'label' => 'عنوان', 'default' => 'نقشه' ),
			),
			'render' => array( __CLASS__, 'r_map' ),
		);
		$w['alert'] = array(
			'label'  => 'اعلان / هشدار',
			'cat'    => 'basic',
			'icon'   => 'info',
			'fields' => array(
				'kind' => array( 'type' => 'select', 'label' => 'نوع', 'options' => array( 'info' => 'اطلاع', 'success' => 'موفقیت', 'warn' => 'مهلت/هشدار', 'error' => 'خطر' ), 'default' => 'warn' ),
				'text' => array( 'type' => 'textarea', 'label' => 'متن', 'default' => 'مهلت تخفیف عوارض نوسازی تا پایان آذر' ),
				'link' => array( 'type' => 'url', 'label' => 'پیوند', 'default' => '' ),
			),
			'render' => array( __CLASS__, 'r_alert' ),
		);

		/* ---------------- Brand / sections ---------------- */
		$w['hero'] = array(
			'label'  => 'سربرگ خط آسمان',
			'cat'    => 'brand',
			'icon'   => 'badgir',
			'fields' => array(
				'eyebrow' => array( 'type' => 'text', 'label' => 'برچسب', 'default' => 'چهارمین شهر خشت و گلی ایران' ),
				'title'   => array( 'type' => 'text', 'label' => 'تیتر', 'default' => 'بشرویه؛ شهرِ خشت، باد و قنات' ),
				'text'    => array( 'type' => 'textarea', 'label' => 'متن', 'default' => '' ),
				'search'  => array( 'type' => 'toggle', 'label' => 'جست‌وجوی هوشمند خدمات', 'default' => true ),
				'buttons' => array(
					'type'    => 'repeater',
					'label'   => 'دکمه‌ها',
					'max'     => 2,
					'fields'  => array(
						'label' => array( 'type' => 'text', 'label' => 'عنوان', 'default' => 'همهٔ خدمات' ),
						'url'   => array( 'type' => 'url', 'label' => 'پیوند', 'default' => '/services/' ),
						'style' => array( 'type' => 'select', 'label' => 'سبک', 'options' => $o['btn'], 'default' => 'action' ),
					),
					'default' => array(),
				),
				'variant' => array( 'type' => 'select', 'label' => 'زمینه', 'options' => array( 'dusk' => 'غروب کاهگلی', 'night' => 'شب کویر' ), 'default' => 'dusk' ),
				'height'  => array( 'type' => 'select', 'label' => 'ارتفاع', 'options' => array( 'md' => 'متوسط', 'lg' => 'بلند' ), 'default' => 'lg' ),
			),
			'render' => array( __CLASS__, 'r_hero' ),
		);
		$w['cards'] = array(
			'label'  => 'کارت‌های ویژگی',
			'cat'    => 'brand',
			'icon'   => 'grid',
			'fields' => array(
				'items'   => array(
					'type'    => 'repeater',
					'label'   => 'کارت‌ها',
					'max'     => 12,
					'fields'  => array(
						'icon'  => array( 'type' => 'icon', 'label' => 'آیکن', 'options' => $o['icons'], 'default' => 'star8' ),
						'title' => array( 'type' => 'text', 'label' => 'عنوان', 'default' => 'عنوان' ),
						'tag'   => array( 'type' => 'text', 'label' => 'برچسب', 'default' => '' ),
						'text'  => array( 'type' => 'textarea', 'label' => 'متن', 'default' => '' ),
						'url'   => array( 'type' => 'url', 'label' => 'پیوند', 'default' => '' ),
						'color' => array( 'type' => 'select', 'label' => 'رنگ نوار', 'options' => array( 'khesht' => 'خشت', 'brick' => 'آجری', 'qanat' => 'فیروزه', 'sun' => 'خورشید', 'night' => 'لاجورد' ), 'default' => 'khesht' ),
					),
					'default' => array(
						array( 'icon' => 'building', 'title' => 'خشت', 'tag' => 'ماده', 'text' => 'بافت گرم کاهگل، رنگ‌های خاکی؛ آرام، صادق و بومی.', 'url' => '', 'color' => 'khesht' ),
						array( 'icon' => 'badgir', 'title' => 'باد', 'tag' => 'فرم', 'text' => 'بادگیر سه‌گانهٔ خانهٔ مستوفی؛ قوس شکاف بادگیر.', 'url' => '', 'color' => 'brick' ),
						array( 'icon' => 'drop', 'title' => 'آب', 'tag' => 'کنش', 'text' => 'قنات و آسیاب‌های آبی؛ فیروزه‌ای رنگ «اقدام».', 'url' => '', 'color' => 'qanat' ),
						array( 'icon' => 'star8', 'title' => 'گره', 'tag' => 'نظم', 'text' => 'ستارهٔ هشت‌پر نشان و گره‌چینی کم‌رنگ.', 'url' => '', 'color' => 'sun' ),
					),
				),
				'columns' => array( 'type' => 'select', 'label' => 'ستون', 'options' => $o['cols'], 'default' => '4' ),
				'style'   => array( 'type' => 'select', 'label' => 'سبک', 'options' => array( 'stripe' => 'نوار رنگی بالا', 'taqche' => 'طاقچه‌ای', 'flat' => 'ساده' ), 'default' => 'stripe' ),
			),
			'render' => array( __CLASS__, 'r_cards' ),
		);
		$w['stats'] = array(
			'label'  => 'آمار و شمارنده',
			'cat'    => 'brand',
			'icon'   => 'chart',
			'fields' => array(
				'items' => array(
					'type'    => 'repeater',
					'label'   => 'آمارها',
					'max'     => 6,
					'fields'  => array(
						'number' => array( 'type' => 'number', 'label' => 'عدد', 'default' => 38 ),
						'suffix' => array( 'type' => 'text', 'label' => 'پسوند', 'default' => '' ),
						'label'  => array( 'type' => 'text', 'label' => 'عنوان', 'default' => 'خدمت الکترونیک' ),
						'icon'   => array( 'type' => 'icon', 'label' => 'آیکن', 'options' => array( '' => '—' ) + $o['icons'], 'default' => '' ),
					),
					'default' => array(
						array( 'number' => 38, 'suffix' => '', 'label' => 'خدمت الکترونیک', 'icon' => 'grid' ),
						array( 'number' => 72, 'suffix' => ' ساعت', 'label' => 'حداکثر پاسخ ۱۳۷', 'icon' => '137' ),
						array( 'number' => 50, 'suffix' => '٪', 'label' => 'پرداخت آنلاین (هدف)', 'icon' => 'payment' ),
						array( 'number' => 12, 'suffix' => '', 'label' => 'جاذبهٔ گردشگری', 'icon' => 'badgir' ),
					),
				),
				'style' => array( 'type' => 'select', 'label' => 'سبک', 'options' => array( 'cards' => 'کارتی', 'plain' => 'ساده' ), 'default' => 'cards' ),
			),
			'render' => array( __CLASS__, 'r_stats' ),
		);
		$w['steps'] = array(
			'label'  => 'مراحل / سفر شهروند',
			'cat'    => 'brand',
			'icon'   => 'route',
			'fields' => array(
				'items'  => array(
					'type'    => 'repeater',
					'label'   => 'مراحل',
					'max'     => 8,
					'fields'  => array(
						'icon'  => array( 'type' => 'icon', 'label' => 'آیکن', 'options' => $o['icons'], 'default' => 'check' ),
						'title' => array( 'type' => 'text', 'label' => 'عنوان', 'default' => 'مرحله' ),
						'text'  => array( 'type' => 'text', 'label' => 'توضیح', 'default' => '' ),
					),
					'default' => array(
						array( 'icon' => 'search', 'title' => 'جست‌وجو یا میانبر', 'text' => 'از صفحهٔ اصلی یا اپ' ),
						array( 'icon' => 'user', 'title' => 'ورود با کد پیامکی', 'text' => 'بدون رمز و ثبت‌نام طولانی' ),
						array( 'icon' => 'permit', 'title' => 'دیدن فیش و تخفیف', 'text' => 'با شناسهٔ ملک' ),
						array( 'icon' => 'payment', 'title' => 'پرداخت و رسید', 'text' => 'درگاه بانکی + رسید' ),
					),
				),
				'layout' => array( 'type' => 'select', 'label' => 'چیدمان', 'options' => array( 'row' => 'افقی', 'vertical' => 'عمودی (خط زمان)' ), 'default' => 'row' ),
			),
			'render' => array( __CLASS__, 'r_steps' ),
		);
		$w['faq'] = array(
			'label'  => 'پرسش‌های پرتکرار',
			'cat'    => 'brand',
			'icon'   => 'info',
			'fields' => array(
				'title'  => array( 'type' => 'text', 'label' => 'عنوان', 'default' => 'پرسش‌های پرتکرار' ),
				'items'  => array(
					'type'    => 'repeater',
					'label'   => 'پرسش‌ها',
					'max'     => 30,
					'fields'  => array(
						'q' => array( 'type' => 'text', 'label' => 'پرسش', 'default' => 'پرسش؟' ),
						'a' => array( 'type' => 'textarea', 'label' => 'پاسخ', 'default' => 'پاسخ کوتاه و روشن.' ),
					),
					'default' => array(
						array( 'q' => 'کد نوسازی را از کجا پیدا کنم؟', 'a' => 'روی فیش‌های قبلی عوارض یا با تماس با واحد درآمد.' ),
						array( 'q' => 'رسید پرداخت را چطور بگیرم؟', 'a' => 'پس از پرداخت، رسید قابل چاپ در پیشخوان شما ذخیره می‌شود.' ),
					),
				),
				'schema' => array( 'type' => 'toggle', 'label' => 'دادهٔ ساختاریافتهٔ FAQ برای گوگل', 'default' => true ),
			),
			'render' => array( __CLASS__, 'r_faq' ),
		);
		$w['tabs'] = array(
			'label'  => 'زبانه‌ها',
			'cat'    => 'brand',
			'icon'   => 'menu',
			'fields' => array(
				'items' => array(
					'type'    => 'repeater',
					'label'   => 'زبانه‌ها',
					'max'     => 8,
					'fields'  => array(
						'title' => array( 'type' => 'text', 'label' => 'عنوان', 'default' => 'زبانه' ),
						'html'  => array( 'type' => 'richtext', 'label' => 'محتوا', 'default' => '<p>محتوای زبانه</p>' ),
					),
					'default' => array(
						array( 'title' => 'تاریخچه', 'html' => '<p>بشرویه شهری است که با خشت و گل ساخته شده است.</p>' ),
						array( 'title' => 'جغرافیا', 'html' => '<p>در دل کویر و در استان خراسان جنوبی.</p>' ),
					),
				),
			),
			'render' => array( __CLASS__, 'r_tabs' ),
		);
		$w['timeline'] = array(
			'label'  => 'خط زمان تاریخی',
			'cat'    => 'brand',
			'icon'   => 'clock',
			'fields' => array(
				'items' => array(
					'type'    => 'repeater',
					'label'   => 'رویدادها',
					'max'     => 20,
					'fields'  => array(
						'year'  => array( 'type' => 'text', 'label' => 'سال/دوره', 'default' => 'صفوی' ),
						'title' => array( 'type' => 'text', 'label' => 'عنوان', 'default' => '' ),
						'text'  => array( 'type' => 'textarea', 'label' => 'شرح', 'default' => '' ),
					),
					'default' => array(
						array( 'year' => 'صفوی', 'title' => 'مسجد جامع', 'text' => 'مسجد تک‌ایوانی با آجر بی‌لعاب.' ),
						array( 'year' => 'قاجار', 'title' => 'خانهٔ مستوفی', 'text' => 'بنای اعیانی با بادگیر سه‌گانه و حوض.' ),
						array( 'year' => 'امروز', 'title' => 'چهارمین شهر خشت و گلی', 'text' => 'میراثی زنده که هنوز نفس می‌کشد.' ),
					),
				),
			),
			'render' => array( __CLASS__, 'r_timeline' ),
		);
		$w['gallery'] = array(
			'label'  => 'گالری',
			'cat'    => 'brand',
			'icon'   => 'camera',
			'fields' => array(
				'images'  => array( 'type' => 'gallery', 'label' => 'تصاویر', 'default' => array() ),
				'columns' => array( 'type' => 'select', 'label' => 'ستون', 'options' => $o['cols'], 'default' => '3' ),
				'frame'   => array( 'type' => 'select', 'label' => 'قاب', 'options' => array( 'rounded' => 'گوشه گرد', 'taqche' => 'طاقچه' ), 'default' => 'rounded' ),
			),
			'render' => array( __CLASS__, 'r_gallery' ),
		);
		$w['cta'] = array(
			'label'  => 'بنر دعوت به اقدام',
			'cat'    => 'brand',
			'icon'   => 'star8',
			'fields' => array(
				'title'   => array( 'type' => 'text', 'label' => 'تیتر', 'default' => 'شهرداری در جیب شما' ),
				'text'    => array( 'type' => 'textarea', 'label' => 'متن', 'default' => 'پرداخت، گزارش ۱۳۷ و پیگیری در کمتر از سه لمس.' ),
				'label'   => array( 'type' => 'text', 'label' => 'عنوان دکمه', 'default' => 'دریافت اپ «بشرویهٔ من»' ),
				'url'     => array( 'type' => 'url', 'label' => 'پیوند', 'default' => '/app/' ),
				'variant' => array( 'type' => 'select', 'label' => 'رنگ', 'options' => array( 'night' => 'لاجورد + گره', 'qanat' => 'فیروزه + گره', 'brick' => 'آجری', 'kahgel' => 'کاهگلی' ), 'default' => 'night' ),
				'logo'    => array( 'type' => 'toggle', 'label' => 'نمایش نشان', 'default' => true ),
			),
			'render' => array( __CLASS__, 'r_cta' ),
		);
		$w['person'] = array(
			'label'  => 'پیام / معرفی شخص',
			'cat'    => 'brand',
			'icon'   => 'user',
			'fields' => array(
				'image' => array( 'type' => 'image', 'label' => 'تصویر', 'default' => 0 ),
				'name'  => array( 'type' => 'text', 'label' => 'نام', 'default' => 'شهردار بشرویه' ),
				'role'  => array( 'type' => 'text', 'label' => 'سمت', 'default' => 'پیام شهردار' ),
				'text'  => array( 'type' => 'textarea', 'label' => 'متن', 'default' => 'بشرویه را با هم آبادتر می‌سازیم…' ),
				'url'   => array( 'type' => 'url', 'label' => 'پیوند «ادامه»', 'default' => '' ),
			),
			'render' => array( __CLASS__, 'r_person' ),
		);
		$w['countdown'] = array(
			'label'  => 'شمارش معکوس',
			'cat'    => 'brand',
			'icon'   => 'clock',
			'fields' => array(
				'title'    => array( 'type' => 'text', 'label' => 'عنوان', 'default' => 'مهلت تخفیف عوارض' ),
				'deadline' => array( 'type' => 'jdate', 'label' => 'تاریخ و ساعت پایان (شمسی)', 'default' => '' ),
				'expired'  => array( 'type' => 'text', 'label' => 'متن پس از پایان', 'default' => 'مهلت به پایان رسید' ),
			),
			'render' => array( __CLASS__, 'r_countdown' ),
		);
		$w['app'] = array(
			'label'  => 'دانلود اپ',
			'cat'    => 'brand',
			'icon'   => 'download',
			'fields' => array(
				'title' => array( 'type' => 'text', 'label' => 'عنوان', 'default' => 'اپ «بشرویهٔ من»' ),
				'text'  => array( 'type' => 'text', 'label' => 'متن', 'default' => 'دریافت از بازار، مایکت و نسخهٔ وب' ),
			),
			'render' => array( __CLASS__, 'r_app' ),
		);

		/* ---------------- City (dynamic, from Boshrouyeh Core) ---------------- */
		$dyn = array(
			'quick-services'  => array( 'میانبر خدمات', 'grid', 'boshrouyeh/quick-services', array( 'count' => array( 'type' => 'number', 'label' => 'تعداد', 'default' => 7, 'min' => 3, 'max' => 11 ) ) ),
			'service-dir'     => array( 'فهرست خدمات با فیلتر', 'filter', 'boshrouyeh/service-directory', array() ),
			'news'            => array( 'تازه‌ترین اخبار', 'permit', 'kb:news', array( 'count' => array( 'type' => 'number', 'label' => 'تعداد', 'default' => 3, 'min' => 1, 'max' => 12 ), 'columns' => array( 'type' => 'select', 'label' => 'ستون', 'options' => $o['cols'], 'default' => '3' ) ) ),
			'notices'         => array( 'اطلاعیه‌ها', 'bell', 'boshrouyeh/notices', array( 'count' => array( 'type' => 'number', 'label' => 'تعداد', 'default' => 5, 'min' => 1, 'max' => 20 ) ) ),
			'tenders'         => array( 'مناقصه و مزایده', 'tender', 'boshrouyeh/tender-list', array( 'count' => array( 'type' => 'number', 'label' => 'تعداد', 'default' => 6, 'min' => 1, 'max' => 30 ) ) ),
			'places'          => array( 'جاذبه‌های گردشگری', 'badgir', 'boshrouyeh/place-grid', array( 'count' => array( 'type' => 'number', 'label' => 'تعداد', 'default' => 8, 'min' => 1, 'max' => 24 ) ) ),
			'events'          => array( 'تقویم رویدادها', 'calendar', 'boshrouyeh/event-list', array( 'count' => array( 'type' => 'number', 'label' => 'تعداد', 'default' => 5, 'min' => 1, 'max' => 20 ) ) ),
			'transparency'    => array( 'اسناد شفافیت', 'chart', 'boshrouyeh/transparency-list', array() ),
			'waste'           => array( 'زمان‌بندی پسماند', 'waste', 'boshrouyeh/waste-schedule', array() ),
			'deceased'        => array( 'جست‌وجوی متوفیان', 'search', 'boshrouyeh/deceased-search', array() ),
			'contact'         => array( 'اطلاعات تماس', 'phone', 'boshrouyeh/contact-info', array() ),
		);
		foreach ( $dyn as $type => $d ) {
			$w[ $type ] = array(
				'label'  => $d[0],
				'cat'    => 'city',
				'icon'   => $d[1],
				'fields' => $d[3],
				'block'  => $d[2],
				'render' => array( __CLASS__, 'r_dynamic' ),
			);
		}

		/* ---------------- E-services (from Boshrouyeh E-Services) ---------------- */
		$es = array(
			'es-login'       => array( 'ورود شهروند', 'user', 'boshrouyeh/citizen-login', array() ),
			'es-dashboard'   => array( 'پیشخوان شهروند', 'home', 'boshrouyeh/citizen-dashboard', array() ),
			'es-dues'        => array( 'استعلام و پرداخت عوارض', 'payment', 'boshrouyeh/dues', array( 'type' => array( 'type' => 'select', 'label' => 'نوع عوارض', 'options' => array( 'renovation' => 'نوسازی و سطح شهر', 'vehicle' => 'خودرو', 'business' => 'کسب و پیشه' ), 'default' => 'renovation' ) ) ),
			'es-report'      => array( 'فرم گزارش ۱۳۷', '137', 'boshrouyeh/report-137', array() ),
			'es-track'       => array( 'پیگیری درخواست', 'search', 'boshrouyeh/track', array() ),
			'es-appointment' => array( 'نوبت ملاقات', 'calendar', 'boshrouyeh/appointment', array() ),
			'es-feedback'    => array( 'انتقاد و پیشنهاد', 'chat', 'boshrouyeh/feedback', array() ),
		);
		foreach ( $es as $type => $d ) {
			$w[ $type ] = array(
				'label'  => $d[0],
				'cat'    => 'eservice',
				'icon'   => $d[1],
				'fields' => $d[3],
				'block'  => $d[2],
				'render' => array( __CLASS__, 'r_dynamic' ),
			);
		}

		self::$registry = apply_filters( 'kb_widgets', $w );
		return self::$registry;
	}

	/**
	 * Categories.
	 *
	 * @return array
	 */
	public static function categories() {
		return array(
			'basic'    => 'پایه',
			'brand'    => 'بخش‌های هویتی',
			'city'     => 'محتوای شهری (پویا)',
			'eservice' => 'خدمات الکترونیک',
		);
	}

	/**
	 * Common (advanced) fields available on every widget.
	 *
	 * @return array
	 */
	public static function common_fields() {
		return array(
			'_align' => array( 'type' => 'select', 'label' => 'تراز', 'options' => self::opts()['align'], 'default' => 'start', 'group' => 'adv' ),
			'_space' => array( 'type' => 'select', 'label' => 'فاصلهٔ پایین', 'options' => array( 'none' => 'هیچ', 'sm' => 'کم', 'md' => 'معمولی', 'lg' => 'زیاد' ), 'default' => 'md', 'group' => 'adv' ),
			'_anim'  => array( 'type' => 'select', 'label' => 'پویانمایی ورود', 'options' => array( 'none' => 'بدون', 'fade' => 'محوشدن', 'up' => 'بالا آمدن', 'zoom' => 'بزرگ‌نمایی' ), 'default' => 'up', 'group' => 'adv' ),
			'_hide'  => array( 'type' => 'select', 'label' => 'نمایش', 'options' => array( '' => 'همه دستگاه‌ها', 'mobile' => 'پنهان در موبایل', 'desktop' => 'پنهان در دسکتاپ' ), 'default' => '', 'group' => 'adv' ),
			'_id'    => array( 'type' => 'text', 'label' => 'شناسهٔ لنگر (anchor)', 'default' => '', 'group' => 'adv' ),
			'_class' => array( 'type' => 'text', 'label' => 'کلاس CSS', 'default' => '', 'group' => 'adv' ),
		);
	}

	/**
	 * Section fields.
	 *
	 * @return array
	 */
	public static function section_fields() {
		return array(
			'bg'      => array( 'type' => 'select', 'label' => 'زمینه', 'options' => array( 'none' => 'بدون (زمینهٔ صفحه)', 'white' => 'سفید', 'milk' => 'شیری خاک', 'kahgel' => 'کاهگل', 'khesht' => 'خشت‌چین', 'arches' => 'قوس بادگیر', 'girih' => 'گره روشن', 'night' => 'لاجورد + گره', 'qanat' => 'فیروزه + گره', 'brick' => 'آجری تیره', 'image' => 'تصویر' ), 'default' => 'none' ),
			'image'   => array( 'type' => 'image', 'label' => 'تصویر زمینه', 'default' => 0 ),
			'overlay' => array( 'type' => 'toggle', 'label' => 'لایهٔ تیره روی تصویر (۳۵٪ لاجوردی)', 'default' => true ),
			'width'   => array( 'type' => 'select', 'label' => 'عرض محتوا', 'options' => array( 'content' => 'متن (۷۶۰)', 'wide' => 'عریض (۱۲۰۰)', 'full' => 'تمام صفحه' ), 'default' => 'wide' ),
			'pad'     => array( 'type' => 'select', 'label' => 'فاصلهٔ داخلی', 'options' => array( 'none' => 'هیچ', 'sm' => 'کم', 'md' => 'معمولی', 'lg' => 'زیاد' ), 'default' => 'md' ),
			'gap'     => array( 'type' => 'select', 'label' => 'فاصلهٔ ستون‌ها', 'options' => array( 'sm' => 'کم', 'md' => 'معمولی', 'lg' => 'زیاد' ), 'default' => 'md' ),
			'valign'  => array( 'type' => 'select', 'label' => 'تراز عمودی ستون‌ها', 'options' => array( 'start' => 'بالا', 'center' => 'وسط', 'end' => 'پایین', 'stretch' => 'کشیده' ), 'default' => 'start' ),
			'round'   => array( 'type' => 'toggle', 'label' => 'گوشه‌های گرد (کارت بزرگ)', 'default' => false ),
			'top'     => array( 'type' => 'select', 'label' => 'جداکنندهٔ بالا', 'options' => array( 'none' => 'بدون', 'qanat' => 'رشته‌چاه قنات', 'skyline' => 'خط آسمان', 'arches' => 'قوس‌ها' ), 'default' => 'none' ),
			'bottom'  => array( 'type' => 'select', 'label' => 'جداکنندهٔ پایین', 'options' => array( 'none' => 'بدون', 'qanat' => 'رشته‌چاه قنات', 'skyline' => 'خط آسمان', 'arches' => 'قوس‌ها' ), 'default' => 'none' ),
			'anchor'  => array( 'type' => 'text', 'label' => 'شناسهٔ لنگر (anchor)', 'default' => '' ),
			'hide'    => array( 'type' => 'select', 'label' => 'نمایش', 'options' => array( '' => 'همه دستگاه‌ها', 'mobile' => 'پنهان در موبایل', 'desktop' => 'پنهان در دسکتاپ' ), 'default' => '' ),
		);
	}

	/* ===================== Renderers ===================== */

	/**
	 * Tone class.
	 *
	 * @param string $tone Tone.
	 * @return string
	 */
	private static function tone( $tone ) {
		return 'auto' === $tone ? '' : ' kb-tone-' . sanitize_html_class( $tone );
	}

	/**
	 * Allowed HTML for richtext.
	 *
	 * @return array
	 */
	public static function kses() {
		$a = array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
			'title'  => true,
		);
		return array(
			'p'          => array(),
			'br'         => array(),
			'strong'     => array(),
			'b'          => array(),
			'em'         => array(),
			'i'          => array(),
			'u'          => array(),
			'mark'       => array(),
			'small'      => array(),
			'sup'        => array(),
			'sub'        => array(),
			'a'          => $a,
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'blockquote' => array(),
			'span'       => array( 'lang' => true, 'dir' => true ),
			'abbr'       => array( 'title' => true ),
		);
	}

	/**
	 * Resolve site-relative URLs.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function url( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return '';
		}
		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			$url = home_url( $url );
		}
		return esc_url( $url, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/**
	 * Image tag for an attachment or brand illustration fallback.
	 *
	 * @param int    $id    Attachment.
	 * @param string $size  Size.
	 * @param string $alt   Alt.
	 * @param string $fallback Illustration key.
	 * @return string
	 */
	private static function img( $id, $size = 'large', $alt = '', $fallback = '' ) {
		if ( $id && wp_attachment_is_image( $id ) ) {
			return wp_get_attachment_image( $id, $size, false, array( 'alt' => $alt, 'loading' => 'lazy', 'decoding' => 'async' ) );
		}
		if ( $fallback && defined( 'BMN_CORE_URL' ) ) {
			return '<img src="' . esc_url( BMN_CORE_URL . 'assets/illustrations/' . sanitize_key( $fallback ) . '.svg' ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" width="400" height="300">';
		}
		return '<span class="kb-img-ph" aria-hidden="true"></span>';
	}

	/**
	 * Heading.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_heading( $s ) {
		$tag = in_array( $s['level'], array( 'h1', 'h2', 'h3', 'h4' ), true ) ? $s['level'] : 'h2';
		$h   = '';
		if ( '' !== $s['eyebrow'] ) {
			$h .= '<p class="kb-eyebrow">' . esc_html( $s['eyebrow'] ) . '</p>';
		}
		return $h . '<' . $tag . ' class="kb-heading kb-size-' . esc_attr( $s['size'] ) . ' kb-font-' . esc_attr( $s['font'] ) . self::tone( $s['tone'] ) . ( $s['accent'] ? ' kb-accent' : '' ) . '">' . esc_html( $s['text'] ) . '</' . $tag . '>';
	}

	/**
	 * Text.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_text( $s ) {
		return '<div class="kb-text kb-text-' . esc_attr( $s['size'] ) . self::tone( $s['tone'] ) . ( $s['justify'] ? ' kb-justify' : '' ) . '">' . wp_kses( $s['html'], self::kses() ) . '</div>';
	}

	/**
	 * Buttons.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_buttons( $s ) {
		$h = '<div class="kb-buttons' . ( $s['block'] ? ' kb-buttons--block' : '' ) . '">';
		foreach ( $s['items'] as $b ) {
			$h .= '<a class="kh-btn kh-btn--' . esc_attr( $b['style'] ) . ' kb-btn-' . esc_attr( $s['size'] ) . '" href="' . self::url( $b['url'] ) . '"' . ( $b['newtab'] ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . ( $b['icon'] ? self::icon( $b['icon'], 18 ) : '' ) . '<span>' . esc_html( $b['label'] ) . '</span></a>';
		}
		return $h . '</div>';
	}

	/**
	 * Image.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_image( $s ) {
		$ratio = 'auto' !== $s['ratio'] ? ' style="aspect-ratio:' . esc_attr( $s['ratio'] ) . '"' : '';
		$img   = self::img( (int) $s['image'], 'large', $s['alt'], $s['illustration'] );
		if ( $s['url'] ) {
			$img = '<a href="' . self::url( $s['url'] ) . '">' . $img . '</a>';
		}
		return '<figure class="kb-image kb-frame-' . esc_attr( $s['frame'] ) . ( 'auto' !== $s['ratio'] ? ' kb-ratio' : '' ) . '"><div class="kb-image__box"' . $ratio . '>' . $img . '</div>' . ( $s['caption'] ? '<figcaption>' . esc_html( $s['caption'] ) . '</figcaption>' : '' ) . '</figure>';
	}

	/**
	 * Icon box.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_icon_box( $s ) {
		$tag  = $s['url'] ? 'a' : 'div';
		$href = $s['url'] ? ' href="' . self::url( $s['url'] ) . '"' : '';
		return '<' . $tag . $href . ' class="kb-iconbox kb-iconbox--' . esc_attr( $s['layout'] ) . ( $s['card'] ? ' kb-card' : '' ) . '"><span class="kh-taqche">' . self::icon( $s['icon'] ) . '</span><span class="kb-iconbox__body"><strong>' . esc_html( $s['title'] ) . '</strong>' . ( $s['text'] ? '<span>' . esc_html( $s['text'] ) . '</span>' : '' ) . '</span></' . $tag . '>';
	}

	/**
	 * List.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_list( $s ) {
		$items = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $s['items'] ) ) );
		$tag   = 'number' === $s['style'] ? 'ol' : 'ul';
		$h     = '<' . $tag . ' class="kb-list kb-list--' . esc_attr( $s['style'] ) . '">';
		foreach ( $items as $i ) {
			$h .= '<li>' . esc_html( $i ) . '</li>';
		}
		return $h . '</' . $tag . '>';
	}

	/**
	 * Quote.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_quote( $s ) {
		return '<blockquote class="kb-quote kb-quote--' . esc_attr( $s['style'] ) . '"><p>' . esc_html( $s['text'] ) . '</p>' . ( $s['cite'] ? '<cite>' . esc_html( $s['cite'] ) . '</cite>' : '' ) . '</blockquote>';
	}

	/**
	 * Divider.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_divider( $s ) {
		if ( 'star' === $s['style'] ) {
			return '<div class="kb-divider kb-divider--star" role="separator">' . self::icon( 'star8', 28 ) . '</div>';
		}
		return '<div class="kb-divider kb-divider--' . esc_attr( $s['style'] ) . '" role="separator"></div>';
	}

	/**
	 * Spacer.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_spacer( $s ) {
		return '<div class="kb-spacer" style="height:' . absint( $s['size'] ) . 'px" aria-hidden="true"></div>';
	}

	/**
	 * Video (Aparat / YouTube / media library).
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_video( $s ) {
		$title = esc_attr( $s['title'] );
		if ( $s['file'] && str_starts_with( (string) get_post_mime_type( (int) $s['file'] ), 'video/' ) ) {
			return '<div class="kb-video"><video controls preload="metadata" src="' . esc_url( (string) wp_get_attachment_url( (int) $s['file'] ) ) . '" title="' . $title . '"></video></div>';
		}
		$url = (string) $s['url'];
		$src = '';
		if ( preg_match( '#aparat\.com/v/([A-Za-z0-9]+)#', $url, $m ) ) {
			$src = 'https://www.aparat.com/video/video/embed/videohash/' . $m[1] . '/vt/frame';
		} elseif ( preg_match( '#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,15})#', $url, $m ) ) {
			$src = 'https://www.youtube-nocookie.com/embed/' . $m[1];
		}
		if ( ! $src ) {
			return '<div class="kb-video kb-video--empty"><span>' . self::icon( 'play', 40 ) . '</span><small>پیوند آپارات یا یوتیوب را وارد کنید</small></div>';
		}
		return '<div class="kb-video"><iframe src="' . esc_url( $src ) . '" title="' . $title . '" loading="lazy" allowfullscreen allow="encrypted-media; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
	}

	/**
	 * Map (OpenStreetMap embed).
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_map( $s ) {
		$lat = max( -90, min( 90, (float) $s['lat'] ) );
		$lng = max( -180, min( 180, (float) $s['lng'] ) );
		$d   = array(
			'city'     => 0.03,
			'street'   => 0.008,
			'building' => 0.003,
		)[ $s['zoom'] ] ?? 0.008;
		$bbox = sprintf( '%F%%2C%F%%2C%F%%2C%F', $lng - $d, $lat - $d * 0.7, $lng + $d, $lat + $d * 0.7 );
		$src  = 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . rawurlencode( $lat . ',' . $lng );
		return '<div class="kb-map"><iframe title="' . esc_attr( $s['title'] ) . '" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin" src="' . esc_url( $src ) . '"></iframe><a class="kb-map__link" href="' . esc_url( sprintf( 'https://www.openstreetmap.org/directions?to=%F%%2C%F', $lat, $lng ) ) . '" target="_blank" rel="noopener noreferrer">' . self::icon( 'route', 16 ) . 'مسیریابی</a></div>';
	}

	/**
	 * Alert.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_alert( $s ) {
		$icons = array( 'info' => 'info', 'success' => 'check', 'warn' => 'clock', 'error' => 'shield' );
		$text  = esc_html( $s['text'] );
		if ( $s['link'] ) {
			$text .= ' <a href="' . self::url( $s['link'] ) . '">جزئیات ←</a>';
		}
		return '<p class="kh-alert kh-alert--' . esc_attr( $s['kind'] ) . '" role="note">' . self::icon( $icons[ $s['kind'] ] ?? 'info', 20 ) . '<span>' . $text . '</span></p>';
	}

	/**
	 * Hero.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_hero( $s ) {
		$uid = wp_unique_id( 'kb-hero-' );
		$h   = '<section class="kh-hero kb-hero kb-hero--' . esc_attr( $s['variant'] ) . ' kb-hero--' . esc_attr( $s['height'] ) . '" aria-labelledby="' . esc_attr( $uid ) . '"><div class="kh-hero__inner">';
		if ( $s['eyebrow'] ) {
			$h .= '<p class="kh-eyebrow">' . esc_html( $s['eyebrow'] ) . '</p>';
		}
		$h .= '<h1 id="' . esc_attr( $uid ) . '" class="kh-hero__title">' . esc_html( $s['title'] ) . '</h1>';
		if ( $s['text'] ) {
			$h .= '<p class="kb-hero__text">' . esc_html( $s['text'] ) . '</p>';
		}
		if ( $s['search'] ) {
			$h .= '<form role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '" class="kh-search-form kh-search-form--hero"><label class="screen-reader-text" for="' . esc_attr( $uid ) . '-s">جست‌وجوی خدمات</label>' . self::icon( 'search' ) . '<input id="' . esc_attr( $uid ) . '-s" type="search" name="s" autocomplete="off" placeholder="چه خدمتی نیاز دارید؟ مثلاً «عوارض نوسازی»" data-kh-suggest><button type="submit" class="kh-btn kh-btn--action">جست‌وجو</button><ul class="kh-suggest" role="listbox" hidden></ul></form>';
		}
		if ( $s['buttons'] ) {
			$h .= '<div class="kb-buttons kb-hero__buttons">';
			foreach ( $s['buttons'] as $b ) {
				$h .= '<a class="kh-btn kh-btn--' . esc_attr( $b['style'] ) . '" href="' . self::url( $b['url'] ) . '">' . esc_html( $b['label'] ) . '</a>';
			}
			$h .= '</div>';
		}
		return $h . '</div><div class="kh-hero__sun" aria-hidden="true"></div><div class="kh-hero__skyline" aria-hidden="true"></div></section>';
	}

	/**
	 * Feature cards.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_cards( $s ) {
		$h = '<div class="kb-cards kb-cols-' . esc_attr( $s['columns'] ) . ' kb-cards--' . esc_attr( $s['style'] ) . '">';
		foreach ( $s['items'] as $c ) {
			$tag  = $c['url'] ? 'a' : 'article';
			$href = $c['url'] ? ' href="' . self::url( $c['url'] ) . '"' : '';
			$h   .= '<' . $tag . $href . ' class="kb-card kb-feature kb-c-' . esc_attr( $c['color'] ) . '"><span class="kh-taqche">' . self::icon( $c['icon'] ) . '</span><h3>' . esc_html( $c['title'] ) . ( $c['tag'] ? ' <span class="kh-chip">' . esc_html( $c['tag'] ) . '</span>' : '' ) . '</h3>' . ( $c['text'] ? '<p>' . esc_html( $c['text'] ) . '</p>' : '' ) . '</' . $tag . '>';
		}
		return $h . '</div>';
	}

	/**
	 * Stats.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_stats( $s ) {
		$h = '<ul class="kb-stats kb-stats--' . esc_attr( $s['style'] ) . '" role="list">';
		foreach ( $s['items'] as $i ) {
			$n  = (int) $i['number'];
			$h .= '<li>' . ( $i['icon'] ? '<span class="kh-taqche">' . self::icon( $i['icon'] ) . '</span>' : '' ) . '<b><span data-kb-count="' . esc_attr( (string) $n ) . '">' . esc_html( function_exists( 'bmn_fa_digits' ) ? bmn_fa_digits( number_format( $n, 0, '.', '٬' ) ) : (string) $n ) . '</span>' . esc_html( $i['suffix'] ) . '</b><span>' . esc_html( $i['label'] ) . '</span></li>';
		}
		return $h . '</ul>';
	}

	/**
	 * Steps.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_steps( $s ) {
		$h = '<ol class="kb-steps kb-steps--' . esc_attr( $s['layout'] ) . '">';
		foreach ( $s['items'] as $n => $i ) {
			$num = function_exists( 'bmn_fa_digits' ) ? bmn_fa_digits( $n + 1 ) : (string) ( $n + 1 );
			$h  .= '<li><span class="kh-taqche">' . self::icon( $i['icon'] ) . '</span><span><strong>' . esc_html( $num . '. ' . $i['title'] ) . '</strong>' . ( $i['text'] ? '<small>' . esc_html( $i['text'] ) . '</small>' : '' ) . '</span></li>';
		}
		return $h . '</ol>';
	}

	/**
	 * FAQ.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_faq( $s ) {
		$h      = '<section class="kb-faq bmn-faq">' . ( $s['title'] ? '<h2>' . esc_html( $s['title'] ) . '</h2>' : '' );
		$schema = array();
		foreach ( $s['items'] as $i ) {
			$h       .= '<details><summary>' . esc_html( $i['q'] ) . '</summary><p>' . esc_html( $i['a'] ) . '</p></details>';
			$schema[] = array( '@type' => 'Question', 'name' => $i['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $i['a'] ) );
		}
		$h .= '</section>';
		if ( $s['schema'] && $schema && ! KB_Render::$editing ) {
			$h .= '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $schema ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>';
		}
		return $h;
	}

	/**
	 * Tabs.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_tabs( $s ) {
		$uid  = wp_unique_id( 'kb-tabs-' );
		$tabs = '<div class="kb-tabs__list" role="tablist">';
		$pans = '';
		foreach ( $s['items'] as $n => $t ) {
			$id    = $uid . '-' . $n;
			$tabs .= '<button type="button" role="tab" id="' . esc_attr( $id ) . '-t" aria-controls="' . esc_attr( $id ) . '" aria-selected="' . ( 0 === $n ? 'true' : 'false' ) . '" tabindex="' . ( 0 === $n ? '0' : '-1' ) . '">' . esc_html( $t['title'] ) . '</button>';
			$pans .= '<div class="kb-tabs__panel" role="tabpanel" id="' . esc_attr( $id ) . '" aria-labelledby="' . esc_attr( $id ) . '-t"' . ( 0 === $n ? '' : ' hidden' ) . '>' . wp_kses( $t['html'], self::kses() ) . '</div>';
		}
		return '<div class="kb-tabs" data-kb-tabs>' . $tabs . '</div>' . $pans . '</div>';
	}

	/**
	 * Timeline.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_timeline( $s ) {
		$h = '<ol class="kb-timeline">';
		foreach ( $s['items'] as $i ) {
			$h .= '<li><span class="kb-timeline__year">' . esc_html( $i['year'] ) . '</span><div class="kb-card"><strong>' . esc_html( $i['title'] ) . '</strong>' . ( $i['text'] ? '<p>' . esc_html( $i['text'] ) . '</p>' : '' ) . '</div></li>';
		}
		return $h . '</ol>';
	}

	/**
	 * Gallery with lightbox.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_gallery( $s ) {
		$ids = array_filter( array_map( 'absint', (array) $s['images'] ) );
		if ( ! $ids ) {
			$ph = '';
			foreach ( array( 'badgir-house', 'mosque', 'watermill', 'garden', 'desert', 'village' ) as $k ) {
				$ph .= '<li>' . self::img( 0, 'medium', '', $k ) . '</li>';
			}
			return '<ul class="kb-gallery kb-cols-' . esc_attr( $s['columns'] ) . ' kb-frame-' . esc_attr( $s['frame'] ) . '" role="list">' . $ph . '</ul>';
		}
		$h = '<ul class="kb-gallery kb-cols-' . esc_attr( $s['columns'] ) . ' kb-frame-' . esc_attr( $s['frame'] ) . '" role="list" data-kb-lightbox>';
		foreach ( $ids as $id ) {
			if ( ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			$full = wp_get_attachment_image_url( $id, 'full' );
			$alt  = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			$h   .= '<li><a href="' . esc_url( (string) $full ) . '" data-kb-zoom>' . wp_get_attachment_image( $id, 'medium_large', false, array( 'loading' => 'lazy', 'alt' => $alt ) ) . '</a></li>';
		}
		return $h . '</ul>';
	}

	/**
	 * CTA banner.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_cta( $s ) {
		$logo = ( $s['logo'] && function_exists( 'khesht_logo' ) ) ? '<span class="kb-cta__logo">' . khesht_logo( 72 ) . '</span>' : '';
		return '<div class="kb-cta kb-cta--' . esc_attr( $s['variant'] ) . '">' . $logo . '<div class="kb-cta__body"><h2>' . esc_html( $s['title'] ) . '</h2>' . ( $s['text'] ? '<p>' . esc_html( $s['text'] ) . '</p>' : '' ) . '</div>' . ( $s['label'] ? '<a class="kh-btn kh-btn--sun" href="' . self::url( $s['url'] ) . '">' . esc_html( $s['label'] ) . '</a>' : '' ) . '</div>';
	}

	/**
	 * Person / message.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_person( $s ) {
		$img = $s['image'] ? self::img( (int) $s['image'], 'medium', $s['name'] ) : '<span class="kb-person__ph">' . self::icon( 'user', 40 ) . '</span>';
		return '<figure class="kb-person kb-card"><div class="kb-person__img">' . $img . '</div><figcaption><span class="kb-eyebrow">' . esc_html( $s['role'] ) . '</span><strong>' . esc_html( $s['name'] ) . '</strong><blockquote>' . esc_html( $s['text'] ) . '</blockquote>' . ( $s['url'] ? '<a class="bmn-link" href="' . self::url( $s['url'] ) . '">ادامهٔ پیام ←</a>' : '' ) . '</figcaption></figure>';
	}

	/**
	 * Countdown.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_countdown( $s ) {
		$ts = (int) $s['deadline'];
		if ( ! $ts ) {
			return '<div class="kb-countdown"><strong>' . esc_html( $s['title'] ) . '</strong><span class="kb-countdown__empty">تاریخ پایان را تنظیم کنید</span></div>';
		}
		$label = function_exists( 'bmn_jdate' ) ? bmn_jdate( 'l j F Y - H:i', $ts ) : gmdate( 'Y-m-d H:i', $ts );
		return '<div class="kb-countdown" data-kb-deadline="' . esc_attr( gmdate( 'c', $ts ) ) . '" data-kb-expired="' . esc_attr( $s['expired'] ) . '"><strong>' . esc_html( $s['title'] ) . '</strong><div class="kb-countdown__units" aria-live="off"><span><b data-u="d">۰</b><small>روز</small></span><span><b data-u="h">۰</b><small>ساعت</small></span><span><b data-u="m">۰</b><small>دقیقه</small></span><span><b data-u="s">۰</b><small>ثانیه</small></span></div><small class="kb-countdown__date">تا ' . esc_html( $label ) . '</small></div>';
	}

	/**
	 * App download.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function r_app( $s ) {
		$links = array(
			'کافه‌بازار' => function_exists( 'bmn_option' ) ? (string) bmn_option( 'app_bazaar' ) : '',
			'مایکت'     => function_exists( 'bmn_option' ) ? (string) bmn_option( 'app_myket' ) : '',
			'دانلود مستقیم' => function_exists( 'bmn_option' ) ? (string) bmn_option( 'app_apk' ) : '',
		);
		$logo = function_exists( 'khesht_logo' ) ? khesht_logo( 54 ) : '';
		$h    = '<div class="kb-app"><span class="kb-app__icon">' . $logo . '</span><div><strong>' . esc_html( $s['title'] ) . '</strong><span>' . esc_html( $s['text'] ) . '</span></div><div class="kb-app__badges">';
		foreach ( $links as $label => $url ) {
			$h .= '<a class="kb-badge" href="' . esc_url( $url ? $url : '#' ) . '"' . ( $url ? ' rel="noopener"' : ' aria-disabled="true"' ) . '>' . self::icon( 'download', 16 ) . esc_html( $label ) . '</a>';
		}
		return $h . '<a class="kb-badge kb-badge--pwa" href="#" data-kh-pwa-install>' . self::icon( 'globe', 16 ) . 'نسخهٔ وب (PWA)</a></div></div>';
	}

	/**
	 * News grid (core posts).
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private static function news( $s ) {
		$posts = get_posts( array( 'post_type' => 'post', 'posts_per_page' => max( 1, min( 12, (int) $s['count'] ) ), 'no_found_rows' => true ) );
		if ( ! $posts ) {
			return '<p class="kh-empty">هنوز خبری منتشر نشده است.</p>';
		}
		$h = '<ul class="kb-news kb-cols-' . esc_attr( $s['columns'] ) . '" role="list">';
		foreach ( $posts as $p ) {
			$thumb = function_exists( 'bmn_thumb' ) ? bmn_thumb( $p->ID ) : get_the_post_thumbnail( $p, 'medium_large' );
			$date  = function_exists( 'bmn_jdate' ) ? bmn_jdate( 'j F Y', (int) get_post_timestamp( $p ) ) : get_the_date( '', $p );
			$h    .= '<li class="kh-card kh-card--post"><a href="' . esc_url( (string) get_permalink( $p ) ) . '"><span class="kb-news__img">' . $thumb . '</span><span class="kh-card__body"><strong class="kh-card__title">' . esc_html( get_the_title( $p ) ) . '</strong><time class="kh-card__date" datetime="' . esc_attr( get_post_time( 'c', true, $p ) ) . '">' . esc_html( $date ) . '</time></span></a></li>';
		}
		return $h . '</ul>';
	}

	/**
	 * Dynamic (block-backed) widgets.
	 *
	 * @param array  $s    Settings.
	 * @param string $type Widget type.
	 * @return string
	 */
	public static function r_dynamic( $s, $type ) {
		$def = self::all()[ $type ];
		if ( 'kb:news' === $def['block'] ) {
			return self::news( $s );
		}
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $def['block'] ) ) {
			return KB_Render::$editing ? '<div class="kb-missing">' . esc_html( 'این ویجت به افزونهٔ «' . ( 'eservice' === $def['cat'] ? 'Boshrouyeh E-Services' : 'Boshrouyeh Core' ) . '» نیاز دارد.' ) . '</div>' : '';
		}
		$attrs = array();
		foreach ( $def['fields'] as $k => $f ) {
			$attrs[ $k ] = $s[ $k ];
		}
		return render_block(
			array(
				'blockName'    => $def['block'],
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}
}
