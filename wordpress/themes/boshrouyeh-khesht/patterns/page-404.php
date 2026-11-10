<?php
/**
 * Title: محتوای صفحهٔ ۴۰۴
 * Slug: boshrouyeh-khesht/page-404
 * Categories: khesht-pages
 * Inserter: no
 *
 * @package BoshrouyehKhesht
 */

?>
<!-- wp:html -->
<section class="kh-404" aria-labelledby="kh-404-title">
	<div class="kh-404__logo"><?php echo khesht_logo( 116 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted theme SVG. ?></div>
	<p class="kh-404__code" aria-hidden="true">۴۰۴</p>
	<h1 id="kh-404-title"><?php esc_html_e( 'این کوچه بن‌بست است!', 'boshrouyeh-khesht' ); ?></h1>
	<p><?php esc_html_e( 'صفحه‌ای که دنبالش بودید پیدا نشد. شاید جابه‌جا شده باشد؛ از جست‌وجو یا خدمات پرکاربرد کمک بگیرید.', 'boshrouyeh-khesht' ); ?></p>
	<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="kh-search-form">
		<label class="screen-reader-text" for="kh-404-search"><?php esc_html_e( 'جست‌وجو', 'boshrouyeh-khesht' ); ?></label>
		<?php echo khesht_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input id="kh-404-search" type="search" name="s" placeholder="<?php esc_attr_e( 'جست‌وجو در سایت…', 'boshrouyeh-khesht' ); ?>">
		<button type="submit" class="kh-btn kh-btn--action"><?php esc_html_e( 'جست‌وجو', 'boshrouyeh-khesht' ); ?></button>
	</form>
	<p><a class="kh-btn kh-btn--outline" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'بازگشت به خانه', 'boshrouyeh-khesht' ); ?></a></p>
</section>
<!-- /wp:html -->
