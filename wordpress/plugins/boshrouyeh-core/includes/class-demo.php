<?php
/**
 * Base content importer: 8 categories, 38 services, places, pages and samples.
 * Idempotent — existing items (matched by slug) are left untouched.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Demo / base content.
 */
final class BMN_Demo {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_bmn_demo_import', array( __CLASS__, 'handle' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'bmn demo', array( __CLASS__, 'cli' ) );
		}
	}

	/**
	 * Admin handler.
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'boshrouyeh-core' ), 403 );
		}
		check_admin_referer( 'bmn_demo_import' );
		$n = self::run();
		wp_safe_redirect( add_query_arg( array( 'page' => 'bmn-settings', 'bmn_imported' => $n ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * WP-CLI: wp bmn demo
	 */
	public static function cli() {
		$n = self::run();
		WP_CLI::success( sprintf( '%d items created.', $n ) );
	}

	/**
	 * Create a post if slug is free.
	 *
	 * @param array $args Post args (post_name required).
	 * @param array $meta Meta without prefix.
	 * @return int Post id (0 if it existed).
	 */
	private static function create( $args, $meta = array() ) {
		$existing = get_page_by_path( $args['post_name'], OBJECT, $args['post_type'] );
		if ( $existing ) {
			return 0;
		}
		$id = wp_insert_post( array_merge( array( 'post_status' => 'publish' ), $args ), true );
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, '_bmn_' . $k, $v );
		}
		return (int) $id;
	}

	/**
	 * Ensure a term.
	 *
	 * @param string $name Name.
	 * @param string $slug Slug.
	 * @param string $tax  Taxonomy.
	 * @param string $icon Icon key.
	 * @return int
	 */
	private static function term( $name, $slug, $tax, $icon = '' ) {
		$t = get_term_by( 'slug', $slug, $tax );
		if ( $t ) {
			return (int) $t->term_id;
		}
		$r = wp_insert_term( $name, $tax, array( 'slug' => $slug ) );
		if ( is_wp_error( $r ) ) {
			return 0;
		}
		if ( $icon ) {
			update_term_meta( $r['term_id'], '_bmn_icon', $icon );
		}
		return (int) $r['term_id'];
	}

	/**
	 * Run import.
	 *
	 * @return int Count created.
	 */
	public static function run() {
		$n = 0;

		// ---- Service catalogue (8 categories, 57 services) ----
		$catalog = array(
			array( 'شهرسازی و ساختمان', 'urban', 'building', array(
				array( 'building-permit', 'صدور و تمدید پروانهٔ ساختمانی', 'permit', 'semi', '۷ تا ۱۵ روز کاری', '', true, 'پروانه ساختمان', 'https://eservices.mrud.ir' ),
				array( 'land-use-inquiry', 'استعلام کاربری و طرح تفصیلی', 'pin', 'online', '۳ روز کاری' ),
				array( 'completion-certificate', 'گواهی عدم خلاف و پایان‌کار', 'doc-check', 'semi', '۱۰ روز کاری' ),
				array( 'expert-visit', 'درخواست بازدید کارشناس', 'eye', 'online', '۵ روز کاری', 'request' ),
				array( 'article-100', 'پیگیری پرونده کمیسیون مادهٔ ۱۰۰', 'tender', 'online', 'فوری', 'tracking' ),
				array( 'historic-fabric-rules', 'ضوابط نما و ساخت در بافت تاریخی', 'badgir', 'online', 'فوری' ),
				array( 'excavation-permit', 'مجوز حفاری و ترمیم نوار حفاری', 'permit', 'semi', '۳ روز کاری', 'request' ),
				array( 'address-plaque', 'پلاک‌کوبی و استعلام نشانی', 'pin', 'online', '۵ روز کاری', 'request' ),
				array( 'demolition-permit', 'مجوز تخریب و نوسازی', 'building', 'semi', '۷ روز کاری', 'request' ),
			) ),
			array( 'عوارض و پرداخت', 'dues', 'payment', array(
				array( 'renovation-dues', 'عوارض نوسازی و سطح شهر', 'payment', 'online', 'کمتر از ۳ دقیقه', 'dues_renovation', true, 'پرداخت عوارض' ),
				array( 'vehicle-dues', 'عوارض سالانهٔ خودرو', 'car', 'online', 'فوری', 'dues_vehicle', true, 'عوارض خودرو' ),
				array( 'business-dues', 'عوارض کسب و پیشه', 'store', 'online', 'فوری', 'dues_business' ),
				array( 'clearance-installments', 'مفاصاحساب و تقسیط بدهی', 'doc-check', 'semi', '۵ روز کاری', 'request' ),
				array( 'rent-bills', 'پرداخت قبوض و اجاره‌بها', 'ticket', 'online', 'فوری' ),
				array( 'payment-receipt', 'استعلام و دریافت رسید', 'print', 'online', 'فوری', 'tracking' ),
				array( 'signage-dues', 'عوارض تابلو و تبلیغات محیطی', 'star8', 'online', '۲ روز کاری', 'request' ),
			) ),
			array( 'خدمات شهری', 'city', '137', array(
				array( 'report-137-service', 'سامانهٔ ۱۳۷ (گزارش با عکس و موقعیت)', '137', 'online', 'پاسخ کمتر از ۷۲ ساعت', 'report137', true, 'سامانه ۱۳۷' ),
				array( 'waste-schedule', 'زمان‌بندی جمع‌آوری پسماند', 'waste', 'online', 'فوری', 'waste', true, 'پسماند' ),
				array( 'debris-collection', 'درخواست جمع‌آوری نخاله', 'car', 'online', '۲ روز کاری', 'request' ),
				array( 'lighting-asphalt', 'روشنایی و آسفالت معابر', 'lamp', 'online', '۷۲ ساعت', 'report137' ),
				array( 'green-space', 'فضای سبز و درخواست درخت', 'tree', 'online', '۷ روز کاری', 'request' ),
				array( 'public-transport', 'حمل‌ونقل عمومی و تاکسی', 'bus', 'online', 'فوری' ),
				array( 'minibus-live', 'مینی‌بوس آنلاین (موقعیت لحظه‌ای)', 'bus', 'online', 'لحظه‌ای', 'fleet', true, 'مینی‌بوس آنلاین' ),
				array( 'water-tanker', 'درخواست تانکر آب‌رسانی', 'drop', 'online', '۲۴ ساعت', 'request' ),
				array( 'qanat-report', 'گزارش خرابی قنات و مسیل', 'drop', 'online', '۷۲ ساعت', 'report137' ),
				array( 'bulky-waste', 'جمع‌آوری اقلام حجیم و بازیافت', 'waste', 'online', '۲ روز کاری', 'request' ),
				array( 'stray-animals', 'گزارش حیوانات بلاتکلیف', 'shield', 'online', '۴۸ ساعت', 'report137' ),
			) ),
			array( 'ایمنی و آتش‌نشانی', 'safety', 'fire', array(
				array( 'emergency-125', 'تماس اضطراری ۱۲۵', 'phone', 'online', 'فوری' ),
				array( 'safety-approval', 'استعلام و تأییدیهٔ ایمنی', 'shield', 'semi', '۵ روز کاری', 'request' ),
				array( 'safety-inspection', 'درخواست بازدید ایمنی', 'fire', 'online', '۳ روز کاری', 'request' ),
				array( 'safety-training', 'آموزش همگانی و مانور', 'users', 'onsite', 'طبق برنامه' ),
				array( 'extinguisher-service', 'شارژ کپسول و تجهیزات آتش‌نشانی', 'fire', 'semi', '۳ روز کاری', 'request' ),
			) ),
			array( 'آرامستان', 'cemetery', 'cemetery', array(
				array( 'deceased-search', 'جست‌وجوی متوفیان و مزار', 'search', 'online', 'فوری', 'deceased', true, 'آرامستان' ),
				array( 'burial-services', 'ثبت درخواست خدمات تدفین', 'cemetery', 'semi', 'همان روز', 'request' ),
				array( 'memorial-notice', 'اعلام مراسم و آگهی ترحیم', 'calendar', 'online', '۱ روز کاری', 'request' ),
			) ),
			array( 'مشارکت و شفافیت', 'participation', 'users', array(
				array( 'tenders-auctions', 'مناقصه، مزایده و استعلام بها', 'tender', 'online', 'طبق فراخوان', '', true, 'مناقصه و مزایده' ),
				array( 'budget-reports', 'بودجه و گزارش عملکرد', 'chart', 'online', 'فوری' ),
				array( 'council-resolutions', 'مصوبات شورای شهر', 'doc-check', 'online', 'فوری' ),
				array( 'mayor-appointment', 'نوبت ملاقات با شهردار', 'calendar', 'online', 'تأیید ظرف ۳ روز', 'appointment' ),
				array( 'feedback', 'نظرسنجی و انتقاد و پیشنهاد', 'chat', 'online', 'پاسخ ظرف ۷ روز', 'feedback' ),
				array( 'citizen-reporter', 'شهروندخبرنگار', 'camera', 'online', 'فوری', 'request' ),
				array( 'volunteers', 'ثبت‌نام داوطلبان طرح‌های محله‌ای', 'users', 'online', '۳ روز کاری', 'request' ),
				array( 'mayor-visits-tracking', 'بازدیدهای شهردار و پیگیری دستورها', 'eye', 'online', 'فوری', 'visits', true, 'بازدید شهردار' ),
				array( 'staff-complaint', 'شکایت از عملکرد کارکنان (بازرسی)', 'shield', 'online', 'پاسخ ظرف ۱۰ روز', 'complaint' ),
			) ),
			array( 'فرهنگ و گردشگری', 'culture', 'badgir', array(
				array( 'events-calendar', 'تقویم رویدادهای شهر', 'calendar', 'online', 'فوری' ),
				array( 'venue-booking', 'رزرو سالن، فضا و غرفه', 'building', 'semi', '۳ روز کاری', 'request' ),
				array( 'attractions-guide', 'راهنمای جاذبه‌ها و مسیرها', 'route', 'online', 'فوری' ),
				array( 'eco-lodges', 'معرفی اقامتگاه‌های بومگردی', 'home', 'online', 'فوری' ),
				array( 'sports-booking', 'رزرو سالن و زمین ورزشی', 'calendar', 'online', '۲ روز کاری', 'request' ),
				array( 'library-membership', 'عضویت کتابخانه و فرهنگسرا', 'users', 'semi', 'همان روز', 'request' ),
			) ),
			array( 'کسب‌وکار و اشتغال', 'business', 'store', array(
				array( 'careers', 'فراخوان‌های استخدام و همکاری', 'briefcase', 'online', 'طبق فراخوان' ),
				array( 'seasonal-bazaar', 'ثبت‌نام بازارچه‌های فصلی', 'store', 'online', '۵ روز کاری', 'request' ),
				array( 'booth-rental', 'اجارهٔ اماکن و غرفه‌های شهرداری', 'ticket', 'semi', 'طبق مزایده', 'request' ),
				array( 'taxi-license', 'صدور و تمدید پروانهٔ تاکسیرانی', 'car', 'semi', '۷ روز کاری', 'request' ),
				array( 'vendor-registration', 'ثبت‌نام پیمانکاران و تأمین‌کنندگان', 'briefcase', 'online', '۵ روز کاری', 'request' ),
			) ),
		);

		$order = 0;
		foreach ( $catalog as $ci => $cat ) {
			$term_id = self::term( $cat[0], $cat[1], 'bmn_service_cat', $cat[2] );
			foreach ( $cat[3] as $s ) {
				++$order;
				$is_online = 'online' === $s[3];
				$steps     = $is_online
					? "ورود با شمارهٔ همراه و کد پیامکی\nتکمیل فرم و بارگذاری مدارک (در صورت نیاز)\nبررسی توسط واحد مربوط\nاعلام نتیجه با پیامک و کد پیگیری"
					: "تکمیل فرم درخواست\nمراجعه با اصل مدارک به واحد مربوط\nبازدید یا بررسی کارشناسی\nصدور نتیجه و اطلاع‌رسانی با پیامک";
				$id = self::create(
					array(
						'post_type'    => 'bmn_service',
						'post_name'    => $s[0],
						'post_title'   => $s[1],
						'post_excerpt' => $is_online ? 'ثبت و پیگیری کاملاً آنلاین؛ مدارک، مراحل و زمان انجام در یک نگاه.' : 'مدارک و مراحل را ببینید، فرم را آنلاین پر کنید و فقط یک‌بار مراجعه کنید.',
						'post_content' => '<!-- wp:paragraph --><p>متن راهنمای این خدمت باید با واحد مربوط و مصوبات شورا تطبیق داده شود.</p><!-- /wp:paragraph -->',
						'menu_order'   => $order,
					),
					array_filter(
						array(
							'icon'         => $s[2],
							'mode'         => $s[3],
							'duration'     => $s[4],
							'eservice'     => $s[5] ?? '',
							'featured'     => ! empty( $s[6] ),
							'popular'      => ! empty( $s[6] ),
							'short_title'  => $s[7] ?? '',
							'external_url' => $s[8] ?? '',
							'steps'        => $steps,
							'documents'    => "کارت ملی\nشمارهٔ همراه به نام متقاضی",
							'faq'          => "هزینهٔ این خدمت چقدر است؟ | پیش از پرداخت، مبلغ دقیق و تخفیف‌های فعال نمایش داده می‌شود.\nچطور درخواستم را پیگیری کنم؟ | با کد پیگیری در صفحهٔ «پیگیری درخواست» یا اپ «بشرویهٔ من».",
						)
					)
				);
				if ( $id ) {
					wp_set_object_terms( $id, array( $term_id ), 'bmn_service_cat' );
					++$n;
				}
			}
		}
		// Specific detail for the flagship service.
		$reno = get_page_by_path( 'renovation-dues', OBJECT, 'bmn_service' );
		if ( $reno && ! get_post_meta( $reno->ID, '_bmn_custom', true ) ) {
			update_post_meta( $reno->ID, '_bmn_steps', "وارد کردن کد نوسازی یا شناسهٔ ملک\nورود با شمارهٔ همراه و کد پیامکی\nمشاهدهٔ فیش، جریمه و تخفیف‌های فعال\nپرداخت از درگاه بانکی و دریافت رسید" );
			update_post_meta( $reno->ID, '_bmn_documents', "کد نوسازی (روی فیش‌های قبلی)\nکد ملی مالک\nشمارهٔ همراه به نام مالک" );
			update_post_meta( $reno->ID, '_bmn_faq', "کد نوسازی را از کجا پیدا کنم؟ | روی فیش‌های قبلی عوارض یا با تماس با واحد درآمد.\nرسید پرداخت را چطور بگیرم؟ | پس از پرداخت، رسید قابل چاپ نمایش داده و در پیشخوان شما ذخیره می‌شود." );
			update_post_meta( $reno->ID, '_bmn_custom', 1 );
		}

		// ---- Neighborhoods ----
		foreach ( array( array( 'میان‌ده', 'mianeh', '0,2,4', '1' ), array( 'مرکز شهر', 'center', '0,1,2,3,4,5', '3' ), array( 'محلهٔ نمونه', 'sample', '1,3,5', '5' ) ) as $h ) {
			$tid = self::term( $h[0], $h[1], 'bmn_neighborhood' );
			if ( $tid && ! get_term_meta( $tid, '_bmn_waste_days', true ) ) {
				update_term_meta( $tid, '_bmn_waste_days', $h[2] );
				update_term_meta( $tid, '_bmn_recycle_days', $h[3] );
				update_term_meta( $tid, '_bmn_waste_time', '21:00' );
			}
		}

		// ---- Transparency types ----
		foreach ( array( array( 'بودجه', 'budget' ), array( 'گزارش عملکرد', 'performance' ), array( 'قرارداد', 'contract' ), array( 'مصوبهٔ شورا', 'resolution' ) ) as $t ) {
			self::term( $t[0], $t[1], 'bmn_doc_type' );
		}

		// ---- Places (tourism) ----
		$places = array(
			array( 'mostowfi-house', 'خانهٔ مستوفی', 'بنای اعیانی قاجاری با بادگیر سه‌گانه و حوض؛ نماد شهر و الهام‌بخش نشان شهرداری.', 'دورهٔ قاجار', 'بادگیر سه‌گانه، بافت تاریخی' ),
			array( 'jameh-mosque', 'مسجد جامع بشرویه', 'مسجد تک‌ایوانی با آجر بی‌لعاب از دورهٔ صفوی.', 'دورهٔ صفوی', 'معماری مذهبی' ),
			array( 'qaleh-dokhtar', 'قلعه دختر', 'برج و بارو بر فراز تپه‌ای مخروطی نزدیک روستای هوگند.', 'تاریخی', 'منظره، کوهپیمایی' ),
			array( 'water-mills', 'آسیاب‌های آبی زیرزمینی', 'آسیاب‌های قناتی‌گردان؛ برخی هنوز آرد می‌کنند.', 'سنتی', 'قنات، آب' ),
			array( 'ab-anbar', 'آب‌انبار (حوض‌انبار) میان‌ده', 'از آثار ملی؛ گنبد و بادگیرهای خنک‌کننده.', 'اثر ملی', 'گنبد، بادگیر' ),
			array( 'forouzanfar-house', 'منزل استاد فروزانفر', 'زادگاه بدیع‌الزمان فروزانفر؛ ثبت ملی.', 'ثبت ملی', 'مفاخر، ادبیات' ),
			array( 'tarnav-bridge', 'پل آب‌بر (ترناو)', 'سازهٔ سنتی عبور آب؛ نماد مدیریت هوشمند آب.', 'سنتی', 'قنات، محیط‌زیست' ),
			array( 'bagh-khan', 'باغ خان', 'باغی به سبک قاجار در دل کویر.', 'دورهٔ قاجار', 'باغ ایرانی' ),
			array( 'boshrouyeh-desert', 'کویر بشرویه', 'تپه‌های شنی و آسمان پرستاره.', 'طبیعی', 'آسمان شب، کویرنوردی' ),
			array( 'arsk-hogand', 'روستاهای ارسک و هوگند', 'بافت خشتی کهن و خانه‌های گنبدی.', 'روستایی', 'بومگردی' ),
			array( 'rabat-esfak', 'رباط اصفاک و نیگنان', 'کاروانسراهای کهن مسیر کویری.', 'تاریخی', 'کاروانسرا' ),
			array( 'boshrouyeh-museum', 'موزهٔ خصوصی بشرویه', 'در خانهٔ تاریخی ملا عبدالله تونی.', 'موزه', 'تاریخ شهر' ),
		);
		$art = array( 'badgir-house', 'mosque', 'fortress', 'watermill', 'ab-anbar', 'house', 'bridge', 'garden', 'desert', 'village', 'caravanserai', 'museum' );
		foreach ( $places as $i => $p ) {
			if ( self::create(
				array(
					'post_type'    => 'bmn_place',
					'post_name'    => $p[0],
					'post_title'   => $p[1],
					'post_excerpt' => $p[2],
					'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $p[2] ) . ' (متن کامل، ساعت بازدید و مختصات پس از بازدید میدانی تکمیل شود.)</p><!-- /wp:paragraph -->',
					'menu_order'   => $i + 1,
				),
				array(
					'era'          => $p[3],
					'tags'         => $p[4],
					'illustration' => $art[ $i ],
				)
			) ) {
				++$n;
			}
		}

		// ---- Sample notices / news / tenders / events (marked as samples) ----
		$mianeh = get_term_by( 'slug', 'mianeh', 'bmn_neighborhood' );
		$notices = array(
			array( 'water-outage-mianeh', 'قطع موقت آب محلهٔ میان‌ده (نمونه)', 'urgent', 3 ),
			array( 'renovation-discount', 'مهلت تخفیف عوارض نوسازی تا پایان آذر (نمونه)', 'normal', 30 ),
			array( 'bazaar-booth-auction', 'آگهی مزایدهٔ اجارهٔ غرفه‌های بازارچه (نمونه)', 'normal', 20 ),
			array( 'holiday-waste', 'برنامهٔ جمع‌آوری پسماند در روزهای تعطیل (نمونه)', 'normal', 15 ),
			array( 'green-space-call', 'فراخوان همکاری نیروی فصلی فضای سبز (نمونه)', 'normal', 25 ),
		);
		foreach ( $notices as $no ) {
			$id = self::create(
				array(
					'post_type'    => 'bmn_notice',
					'post_name'    => $no[0],
					'post_title'   => $no[1],
					'post_content' => '<!-- wp:paragraph --><p>متن اطلاعیه نمونه است و برای نمایش طراحی آمده است.</p><!-- /wp:paragraph -->',
				),
				array(
					'urgency' => $no[2],
					'expires' => time() + $no[3] * DAY_IN_SECONDS,
				)
			);
			if ( $id ) {
				++$n;
				if ( 'water-outage-mianeh' === $no[0] && $mianeh ) {
					wp_set_object_terms( $id, array( $mianeh->term_id ), 'bmn_neighborhood' );
				}
			}
		}
		foreach ( array(
			array( 'historic-fabric-restoration', 'مرمت بافت تاریخی محلهٔ میان‌ده آغاز شد (نمونه)', 'house' ),
			array( 'desert-nights-festival', 'جشنوارهٔ فرهنگی «شب‌های کویر» برگزار می‌شود (نمونه)', 'night' ),
			array( 'entrance-lighting', 'بهسازی معابر و روشنایی ورودی شهر (نمونه)', 'news-city' ),
		) as $news ) {
			if ( self::create(
				array(
					'post_type'    => 'post',
					'post_name'    => $news[0],
					'post_title'   => $news[1],
					'post_excerpt' => 'خبر نمونه برای نمایش قالب خشت.',
					'post_content' => '<!-- wp:paragraph --><p>این خبر نمونه است. مبالغ، نام‌ها و تاریخ‌ها فرضی‌اند و فقط برای نمایش طراحی آمده‌اند.</p><!-- /wp:paragraph -->',
				),
				array( 'illustration' => $news[2] )
			) ) {
				++$n;
			}
		}
		foreach ( array(
			array( 'booth-auction-1405', 'مزایدهٔ اجارهٔ غرفه‌های بازارچه (نمونه)', 'auction', '۱۴۰۵/۱۲', 20 ),
			array( 'street-improvement-tender', 'مناقصهٔ بهسازی و آسفالت معابر (نمونه)', 'tender', '۱۴۰۵/۱۳', 12 ),
			array( 'lighting-inquiry', 'استعلام بهای تجهیزات روشنایی (نمونه)', 'inquiry', '۱۴۰۵/۱۴', 5 ),
		) as $t ) {
			if ( self::create(
				array(
					'post_type'    => 'bmn_tender',
					'post_name'    => $t[0],
					'post_title'   => $t[1],
					'post_content' => '<!-- wp:paragraph --><p>فراخوان نمونه برای نمایش طراحی.</p><!-- /wp:paragraph -->',
				),
				array(
					'kind'     => $t[2],
					'number'   => $t[3],
					'deadline' => time() + $t[4] * DAY_IN_SECONDS,
					'opening'  => time() + ( $t[4] + 2 ) * DAY_IN_SECONDS,
					'status'   => 'open',
				)
			) ) {
				++$n;
			}
		}
		foreach ( array(
			array( 'desert-poetry-night', 'شب شعر کویر در خانهٔ فروزانفر (نمونه)', 3, 18, 'خانهٔ استاد فروزانفر' ),
			array( 'green-belt-planting', 'پویش درخت‌کاری کمربند سبز (نمونه)', 4, 8, 'کمربند سبز شهر' ),
			array( 'council-public-meeting', 'نشست عمومی شورا با شهروندان (نمونه)', 5, 17, 'سالن شورای شهر' ),
		) as $e ) {
			$start = strtotime( 'today', time() ) + $e[2] * DAY_IN_SECONDS + $e[3] * HOUR_IN_SECONDS;
			if ( self::create(
				array(
					'post_type'    => 'bmn_event',
					'post_name'    => $e[0],
					'post_title'   => $e[1],
					'post_content' => '<!-- wp:paragraph --><p>رویداد نمونه.</p><!-- /wp:paragraph -->',
				),
				array(
					'start' => $start,
					'end'   => $start + 2 * HOUR_IN_SECONDS,
					'venue' => $e[4],
				)
			) ) {
				++$n;
			}
		}

		// ---- Pages ----
		$pages = array(
			'home'         => array( 'خانه', '' ),
			'news'         => array( 'اخبار', '' ),
			'about'        => array( 'درباره بشرویه', '<!-- wp:paragraph --><p>بشرویه شهری است که با خشت و گل ساخته شده، با بادگیر نفس می‌کشد و با قنات زنده است؛ چهارمین شهر خشت و گلی ایران.</p><!-- /wp:paragraph --><!-- wp:pattern {"slug":"boshrouyeh-khesht/concept-cards"} /-->' ),
			'municipality' => array( 'شهرداری', '<!-- wp:paragraph --><p>معرفی شهرداری، معاونت‌ها و واحدها.</p><!-- /wp:paragraph -->' ),
			'contact'      => array( 'تماس با ما', '<!-- wp:boshrouyeh/contact-info /--><!-- wp:boshrouyeh/feedback /-->' ),
			'citizen'      => array( 'پیشخوان شهروند', '<!-- wp:boshrouyeh/citizen-dashboard /-->', 'page-citizen' ),
			'report-137'   => array( 'سامانه ۱۳۷', '<!-- wp:boshrouyeh/report-137 /-->' ),
			'track'        => array( 'پیگیری درخواست', '<!-- wp:boshrouyeh/track /-->' ),
			'dues'         => array( 'پرداخت عوارض', '<!-- wp:boshrouyeh/dues {"type":"renovation"} /-->' ),
			'deceased'     => array( 'جست‌وجوی متوفیان', '<!-- wp:boshrouyeh/deceased-search /-->' ),
			'appointment'  => array( 'نوبت ملاقات با شهردار', '<!-- wp:boshrouyeh/appointment /-->' ),
			'feedback'     => array( 'انتقاد و پیشنهاد', '<!-- wp:boshrouyeh/feedback /-->' ),
			'waste'        => array( 'زمان‌بندی جمع‌آوری پسماند', '<!-- wp:boshrouyeh/waste-schedule /-->' ),
			'app'          => array( 'اپ بشرویهٔ من', '<!-- wp:pattern {"slug":"boshrouyeh-khesht/cta-app"} /-->' ),
			'foia'         => array( 'قانون دسترسی آزاد به اطلاعات', '<!-- wp:paragraph --><p>درخواست اطلاعات بر اساس قانون انتشار و دسترسی آزاد به اطلاعات از طریق سامانهٔ ملی ثبت می‌شود.</p><!-- /wp:paragraph -->' ),
			'privacy'      => array( 'حریم خصوصی', '<!-- wp:paragraph --><p>شمارهٔ همراه فقط برای احراز هویت و اطلاع‌رسانی خدمات استفاده می‌شود و در اختیار اشخاص ثالث قرار نمی‌گیرد. تصاویر گزارش‌های ۱۳۷ فقط برای کارکنان مسئول قابل مشاهده است.</p><!-- /wp:paragraph -->' ),
			'careers'      => array( 'فرصت‌های شغلی', '<!-- wp:paragraph --><p>فراخوان‌های استخدام و همکاری در این صفحه منتشر می‌شود.</p><!-- /wp:paragraph -->' ),
		);
		$ids = array();
		foreach ( $pages as $slug => $pg ) {
			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				$ids[ $slug ] = $existing->ID;
				continue;
			}
			$id = self::create(
				array(
					'post_type'    => 'page',
					'post_name'    => $slug,
					'post_title'   => $pg[0],
					'post_content' => $pg[1],
				)
			);
			if ( $id ) {
				$ids[ $slug ] = $id;
				if ( ! empty( $pg[2] ) ) {
					update_post_meta( $id, '_wp_page_template', $pg[2] );
				}
				++$n;
			}
		}
		foreach ( array( 'mayor' => 'پیام شهردار', 'structure' => 'ساختار سازمانی', 'council' => 'شورای اسلامی شهر', 'fire' => 'سازمان آتش‌نشانی' ) as $slug => $title ) {
			if ( ! empty( $ids['municipality'] ) && ! get_page_by_path( 'municipality/' . $slug ) ) {
				if ( self::create( array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $title, 'post_parent' => $ids['municipality'], 'post_content' => '<!-- wp:paragraph --><p>محتوای این بخش توسط روابط عمومی تکمیل می‌شود.</p><!-- /wp:paragraph -->' ) ) ) {
					++$n;
				}
			}
		}

		if ( ! empty( $ids['home'] ) && ! empty( $ids['news'] ) && 'page' !== get_option( 'show_on_front' ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $ids['home'] );
			update_option( 'page_for_posts', $ids['news'] );
		}

		$settings = get_option( 'bmn_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$map      = array( 'page_dashboard' => 'citizen', 'page_137' => 'report-137', 'page_dues' => 'dues', 'page_track' => 'track', 'page_deceased' => 'deceased', 'page_appointment' => 'appointment', 'page_feedback' => 'feedback' );
		foreach ( $map as $key => $slug ) {
			if ( empty( $settings[ $key ] ) && ! empty( $ids[ $slug ] ) ) {
				$settings[ $key ] = $ids[ $slug ];
			}
		}
		$settings += array(
			'org_name'   => 'شهرداری بشرویه',
			'work_hours' => 'ساعت کاری: ۷:۳۰ تا ۱۴:۳۰',
			'address'    => 'بشرویه، خیابان امام، ساختمان شهرداری',
		);
		update_option( 'bmn_settings', $settings );
		$n += (int) apply_filters( 'bmn_demo_extra', 0 );
		flush_rewrite_rules();
		return $n;
	}
}
