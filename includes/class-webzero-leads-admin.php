<?php
/**
 * Listado y gestión de solicitudes dentro del administrador de WordPress.
 *
 * @package WebZeroLeads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebZero_Leads_Admin {
	public const CAPABILITY  = 'manage_options';
	private const PAGE       = 'webzero-leads';
	private const PAGE_SIZE  = 20;
	private const NOTE_LIMIT = 3000;
	private static $screen_hook;

	public static function register() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_dashboard' ) );
		add_action( 'admin_post_webzero_leads_update', array( self::class, 'update' ) );
		add_action( 'admin_post_webzero_leads_follow_up', array( self::class, 'update_follow_up' ) );
		add_action( 'admin_post_webzero_leads_note', array( self::class, 'add_note' ) );
		add_action( 'admin_post_webzero_leads_delete', array( self::class, 'delete' ) );
		add_action( 'admin_init', array( self::class, 'privacy_policy' ) );
	}

	public static function menu() {
		self::$screen_hook = add_menu_page( 'WebZero Leads', 'WebZero Leads', self::CAPABILITY, self::PAGE, array( self::class, 'render' ), 'dashicons-email-alt' );
		add_submenu_page( self::PAGE, 'WebZero Leads', __( 'Dashboard', 'webzero-leads' ), self::CAPABILITY, self::PAGE );
		WebZero_Leads_Pipeline::menu( self::PAGE );
	}

	public static function enqueue_dashboard( $hook ) {
		if ( self::$screen_hook === $hook && current_user_can( self::CAPABILITY ) && ! self::requested_lead() ) {
			WebZero_Leads_Dashboard::enqueue();
		}
	}

	private static function requested_lead() {
		return is_string( $_GET['lead_id'] ?? null ) ? absint( $_GET['lead_id'] ) : 0;
	}

	public static function privacy_policy() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			'WebZero Leads',
			'<p>' . esc_html__( 'Cuando envías una solicitud desde Contacto, guardamos tu nombre, correo electrónico, teléfono, servicio de interés y mensaje para gestionar tu consulta. También registramos la fecha de recepción, el estado, las fechas de seguimiento y las notas internas relacionadas con la solicitud. El equipo autorizado consulta estos datos desde el administrador de WordPress.', 'webzero-leads' ) . '</p>'
		);
	}

	public static function url( $args = array() ) {
		return add_query_arg( array( 'page' => self::PAGE ) + $args, admin_url( 'admin.php' ) );
	}

	public static function authorize() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permiso para gestionar solicitudes.', 'webzero-leads' ), '', array( 'response' => 403 ) );
		}
	}

	private static function authorize_post( $action ) {
		self::authorize();
		$id    = is_string( $_POST['lead_id'] ?? null ) ? absint( $_POST['lead_id'] ) : 0;
		$nonce = is_string( $_POST['_wpnonce'] ?? null ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! $id || ! wp_verify_nonce( $nonce, $action . '_' . $id ) ) {
			wp_die( esc_html__( 'No se pudo verificar esta operación.', 'webzero-leads' ), '', array( 'response' => 403 ) );
		}
		return $id;
	}

	private static function redirect( $notice, $id = 0 ) {
		$args = array( 'webzero_notice' => $notice );
		if ( $id ) {
			$args['lead_id'] = $id;
		}
		wp_safe_redirect( self::url( $args ), 303 );
		exit;
	}

	public static function update() {
		$id     = self::authorize_post( 'webzero_leads_update' );
		$status = is_string( $_POST['lead_status'] ?? null ) ? wp_unslash( $_POST['lead_status'] ) : '';
		if ( ! isset( WebZero_Leads_Storage::statuses()[ $status ] ) ) {
			self::redirect( 'invalid_status', $id );
		}

		self::complete_update( WebZero_Leads_Storage::update_status( $id, $status ), $id, 'updated' );
	}

	public static function update_follow_up() {
		$id   = self::authorize_post( 'webzero_leads_follow_up' );
		$date = is_string( $_POST['next_follow_up'] ?? null ) ? wp_unslash( $_POST['next_follow_up'] ) : null;
		if ( null === $date || ( '' !== $date && ! self::valid_date( $date ) ) ) {
			self::redirect( 'invalid_follow_up', $id );
		}

		self::complete_update( WebZero_Leads_Storage::update_follow_up( $id, '' === $date ? null : $date ), $id, 'follow_up_saved' );
	}

	public static function valid_date( $date ) {
		return 1 === preg_match( '/\A([1-9][0-9]{3})-([0-9]{2})-([0-9]{2})\z/D', $date, $parts )
			&& checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}

	private static function complete_update( $result, $id, $notice ) {
		if ( false === $result ) {
			self::redirect( 'error', $id );
		}
		if ( 0 === $result && ! WebZero_Leads_Storage::find( $id ) ) {
			self::redirect( 'missing' );
		}
		self::redirect( $notice, $id );
	}

	public static function add_note() {
		$id   = self::authorize_post( 'webzero_leads_note' );
		$raw  = $_POST['lead_note'] ?? '';
		$note = is_string( $raw ) && strlen( $raw ) <= self::NOTE_LIMIT * 8 ? sanitize_textarea_field( wp_unslash( $raw ) ) : '';
		if ( '' === $note || mb_strlen( $note, 'UTF-8' ) > self::NOTE_LIMIT ) {
			self::redirect( 'invalid_note', $id );
		}
		if ( ! WebZero_Leads_Storage::add_note( $id, $note ) ) {
			if ( ! WebZero_Leads_Storage::find( $id ) ) {
				self::redirect( 'missing' );
			}
			self::redirect( 'error', $id );
		}
		self::redirect( 'note_added', $id );
	}

	public static function delete() {
		$id = self::authorize_post( 'webzero_leads_delete' );
		if ( 'yes' !== ( $_POST['confirm_delete'] ?? '' ) ) {
			self::redirect( 'confirm', $id );
		}

		$result = WebZero_Leads_Storage::delete( $id );
		if ( false === $result ) {
			self::redirect( 'error', $id );
		}
		self::redirect( 1 === $result ? 'deleted' : 'missing' );
	}

	public static function render() {
		self::authorize();
		$id = self::requested_lead();
		?>
		<div class="wrap<?php echo $id ? '' : ' webzero-leads-dashboard'; ?>">
			<h1>WebZero Leads</h1>
			<?php self::notice(); ?>
			<?php if ( $id ) : ?>
				<?php self::render_detail( $id ); ?>
			<?php else : ?>
				<?php WebZero_Leads_Dashboard::render( self::PAGE ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function notice() {
		$notices = array(
			'updated'           => __( 'Estado guardado.', 'webzero-leads' ),
			'deleted'           => __( 'Solicitud eliminada.', 'webzero-leads' ),
			'missing'           => __( 'La solicitud ya no está disponible.', 'webzero-leads' ),
			'invalid_status'    => __( 'Selecciona un estado válido.', 'webzero-leads' ),
			'follow_up_saved'   => __( 'Fecha de seguimiento guardada.', 'webzero-leads' ),
			'invalid_follow_up' => __( 'Introduce una fecha de seguimiento válida.', 'webzero-leads' ),
			'note_added'        => __( 'Nota añadida al historial.', 'webzero-leads' ),
			'invalid_note'      => __( 'Introduce una nota de hasta 3000 caracteres.', 'webzero-leads' ),
			'confirm'           => __( 'Confirma la eliminación antes de continuar.', 'webzero-leads' ),
			'error'             => __( 'No se pudo completar la operación.', 'webzero-leads' ),
		);
		$key = is_string( $_GET['webzero_notice'] ?? null ) ? sanitize_key( wp_unslash( $_GET['webzero_notice'] ) ) : '';
		if ( isset( $notices[ $key ] ) ) {
			$type = in_array( $key, array( 'updated', 'deleted', 'follow_up_saved', 'note_added' ), true ) ? 'success' : 'warning';
			printf( '<div class="notice notice-%s"><p>%s</p></div>', esc_attr( $type ), esc_html( $notices[ $key ] ) );
		}
	}

	public static function render_pagination( $page, $total, $parameter, $args = array() ) {
		if ( $total > 1 ) :
			?>
			<nav aria-label="<?php esc_attr_e( 'Paginación', 'webzero-leads' ); ?>" class="tablenav">
				<?php
				$base = str_replace( $parameter . '=999999999', $parameter . '=%#%', add_query_arg( $parameter, 999999999, self::url( $args ) ) );
				echo wp_kses_post( paginate_links( array( 'base' => $base, 'format' => '', 'current' => $page, 'total' => $total, 'add_args' => false ) ) );
				?>
			</nav>
			<?php
		endif;
	}

	public static function date( $date ) {
		return get_date_from_gmt( $date, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	}

	public static function follow_up_label( $date ) {
		return $date ? mysql2date( get_option( 'date_format' ), $date . ' 00:00:00' ) : __( 'Sin fecha', 'webzero-leads' );
	}

	private static function render_detail( $id ) {
		$lead = WebZero_Leads_Storage::find( $id );
		?>
		<p><a href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Volver al listado', 'webzero-leads' ); ?></a></p>
		<?php if ( ! $lead ) : ?>
			<p><?php esc_html_e( 'La solicitud no está disponible.', 'webzero-leads' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<h2><?php echo esc_html( $lead['name'] ); ?></h2>
		<dl>
			<dt><strong><?php esc_html_e( 'Correo electrónico', 'webzero-leads' ); ?></strong></dt>
			<dd><?php echo esc_html( $lead['email'] ); ?></dd>
			<dt><strong><?php esc_html_e( 'Teléfono', 'webzero-leads' ); ?></strong></dt>
			<dd><?php echo esc_html( $lead['phone'] ?? __( 'Sin teléfono', 'webzero-leads' ) ); ?></dd>
			<dt><strong><?php esc_html_e( 'Servicio', 'webzero-leads' ); ?></strong></dt>
			<dd><?php echo esc_html( $lead['service'] ); ?></dd>
			<dt><strong><?php esc_html_e( 'Fecha de recepción', 'webzero-leads' ); ?></strong></dt>
			<dd><?php echo esc_html( self::date( $lead['created_at'] ) ); ?></dd>
		</dl>
		<h3><?php esc_html_e( 'Mensaje', 'webzero-leads' ); ?></h3>
		<p style="white-space: pre-wrap; overflow-wrap: anywhere"><?php echo esc_html( $lead['message'] ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="webzero_leads_update">
			<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $id ); ?>">
			<?php wp_nonce_field( 'webzero_leads_update_' . $id ); ?>
			<label for="webzero-lead-status"><strong><?php esc_html_e( 'Estado comercial', 'webzero-leads' ); ?></strong></label>
			<select id="webzero-lead-status" name="lead_status">
				<?php foreach ( WebZero_Leads_Storage::statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $lead['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Guardar estado', 'webzero-leads' ), 'primary', 'submit', false ); ?>
		</form>
		<?php self::render_follow_up( $lead ); ?>
		<hr>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="webzero_leads_delete">
			<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $id ); ?>">
			<?php wp_nonce_field( 'webzero_leads_delete_' . $id ); ?>
			<p><label><input type="checkbox" name="confirm_delete" value="yes" required> <?php esc_html_e( 'Confirmo que quiero eliminar esta solicitud definitivamente.', 'webzero-leads' ); ?></label></p>
			<?php submit_button( __( 'Eliminar solicitud', 'webzero-leads' ), 'delete', 'submit', false ); ?>
		</form>
		<?php
	}

	private static function render_follow_up( $lead ) {
		$id = (int) $lead['id'];
		?>
		<hr>
		<h3><?php esc_html_e( 'Seguimiento', 'webzero-leads' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="webzero_leads_follow_up">
			<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $id ); ?>">
			<?php wp_nonce_field( 'webzero_leads_follow_up_' . $id ); ?>
			<label for="webzero-next-follow-up"><strong><?php esc_html_e( 'Próximo contacto', 'webzero-leads' ); ?></strong></label>
			<input type="date" id="webzero-next-follow-up" name="next_follow_up" min="1000-01-01" max="9999-12-31" value="<?php echo esc_attr( $lead['next_follow_up'] ?? '' ); ?>" aria-describedby="webzero-follow-up-help">
			<?php submit_button( __( 'Guardar fecha', 'webzero-leads' ), 'secondary', 'submit', false ); ?>
			<p id="webzero-follow-up-help" class="description"><?php esc_html_e( 'Deja la fecha vacía para quitar el seguimiento programado.', 'webzero-leads' ); ?></p>
		</form>
		<h4><?php esc_html_e( 'Notas internas', 'webzero-leads' ); ?></h4>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="webzero_leads_note">
			<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $id ); ?>">
			<?php wp_nonce_field( 'webzero_leads_note_' . $id ); ?>
			<p><label for="webzero-lead-note"><?php esc_html_e( 'Nueva nota', 'webzero-leads' ); ?></label></p>
			<textarea id="webzero-lead-note" name="lead_note" class="large-text" rows="3" maxlength="<?php echo esc_attr( (string) self::NOTE_LIMIT ); ?>" required></textarea>
			<?php submit_button( __( 'Añadir nota', 'webzero-leads' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php self::render_notes( $id ); ?>
		<?php
	}

	private static function render_notes( $id ) {
		$count = WebZero_Leads_Storage::count_notes( $id );
		if ( null === $count ) {
			echo '<p>' . esc_html__( 'No se pudo consultar el historial.', 'webzero-leads' ) . '</p>';
			return;
		}
		if ( 0 === (int) $count ) {
			echo '<p>' . esc_html__( 'Todavía no hay notas.', 'webzero-leads' ) . '</p>';
			return;
		}
		$total     = (int) ceil( (int) $count / self::PAGE_SIZE );
		$requested = is_string( $_GET['note_page'] ?? null ) ? absint( $_GET['note_page'] ) : 1;
		$page      = max( 1, min( $total, $requested ) );
		$notes     = WebZero_Leads_Storage::list_notes( $id, self::PAGE_SIZE, ( $page - 1 ) * self::PAGE_SIZE );
		if ( ! $notes ) {
			echo '<p>' . esc_html__( 'No se pudo consultar el historial.', 'webzero-leads' ) . '</p>';
			return;
		}
		?>
		<h4><?php esc_html_e( 'Historial de notas', 'webzero-leads' ); ?></h4>
		<ul>
			<?php foreach ( $notes as $note ) : ?>
				<li>
					<strong><?php echo esc_html( self::date( $note['created_at'] ) ); ?></strong>
					<p style="white-space: pre-wrap; overflow-wrap: anywhere"><?php echo esc_html( $note['note'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		self::render_pagination( $page, $total, 'note_page', array( 'lead_id' => $id ) );
	}
}
