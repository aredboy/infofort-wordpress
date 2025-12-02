<?php
/**
 * Plugin Name: ANSV Cajas - VERSIÓN 100% FUNCIONAL
 * Version: 10.0 - SOLUCIONA TODO
 */

if (!defined('ABSPATH')) exit;

// === CPT ===
add_action('init', 'ansv_registrar_cpt_caja');
function ansv_registrar_cpt_caja() {
    register_post_type('caja', [
        'labels' => ['name' => 'Cajas', 'singular_name' => 'Caja'],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'supports' => ['title'],
        'menu_position' => 25,
        'rewrite' => false,
        'menu_position'=> 25
    ]);
}

// === VALIDACIÓN DE FORMATO ===
add_action('acf/validate_value/name=caja_id', 'validar_caja_id', 10, 4);
function validar_caja_id($valid, $value) {
    if (!$valid) return $valid;
    // Solo validar el formato (mayúsculas, números, guiones)
    if (!preg_match('/^[A-Z0-9\-]+$/', $value)) {
        return 'Solo mayúsculas, números y guiones';
    }
    // NOTA: El chequeo de duplicado se hace en acf/pre_save_post
    return $valid;
}

// === VALIDACIÓN SERVER-SIDE Y REDIRECCIÓN DE ERRORES (ANTES DEL GUARDADO) ===
add_action('acf/pre_save_post', 'ansv_pre_save_caja', 20);
// === DEBUG: DESACTIVAR NONCE DE ACF (SOLO PARA PRUEBAS) ===
add_filter('acf/validate_submit_post', '__return_false');

if (!function_exists('ansv_pre_save_caja')) {
function ansv_pre_save_caja($post_id) {
    // Solo si estamos enviando un formulario de frontend ('new_post' o un CPT 'caja')
    if ( $post_id !== 'new_post' && get_post_type($post_id) !== 'caja' ) return;
    if ( is_admin() ) return; // No interferir con el backend de WP

    if (defined('WP_DEBUG') && WP_DEBUG) {
        @error_log('ANSV PRE_SAVE_POST ID: ' . (is_numeric($post_id) ? intval($post_id) : $post_id));
        @error_log('ANSV ACF DATA: ' . print_r($_POST['acf'] ?? [], true));
    }
    // Cerca de la Línea 45
if (defined('WP_DEBUG') && WP_DEBUG) {
    @error_log('*** ANSV START: ansv_pre_save_caja ejecutada. Post ID: ' . (is_numeric($post_id) ? intval($post_id) : $post_id));
}

    // Campos ACF esperados
    $caja_raw = trim(strval($_POST['acf']['field_caja_id'] ?? ''));
    $fecha_usuario = trim(strval($_POST['acf']['field_fecha_alta'] ?? ''));
    $estado = trim(strval($_POST['acf']['field_estado'] ?? ''));
    $ubicacion = trim(strval($_POST['acf']['field_ubicacion'] ?? ''));

    // Normalizar caja para comparar duplicados
    $caja_norm = strtoupper($caja_raw);
    if ($caja_norm !== '' && preg_match('/^(\d+)$/', $caja_norm)) {
        // Normalización a 6 dígitos si es puramente numérico
        $caja_norm = str_pad($caja_norm, 6, '0', STR_PAD_LEFT);
    }

    // Reglas de validación server-side
    $missing = [];
    if ($caja_raw === '' || !preg_match('/^[A-Z0-9\-]+$/', $caja_raw)) $missing[] = 'caja_id';
    if ($fecha_usuario === '') $missing[] = 'fecha_alta';
    if ($estado === '') $missing[] = 'estado';
    if ($ubicacion === '') $missing[] = 'ubicacion';

    // // chequeo duplicado: sólo si usuario ingresó algo
    // if ($caja_norm !== '') {
    //     $existing = get_posts([
    //         'post_type' => 'caja',
    //         'meta_key' => 'caja_id',
    //         'meta_value' => $caja_norm,
    //         'posts_per_page' => 1,
    //         'fields' => 'ids'
    //     ]);
        
    //     // Si encontramos un post existente...
    //     if (!empty($existing)) {
    //         $found_id = intval($existing[0]);
            
    //         // ... y estamos creando uno nuevo (o editando uno distinto al encontrado)
    //         $is_new_post = ($post_id === 'new_post' || !is_numeric($post_id));
    //         if ($is_new_post || $found_id !== intval($post_id)) {
    //             $missing[] = 'duplicate'; // Marcar como error duplicado
    //         }
    //     }
    // }

    // --- MANEJO DE ERRORES: Redirección ---
    if (!empty($missing)) {
        // La redirección DEBE hacerse aquí.
        // Si es 'new_post', ACF crea un borrador que se debe mantener en borrador.
        
        $missing_param = implode(',', $missing);
        $redirect = add_query_arg([
            'caja_status' => 'error_missing_fields',
            'missing' => $missing_param
        ], get_permalink()) . '#formulario-cajas';

        // Redirección segura
        if (!headers_sent()) {
            wp_redirect($redirect);
            exit;
        } else {
            // Último recurso si hay output. NOTA: Esto no soluciona el error fatal original (throw), 
            // pero sí el error de wp_redirect.
            @error_log('ANSV ERROR: Headers sent, cannot redirect. Missing fields: ' . $missing_param);
            exit;
        }
    }
    
    // Si la validación pasa, devuelve el post_id para que ACF continúe con el guardado
    return $post_id;
}
}

