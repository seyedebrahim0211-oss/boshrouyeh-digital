<?php
/**
 * Jalali (Solar Hijri) calendar — conversion, formatting and WordPress date filters.
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Jalali helper.
 */
final class BMN_Jalali {

	const MONTHS = array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
	// PHP date('w'): 0 = Sunday.
	const DAYS = array( 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' );

	/**
	 * Hooks.
	 */
	public static function init() {
		// Respect WP-Parsidate if the site owner prefers it.
		if ( class_exists( 'WP_Parsidate' ) || ! apply_filters( 'bmn_jalali_filters', true ) ) {
			return;
		}
		add_filter( 'get_the_date', array( __CLASS__, 'filter_post_date' ), 20, 3 );
		add_filter( 'get_the_modified_date', array( __CLASS__, 'filter_modified_date' ), 20, 3 );
		add_filter( 'get_comment_date', array( __CLASS__, 'filter_comment_date' ), 20, 3 );
		add_filter( 'render_block_core/post-date', array( __CLASS__, 'filter_date_block' ), 20, 2 );
		add_filter( 'render_block_core/comment-date', array( __CLASS__, 'filter_date_block' ), 20, 2 );
	}

	/**
	 * Post-date / comment-date blocks: rewrite the visible text from the machine datetime.
	 *
	 * @param string $html  Block HTML.
	 * @param array  $block Block.
	 * @return string
	 */
	public static function filter_date_block( $html, $block ) {
		$format = ! empty( $block['attrs']['format'] ) ? (string) $block['attrs']['format'] : 'j F Y';
		if ( 'human-diff' === $format ) {
			return $html;
		}
		return (string) preg_replace_callback(
			'#(<time[^>]*datetime="([^"]+)"[^>]*>)(.*?)(</time>)#s',
			static function ( $m ) use ( $format ) {
				$ts = strtotime( $m[2] );
				if ( ! $ts ) {
					return $m[0];
				}
				$inner = $m[3];
				$text  = esc_html( self::format( $format, $ts ) );
				// Keep a wrapping link if present.
				if ( preg_match( '#^(<a [^>]+>).*(</a>)$#s', $inner, $a ) ) {
					return $m[1] . $a[1] . $text . $a[2] . $m[4];
				}
				return $m[1] . $text . $m[4];
			},
			$html
		);
	}

	/**
	 * Gregorian → Jalali.
	 *
	 * @param int $gy Year.
	 * @param int $gm Month.
	 * @param int $gd Day.
	 * @return int[] [y, m, d]
	 */
	public static function to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;
		$jy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return array( $jy, $jm, $jd );
	}

