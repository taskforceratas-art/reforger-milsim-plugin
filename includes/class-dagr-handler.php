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
		add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );
	}

	public function ensure_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'rmm_dagr_maps';
		$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
		if ( $exists !== $table ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			RMM_DB_Handler::create_tables();
		}
		$this->insert_default_maps();
	}

	private function insert_default_maps() {
		global $wpdb;
		$table = $wpdb->prefix . 'rmm_dagr_maps';
		$defaults = array(
			array(
				'map_name' => 'everon', 'display_name' => 'Everon',
				'tiles_path' => 'https://reforger.recoil.org/map-tiles/everon/{z}/{x}/{y}/tile.jpg',
				'scale_factor' => 0.08, 'edge_offset' => 50,
				'min_x' => 0, 'min_y' => 0, 'max_x' => 12800, 'max_y' => 12800, 'max_zoom' => 5,
			),
			array(
				'map_name' => 'arland', 'display_name' => 'Arland',
				'tiles_path' => 'https://reforger.recoil.org/arland/LODS/{z}/{x}/{y}/tile.jpg',
				'scale_factor' => 0.08, 'edge_offset' => 50,
				'min_x' => 0, 'min_y' => 0, 'max_x' => 4000, 'max_y' => 4000, 'max_zoom' => 4,
			),
		);
		foreach ( $defaults as $map ) {
			$wpdb->replace( $table, $map );
		}
	}

	public function register_rest_endpoints() {
		register_rest_route( 'clan/v1', '/dagr/positions', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_positions' ),
			'permission_callback' => '__return_true',
		));
		register_rest_route( 'clan/v1', '/dagr/markers', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_markers' ),
			'permission_callback' => '__return_true',
		));
		register_rest_route( 'clan/v1', '/dagr/markers', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'receive_markers' ),
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

	public function get_markers( $request ) {
		$map = sanitize_text_field( $request->get_param( 'map' ) ?: '' );
		$key = 'dagr_markers_' . ( $map ?: 'all' );
		$markers = get_transient( $key );
		if ( ! is_array( $markers ) ) $markers = array();
		return rest_ensure_response( array( 'markers' => $markers, 'count' => count( $markers ) ) );
	}

	public function receive_markers( $request ) {
		$body = $request->get_body();
		$data = json_decode( $body, true );
		if ( ! $data ) {
			return new WP_REST_Response( array( 'error' => 'JSON invalido' ), 400 );
		}

		$map = sanitize_text_field( $data['map'] ?? '' );
		$markers = $data['markers'] ?? array();
		if ( empty( $markers ) ) {
			return new WP_REST_Response( array( 'error' => 'No markers provided' ), 400 );
		}

		// Validar y limpiar cada marker
		$clean = array();
		foreach ( $markers as $m ) {
			$clean[] = array(
				'id'     => sanitize_text_field( $m['id'] ?? uniqid('m') ),
				'type'   => sanitize_text_field( $m['type'] ?? 'marker' ),
				'label'  => sanitize_text_field( $m['label'] ?? '' ),
				'pos_x'  => floatval( $m['pos_x'] ?? 0 ),
				'pos_y'  => floatval( $m['pos_y'] ?? 0 ),
				'color'  => sanitize_text_field( $m['color'] ?? '#d2a850' ),
				'author' => sanitize_text_field( $m['author'] ?? '' ),
				'time'   => current_time( 'mysql' ),
			);
		}

		$key = 'dagr_markers_' . ( $map ?: 'all' );
		set_transient( $key, $clean, 3600 ); // 1 hora

		return rest_ensure_response( array( 'success' => true, 'saved' => count( $clean ) ) );
	}

	public function render_tactical_map( $atts ) {
		global $wpdb;
		$atts = shortcode_atts( array( 'height' => '600px', 'map' => '', 'markers' => '', 'positions' => '', 'id' => '' ), $atts );

		// Si hay ID, cargar preset de BD
		if ( ! empty( $atts['id'] ) ) {
			$presets_table = $wpdb->prefix . 'rmm_dagr_presets';
			$preset = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $presets_table WHERE id = %d", intval( $atts['id'] ) ) );
			if ( $preset ) {
				$atts['map']       = $preset->map_name;
				$atts['markers']   = $preset->markers;
				$atts['positions'] = $preset->positions;
				$atts['height']    = $preset->height;
			}
		}

		// Los JSON se pasan en base64 para evitar conflictos con comillas del shortcode parser
		$markers_raw = ! empty( $atts['markers'] ) ? base64_decode( $atts['markers'] ) : '';
		$positions_raw = ! empty( $atts['positions'] ) ? base64_decode( $atts['positions'] ) : '';
		$static_markers = $markers_raw ? json_decode( $markers_raw, true ) : array();
		$static_positions = $positions_raw ? json_decode( $positions_raw, true ) : array();
		if ( ! is_array( $static_markers ) ) $static_markers = array();
		if ( ! is_array( $static_positions ) ) $static_positions = array();
		$has_static_data = ! empty( $static_markers ) || ! empty( $static_positions );

		$map_name = sanitize_text_field( $atts['map'] );
		$active = null;

		// Si no se especifica mapa, buscar sesion activa
		if ( empty( $map_name ) ) {
			$sessions = $wpdb->prefix . 'rmm_match_sessions';
			$active = $wpdb->get_row( "SELECT * FROM $sessions WHERE ended_at IS NULL ORDER BY started_at DESC LIMIT 1" );

			if ( ! $active ) {
				return '<div style="background:#0d1117;border:1px solid #21262d;border-radius:8px;padding:24px;text-align:center;color:#8b949e;font-family:Inter,sans-serif;">Sin señal GPS: No hay partida activa. Usa <code>[rmm_tactical_map map="everon"]</code></div>';
			}

			$map_name = ! empty( $active->scenario_name ) ? sanitize_title( $active->scenario_name ) : '';
			if ( ! $map_name && ! empty( $active->scenario_id ) ) {
				$map_name = sanitize_title( basename( $active->scenario_id ) );
			}
		}

		// Buscar mapa en la BD de DAGR
		$dagr_table = $wpdb->prefix . 'rmm_dagr_maps';
		$map_config = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $dagr_table WHERE enabled = 1 AND map_name = %s", $map_name
		) );

		if ( ! $map_config ) {
			$display = $active ? ( $active->scenario_name ?: $map_name ) : $map_name;
			return '<div style="background:#0d1117;border:1px solid #21262d;border-radius:8px;padding:24px;text-align:center;color:#8b949e;font-family:Inter,sans-serif;">Sin señal GPS: Mapa no reconocido <strong>' . esc_html( $display ) . '</strong></div>';
		}

		$tiles_url = ! empty( $map_config->tiles_path )
			? $map_config->tiles_path
			: content_url( 'uploads/maps/' . $map_name . '/LODS/{z}/{x}/{y}/tile.jpg' );

		// Si el path es local, ver si existe; si no, usar CDN
		$local_path = WP_CONTENT_DIR . '/uploads/maps/' . $map_name . '/LODS/4/4/4/tile.jpg';
		$local_fallback = content_url( 'uploads/maps/' . $map_name . '/LODS/{z}/{x}/{y}/tile.jpg' );
		if ( empty( $map_config->tiles_path ) ) {
			// Si no hay path configurado, usar local si existe, sino CDN
			if ( file_exists( $local_path ) ) {
				$tiles_url = $local_fallback;
			} else {
				$cdn_fallbacks = array(
					'everon' => 'https://reforger.recoil.org/map-tiles/everon/{z}/{x}/{y}/tile.jpg',
					'arland' => 'https://reforger.recoil.org/map-tiles/arland/{z}/{x}/{y}/tile.jpg',
				);
				if ( isset( $cdn_fallbacks[ $map_name ] ) ) {
					$tiles_url = $cdn_fallbacks[ $map_name ];
				}
			}
		}

		$uid = 'dagr-map-' . uniqid();

		wp_enqueue_style( 'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );

		ob_start();
		?>
		<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
		<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
		<div id="<?php echo $uid; ?>" style="width:100%;height:<?php echo esc_attr( $atts['height'] ); ?>;background:#0d1117;border:1px solid #21262d;border-radius:8px;position:relative;">
			<div class="dagr-mode-toggle" style="position:absolute;top:10px;right:10px;z-index:1000;display:flex;gap:4px;">
				<button class="dagr-mode-btn active" data-mode="personal" style="background:#1a1d21;color:#849b4c;border:1px solid #849b4c;padding:6px 12px;border-radius:4px;cursor:pointer;font-size:0.7rem;font-weight:700;text-transform:uppercase;font-family:Inter,sans-serif;">👤 Yo</button>
				<button class="dagr-mode-btn" data-mode="global" style="background:#1a1d21;color:#555;border:1px solid #333;padding:6px 12px;border-radius:4px;cursor:pointer;font-size:0.7rem;font-weight:700;text-transform:uppercase;font-family:Inter,sans-serif;">🌍 Global</button>
			</div>
		</div>
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
			var mode = localStorage.getItem('dagr_mode') || 'personal';
			var currentUserId = <?php echo get_current_user_id() ?: 0; ?>;
			var tilesUrl = '<?php echo esc_js( $tiles_url ); ?>';

			/* DATOS ESTATICOS via shortcode */
			var staticMarkers = <?php echo json_encode( $static_markers ); ?>;
			var staticPositions = <?php echo json_encode( $static_positions ); ?>;
			var hasStaticData = <?php echo $has_static_data ? 'true' : 'false'; ?>;

			// Toggle buttons
			var toggleContainer = container.querySelector('.dagr-mode-toggle');
			toggleContainer.querySelectorAll('.dagr-mode-btn').forEach(function(btn) {
				var m = btn.dataset.mode;
				if (m === mode) {
					btn.classList.add('active');
					btn.style.color = '#849b4c';
					btn.style.borderColor = '#849b4c';
				} else {
					btn.classList.remove('active');
					btn.style.color = '#555';
					btn.style.borderColor = '#333';
				}
			});

			container.addEventListener('click', function(e) {
				var btn = e.target.closest('.dagr-mode-btn');
				if (!btn) return;
				mode = btn.dataset.mode;
				localStorage.setItem('dagr_mode', mode);
				toggleContainer.querySelectorAll('.dagr-mode-btn').forEach(function(b) {
					b.classList.remove('active');
					b.style.color = '#555';
					b.style.borderColor = '#333';
				});
				btn.classList.add('active');
				btn.style.color = '#849b4c';
				btn.style.borderColor = '#849b4c';
				updatePositions();
			});

			// CRS personalizado de EnfusionMapMaker (1/12.5 scale, InvertedY, zoomReverse)
			L.CRS.CustomSimple = L.Util.extend({}, L.CRS, {
				projection: L.Projection.LonLat,
				transformation: new L.Transformation(1/12.5, 0, -1/12.5, 0),
				scale: function(z) { return Math.pow(2, z); },
				zoom: function(s) { return Math.log(s) / Math.LN2; },
				distance: function(a, b) { return Math.sqrt(Math.pow(b.lng-a.lng,2) + Math.pow(b.lat-a.lat,2)); },
				infinite: true
			});

			// Invertir Y para tiles LODS (igual que el juego)
			L.TileLayer.InvertedY = L.TileLayer.extend({
				getTileUrl: function(c) {
					c.y = -(c.y + 1);
					return L.TileLayer.prototype.getTileUrl.call(this, c);
				}
			});

			// Bounds del mapa Everon (0,0 a 12800,12800 + offset 50)
			var bounds = L.latLngBounds(
				L.latLng([0 + edgeOffset, 0 + edgeOffset]),
				L.latLng([maxY + edgeOffset, maxX + edgeOffset])
			);

			var map = L.map(container, {
				crs: L.CRS.CustomSimple,
				zoom: 3,
				center: bounds.getCenter(),
				maxZoom: maxZoom,
				minZoom: 0,
				zoomControl: true,
				attributionControl: false
			});

			new L.TileLayer.InvertedY(tilesUrl, {
				maxZoom: maxZoom,
				minZoom: 0,
				zoomReverse: true,
				bounds: bounds,
				errorTileUrl: ''
			}).addTo(map);

			// Conversion de coordenadas de juego (EnfusionMapMaker: +50 offset)
			function gameToLatLng(x, y) {
				return L.latLng([y + edgeOffset, x + edgeOffset]);
			}

			// Marcadores de jugadores
			var playerMarkers = {};
			var playerIcons = {};

			function updatePositions() {
				if ( hasStaticData ) {
					staticPositions.forEach(function(p) {
						var latlng = gameToLatLng(p.pos_x, p.pos_y);
						var color = p.color || '#58a6ff';
						var size = '10px';
						var icon = L.divIcon({
							className: 'dagr-player-marker',
							html: '<div style="width:'+size+';height:'+size+';background:'+color+';border:2px solid #fff;border-radius:50%;box-shadow:0 0 8px ' + color + ';" title="' + (p.name||'') + '"></div>',
							iconSize: [14,14],
							iconAnchor: [7,7]
						});
						L.marker(latlng, { icon: icon }).addTo(map).bindTooltip(p.name||'', { direction:'top', offset:[0,-8] });
					});
					return;
				}
				var url = '<?php echo rest_url( 'clan/v1/dagr/positions' ); ?>?map=<?php echo urlencode( $map_name ); ?>';
				fetch(url).then(function(r) { return r.json(); }).then(function(data) {
					if (!data.players) return;
					var seen = {};

					data.players.forEach(function(p) {
						if (mode === 'personal' && p.id != currentUserId) return;
						seen[p.id] = true;
						var latlng = gameToLatLng(p.pos_x, p.pos_y);
						var isMe = (p.id == currentUserId);

						if (playerMarkers[p.id]) {
							playerMarkers[p.id].setLatLng(latlng);
						} else {
							var color = isMe ? '#849b4c' : '#58a6ff';
							var size = isMe ? '14px' : '10px';
							var icon = L.divIcon({
								className: 'dagr-player-marker',
								html: '<div style="width:' + size + ';height:' + size + ';background:' + color + ';border:2px solid #fff;border-radius:50%;box-shadow:0 0 8px ' + color + ';" title="' + p.name + '"></div>',
								iconSize: [parseInt(size)+4, parseInt(size)+4],
								iconAnchor: [(parseInt(size)+4)/2, (parseInt(size)+4)/2]
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

			// === Marcadores de mapa (objetivos, POIs) ===
			var mapMarkers = {};
			var markerColors = {
				'objective': '#22c55e',
				'completed': '#d2a850',
				'danger': '#ef4444',
				'info': '#58a6ff',
				'marker': '#a371f7'
			};
			var markerIcons = {
				'objective': '<div style="width:16px;height:16px;background:#22c55e;border:2px solid #fff;border-radius:3px;transform:rotate(45deg);box-shadow:0 0 8px rgba(34,197,94,0.5);"></div>',
				'completed': '<div style="width:14px;height:14px;background:#d2a850;border:2px solid #fff;border-radius:50%;box-shadow:0 0 8px rgba(210,168,80,0.5);"></div>',
				'danger': '<div style="width:0;height:0;border-left:8px solid transparent;border-right:8px solid transparent;border-bottom:16px solid #ef4444;filter:drop-shadow(0 0 4px rgba(239,68,68,0.5));"></div>',
				'info': '<div style="width:14px;height:14px;background:#58a6ff;border:2px solid #fff;border-radius:2px;box-shadow:0 0 8px rgba(88,166,255,0.5);"></div>',
				'marker': '<div style="width:12px;height:12px;background:#a371f7;border:2px solid #fff;border-radius:50%;box-shadow:0 0 8px rgba(163,113,247,0.5);"></div>'
			};

			function updateMapMarkers() {
				if ( hasStaticData ) {
					staticMarkers.forEach(function(m) {
						var latlng = gameToLatLng(m.pos_x, m.pos_y);
						var html = markerIcons[m.type] || markerIcons['marker'];
						var icon = L.divIcon({ className: 'dagr-map-marker', html: html, iconSize: [20,20], iconAnchor: [10,10] });
						L.marker(latlng, { icon: icon }).addTo(map).bindTooltip(m.label || m.type, { direction:'top', offset:[0,-12] });
					});
					return;
				}
				var url = '<?php echo rest_url( 'clan/v1/dagr/markers' ); ?>?map=<?php echo urlencode( $map_name ); ?>';
				fetch(url).then(function(r) { return r.json(); }).then(function(data) {
					if (!data.markers) return;
					var seen = {};

					data.markers.forEach(function(m) {
						seen[m.id] = true;
						var latlng = gameToLatLng(m.pos_x, m.pos_y);

						if (mapMarkers[m.id]) {
							mapMarkers[m.id].setLatLng(latlng);
						} else {
							var html = markerIcons[m.type] || markerIcons['marker'];
							var icon = L.divIcon({
								className: 'dagr-map-marker',
								html: html,
								iconSize: [20, 20],
								iconAnchor: [10, 10]
							});
							mapMarkers[m.id] = L.marker(latlng, { icon: icon }).addTo(map);
							var tip = m.label ? (m.label + (m.author ? ' - ' + m.author : '')) : '';
							if (tip) mapMarkers[m.id].bindTooltip(tip, { direction: 'top', offset: [0, -12] });
						}
					});

					for (var id in mapMarkers) {
						if (!seen[id]) {
							map.removeLayer(mapMarkers[id]);
							delete mapMarkers[id];
						}
					}
				});
			}

			updateMapMarkers();
			if ( ! hasStaticData ) {
				setInterval(updatePositions, 10000);
				setInterval(updateMapMarkers, 15000);
			}
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	public function register_admin_pages() {
		add_submenu_page(
			'rmm-dashboard',
			__( 'Mapas DAGR', 'reforger-milsim' ),
			__( '🗺️ Mapas DAGR', 'reforger-milsim' ),
			'manage_options',
			'rmm-dagr-maps',
			array( $this, 'render_admin_page' )
		);
	}

	public function render_admin_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'rmm_dagr_presets';

		// Handle save
		if ( isset( $_POST['rmm_dagr_save'] ) && check_admin_referer( 'rmm_dagr_nonce' ) ) {
			$id = intval( $_POST['preset_id'] );
			$data = array(
				'title'     => sanitize_text_field( $_POST['title'] ),
				'map_name'  => sanitize_text_field( $_POST['map_name'] ),
				'markers'   => wp_unslash( $_POST['markers'] ),
				'positions' => wp_unslash( $_POST['positions'] ),
				'height'    => sanitize_text_field( $_POST['height'] ),
			);
			if ( $id > 0 ) {
				$wpdb->update( $table, $data, array( 'id' => $id ) );
			} else {
				$wpdb->insert( $table, $data );
				$id = $wpdb->insert_id;
			}
			echo '<div class="notice notice-success"><p>Mapa guardado. Shortcode: <code>[rmm_tactical_map id="' . $id . '"]</code></p></div>';
		}

		// Handle delete
		if ( isset( $_GET['delete'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'rmm_dagr_delete' ) ) {
			$wpdb->delete( $table, array( 'id' => intval( $_GET['delete'] ) ) );
			echo '<div class="notice notice-success"><p>Mapa eliminado.</p></div>';
		}

		// Load presets
		$presets = $wpdb->get_results( "SELECT * FROM $table ORDER BY updated_at DESC" );

		// Load for edit
		$editing = null;
		if ( isset( $_GET['edit'] ) ) {
			$editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", intval( $_GET['edit'] ) ) );
		}

		?>
		<div class="wrap">
			<h1>🗺️ Mapas DAGR</h1>

			<div class="card" style="max-width:100%; padding:20px; margin-bottom:20px;">
				<h2><?php echo $editing ? 'Editar' : 'Nuevo'; ?> Mapa</h2>
				<form method="post">
					<?php wp_nonce_field( 'rmm_dagr_nonce' ); ?>
					<input type="hidden" name="preset_id" value="<?php echo $editing ? $editing->id : 0; ?>">
					<table class="form-table">
						<tr><th>Título</th><td><input type="text" name="title" value="<?php echo $editing ? esc_attr($editing->title) : ''; ?>" class="regular-text" required></td></tr>
						<tr><th>Mapa</th><td><select name="map_name"><option value="everon" <?php selected( $editing ? $editing->map_name : 'everon', 'everon' ); ?>>Everon</option><option value="arland" <?php selected( $editing ? $editing->map_name : '', 'arland' ); ?>>Arland</option></select></td></tr>
						<tr><th>Marcadores (JSON)</th><td><textarea name="markers" class="large-text" rows="6"><?php echo $editing ? esc_textarea($editing->markers) : '[{"id":"obj1","type":"objective","label":"Base","pos_x":5000,"pos_y":3000}]'; ?></textarea><p class="description">Array JSON. Tipos: objective, completed, danger, info, marker. Campos: id, type, label, pos_x, pos_y</p></td></tr>
						<tr><th>Posiciones (JSON)</th><td><textarea name="positions" class="large-text" rows="4"><?php echo $editing ? esc_textarea($editing->positions) : '[]'; ?></textarea><p class="description">Array JSON. Campos: name, pos_x, pos_y, color</p></td></tr>
						<tr><th>Altura</th><td><input type="text" name="height" value="<?php echo $editing ? esc_attr($editing->height) : '600px'; ?>" class="small-text"></td></tr>
					</table>
					<p class="submit">
						<button type="submit" name="rmm_dagr_save" class="button button-primary">Guardar</button>
						<?php if ( $editing ) : ?>
							<a href="?page=rmm-dagr-maps" class="button">Cancelar</a>
						<?php endif; ?>
					</p>
				</form>
			</div>

			<div class="card" style="max-width:100%; padding:20px;">
				<h2>Mapas Guardados</h2>
				<table class="wp-list-table widefat striped">
					<thead><tr><th>ID</th><th>Título</th><th>Mapa</th><th>Shortcode</th><th>Acciones</th></tr></thead>
					<tbody>
					<?php if ( empty( $presets ) ) : ?>
						<tr><td colspan="5">No hay mapas guardados.</td></tr>
					<?php else : foreach ( $presets as $p ) : ?>
						<tr>
							<td><?php echo $p->id; ?></td>
							<td><?php echo esc_html( $p->title ); ?></td>
							<td><?php echo esc_html( $p->map_name ); ?></td>
							<td><code>[rmm_tactical_map id="<?php echo $p->id; ?>"]</code> <button class="button button-small" onclick="navigator.clipboard.writeText('[rmm_tactical_map id=&quot;<?php echo $p->id; ?>&quot;]')">📋 Copiar</button></td>
							<td>
								<a href="?page=rmm-dagr-maps&edit=<?php echo $p->id; ?>" class="button button-small">Editar</a>
								<a href="?page=rmm-dagr-maps&delete=<?php echo $p->id; ?>&_wpnonce=<?php echo wp_create_nonce('rmm_dagr_delete'); ?>" class="button button-small" onclick="return confirm('¿Eliminar?')">Eliminar</a>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