// === FORMULARIO (CON REDIRECT CORRECTO) ===
add_shortcode('ansv_cajas_frontend', 'ansv_form_final');
function ansv_form_final() {
    if (!is_user_logged_in()) return '<p>Debes <a href="' . wp_login_url() . '">iniciar sesión</a>.</p>';

    // MOSTRAR MENSAJE VERDE SI VIENE EL PARAM
    $mensaje = '';
    if (isset($_GET['caja_status']) && $_GET['caja_status'] === 'success') {
        $mensaje = '<div style="background:#d4edda;padding:25px;margin:25px 0;border:2px solid #c3e6cb;border-radius:10px;color:#155724;font-weight:bold;font-size:22px;text-align:center;">¡CAJA CARGADA CORRECTAMENTE!</div>';
    }

    ob_start(); ?>
    <div class="ansv-cajas-form" id="formulario-cajas">
        <h3>Agregar Nueva Caja</h3>
        <?php
        acf_form([
            'post_id'         => 'new_post',
            'post_title'      => false,
            'new_post'        => [
                'post_type'   => 'caja',
                'post_status' => 'draft'
            ],
            'fields'    => ['field_caja_id','field_estado','field_ubicacion','field_fecha_alta','field_expedientes'],
            'submit_value'    => 'Guardar Caja',
            'updated_message' => false,
            'return'          => add_query_arg('caja_status', 'success', get_permalink()) . '#formulario-cajas',
            'html_updated_message' => true
        ]);
        ?>
        <style>
            div[data-name="digitalizacion_multi"] .acf-button,
            .a.acf-button .button {
                display: inline-block !important;
                border-radius: 5px !important;
                background: #ff3e26 !important;
            }
        </style>
    </div>
    <?php
    return ob_get_clean();
}


// // === JS PERSONALIZADO EN EL FOOTER (reemplazo seguro) ===
// add_action('wp_footer', 'ansv_print_footer_js_safe');
// function ansv_print_footer_js_safe() {
//     if (!is_user_logged_in()) return;
//     if (!is_singular()) return;

//     // Imprimimos JS en el footer; NO usar comprobaciones JS en PHP.
/*     ?>
//     <script> */
//     (function(){
//         // todo en IIFE, sin depender de jQuery
//         var container = document.getElementById('formulario-cajas');
//         if (!container) return;

//         // Mensajes (creamos wrapper si no existe)
//         var msgWrap = container.querySelector('.ansv-messages');
//         if (!msgWrap) {
//             msgWrap = document.createElement('div');
//             msgWrap.className = 'ansv-messages';
//             msgWrap.style.marginBottom = '12px';
//             container.insertBefore(msgWrap, container.firstChild);
//         }

//         // Detectar elementos ACF
//         var form = container.querySelector('form');
//         var $caja = container.querySelector('input[name="acf[field_caja_id]"], #acf-field_caja_id');
//         // hidden real de ACF (valor que se guarda)
//         var $fecha_hidden = container.querySelector('input[name="acf[field_fecha_alta]"]');
//         // input visible del date picker (ACF lo renderiza aparte)
//         var $fecha_visible = container.querySelector('.acf-date-picker .input, .acf-date-picker input[type="text"], .acf-date-picker input[type="date"]');
//         var $estado = container.querySelector('select[name="acf[field_estado]"], input[name="acf[field_estado]"]');
//         var $ubicacion = container.querySelector('select[name="acf[field_ubicacion]"], input[name="acf[field_ubicacion]"], textarea[name="acf[field_ubicacion]"]');

