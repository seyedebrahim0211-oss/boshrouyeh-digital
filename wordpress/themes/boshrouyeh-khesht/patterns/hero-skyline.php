<?php
/**
 * Title: سربرگ تصویری خط آسمان خشتی + جست‌وجوی هوشمند
 * Slug: boshrouyeh-khesht/hero-skyline
 * Categories: khesht-home
 * Keywords: hero, search, skyline
 *
 * @package BoshrouyehKhesht
 */

?>
<!-- wp:html -->
<section class="kh-hero alignfull" aria-labelledby="kh-hero-title">
	<div class="kh-hero__inner">
		<p class="kh-eyebrow"><?php esc_html_e( 'چهارمین شهر خشت و گلی ایران', 'boshrouyeh-khesht' ); ?></p>
		<h1 id="kh-hero-title" class="kh-hero__title"><?php esc_html_e( 'بشرویه؛ شهرِ خشت، باد و قنات', 'boshrouyeh-khesht' ); ?></h1>
		<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="kh-search-form kh-search-form--hero">
			<label class="screen-reader-text" for="kh-hero-search"><?php esc_html_e( 'جست‌وجوی خدمات', 'boshrouyeh-khesht' ); ?></label>
			<?php echo khesht_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input id="kh-hero-search" type="search" name="s" autocomplete="off" placeholder="<?php esc_attr_e( 'چه خدمتی نیاز دارید؟ مثلاً «عوارض نوسازی»', 'boshrouyeh-khesht' ); ?>" data-kh-suggest>
			<button type="submit" class="kh-btn kh-btn--action"><?php esc_html_e( 'جست‌وجو', 'boshrouyeh-khesht' ); ?></button>
			<ul class="kh-suggest" role="listbox" hidden></ul>
		</form>
		<p class="kh-hero__popular">
			<span><?php esc_html_e( 'پرجست‌وجوها:', 'boshrouyeh-khesht' ); ?></span>
			<a href="<?php echo esc_url( home_url( '/services/renovation-dues/' ) ); ?>"><?php esc_html_e( 'عوارض نوسازی', 'boshrouyeh-khesht' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/services/building-permit/' ) ); ?>"><?php esc_html_e( 'پروانه ساختمان', 'boshrouyeh-khesht' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/report-137/' ) ); ?>"><?php esc_html_e( 'گزارش ۱۳۷', 'boshrouyeh-khesht' ); ?></a>
		</p>
	</div>
	<div class="kh-hero__sun" aria-hidden="true"></div>
	<div class="kh-hero__skyline" aria-hidden="true"></div>
</section>
<!-- /wp:html -->
