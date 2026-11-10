<?php
/**
 * Title: میانبر خدمات (۸ خدمت پرکاربرد)
 * Slug: boshrouyeh-khesht/quick-services
 * Categories: khesht-home, khesht-services
 *
 * @package BoshrouyehKhesht
 */

if ( defined( 'BMN_CORE_VERSION' ) ) : ?>
<!-- wp:boshrouyeh/quick-services {"align":"wide"} /-->
<?php else : ?>
<!-- wp:html -->
<nav class="kh-quick alignwide" aria-label="<?php esc_attr_e( 'خدمات پرکاربرد', 'boshrouyeh-khesht' ); ?>">
	<ul class="kh-quick__list">
		<?php
		$kh_items = array(
			array( 'payment', 'پرداخت عوارض', '/services/renovation-dues/' ),
			array( 'permit', 'پروانه ساختمان', '/services/building-permit/' ),
			array( '137', 'سامانه ۱۳۷', '/report-137/' ),
			array( 'waste', 'پسماند', '/services/waste-schedule/' ),
			array( 'cemetery', 'آرامستان', '/deceased/' ),
			array( 'tender', 'مناقصه و مزایده', '/tenders/' ),
			array( 'car', 'عوارض خودرو', '/services/vehicle-dues/' ),
			array( 'grid', 'همهٔ خدمات', '/services/' ),
		);
		foreach ( $kh_items as $kh_item ) :
			?>
			<li><a class="kh-quick__item" href="<?php echo esc_url( home_url( $kh_item[2] ) ); ?>"><span class="kh-taqche"><?php echo khesht_icon( $kh_item[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><span><?php echo esc_html( $kh_item[1] ); ?></span></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
<!-- /wp:html -->
<?php endif; ?>
