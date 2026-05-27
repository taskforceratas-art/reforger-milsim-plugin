<?php
/**
 * DAGR Handler — Mapa Tactico en tiempo real
 * @package ReforgerMilsimManagement
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RMM_DAGR_Handler {

	public function __construct() {
		add_shortcode( 'rmm_tactical_map', array( $this, 'render_tactical_map' ) );
		add_action( 'init', array( $this, 'ensure_table' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
	}

	public function ensure_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'rmm_dagr_maps';
		$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
		if ( $exists !== $table ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			RMM_DB_Handler::create_tables();
		}
	}

	public function register_rest_endpoints() {
		register_rest_route( 'clan/v1', '/dagr/positions', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_positions' ),
			'permission_callback' => '__return_true',
		));
	}

	public function get_positions( $request ) {
		$map = sanitize_text_field( $request->get_param( 'map' ) ?: '' );
		$players = array();

		$users = get_users( array( 'number' => 100 ) );
		foreach ( $users as $user ) {
			$px = get_user_meta( $user->ID, 'rmm_pos_x', true );
			$py = get_user_meta( $user->ID, 'rmm_pos_y', true );
			$pm = get_user_meta( $user->ID, 'rmm_map', true );

			if ( $px === '' || $py === '' ) continue;
			if ( $map && $pm && $pm !== $map ) continue;

			$players[] = array(
				'id'       => $user->ID,
				'name'     => $user->display_name,
				'pos_x'    => floatval( $px ),
				'pos_y'    => floatval( $py ),
				'pos_z'    => floatval( get_user_meta( $user->ID, 'rmm_pos_z', true ) ?: 0 ),
				'heading'  => floatval( get_user_meta( $user->ID, 'rmm_heading', true ) ?: 0 ),
				'steamid'  => get_user_meta( $user->ID, 'steamid_64', true ),
			);
		}

		return rest_ensure_response( array( 'players' => $players, 'count' => count( $players ) ) );
	}

	public function render_tactical_map( $atts ) {
		global $wpdb;
		$atts = shortcode_atts( array( 'height' => '600px', 'mode' => 'global' ), $atts );

		// Buscar sesion activa
		$sessions = $wpdb->prefix . 'rmm_match_sessions';
		$active = $wpdb->get_row( "SELECT * FROM $sessions WHERE ended_at IS NULL ORDER BY started_at DESC LIMIT 1" );

		if ( ! $active ) {
			return '<div style="background:#0d1117;border:1px solid #21262d;border-radius:8px;padding:24px;text-align:center;color:#8b949e;font-family:Inter,sans-serif;">Sin señal GPS: No hay partida activa.</div>';
		}

		$map_name = ! empty( $active->scenario_name ) ? sanitize_title( $active->scenario_name ) : '';
		if ( ! $map_name && ! empty( $active->scenario_id ) ) {
			$map_name = sanitize_title( basename( $active->scenario_id ) );
		}

		// Buscar mapa en la BD de DAGR
		$dagr_table = $wpdb->prefix . 'rmm_dagr_maps';
		$map_config = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $dagr_table WHERE enabled = 1 AND map_name = %s", $map_name
		) );

		if ( ! $map_config ) {
			return '<div style="background:#0d1117;border:1px solid #21262d;border-radius:8px;padding:24px;text-align:center;color:#8b949e;font-family:Inter,sans-serif;">Sin señal GPS: Mapa no reconocido <strong>' . esc_html( $active->scenario_name ?: $map_name ) . '</strong></div>';
		}

		$tiles_url = ! empty( $map_config->tiles_path )
			? $map_config->tiles_path . '/{z}/{x}/{y}.png'
			: content_url( 'uploads/maps/' . $map_name . '/{z}/{x}/{y}.png' );

		$uid = 'dagr-map-' . uniqid();

		wp_enqueue_style( 'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
		wp_enqueue_script( 'leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

		ob_start();
		?>
		<div id="<?php echo $uid; ?>" style="width:100%;height:<?php echo esc_attr( $atts['height'] ); ?>;background:#0d1117;border:1px solid #21262d;border-radius:8px;"></div>
		<script>
		(function() {
			var container = document.getElementById('<?php echo $uid; ?>');
			if (!container || typeof L === 'undefined') return;

			var scale = <?php echo floatval( $map_config->scale_factor ); ?>;
			var edgeOffset = <?php echo intval( $map_config->edge_offset ); ?>;
			var maxZoom = <?php echo intval( $map_config->max_zoom ); ?>;
			var minX = <?php echo floatval( $map_config->min_x ); ?>;
			var minY = <?php echo floatval( $map_config->min_y ); ?>;
			var maxX = <?php echo floatval( $map_config->max_x ); ?>;
			var maxY = <?php echo floatval( $map_config->max_y ); ?>;
			var mode = '<?php echo esc_js( $atts['mode'] ); ?>';
			var currentUserId = <?php echo get_current_user_id(); ?>;
			var tilesUrl = '<?php echo esc_url( $tiles_url ); ?>';

			// CRS personalizado
			L.CRS.CustomSimple = L.Util.extend({}, L.CRS, {
				projection: L.Projection.LonLat,
				transformation: new L.Transformation(scale, 0, -scale, 0),
				scale: function(z) { return Math.pow(2, z); },
				zoom: function(s) { return Math.log(s) / Math.LN2; },
				distance: function(a, b) { return Math.sqrt(Math.pow(b.lng-a.lng,2) + Math.pow(b.lat-a.lat,2)); },
				infinite: true
			});

			// TileLayer con Y invertido
			L.TileLayer.InvertedY = L.TileLayer.extend({
				getTileUrl: function(c) {
					c.y = -(c.y + 1);
					return L.TileLayer.prototype.getTileUrl.call(this, c);
				}
			});

			var map = L.map(container, {
				crs: L.CRS.CustomSimple,
				zoom: 3,
				center: [(minY+maxY)/2 + edgeOffset, (minX+maxX)/2 + edgeOffset],
				zoomControl: true,
				attributionControl: false
			});

			new L.TileLayer.InvertedY(tilesUrl, {
				maxZoom: maxZoom,
				minZoom: 0,
				zoomReverse: true,
				bounds: L.latLngBounds(
					[minY + edgeOffset, minX + edgeOffset],
					[maxY + edgeOffset, maxX + edgeOffset]
				)
			}).addTo(map);

			// Conversion de coordenadas de juego a LatLng
			function gameToLatLng(x, y) {
				return L.latLng([y + edgeOffset, x + edgeOffset]);
			}

			// Marcadores de jugadores
			var playerMarkers = {};
			var playerIcons = {};

			function updatePositions() {
				var url = '<?php echo rest_url( 'clan/v1/dagr/positions' ); ?>?map=<?php echo urlencode( $map_name ); ?>';
				fetch(url).then(function(r) { return r.json(); }).then(function(data) {
					if (!data.players) return;
					var seen = {};

					data.players.forEach(function(p) {
						if (mode === 'personal' && p.id != currentUserId) return;
						seen[p.id] = true;
						var latlng = gameToLatLng(p.pos_x, p.pos_y);

						if (playerMarkers[p.id]) {
							playerMarkers[p.id].setLatLng(latlng);
						} else {
							var icon = L.divIcon({
								className: 'dagr-player-marker',
								html: '<div style="width:12px;height:12px;background:#849b4c;border:2px solid #fff;border-radius:50%;box-shadow:0 0 6px #849b4c;" title="' + p.name + '"></div>',
								iconSize: [12, 12],
								iconAnchor: [6, 6]
							});
							playerMarkers[p.id] = L.marker(latlng, { icon: icon }).addTo(map);
							playerMarkers[p.id].bindTooltip(p.name, { direction: 'top', offset: [0, -8] });
						}
					});

					// Eliminar marcadores de jugadores que ya no estan
					for (var id in playerMarkers) {
						if (!seen[id]) {
							map.removeLayer(playerMarkers[id]);
							delete playerMarkers[id];
						}
					}
				});
			}

			updatePositions();
			setInterval(updatePositions, 10000);
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}
