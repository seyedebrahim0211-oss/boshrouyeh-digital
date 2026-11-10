<?php
/**
 * Organisation: units (واحدها), staff roles with scoped capabilities, referral of requests, audit log (حراست).
 *
 * @package BoshrouyehEservices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Staff & units.
 */
class BMN_Staff {

	const TAX = 'bmn_unit';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_action( 'bmn_request_created', array( __CLASS__, 'auto_assign' ), 10, 2 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'scope_caps' ), 10, 4 );
		add_action( 'pre_get_posts', array( __CLASS__, 'scope_lists' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'profile_field' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_field' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'profile_save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'profile_save' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );
		// Audit trail.
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 10, 2 );
		add_action( 'wp_login_failed', array( __CLASS__, 'on_login_failed' ) );
		add_action( 'set_user_role', array( __CLASS__, 'on_role' ), 10, 3 );
		add_action( 'bmn_request_status_changed', array( __CLASS__, 'on_status' ), 10, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
		add_action( 'bmn_audit_purge', array( __CLASS__, 'purge' ) );
		if ( ! wp_next_scheduled( 'bmn_audit_purge' ) ) {
			wp_schedule_event( time() + 600, 'daily', 'bmn_audit_purge' );
		}
	}

	/* ------------------------------------------------------------------ Units */

	/**
	 * Default units with the 137 categories they handle.
	 *
	 * @return array slug => [name, categories[]]
	 */
	public static function default_units() {
		return apply_filters(
			'bmn_default_units',
			array(
				'service-desk'   => array( 'میز خدمت و سامانه ۱۳۷', array( 'other' ) ),
				'city-services'  => array( 'خدمات شهری', array( 'waste', 'lighting' ) ),
				'green-space'    => array( 'فضای سبز', array( 'green' ) ),
				'civil-works'    => array( 'عمران و آسفالت', array( 'asphalt', 'water' ) ),
				'enforcement'    => array( 'اجرائیات و رفع سد معبر', array( 'obstruction' ) ),
				'urban-planning' => array( 'شهرسازی', array() ),
				'revenue'        => array( 'درآمد و وصول عوارض', array() ),
				'finance'        => array( 'امور مالی', array() ),
				'fire'           => array( 'آتش‌نشانی و خدمات ایمنی', array() ),
				'transport'      => array( 'حمل‌ونقل و ترافیک', array() ),
				'culture'        => array( 'فرهنگی، اجتماعی و گردشگری', array() ),
				'pr'             => array( 'روابط عمومی', array() ),
				'security'       => array( 'حراست و بازرسی', array() ),
				'mayor-office'   => array( 'دفتر شهردار', array() ),
			)
		);
	}

	/**
	 * Register taxonomy (staff-only UI).
	 */
	public static function register() {
		register_taxonomy(
			self::TAX,
			array( 'bmn_request', 'bmn_directive' ),
			array(
				'labels'            => array(
					'name'          => __( 'واحدهای شهرداری', 'boshrouyeh-eservices' ),
					'singular_name' => __( 'واحد', 'boshrouyeh-eservices' ),
					'menu_name'     => __( 'واحدها', 'boshrouyeh-eservices' ),
					'add_new_item'  => __( 'افزودن واحد', 'boshrouyeh-eservices' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'hierarchical'      => true,
				'meta_box_cb'       => false,
				'capabilities'      => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_bmn_requests',
				),
			)
		);
	}

	/**
	 * Create default units (idempotent).
	 */
	public static function seed_units() {
		self::register();
		foreach ( self::default_units() as $slug => $u ) {
			if ( ! term_exists( $slug, self::TAX ) ) {
				$t = wp_insert_term( $u[0], self::TAX, array( 'slug' => $slug ) );
				if ( ! is_wp_error( $t ) ) {
					update_term_meta( (int) $t['term_id'], 'bmn_cats', implode( ',', $u[1] ) );
				}
			}
		}
	}

	/**
	 * All units id => name.
	 *
	 * @return array
	 */
	public static function units() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAX,
				'hide_empty' => false,
				'orderby'    => 'term_id',
			)
		);
		$out = array();
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$out[ (int) $t->term_id ] = $t->name;
		}
		return $out;
	}

	/**
	 * Unit of a staff user (0 = none).
	 *
	 * @param int $uid User.
	 * @return int
	 */
	public static function user_unit( $uid = 0 ) {
		return (int) get_user_meta( $uid ? $uid : get_current_user_id(), 'bmn_unit', true );
	}

	/**
	 * Unit id of a post (request/directive).
	 *
	 * @param int $post_id Post.
	 * @return int
	 */
	public static function post_unit( $post_id ) {
		$t = wp_get_object_terms( $post_id, self::TAX, array( 'fields' => 'ids' ) );
		return ( ! is_wp_error( $t ) && $t ) ? (int) $t[0] : 0;
	}

	/**
	 * Unit name for a post.
	 *
	 * @param int $post_id Post.
	 * @return string
	 */
	public static function post_unit_name( $post_id ) {
		$id = self::post_unit( $post_id );
		$t  = $id ? get_term( $id, self::TAX ) : null;
		return ( $t && ! is_wp_error( $t ) ) ? $t->name : '';
	}

	/**
	 * New request → default unit by type/category.
	 *
	 * @param int    $id   Request.
	 * @param string $type Type.
	 */
	public static function auto_assign( $id, $type ) {
		$slug = 'service-desk';
		if ( 'report137' === $type ) {
			$cat = (string) get_post_meta( $id, '_bmn_category', true );
			foreach ( self::units() as $tid => $name ) {
				$cats = array_filter( explode( ',', (string) get_term_meta( $tid, 'bmn_cats', true ) ) );
				if ( in_array( $cat, $cats, true ) ) {
					$term = get_term( $tid, self::TAX );
					$slug = $term ? $term->slug : $slug;
					break;
				}
			}
		} elseif ( 'appointment' === $type ) {
			$slug = 'mayor-office';
		} elseif ( 'complaint' === $type ) {
			$slug = 'security';
		} elseif ( 'feedback' === $type ) {
			$slug = 'pr';
		}
		$term = get_term_by( 'slug', $slug, self::TAX );
		if ( $term ) {
			self::refer( $id, (int) $term->term_id, '', false );
		}
	}

	/**
	 * Refer (assign) a request to a unit and record it in the citizen-visible timeline.
	 *
	 * @param int    $id      Request.
	 * @param int    $unit    Unit term id.
	 * @param string $note    Note.
	 * @param bool   $by_user Whether a staff member did it (logged).
	 * @return bool
	 */
	public static function refer( $id, $unit, $note = '', $by_user = true ) {
		$term = get_term( $unit, self::TAX );
		if ( ! $term || is_wp_error( $term ) || self::post_unit( $id ) === (int) $unit ) {
			return false;
		}
		wp_set_object_terms( $id, array( (int) $unit ), self::TAX, false );
		$tl   = get_post_meta( $id, '_bmn_timeline', true );
		$tl   = is_array( $tl ) ? $tl : array();
		$tl[] = array(
			's'  => (string) get_post_meta( $id, '_bmn_status', true ),
			'n'  => mb_substr( (string) $note, 0, 500 ),
			't'  => time(),
			'by' => get_current_user_id(),
			'u'  => $term->name,
		);
		update_post_meta( $id, '_bmn_timeline', $tl );
		if ( $by_user ) {
			self::log( 'request.refer', $id, $term->name );
		}
		do_action( 'bmn_request_referred', $id, (int) $unit );
		return true;
	}

	/* ------------------------------------------------------------------ Roles */

	/**
	 * Capability groups.
	 *
	 * @return array
	 */
	private static function cap_sets() {
		$req = array(
			'edit_bmn_requests'           => true,
			'edit_others_bmn_requests'    => true,
			'read_private_bmn_requests'   => true,
			'edit_private_bmn_requests'   => true,
			'edit_published_bmn_requests' => true,
			'publish_bmn_requests'        => true,
		);
		$content = array(
			'edit_posts'             => true,
			'edit_others_posts'      => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
			'delete_posts'           => true,
			'delete_published_posts' => true,
			'edit_pages'             => true,
			'edit_others_pages'      => true,
			'edit_published_pages'   => true,
			'publish_pages'          => true,
			'upload_files'           => true,
			'moderate_comments'      => true,
			'manage_categories'      => true,
		);
		$visits = array(
			'edit_bmn_visits'             => true,
			'edit_others_bmn_visits'      => true,
			'edit_published_bmn_visits'   => true,
			'publish_bmn_visits'          => true,
			'delete_bmn_visits'           => true,
			'delete_published_bmn_visits' => true,
			'read_private_bmn_visits'     => true,
		);
		$dir_full = array(
			'edit_bmn_directives'             => true,
			'edit_others_bmn_directives'      => true,
			'edit_published_bmn_directives'   => true,
			'publish_bmn_directives'          => true,
			'delete_bmn_directives'           => true,
			'delete_published_bmn_directives' => true,
			'read_private_bmn_directives'     => true,
			'bmn_manage_directives'           => true,
		);
		$dir_unit = array(
			'edit_bmn_directives'           => true,
			'edit_others_bmn_directives'    => true,
			'edit_published_bmn_directives' => true,
			'bmn_update_directives'         => true,
		);
		$fleet = array(
			'edit_bmn_fleets'             => true,
			'edit_others_bmn_fleets'      => true,
			'edit_published_bmn_fleets'   => true,
			'publish_bmn_fleets'          => true,
			'delete_bmn_fleets'           => true,
			'delete_published_bmn_fleets' => true,
			'bmn_manage_fleet'            => true,
		);
		return compact( 'req', 'content', 'visits', 'dir_full', 'dir_unit', 'fleet' );
	}

	/**
	 * Role definitions: slug => [label, caps].
	 *
	 * @return array
	 */
	public static function roles_def() {
		$s    = self::cap_sets();
		$base = array(
			'read'          => true,
			'upload_files'  => true,
			'bmn_dashboard' => true,
		);
		return array(
			'bmn_mayor'      => array(
				__( 'شهردار', 'boshrouyeh-eservices' ),
				$base + $s['req'] + $s['content'] + $s['visits'] + $s['dir_full'] + $s['fleet'] + array(
					'bmn_all_units'     => true,
					'bmn_refer'         => true,
					'bmn_view_payments' => true,
					'bmn_view_reports'  => true,
					'bmn_view_audit'    => true,
					'list_users'        => true,
				),
			),
			'bmn_deputy'     => array(
				__( 'معاون شهردار', 'boshrouyeh-eservices' ),
				$base + $s['req'] + $s['visits'] + $s['dir_full'] + $s['fleet'] + array(
					'bmn_all_units'     => true,
					'bmn_refer'         => true,
					'bmn_view_payments' => true,
					'bmn_view_reports'  => true,
				),
			),
			'bmn_pr'         => array(
				__( 'روابط عمومی', 'boshrouyeh-eservices' ),
				$base + $s['content'] + $s['visits'] + array( 'bmn_view_reports' => true ),
			),
			'bmn_security'   => array(
				__( 'حراست و بازرسی', 'boshrouyeh-eservices' ),
				$base + $s['req'] + array(
					'bmn_view_audit' => true,
					'list_users'     => true,
				),
			),
			'bmn_finance'    => array(
				__( 'امور مالی و درآمد', 'boshrouyeh-eservices' ),
				$base + array(
					'bmn_view_payments'   => true,
					'bmn_export_payments' => true,
					'bmn_view_reports'    => true,
				),
			),
			'bmn_dispatcher' => array(
				__( 'کارشناس میز خدمت / ۱۳۷', 'boshrouyeh-eservices' ),
				$base + $s['req'] + array(
					'bmn_all_units' => true,
					'bmn_refer'     => true,
				),
			),
			'bmn_unit_head'  => array(
				__( 'مدیر واحد', 'boshrouyeh-eservices' ),
				$base + $s['req'] + $s['dir_unit'] + array( 'bmn_refer' => true ),
			),
			'bmn_staff'      => array(
				__( 'کارشناس واحد', 'boshrouyeh-eservices' ),
				$base + $s['req'] + $s['dir_unit'],
			),
			'bmn_transport'  => array(
				__( 'مسئول حمل‌ونقل (ناوگان)', 'boshrouyeh-eservices' ),
				$base + $s['req'] + $s['fleet'],
			),
		);
	}

	/**
	 * (Re)create roles; admin gets every custom cap.
	 */
	public static function install_roles() {
		$admin = get_role( 'administrator' );
		foreach ( self::roles_def() as $slug => $def ) {
			remove_role( $slug );
			add_role( $slug, $def[0], $def[1] );
			if ( $admin ) {
				foreach ( array_keys( $def[1] ) as $cap ) {
					$admin->add_cap( $cap );
				}
			}
		}
		if ( $admin ) {
			foreach ( array( 'delete_bmn_requests', 'delete_others_bmn_requests', 'delete_published_bmn_requests', 'delete_private_bmn_requests', 'bmn_manage_eservices', 'delete_others_bmn_visits', 'delete_others_bmn_directives', 'delete_others_bmn_fleets', 'read_private_bmn_fleets' ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Unit-scoped staff may only touch items of their own unit.
	 *
	 * @param array  $caps    Required caps.
	 * @param string $cap     Requested cap.
	 * @param int    $user_id User.
	 * @param array  $args    Args.
	 * @return array
	 */
	public static function scope_caps( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'read_post', 'delete_post' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post || ! in_array( $post->post_type, array( 'bmn_request', 'bmn_directive' ), true ) ) {
			return $caps;
		}
		$is_admin = user_can( $user_id, 'manage_options' );
		if ( 'bmn_request' === $post->post_type && 'complaint' === get_post_meta( $post->ID, '_bmn_type', true ) ) {
			// Complaints about staff: only security/inspection, the mayor and admins.
			return ( $is_admin || user_can( $user_id, 'bmn_view_audit' ) ) ? $caps : array( 'do_not_allow' );
		}
		if ( $is_admin || user_can( $user_id, 'bmn_all_units' ) ) {
			return $caps;
		}
		$mine = self::user_unit( $user_id );
		if ( ! $mine || self::post_unit( $post->ID ) !== $mine ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	/**
	 * Admin list screens show only the user's unit for scoped staff.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function scope_lists( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || ! in_array( $q->get( 'post_type' ), array( 'bmn_request', 'bmn_directive' ), true ) ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		$is_req = 'bmn_request' === $q->get( 'post_type' );
		if ( $is_req && current_user_can( 'bmn_view_audit' ) && ! current_user_can( 'bmn_all_units' ) ) {
			// Security & inspection: complaints only.
			$q->set( 'meta_query', array( array( 'key' => '_bmn_type', 'value' => 'complaint' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			return;
		}
		if ( $is_req && ! current_user_can( 'bmn_view_audit' ) ) {
			$mq   = (array) $q->get( 'meta_query' );
			$mq[] = array( 'key' => '_bmn_type', 'value' => 'complaint', 'compare' => '!=' );
			$q->set( 'meta_query', $mq ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		if ( current_user_can( 'bmn_all_units' ) ) {
			return;
		}
		$mine = self::user_unit();
		$tax  = (array) $q->get( 'tax_query' );
		$tax[] = array(
			'taxonomy' => self::TAX,
			'field'    => 'term_id',
			'terms'    => $mine ? array( $mine ) : array( 0 ),
		);
		$q->set( 'tax_query', $tax ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	/**
	 * Profile: unit selector (admins) / display (staff).
	 *
	 * @param WP_User $user User.
	 */
	public static function profile_field( $user ) {
		$is_staff = (bool) array_intersect( array_keys( self::roles_def() ), (array) $user->roles );
		if ( ! $is_staff && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$cur = self::user_unit( $user->ID );
		echo '<h2>' . esc_html__( 'شهرداری بشرویه', 'boshrouyeh-eservices' ) . '</h2><table class="form-table"><tr><th><label for="bmn_unit">' . esc_html__( 'واحد سازمانی', 'boshrouyeh-eservices' ) . '</label></th><td>';
		if ( current_user_can( 'promote_users' ) ) {
			wp_nonce_field( 'bmn_unit_save', 'bmn_unit_nonce' );
			echo '<select name="bmn_unit" id="bmn_unit"><option value="0">—</option>';
			foreach ( self::units() as $id => $name ) {
				printf( '<option value="%d" %s>%s</option>', (int) $id, selected( $cur, $id, false ), esc_html( $name ) );
			}
			echo '</select><p class="description">' . esc_html__( 'کارشناس و مدیر واحد فقط درخواست‌ها و دستورهای همین واحد را می‌بینند.', 'boshrouyeh-eservices' ) . '</p>';
		} else {
			$u = self::units();
			echo esc_html( $u[ $cur ] ?? '—' );
		}
		echo '</td></tr></table>';
	}

	/**
	 * Save unit.
	 *
	 * @param int $user_id User.
	 */
	public static function profile_save( $user_id ) {
		if ( ! current_user_can( 'promote_users' ) || ! isset( $_POST['bmn_unit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmn_unit_nonce'] ) ), 'bmn_unit_save' ) ) {
			return;
		}
		$unit = isset( $_POST['bmn_unit'] ) ? absint( $_POST['bmn_unit'] ) : 0;
		if ( $unit && ! isset( self::units()[ $unit ] ) ) {
			return;
		}
		$old = self::user_unit( $user_id );
		update_user_meta( $user_id, 'bmn_unit', $unit );
		if ( $old !== $unit ) {
			self::log( 'user.unit', $user_id, (string) ( self::units()[ $unit ] ?? '-' ) );
		}
	}

	/**
	 * Staff dashboard + audit pages.
	 */
	public static function menu() {
		add_menu_page( __( 'میز کار کارکنان', 'boshrouyeh-eservices' ), __( 'میز کار', 'boshrouyeh-eservices' ), 'bmn_dashboard', 'bmn-desk', array( __CLASS__, 'render_desk' ), 'dashicons-clipboard', 3 );
		add_submenu_page( 'bmn-desk', __( 'گزارش حراست', 'boshrouyeh-eservices' ), __( 'گزارش حراست', 'boshrouyeh-eservices' ), 'bmn_view_audit', 'bmn-audit', array( __CLASS__, 'render_audit' ) );
	}

	/**
	 * Quick link in the admin bar.
	 *
	 * @param WP_Admin_Bar $bar Bar.
	 */
	public static function admin_bar( $bar ) {
		if ( current_user_can( 'bmn_dashboard' ) ) {
			$bar->add_node(
				array(
					'id'    => 'bmn-desk',
					'title' => __( 'میز کار', 'boshrouyeh-eservices' ),
					'href'  => admin_url( 'admin.php?page=bmn-desk' ),
				)
			);
		}
	}

	/**
	 * Role-aware desk: what this person must handle now.
	 */
	public static function render_desk() {
		if ( ! current_user_can( 'bmn_dashboard' ) ) {
			return;
		}
		$user  = wp_get_current_user();
		$roles = self::roles_def();
		$label = '';
		foreach ( (array) $user->roles as $r ) {
			$label = $roles[ $r ][0] ?? ( 'administrator' === $r ? __( 'مدیر سامانه', 'boshrouyeh-eservices' ) : $label );
		}
		$unit  = self::user_unit();
		$units = self::units();
		echo '<div class="wrap"><h1>' . esc_html__( 'میز کار', 'boshrouyeh-eservices' ) . '</h1>';
		/* translators: 1: name, 2: role, 3: unit. */
		echo '<p>' . esc_html( sprintf( __( '%1$s — %2$s %3$s', 'boshrouyeh-eservices' ), $user->display_name, $label, $unit ? '(' . ( $units[ $unit ] ?? '' ) . ')' : '' ) ) . '</p><div style="display:flex;gap:16px;flex-wrap:wrap">';
		$cards = array();
		if ( current_user_can( 'edit_bmn_requests' ) ) {
			$cards[] = array( __( 'درخواست‌ها و گزارش‌های باز', 'boshrouyeh-eservices' ), self::count_open( 'bmn_request' ), admin_url( 'edit.php?post_type=bmn_request' ) );
		}
		if ( current_user_can( 'edit_bmn_directives' ) ) {
			$cards[] = array( __( 'دستورهای بازدید در جریان', 'boshrouyeh-eservices' ), self::count_open( 'bmn_directive' ), admin_url( 'edit.php?post_type=bmn_directive' ) );
			$cards[] = array( __( 'دستورهای عقب‌افتاده', 'boshrouyeh-eservices' ), self::count_open( 'bmn_directive', true ), admin_url( 'edit.php?post_type=bmn_directive&bmn_overdue=1' ) );
		}
		if ( current_user_can( 'edit_bmn_visits' ) ) {
			$cards[] = array( __( 'بازدیدهای شهردار', 'boshrouyeh-eservices' ), (int) wp_count_posts( 'bmn_visit' )->publish, admin_url( 'edit.php?post_type=bmn_visit' ) );
		}
		if ( current_user_can( 'bmn_view_payments' ) ) {
			$cards[] = array( __( 'پرداخت‌ها', 'boshrouyeh-eservices' ), '→', admin_url( 'admin.php?page=bmn-payments' ) );
		}
		if ( current_user_can( 'bmn_manage_fleet' ) ) {
			$cards[] = array( __( 'ناوگان مینی‌بوس', 'boshrouyeh-eservices' ), (int) wp_count_posts( 'bmn_vehicle' )->publish, admin_url( 'edit.php?post_type=bmn_vehicle' ) );
		}
		if ( current_user_can( 'edit_posts' ) ) {
			$cards[] = array( __( 'خبر جدید', 'boshrouyeh-eservices' ), '+', admin_url( 'post-new.php' ) );
		}
		if ( current_user_can( 'bmn_view_audit' ) ) {
			$cards[] = array( __( 'گزارش حراست', 'boshrouyeh-eservices' ), '→', admin_url( 'admin.php?page=bmn-audit' ) );
		}
		foreach ( $cards as $c ) {
			printf( '<a href="%s" style="flex:1 1 220px;background:#fff;border:1px solid #e6dccb;border-radius:14px;padding:18px;text-decoration:none;color:#1f2a44"><div style="font-size:30px;font-weight:800;color:#a8442a">%s</div><div>%s</div></a>', esc_url( $c[2] ), esc_html( is_int( $c[1] ) ? bmn_fa_digits( $c[1] ) : $c[1] ), esc_html( $c[0] ) );
		}
		echo '</div></div>';
	}

	/**
	 * Count open items visible to the current user.
	 *
	 * @param string $pt      Post type.
	 * @param bool   $overdue Only overdue directives.
	 * @return int
	 */
	private static function count_open( $pt, $overdue = false ) {
		$args = array(
			'post_type'      => $pt,
			'post_status'    => array( 'publish', 'private', 'pending', 'draft' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'bmn_request' === $pt ? '_bmn_status' : '_bmn_dstatus',
					'value'   => array( 'done', 'rejected', 'stopped' ),
					'compare' => 'NOT IN',
				),
			),
		);
		if ( $overdue ) {
			$args['meta_query'][] = array(
				'key'     => '_bmn_deadline',
				'value'   => time(),
				'compare' => '<',
				'type'    => 'NUMERIC',
			);
		}
		if ( ! current_user_can( 'bmn_all_units' ) && ! current_user_can( 'manage_options' ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => self::TAX,
					'field'    => 'term_id',
					'terms'    => array( max( 0, self::user_unit() ) ),
				),
			);
		}
		$q = new WP_Query( $args );
		return (int) $q->found_posts;
	}

	/* ------------------------------------------------------------------ Audit */

	/**
	 * Audit table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bmn_audit';
	}

	/**
	 * Create audit table.
	 */
	public static function install_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		dbDelta(
			"CREATE TABLE {$t} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action varchar(40) NOT NULL DEFAULT '',
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				detail varchar(255) NOT NULL DEFAULT '',
				ip varchar(45) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY action (action),
				KEY created_at (created_at)
			) {$wpdb->get_charset_collate()};"
		);
	}

	/**
	 * Write an audit entry.
	 *
	 * @param string $action    Action.
	 * @param int    $object_id Object.
	 * @param string $detail    Detail.
	 * @param int    $uid       User (default current).
	 */
	public static function log( $action, $object_id = 0, $detail = '', $uid = null ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom audit table.
		$wpdb->insert(
			self::table(),
			array(
				'user_id'    => null === $uid ? get_current_user_id() : (int) $uid,
				'action'     => substr( sanitize_key( str_replace( '.', '_', $action ) ), 0, 40 ),
				'object_id'  => (int) $object_id,
				'detail'     => mb_substr( (string) $detail, 0, 250 ),
				'ip'         => substr( (string) bmn_client_ip(), 0, 45 ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Is the user municipality staff?
	 *
	 * @param WP_User $user User.
	 * @return bool
	 */
	private static function is_staff( $user ) {
		return $user instanceof WP_User && ( array_intersect( array_keys( self::roles_def() ), (array) $user->roles ) || in_array( 'administrator', (array) $user->roles, true ) || in_array( 'editor', (array) $user->roles, true ) );
	}

	/**
	 * Staff login.
	 *
	 * @param string  $login Login.
	 * @param WP_User $user  User.
	 */
	public static function on_login( $login, $user ) {
		if ( self::is_staff( $user ) ) {
			self::log( 'login', $user->ID, '', $user->ID );
		}
	}

	/**
	 * Failed login.
	 *
	 * @param string $login Login.
	 */
	public static function on_login_failed( $login ) {
		self::log( 'login_failed', 0, sanitize_user( (string) $login ), 0 );
	}

	/**
	 * Role change.
	 *
	 * @param int    $user_id User.
	 * @param string $role    New.
	 * @param array  $old     Old.
	 */
	public static function on_role( $user_id, $role, $old ) {
		self::log( 'user_role', $user_id, implode( ',', (array) $old ) . ' → ' . $role );
	}

	/**
	 * Request status change.
	 *
	 * @param int    $id  Request.
	 * @param string $new New.
	 * @param string $old Old.
	 */
	public static function on_status( $id, $new, $old ) {
		self::log( 'request_status', $id, $old . ' → ' . $new );
	}

	/**
	 * Publishing by staff.
	 *
	 * @param string  $new  New status.
	 * @param string  $old  Old status.
	 * @param WP_Post $post Post.
	 */
	public static function on_publish( $new, $old, $post ) {
		if ( 'publish' === $new && 'publish' !== $old && is_user_logged_in() && in_array( $post->post_type, array( 'post', 'page', 'bmn_notice', 'bmn_tender', 'bmn_visit', 'bmn_directive' ), true ) ) {
			self::log( 'publish', $post->ID, $post->post_type . ': ' . mb_substr( $post->post_title, 0, 120 ) );
		}
	}

	/**
	 * Deletions.
	 *
	 * @param int $id Post.
	 */
	public static function on_delete( $id ) {
		$p = get_post( $id );
		if ( $p && is_user_logged_in() && ! in_array( $p->post_type, array( 'revision', 'customize_changeset', 'oembed_cache' ), true ) ) {
			self::log( 'delete', $id, $p->post_type . ': ' . mb_substr( $p->post_title, 0, 120 ) );
		}
	}

	/**
	 * Keep 180 days.
	 */
	public static function purge() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', self::table(), gmdate( 'Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS ) ) );
	}

	/**
	 * Audit viewer.
	 */
	public static function render_audit() {
		if ( ! current_user_can( 'bmn_view_audit' ) ) {
			return;
		}
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$action = isset( $_GET['a'] ) ? sanitize_key( wp_unslash( $_GET['a'] ) ) : '';
		// phpcs:enable
		$sql = $action
			? $wpdb->prepare( 'SELECT * FROM %i WHERE action = %s ORDER BY id DESC LIMIT 300', self::table(), $action )
			: $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 300', self::table() );
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$labels = array(
			'login'          => __( 'ورود', 'boshrouyeh-eservices' ),
			'login_failed'   => __( 'ورود ناموفق', 'boshrouyeh-eservices' ),
			'user_role'      => __( 'تغییر نقش', 'boshrouyeh-eservices' ),
			'user_unit'      => __( 'تغییر واحد', 'boshrouyeh-eservices' ),
			'request_status' => __( 'تغییر وضعیت درخواست', 'boshrouyeh-eservices' ),
			'request_refer'  => __( 'ارجاع درخواست', 'boshrouyeh-eservices' ),
			'directive'      => __( 'بروزرسانی دستور بازدید', 'boshrouyeh-eservices' ),
			'publish'        => __( 'انتشار', 'boshrouyeh-eservices' ),
			'delete'         => __( 'حذف', 'boshrouyeh-eservices' ),
			'payments_csv'   => __( 'خروجی پرداخت‌ها', 'boshrouyeh-eservices' ),
			'fleet_key'      => __( 'کلید ردیاب ناوگان', 'boshrouyeh-eservices' ),
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'گزارش حراست (۱۸۰ روز اخیر)', 'boshrouyeh-eservices' ) . '</h1><form method="get"><input type="hidden" name="page" value="bmn-audit"><select name="a"><option value="">' . esc_html__( 'همهٔ رویدادها', 'boshrouyeh-eservices' ) . '</option>';
		foreach ( $labels as $k => $v ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $action, $k, false ), esc_html( $v ) );
		}
		echo '</select> <button class="button">' . esc_html__( 'فیلتر', 'boshrouyeh-eservices' ) . '</button></form>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'زمان', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'کاربر', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'رویداد', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'شناسه', 'boshrouyeh-eservices' ) . '</th><th>' . esc_html__( 'جزئیات', 'boshrouyeh-eservices' ) . '</th><th>IP</th></tr></thead><tbody>';
		foreach ( (array) $rows as $r ) {
			$u = $r->user_id ? get_userdata( (int) $r->user_id ) : null;
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td dir="ltr">%s</td></tr>',
				esc_html( bmn_jdate( 'Y/m/d H:i', strtotime( $r->created_at . ' UTC' ) ) ),
				esc_html( $u ? $u->display_name : '—' ),
				esc_html( $labels[ $r->action ] ?? $r->action ),
				esc_html( (string) $r->object_id ),
				esc_html( $r->detail ),
				esc_html( $r->ip )
			);
		}
		echo '</tbody></table></div>';
	}
}
