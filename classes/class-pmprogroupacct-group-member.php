<?php

/**
 * The PMPro Group Account Member object.
 *
 * @since 1.0
 */
class PMProGroupAcct_Group_Member {
	protected $id;
	protected $group_child_user_id;
	protected $group_child_level_id;
	protected $group_id;
	protected $group_child_status;
	protected $status_updated;
	protected $first_name;
	protected $last_name;
	protected $date_of_birth;
	protected $gender;
	protected $emergency_phone;
	protected $team_post_id;
	protected $child_order;
	protected $custom_meta;

	public function __construct( $member_id ) {
		global $wpdb;

		if ( is_int( $member_id ) ) {
			$data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->pmprogroupacct_group_members} WHERE id = %d",
					$member_id
				)
			);

			if ( ! empty( $data ) ) {
				$this->id                   = (int) $data->id;
				$this->group_child_user_id  = (int) $data->group_child_user_id;
				$this->group_child_level_id = (int) $data->group_child_level_id;
				$this->group_id             = (int) $data->group_id;
				$this->group_child_status   = $data->group_child_status;
				$this->status_updated       = $data->status_updated;
				$this->first_name           = isset( $data->first_name ) ? $data->first_name : '';
				$this->last_name            = isset( $data->last_name ) ? $data->last_name : '';
				$this->date_of_birth        = isset( $data->date_of_birth ) ? $data->date_of_birth : null;
				$this->gender               = isset( $data->gender ) ? $data->gender : '';
				$this->emergency_phone      = isset( $data->emergency_phone ) ? $data->emergency_phone : '';
				$this->team_post_id         = isset( $data->team_post_id ) ? (int) $data->team_post_id : 0;
				$this->child_order          = isset( $data->child_order ) ? (int) $data->child_order : 0;
				$this->custom_meta           = isset( $data->custom_meta ) ? $data->custom_meta : '';
			}
		}
	}

	public static function get_group_members( $args = array() ) {
		global $wpdb;

		$sql_query = empty( $args['return_count'] ) ? "SELECT id FROM {$wpdb->pmprogroupacct_group_members}" : "SELECT COUNT(id) FROM {$wpdb->pmprogroupacct_group_members}";
		$limit     = empty( $args['limit'] ) ? 0 : (int) $args['limit'];
		$offset    = empty( $args['offset'] ) ? 0 : (int) $args['offset'];

		$prepared = array();
		$where    = array();

		if ( isset( $args['id'] ) ) {
			$where[]    = 'id = %d';
			$prepared[] = $args['id'];
		}

		if ( isset( $args['group_child_user_id'] ) ) {
			if ( is_array( $args['group_child_user_id'] ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $args['group_child_user_id'] ), '%d' ) );
				$where[]      = "group_child_user_id IN ($placeholders)";
				$prepared     = array_merge( $prepared, $args['group_child_user_id'] );
			} else {
				$where[]    = 'group_child_user_id = %d';
				$prepared[] = $args['group_child_user_id'];
			}
		}

		if ( isset( $args['group_child_level_id'] ) ) {
			$where[]    = 'group_child_level_id = %d';
			$prepared[] = $args['group_child_level_id'];
		}

		if ( isset( $args['group_id'] ) ) {
			$where[]    = 'group_id = %d';
			$prepared[] = $args['group_id'];
		}

		if ( isset( $args['group_child_status'] ) ) {
			$where[]    = 'group_child_status = %s';
			$prepared[] = $args['group_child_status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$search     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]    = '(first_name LIKE %s OR last_name LIKE %s OR emergency_phone LIKE %s)';
			$prepared[] = $search;
			$prepared[] = $search;
			$prepared[] = $search;
		}

		if ( ! empty( $where ) ) {
			$sql_query .= ' WHERE ' . implode( ' AND ', $where );
		}

		if ( empty( $args['return_count'] ) ) {
			$sql_query .= ' ORDER BY child_order ASC, status_updated DESC';

			if ( ! empty( $limit ) ) {
				$sql_query .= ' LIMIT %d OFFSET %d';
				$prepared[] = $limit;
				$prepared[] = $offset;
			}
		}

		if ( ! empty( $prepared ) ) {
			$sql_query = $wpdb->prepare( $sql_query, $prepared );
		}

		$member_ids = $wpdb->get_col( $sql_query );

		if ( ! empty( $args['return_count'] ) ) {
			return (int) $member_ids[0];
		}

		if ( empty( $member_ids ) ) {
			return array();
		}

		$members = array();
		foreach ( $member_ids as $member_id ) {
			$member = new self( (int) $member_id );
			if ( ! empty( $member->id ) ) {
				$members[] = $member;
			}
		}
		return $members;
	}

	public static function create( $group_child_user_id, $group_child_level_id, $group_id ) {
		global $wpdb;

		if (
			! is_numeric( $group_child_user_id ) || (int) $group_child_user_id <= 0 ||
			! is_numeric( $group_child_level_id ) || (int) $group_child_level_id <= 0 ||
			! is_numeric( $group_id ) || (int) $group_id <= 0
		) {
			return false;
		}

		$existing_group_member = self::get_group_members( array(
			'group_child_user_id'  => $group_child_user_id,
			'group_child_level_id' => $group_child_level_id,
			'group_id'             => $group_id,
			'group_child_status'   => 'inactive',
		) );

		if ( ! empty( $existing_group_member ) ) {
			$existing_group_member[0]->update_group_child_status( 'active' );
			return $existing_group_member[0];
		}

		$wpdb->insert(
			$wpdb->pmprogroupacct_group_members,
			array(
				'group_child_user_id'  => (int) $group_child_user_id,
				'group_child_level_id' => (int) $group_child_level_id,
				'group_id'             => (int) $group_id,
				'group_child_status'   => 'active',
				'status_updated'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		if ( empty( $wpdb->insert_id ) ) {
			return false;
		}

		return new self( $wpdb->insert_id );
	}

	/**
	 * Create a profile-only child member (no WordPress account).
	 *
	 * @since 2.0
	 *
	 * @param int   $group_id Group ID.
	 * @param array $profile  Child profile data.
	 * @return PMProGroupAcct_Group_Member|false
	 */
	public static function create_from_profile( $group_id, $profile ) {
		global $wpdb;

		if ( ! is_numeric( $group_id ) || (int) $group_id <= 0 ) {
			return false;
		}

		$profile = self::sanitize_profile( $profile );

		if ( empty( $profile['first_name'] ) || empty( $profile['last_name'] ) ) {
			return false;
		}

		if ( empty( $profile['team_post_id'] ) || ! pmprogroupacct_validate_team_post_id( $profile['team_post_id'] ) ) {
			return false;
		}

		$wpdb->insert(
			$wpdb->pmprogroupacct_group_members,
			array(
				'group_child_user_id'  => 0,
				'group_child_level_id' => 0,
				'group_id'             => (int) $group_id,
				'group_child_status'   => 'active',
				'status_updated'       => current_time( 'mysql' ),
				'first_name'           => $profile['first_name'],
				'last_name'            => $profile['last_name'],
				'date_of_birth'        => $profile['date_of_birth'],
				'gender'               => $profile['gender'],
				'emergency_phone'      => $profile['emergency_phone'],
				'team_post_id'         => (int) $profile['team_post_id'],
				'child_order'          => (int) $profile['child_order'],
				'custom_meta'          => pmprogroupacct_encode_child_custom_meta( $profile['custom_meta'] ?? array() ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( empty( $wpdb->insert_id ) ) {
			return false;
		}

		return new self( $wpdb->insert_id );
	}

	/**
	 * Update a profile-only child member.
	 *
	 * @since 2.0
	 *
	 * @param array $profile Child profile data.
	 * @return bool
	 */
	public function update_profile( $profile ) {
		global $wpdb;

		$profile = self::sanitize_profile( $profile );

		if ( empty( $profile['first_name'] ) || empty( $profile['last_name'] ) ) {
			return false;
		}

		if ( empty( $profile['team_post_id'] ) || ! pmprogroupacct_validate_team_post_id( $profile['team_post_id'] ) ) {
			return false;
		}

		$this->first_name      = $profile['first_name'];
		$this->last_name       = $profile['last_name'];
		$this->date_of_birth   = $profile['date_of_birth'];
		$this->gender          = $profile['gender'];
		$this->emergency_phone = $profile['emergency_phone'];
		$this->team_post_id    = (int) $profile['team_post_id'];
		$this->child_order     = (int) $profile['child_order'];
		$this->custom_meta      = pmprogroupacct_encode_child_custom_meta( $profile['custom_meta'] ?? array() );

		$result = $wpdb->update(
			$wpdb->pmprogroupacct_group_members,
			array(
				'first_name'      => $this->first_name,
				'last_name'       => $this->last_name,
				'date_of_birth'   => $this->date_of_birth,
				'gender'          => $this->gender,
				'emergency_phone' => $this->emergency_phone,
				'team_post_id'    => $this->team_post_id,
				'child_order'     => $this->child_order,
				'custom_meta'     => $this->custom_meta,
				'status_updated'  => current_time( 'mysql' ),
			),
			array( 'id' => $this->id ),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get the display name for a profile child.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public function get_display_name() {
		if ( ! empty( $this->first_name ) || ! empty( $this->last_name ) ) {
			return trim( $this->first_name . ' ' . $this->last_name );
		}

		if ( ! empty( $this->group_child_user_id ) ) {
			$user = get_userdata( $this->group_child_user_id );
			if ( ! empty( $user ) ) {
				return $user->display_name;
			}
		}

		return '';
	}

	/**
	 * Whether this member is a profile-only child (no WP account).
	 *
	 * @since 2.0
	 *
	 * @return bool
	 */

	public function get_custom_meta() {
		return pmprogroupacct_decode_child_custom_meta( $this->custom_meta );
	}

	public function is_profile_child() {
		return empty( $this->group_child_user_id );
	}

	protected static function sanitize_profile( $profile ) {
		$date_of_birth = null;
		if ( ! empty( $profile['date_of_birth'] ) ) {
			$parsed = strtotime( sanitize_text_field( $profile['date_of_birth'] ) );
			if ( $parsed ) {
				$date_of_birth = gmdate( 'Y-m-d', $parsed );
			}
		}

		return array(
			'first_name'      => sanitize_text_field( $profile['first_name'] ?? '' ),
			'last_name'       => sanitize_text_field( $profile['last_name'] ?? '' ),
			'date_of_birth'   => $date_of_birth,
			'gender'          => sanitize_text_field( $profile['gender'] ?? '' ),
			'emergency_phone' => sanitize_text_field( $profile['emergency_phone'] ?? '' ),
			'team_post_id'    => intval( $profile['team_post_id'] ?? 0 ),
			'child_order'     => intval( $profile['child_order'] ?? 0 ),
			'custom_meta'     => is_array( $profile['custom_meta'] ?? null ) ? $profile['custom_meta'] : array(),
		);
	}

	public function __get( $name ) {
		if ( property_exists( $this, $name ) ) {
			return $this->$name;
		}
	}

	public function __isset( $name ) {
		if ( property_exists( $this, $name ) ) {
			return isset( $this->$name );
		}
		return false;
	}

	public function update_group_child_status( $group_child_status ) {
		global $wpdb;

		if ( ! in_array( $group_child_status, array( 'active', 'inactive' ), true ) ) {
			return;
		}

		$this->group_child_status = $group_child_status;
		$wpdb->update(
			$wpdb->pmprogroupacct_group_members,
			array(
				'group_child_status' => $group_child_status,
				'status_updated'     => current_time( 'mysql' ),
			),
			array( 'id' => $this->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}
}
