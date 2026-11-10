<?php
/**
 * Sample mayor visit, follow-up directives and a minibus line (hooked into `wp bmn demo`).
 *
 * @package BoshrouyehEservices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Demo data for e-services modules.
 */
class BMN_ES_Demo {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'bmn_demo_extra', array( __CLASS__, 'run' ) );
	}

	/**
	 * Unit id by slug.
	 *
	 * @param string $slug Slug.
	 * @return int
	 */
	private static function unit( $slug ) {
		$t = get_term_by( 'slug', $slug, BMN_Staff::TAX );
		return $t ? (int) $t->term_id : 0;
	}

	/**
	 * Create sample items (idempotent by slug).
	 *
	 * @param int $n Count so far.
	 * @return int
	 */
	public static function run( $n ) {
		BMN_Staff::seed_units();
		if ( ! get_page_by_path( 'sample-visit-miandeh', OBJECT, BMN_Visits::VISIT ) ) {
			$vid = wp_insert_post(
				array(
					'post_type'    => BMN_Visits::VISIT,
					'post_status'  => 'publish',
					'post_name'    => 'sample-visit-miandeh',
					'post_title'   => 'بازدید میدانی شهردار از محلهٔ میان‌ده و بازار قدیم (نمونه)',
					'post_excerpt' => 'بررسی وضعیت معابر، روشنایی و جمع‌آوری پسماند با حضور معاونان و مدیران واحدها.',
					'post_content' => '<!-- wp:paragraph --><p>این بازدید نمونه است و برای نمایش «پروندهٔ پیگیری» آمده است. پس از هر بازدید، دستورها با مسئلهٔ دقیق، واحد مسئول، مهلت و منبع اعتبار ثبت و وضعیت آن‌ها به‌صورت عمومی منتشر می‌شود.</p><!-- /wp:paragraph -->',
				)
			);
			if ( $vid && ! is_wp_error( $vid ) ) {
				update_post_meta( $vid, '_bmn_vdate', time() - 12 * DAY_IN_SECONDS );
				update_post_meta( $vid, '_bmn_place', 'کوچه‌های بافت تاریخی و بازار قدیم' );
				update_post_meta( $vid, '_bmn_hood', 'میان‌ده' );
				$dirs = array(
					array( 'گودال و نشست آسفالت در ورودی بازار قدیم به طول حدود ۴۰ متر', 'لکه‌گیری و ترمیم آسفالت ورودی بازار', 'civil-works', 18, 'civil', 'ردیف عمرانی ۱۴۰۵ — ترمیم معابر', 'progress', 60 ),
					array( '۹ پایهٔ روشنایی خاموش در کوچهٔ آب‌انبار', 'تعویض لامپ‌ها با LED و رفع اتصالی تابلو', 'city-services', 7, 'current', '', 'done', 100 ),
					array( 'تجمع نخاله ساختمانی کنار قنات میان‌ده', 'جمع‌آوری نخاله و برخورد با متخلف طبق ماده ۱۰۰', 'enforcement', -3, 'none', '', 'progress', 40 ),
					array( 'نبود سطل پسماند تفکیکی در محوطهٔ بازار', 'نصب ۶ مخزن تفکیک از مبدأ', 'city-services', 30, 'people', 'مشارکت اصناف بازار', 'stopped', 10 ),
				);
				foreach ( $dirs as $i => $d ) {
					$id = wp_insert_post(
						array(
							'post_type'   => BMN_Visits::DIR,
							'post_status' => 'publish',
							'post_title'  => $d[1],
							'menu_order'  => $i,
						)
					);
					if ( ! $id || is_wp_error( $id ) ) {
						continue;
					}
					update_post_meta( $id, '_bmn_visit', $vid );
					update_post_meta( $id, '_bmn_issue', $d[0] );
					update_post_meta( $id, '_bmn_order', $d[1] );
					update_post_meta( $id, '_bmn_deadline', time() + $d[3] * DAY_IN_SECONDS );
					update_post_meta( $id, '_bmn_funding', $d[4] );
					update_post_meta( $id, '_bmn_funding_note', $d[5] );
					update_post_meta( $id, '_bmn_dstatus', $d[6] );
					update_post_meta( $id, '_bmn_progress', $d[7] );
					update_post_meta( $id, '_bmn_dcode', (string) ( 405100 + $i ) );
					update_post_meta(
						$id,
						'_bmn_dlog',
						array(
							array( 's' => 'new', 'p' => 0, 'n' => 'ابلاغ به واحد', 't' => time() - 11 * DAY_IN_SECONDS, 'by' => 0 ),
							array( 's' => $d[6], 'p' => $d[7], 'n' => 'گزارش واحد مسئول', 't' => time() - 2 * DAY_IN_SECONDS, 'by' => 0 ),
						)
					);
					wp_set_object_terms( $id, array( self::unit( $d[2] ) ), BMN_Staff::TAX );
					++$n;
				}
				++$n;
			}
		}
		if ( ! get_page_by_path( 'line-1', OBJECT, BMN_Fleet::ROUTE ) ) {
			$stops = "پایانهٔ شهید بهشتی|33.8612|57.4211\nمیدان امام|33.8655|57.4268\nبازار قدیم|33.8679|57.4301\nشهرداری|33.8702|57.4329\nبیمارستان|33.8741|57.4370\nپایانهٔ شمالی|33.8785|57.4405";
			$rid   = wp_insert_post(
				array(
					'post_type'    => BMN_Fleet::ROUTE,
					'post_status'  => 'publish',
					'post_name'    => 'line-1',
					'post_title'   => 'خط ۱: پایانهٔ جنوبی — پایانهٔ شمالی (نمونه)',
					'post_excerpt' => 'مختصات ایستگاه‌ها نمونه است؛ از بخش «ناوگان مینی‌بوس» اصلاح کنید.',
				)
			);
			if ( $rid && ! is_wp_error( $rid ) ) {
				update_post_meta( $rid, '_bmn_stops', $stops );
				update_post_meta( $rid, '_bmn_stops_parsed', BMN_Fleet::parse_stops( $stops ) );
				update_post_meta( $rid, '_bmn_color', '#16737A' );
				update_post_meta( $rid, '_bmn_hours', '۶:۳۰ تا ۲۱' );
				update_post_meta( $rid, '_bmn_headway', 'هر ۲۰ دقیقه' );
				foreach ( array( 'مینی‌بوس ۱۰۱', 'مینی‌بوس ۱۰۲' ) as $name ) {
					$vid = wp_insert_post( array( 'post_type' => BMN_Fleet::VEHICLE, 'post_status' => 'publish', 'post_title' => $name ) );
					if ( $vid && ! is_wp_error( $vid ) ) {
						update_post_meta( $vid, '_bmn_route', $rid );
						++$n;
					}
				}
				++$n;
			}
		}
		return $n;
	}
}
