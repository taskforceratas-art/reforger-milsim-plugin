<?php
/**
 * TFR Login Customizer — Estética MILSIM Táctico
 * 
 * Rediseño del wp-login.php con temática militar para [=TFR=].
 * Compatible con miniOrange Social Login (Discord).
 * 
 * Incluido desde: functions.php del tema hijo
 */

/* ===========================================
   VARIABLES DE COLOR TÁCTICAS
   =========================================== */
define( 'TFR_ACCENT', '#CFDC35' );     // Verde oliva táctico
define( 'TFR_DARK',  '#0d0e10' );      // Fondo principal
define( 'TFR_CARD',  '#141619' );      // Fondo tarjeta
define( 'TFR_BORDER','#1f2226' );      // Bordes
define( 'TFR_TEXT',  '#c9d1d9' );      // Texto
define( 'TFR_MUTED', '#555555' );      // Texto secundario

/* ===========================================
   LOGO — Usar el del personalizador o site_icon
   =========================================== */
function tfr_get_login_logo_url() {
	$custom_logo_id = get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$image = wp_get_attachment_image_src( $custom_logo_id, 'full' );
		if ( $image && ! empty( $image[0] ) ) return esc_url( $image[0] );
	}
	$site_icon_id = get_option( 'site_icon' );
	if ( $site_icon_id ) {
		$url = wp_get_attachment_image_url( $site_icon_id, 'full' );
		if ( $url ) return esc_url( $url );
	}
	return '';
}

$tfr_logo_url = tfr_get_login_logo_url();

/* ===========================================
   CONFIGURACIÓN
   =========================================== */
$tfr_login = array(
	'logo_url'           => $tfr_logo_url,
	'logo_link'          => home_url('/'),
	'logo_title'         => get_bloginfo('name'),
	'tagline'            => get_bloginfo('description'),
	'show_back_to_blog'  => false,
	'show_lost_password' => true,
	'show_language'      => false,
);

/* ===========================================
   ESTILOS PRINCIPALES
   =========================================== */
