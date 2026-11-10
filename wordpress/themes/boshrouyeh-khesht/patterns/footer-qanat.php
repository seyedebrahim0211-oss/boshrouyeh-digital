<?php
/**
 * Title: پانویس رشته‌چاه قنات
 * Slug: boshrouyeh-khesht/footer-qanat
 * Categories: khesht-sections
 * Inserter: no
 *
 * @package BoshrouyehKhesht
 */

$kh_address = khesht_opt( 'address', 'بشرویه، خیابان امام، ساختمان شهرداری' );
$kh_phone   = khesht_opt( 'phone', '۰۵۶-۳۲۷۲۰۰۰۰' );
$kh_email   = khesht_opt( 'email', 'info@boshrooyeh.ir' );
$kh_cols    = array(
	__( 'خدمات پرمراجعه', 'boshrouyeh-khesht' ) => array(
		array( 'پرداخت عوارض نوسازی', '/services/renovation-dues/' ),
		array( 'عوارض سالانه خودرو', '/services/vehicle-dues/' ),
		array( 'سامانه ۱۳۷', '/report-137/' ),
		array( 'پیگیری درخواست', '/track/' ),
		array( 'جست‌وجوی متوفیان', '/deceased/' ),
	),
	__( 'شهرداری', 'boshrouyeh-khesht' )       => array(
		array( 'پیام شهردار', '/municipality/mayor/' ),
		array( 'مناقصه و مزایده', '/tenders/' ),
		array( 'بودجه و گزارش عملکرد', '/transparency/' ),
		array( 'فرصت‌های شغلی', '/careers/' ),
		array( 'نوبت ملاقات با شهردار', '/appointment/' ),
	),
	__( 'بشرویه را بگرد', 'boshrouyeh-khesht' ) => array(
		array( 'خانهٔ مستوفی', '/places/' ),
		array( 'قلعه دختر', '/places/' ),
		array( 'آسیاب‌های آبی', '/places/' ),
		array( 'تقویم رویدادها', '/events/' ),
		array( 'نقشه و راهنمای سفر', '/about/map/' ),
	),
);
?>
<!-- wp:html -->
<footer class="kh-footer" role="contentinfo">
	<div class="kh-footer__chain" aria-hidden="true"></div>
	<div class="kh-footer__inner">
		<div class="kh-footer__brand">
			<a class="kh-lockup kh-lockup--light" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php echo khesht_logo( 60 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="kh-lockup__text">
					<span class="kh-lockup__fa"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
					<span class="kh-lockup__en" lang="en">BOSHROUYEH MUNICIPALITY</span>
				</span>
			</a>
			<p class="kh-footer__motto"><?php esc_html_e( 'شهرِ خشت، باد و قنات', 'boshrouyeh-khesht' ); ?></p>
			<ul class="kh-footer__contact">
				<li><?php echo khesht_icon( 'pin', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $kh_address ); ?></li>
				<li><?php echo khesht_icon( 'phone', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', strtr( $kh_phone, array_combine( array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), range( 0, 9 ) ) ) ) ); ?>"><?php echo esc_html( $kh_phone ); ?></a></li>
				<li><?php echo khesht_icon( 'mail', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="mailto:<?php echo esc_attr( antispambot( $kh_email ) ); ?>"><?php echo esc_html( antispambot( $kh_email ) ); ?></a></li>
			</ul>
		</div>
		<?php foreach ( $kh_cols as $kh_title => $kh_links ) : ?>
			<nav class="kh-footer__col" aria-label="<?php echo esc_attr( $kh_title ); ?>">
				<h2 class="kh-footer__title"><?php echo esc_html( $kh_title ); ?></h2>
				<ul>
					<?php foreach ( $kh_links as $kh_link ) : ?>
						<li><a href="<?php echo esc_url( home_url( $kh_link[1] ) ); ?>"><?php echo esc_html( $kh_link[0] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endforeach; ?>
	</div>
	<div class="kh-footer__bottom">
		<p>
			<?php
			/* translators: %s: Jalali year. */
			printf( esc_html__( '© %s شهرداری بشرویه — همهٔ حقوق محفوظ است.', 'boshrouyeh-khesht' ), esc_html( function_exists( 'bmn_jdate' ) ? bmn_jdate( 'Y' ) : wp_date( 'Y' ) ) );
			?>
		</p>
		<p class="kh-footer__legal">
			<a href="<?php echo esc_url( home_url( '/foia/' ) ); ?>"><?php esc_html_e( 'قانون دسترسی آزاد به اطلاعات', 'boshrouyeh-khesht' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>"><?php esc_html_e( 'حریم خصوصی', 'boshrouyeh-khesht' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?>"><?php esc_html_e( 'نقشهٔ سایت', 'boshrouyeh-khesht' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'تماس', 'boshrouyeh-khesht' ); ?></a>
		</p>
	</div>
</footer>
<!-- /wp:html -->
