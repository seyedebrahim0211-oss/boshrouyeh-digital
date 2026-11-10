<?php
/**
 * Title: چهار عنصر: خشت، باد، آب، گره
 * Slug: boshrouyeh-khesht/concept-cards
 * Categories: khesht-sections
 *
 * @package BoshrouyehKhesht
 */

$kh_cards = array(
	array( 'khesht', 'خشت', 'ماده', 'بافت گرم کاهگل، رنگ‌های خاکی و الگوی خشت‌چینی؛ آرام، صادق و بومی.', 'building' ),
	array( 'brick', 'باد', 'فرم', 'بادگیر سه‌گانهٔ خانهٔ مستوفی؛ قوس شکاف بادگیر، فرم قاب آیکن‌ها و دکمه‌ها.', 'badgir' ),
	array( 'qanat', 'آب', 'کنش', 'قنات و آسیاب‌های آبی؛ فیروزه‌ای تنها رنگ «اقدام» است.', 'drop' ),
	array( 'sun', 'گره', 'نظم', 'ستارهٔ هشت‌پر نشان، گره‌چینی کم‌رنگ در پس‌زمینهٔ سربرگ‌ها.', 'star8' ),
);
?>
<!-- wp:html -->
<div class="kh-concepts alignwide">
	<?php foreach ( $kh_cards as $kh_c ) : ?>
		<article class="kh-concept kh-concept--<?php echo esc_attr( $kh_c[0] ); ?>">
			<span class="kh-taqche"><?php echo khesht_icon( $kh_c[4] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<h3><?php echo esc_html( $kh_c[1] ); ?> <span class="kh-chip"><?php echo esc_html( $kh_c[2] ); ?></span></h3>
			<p><?php echo esc_html( $kh_c[3] ); ?></p>
		</article>
	<?php endforeach; ?>
</div>
<!-- /wp:html -->
