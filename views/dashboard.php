<?php
/**
 * Vista del dashboard de WebZero Leads.
 *
 * @package WebZeroLeads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cards = array(
	'total'       => __( 'Total de leads', 'webzero-leads' ),
	'nuevo'       => __( 'Leads nuevos', 'webzero-leads' ),
	'seguimiento' => __( 'En seguimiento', 'webzero-leads' ),
	'finalizado'  => __( 'Finalizados', 'webzero-leads' ),
	'overdue'     => __( 'Pendientes vencidos', 'webzero-leads' ),
);
$status_rows = array();
foreach ( $statuses as $key => $label ) {
	$status_rows[] = array( 'label' => $label, 'total' => $metrics[ $key ] ?? 0, 'color' => $key );
}
$groups = array(
	'services' => array(
		'title' => __( 'Por servicio', 'webzero-leads' ),
		'rows'  => null === $services ? null : array_map(
			static function ( $row ) {
				return array(
					'label' => '' === $row['service'] ? __( 'Sin servicio', 'webzero-leads' ) : $row['service'],
					'total' => $row['total'],
					'color' => 'service',
				);
			},
			$services
		),
	),
	'statuses' => array(
		'title' => __( 'Por estado', 'webzero-leads' ),
		'rows'  => null === $metrics ? null : $status_rows,
	),
);
?>
<section class="wzl-section" aria-labelledby="wzl-overview">
	<div class="wzl-overview-header">
		<div>
			<h2 id="wzl-overview"><?php esc_html_e( 'Resumen por período', 'webzero-leads' ); ?></h2>
			<?php if ( $period_label ) : ?>
				<p class="wzl-muted"><strong><?php echo esc_html( $period_options[ $period ] ); ?></strong> · <?php echo esc_html( $period_label ); ?></p>
			<?php endif; ?>
		</div>
		<form class="wzl-period-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" aria-label="<?php esc_attr_e( 'Período de análisis', 'webzero-leads' ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( $menu_slug ); ?>">
			<?php foreach ( $active_filters as $key => $value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<?php endforeach; ?>
			<label>
				<span><?php esc_html_e( 'Período', 'webzero-leads' ); ?></span>
				<select name="wzl_period">
					<?php foreach ( $period_options as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $period, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<fieldset class="wzl-period-dates">
				<legend class="screen-reader-text"><?php esc_html_e( 'Fechas del rango personalizado', 'webzero-leads' ); ?></legend>
				<label>
					<span><?php esc_html_e( 'Fecha inicial', 'webzero-leads' ); ?></span>
					<input type="date" name="wzl_start" min="1000-01-01" max="9999-12-31" value="<?php echo esc_attr( $period_args['wzl_start'] ?? '' ); ?>" <?php if ( $period_error ) : ?>aria-invalid="true" aria-describedby="wzl-period-error"<?php endif; ?>>
				</label>
				<label>
					<span><?php esc_html_e( 'Fecha final', 'webzero-leads' ); ?></span>
					<input type="date" name="wzl_end" min="1000-01-01" max="9999-12-31" value="<?php echo esc_attr( $period_args['wzl_end'] ?? '' ); ?>" <?php if ( $period_error ) : ?>aria-invalid="true" aria-describedby="wzl-period-error"<?php endif; ?>>
				</label>
			</fieldset>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Aplicar período', 'webzero-leads' ); ?></button>
		</form>
	</div>
	<?php if ( $period_error ) : ?>
		<p id="wzl-period-error" class="wzl-error"><?php echo esc_html( $period_error ); ?></p>
	<?php elseif ( null === $metrics ) : ?>
		<p><?php esc_html_e( 'No se pudieron consultar las métricas.', 'webzero-leads' ); ?></p>
	<?php else : ?>
		<dl class="wzl-metrics">
			<?php foreach ( $cards as $key => $label ) : ?>
				<div class="wzl-card wzl-metric wzl-tone-<?php echo esc_attr( $key ); ?>">
					<dt><?php echo esc_html( $label ); ?></dt>
					<dd><?php echo esc_html( number_format_i18n( (int) $metrics[ $key ] ) ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<div class="wzl-analytics">
			<section class="wzl-card wzl-trend" aria-labelledby="wzl-trend">
				<h3 id="wzl-trend"><?php esc_html_e( 'Leads por día', 'webzero-leads' ); ?></h3>
				<p class="wzl-muted"><?php esc_html_e( 'Zona horaria del sitio', 'webzero-leads' ); ?></p>
				<?php if ( 0 === (int) $metrics['total'] ) : ?>
					<p class="wzl-empty"><?php esc_html_e( 'No se recibieron leads en este período.', 'webzero-leads' ); ?></p>
				<?php elseif ( null === $activity ) : ?>
					<p><?php esc_html_e( 'No se pudieron consultar las solicitudes por día.', 'webzero-leads' ); ?></p>
				<?php else : ?>
					<canvas class="wzl-line-chart" data-chart="<?php echo esc_attr( wp_json_encode( $activity['chart'] ) ); ?>" aria-hidden="true" hidden></canvas>
					<details class="wzl-chart-data">
						<summary><?php esc_html_e( 'Ver datos por día', 'webzero-leads' ); ?></summary>
						<?php if ( $activity['compact'] ) : ?>
							<p class="wzl-muted"><?php esc_html_e( 'Los días que no aparecen en la tabla tuvieron 0 leads.', 'webzero-leads' ); ?></p>
						<?php endif; ?>
						<table class="widefat striped">
							<thead><tr><th scope="col"><?php esc_html_e( 'Fecha', 'webzero-leads' ); ?></th><th scope="col"><?php esc_html_e( 'Leads', 'webzero-leads' ); ?></th></tr></thead>
							<tbody>
								<?php foreach ( $activity['rows'] as $day ) : ?>
									<tr><th scope="row"><?php echo esc_html( $day['date'] ); ?></th><td><?php echo esc_html( number_format_i18n( $day['count'] ) ); ?></td></tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				<?php endif; ?>
			</section>
			<?php foreach ( $groups as $group => $distribution ) : ?>
				<section class="wzl-card wzl-distribution" aria-labelledby="wzl-<?php echo esc_attr( $group ); ?>">
					<h3 id="wzl-<?php echo esc_attr( $group ); ?>"><?php echo esc_html( $distribution['title'] ); ?></h3>
					<?php if ( null === $distribution['rows'] ) : ?>
						<p><?php esc_html_e( 'No se pudo consultar la distribución.', 'webzero-leads' ); ?></p>
					<?php elseif ( ! $distribution['rows'] ) : ?>
						<p class="wzl-muted"><?php esc_html_e( 'Sin solicitudes en este período.', 'webzero-leads' ); ?></p>
					<?php else : ?>
						<?php $distribution_total = array_sum( array_column( $distribution['rows'], 'total' ) ); ?>
						<ul class="wzl-bars">
							<?php foreach ( $distribution['rows'] as $row ) : ?>
								<?php $share = $distribution_total ? (int) round( 100 * $row['total'] / $distribution_total ) : 0; ?>
								<li class="wzl-tone-<?php echo esc_attr( $row['color'] ); ?>">
									<span class="wzl-bar-label"><span><?php echo esc_html( $row['label'] ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $row['total'] ) ); ?></strong></span>
									<span class="wzl-bar-track" aria-hidden="true"><span style="width: <?php echo esc_attr( (string) $share ); ?>%"></span></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<section class="wzl-card wzl-section wzl-registry" aria-labelledby="wzl-leads">
	<div class="wzl-registry-heading">
		<h2 id="wzl-leads"><?php esc_html_e( 'Solicitudes', 'webzero-leads' ); ?></h2>
		<?php if ( null !== $count ) : ?>
			<p class="wzl-muted">
				<?php
				/* translators: %s: number of matching leads. */
				echo esc_html( sprintf( _n( '%s solicitud', '%s solicitudes', (int) $count, 'webzero-leads' ), number_format_i18n( (int) $count ) ) );
				?>
			</p>
		<?php endif; ?>
	</div>
	<form class="wzl-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" aria-label="<?php esc_attr_e( 'Filtrar solicitudes', 'webzero-leads' ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( $menu_slug ); ?>">
		<?php foreach ( $period_args as $key => $value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<?php endforeach; ?>
		<label class="wzl-search">
			<span><?php esc_html_e( 'Buscar', 'webzero-leads' ); ?></span>
			<input type="search" name="lead_search" value="<?php echo esc_attr( $filters['lead_search'] ); ?>" maxlength="254" placeholder="<?php esc_attr_e( 'Nombre, correo o teléfono', 'webzero-leads' ); ?>">
		</label>
		<label>
			<span><?php esc_html_e( 'Estado', 'webzero-leads' ); ?></span>
			<select name="lead_status">
				<option value=""><?php esc_html_e( 'Todos los estados', 'webzero-leads' ); ?></option>
				<?php foreach ( $statuses as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['lead_status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'Servicio', 'webzero-leads' ); ?></span>
			<select name="lead_service">
				<option value=""><?php esc_html_e( 'Todos los servicios', 'webzero-leads' ); ?></option>
				<?php foreach ( $service_filter as $service ) : ?>
					<?php if ( '' !== $service ) : ?>
						<option value="<?php echo esc_attr( $service ); ?>" <?php selected( $filters['lead_service'], $service ); ?>><?php echo esc_html( $service ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'Recibidas desde', 'webzero-leads' ); ?></span>
			<input type="date" name="lead_from" value="<?php echo esc_attr( $filters['lead_from'] ); ?>" <?php if ( $date_error ) : ?>aria-invalid="true" aria-describedby="wzl-date-error"<?php endif; ?>>
		</label>
		<label>
			<span><?php esc_html_e( 'Recibidas hasta', 'webzero-leads' ); ?></span>
			<input type="date" name="lead_to" value="<?php echo esc_attr( $filters['lead_to'] ); ?>" <?php if ( $date_error ) : ?>aria-invalid="true" aria-describedby="wzl-date-error"<?php endif; ?>>
		</label>
		<div class="wzl-filter-actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrar', 'webzero-leads' ); ?></button>
			<?php if ( $active_filters || $date_error ) : ?>
				<a href="<?php echo esc_url( WebZero_Leads_Admin::url( $period_args ) ); ?>"><?php esc_html_e( 'Limpiar', 'webzero-leads' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
	<?php if ( $date_error ) : ?>
		<p id="wzl-date-error" class="wzl-error"><?php echo esc_html( $date_error ); ?></p>
	<?php elseif ( null === $count || null === $leads ) : ?>
		<p class="wzl-error"><?php esc_html_e( 'No se pudieron consultar las solicitudes.', 'webzero-leads' ); ?></p>
	<?php elseif ( ! $leads ) : ?>
		<p class="wzl-empty"><?php echo esc_html( $active_filters ? __( 'No hay solicitudes que coincidan con los filtros.', 'webzero-leads' ) : __( 'Aún no hemos recibido solicitudes.', 'webzero-leads' ) ); ?></p>
	<?php else : ?>
		<p id="wzl-table-hint" class="wzl-table-hint"><?php esc_html_e( 'Desplaza la tabla para ver todas las columnas.', 'webzero-leads' ); ?></p>
		<div class="wzl-table-wrap" tabindex="0" role="region" aria-labelledby="wzl-leads" aria-describedby="wzl-table-hint">
			<table class="widefat wzl-table">
				<caption class="screen-reader-text"><?php esc_html_e( 'Solicitudes recibidas', 'webzero-leads' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Nombre', 'webzero-leads' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Correo electrónico', 'webzero-leads' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Teléfono', 'webzero-leads' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Servicio', 'webzero-leads' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Fecha', 'webzero-leads' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Estado', 'webzero-leads' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Próximo contacto', 'webzero-leads' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $leads as $lead ) : ?>
						<?php
						$overdue = $lead['next_follow_up'] && $lead['next_follow_up'] < $today;
						$status  = isset( $statuses[ $lead['status'] ] ) ? $lead['status'] : 'unknown';
						?>
						<tr<?php if ( $overdue ) : ?> class="wzl-overdue-row"<?php endif; ?>>
							<th scope="row"><a href="<?php echo esc_url( WebZero_Leads_Admin::url( array( 'lead_id' => (int) $lead['id'] ) ) ); ?>"><?php echo esc_html( $lead['name'] ); ?></a></th>
							<td><?php echo esc_html( $lead['email'] ); ?></td>
							<td><?php echo esc_html( $lead['phone'] ?? __( 'Sin teléfono', 'webzero-leads' ) ); ?></td>
							<td><?php echo esc_html( $lead['service'] ); ?></td>
							<td><?php echo esc_html( WebZero_Leads_Admin::date( $lead['created_at'] ) ); ?></td>
							<td><span class="wzl-chip wzl-tone-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $statuses[ $status ] ?? $lead['status'] ); ?></span></td>
							<td>
								<?php echo esc_html( WebZero_Leads_Admin::follow_up_label( $lead['next_follow_up'] ) ); ?>
								<?php if ( $overdue ) : ?>
									<br><span class="wzl-chip wzl-tone-overdue"><?php esc_html_e( 'Vencido', 'webzero-leads' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php WebZero_Leads_Admin::render_pagination( $page, $total_pages, 'paged', array_map( 'rawurlencode', $period_args + $active_filters ) ); ?>
	<?php endif; ?>
</section>