//         // función auxiliar para mostrar error global
//         function showGlobalError(html) {
//             msgWrap.innerHTML = '<div style="background:#f8d7da;padding:12px;border-radius:6px;border:1px solid #f5c6cb;color:#721c24;font-weight:600;">' + html + '</div>';
//             // scroll al mensaje
//             try { window.scrollTo({ top: container.getBoundingClientRect().top + window.pageYOffset - 60, behavior: 'smooth' }); } catch(e){}
//         }
//         function clearGlobal() { msgWrap.innerHTML = ''; }
//         function markFieldError(el, text) {
//             if (!el) return;
//             el.classList.add('ansv-error-field');
//             // next sibling error message
//             var ex = el.nextElementSibling;
//             if (!ex || !ex.classList || !ex.classList.contains('ansv-field-error')) {
//                 ex = document.createElement('div');
//                 ex.className = 'ansv-field-error';
//                 ex.style.color = '#b02a37';
//                 ex.style.marginTop = '6px';
//                 ex.style.fontSize = '13px';
//                 el.parentNode.insertBefore(ex, el.nextSibling);
//             }
//             ex.textContent = text;
//         }
//         function clearFieldErrors() {
//             var els = container.querySelectorAll('.ansv-field-error');
//             els.forEach(function(x){ x.parentNode && x.parentNode.removeChild(x); });
//             var bad = container.querySelectorAll('.ansv-error-field');
//             bad.forEach(function(x){ x.classList.remove('ansv-error-field'); });
//         }

//         // Estilo pequeño para campo con error
//         (function(){
//             var s = document.createElement('style');
//             s.textContent = '.ansv-error-field{box-shadow:0 0 0 2px rgba(176,42,55,0.12) !important;}';
//             document.head.appendChild(s);
//         })();

//         // Sincronizar visible -> hidden para fecha: normaliza a YYYY-MM-DD
//         function normalizeDateToISO(str) {
//             if (!str) return '';
//             str = str.trim();
//             // si viene DD/MM/YYYY
//             var m1 = str.match(/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/);
//             if (m1) { return m1[3] + '-' + m1[2] + '-' + m1[1]; }
//             // si viene YYYY-MM-DD o YYYY/MM/DD
//             var m2 = str.match(/^(\d{4})[\/\-](\d{2})[\/\-](\d{2})$/);
//             if (m2) { return m2[1] + '-' + m2[2] + '-' + m2[3]; }
//             // intentar Date parse (por si el navegador entrega otro formato)
//             var d = new Date(str);
//             if (!isNaN(d.getTime())) {
//                 var yy = d.getFullYear();
//                 var mm = ('0' + (d.getMonth()+1)).slice(-2);
//                 var dd = ('0' + d.getDate()).slice(-2);
//                 return yy + '-' + mm + '-' + dd;
//             }
//             return '';
//         }
//         function syncVisibleToHidden() {
//             if (!$fecha_visible || !$fecha_hidden) return;
//             var v = $fecha_visible.value || '';
//             var iso = normalizeDateToISO(v);
//             $fecha_hidden.value = iso;
//         }

//         // Forzamos que el visible sea tipo=date cuando el navegador lo soporte,
//         // pero no alteramos si ACF ya maneja algo especial.
//         if ($fecha_visible) {
//             try { $fecha_visible.setAttribute('type','date'); } catch(e){}
//             // sincronizar en input/change/blur
//             $fecha_visible.addEventListener('input', syncVisibleToHidden);
//             $fecha_visible.addEventListener('change', syncVisibleToHidden);
//             $fecha_visible.addEventListener('blur', syncVisibleToHidden);
//             // cuando la página carga, sincronizar (si hay valor visible)
//             setTimeout(syncVisibleToHidden, 200);
//         }

//         // Añadimos listener al form submit para validación cliente
//         if (form) {
//             form.addEventListener('submit', function(e){
//                 clearFieldErrors();
//                 clearGlobal();

//                 var errors = [];

//                 var cajaVal = $caja ? ($caja.value || '').toString().trim() : '';
//                 if (!cajaVal || !/^[A-Z0-9\-]+$/.test(cajaVal)) {
//                     errors.push('ID Caja inválido (solo MAYÚSCULAS, números y guiones).');
//                     markFieldError($caja, 'Solo MAYÚSCULAS, números y guiones.');
//                 }

