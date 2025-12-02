<?php
/**
 * functions.php - Astra Child
 *
 * Evita que Elementor cargue CSS/JS en páginas que contienen [ansv_cajas_frontend]
 * y aplica un dequeue adicional por si queda algo.
 *
 * Colocar este archivo en: wp-content/themes/astra-child/functions.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1) Filtros oficiales de Elementor para evitar carga de CSS/JS
 * Devuelven false cuando la página contiene el shortcode ansv_cajas_frontend.
 */
add_filter( 'elementor/frontend/should_load_css', 'ansv_maybe_disable_elementor_assets', 10, 1 );
add_filter( 'elementor/frontend/should_load_js',  'ansv_maybe_disable_elementor_assets', 10, 1 );

function ansv_maybe_disable_elementor_assets( $should_load ) {
	// No tocar en admin ni REST API
	if ( is_admin() || ( defined('REST_REQUEST') && REST_REQUEST ) ) {
		return $should_load;
	}

	// Sólo en páginas singulares (posts/páginas)
	if ( is_singular() ) {
		global $post;
		// si no hay post, devolvemos comportamiento por defecto
		if ( empty( $post ) || ! isset( $post->post_content ) ) {
			return $should_load;
		}

		// Si la página contiene el shortcode, evitar carga de CSS/JS de Elementor
		if ( has_shortcode( $post->post_content, 'ansv_cajas_frontend' ) ) {
			return false;
		}
	}

	return $should_load;
}

/**
 * 2) Medida de respaldo: dequeque cualquier handle que contenga 'elementor' en su nombre
 * Sólo si la página contiene el shortcode. Esto cubre casos en que Elementor no respeta
 * los filtros o carga assets via inline registrations.
 */
add_action( 'wp_enqueue_scripts', 'ansv_dequeue_elementor_handles', 20 );
function ansv_dequeue_elementor_handles() {
	// No tocar en admin
	if ( is_admin() ) return;

	if ( is_singular() ) {
		global $post, $wp_styles, $wp_scripts;

		if ( empty( $post ) || ! isset( $post->post_content ) ) return;

		if ( has_shortcode( $post->post_content, 'ansv_cajas_frontend' ) ) {

			// Dequeue estilos con 'elementor' en el handle
			if ( isset( $wp_styles ) && isset( $wp_styles->queue ) && is_array( $wp_styles->queue ) ) {
				foreach ( $wp_styles->queue as $handle ) {
					if ( false !== strpos( $handle, 'elementor' ) ) {
						wp_dequeue_style( $handle );
						wp_deregister_style( $handle );
					}
				}
			}

			// Dequeue scripts con 'elementor' en el handle
			if ( isset( $wp_scripts ) && isset( $wp_scripts->queue ) && is_array( $wp_scripts->queue ) ) {
				foreach ( $wp_scripts->queue as $handle ) {
					if ( false !== strpos( $handle, 'elementor' ) ) {
						wp_dequeue_script( $handle );
						wp_deregister_script( $handle );
					}
				}
			}

			// Opcional adicional: dequeue de handles comunes de Elementor si exiten
			$safe_handles = [
				'elementor-frontend',
				'elementor-frontend-modules',
				'elementor-icons',
				'elementor-pro-frontend',
			];
			foreach ( $safe_handles as $h ) {
				wp_dequeue_script( $h );
				wp_dequeue_style( $h );
			}
		}
	}
}

/**
 * 3) Helper: añade una pequeña clase al body para debug si la página tiene el shortcode
 * (te ayuda a inspeccionar en DevTools si estamos en la página correcta).
 */
add_filter( 'body_class', 'ansv_body_class_if_shortcode' );
function ansv_body_class_if_shortcode( $classes ) {
	if ( is_singular() ) {
		global $post;
		if ( ! empty( $post ) && isset( $post->post_content ) && has_shortcode( $post->post_content, 'ansv_cajas_frontend' ) ) {
			$classes[] = 'ansv-has-ansv-form';
		}
	}
	return $classes;
}

/**
 * 4) Enqueue stylesheet del child theme (vacío por defecto; pon aquí tus ajustes si querés)
 */
add_action( 'wp_enqueue_scripts', 'ansv_child_enqueue_styles' );
function ansv_child_enqueue_styles() {
	$ver = filemtime( get_stylesheet_directory() . '/style.css' );
	wp_enqueue_style( 'astra-child-style', get_stylesheet_directory_uri() . '/style.css', [], $ver );
}

// Forzar dequeue de Elementor (y derivados) solo en la página 'Carga de Cajas' (ID = 123)
add_action('wp_enqueue_scripts', function() {
    if ( is_singular() ) {
        global $post, $wp_styles, $wp_scripts;
        if ( empty($post) ) return;
        $target_page_id = 2634; // <-- Cambia esto por el ID real de tu página

        if ( intval($post->ID) === intval($target_page_id) ) {
            // Dequeue common Elementor handles
            $handles = [
                'elementor-frontend',
                'elementor-frontend-modules',
                'elementor-icons',
                'elementor-pro-frontend',
                'elementor-post-'. $post->ID,
            ];
            foreach ($handles as $h) {
                wp_dequeue_script($h);
                wp_dequeue_style($h);
                wp_deregister_script($h);
                wp_deregister_style($h);
            }

            // Dequeue cualquier handle que contenga 'elementor'
            if ( isset($wp_styles->queue) && is_array($wp_styles->queue) ) {
                foreach ($wp_styles->queue as $handle) {
                    if (strpos($handle, 'elementor') !== false) {
                        wp_dequeue_style($handle);
                        wp_deregister_style($handle);
                    }
                }
            }
            if ( isset($wp_scripts->queue) && is_array($wp_scripts->queue) ) {
                foreach ($wp_scripts->queue as $handle) {
                    if (strpos($handle, 'elementor') !== false) {
                        wp_dequeue_script($handle);
                        wp_deregister_script($handle);
                    }
                }
            }
        }
    }
}, 5); // prioridad temprana