add_action( 'login_enqueue_scripts', function() use ( $tfr_login ) {
	?>
	<style>
		/* ── RESET Y FONDO TÁCTICO ── */
		body.login {
			background: <?php echo TFR_DARK; ?>;
			background-image:
				/* Scanlines sutiles */
				repeating-linear-gradient(
					0deg,
					transparent,
					transparent 2px,
					rgba(0,0,0,0.15) 2px,
					rgba(0,0,0,0.15) 4px
				),
				/* Grid táctico */
				linear-gradient(rgba(207,220,53,0.03) 1px, transparent 1px),
				linear-gradient(90deg, rgba(207,220,53,0.03) 1px, transparent 1px);
			background-size: 100% 4px, 40px 40px, 40px 40px;
			color: <?php echo TFR_TEXT; ?>;
			font-family: 'Inter', 'Helvetica Neue', system-ui, sans-serif;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
		}

		/* ── CONTENEDOR PRINCIPAL ── */
		#login {
			width: 380px;
			max-width: 92vw;
			padding: 0;
			margin: 0 auto;
		}

		/* ── LOGO ── */
		#login h1 {
			margin-bottom: 10px;
		}
		#login h1 a {
			background-image: url('<?php echo esc_url( $tfr_login["logo_url"] ); ?>');
			width: 100%;
			height: 90px;
			background-size: contain;
			background-repeat: no-repeat;
			background-position: center;
			padding-bottom: 0;
			margin: 0;
		}

		/* ── CLASIFICACIÓN TÁCTICA ── */
		.tfr-classification {
			text-align: center;
			margin-bottom: 20px;
		}
		.tfr-classification-bar {
			display: inline-block;
			font-family: 'JetBrains Mono', 'SF Mono', 'Fira Code', monospace;
			font-size: 0.7rem;
			font-weight: 700;
			color: <?php echo TFR_ACCENT; ?>;
			text-transform: uppercase;
			letter-spacing: 0.2em;
			padding: 6px 20px;
			border: 1px solid <?php echo TFR_BORDER; ?>;
			border-radius: 3px;
			background: rgba(207,220,53,0.05);
			margin-bottom: 5px;
		}
		.tfr-classification-tagline {
			font-size: 0.6rem;
			color: <?php echo TFR_MUTED; ?>;
			text-transform: uppercase;
			letter-spacing: 0.12em;
			display: block;
		}

		/* ── TARJETA DEL FORMULARIO ── */
		#loginform {
			background: linear-gradient(180deg, <?php echo TFR_CARD; ?> 0%, rgba(20,22,25,0.96) 100%);
			border: 1px solid <?php echo TFR_BORDER; ?>;
			border-radius: 8px;
			padding: 28px 24px 20px !important;
			box-shadow:
				0 8px 32px rgba(0,0,0,0.6),
				0 0 0 1px rgba(207,220,53,0.08);
			margin-top: 0;
		}

		/* ── INPUTS ── */
		.login form .input,
		.login input[type="password"],
		.login input[type="text"] {
			font-size: 0.85rem !important;
			line-height: 1.5;
			width: 100%;
			border: 1px solid #2a2d31 !important;
			border-radius: 4px !important;
			padding: 10px 12px !important;
			margin: 0 0 16px 0 !important;
			min-height: 44px;
			background: #0d0e10 !important;
			color: #e5e7eb !important;
			box-shadow: inset 0 1px 3px rgba(0,0,0,0.5) !important;
			transition: border-color 0.25s ease, box-shadow 0.25s ease;
			box-sizing: border-box;
		}
		.login form .input:focus,
		.login input[type="password"]:focus,
		.login input[type="text"]:focus {
			border-color: <?php echo TFR_ACCENT; ?> !important;
			box-shadow: inset 0 1px 3px rgba(0,0,0,0.5), 0 0 0 2px rgba(207,220,53,0.2) !important;
			outline: none !important;
		}
		.login form .input::placeholder,
		.login input::placeholder {
			color: #555 !important;
			font-family: 'JetBrains Mono', 'SF Mono', monospace;
			font-size: 0.7rem;
			text-transform: uppercase;
			letter-spacing: 0.05em;
		}

		/* ── CHECKBOX ── */
		.login .forgetmenot {
			margin-top: 2px;
		}
		.login .forgetmenot label {
			font-size: 0.7rem !important;
			color: <?php echo TFR_MUTED; ?>;
			text-transform: uppercase;
			letter-spacing: 0.05em;
		}

		/* ── BOTÓN PRINCIPAL ── */
		.wp-core-ui .button-primary {
			background: <?php echo TFR_ACCENT; ?> !important;
			border: 1px solid <?php echo TFR_ACCENT; ?> !important;
			border-radius: 4px !important;
			color: #0d0e10 !important;
			font-weight: 700 !important;
			font-size: 0.8rem !important;
			text-transform: uppercase;
			letter-spacing: 0.1em;
			padding: 10px 20px !important;
			width: 100%;
			min-height: 44px;
			transition: filter 0.2s ease, transform 0.1s ease;
			margin-top: 4px;
		}
		.wp-core-ui .button-primary:hover {
			filter: brightness(1.15);
			transform: translateY(-1px);
		}
		.wp-core-ui .button-primary:active {
			transform: translateY(0);
		}

		/* ── ENLACES INFERIORES ── */
		.login #nav,
		.login #backtoblog {
			text-align: center;
			padding: 0;
			margin-top: 12px;
		}
		.login #nav a,
		.login #backtoblog a {
			color: <?php echo TFR_MUTED; ?>;
			font-size: 0.7rem;
			text-decoration: none;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			transition: color 0.2s ease;
		}
		.login #nav a:hover,
		.login #backtoblog a:hover {
			color: <?php echo TFR_ACCENT; ?>;
		}

		/* ── MENSAJES DE ERROR / INFO ── */
		.login .message {
			background: rgba(30,40,30,0.6) !important;
			border: 1px solid rgba(207,220,53,0.3) !important;
			border-left: 3px solid <?php echo TFR_ACCENT; ?> !important;
			color: <?php echo TFR_TEXT; ?> !important;
			font-size: 0.75rem;
			border-radius: 4px;
			padding: 10px 14px !important;
			margin-bottom: 16px;
		}
		#login_error {
			background: rgba(40,20,20,0.6) !important;
			border: 1px solid rgba(220,38,38,0.3) !important;
			border-left: 3px solid #dc2626 !important;
			color: #fca5a5 !important;
			font-size: 0.75rem;
			border-radius: 4px;
			padding: 10px 14px !important;
		}

		/* ── MINIORANGE DISCORD BUTTON ── */
		.mo-openid-app-icons {
			text-align: center;
			margin: 0 0 20px 0;
		}
		/* Texto "Connect with" */
		.mo-openid-app-icons > p {
			font-size: 0.6rem !important;
			color: <?php echo TFR_MUTED; ?> !important;
			text-transform: uppercase;
			letter-spacing: 0.12em;
			margin: 0 0 12px 0 !important;
			width: 100% !important;
			text-align: center !important;
		}
		/* Botón de Discord */
		.mo-openid-app-icons .mo_btn-mo {
			display: flex !important;
			align-items: center;
			justify-content: center;
			gap: 10px;
			width: 100% !important;
			max-width: 100% !important;
			box-sizing: border-box !important;
			margin-left: 0 !important;
			margin-right: 0 !important;
			padding: 12px 20px !important;
			border-radius: 4px !important;
			font-size: 0.8rem !important;
			font-weight: 700 !important;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			text-decoration: none !important;
			color: #fff !important;
			background: #5865F2 !important;
			border: 1px solid #4752c4 !important;
			transition: filter 0.2s ease, transform 0.1s ease;
			min-height: 44px;
			float: none !important;
		}
		.mo-openid-app-icons .mo_btn-mo:hover {
			filter: brightness(1.15) !important;
			transform: translateY(-1px);
			color: #fff !important;
			text-decoration: none !important;
		}
		/* Icono de Discord */
		.mo-openid-app-icons .mo_btn-mo i.fab.fa-discord {
			font-size: 1.2rem !important;
			margin-right: 4px;
		}
		/* Separador */
		.mo-openid-app-icons + p {
			color: <?php echo TFR_MUTED; ?> !important;
			font-size: 0.6rem;
			text-transform: uppercase;
			letter-spacing: 0.15em;
		}

		/* ── FOOTER TÁCTICO ── */
		.tfr-login-footer {
			text-align: center;
			margin-top: 24px;
			font-family: 'JetBrains Mono', 'SF Mono', monospace;
			font-size: 0.6rem;
			color: #CFDC35;
			text-transform: uppercase;
			letter-spacing: 0.15em;
		}

		/* ── RESPONSIVE ── */
		@media (max-width: 480px) {
			#login {
				width: 100%;
				padding: 0 16px;
			}
			#loginform {
				padding: 20px 16px 16px !important;
			}
			.tfr-classification-bar {
				font-size: 0.6rem;
				padding: 4px 14px;
			}
		}

		/* ── OCULTAR SELECTOR DE IDIOMA ── */
		#language-switcher {
			display: none;
		}
	</style>
	<?php
});

