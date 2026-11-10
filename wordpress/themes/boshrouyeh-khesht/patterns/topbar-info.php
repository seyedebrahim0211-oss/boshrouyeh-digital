<?php
/**
 * Title: نوار بالایی (تاریخ، ساعات کاری، تماس اضطراری)
 * Slug: boshrouyeh-khesht/topbar-info
 * Categories: khesht-sections
 * Inserter: no
 *
 * @package BoshrouyehKhesht
 */

$kh_hours = khesht_opt( 'work_hours', 'ساعت کاری: ۷:۳۰ تا ۱۴:۳۰' );
$kh_en    = khesht_opt( 'english_url', '' );
?>
<!-- wp:html -->
<div class="kh-topbar" role="region" aria-label="<?php esc_attr_e( 'اطلاعات تماس سریع', 'boshrouyeh-khesht' ); ?>">
	<div class="kh-topbar__inner">
		<p class="kh-topbar__meta">
			<span><?php echo khesht_icon( 'calendar', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( khesht_today() ); ?></span>
			<span class="kh-hide-sm"><?php echo khesht_icon( 'clock', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $kh_hours ); ?></span>
		</p>
		<p class="kh-topbar__links">
			<a href="tel:125"><?php echo khesht_icon( 'fire', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'آتش‌نشانی ۱۲۵', 'boshrouyeh-khesht' ); ?></a>
			<a href="tel:137"><?php echo khesht_icon( '137', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'سامانه ۱۳۷', 'boshrouyeh-khesht' ); ?></a>
			<?php if ( $kh_en ) : ?>
				<a href="<?php echo esc_url( $kh_en ); ?>" lang="en" hreflang="en">English</a>
			<?php endif; ?>
			<span class="kh-a11y" role="group" aria-label="<?php esc_attr_e( 'تنظیمات نمایش', 'boshrouyeh-khesht' ); ?>">
				<button type="button" class="kh-a11y__btn" data-kh-font="down" aria-label="<?php esc_attr_e( 'کوچک کردن متن', 'boshrouyeh-khesht' ); ?>">ا-</button>
				<button type="button" class="kh-a11y__btn" data-kh-font="up" aria-label="<?php esc_attr_e( 'بزرگ کردن متن', 'boshrouyeh-khesht' ); ?>">ا+</button>
				<button type="button" class="kh-a11y__btn" data-kh-theme-toggle aria-pressed="false" aria-label="<?php esc_attr_e( 'حالت شب کویر', 'boshrouyeh-khesht' ); ?>"><?php echo khesht_icon( 'moon', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			</span>
		</p>
	</div>
</div>
<!-- /wp:html -->
