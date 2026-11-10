<?php
/**
 * Title: پیام شهردار + شفافیت + اپ
 * Slug: boshrouyeh-khesht/mayor-transparency-app
 * Categories: khesht-home
 *
 * @package BoshrouyehKhesht
 */

?>
<!-- wp:html -->
<div class="kh-trio alignwide">
	<a class="kh-trio__item" href="<?php echo esc_url( home_url( '/municipality/mayor/' ) ); ?>">
		<span class="kh-taqche kh-taqche--round"><?php echo khesht_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span><strong><?php esc_html_e( 'پیام شهردار', 'boshrouyeh-khesht' ); ?></strong><small><?php esc_html_e( 'بشرویه را با هم آبادتر می‌سازیم…', 'boshrouyeh-khesht' ); ?></small></span>
	</a>
	<a class="kh-trio__item" href="<?php echo esc_url( home_url( '/transparency/' ) ); ?>">
		<span class="kh-taqche"><?php echo khesht_icon( 'chart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span><strong><?php esc_html_e( 'شفافیت', 'boshrouyeh-khesht' ); ?></strong><small><?php esc_html_e( 'بودجه، قراردادها و مصوبات شورا', 'boshrouyeh-khesht' ); ?></small></span>
	</a>
	<a class="kh-trio__item kh-trio__item--app" href="<?php echo esc_url( home_url( '/app/' ) ); ?>">
		<span class="kh-trio__applogo"><?php echo khesht_logo( 44 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span><strong><?php esc_html_e( 'اپ «بشرویهٔ من»', 'boshrouyeh-khesht' ); ?></strong><small><?php esc_html_e( 'دریافت از بازار، مایکت و نسخهٔ وب', 'boshrouyeh-khesht' ); ?></small></span>
	</a>
</div>
<!-- /wp:html -->