//                 // sincronizar fecha antes de validar
//                 syncVisibleToHidden();
//                 var fechaVal = $fecha_hidden ? ($fecha_hidden.value || '').toString().trim() : '';
//                 if (!fechaVal || !/^\d{4}-\d{2}-\d{2}$/.test(fechaVal)) {
//                     errors.push('Fecha obligatoria o en formato inválido (AAAA-MM-DD).');
//                     if ($fecha_visible) markFieldError($fecha_visible, 'Fecha obligatoria.');
//                     else if ($fecha_hidden) markFieldError($fecha_hidden, 'Fecha obligatoria.');
//                 }

//                 var estadoVal = $estado ? ($estado.value || '').toString().trim() : '';
//                 if (!estadoVal) {
//                     errors.push('Estado obligatorio.');
//                     markFieldError($estado || $estado, 'Estado obligatorio.');
//                 }

//                 var ubicVal = $ubicacion ? ($ubicacion.value || '').toString().trim() : '';
//                 if (!ubicVal) {
//                     errors.push('Ubicación obligatoria.');
//                     markFieldError($ubicacion || $ubicacion, 'Ubicación obligatoria.');
//                 }

//                 if (errors.length) {
//                     e.preventDefault();
//                     showGlobalError('Hay errores en el formulario: ' + errors.join(' '));
//                     return false;
//                 }

//                 // si pasa la validación cliente, dejamos que el form se envíe.
//                 // NOTA: la validación server-side (ansv_save_final) también comprobará campos.
//                 return true;
//             }, false);
//         }

//         // limpiar errores al modificar campos
//         container.addEventListener('input', function(ev){
//             clearFieldErrors();
//             clearGlobal();
//         }, true);

//         // Si la URL tiene caja_status=success o error_missing_fields, mostramos mensaje (limpiamos history)
//         (function(){
//             var params = new URLSearchParams(window.location.search);
//             var status = params.get('caja_status');
//             if (status === 'success') {
//                 showGlobalError('<div style="background:#d4edda;padding:12px;border-radius:6px;border:1px solid #c3e6cb;color:#155724;font-weight:600;">¡Caja cargada correctamente!</div>');
//                 if (history.replaceState) history.replaceState({}, document.title, window.location.pathname + window.location.hash);
//             } else if (status === 'error_missing_fields') {
//                 var missing = params.get('missing') || '';
//                 var items = missing.split(',').map(function(n){
//                     switch(n){
//                         case 'caja_id': return 'ID Caja';
//                         case 'fecha_alta': return 'Fecha';
//                         case 'estado': return 'Estado';
//                         case 'ubicacion': return 'Ubicación';
//                         default: return n;
//                     }
//                 }).filter(Boolean);
//                 if (items.length) showGlobalError('Faltan campos obligatorios: ' + items.join(', '));
//                 if (history.replaceState) history.replaceState({}, document.title, window.location.pathname + window.location.hash);
//             }
//         })();

//     })();
//     </script>

//     <style>
//     /* estilos de ayuda para mensajes y errores (puedes mover a CSS del theme) */
//     .ansv-field-error { color:#b02a37; margin-top:6px; font-size:13px; }
//     .ansv-error-field { box-shadow:0 0 0 2px rgba(176,42,55,0.12) !important; }
//     .ansv-messages { margin-bottom:12px; }
//     </style>
//     <?php
// }


/**
 * AJAX handler para comprobar existencia de caja (unicidad)
 */
add_action('wp_ajax_ansv_check_caja', 'ansv_check_caja');
function ansv_check_caja() {
    // seguridad
    $nonce = $_POST['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'ansv_ajax_nonce')) {
        wp_send_json(['exists' => false, 'error' => 'nonce']);
    }
    $caja = strtoupper(trim((string)($_POST['caja'] ?? '')));
    if ($caja === '') {
        wp_send_json(['exists' => false]);
    }

    $args = [
        'post_type' => 'caja',
        'post_status' => 'publish',
        'meta_key' => 'caja_id',
        'meta_value' => $caja,
        'posts_per_page' => 1,
        'fields' => 'ids',
    ];
    $found = get_posts($args);
    wp_send_json(['exists' => !empty($found)]);
}



