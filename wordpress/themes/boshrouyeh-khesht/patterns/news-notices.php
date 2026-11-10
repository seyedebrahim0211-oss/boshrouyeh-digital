<?php
/**
 * Title: تازه‌ترین اخبار + اطلاعیه‌ها
 * Slug: boshrouyeh-khesht/news-notices
 * Categories: khesht-home
 *
 * @package BoshrouyehKhesht
 */

?>
<!-- wp:group {"align":"wide","className":"kh-news-notices","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide kh-news-notices">
	<!-- wp:group {"className":"kh-news","layout":{"type":"default"}} -->
	<div class="wp-block-group kh-news">
		<!-- wp:group {"className":"kh-section-head","layout":{"type":"flex","justifyContent":"space-between"}} -->
		<div class="wp-block-group kh-section-head">
			<!-- wp:heading {"className":"kh-section-title"} -->
			<h2 class="wp-block-heading kh-section-title"><?php esc_html_e( 'تازه‌ترین اخبار', 'boshrouyeh-khesht' ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"kh-more"} -->
			<p class="kh-more"><a href="<?php echo esc_url( home_url( '/news/' ) ); ?>"><?php esc_html_e( 'همهٔ اخبار ←', 'boshrouyeh-khesht' ); ?></a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
		<!-- wp:query {"queryId":11,"query":{"perPage":3,"postType":"post","order":"desc","orderBy":"date","inherit":false,"sticky":""},"className":"kh-news-query"} -->
		<div class="wp-block-query kh-news-query">
			<!-- wp:post-template {"className":"kh-cards kh-cards--3","layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"14rem"}} -->
				<!-- wp:group {"className":"kh-card kh-card--post","layout":{"type":"default"}} -->
				<div class="wp-block-group kh-card kh-card--post">
					<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3","sizeSlug":"khesht-card","className":"kh-card__media"} /-->
					<!-- wp:group {"className":"kh-card__body","layout":{"type":"default"}} -->
					<div class="wp-block-group kh-card__body">
						<!-- wp:post-title {"level":3,"isLink":true,"className":"kh-card__title"} /-->
						<!-- wp:post-date {"className":"kh-card__date"} /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
			<!-- /wp:post-template -->
			<!-- wp:query-no-results -->
				<!-- wp:paragraph -->
				<p><?php esc_html_e( 'هنوز خبری منتشر نشده است.', 'boshrouyeh-khesht' ); ?></p>
				<!-- /wp:paragraph -->
			<!-- /wp:query-no-results -->
		</div>
		<!-- /wp:query -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"kh-notices-col","layout":{"type":"default"}} -->
	<div class="wp-block-group kh-notices-col">
		<!-- wp:heading {"className":"kh-section-title"} -->
		<h2 class="wp-block-heading kh-section-title"><?php esc_html_e( 'اطلاعیه‌ها', 'boshrouyeh-khesht' ); ?></h2>
		<!-- /wp:heading -->
		<?php if ( defined( 'BMN_CORE_VERSION' ) ) : ?>
		<!-- wp:boshrouyeh/notices {"count":5} /-->
		<?php endif; ?>
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
