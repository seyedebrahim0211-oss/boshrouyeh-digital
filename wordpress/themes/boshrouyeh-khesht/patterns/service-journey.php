<?php
/**
 * Title: سفر شهروند در چهار قدم
 * Slug: boshrouyeh-khesht/service-journey
 * Categories: khesht-services, khesht-sections
 *
 * @package BoshrouyehKhesht
 */

$kh_steps = array(
	array( 'search', 'جست‌وجو یا میانبر', 'از صفحهٔ اصلی یا اپ' ),
	array( 'user', 'ورود با کد پیامکی', 'بدون رمز و ثبت‌نام طولانی' ),
	array( 'permit', 'دیدن فیش و تخفیف', 'با شناسهٔ ملک یا کد نوسازی' ),
	array( 'payment', 'پرداخت و رسید', 'درگاه بانکی + رسید قابل چاپ' ),
);
?>
<!-- wp:html -->
<ol class="kh-journey alignwide">
	<?php foreach ( $kh_steps as $kh_i => $kh_s ) : ?>
		<li class="kh-journey__step">
			<span class="kh-taqche"><?php echo khesht_icon( $kh_s[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span><strong><?php echo esc_html( khesht_fa( $kh_i + 1 ) . '. ' . $kh_s[1] ); ?></strong><small><?php echo esc_html( $kh_s[2] ); ?></small></span>
		</li>
	<?php endforeach; ?>
</ol>
<!-- /wp:html -->
