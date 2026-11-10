<?php
/**
 * Structured data (GovernmentOrganization, GovernmentService, TouristAttraction) and
 * fallback meta tags when no SEO plugin is active.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO.
 */
final class BMN_SEO {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'schema' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'meta' ), 2 );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'sitemap_types' ) );
	}

	/**
	 * Is a dedicated SEO plugin handling meta?
	 *
	 * @return bool
	 */
	private static function has_seo_plugin() {
		return defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'AIOSEO_VERSION' );
	}

	/**
	 * Description for current view.
	 *
	 * @return string
	 */
	private static function description() {
		if ( is_singular() ) {
			$p = get_queried_object();
			$d = has_excerpt( $p ) ? get_the_excerpt( $p ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $p->post_content ) ), 30, '…' );
		} elseif ( is_tax() || is_category() ) {
			$d = term_description();
		} else {
			$d = get_bloginfo( 'description' );
		}
		return trim( wp_strip_all_tags( (string) $d ) );
	}

	/**
	 * Fallback description / Open Graph tags.
	 */
	public static function meta() {
		if ( self::has_seo_plugin() || is_admin() ) {
			return;
		}
		$desc  = self::description();
		$title = wp_get_document_title();
		$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
		$img   = is_singular() && has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'khesht-hero' ) : BMN_CORE_URL . 'assets/img/og-default.png';
		if ( $desc ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_html_excerpt( $desc, 160, '…' ) ) );
		}
		printf( '<meta property="og:locale" content="fa_IR">' . "\n" );
		printf( '<meta property="og:type" content="%s">' . "\n", is_singular( 'post' ) ? 'article' : 'website' );
		printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
		printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		if ( $desc ) {
			printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( wp_html_excerpt( $desc, 200, '…' ) ) );
		}
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( (string) $img ) );
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	}

	/**
	 * Organisation / service / attraction JSON-LD.
	 */
	public static function schema() {
		$org = array(
			'@type'     => 'GovernmentOrganization',
			'@id'       => home_url( '/#organization' ),
			'name'      => bmn_option( 'org_name', get_bloginfo( 'name' ) ),
			'alternateName' => 'Boshrouyeh Municipality',
			'url'       => home_url( '/' ),
			'logo'      => BMN_CORE_URL . 'assets/img/icon-512.png',
			'telephone' => (string) bmn_option( 'phone' ),
			'email'     => (string) bmn_option( 'email' ),
			'address'   => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => (string) bmn_option( 'address' ),
				'postalCode'      => (string) bmn_option( 'postal_code' ),
				'addressLocality' => 'بشرویه',
				'addressRegion'   => 'خراسان جنوبی',
				'addressCountry'  => 'IR',
			),
			'sameAs'    => array_values( array_filter( array( bmn_option( 'instagram' ), bmn_option( 'telegram' ), bmn_option( 'eitaa' ), bmn_option( 'bale' ), bmn_option( 'aparat' ) ) ) ),
		);
		$graph = array();
		if ( is_front_page() ) {
			$graph[] = array_filter( $org );
			$graph[] = array(
				'@type'           => 'WebSite',
				'@id'             => home_url( '/#website' ),
				'url'             => home_url( '/' ),
				'name'            => get_bloginfo( 'name' ),
				'inLanguage'      => 'fa-IR',
				'publisher'       => array( '@id' => home_url( '/#organization' ) ),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => home_url( '/?s={search_term_string}' ),
					'query-input' => 'required name=search_term_string',
				),
			);
		} elseif ( is_singular( 'bmn_service' ) ) {
			$p       = get_queried_object();
			$graph[] = array(
				'@type'            => 'GovernmentService',
				'name'             => wp_strip_all_tags( get_the_title( $p ) ),
				'description'      => self::description(),
				'url'              => get_permalink( $p ),
				'serviceType'      => wp_strip_all_tags( get_the_title( $p ) ),
				'provider'         => array( '@id' => home_url( '/#organization' ) ),
				'areaServed'       => array( '@type' => 'City', 'name' => 'بشرویه' ),
				'availableChannel' => array(
					'@type'      => 'ServiceChannel',
					'serviceUrl' => get_permalink( $p ),
				),
			);
		} elseif ( is_singular( 'bmn_place' ) ) {
			$p       = get_queried_object();
			$lat     = (float) bmn_meta( $p->ID, 'lat', 0 );
			$lng     = (float) bmn_meta( $p->ID, 'lng', 0 );
			$graph[] = array_filter(
				array(
					'@type'       => 'TouristAttraction',
					'name'        => wp_strip_all_tags( get_the_title( $p ) ),
					'description' => self::description(),
					'url'         => get_permalink( $p ),
					'image'       => get_the_post_thumbnail_url( $p, 'khesht-hero' ),
					'geo'         => ( $lat && $lng ) ? array( '@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng ) : null,
					'isAccessibleForFree' => true,
				)
			);
		} elseif ( is_singular( 'post' ) ) {
			$p       = get_queried_object();
			$graph[] = array_filter(
				array(
					'@type'         => 'NewsArticle',
					'headline'      => wp_strip_all_tags( get_the_title( $p ) ),
					'datePublished' => get_post_time( 'c', true, $p ),
					'dateModified'  => get_post_modified_time( 'c', true, $p ),
					'image'         => get_the_post_thumbnail_url( $p, 'khesht-hero' ),
					'mainEntityOfPage' => get_permalink( $p ),
					'publisher'     => array( '@id' => home_url( '/#organization' ) ),
					'inLanguage'    => 'fa-IR',
				)
			);
			$graph[] = array_filter( $org );
		}
		if ( ! $graph ) {
			return;
		}
		echo bmn_json_ld( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG.
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			)
		);
	}

	/**
	 * Keep private CPTs out of the core sitemap.
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public static function sitemap_types( $types ) {
		unset( $types['bmn_deceased'] );
		return $types;
	}
}
