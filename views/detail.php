<?php
/**
 * Ficha comercial de una solicitud.
 *
 * @package WebZeroLeads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap webzero-leads-detail">
	<header class="wzl-detail-header">
		<a class="wzl-detail-back" href="<?php echo esc_url( self::url() ); ?>">
			<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
			<?php esc_html_e( 'Volver al listado', 'webzero-leads' ); ?>
		</a>
		<?php if ( $lead ) : ?>
			<div class="wzl-detail-heading">
				<div>
					<p class="wzl-detail-eyebrow"><?php esc_html_e( 'WebZero Leads · Ficha comercial', 'webzero-leads' ); ?></p>
					<h1><?php echo esc_html( $lead['name'] ); ?></h1>
				</div>
				<dl class="wzl-detail-stage">
					<dt><?php esc_html_e( 'Estado comercial', 'webzero-leads' ); ?></dt>
					<dd class="wzl-detail-badge" data-stage="<?php echo esc_attr( $lead['status'] ); ?>"><?php echo esc_html( $statuses[ $lead['status'] ] ?? $lead['status'] ); ?></dd>
				</dl>
			</div>
			<dl class="wzl-detail-meta">
				<div>
					<dt><?php esc_html_e( 'Servicio solicitado', 'webzero-leads' ); ?></dt>
					<dd><?php echo esc_html( $lead['service'] ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Fecha de recepción', 'webzero-leads' ); ?></dt>
					<dd><?php echo esc_html( self::date( $lead['created_at'] ) ); ?></dd>
				</div>
			</dl>
		<?php else : ?>
			<h1>WebZero Leads</h1>
		<?php endif; ?>
	</header>
	<?php self::notice(); ?>
	<?php if ( ! $lead ) : ?>
		<p class="wzl-detail-card"><?php esc_html_e( 'La solicitud no está disponible.', 'webzero-leads' ); ?></p>
	</div>
		<?php return; ?>
	<?php endif; ?>

	<div class="wzl-detail-grid">
		<section class="wzl-detail-card" aria-labelledby="wzl-detail-contact">
			<h2 id="wzl-detail-contact"><?php esc_html_e( 'Información de contacto', 'webzero-leads' ); ?></h2>
			<dl class="wzl-detail-contact">
				<div>
					<dt><?php esc_html_e( 'Correo electrónico', 'webzero-leads' ); ?></dt>
					<dd><?php echo esc_html( $lead['email'] ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Teléfono', 'webzero-leads' ); ?></dt>
					<dd><?php echo esc_html( $lead['phone'] ?? __( 'Sin teléfono', 'webzero-leads' ) ); ?></dd>
				</div>
			</dl>
		</section>
		<section class="wzl-detail-card" aria-labelledby="wzl-detail-message">
			<h2 id="wzl-detail-message"><?php esc_html_e( 'Mensaje original', 'webzero-leads' ); ?></h2>
			<p class="wzl-detail-text wzl-detail-message"><?php echo esc_html( $lead['message'] ); ?></p>
		</section>
		<section class="wzl-detail-card" aria-labelledby="wzl-detail-management">
			<h2 id="wzl-detail-management"><?php esc_html_e( 'Gestión comercial', 'webzero-leads' ); ?></h2>
			<form class="wzl-detail-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="webzero_leads_update">
				<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $id ); ?>">
				<?php wp_nonce_field( 'webzero_leads_update_' . $id ); ?>
				<label for="webzero-lead-status"><?php esc_html_e( 'Estado comercial', 'webzero-leads' ); ?></label>
				<select id="webzero-lead-status" name="lead_status">
					<?php foreach ( $statuses as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $lead['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Guardar estado', 'webzero-leads' ), 'secondary wzl-detail-button', 'submit', false, array( 'id' => 'wzl-detail-save-status' ) ); ?>
			</form>
			<form class="wzl-detail-form wzl-detail-follow-up" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="webzero_leads_follow_up">
				<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $id ); ?>">
				<?php wp_nonce_field( 'webzero_leads_follow_up_' . $id ); ?>
				<label for="webzero-next-follow-up"><?php esc_html_e( 'Próximo contacto', 'webzero-leads' ); ?></label>
				<input type="date" id="webzero-next-follow-up" name="next_follow_up" min="1000-01-01" max="9999-12-31" value="<?php echo esc_attr( $lead['next_follow_up'] ?? '' ); ?>" aria-describedby="webzero-follow-up-help">
				<p id="webzero-follow-up-help" class="description"><?php esc_html_e( 'Deja la fecha vacía para quitar el seguimiento programado.', 'webzero-leads' ); ?></p>
				<?php submit_button( __( 'Guardar fecha', 'webzero-leads' ), 'secondary wzl-detail-button', 'submit', false, array( 'id' => 'wzl-detail-save-date' ) ); ?>
			</form>
		</section>
		<section class="wzl-detail-card" aria-labelledby="wzl-detail-notes">
			<h2 id="wzl-detail-notes"><?php esc_html_e( 'Notas internas', 'webzero-leads' ); ?></h2>
			<form class="wzl-detail-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="webzero_leads_note">
				<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $id ); ?>">
				<?php wp_nonce_field( 'webzero_leads_note_' . $id ); ?>
				<label for="webzero-lead-note"><?php esc_html_e( 'Nueva nota', 'webzero-leads' ); ?></label>
				<textarea id="webzero-lead-note" name="lead_note" rows="5" maxlength="<?php echo esc_attr( (string) self::NOTE_LIMIT ); ?>" required></textarea>
				<?php submit_button( __( 'Añadir nota', 'webzero-leads' ), 'primary wzl-detail-button', 'submit', false, array( 'id' => 'wzl-detail-add-note' ) ); ?>
			</form>
			<div class="wzl-detail-notes-history">
				<?php self::render_notes( $id ); ?>
			</div>
		</section>
	</div>
	<section class="wzl-detail-card wzl-detail-danger" aria-labelledby="wzl-detail-delete">
		<h2 id="wzl-detail-delete"><?php esc_html_e( 'Eliminar solicitud', 'webzero-leads' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="webzero_leads_delete">
			<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $id ); ?>">
			<?php wp_nonce_field( 'webzero_leads_delete_' . $id ); ?>
			<label class="wzl-detail-confirm">
				<input type="checkbox" name="confirm_delete" value="yes" required>
				<?php esc_html_e( 'Confirmo que quiero eliminar esta solicitud definitivamente.', 'webzero-leads' ); ?>
			</label>
			<?php submit_button( __( 'Eliminar solicitud', 'webzero-leads' ), 'delete wzl-detail-button', 'submit', false, array( 'id' => 'wzl-detail-delete-lead' ) ); ?>
		</form>
	</section>
</div>