/* ===========================================
   LOGO Y LINKS
   =========================================== */
add_filter( 'login_headerurl', function() use ( $tfr_login ) { return $tfr_login['logo_link']; });
add_filter( 'login_headertext', function() use ( $tfr_login ) { return $tfr_login['logo_title']; });

/* ===========================================
   MENSAJE PERSONALIZADO + CLASIFICACIÓN
   =========================================== */
add_filter( 'login_message', function( $message ) use ( $tfr_login ) {
	$html  = '<div class="tfr-classification">';
	$html .= '<div class="tfr-classification-bar">⚠ CLASIFICACIÓN: RESTRINGIDO</div>';
	if ( ! empty( $tfr_login['tagline'] ) ) {
		$html .= '<span class="tfr-classification-tagline">' . esc_html( $tfr_login['tagline'] ) . '</span>';
	}
	$html .= '</div>';
	return $html . $message;
});

/* ===========================================
   FOOTER TÁCTICO
   =========================================== */
add_action( 'login_footer', function() use ( $tfr_login ) {
	?>
	<div class="tfr-login-footer">
		[=TFR=] TASK FORCE RECON • COM SEC // SIGINT • <?php echo date('Y'); ?>
	</div>
	<script>
		document.addEventListener("DOMContentLoaded", function() {
			<?php if( ! $tfr_login['show_back_to_blog'] ) : ?>
				document.getElementById('backtoblog')?.remove();
			<?php endif; ?>
			<?php if( ! $tfr_login['show_lost_password'] ) : ?>
				var lostLink = document.querySelector('#nav a[href*="lostpassword"]');
				if ( lostLink ) lostLink.parentElement?.remove();
			<?php endif; ?>
			<?php if( ! $tfr_login['show_language'] ) : ?>
				document.getElementById('language-switcher')?.remove();
			<?php endif; ?>

			// Sustituir etiquetas por placeholders estilo militar
			var userLabel = document.querySelector("label[for='user_login']");
			var passLabel = document.querySelector("label[for='user_pass']");
			
			if (userLabel) {
				document.getElementById("user_login").setAttribute("placeholder", userLabel.textContent.trim().toUpperCase());
				userLabel.style.display = "none";
			}
			if (passLabel) {
				document.getElementById("user_pass").setAttribute("placeholder", passLabel.textContent.trim().toUpperCase());
				passLabel.style.display = "none";
			}

			// Cambiar texto del botón a estilo militar
			var submitBtn = document.getElementById("wp-submit");
			if (submitBtn) {
				submitBtn.value = "▶ ACCEDER";
			}

			// Etiqueta "Recordarme"
			var rememberLabel = document.querySelector("label[for='rememberme']");
			if (rememberLabel) {
				rememberLabel.textContent = "MANTENER SESIÓN ACTIVA";
			}
		});
	</script>
	<?php
});
