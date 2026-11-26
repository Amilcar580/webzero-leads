<?php
/**
 * Plugin Name: WebZero Leads
 * Description: Recibe y gestiona en WordPress las solicitudes del formulario de WebZero.
 * Version: 1.5.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Text Domain: webzero-leads
 * License: GPL-2.0-or-later
 *
 * @package WebZeroLeads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-webzero-leads-storage.php';
require_once __DIR__ . '/includes/class-webzero-leads-dashboard.php';
require_once __DIR__ . '/includes/class-webzero-leads-admin.php';
require_once __DIR__ . '/includes/class-webzero-leads-pipeline.php';

register_activation_hook( __FILE__, array( 'WebZero_Leads_Storage', 'install' ) );
add_action( 'plugins_loaded', array( 'WebZero_Leads_Storage', 'maybe_upgrade' ) );

/**
 * Recibe campos sin barras añadidas de una integración que ya verificó el formulario.
 * Devuelve true únicamente cuando la solicitud se guardó.
 */
function webzero_leads_receive( $handled, $submission ) {
	if ( null !== $handled ) {
		return $handled;
	}
	if ( ! is_array( $submission ) ) {
		return new WP_Error( 'webzero_leads_invalid', __( 'Revisa los datos de la solicitud.', 'webzero-leads' ), array( 'status' => 422 ) );
	}
	$phone = $submission['phone'] ?? '';
	if ( ! is_string( $phone ) || strlen( $phone ) > 16 || 1 !== preg_match( '/\A(?:\+[1-9][0-9]{6,14}|[0-9]{7,15})\z/D', $phone ) ) {
		return new WP_Error( 'webzero_leads_invalid_phone', __( 'Introduce un teléfono válido.', 'webzero-leads' ), array( 'status' => 422 ) );
	}

	$limits = array( 'name' => 120, 'email' => 254, 'service' => 120, 'message' => 2000 );
	$lead   = array( 'phone' => $phone );

	foreach ( $limits as $field => $max ) {
		$value = $submission[ $field ] ?? '';
		if ( ! is_string( $value ) || strlen( $value ) > $max * 4 ) {
			return new WP_Error( 'webzero_leads_invalid', __( 'Revisa los datos de la solicitud.', 'webzero-leads' ), array( 'status' => 422 ) );
		}
		$value = 'message' === $field ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		if ( '' === $value || mb_strlen( $value, 'UTF-8' ) > $max ) {
			return new WP_Error( 'webzero_leads_invalid', __( 'Revisa los datos de la solicitud.', 'webzero-leads' ), array( 'status' => 422 ) );
		}
		$lead[ $field ] = $value;
	}
	if ( ! is_email( $lead['email'] ) || trim( $submission['email'] ) !== $lead['email'] || strlen( $lead['email'] ) > 254 ) {
		return new WP_Error( 'webzero_leads_invalid_email', __( 'Introduce un correo electrónico válido.', 'webzero-leads' ), array( 'status' => 422 ) );
	}

	$address = filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP );
	$source  = $address ? 'ip:' . $address : 'email:' . strtolower( $lead['email'] );
	$key     = 'webzero_leads_submit_' . wp_hash( $source, 'nonce' );
	if ( get_transient( $key ) ) {
		return new WP_Error( 'webzero_leads_rate_limit', __( 'Espera un minuto antes de volver a enviar una solicitud.', 'webzero-leads' ), array( 'status' => 429 ) );
	}

	set_transient( $key, 1, MINUTE_IN_SECONDS );
	if ( ! WebZero_Leads_Storage::insert( $lead ) ) {
		delete_transient( $key );
		return new WP_Error( 'webzero_leads_storage', __( 'No hemos podido guardar tu solicitud. Inténtalo de nuevo más tarde.', 'webzero-leads' ), array( 'status' => 503 ) );
	}

	return true;
}

add_filter( 'webzero_contact_submission', 'webzero_leads_receive', 10, 2 );

WebZero_Leads_Admin::register();
WebZero_Leads_Pipeline::register();
