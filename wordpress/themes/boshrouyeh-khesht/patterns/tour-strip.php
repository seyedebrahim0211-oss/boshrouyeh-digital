<?php
/**
 * Title: نوار گردشگری «بشرویه را بگرد»
 * Slug: boshrouyeh-khesht/tour-strip
 * Categories: khesht-home, khesht-sections
 *
 * @package BoshrouyehKhesht
 */

?>
<!-- wp:group {"align":"wide","className":"kh-tour","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide kh-tour">
	<!-- wp:group {"className":"kh-tour__head","layout":{"type":"default"}} -->
	<div class="wp-block-group kh-tour__head">
		<!-- wp:heading {"className":"kh-tour__title"} -->
		<h2 class="wp-block-heading kh-tour__title"><?php esc_html_e( 'بشرویه را بگرد', 'boshrouyeh-khesht' ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'جاذبه‌ها، مسیرها و راهنمای صوتی', 'boshrouyeh-khesht' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:buttons -->
		<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kh-sun"} -->
		<div class="wp-block-button is-style-kh-sun"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/places/' ) ); ?>"><?php esc_html_e( 'همهٔ جاذبه‌ها', 'boshrouyeh-khesht' ); ?></a></div>
		<!-- /wp:button --></div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
	<!-- wp:query {"queryId":12,"query":{"perPage":4,"postType":"bmn_place","order":"asc","orderBy":"menu_order","inherit":false},"className":"kh-tour__query"} -->
	<div class="wp-block-query kh-tour__query">
		<!-- wp:post-template {"className":"kh-tour__cards","layout":{"type":"grid","columnCount":4,"minimumColumnWidth":"11rem"}} -->
			<!-- wp:group {"className":"kh-tour-card","layout":{"type":"default"}} -->
			<div class="wp-block-group kh-tour-card">
				<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3","sizeSlug":"khesht-card"} /-->
				<!-- wp:post-title {"level":3,"isLink":true,"className":"kh-tour-card__label"} /-->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
