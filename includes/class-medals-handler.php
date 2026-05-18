<?php
/**
 * Medals & Ribbon Rack Handler Class
 * @package ReforgerMilsimManagement
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RMM_Medals_Handler {

	public function __construct() {
		// Bloque 2: Estandarización de Imágenes
		add_action( 'init', array( $this, 'register_image_sizes' ) );
		
		// Bloque 1: Metabox de Prioridad Visual
		add_action( 'add_meta_boxes', array( $this, 'add_priority_metabox' ) );
		add_action( 'save_post', array( $this, 'save_priority_metabox' ) );

		// Bloque 3: Interfaz de Otorgamiento Manual (Backend)
		add_action( 'admin_menu', array( $this, 'register_medal_submenu' ) );
		
		// Bloque 4: El Pasador de Diario - Ribbon Rack (Frontend)
		add_shortcode( 'clan_pasador_medallas', array( $this, 'render_ribbon_rack' ) );
	}

	/**
	 * Bloque 2: Estandarización de Imágenes
	 */
	public function register_image_sizes() {
		add_image_size( 'metopa-militar', 120, 35, true );
	}

	/**
	 * Bloque 1: Metabox de Prioridad Visual
	 */
	public function add_priority_metabox() {
		add_meta_box(
			'rmm_medal_priority',
			__( 'Jerarquía Militar', 'reforger-milsim' ),
			array( $this, 'render_priority_metabox' ),
			'condecoraciones',
			'side',
			'default'
		);
	}

	public function render_priority_metabox( $post ) {
		$prioridad = get_post_meta( $post->ID, 'prioridad_visual', true );
		if ( $prioridad === '' ) $prioridad = 99; // Por defecto 99
		wp_nonce_field( 'rmm_save_medal_priority', 'rmm_medal_priority_nonce' );
		?>
		<p>
			<label for="prioridad_visual"><?php _e( 'Prioridad Visual (1 = Más alta):', 'reforger-milsim' ); ?></label>
			<input type="number" id="prioridad_visual" name="prioridad_visual" value="<?php echo esc_attr( $prioridad ); ?>" min="1" max="999" class="widefat">
		</p>
		<p class="description"><?php _e( 'Sirve para ordenar el pasador (ej. 1 es la medalla más alta, 99 la más baja).', 'reforger-milsim' ); ?></p>
		<?php
	}

	public function save_priority_metabox( $post_id ) {
		if ( ! isset( $_POST['rmm_medal_priority_nonce'] ) || ! wp_verify_nonce( $_POST['rmm_medal_priority_nonce'], 'rmm_save_medal_priority' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['prioridad_visual'] ) ) {
			update_post_meta( $post_id, 'prioridad_visual', intval( $_POST['prioridad_visual'] ) );
		}
	}

	/**
	 * Bloque 3: Interfaz de Otorgamiento Manual (Backend)
	 */
	public function register_medal_submenu() {
		add_submenu_page(
			'edit.php?post_type=condecoraciones',
			__( 'Otorgar Medalla', 'reforger-milsim' ),
			__( 'Otorgar Medalla', 'reforger-milsim' ),
			'manage_options',
			'otorgar-medalla',
			array( $this, 'render_award_medal_page' )
		);
	}

	public function render_award_medal_page() {
		if ( isset( $_POST['rmm_award_medal_nonce'] ) && wp_verify_nonce( $_POST['rmm_award_medal_nonce'], 'rmm_award_medal_action' ) ) {
			$this->process_manual_award();
		}

		$medallas = get_posts( array( 'post_type' => 'condecoraciones', 'numberposts' => -1 ) );
		?>
		<div class="wrap">
			<h1><?php _e( 'Otorgar Medalla al Operador', 'reforger-milsim' ); ?></h1>
			<form method="post" style="max-width: 600px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px;">
				<?php wp_nonce_field( 'rmm_award_medal_action', 'rmm_award_medal_nonce' ); ?>
				<p>
					<label><strong>Operador:</strong></label><br>
					<?php wp_dropdown_users( array( 'name' => 'usuario_id', 'class' => 'widefat' ) ); ?>
				</p>
				<p>
					<label><strong>Condecoración:</strong></label><br>
					<select name="condecoracion_id" class="widefat" required>
						<option value="">-- Selecciona Medalla --</option>
						<?php foreach ( $medallas as $m ) : ?>
							<option value="<?php echo $m->ID; ?>"><?php echo esc_html( $m->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label><strong>Motivo de la Citación:</strong></label><br>
					<textarea name="motivo" class="widefat" rows="5" required placeholder="Motivo..."></textarea>
				</p>
				<p>
					<button type="submit" class="button button-primary">Confirmar Otorgamiento</button>
				</p>
			</form>
		</div>
		<?php
	}

	private function process_manual_award() {
		global $wpdb;
		$table = $wpdb->prefix . 'operador_condecoraciones';
		$wpdb->insert(
			$table,
			array(
				'usuario_id'            => intval( $_POST['usuario_id'] ),
				'condecoracion_id'      => intval( $_POST['condecoracion_id'] ),
				'motivo'                => sanitize_textarea_field( $_POST['motivo'] ),
				'otorgada_por_admin_id' => get_current_user_id(),
				'fecha_obtenida'        => current_time( 'mysql' ),
			)
		);
		echo '<div class="notice notice-success is-dismissible"><p>Condecoración otorgada con éxito.</p></div>';
	}

	/**
	 * Bloque 4: El Pasador de Diario - Ribbon Rack (Frontend)
	 */
	public function render_ribbon_rack( $atts ) {
		global $wpdb;
		$atts = shortcode_atts( array( 'user_id' => '' ), $atts );
		$user_id = !empty($atts['user_id']) ? intval($atts['user_id']) : get_current_user_id();
		
		if ( ! $user_id ) return '';

		// Consulta con JOIN para ordenar por prioridad_visual (guardada en postmeta)
		$query = $wpdb->prepare(
			"SELECT oc.motivo, p.ID, p.post_title, pm.meta_value as prioridad
			 FROM {$wpdb->prefix}operador_condecoraciones oc
			 JOIN {$wpdb->posts} p ON oc.condecoracion_id = p.ID
			 LEFT JOIN {$wpdb->postmeta} pm ON oc.condecoracion_id = pm.post_id AND pm.meta_key = 'prioridad_visual'
			 WHERE oc.usuario_id = %d
			 ORDER BY CAST(COALESCE(pm.meta_value, 999) AS UNSIGNED) ASC, oc.fecha_obtenida DESC",
			$user_id
		);

		$medals = $wpdb->get_results( $query );
		if ( empty($medals) ) return '';

		ob_start();
		?>
		<div class="rmm-ribbon-rack-container" style="margin-top: 15px;">
			<!-- Usamos las clases de Tailwind solicitadas -->
			<div class="grid grid-cols-6 gap-0 max-w-fit bg-gray-900 border-2 border-gray-900 shadow-md">
				<?php foreach ( $medals as $m ) : 
					$thumb_url = get_the_post_thumbnail_url( $m->ID, 'metopa-militar' );
					if ( !$thumb_url ) $thumb_url = 'https://via.placeholder.com/120x35?text=Sin+Imagen';
					?>
					<img src="<?php echo esc_url($thumb_url); ?>" 
						 title="<?php echo esc_attr( $m->post_title . ' - ' . $m->motivo ); ?>"
						 class="w-full h-auto block object-cover"
						 style="width:120px; height:35px;" 
					>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