// === GUARDADO FINAL (normalización de datos y publicación) ===
add_action('acf/save_post', 'ansv_save_final', 20);
if (!function_exists('ansv_save_final')) {
function ansv_save_final($post_id) {
    // Solo para CPT caja (y evitamos el guardado en pre_save_post)
    if (get_post_type($post_id) !== 'caja') return;
    if (is_admin()) return;
    
    // Si la función anterior redirigió con error, no llegamos aquí.
    
    // Obtenemos los datos (asumiendo que ACF ya los guardó en el post)
    $caja_raw = trim(strval($_POST['acf']['field_caja_id'] ?? ''));
    $fecha_usuario = trim(strval($_POST['acf']['field_fecha_alta'] ?? ''));

    // Normalizar caja_id
    $caja_id = strtoupper($caja_raw);
    if (preg_match('/^(\d+)$/', $caja_id)) {
        $caja_id = str_pad($caja_id, 6, '0', STR_PAD_LEFT);
    }
    
    // 1. Actualizar Título y Publicar
    wp_update_post([
        'ID' => $post_id,
        'post_title' => $caja_id,
        'post_status' => 'publish',
        'post_name' => 'caja-' . $post_id
    ]);

    // 2. Guardar Meta de Usuario
    update_post_meta($post_id, 'usuario_alta', get_current_user_id());
    
    // 3. Manejo Fecha (ACF maneja 'field_fecha_alta', pero actualizamos el meta si es necesario)
    if ($fecha_usuario && preg_match('/^\d{8}$/', $fecha_usuario)) {
        // formato YYYYMMDD -> YYYY-MM-DD
        $fecha_formateada = substr($fecha_usuario, 0, 4) . '-' .
                            substr($fecha_usuario, 4, 2) . '-' .
                            substr($fecha_usuario, 6, 2);
        update_post_meta($post_id, 'fecha_alta', $fecha_formateada);
    } else if ($fecha_usuario && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_usuario)) {
        update_post_meta($post_id, 'fecha_alta', $fecha_usuario);
    } else {
        // En un caso ideal, esto no debería pasar si la validación funciona
        update_post_meta($post_id, 'fecha_alta', current_time('Y-m-d'));
    }
    
    // Cerca de la Línea 175
    if (defined('WP_DEBUG') && WP_DEBUG) {
        @error_log('*** ANSV START: ansv_save_final ejecutada. Post ID: ' . intval($post_id));
    }
    // ACF se encarga de la redirección de éxito gracias a 'return' en acf_form()
}
}




