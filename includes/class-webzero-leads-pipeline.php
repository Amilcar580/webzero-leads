<?php
/**
 * Pantalla y cambios de etapa del pipeline comercial.
 *
 * @package WebZeroLeads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebZero_Leads_Pipeline {
	private const PAGE   = 'webzero-leads-pipeline';
	private const ACTION = 'webzero_leads_pipeline_move';
	private static $screen_hook;

	public static function register() {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'move' ) );
	}

	public static function menu( $parent ) {
		$title = __( 'Pipeline', 'webzero-leads' );
		self::$screen_hook = add_submenu_page(
			$parent, $title, $title, WebZero_Leads_Admin::CAPABILITY, self::PAGE, array( self::class, 'render' )
		);
	}

	public static function enqueue( $hook ) {
		if ( self::$screen_hook !== $hook || ! current_user_can( WebZero_Leads_Admin::CAPABILITY ) ) {
			return;
		}
		$dir = dirname( __DIR__ );
		$url = plugins_url( 'assets', $dir . '/webzero-leads.php' );
		wp_enqueue_style( 'webzero-leads-pipeline', $url . '/pipeline.css', array(), (string) filemtime( $dir . '/assets/pipeline.css' ) );
		wp_enqueue_script( 'webzero-leads-pipeline', $url . '/pipeline.js', array(), (string) filemtime( $dir . '/assets/pipeline.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
		wp_localize_script(
			'webzero-leads-pipeline',
			'webzeroLeadsPipeline',
			array(
				'action'  => self::ACTION,
				'nonce'   => wp_create_nonce( self::ACTION ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'saving'  => __( 'Guardando el cambio de etapa…', 'webzero-leads' ),
				/* translators: 1: lead name, 2: commercial stage. */
				'saved'   => __( '%1$s pasó a «%2$s».', 'webzero-leads' ),
				'failed'  => __( 'No se pudo confirmar el cambio. Revisa la conexión o recarga el tablero antes de intentarlo de nuevo.', 'webzero-leads' ),
			)
		);
	}

	public static function render() {
		WebZero_Leads_Admin::authorize();
		$statuses = WebZero_Leads_Storage::statuses();
		$today    = current_datetime()->format( 'Y-m-d' );
		$groups   = array_fill_keys( array_keys( $statuses ), array() );
		$leads    = WebZero_Leads_Storage::pipeline_cards();
		foreach ( $leads ?? array() as $lead ) {
			if ( isset( $groups[ $lead['status'] ] ) ) {
				$groups[ $lead['status'] ][] = $lead;
			}
		}
		require dirname( __DIR__ ) . '/views/pipeline.php';
	}

	public static function move() {
		if ( ! current_user_can( WebZero_Leads_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permiso para gestionar solicitudes.', 'webzero-leads' ) ), 403 );
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo verificar la solicitud. Recarga el tablero e inténtalo de nuevo.', 'webzero-leads' ) ), 403 );
		}

		$raw_id   = $_POST['lead_id'] ?? null;
		$id       = is_string( $raw_id ) && ctype_digit( $raw_id ) ? filter_var( $raw_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
		$previous = is_string( $_POST['previous'] ?? null ) ? wp_unslash( $_POST['previous'] ) : '';
		$status   = is_string( $_POST['status'] ?? null ) ? wp_unslash( $_POST['status'] ) : '';
		$statuses = WebZero_Leads_Storage::statuses();
		if ( ! $id || ! isset( $statuses[ $status ], $statuses[ $previous ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Selecciona una solicitud y una etapa válidas.', 'webzero-leads' ) ), 400 );
		}

		$result = WebZero_Leads_Storage::update_status( $id, $status, $previous );
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo guardar la etapa. Inténtalo de nuevo.', 'webzero-leads' ) ), 500 );
		}
		if ( 0 === $result ) {
			$lead = WebZero_Leads_Storage::find( $id );
			if ( ! $lead ) {
				wp_send_json_error( array( 'message' => __( 'La solicitud ya no está disponible. Recarga el tablero.', 'webzero-leads' ) ), 404 );
			}
			if ( $lead['status'] !== $status ) {
				wp_send_json_error( array( 'message' => __( 'La etapa cambió en otra sesión. Recarga el tablero antes de mover la tarjeta.', 'webzero-leads' ) ), 409 );
			}
		}
		wp_send_json_success( array( 'status' => $status ) );
	}
}
