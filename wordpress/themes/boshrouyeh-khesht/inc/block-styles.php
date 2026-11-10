<?php
/**
 * Brand block styles.
 *
 * @package BoshrouyehKhesht
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'khesht_register_block_styles' );
/**
 * Register block style variations that map to brand surfaces.
 */
function khesht_register_block_styles() {
	$styles = array(
		'core/group'   => array(
			'kh-card'    => __( 'کارت', 'boshrouyeh-khesht' ),
			'kh-khesht'  => __( 'زمینه خشت‌چین', 'boshrouyeh-khesht' ),
			'kh-night'   => __( 'لاجورد با گره', 'boshrouyeh-khesht' ),
			'kh-qanat'   => __( 'فیروزه با گره', 'boshrouyeh-khesht' ),
			'kh-arches'  => __( 'قوس بادگیر', 'boshrouyeh-khesht' ),
			'kh-taqche'  => __( 'طاقچه', 'boshrouyeh-khesht' ),
		),
		'core/button'  => array(
			'kh-outline' => __( 'حاشیه آجری', 'boshrouyeh-khesht' ),
			'kh-sun'     => __( 'خورشیدی', 'boshrouyeh-khesht' ),
		),
		'core/image'   => array(
			'kh-taqche'  => __( 'قاب طاقچه', 'boshrouyeh-khesht' ),
			'kh-rounded' => __( 'گوشه گرد', 'boshrouyeh-khesht' ),
		),
		'core/separator' => array(
			'kh-qanat'   => __( 'رشته‌چاه قنات', 'boshrouyeh-khesht' ),
		),
		'core/list'    => array(
			'kh-diamond' => __( 'لوزی خشتی', 'boshrouyeh-khesht' ),
			'kh-check'   => __( 'تیک', 'boshrouyeh-khesht' ),
		),
		'core/heading' => array(
			'kh-eyebrow' => __( 'برچسب بالای تیتر', 'boshrouyeh-khesht' ),
		),
	);
	foreach ( $styles as $block => $variations ) {
		foreach ( $variations as $name => $label ) {
			register_block_style(
				$block,
				array(
					'name'  => $name,
					'label' => $label,
				)
			);
		}
	}
}