	/**
	 * Jalali → Gregorian.
	 *
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @param int $jd Day.
	 * @return int[] [y, m, d]
	 */
	public static function to_gregorian( $jy, $jm, $jd ) {
		$jy  += 1595;
		$days = -355668 + ( 365 * $jy ) + ( intdiv( $jy, 33 ) * 8 ) + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd + ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
		$gy   = 400 * intdiv( $days, 146097 );
		$days %= 146097;
		if ( $days > 36524 ) {
			--$days;
			$gy  += 100 * intdiv( $days, 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				++$days;
			}
		}
		$gy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$gy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}
		$gd    = $days + 1;
		$leap  = ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400;
		$month = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		for ( $gm = 0; $gm < 13 && $gd > $month[ $gm ]; $gm++ ) {
			$gd -= $month[ $gm ];
		}
		return array( $gy, $gm, $gd );
	}

	/**
	 * Format a timestamp in Jalali.
	 *
	 * Supported tokens: Y y m n d j F M l D w N H G h g i s A a t; backslash escapes.
	 *
	 * @param string   $format  Format.
	 * @param int|null $ts      Unix timestamp (UTC). Null = now.
	 * @param bool     $fa      Persian digits.
	 * @return string
	 */
	public static function format( $format, $ts = null, $fa = true ) {
		$ts = null === $ts ? time() : (int) $ts;
		$dt = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( wp_timezone() );
		list( $jy, $jm, $jd ) = self::to_jalali( (int) $dt->format( 'Y' ), (int) $dt->format( 'n' ), (int) $dt->format( 'j' ) );
		$w   = (int) $dt->format( 'w' );
		$out = '';
		$len = strlen( $format );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $format[ $i ];
			if ( '\\' === $c && $i + 1 < $len ) {
				$out .= $format[ ++$i ];
				continue;
			}
			switch ( $c ) {
				case 'Y':
					$out .= $jy;
					break;
				case 'y':
					$out .= substr( (string) $jy, -2 );
					break;
				case 'm':
					$out .= str_pad( (string) $jm, 2, '0', STR_PAD_LEFT );
					break;
				case 'n':
					$out .= $jm;
					break;
				case 'd':
					$out .= str_pad( (string) $jd, 2, '0', STR_PAD_LEFT );
					break;
				case 'j':
					$out .= $jd;
					break;
				case 'F':
				case 'M':
					$out .= self::MONTHS[ $jm - 1 ];
					break;
				case 'l':
				case 'D':
					$out .= self::DAYS[ $w ];
					break;
				case 'N':
					$out .= ( ( $w + 1 ) % 7 ) + 1; // Saturday = 1.
					break;
				case 't':
					$out .= self::month_length( $jy, $jm );
					break;
				case 'A':
					$out .= (int) $dt->format( 'G' ) < 12 ? 'ق.ظ' : 'ب.ظ';
					break;
				case 'a':
					$out .= (int) $dt->format( 'G' ) < 12 ? 'صبح' : 'عصر';
					break;
				case 'H':
				case 'G':
				case 'h':
				case 'g':
				case 'i':
				case 's':
				case 'w':
					$out .= $dt->format( $c );
					break;
				default:
					$out .= $c;
			}
		}
		return $fa ? bmn_fa_digits( $out ) : $out;
	}

	/**
	 * Days in a Jalali month.
	 *
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @return int
	 */
	public static function month_length( $jy, $jm ) {
		if ( $jm <= 6 ) {
			return 31;
		}
		if ( $jm <= 11 ) {
			return 30;
		}
		list( $gy, $gm, $gd ) = self::to_gregorian( $jy + 1, 1, 1 );
		$next = gmmktime( 0, 0, 0, $gm, $gd, $gy );
		list( $gy, $gm, $gd ) = self::to_gregorian( $jy, 12, 1 );
		$first = gmmktime( 0, 0, 0, $gm, $gd, $gy );
		return (int) round( ( $next - $first ) / DAY_IN_SECONDS );
	}

	/**
	 * Parse "1405/08/19" or "1405-8-19 14:30" (Persian digits OK) → UTC timestamp, or 0.
	 *
	 * @param string $text Input.
	 * @return int
	 */
	public static function parse( $text ) {
		$text = trim( bmn_en_digits( (string) $text ) );
		if ( ! preg_match( '#^(1[3-4]\d\d)[/\-.](\d{1,2})[/\-.](\d{1,2})(?:\s+(\d{1,2}):(\d{2}))?$#', $text, $m ) ) {
			return 0;
		}
		$jy = (int) $m[1];
		$jm = (int) $m[2];
		$jd = (int) $m[3];
		if ( $jm < 1 || $jm > 12 || $jd < 1 || $jd > self::month_length( $jy, $jm ) ) {
			return 0;
		}
		$h   = isset( $m[4] ) ? min( 23, (int) $m[4] ) : 0;
		$min = isset( $m[5] ) ? min( 59, (int) $m[5] ) : 0;
		list( $gy, $gm, $gd ) = self::to_gregorian( $jy, $jm, $jd );
		try {
			$dt = new DateTimeImmutable( sprintf( '%04d-%02d-%02d %02d:%02d:00', $gy, $gm, $gd, $h, $min ), wp_timezone() );
		} catch ( Exception $e ) {
			return 0;
		}
		return $dt->getTimestamp();
	}

	/**
	 * Filter: post date.
	 *
	 * @param string           $date   Formatted date.
	 * @param string           $format Format.
	 * @param int|WP_Post|null $post   Post.
	 * @return string
	 */
	public static function filter_post_date( $date, $format, $post ) {
		$post = get_post( $post );
		if ( ! $post || self::is_machine_format( $format ) ) {
			return $date;
		}
		return self::format( $format ? $format : get_option( 'date_format' ), (int) get_post_timestamp( $post ) );
	}

	/**
	 * Filter: modified date.
	 *
	 * @param string           $date   Date.
	 * @param string           $format Format.
	 * @param int|WP_Post|null $post   Post.
	 * @return string
	 */
	public static function filter_modified_date( $date, $format, $post ) {
		$post = get_post( $post );
		if ( ! $post || self::is_machine_format( $format ) ) {
			return $date;
		}
		return self::format( $format ? $format : get_option( 'date_format' ), (int) get_post_timestamp( $post, 'modified' ) );
	}

	/**
	 * Filter: comment date.
	 *
	 * @param string     $date    Date.
	 * @param string     $format  Format.
	 * @param WP_Comment $comment Comment.
	 * @return string
	 */
	public static function filter_comment_date( $date, $format, $comment ) {
		if ( self::is_machine_format( $format ) ) {
			return $date;
		}
		return self::format( $format ? $format : get_option( 'date_format' ), strtotime( $comment->comment_date_gmt . ' UTC' ) );
	}

	/**
	 * Machine formats (datetime attributes, feeds) must stay Gregorian.
	 *
	 * @param string $format Format.
	 * @return bool
	 */
	private static function is_machine_format( $format ) {
		return in_array( $format, array( 'c', 'U', 'r', DATE_W3C, DATE_ATOM, DATE_RFC2822, 'Y-m-d H:i:s', 'G' ), true ) || is_feed();
	}
}

/**
 * Template helper: Jalali date.
 *
 * @param string   $format Format.
 * @param int|null $ts     Timestamp.
 * @return string
 */
function bmn_jdate( $format, $ts = null ) {
	return BMN_Jalali::format( $format, $ts );
}