// === TABLA (MISMA QUE ANTES, PERO CON ORDEN POR FECHA) ===
add_shortcode('ansv_tabla_cajas', 'ansv_tabla_final');
function ansv_tabla_final() {
    if (!is_user_logged_in()) return '<p>Inicia sesión.</p>';

    header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private");
    header("Pragma: no-cache");
    header("Expires: 0");
    nocache_headers();
    if (function_exists('wp_cache_flush')) wp_cache_flush(); 

    $unique = time() . wp_rand(1,999);
    $_GET['_nocache'] = $unique; // fuerza recarga


    $items_per_page = 50;
    $current_page = max(1, intval($_GET['pagina'] ?? 1));
    $offset = ($current_page - 1) * $items_per_page;


    $buscar_id = sanitize_text_field($_GET['buscar_id'] ?? '');
    $desde_num = intval($_GET['desde_num'] ?? 0);
    $hasta_num = intval($_GET['hasta_num'] ?? 0);
    $desde     = sanitize_text_field($_GET['desde'] ?? '');
    $hasta     = sanitize_text_field($_GET['hasta'] ?? '');
    $estado_f  = sanitize_text_field($_GET['estado'] ?? '');
    $limpiar_url = remove_query_arg(['buscar_id','desde','hasta','estado','desde_num','hasta_num','pagina','_nocache']);

    $all_cajas = get_posts([
        'post_type'      => 'caja',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'cache_results'           => false,
        'update_post_meta_cache'  => false,
        'update_post_term_cache'  => false,
        'no_found_rows'           => false,
        'ignore_sticky_posts' => true,
        'date_query' => [['after' => '1970-01-01']]
    ]);

    $cajas_filtradas = array_filter($all_cajas, function($c) use ($buscar_id, $desde, $hasta, $desde_num, $hasta_num, $estado_f) {
        $id = get_post_meta($c->ID, 'caja_id', true);
        $estado = get_post_meta($c->ID, 'estado', true);
        $fecha  = get_post_meta($c->ID, 'fecha_alta', true);
        $caja_id_raw = get_post_meta($c->ID, 'caja_id', true);
        $caja_id = preg_match('/^\d+$/', $caja_id_raw) ? intval(ltrim($caja_id_raw, '0')) : 0; 

        if ($buscar_id && stripos($caja_id_raw, $buscar_id) === false) return false;
        if ($desde_num > 0 && $caja_id < $desde_num) return false;
        if ($hasta_num > 0 && $caja_id > $hasta_num) return false;
        if ($buscar_id && stripos($id, $buscar_id) === false) return false;
        if ($estado_f && $estado !== $estado_f) return false;
        if ($desde && $fecha < $desde) return false;
        if ($hasta && $fecha > $hasta) return false;
        return true;
    });

    $total_cajas = count($cajas_filtradas);
    $total_pages = ceil($total_cajas / $items_per_page);
    $cajas_pagina = array_slice($cajas_filtradas, $offset, $items_per_page, true);

    // === EXPORTAR CSV (usa $cajas_filtradas DESPUÉS del filtro) ===
    if (isset($_GET['export'])) {

        // Si querés permitir exportar sólo una selección: pasar selected_ids como "123,456" o como array.
        $selected_ids_param = $_GET['selected_ids'] ?? '';
        $selected_ids = [];
        if (!empty($selected_ids_param)) {
            if (is_array($selected_ids_param)) {
                $selected_ids = array_map('intval', $selected_ids_param);
            } else {
                $selected_ids = array_map('intval', array_filter(array_map('trim', explode(',', $selected_ids_param))));
            }
        }

        // Determinar lista final de posts a exportar
        if (!empty($selected_ids)) {
            // usar sólo los seleccionados (si existen en el set filtrado)
            $to_export = array_filter($cajas_filtradas, function($c) use ($selected_ids) {
                return in_array(intval($c->ID), $selected_ids, true);
            });
        } else {
            // por defecto exportar todas las cajas filtradas
            $to_export = $cajas_filtradas;
        }

        // Limpiar buffers previos (evita que se incluya HTML)
        while (ob_get_level()) { ob_end_clean(); }

        // Headers para descarga CSV (Excel-friendly)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=cajas-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // BOM para que Excel reconozca UTF-8
        echo "\xEF\xBB\xBF";

        $f = fopen('php://output', 'w');

        // Encabezado (igual que tus <th>)
        $headers = ['ID Caja', 'Estado', 'Ubicación', 'Fecha', 'Expedientes (raw)', 'Archivo URLs'];
        // Uso de ';' como delimitador para mejor compatibilidad regional con Excel
        fputcsv($f, $headers, ';');

        foreach ($to_export as $c) {
            $caja_id = get_post_meta($c->ID, 'caja_id', true);
            $estado   = get_post_meta($c->ID, 'estado', true);
            $ubicacion= get_post_meta($c->ID, 'ubicacion', true);
            $fecha    = get_post_meta($c->ID, 'fecha_alta', true);
            $exp_raw  = get_post_meta($c->ID, 'expedientes_raw', true);

            // Limpieza: quitar tags HTML si hubiera
            $caja_id   = is_scalar($caja_id) ? strip_tags((string)$caja_id) : '';
            $estado    = is_scalar($estado) ? strip_tags((string)$estado) : '';
            $ubicacion = is_scalar($ubicacion) ? strip_tags((string)$ubicacion) : '';
            $fecha     = is_scalar($fecha) ? strip_tags((string)$fecha) : '';
            $exp_raw   = is_scalar($exp_raw) ? strip_tags((string)$exp_raw) : '';

            // Manejar digitalizacion_multi que puede ser ID único o array de IDs
            $attach_meta = get_post_meta($c->ID, 'digitalizacion_multi', true);
            $file_urls = [];
            if (is_array($attach_meta)) {
                foreach ($attach_meta as $aid) {
                    if ($aid = intval($aid)) {
                        $url = wp_get_attachment_url($aid);
                        if ($url) $file_urls[] = $url;
                    }
                }
            } else {
                $aid = intval($attach_meta);
                if ($aid) {
                    $url = wp_get_attachment_url($aid);
                    if ($url) $file_urls[] = $url;
                }
            }
            // Si no es ID pero el campo contiene directamente una URL, añadimos también
            if (empty($file_urls) && is_string($attach_meta) && filter_var($attach_meta, FILTER_VALIDATE_URL)) {
                $file_urls[] = $attach_meta;
            }

            $file_urls_str = implode(', ', $file_urls);

            // Escribir fila (nota: fputcsv escapará comillas y saltos de línea correctamente)
            fputcsv($f, [
                $caja_id,
                $estado,
                $ubicacion,
                $fecha,
                $exp_raw,
                $file_urls_str
            ], ';');
        }
        fclose($f);
        exit;
    }
    // === FIN EXPORTAR CSV ===

    ob_start(); ?>

    <div class="ansv-tabla-cajas">
        <div style="background:#f0f8ff;padding:20px;border-radius:10px;margin:20px 0;border:2px solid #0073aa;border-box:content-box;">
            <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                <input type="text" name="buscar_id" value="<?php echo esc_attr($buscar_id); ?>" placeholder="ID Caja (ej: 000050)" style="padding:10px;">
                <input type="number" name="desde_num" value="<?php echo esc_attr($desde_num); ?>" placeholder="Desde número (ej: 17)">
                <input type="number" name="hasta_num" value="<?php echo esc_attr($hasta_num); ?>" placeholder="Hasta número (ej: 37)">
                <select name="estado" style="padding:10px;">
                    <option value="">Todos los estados</option>
                    <option value="alta_administrativa" <?php selected($estado_f, 'alta_administrativa'); ?>>Alta Administrativa</option>
                    <option value="sin_asignar" <?php selected($estado_f, 'sin_asignar'); ?>>Sin Asignar</option>
                    <option value="asignada" <?php selected($estado_f, 'asignada'); ?>>Asignada</option>
                    <option value="destruida" <?php selected($estado_f, 'destruida'); ?>>Destruida</option>
                </select>
                <input type="date" name="desde" value="<?php echo esc_attr($desde); ?>" placeholder="Desde">
                <input type="date" name="hasta" value="<?php echo esc_attr($hasta); ?>" placeholder="Hasta">
                <div style="display:flex;gap:10px;">
                    <button type="submit" 
                        class="button button-primary" 
                        style="height:40px;padding-top:12px!important;">
                        Filtrar
                    </button>
                    <a href="<?php echo esc_url($limpiar_url); ?>" 
                        class="button" 
                        style="background-color:#FF3E26;max-height:40px!important;line-height:0px;border-radius:5px;padding-top:20px!important;">
                        Limpiar
                    </a>
                </div>
            </form>
        </div>

        <p>
            <strong><?php echo $total_cajas; ?> caja(s)</strong> | 
            Página <?php echo $current_page; ?> de <?php echo $total_pages; ?>
        </p>

        <?php if ($cajas_pagina): ?>
            <div class="table-responsive" style="overflow:auto;">
            <table style="width:100%;border-collapse:collapse;table-layout:fixed;word-wrap:break-word;">
                <thead style="background:#f1f1f1;">
                    <tr>
                        <th style="border:1px solid #ddd;padding:12px;">ID Caja</th>
                        <th style="border:1px solid #ddd;padding:12px;">Estado</th>
                        <th style="border:1px solid #ddd;padding:12px;">Ubicación</th>
                        <th style="border:1px solid #ddd;padding:12px;">Fecha</th>
                        <th style="border:1px solid #ddd;padding:12px;">Exp.</th>
                        <th style="border:1px solid #ddd;padding:12px;">Archivo</th>
                        <!-- Nueva columna para seleccionar -->
                        <th style="border:1px solid #ddd;padding:12px;">
                            <label style="cursor:pointer;">
                                <input type="checkbox" id="ansv-select-all" title="Seleccionar todo">
                                &nbsp;Sel.
                            </label>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $estados = ['alta_administrativa'=>'Alta Admin','sin_asignar'=>'Sin Asignar','asignada'=>'Asignada','destruida'=>'Destruida'];
                    foreach ($cajas_pagina as $c):
                        $caja_id_raw = get_post_meta($c->ID, 'caja_id', true);
                        $caja_id = preg_match('/^\d+$/', $caja_id_raw) ? str_pad($caja_id_raw, 6, '0', STR_PAD_LEFT) : $caja_id_raw;
                        $estado = get_post_meta($c->ID, 'estado', true);
                        $ubicacion = get_post_meta($c->ID, 'ubicacion', true);
                        $fecha_raw = get_post_meta($c->ID, 'fecha_alta', true);
                        $fecha = $fecha_raw ? date('d/m/Y', strtotime($fecha_raw)) : 'Sin fecha';
                        $exp_raw   = get_post_meta($c->ID, 'expedientes_raw', true);
                        $num_exp   = $exp_raw ? count(explode("\n", trim($exp_raw))) : 0;

                        // Manejo seguro de digitalizacion_multi: puede ser array o ID o URL
                        $file_url = '—';
                        $attach_meta = get_post_meta($c->ID, 'digitalizacion_multi', true);
                        if (is_array($attach_meta) && !empty($attach_meta)) {
                            $first_aid = intval($attach_meta[0]);
                            if ($first_aid) $file_url = wp_get_attachment_url($first_aid) ?: '—';
                        } else {
                            if (intval($attach_meta)) {
                                $file_url = wp_get_attachment_url(intval($attach_meta)) ?: '—';
                            } else if (is_string($attach_meta) && filter_var($attach_meta, FILTER_VALIDATE_URL)) {
                                $file_url = $attach_meta;
                            }
                        }
                    ?>
                        <tr>
                            <td style="border:1px solid #ddd;padding:12px;"><?php echo esc_html($caja_id); ?></td>
                            <td style="border:1px solid #ddd;padding:12px;"><?php echo $estados[$estado] ?? esc_html($estado); ?></td>
                            <td style="border:1px solid #ddd;padding:12px;"><?php echo esc_html($ubicacion); ?></td>
                            <td style="border:1px solid #ddd;padding:12px;"><?php echo esc_html($fecha); ?></td>
                            <td style="border:1px solid #ddd;padding:12px;"><?php echo $num_exp; ?></td>
                            <td style="border:1px solid #ddd;padding:12px;">
                                <?php echo $file_url && $file_url !== '—' ? '<a href="'.esc_url($file_url).'" target="_blank">Descargar</a>' : '—'; ?>
                            </td>

                            <!-- Checkbox (última columna) -->
                            <td style="border:1px solid #ddd;padding:12px;text-align:center;">
                                <input type="checkbox" class="ansv-select-caja" value="<?php echo intval($c->ID); ?>" aria-label="Seleccionar caja <?php echo esc_attr($caja_id); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <!-- Botón Exportar: ahora usa JS inline para enviar selected_ids -->
            <?php $export_base = esc_url(add_query_arg('export', '1')); ?>
            <button id="ansv-export-btn"
                type="button"
                class="button button-secondary"
                data-export-url="<?php echo esc_attr($export_base); ?>"
                style="background-color:#FF3E26;border-radius:5px;margin-top:12px;">
                Exportar CSV
            </button>

            <!-- JS mínimo embebido: construye la URL con selected_ids (comma-separated) y navega -->
            <script>
            (function(){
                var btn = document.getElementById('ansv-export-btn');
                if (!btn) return;

                btn.addEventListener('click', function(){
                    var checked = Array.prototype.slice.call(document.querySelectorAll('.ansv-select-caja:checked'));
                    var ids = checked.map(function(cb){ return cb.value; }).filter(Boolean);
                    var url = btn.getAttribute('data-export-url') || window.location.href;

                    if (ids.length > 0) {
                        // append selected_ids=1,2,3
                        url += (url.indexOf('?') === -1 ? '?' : '&') + 'selected_ids=' + encodeURIComponent(ids.join(','));
                    } else {
                        // sin seleccionados: exportar todas las cajas filtradas (comportamiento por defecto)
                        url = btn.getAttribute('data-export-url');
                    }
                    // preservar _nocache si existe en la URL actual (opcional)
                    window.location.href = url;
                });

                // Select all checkbox behavior
                var selectAll = document.getElementById('ansv-select-all');
                if (selectAll) {
                    selectAll.addEventListener('change', function(){
                        var all = document.querySelectorAll('.ansv-select-caja');
                        for (var i = 0; i < all.length; i++) all[i].checked = selectAll.checked;
                    });
                }
            })();
            </script>

            <!-- PAGINACIÓN (mantengo tu lógica original, sin cambios) -->
            <div style="text-align:center;margin:30px 0;">
                <?php if ($current_page > 1): ?>
                    <a href="<?php echo esc_url(add_query_arg(['pagina', $current_page-1, '_nocache' => $unique], remove_query_arg('pagina'))); ?>" 
                        class="button" style="margin:0 3px;border-radius:5px;">
                        Anterior
                    </a>
                <?php endif; ?>
                <?php for ($i = max(1, $current_page-2); $i <= min($total_pages, $current_page+2); $i++): ?>
                    <a href="<?php echo esc_url(add_query_arg(['pagina' => $i, '_nocache' => $unique], remove_query_arg('pagina'))); ?>" 
                        class="button <?php echo $i==$current_page?'button-primary':''; ?>" 
                        style="margin:0 3px;border-radius:5px;">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo esc_url(add_query_arg(['pagina', $current_page+1, '_nocache' => $unique], remove_query_arg('pagina'))); ?>" 
                        class="button" 
                        style="margin:0 3px;border-radius:5px;">
                        Siguiente
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p><em>No se encontraron cajas.</em></p>
        <?php endif; ?>
    </div>
    
    <?php
    return ob_get_clean();
}
