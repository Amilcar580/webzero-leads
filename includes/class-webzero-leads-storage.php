<?php
/**
 * Acceso a la tabla privada de solicitudes.
 *
 * @package WebZeroLeads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebZero_Leads_Storage {
	private const SCHEMA_OPTION  = 'webzero_leads_schema_version';
	private const SCHEMA_VERSION = '3';

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'webzero_leads';
	}

	private static function notes_table() {
		global $wpdb;

		return $wpdb->prefix . 'webzero_lead_notes';
	}

	public static function install( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			wp_die( esc_html__( 'Activa WebZero Leads en cada sitio por separado.', 'webzero-leads' ) );
		}
		if ( ! self::upgrade_schema() ) {
			wp_die( esc_html__( 'No se pudo preparar la tabla de WebZero Leads. Revisa los permisos de MySQL y vuelve a intentar la activación.', 'webzero-leads' ) );
		}
	}

	public static function maybe_upgrade() {
		return self::SCHEMA_VERSION === get_option( self::SCHEMA_OPTION ) || self::upgrade_schema();
	}

	private static function upgrade_schema() {
		global $wpdb;
		$table    = self::table();
		$previous = $wpdb->suppress_errors( true );
		try {
			$exists = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( ! $exists ) {
				self::create_table( $table );
			} else {
				$existing = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
				if ( ! in_array( 'phone', $existing, true ) ) {
					$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN phone varchar(16) DEFAULT NULL AFTER email', $table ) );
				}
				if ( ! in_array( 'next_follow_up', $existing, true ) ) {
					$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN next_follow_up date DEFAULT NULL', $table ) );
				}
			}

			$columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), ARRAY_A );
			$fields  = array_column( $columns, null, 'Field' );
			if ( array_diff( array( 'id', 'name', 'email', 'phone', 'service', 'message', 'created_at', 'status', 'next_follow_up' ), array_keys( $fields ) )
				|| 'varchar(16)' !== $fields['phone']['Type'] || 'YES' !== $fields['phone']['Null']
				|| 'date' !== $fields['next_follow_up']['Type'] || 'YES' !== $fields['next_follow_up']['Null']
				|| ! self::prepare_notes_table( $table ) ) {
				return false;
			}
		} finally {
			$wpdb->suppress_errors( $previous );
		}

		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
		return true;
	}

	private static function create_table( $table ) {
		global $wpdb;
		$collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(120) NOT NULL,
				email varchar(254) NOT NULL,
				phone varchar(16) DEFAULT NULL,
				service varchar(120) NOT NULL,
				message text NOT NULL,
				created_at datetime NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'nuevo',
				next_follow_up date DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY created_at (created_at,id)
			) ENGINE=InnoDB {$collate};"
		);
	}

	private static function prepare_notes_table( $lead_table ) {
		global $wpdb;
		$table   = self::notes_table();
		$collate = $wpdb->get_charset_collate();
		$wpdb->query(
			$wpdb->prepare(
				"CREATE TABLE IF NOT EXISTS %i (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					lead_id bigint(20) unsigned NOT NULL,
					note text NOT NULL,
					created_at datetime NOT NULL,
					PRIMARY KEY (id),
					KEY lead_history (lead_id,created_at,id),
					FOREIGN KEY (lead_id) REFERENCES %i (id) ON DELETE CASCADE
				) ENGINE=InnoDB {$collate}",
				$table,
				$lead_table
			)
		);

		$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
		return ! array_diff( array( 'id', 'lead_id', 'note', 'created_at' ), $columns )
			&& 'CASCADE' === $wpdb->get_var(
				$wpdb->prepare(
					'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s AND REFERENCED_TABLE_NAME = %s',
					$table,
					$lead_table
				)
			);
	}

	public static function statuses() {
		return array(
			'nuevo'       => __( 'Nuevo', 'webzero-leads' ),
			'seguimiento' => __( 'En seguimiento', 'webzero-leads' ),
			'finalizado'  => __( 'Finalizado', 'webzero-leads' ),
		);
	}

	public static function insert( $lead ) {
		global $wpdb;
		$previous = $wpdb->suppress_errors( true );
		try {
			return 1 === $wpdb->insert(
				self::table(),
				array(
					'name'       => $lead['name'],
					'email'      => $lead['email'],
					'phone'      => $lead['phone'],
					'service'    => $lead['service'],
					'message'    => $lead['message'],
					'created_at' => current_time( 'mysql', true ),
					'status'     => 'nuevo',
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		} finally {
			$wpdb->suppress_errors( $previous );
		}
	}

	private static function receipt_bounds( DateTimeImmutable $start, DateTimeImmutable $until ) {
		$utc   = new DateTimeZone( 'UTC' );
		$first = max( $start->setTimezone( $utc ), new DateTimeImmutable( '1000-01-01 00:00:00', $utc ) );
		// The receipt column stores UTC with second precision.
		$last  = min( $until->setTimezone( $utc )->modify( '-1 second' ), new DateTimeImmutable( '9999-12-31 23:59:59', $utc ) );
		return array( $first->format( 'Y-m-d H:i:s' ), $last->format( 'Y-m-d H:i:s' ) );
	}

	public static function metrics( $today, DateTimeImmutable $start, DateTimeImmutable $until ) {
		global $wpdb;
		list( $first, $last ) = self::receipt_bounds( $start, $until );
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COUNT(*) AS total,
					COUNT(CASE WHEN status = %s THEN 1 END) AS nuevo,
					COUNT(CASE WHEN status = %s THEN 1 END) AS seguimiento,
					COUNT(CASE WHEN status = %s THEN 1 END) AS finalizado,
					COUNT(CASE WHEN status IN (%s, %s) AND next_follow_up < %s THEN 1 END) AS overdue,
					MIN(created_at) AS first_received, MAX(created_at) AS last_received
				FROM %i WHERE created_at BETWEEN %s AND %s',
				'nuevo', 'seguimiento', 'finalizado', 'nuevo', 'seguimiento', $today, self::table(), $first, $last
			),
			ARRAY_A
		);
	}

	public static function services() {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT service FROM %i ORDER BY service ASC', self::table() ) );
	}

	public static function service_counts( DateTimeImmutable $start, DateTimeImmutable $until ) {
		global $wpdb;
		list( $first, $last ) = self::receipt_bounds( $start, $until );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT service, COUNT(*) AS total FROM %i WHERE created_at BETWEEN %s AND %s GROUP BY service ORDER BY total DESC, service ASC',
				self::table(), $first, $last
			),
			ARRAY_A
		);
		return $wpdb->last_error ? null : $rows;
	}

	public static function daily_counts( DateTimeZone $timezone, $first_received, $last_received ) {
		global $wpdb;
		$utc         = new DateTimeZone( 'UTC' );
		$first       = new DateTimeImmutable( $first_received, $utc );
		$last        = new DateTimeImmutable( $last_received, $utc );
		$start       = $first->getTimestamp();
		$transitions = $timezone->getTransitions( $start, $last->getTimestamp() + 1 );
		$offset      = $timezone->getOffset( $first );
		$cases       = array();
		$values      = array();

		// Apply the site's offset at each receipt without relying on MySQL timezone tables.
		foreach ( $transitions ?: array() as $transition ) {
			if ( $transition['ts'] <= $start || $transition['offset'] === $offset ) {
				continue;
			}
			$cases[]  = 'WHEN created_at < %s THEN %d';
			$values[] = gmdate( 'Y-m-d H:i:s', $transition['ts'] );
			$values[] = $offset;
			$offset   = $transition['offset'];
		}
		$offset_sql = $cases ? 'CASE ' . implode( ' ', $cases ) . ' ELSE %d END' : '%d';
		$values[]   = $offset;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(DATE_ADD(created_at, INTERVAL ({$offset_sql}) SECOND)) AS local_day, COUNT(*) AS total
				FROM %i WHERE created_at BETWEEN %s AND %s GROUP BY local_day ORDER BY local_day ASC",
				array_merge( $values, array( self::table(), $first_received, $last_received ) )
			),
			ARRAY_A
		);
		return $wpdb->last_error ? null : array_column( $rows, 'total', 'local_day' );
	}

	private static function list_conditions( $filters ) {
		global $wpdb;
		$conditions = array();
		$values     = array( self::table() );
		if ( ! empty( $filters['lead_status'] ) ) {
			$conditions[] = 'status = %s';
			$values[]     = $filters['lead_status'];
		}
		if ( isset( $filters['lead_service'] ) && '' !== $filters['lead_service'] ) {
			$conditions[] = 'service = %s';
			$values[]     = $filters['lead_service'];
		}
		foreach ( array( 'lead_from' => 'created_at >= %s', 'lead_to' => 'created_at < %s' ) as $key => $condition ) {
			if ( empty( $filters[ $key ] ) ) {
				continue;
			}
			$boundary = new DateTimeImmutable( $filters[ $key ], wp_timezone() );
			if ( 'lead_to' === $key ) {
				$boundary = $boundary->modify( '+1 day' )->setTime( 0, 0 );
			}
			$conditions[] = $condition;
			$values[]     = $boundary->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		}
		if ( isset( $filters['lead_search'] ) && '' !== $filters['lead_search'] ) {
			$search = $filters['lead_search'];
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$phone  = $like;
			if ( preg_match( '/\A[0-9+().\s-]+\z/', $search ) ) {
				$digits = preg_replace( '/\D/', '', $search );
				if ( strlen( $digits ) > 2 && 0 === strpos( $digits, '00' ) ) {
					$digits = substr( $digits, 2 );
				}
				if ( '' !== $digits ) {
					$phone = '%' . $wpdb->esc_like( $digits ) . '%';
				}
			}
			$conditions[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			array_push( $values, $like, $like, $phone );
		}
		return array( $conditions ? ' WHERE ' . implode( ' AND ', $conditions ) : '', $values );
	}

	public static function count( $filters = array() ) {
		global $wpdb;
		list( $where, $values ) = self::list_conditions( $filters );
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i{$where}", $values ) );
	}

	public static function list_page( $limit, $offset, $filters = array() ) {
		global $wpdb;
		list( $where, $values ) = self::list_conditions( $filters );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, email, phone, service, created_at, status, next_follow_up FROM %i{$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				array_merge( $values, array( $limit, $offset ) )
			),
			ARRAY_A
		);
		return $wpdb->last_error ? null : $rows;
	}

	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id, name, email, phone, service, message, created_at, status, next_follow_up FROM %i WHERE id = %d', self::table(), $id ), ARRAY_A );
	}

	public static function pipeline_cards() {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, name, service, phone, status, next_follow_up FROM %i ORDER BY id DESC', self::table() ),
			ARRAY_A
		);
		return $wpdb->last_error ? null : $rows;
	}

	public static function update_status( $id, $status, $previous = null ) {
		global $wpdb;
		$where = array( 'id' => $id );
		$types = array( '%d' );
		if ( null !== $previous ) {
			$where['status'] = $previous;
			$types[]         = '%s';
		}
		return $wpdb->update( self::table(), array( 'status' => $status ), $where, array( '%s' ), $types );
	}

	public static function update_follow_up( $id, $date ) {
		global $wpdb;

		return $wpdb->update( self::table(), array( 'next_follow_up' => $date ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	public static function add_note( $id, $note ) {
		global $wpdb;
		$previous = $wpdb->suppress_errors( true );
		try {
			return 1 === $wpdb->insert(
				self::notes_table(),
				array( 'lead_id' => $id, 'note' => $note, 'created_at' => current_time( 'mysql', true ) ),
				array( '%d', '%s', '%s' )
			);
		} finally {
			$wpdb->suppress_errors( $previous );
		}
	}

	public static function count_notes( $id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE lead_id = %d', self::notes_table(), $id ) );
	}

	public static function list_notes( $id, $limit, $offset ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, note, created_at FROM %i WHERE lead_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d', self::notes_table(), $id, $limit, $offset ),
			ARRAY_A
		);
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}
}
