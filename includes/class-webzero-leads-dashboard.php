<?php
/**
 * Datos y recursos de la pantalla principal.
 *
 * @package WebZeroLeads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebZero_Leads_Dashboard {
	private const PAGE_SIZE            = 20;
	private const FULL_DAY_TABLE_LIMIT = 366;

	public static function enqueue() {
		$plugin_file = dirname( __DIR__ ) . '/webzero-leads.php';
		wp_enqueue_style( 'webzero-leads-dashboard', plugins_url( 'assets/dashboard.css', $plugin_file ), array(), (string) filemtime( dirname( $plugin_file ) . '/assets/dashboard.css' ) );
		wp_enqueue_script( 'webzero-leads-dashboard', plugins_url( 'assets/dashboard.js', $plugin_file ), array(), (string) filemtime( dirname( $plugin_file ) . '/assets/dashboard.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	}

	private static function get( $key ) {
		return is_string( $_GET[ $key ] ?? null ) ? wp_unslash( $_GET[ $key ] ) : '';
	}

	public static function render( $menu_slug ) {
		$now            = current_datetime();
		$today          = $now->format( 'Y-m-d' );
		$statuses       = WebZero_Leads_Storage::statuses();
		$service_filter = WebZero_Leads_Storage::services();
		$period_options = array(
			'7d'     => __( 'Últimos 7 días', 'webzero-leads' ),
			'30d'    => __( 'Últimos 30 días', 'webzero-leads' ),
			'90d'    => __( 'Últimos 90 días', 'webzero-leads' ),
			'month'  => __( 'Este mes', 'webzero-leads' ),
			'custom' => __( 'Rango personalizado', 'webzero-leads' ),
		);
		$requested_period = self::get( 'wzl_period' );
		$period           = isset( $period_options[ $requested_period ] ) ? $requested_period : '30d';
		$period_args      = array( 'wzl_period' => $period );
		$period_error     = '';
		$period_label     = '';
		$metrics          = null;
		$services         = null;
		$activity         = null;

		if ( 'custom' === $period ) {
			foreach ( array( 'wzl_start', 'wzl_end' ) as $key ) {
				$value = self::get( $key );
				if ( WebZero_Leads_Admin::valid_date( $value ) ) {
					$period_args[ $key ] = $value;
				} else {
					$period_error = __( 'Introduce una fecha inicial y una fecha final válidas.', 'webzero-leads' );
				}
			}
			if ( ! $period_error && $period_args['wzl_start'] > $period_args['wzl_end'] ) {
				$period_error = __( 'La fecha inicial debe ser igual o anterior a la fecha final.', 'webzero-leads' );
			}
		}

		if ( ! $period_error ) {
			list( $start, $end ) = self::period_days( $period, $period_args, $now );
			$until = $end->modify( '+1 day' )->setTime( 0, 0 );
			/* translators: 1: first day of the period, 2: last day of the period. */
			$period_label = sprintf(
				__( 'Del %1$s al %2$s', 'webzero-leads' ),
				wp_date( get_option( 'date_format' ), $start->getTimestamp() ),
				wp_date( get_option( 'date_format' ), $end->getTimestamp() )
			);
			$metrics  = WebZero_Leads_Storage::metrics( $today, $start, $until );
			$services = WebZero_Leads_Storage::service_counts( $start, $until );
			if ( ! empty( $metrics['first_received'] ) ) {
				$activity = self::activity( $start, $end, $metrics );
			}
		}

		$filters  = array(
			'lead_status'  => self::get( 'lead_status' ),
			'lead_service' => self::get( 'lead_service' ),
			'lead_from'    => self::get( 'lead_from' ),
			'lead_to'      => self::get( 'lead_to' ),
			'lead_search'  => mb_substr( sanitize_text_field( self::get( 'lead_search' ) ), 0, 254, 'UTF-8' ),
		);
		$filters['lead_status'] = isset( $statuses[ $filters['lead_status'] ] ) ? $filters['lead_status'] : '';
		if ( ! in_array( $filters['lead_service'], $service_filter, true ) ) {
			$filters['lead_service'] = '';
		}

		$date_error = '';
		foreach ( array( 'lead_from', 'lead_to' ) as $key ) {
			if ( '' !== $filters[ $key ] && ! WebZero_Leads_Admin::valid_date( $filters[ $key ] ) ) {
				$filters[ $key ] = '';
				$date_error = __( 'Introduce fechas de recepción válidas.', 'webzero-leads' );
			}
		}
		if ( $filters['lead_from'] && $filters['lead_to'] && $filters['lead_from'] > $filters['lead_to'] ) {
			$date_error = __( 'La fecha «Desde» debe ser igual o anterior a «Hasta».', 'webzero-leads' );
		}

		$active_filters = array_filter( $filters, 'strlen' );
		$count          = null;
		$leads          = array();
		$total_pages    = 0;
		$page           = 1;
		if ( '' === $date_error ) {
			$count = WebZero_Leads_Storage::count( $active_filters );
			if ( $count > 0 ) {
				$total_pages = (int) ceil( (int) $count / self::PAGE_SIZE );
				$page        = max( 1, min( $total_pages, absint( self::get( 'paged' ) ) ) );
				$leads       = WebZero_Leads_Storage::list_page( self::PAGE_SIZE, ( $page - 1 ) * self::PAGE_SIZE, $active_filters );
			}
		}

		require dirname( __DIR__ ) . '/views/dashboard.php';
	}

	private static function period_days( $period, $args, DateTimeImmutable $now ) {
		$today = $now->setTime( 0, 0 );
		if ( 'custom' === $period ) {
			$zone = $now->getTimezone();
			return array( new DateTimeImmutable( $args['wzl_start'], $zone ), new DateTimeImmutable( $args['wzl_end'], $zone ) );
		}
		if ( 'month' === $period ) {
			return array( $today->modify( 'first day of this month' )->setTime( 0, 0 ), $today );
		}
		$length = array( '7d' => 7, '30d' => 30, '90d' => 90 );
		return array( $today->modify( '-' . ( $length[ $period ] - 1 ) . ' days' )->setTime( 0, 0 ), $today );
	}

	private static function activity( DateTimeImmutable $start, DateTimeImmutable $end, $metrics ) {
		$counts = WebZero_Leads_Storage::daily_counts( $start->getTimezone(), $metrics['first_received'], $metrics['last_received'] );
		if ( null === $counts ) {
			return null;
		}

		// Treat local dates as calendar slots, including 23- and 25-hour days.
		$calendar_zone = new DateTimeZone( 'UTC' );
		$first_day     = new DateTimeImmutable( $start->format( 'Y-m-d' ), $calendar_zone );
		$last_day      = new DateTimeImmutable( $end->format( 'Y-m-d' ), $calendar_zone );
		$last_index    = $first_day->diff( $last_day )->days;
		$compact       = $last_index >= self::FULL_DAY_TABLE_LIMIT;
		$points        = array( 0 => 0, $last_index => 0 );
		$rows          = array();
		$date_format   = get_option( 'date_format' );

		foreach ( $counts as $date => $count ) {
			$day              = new DateTimeImmutable( $date, $calendar_zone );
			$index            = (int) $first_day->diff( $day )->format( '%r%a' );
			$points[ $index ] = (int) $count;
			$rows[ $index ]   = array( 'date' => wp_date( $date_format, $day->getTimestamp(), $calendar_zone ), 'count' => (int) $count );
		}
		if ( ! $compact ) {
			for ( $index = 0; $index <= $last_index; ++$index ) {
				$day = $first_day->modify( "+{$index} days" );
				$rows[ $index ] = array( 'date' => wp_date( $date_format, $day->getTimestamp(), $calendar_zone ), 'count' => $points[ $index ] ?? 0 );
			}
		}

		ksort( $points );
		ksort( $rows );
		$ticks       = array();
		$tick_format = $compact ? 'M Y' : 'j M';
		for ( $tick = 0; $tick <= 4; ++$tick ) {
			$index = (int) round( $tick * $last_index / 4 );
			$ticks[ $index ] = array(
				'index' => $index,
				'label' => wp_date( $tick_format, $first_day->modify( "+{$index} days" )->getTimestamp(), $calendar_zone ),
			);
		}
		return array(
			'rows'    => $rows,
			'compact' => $compact,
			'chart'   => array( 'points' => $points, 'ticks' => array_values( $ticks ), 'lastIndex' => $last_index ),
		);
	}
}
