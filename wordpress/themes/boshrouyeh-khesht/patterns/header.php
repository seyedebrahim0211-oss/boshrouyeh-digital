<?php
/**
 * Title: سربرگ با نشان افقی
 * Slug: boshrouyeh-khesht/header
 * Categories: khesht-sections
 * Inserter: no
 *
 * @package BoshrouyehKhesht
 */

$kh_logged   = is_user_logged_in();
$kh_dash_url = khesht_page_url( 'page_dashboard', 'citizen' );
?>
<!-- wp:html -->
<a class="kh-skip" href="#kh-main"><?php esc_html_e( 'پرش به محتوای اصلی', 'boshrouyeh-khesht' ); ?></a>
<!-- /wp:html -->

<!-- wp:group {"tagName":"header","className":"kh-header","layout":{"type":"constrained","wideSize":"1200px"}} -->
<header class="wp-block-group kh-header">
	<!-- wp:group {"align":"wide","className":"kh-header__row","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group alignwide kh-header__row">
		<!-- wp:html -->
		<a class="kh-lockup" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php echo khesht_logo( 52 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted theme SVG. ?>
			<span class="kh-lockup__text">
				<span class="kh-lockup__fa"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
				<span class="kh-lockup__en" lang="en">BOSHROUYEH MUNICIPALITY</span>
			</span>
		</a>
		<!-- /wp:html -->

		<!-- wp:navigation {"overlayMenu":"mobile","className":"kh-nav","layout":{"type":"flex","justifyContent":"center"},"ariaLabel":"منوی اصلی"} -->
<?php
$kh_menu = array(
	array( 'خانه', '/' ),
	array( 'درباره بشرویه', '/about/', array( array( 'تاریخچه و معرفی', '/about/' ), array( 'مفاخر', '/about/notables/' ), array( 'نقشهٔ شهر', '/about/map/' ), array( 'آمار و جغرافیا', '/about/stats/' ) ) ),
	array( 'شهرداری', '/municipality/', array( array( 'پیام شهردار', '/municipality/mayor/' ), array( 'ساختار سازمانی', '/municipality/structure/' ), array( 'شورای اسلامی شهر', '/municipality/council/' ), array( 'سازمان آتش‌نشانی', '/municipality/fire/' ) ) ),
	array( 'خدمات الکترونیک', '/services/' ),
	array( 'اخبار', '/news/', array( array( 'اخبار شهرداری', '/news/' ), array( 'اطلاعیه‌ها', '/notices/' ), array( 'تقویم رویدادها', '/events/' ), array( 'مناقصه و مزایده', '/tenders/' ) ) ),
	array( 'گردشگری', '/places/' ),
	array( 'شفافیت', '/transparency/' ),
	array( 'تماس', '/contact/' ),
);
foreach ( $kh_menu as $kh_item ) {
	$kh_url = esc_url( home_url( $kh_item[1] ) );
	if ( empty( $kh_item[2] ) ) {
		echo '<!-- wp:navigation-link {"label":"' . esc_attr( $kh_item[0] ) . '","url":"' . $kh_url . '","kind":"custom"} /-->'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		continue;
	}
	echo '<!-- wp:navigation-submenu {"label":"' . esc_attr( $kh_item[0] ) . '","url":"' . $kh_url . '","kind":"custom"} -->'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	foreach ( $kh_item[2] as $kh_sub ) {
		echo '<!-- wp:navigation-link {"label":"' . esc_attr( $kh_sub[0] ) . '","url":"' . esc_url( home_url( $kh_sub[1] ) ) . '","kind":"custom"} /-->'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '<!-- /wp:navigation-submenu -->';
}
?>
<!-- /wp:navigation -->

		<!-- wp:html -->
		<div class="kh-header__actions">
			<button type="button" class="kh-icon-btn" data-kh-search-open aria-controls="kh-search-panel" aria-expanded="false" aria-label="<?php esc_attr_e( 'جست‌وجو', 'boshrouyeh-khesht' ); ?>">
				<?php echo khesht_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
			<a class="kh-btn kh-btn--action kh-btn--sm" href="<?php echo esc_url( $kh_dash_url ); ?>">
				<?php echo khesht_icon( 'user', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php echo $kh_logged ? esc_html__( 'پیشخوان من', 'boshrouyeh-khesht' ) : esc_html__( 'ورود شهروند', 'boshrouyeh-khesht' ); ?></span>
			</a>
		</div>
		<!-- /wp:html -->
	</div>
	<!-- /wp:group -->
</header>
<!-- /wp:group -->

<!-- wp:html -->
<div class="kh-search-panel" id="kh-search-panel" hidden>
	<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="kh-search-form">
		<label class="screen-reader-text" for="kh-search-input"><?php esc_html_e( 'جست‌وجو در خدمات، اخبار و اطلاعیه‌ها', 'boshrouyeh-khesht' ); ?></label>
		<?php echo khesht_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input id="kh-search-input" type="search" name="s" autocomplete="off" placeholder="<?php esc_attr_e( 'چه خدمتی نیاز دارید؟ مثلاً «عوارض نوسازی»', 'boshrouyeh-khesht' ); ?>" data-kh-suggest>
		<button type="submit" class="kh-btn kh-btn--action"><?php esc_html_e( 'جست‌وجو', 'boshrouyeh-khesht' ); ?></button>
		<button type="button" class="kh-icon-btn" data-kh-search-close aria-label="<?php esc_attr_e( 'بستن', 'boshrouyeh-khesht' ); ?>"><?php echo khesht_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		<ul class="kh-suggest" role="listbox" hidden></ul>
	</form>
</div>
<!-- /wp:html -->
