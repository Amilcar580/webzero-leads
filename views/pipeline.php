<?php
/**
 * Tablero comercial del administrador.
 *
 * @package WebZeroLeads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap webzero-leads-pipeline">
	<?php if ( null === $leads ) : ?>
		<h1><?php esc_html_e( 'Pipeline comercial', 'webzero-leads' ); ?></h1>
		<div class="notice notice-error"><p><?php esc_html_e( 'No se pudieron consultar las solicitudes.', 'webzero-leads' ); ?></p></div>
	<?php else : ?>
		<?php
		$total     = array_sum( array_map( 'count', $groups ) );
		$completed = count( $groups['finalizado'] );
		$stage_icons = array( 'nuevo' => 'email-alt', 'seguimiento' => 'clock', 'finalizado' => 'yes' );
		?>
		<header class="wzl-pipeline-hero">
			<div>
				<h1><?php esc_html_e( 'Pipeline comercial', 'webzero-leads' ); ?></h1>
				<p class="wzl-pipeline-intro" hidden><?php esc_html_e( 'Arrastra una tarjeta o elige una etapa y pulsa «Mover».', 'webzero-leads' ); ?></p>
				<noscript><p><?php esc_html_e( 'Puedes abrir las fichas desde el tablero. Activa JavaScript para mover tarjetas desde esta pantalla.', 'webzero-leads' ); ?></p></noscript>
			</div>
			<div class="wzl-pipeline-summary">
				<label for="wzl-pipeline-progress"><?php esc_html_e( 'Oportunidades finalizadas', 'webzero-leads' ); ?></label>
				<span class="wzl-pipeline-tally">
					<?php
					/* translators: 1: completed opportunities, 2: total opportunities. */
					$tally_format = __( '%1$s de %2$s', 'webzero-leads' );
					echo esc_html( sprintf( $tally_format, number_format_i18n( $completed ), number_format_i18n( $total ) ) );
					?>
				</span>
				<progress id="wzl-pipeline-progress" max="<?php echo esc_attr( (string) max( 1, $total ) ); ?>" value="<?php echo esc_attr( (string) $completed ); ?>" data-tally-format="<?php echo esc_attr( $tally_format ); ?>"></progress>
			</div>
		</header>
		<div class="wzl-pipeline-feedback">
			<p class="wzl-pipeline-status" role="status" aria-atomic="true"></p>
		</div>
		<div class="wzl-pipeline-board">
			<?php foreach ( $statuses as $status => $label ) : ?>
				<?php $leads_in_stage = $groups[ $status ]; ?>
				<section class="wzl-stage wzl-stage-<?php echo esc_attr( $status ); ?>" data-stage="<?php echo esc_attr( $status ); ?>" aria-labelledby="wzl-stage-<?php echo esc_attr( $status ); ?>">
					<header class="wzl-stage-heading">
						<span class="wzl-stage-icon dashicons dashicons-<?php echo esc_attr( $stage_icons[ $status ] ); ?>" aria-hidden="true"></span>
						<h2 id="wzl-stage-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $label ); ?></h2>
						<span class="wzl-stage-count"><?php echo esc_html( number_format_i18n( count( $leads_in_stage ) ) ); ?></span>
					</header>
					<ul class="wzl-stage-cards" aria-labelledby="wzl-stage-<?php echo esc_attr( $status ); ?>">
						<?php foreach ( $leads_in_stage as $lead ) : ?>
							<?php
							$id      = (int) $lead['id'];
							$date    = $lead['next_follow_up'];
							$overdue = $date && $date < $today;
							?>
							<li class="wzl-lead-card<?php echo $overdue ? ' is-overdue' : ''; ?>" data-lead-id="<?php echo esc_attr( (string) $id ); ?>">
								<div class="wzl-card-heading">
									<span class="wzl-card-avatar dashicons dashicons-admin-users" aria-hidden="true"></span>
									<div>
										<h3><a class="wzl-card-link" href="<?php echo esc_url( WebZero_Leads_Admin::url( array( 'lead_id' => $id ) ) ); ?>" draggable="false"><?php echo esc_html( $lead['name'] ); ?></a></h3>
										<p class="wzl-card-service"><?php echo esc_html( $lead['service'] ); ?></p>
									</div>
								</div>
								<dl class="wzl-card-details">
									<div><dt><span class="dashicons dashicons-phone" aria-hidden="true"></span><?php esc_html_e( 'Teléfono', 'webzero-leads' ); ?></dt><dd><?php echo esc_html( $lead['phone'] ?: __( 'Sin teléfono', 'webzero-leads' ) ); ?></dd></div>
									<div>
										<dt><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><?php esc_html_e( 'Próximo contacto', 'webzero-leads' ); ?></dt>
										<dd>
											<?php if ( $date ) : ?>
												<time datetime="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( WebZero_Leads_Admin::follow_up_label( $date ) ); ?></time>
											<?php else : ?>
												<?php echo esc_html( WebZero_Leads_Admin::follow_up_label( $date ) ); ?>
											<?php endif; ?>
											<?php if ( $overdue ) : ?>
												<span class="wzl-card-overdue"><?php esc_html_e( 'Vencido', 'webzero-leads' ); ?></span>
											<?php endif; ?>
										</dd>
									</div>
								</dl>
								<div class="wzl-card-actions" hidden>
									<label class="screen-reader-text" for="wzl-stage-select-<?php echo esc_attr( (string) $id ); ?>">
										<?php
										/* translators: %s: lead name. */
										echo esc_html( sprintf( __( 'Etapa de %s', 'webzero-leads' ), $lead['name'] ) );
										?>
									</label>
									<select id="wzl-stage-select-<?php echo esc_attr( (string) $id ); ?>">
										<?php foreach ( $statuses as $option => $option_label ) : ?>
											<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( $option_label ); ?></option>
										<?php endforeach; ?>
									</select>
									<button class="button wzl-move-lead" type="button" disabled><?php esc_html_e( 'Mover', 'webzero-leads' ); ?></button>
								</div>
								<p class="wzl-pipeline-error" role="alert" aria-atomic="true"></p>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="wzl-stage-empty" <?php if ( $leads_in_stage ) : ?>hidden<?php endif; ?>><?php esc_html_e( 'Sin solicitudes en esta etapa.', 'webzero-leads' ); ?></p>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
