<?php
/**
 * Lightweight, schema-driven custom fields (no ACF dependency).
 * Every field is registered with register_post_meta (typed, sanitized, REST-visible).
 *
 * @package BoshrouyehCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fields.
 */
final class BMN_Fields {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'bmn_neighborhood_edit_form_fields', array( __CLASS__, 'term_fields' ) );
		add_action( 'bmn_neighborhood_add_form_fields', array( __CLASS__, 'term_fields_add' ) );
		add_action( 'edited_bmn_neighborhood', array( __CLASS__, 'save_term' ) );
		add_action( 'created_bmn_neighborhood', array( __CLASS__, 'save_term' ) );
	}

	/**
	 * Field schema per post type.
	 *
	 * @return array<string,array>
	 */
	public static function schema() {
		$icons = array_combine( bmn_icon_names(), bmn_icon_names() );
		$schema = array(
			'bmn_service'      => array(
				'title'  => __( 'مشخصات خدمت', 'boshrouyeh-core' ),
				'fields' => array(
					'icon'         => array( 'type' => 'select', 'label' => __( 'آیکن', 'boshrouyeh-core' ), 'options' => $icons, 'default' => 'grid' ),
					'mode'         => array( 'type' => 'select', 'label' => __( 'نحوهٔ ارائه', 'boshrouyeh-core' ), 'options' => bmn_service_modes(), 'default' => 'online' ),
					'duration'     => array( 'type' => 'text', 'label' => __( 'زمان انجام', 'boshrouyeh-core' ), 'help' => __( 'مثلاً: کمتر از ۳ دقیقه، ۵ روز کاری', 'boshrouyeh-core' ) ),
					'cost'         => array( 'type' => 'text', 'label' => __( 'هزینه', 'boshrouyeh-core' ) ),
					'unit'         => array( 'type' => 'text', 'label' => __( 'واحد مسئول', 'boshrouyeh-core' ) ),
					'steps'        => array( 'type' => 'lines', 'label' => __( 'مراحل (هر خط یک مرحله)', 'boshrouyeh-core' ) ),
					'documents'    => array( 'type' => 'lines', 'label' => __( 'مدارک لازم (هر خط یک مورد)', 'boshrouyeh-core' ) ),
					'faq'          => array( 'type' => 'lines', 'label' => __( 'پرسش‌های پرتکرار', 'boshrouyeh-core' ), 'help' => __( 'هر خط: پرسش | پاسخ', 'boshrouyeh-core' ) ),
					'eservice'     => array( 'type' => 'select', 'label' => __( 'اتصال به خدمت الکترونیک داخلی', 'boshrouyeh-core' ), 'options' => self::eservice_options(), 'default' => '' ),
					'external_url' => array( 'type' => 'url', 'label' => __( 'پیوند سامانهٔ ملی/استانی', 'boshrouyeh-core' ), 'help' => __( 'مثلاً سامانهٔ پروانهٔ ساختمانی؛ با توضیح مراحل نمایش داده می‌شود.', 'boshrouyeh-core' ) ),
					'legal_basis'  => array( 'type' => 'text', 'label' => __( 'مبنای قانونی/مصوبه', 'boshrouyeh-core' ) ),
					'featured'     => array( 'type' => 'checkbox', 'label' => __( 'نمایش در میانبر خدمات صفحهٔ اصلی', 'boshrouyeh-core' ) ),
					'popular'      => array( 'type' => 'checkbox', 'label' => __( 'پرمراجعه (فاز نخست)', 'boshrouyeh-core' ) ),
					'short_title'  => array( 'type' => 'text', 'label' => __( 'عنوان کوتاه (میانبر/اپ)', 'boshrouyeh-core' ) ),
				),
			),
			'bmn_notice'       => array(
				'title'  => __( 'تنظیمات اطلاعیه', 'boshrouyeh-core' ),
				'fields' => array(
					'urgency' => array( 'type' => 'select', 'label' => __( 'فوریت', 'boshrouyeh-core' ), 'options' => array( 'normal' => __( 'عادی', 'boshrouyeh-core' ), 'urgent' => __( 'فوری', 'boshrouyeh-core' ) ), 'default' => 'normal' ),
					'expires' => array( 'type' => 'jdate', 'label' => __( 'تاریخ انقضا', 'boshrouyeh-core' ), 'help' => __( 'پس از این تاریخ از فهرست‌ها حذف می‌شود.', 'boshrouyeh-core' ) ),
					'push'    => array( 'type' => 'checkbox', 'label' => __( 'ارسال اعلان به اپ (محلهٔ انتخاب‌شده)', 'boshrouyeh-core' ) ),
				),
			),
			'bmn_tender'       => array(
				'title'  => __( 'مشخصات مناقصه/مزایده', 'boshrouyeh-core' ),
				'fields' => array(
					'kind'      => array( 'type' => 'select', 'label' => __( 'نوع', 'boshrouyeh-core' ), 'options' => array( 'tender' => __( 'مناقصه', 'boshrouyeh-core' ), 'auction' => __( 'مزایده', 'boshrouyeh-core' ), 'inquiry' => __( 'استعلام بها', 'boshrouyeh-core' ) ), 'default' => 'tender' ),
					'number'    => array( 'type' => 'text', 'label' => __( 'شماره فراخوان', 'boshrouyeh-core' ) ),
					'deadline'  => array( 'type' => 'jdate', 'label' => __( 'مهلت ارسال پیشنهاد', 'boshrouyeh-core' ), 'time' => true ),
					'opening'   => array( 'type' => 'jdate', 'label' => __( 'تاریخ بازگشایی پاکات', 'boshrouyeh-core' ), 'time' => true ),
					'guarantee' => array( 'type' => 'text', 'label' => __( 'تضمین شرکت (تومان)', 'boshrouyeh-core' ) ),
					'setadiran' => array( 'type' => 'url', 'label' => __( 'پیوند سامانهٔ ستاد', 'boshrouyeh-core' ) ),
					'files'     => array( 'type' => 'lines', 'label' => __( 'اسناد (هر خط: عنوان | نشانی فایل)', 'boshrouyeh-core' ) ),
					'status'    => array( 'type' => 'select', 'label' => __( 'وضعیت', 'boshrouyeh-core' ), 'options' => array( 'open' => __( 'در جریان', 'boshrouyeh-core' ), 'evaluating' => __( 'در حال ارزیابی', 'boshrouyeh-core' ), 'closed' => __( 'پایان‌یافته', 'boshrouyeh-core' ), 'canceled' => __( 'لغو شده', 'boshrouyeh-core' ) ), 'default' => 'open' ),
					'winner'    => array( 'type' => 'text', 'label' => __( 'برنده', 'boshrouyeh-core' ) ),
				),
			),
			'bmn_place'        => array(
				'title'  => __( 'مشخصات جاذبه', 'boshrouyeh-core' ),
				'fields' => array(
					'era'      => array( 'type' => 'text', 'label' => __( 'دوره/قدمت', 'boshrouyeh-core' ) ),
					'tags'     => array( 'type' => 'text', 'label' => __( 'برچسب‌ها (با ویرگول)', 'boshrouyeh-core' ) ),
					'hours'    => array( 'type' => 'text', 'label' => __( 'ساعت بازدید', 'boshrouyeh-core' ) ),
					'ticket'   => array( 'type' => 'text', 'label' => __( 'بلیت', 'boshrouyeh-core' ) ),
					'address'  => array( 'type' => 'text', 'label' => __( 'نشانی', 'boshrouyeh-core' ) ),
					'lat'      => array( 'type' => 'number', 'label' => __( 'عرض جغرافیایی', 'boshrouyeh-core' ), 'step' => 'any' ),
					'lng'      => array( 'type' => 'number', 'label' => __( 'طول جغرافیایی', 'boshrouyeh-core' ), 'step' => 'any' ),
					'audio'    => array( 'type' => 'media', 'label' => __( 'راهنمای صوتی (فارسی)', 'boshrouyeh-core' ), 'mime' => 'audio' ),
					'audio_en' => array( 'type' => 'media', 'label' => __( 'Audio guide (English)', 'boshrouyeh-core' ), 'mime' => 'audio' ),
				),
			),
			'bmn_event'        => array(
				'title'  => __( 'مشخصات رویداد', 'boshrouyeh-core' ),
				'fields' => array(
					'start'    => array( 'type' => 'jdate', 'label' => __( 'شروع', 'boshrouyeh-core' ), 'time' => true ),
					'end'      => array( 'type' => 'jdate', 'label' => __( 'پایان', 'boshrouyeh-core' ), 'time' => true ),
					'venue'    => array( 'type' => 'text', 'label' => __( 'مکان', 'boshrouyeh-core' ) ),
					'capacity' => array( 'type' => 'number', 'label' => __( 'ظرفیت', 'boshrouyeh-core' ) ),
					'register' => array( 'type' => 'url', 'label' => __( 'پیوند ثبت‌نام', 'boshrouyeh-core' ) ),
				),
			),
			'bmn_deceased'     => array(
				'title'  => __( 'اطلاعات متوفی', 'boshrouyeh-core' ),
				'fields' => array(
					'father'    => array( 'type' => 'text', 'label' => __( 'نام پدر', 'boshrouyeh-core' ) ),
					'death'     => array( 'type' => 'text', 'label' => __( 'تاریخ وفات (شمسی)', 'boshrouyeh-core' ) ),
					'cemetery'  => array( 'type' => 'text', 'label' => __( 'آرامستان', 'boshrouyeh-core' ) ),
					'plot'      => array( 'type' => 'text', 'label' => __( 'قطعه / ردیف / شماره', 'boshrouyeh-core' ) ),
					'ceremony'  => array( 'type' => 'textarea', 'label' => __( 'آگهی ترحیم/مراسم (اختیاری)', 'boshrouyeh-core' ) ),
				),
			),
			'bmn_transparency' => array(
				'title'  => __( 'مشخصات سند', 'boshrouyeh-core' ),
				'fields' => array(
					'file'    => array( 'type' => 'media', 'label' => __( 'فایل سند', 'boshrouyeh-core' ), 'mime' => 'application' ),
					'year'    => array( 'type' => 'text', 'label' => __( 'سال', 'boshrouyeh-core' ) ),
					'amount'  => array( 'type' => 'text', 'label' => __( 'مبلغ (تومان)', 'boshrouyeh-core' ) ),
					'party'   => array( 'type' => 'text', 'label' => __( 'طرف قرارداد/جلسه', 'boshrouyeh-core' ) ),
					'doc_no'  => array( 'type' => 'text', 'label' => __( 'شماره سند/مصوبه', 'boshrouyeh-core' ) ),
				),
			),
		);
		/**
		 * Filter the field schema.
		 *
		 * @param array $schema Schema.
		 */
		return apply_filters( 'bmn_fields_schema', $schema );
	}

	/**
	 * Built-in e-service targets (fulfilled by the E-Services plugin).
	 *
	 * @return array<string,string>
	 */
	public static function eservice_options() {
		return apply_filters(
			'bmn_eservice_options',
			array(
				''              => __( '— ندارد —', 'boshrouyeh-core' ),
				'dues_renovation' => __( 'عوارض نوسازی و سطح شهر', 'boshrouyeh-core' ),
				'dues_vehicle'  => __( 'عوارض سالانه خودرو', 'boshrouyeh-core' ),
				'dues_business' => __( 'عوارض کسب و پیشه', 'boshrouyeh-core' ),
				'report137'     => __( 'سامانه ۱۳۷', 'boshrouyeh-core' ),
				'tracking'      => __( 'پیگیری درخواست', 'boshrouyeh-core' ),
				'request'       => __( 'ثبت درخواست عمومی', 'boshrouyeh-core' ),
				'appointment'   => __( 'نوبت ملاقات با شهردار', 'boshrouyeh-core' ),
				'deceased'      => __( 'جست‌وجوی متوفیان', 'boshrouyeh-core' ),
				'waste'         => __( 'زمان‌بندی جمع‌آوری پسماند', 'boshrouyeh-core' ),
				'feedback'      => __( 'انتقاد و پیشنهاد', 'boshrouyeh-core' ),
			)
		);
	}

	/**
	 * Register post meta.
	 */
	public static function register_meta() {
		foreach ( self::schema() as $post_type => $box ) {
			foreach ( $box['fields'] as $key => $field ) {
				$type = in_array( $field['type'], array( 'number' ), true ) ? 'number' : ( in_array( $field['type'], array( 'checkbox' ), true ) ? 'boolean' : ( in_array( $field['type'], array( 'jdate', 'media' ), true ) ? 'integer' : 'string' ) );
				register_post_meta(
					$post_type,
					'_bmn_' . $key,
					array(
						'type'              => $type,
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => function ( $value ) use ( $field ) {
							return BMN_Fields::sanitize( $value, $field );
						},
						'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}
		register_term_meta( 'bmn_neighborhood', '_bmn_waste_days', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_days' ) ) );
		register_term_meta( 'bmn_neighborhood', '_bmn_waste_time', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field' ) );
		register_term_meta( 'bmn_neighborhood', '_bmn_recycle_days', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_days' ) ) );
	}

	/**
	 * Sanitize by field type.
	 *
	 * @param mixed $value Value.
	 * @param array $field Field definition.
	 * @return mixed
	 */
	public static function sanitize( $value, $field ) {
		switch ( $field['type'] ) {
			case 'textarea':
			case 'lines':
				return sanitize_textarea_field( (string) $value );
			case 'url':
				return esc_url_raw( (string) $value, array( 'http', 'https' ) );
			case 'number':
				$value = bmn_en_digits( (string) $value );
				return is_numeric( $value ) ? (float) $value : 0;
			case 'checkbox':
				return (bool) $value;
			case 'select':
				$value = (string) $value;
				return array_key_exists( $value, $field['options'] ) ? $value : (string) ( $field['default'] ?? '' );
			case 'jdate':
				if ( is_numeric( $value ) ) {
					return max( 0, (int) $value );
				}
				return BMN_Jalali::parse( (string) $value );
			case 'media':
				return absint( $value );
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Sanitize comma list of weekday indexes 0..6.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_days( $value ) {
		$days = array_filter( array_map( 'absint', (array) ( is_array( $value ) ? $value : explode( ',', (string) $value ) ) ), fn( $d ) => $d <= 6 );
		return implode( ',', array_unique( $days ) );
	}

	/**
	 * Meta boxes.
	 */
	public static function add_boxes() {
		foreach ( self::schema() as $post_type => $box ) {
			add_meta_box( 'bmn_fields_' . $post_type, $box['title'], array( __CLASS__, 'render_box' ), $post_type, 'normal', 'high', array( 'fields' => $box['fields'] ) );
		}
	}

	/**
	 * Render a metabox.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $box  Box args.
	 */
	public static function render_box( $post, $box ) {
		wp_nonce_field( 'bmn_fields_save', 'bmn_fields_nonce' );
		echo '<div class="bmn-fields">';
		foreach ( $box['args']['fields'] as $key => $field ) {
			$id    = 'bmn_' . $key;
			$name  = 'bmn_fields[' . $key . ']';
			$value = get_post_meta( $post->ID, '_bmn_' . $key, true );
			if ( '' === $value && isset( $field['default'] ) ) {
				$value = $field['default'];
			}
			$wide = in_array( $field['type'], array( 'lines', 'textarea' ), true ) ? ' bmn-field--wide' : '';
			echo '<div class="bmn-field' . esc_attr( $wide ) . '">';
			if ( 'checkbox' !== $field['type'] ) {
				printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $field['label'] ) );
			}
			switch ( $field['type'] ) {
				case 'textarea':
				case 'lines':
					printf( '<textarea id="%s" name="%s" rows="5">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
					break;
				case 'select':
					printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
					foreach ( $field['options'] as $opt => $label ) {
						printf( '<option value="%s" %s>%s</option>', esc_attr( $opt ), selected( (string) $value, (string) $opt, false ), esc_html( $label ) );
					}
					echo '</select>';
					if ( 'icon' === $key ) {
						echo '<span class="bmn-icon-preview" aria-hidden="true">' . bmn_icon( (string) $value ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					break;
				case 'checkbox':
					printf( '<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $value, true, false ), esc_html( $field['label'] ) );
					break;
				case 'jdate':
					$display = $value ? BMN_Jalali::format( empty( $field['time'] ) ? 'Y/m/d' : 'Y/m/d H:i', (int) $value, false ) : '';
					printf( '<input type="text" id="%s" name="%s" value="%s" placeholder="%s" class="bmn-ltr" inputmode="numeric">', esc_attr( $id ), esc_attr( $name ), esc_attr( $display ), esc_attr( empty( $field['time'] ) ? '1405/08/19' : '1405/08/19 14:30' ) );
					break;
				case 'media':
					$url = $value ? wp_get_attachment_url( (int) $value ) : '';
					printf(
						'<span class="bmn-media"><input type="hidden" id="%1$s" name="%2$s" value="%3$s"><button type="button" class="button bmn-media__pick" data-mime="%4$s">%5$s</button> <button type="button" class="button-link bmn-media__clear">%6$s</button> <span class="bmn-media__name">%7$s</span></span>',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( (string) $value ),
						esc_attr( $field['mime'] ?? '' ),
						esc_html__( 'انتخاب فایل', 'boshrouyeh-core' ),
						esc_html__( 'حذف', 'boshrouyeh-core' ),
						esc_html( $url ? wp_basename( $url ) : '' )
					);
					break;
				case 'number':
					printf( '<input type="number" id="%s" name="%s" value="%s" step="%s" class="bmn-ltr">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), esc_attr( $field['step'] ?? '1' ) );
					break;
				case 'url':
					printf( '<input type="url" id="%s" name="%s" value="%s" class="bmn-ltr">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
					break;
				default:
					printf( '<input type="text" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
			}
			if ( ! empty( $field['help'] ) ) {
				printf( '<p class="description">%s</p>', esc_html( $field['help'] ) );
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Save metabox values.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['bmn_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmn_fields_nonce'] ) ), 'bmn_fields_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$schema = self::schema();
		if ( empty( $schema[ $post->post_type ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$input = isset( $_POST['bmn_fields'] ) && is_array( $_POST['bmn_fields'] ) ? wp_unslash( $_POST['bmn_fields'] ) : array();
		foreach ( $schema[ $post->post_type ]['fields'] as $key => $field ) {
			$raw = $input[ $key ] ?? ( 'checkbox' === $field['type'] ? '' : null );
			if ( null === $raw ) {
				continue;
			}
			$clean = self::sanitize( $raw, $field );
			if ( '' === $clean || 0 === $clean || false === $clean ) {
				delete_post_meta( $post_id, '_bmn_' . $key );
			} else {
				update_post_meta( $post_id, '_bmn_' . $key, $clean );
			}
		}
	}

	/**
	 * Admin assets for fields.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'term.php', 'edit-tags.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'bmn-admin', BMN_CORE_URL . 'assets/css/admin.css', array(), BMN_CORE_VERSION );
		wp_enqueue_script( 'bmn-admin', BMN_CORE_URL . 'assets/js/admin.js', array( 'jquery' ), BMN_CORE_VERSION, true );
	}

	/**
	 * Weekday checkboxes.
	 *
	 * @param string $name     Field name.
	 * @param string $selected Comma list.
	 */
	private static function days_input( $name, $selected ) {
		$sel = array_map( 'intval', array_filter( explode( ',', (string) $selected ), 'strlen' ) );
		foreach ( bmn_weekdays() as $i => $day ) {
			printf( '<label style="margin-inline-end:10px"><input type="checkbox" name="%s[]" value="%d" %s> %s</label>', esc_attr( $name ), (int) $i, checked( in_array( $i, $sel, true ), true, false ), esc_html( $day ) );
		}
	}

	/**
	 * Neighborhood term fields (edit screen).
	 *
	 * @param WP_Term $term Term.
	 */
	public static function term_fields( $term ) {
		wp_nonce_field( 'bmn_term_save', 'bmn_term_nonce' );
		?>
		<tr class="form-field"><th scope="row"><?php esc_html_e( 'روزهای جمع‌آوری پسماند', 'boshrouyeh-core' ); ?></th><td><?php self::days_input( 'bmn_waste_days', (string) get_term_meta( $term->term_id, '_bmn_waste_days', true ) ); ?></td></tr>
		<tr class="form-field"><th scope="row"><?php esc_html_e( 'روزهای جمع‌آوری پسماند خشک', 'boshrouyeh-core' ); ?></th><td><?php self::days_input( 'bmn_recycle_days', (string) get_term_meta( $term->term_id, '_bmn_recycle_days', true ) ); ?></td></tr>
		<tr class="form-field"><th scope="row"><label for="bmn_waste_time"><?php esc_html_e( 'ساعت جمع‌آوری', 'boshrouyeh-core' ); ?></label></th><td><input type="text" id="bmn_waste_time" name="bmn_waste_time" value="<?php echo esc_attr( (string) get_term_meta( $term->term_id, '_bmn_waste_time', true ) ); ?>" placeholder="۲۱:۰۰"></td></tr>
		<?php
	}

	/**
	 * Neighborhood term fields (add screen).
	 */
	public static function term_fields_add() {
		wp_nonce_field( 'bmn_term_save', 'bmn_term_nonce' );
		echo '<div class="form-field"><label>' . esc_html__( 'روزهای جمع‌آوری پسماند', 'boshrouyeh-core' ) . '</label>';
		self::days_input( 'bmn_waste_days', '' );
		echo '</div><div class="form-field"><label for="bmn_waste_time">' . esc_html__( 'ساعت جمع‌آوری', 'boshrouyeh-core' ) . '</label><input type="text" id="bmn_waste_time" name="bmn_waste_time"></div>';
	}

	/**
	 * Save neighborhood term meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_term( $term_id ) {
		if ( ! isset( $_POST['bmn_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmn_term_nonce'] ) ), 'bmn_term_save' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via sanitize_days().
		update_term_meta( $term_id, '_bmn_waste_days', self::sanitize_days( isset( $_POST['bmn_waste_days'] ) ? (array) wp_unslash( $_POST['bmn_waste_days'] ) : array() ) );
		update_term_meta( $term_id, '_bmn_recycle_days', self::sanitize_days( isset( $_POST['bmn_recycle_days'] ) ? (array) wp_unslash( $_POST['bmn_recycle_days'] ) : array() ) );
		// phpcs:enable
		update_term_meta( $term_id, '_bmn_waste_time', isset( $_POST['bmn_waste_time'] ) ? sanitize_text_field( wp_unslash( $_POST['bmn_waste_time'] ) ) : '' );
	}
}
