<?php
/**
 * Title: دعوت به نصب اپ «بشرویهٔ من»
 * Slug: boshrouyeh-khesht/cta-app
 * Categories: khesht-sections
 *
 * @package BoshrouyehKhesht
 */

?>
<!-- wp:group {"align":"wide","className":"is-style-kh-night kh-cta","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide is-style-kh-night kh-cta">
	<!-- wp:group {"layout":{"type":"default"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"textColor":"sun"} -->
		<h2 class="wp-block-heading has-sun-color has-text-color"><?php esc_html_e( 'شهرداری در جیب شما', 'boshrouyeh-khesht' ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"textColor":"milk"} -->
		<p class="has-milk-color has-text-color"><?php esc_html_e( 'پرداخت، گزارش ۱۳۷ و پیگیری در کمتر از سه لمس؛ با اپ «بشرویهٔ من».', 'boshrouyeh-khesht' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'کافه‌بازار', 'boshrouyeh-khesht' ); ?></a></div><!-- /wp:button -->
		<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'مایکت', 'boshrouyeh-khesht' ); ?></a></div><!-- /wp:button -->
		<!-- wp:button {"className":"is-style-kh-sun"} --><div class="wp-block-button is-style-kh-sun"><a class="wp-block-button__link wp-element-button" href="#" data-kh-pwa-install><?php esc_html_e( 'نصب نسخهٔ وب', 'boshrouyeh-khesht' ); ?></a></div><!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
