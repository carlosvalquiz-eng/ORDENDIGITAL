<?php
/**
 * Plugin Name: PD Imaging — Orden radiológica digital
 * Description: Procesa el envío del formulario, guarda la orden con enlace único y notifica por correo al dueño, paciente y doctor (compatible con WP Mail SMTP).
 * Version: 1.0.3
 * Author: PD Imaging
 * Text Domain: pd-imaging-orden
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PD_IMAGING_ORDEN_VERSION', '1.0.3');
define('PD_IMAGING_ORDEN_PATH', plugin_dir_path(__FILE__));
define('PD_IMAGING_ORDEN_URL', plugin_dir_url(__FILE__));

/**
 * URL del logo PD Imaging (correos y vistas; filtrable).
 */
function pd_imaging_orden_logo_url(): string
{
    $default = 'https://pd-imaging.com/wp-content/uploads/2026/04/pd-imaging-centro-de-imagenes-logo.png';
    return apply_filters('pd_imaging_orden_logo_url', $default);
}

require_once PD_IMAGING_ORDEN_PATH . 'includes/order-view.php';

/**
 * Correo del dueño del sitio (filtro para sobrescribir).
 */
function pd_imaging_orden_owner_email(): string
{
    return apply_filters('pd_imaging_orden_owner_email', get_option('admin_email'));
}

/**
 * ID de página de WordPress donde se muestra la orden (hero del tema + contenido).
 * 0 = modo anterior: pantalla completa sin tema.
 */
function pd_imaging_orden_get_view_page_id(): int
{
    return (int) apply_filters('pd_imaging_orden_view_page_id', (int) get_option('pd_imaging_orden_view_page_id', 0));
}

/**
 * Sanitiza el POST completo del formulario.
 *
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function pd_imaging_orden_sanitize_submission(array $post): array
{
    $clean = [];
    foreach ($post as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        if (in_array($key, ['action', 'pd_orden_nonce', 'pd_hp_check'], true)) {
            continue;
        }
        if (is_array($value)) {
            $clean[$key] = array_map(
                static function ($v) {
                    return sanitize_text_field(wp_unslash((string) $v));
                },
                $value
            );
        } else {
            $clean[$key] = sanitize_textarea_field(wp_unslash((string) $value));
        }
    }
    return $clean;
}

/**
 * @param array<string, mixed> $data
 */
function pd_imaging_orden_save(array $data): ?int
{
    $patient = isset($data['paciente_nombre']) ? $data['paciente_nombre'] : '';
    $title = sprintf(
        /* translators: %s: patient name */
        __('Orden — %s', 'pd-imaging-orden'),
        $patient !== '' ? $patient : __('Sin nombre', 'pd-imaging-orden')
    );

    $post_id = wp_insert_post(
        [
            'post_type'    => 'pd_rd_order',
            'post_status'  => 'private',
            'post_title'   => $title,
            'post_content' => '',
        ],
        true
    );

    if (is_wp_error($post_id) || !$post_id) {
        return null;
    }

    $token = wp_generate_password(48, false, false);
    update_post_meta($post_id, '_pd_order_token', $token);
    update_post_meta($post_id, '_pd_order_data', wp_json_encode($data, JSON_UNESCAPED_UNICODE));
    update_post_meta($post_id, '_pd_order_created', current_time('mysql'));

    return (int) $post_id;
}

function pd_imaging_orden_get_public_url(string $token): string
{
    $page_id = pd_imaging_orden_get_view_page_id();
    if ($page_id > 0) {
        $permalink = get_permalink($page_id);
        if (is_string($permalink) && $permalink !== '') {
            return add_query_arg('pd_orden', rawurlencode($token), $permalink);
        }
    }
    return add_query_arg('pd_orden', rawurlencode($token), home_url('/'));
}

function pd_imaging_orden_find_by_token(string $token): ?WP_Post
{
    $q = new WP_Query(
        [
            'post_type'      => 'pd_rd_order',
            'post_status'    => 'private',
            'posts_per_page' => 1,
            'meta_key'       => '_pd_order_token',
            'meta_value'     => $token,
            'fields'         => 'all',
            'no_found_rows'  => true,
        ]
    );
    if (!$q->have_posts()) {
        return null;
    }
    return $q->posts[0];
}

/**
 * @return array<string, mixed>|null
 */
function pd_imaging_orden_get_data(WP_Post $post): ?array
{
    $raw = get_post_meta($post->ID, '_pd_order_data', true);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * @param array<string, mixed> $data
 */
function pd_imaging_orden_email_esc_field(array $data, string $key): string
{
    $v = isset($data[$key]) ? trim((string) $data[$key]) : '';
    return $v !== '' ? esc_html($v) : '—';
}

/**
 * Bloque de firma con logo PD Imaging (HTML para correo).
 */
function pd_imaging_orden_email_signature_html(): string
{
    $logo = esc_url(pd_imaging_orden_logo_url());
    $name = esc_html__('PD Imaging — Centro de Imágenes', 'pd-imaging-orden');
    $line1 = esc_html__('Diagnóstico por imagen de alta calidad', 'pd-imaging-orden');
    $home = home_url('/');
    $link_text = esc_html(preg_replace('#^https?://#', '', $home));

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;border-top:1px solid #e2e8f0;padding-top:20px;">'
        . '<tr><td>'
        . '<img src="' . $logo . '" alt="PD Imaging" width="200" style="max-width:200px;height:auto;display:block;margin-bottom:12px;border:0;">'
        . '<p style="margin:0 0 4px;font-size:14px;font-weight:700;color:#0c2340;">' . $name . '</p>'
        . '<p style="margin:0 0 4px;font-size:13px;color:#475569;line-height:1.45;">' . $line1 . '</p>'
        . '<p style="margin:0;font-size:13px;color:#64748b;"><a href="' . esc_url($home) . '" style="color:#4c65a4;text-decoration:none;">' . $link_text . '</a></p>'
        . '</td></tr></table>';
}

/**
 * Resumen paciente + doctor en tabla para correo.
 *
 * @param array<string, mixed> $data
 */
function pd_imaging_orden_email_summary_table_html(array $data): string
{
    $rows = [
        [__('Paciente', 'pd-imaging-orden'), pd_imaging_orden_email_esc_field($data, 'paciente_nombre')],
        [__('Edad', 'pd-imaging-orden'), pd_imaging_orden_email_esc_field($data, 'paciente_edad')],
        [__('DNI / Carnet', 'pd-imaging-orden'), pd_imaging_orden_email_esc_field($data, 'paciente_dni')],
        [__('Celular (paciente)', 'pd-imaging-orden'), pd_imaging_orden_email_esc_field($data, 'paciente_celular')],
        [__('Correo (paciente)', 'pd-imaging-orden'), pd_imaging_orden_email_esc_field($data, 'paciente_correo')],
        [__('Doctor(a)', 'pd-imaging-orden'), pd_imaging_orden_email_esc_field($data, 'doctor_nombre')],
        [__('COP', 'pd-imaging-orden'), pd_imaging_orden_email_esc_field($data, 'doctor_cop')],
        [__('Celular (doctor)', 'pd-imaging-orden'), pd_imaging_orden_email_esc_field($data, 'doctor_celular')],
        [__('Correo (doctor)', 'pd-imaging-orden'), pd_imaging_orden_email_esc_field($data, 'doctor_correo')],
        [__('Dirección (consultorio)', 'pd-imaging-orden'), pd_imaging_orden_email_esc_field($data, 'doctor_direccion')],
    ];

    $out = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">';
    foreach ($rows as $row) {
        $out .= '<tr><td style="padding:10px 14px;font-size:13px;color:#64748b;width:38%;border-bottom:1px solid #f1f5f9;vertical-align:top;">'
            . esc_html($row[0]) . '</td>'
            . '<td style="padding:10px 14px;font-size:14px;color:#0f172a;border-bottom:1px solid #f1f5f9;">' . $row[1] . '</td></tr>';
    }
    $out .= '</table>';
    return $out;
}

/**
 * Plantilla HTML de correo (tablas + estilos inline para clientes de correo).
 *
 * @param string $signature_html Firma al pie del cuerpo (logo PD Imaging, etc.).
 */
function pd_imaging_orden_email_wrap(string $heading, string $body_html, string $button_url, string $button_label, string $signature_html = ''): string
{
    $safe_heading = esc_html($heading);
    $site = esc_html(get_bloginfo('name'));
    $year = (int) gmdate('Y');
    $sig = $signature_html !== '' ? $signature_html : pd_imaging_orden_email_signature_html();

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $safe_heading . '</title></head><body style="margin:0;padding:0;background:#f1f5f9;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">'
        . '<tr><td style="padding:28px 24px 8px;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;">'
        . '<p style="margin:0 0 8px;font-size:18px;font-weight:700;color:#0c2340;">' . $safe_heading . '</p>'
        . '<div style="font-size:15px;line-height:1.55;color:#334155;">' . $body_html . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:8px 24px 28px;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;"><tr><td align="center" style="padding-top:8px;">'
        . '<a href="' . esc_url($button_url) . '" style="display:inline-block;background:#4c65a4;color:#ffffff;text-decoration:none;font-weight:600;font-size:16px;padding:14px 28px;border-radius:12px;">'
        . esc_html($button_label) . '</a>'
        . '</td></tr></table>'
        . '<p style="margin:20px 0 0;font-size:12px;line-height:1.5;color:#64748b;word-break:break-all;">'
        . esc_html__('Si el botón no funciona, copie y pegue este enlace en su navegador:', 'pd-imaging-orden')
        . '<br><a href="' . esc_url($button_url) . '" style="color:#4c65a4;">' . esc_html($button_url) . '</a>'
        . '</p>'
        . $sig
        . '</td></tr>'
        . '</table>'
        . '<p style="margin:16px 0 0;font-family:Inter,system-ui,-apple-system,sans-serif;font-size:12px;color:#94a3b8;">© ' . esc_html((string) $year) . ' ' . $site . '</p>'
        . '</td></tr></table></body></html>';
}

/**
 * @param array<string, mixed> $data
 */
function pd_imaging_orden_send_notifications(int $post_id, array $data, string $public_url): void
{
    $patient_name = isset($data['paciente_nombre']) ? $data['paciente_nombre'] : '';
    $doctor_name = isset($data['doctor_nombre']) ? $data['doctor_nombre'] : '';
    $summary = pd_imaging_orden_email_summary_table_html($data);
    $sig = ''; // firma por defecto en wrap

    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $owner = pd_imaging_orden_owner_email();
    if (is_email($owner)) {
        $subject = sprintf(
            /* translators: %s patient */
            __('[%s] Nueva orden radiológica — %s', 'pd-imaging-orden'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            $patient_name !== '' ? $patient_name : __('Paciente', 'pd-imaging-orden')
        );
        $body = pd_imaging_orden_email_wrap(
            __('Nueva orden recibida', 'pd-imaging-orden'),
            '<p>' . sprintf(
                /* translators: 1: patient 2: doctor */
                esc_html__('Paciente: %1$s · Doctor: %2$s', 'pd-imaging-orden'),
                esc_html($patient_name !== '' ? $patient_name : '—'),
                esc_html($doctor_name !== '' ? $doctor_name : '—')
            ) . '</p>'
                . $summary
                . '<p>' . esc_html__('Puede abrir o imprimir la orden desde el siguiente enlace.', 'pd-imaging-orden') . '</p>',
            $public_url,
            __('Ver / descargar orden', 'pd-imaging-orden'),
            $sig
        );
        wp_mail($owner, $subject, $body, $headers);
    }

    $patient_mail = isset($data['paciente_correo']) ? sanitize_email($data['paciente_correo']) : '';
    if ($patient_mail && is_email($patient_mail)) {
        $greet = $patient_name !== ''
            ? sprintf(
                /* translators: %s: patient first name or full name */
                esc_html__('Estimado/a %s,', 'pd-imaging-orden'),
                esc_html($patient_name)
            )
            : esc_html__('Estimado/a paciente,', 'pd-imaging-orden');
        $subject = sprintf(
            /* translators: %s: site name */
            __('Su orden radiológica — %s', 'pd-imaging-orden'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );
        $body_inner = '<p style="margin:0 0 12px;">' . $greet . '</p>'
            . '<p>' . esc_html__('Gracias por completar su orden digital en PD Imaging. A continuación resumimos los datos registrados junto con su médico tratante.', 'pd-imaging-orden') . '</p>'
            . $summary
            . '<p>' . esc_html__('Desde el siguiente enlace podrá ver la orden con el mismo formato del sitio web. Use «Imprimir» o «Guardar como PDF» en su navegador para conservar una copia.', 'pd-imaging-orden') . '</p>';
        $body = pd_imaging_orden_email_wrap(
            __('Su orden radiológica digital', 'pd-imaging-orden'),
            $body_inner,
            $public_url,
            __('Abrir mi orden', 'pd-imaging-orden'),
            $sig
        );
        wp_mail($patient_mail, $subject, $body, $headers);
    }

    $doctor_mail = isset($data['doctor_correo']) ? sanitize_email($data['doctor_correo']) : '';
    if ($doctor_mail && is_email($doctor_mail)) {
        $greet_doc = $doctor_name !== ''
            ? sprintf(
                /* translators: %s: doctor name */
                esc_html__('Estimado/a Dr(a). %s,', 'pd-imaging-orden'),
                esc_html($doctor_name)
            )
            : esc_html__('Estimado/a doctor(a),', 'pd-imaging-orden');
        $subject = sprintf(
            /* translators: %s patient name */
            __('Orden radiológica digital — paciente %s', 'pd-imaging-orden'),
            $patient_name !== '' ? $patient_name : __('(sin nombre)', 'pd-imaging-orden')
        );
        $body_inner = '<p style="margin:0 0 12px;">' . $greet_doc . '</p>'
            . '<p>' . esc_html__('Se ha registrado una orden radiológica con los datos siguientes. Puede revisar el detalle clínico y las indicaciones desde el enlace.', 'pd-imaging-orden') . '</p>'
            . $summary
            . '<p>' . esc_html__('El enlace abre la orden completa para consulta o archivo en PDF.', 'pd-imaging-orden') . '</p>';
        $body = pd_imaging_orden_email_wrap(
            __('Notificación de orden — PD Imaging', 'pd-imaging-orden'),
            $body_inner,
            $public_url,
            __('Ver orden del paciente', 'pd-imaging-orden'),
            $sig
        );
        wp_mail($doctor_mail, $subject, $body, $headers);
    }

    do_action('pd_imaging_orden_after_send', $post_id, $data, $public_url);
}

function pd_imaging_orden_handle_post(): void
{
    if (!isset($_POST['action']) || $_POST['action'] !== 'procesar_orden_pd') {
        return;
    }

    if (!empty($_POST['pd_hp_check'])) {
        wp_die(esc_html__('Solicitud no válida.', 'pd-imaging-orden'), 400);
    }

    if (empty($_POST['pd_orden_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pd_orden_nonce'])), 'pd_procesar_orden')) {
        wp_die(esc_html__('La verificación de seguridad falló. Vuelva a cargar la página e intente de nuevo.', 'pd-imaging-orden'), 403);
    }

    $data = pd_imaging_orden_sanitize_submission($_POST);

    $patient = isset($data['paciente_nombre']) ? trim((string) $data['paciente_nombre']) : '';
    $doctor = isset($data['doctor_nombre']) ? trim((string) $data['doctor_nombre']) : '';

    if ($patient === '' || $doctor === '') {
        wp_die(esc_html__('Faltan datos obligatorios (paciente o doctor).', 'pd-imaging-orden'), 400);
    }

    $post_id = pd_imaging_orden_save($data);
    if (!$post_id) {
        wp_die(esc_html__('No se pudo guardar la orden. Intente más tarde.', 'pd-imaging-orden'), 500);
    }

    $token = (string) get_post_meta($post_id, '_pd_order_token', true);
    $url = pd_imaging_orden_get_public_url($token);

    pd_imaging_orden_send_notifications($post_id, $data, $url);

    $redirect = wp_get_referer();
    if (!$redirect) {
        $redirect = home_url('/');
    }
    wp_safe_redirect(add_query_arg('pd_orden_enviada', '1', $redirect));
    exit;
}

function pd_imaging_orden_register_cpt(): void
{
    register_post_type(
        'pd_rd_order',
        [
            'labels' => [
                'name'          => __('Órdenes radiológicas', 'pd-imaging-orden'),
                'singular_name' => __('Orden radiológica', 'pd-imaging-orden'),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-clipboard',
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'supports'            => ['title'],
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
        ]
    );
}

/**
 * Botón imprimir + contenedor de la orden (vista pública).
 *
 * @param array<string, mixed> $data
 */
function pd_imaging_orden_render_public_order_fragment(array $data): void
{
    echo '<div class="pd-order-public-wrap">';
    pd_imaging_render_order_view($data, ['readonly' => true]);
    echo '<div class="pd-view-actions pd-view-actions--footer">';
    echo '<button type="button" class="pd-download-btn" onclick="window.print()">' . esc_html__('Descargar / imprimir PDF', 'pd-imaging-orden') . '</button>';
    echo '</div></div>';
}

/**
 * Respuesta HTTP para ?pd_orden= : incrustado en página del tema o pantalla completa.
 */
function pd_imaging_orden_template_public_orden(): void
{
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || is_customize_preview()) {
        return;
    }

    if (empty($_GET['pd_orden'])) {
        return;
    }

    $token = sanitize_text_field(wp_unslash((string) $_GET['pd_orden']));
    if ($token === '') {
        return;
    }

    unset($GLOBALS['pd_imaging_orden_embed'], $GLOBALS['pd_imaging_orden_public_error'], $GLOBALS['pd_imaging_orden_embed_rendered']);

    $page_id = pd_imaging_orden_get_view_page_id();
    $order_post = pd_imaging_orden_find_by_token($token);

    if ($page_id > 0) {
        if (!$order_post) {
            if (is_page($page_id)) {
                $GLOBALS['pd_imaging_orden_public_error'] = 'not_found';
            } else {
                $permalink = get_permalink($page_id);
                if (is_string($permalink) && $permalink !== '') {
                    nocache_headers();
                    wp_safe_redirect(add_query_arg('pd_orden', rawurlencode($token), $permalink));
                    exit;
                }
            }
            return;
        }

        $data = pd_imaging_orden_get_data($order_post);
        if ($data === null) {
            if (is_page($page_id)) {
                $GLOBALS['pd_imaging_orden_public_error'] = 'no_data';
            } else {
                $permalink = get_permalink($page_id);
                if (is_string($permalink) && $permalink !== '') {
                    nocache_headers();
                    wp_safe_redirect(add_query_arg('pd_orden', rawurlencode($token), $permalink));
                    exit;
                }
            }
            return;
        }

        if (!is_page($page_id)) {
            $permalink = get_permalink($page_id);
            if (is_string($permalink) && $permalink !== '') {
                nocache_headers();
                wp_safe_redirect(add_query_arg('pd_orden', rawurlencode($token), $permalink));
                exit;
            }
            // Página configurada no disponible: mostrar orden a pantalla completa en esta URL.
        } else {
            $GLOBALS['pd_imaging_orden_embed'] = [
                'data'  => $data,
                'token' => $token,
            ];
            nocache_headers();
            header('X-Robots-Tag: noindex, nofollow', true);
            return;
        }
    }

    if (!$order_post) {
        status_header(404);
        nocache_headers();
        wp_die(esc_html__('Orden no encontrada o el enlace ha caducado.', 'pd-imaging-orden'), 404);
    }

    $data = pd_imaging_orden_get_data($order_post);
    if ($data === null) {
        status_header(404);
        nocache_headers();
        wp_die(esc_html__('No hay datos para esta orden.', 'pd-imaging-orden'), 404);
    }

    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow', true);

    echo '<!DOCTYPE html><html ';
    language_attributes();
    echo '><head><meta charset="';
    bloginfo('charset');
    echo '"><meta name="viewport" content="width=device-width, initial-scale=1">';
    wp_head();
    echo '</head><body class="pd-order-public">';
    pd_imaging_orden_render_public_order_fragment($data);
    wp_footer();
    echo '</body></html>';
    exit;
}

/**
 * Tras el contenido de la página configurada: orden + botón PDF abajo a la derecha.
 */
function pd_imaging_orden_append_embed_to_content(string $content): string
{
    if (is_admin()) {
        return $content;
    }

    $page_id = pd_imaging_orden_get_view_page_id();
    if ($page_id <= 0 || !is_singular('page') || (int) get_queried_object_id() !== $page_id) {
        return $content;
    }

    if (!empty($GLOBALS['pd_imaging_orden_embed_rendered'])) {
        return $content;
    }

    if (!empty($GLOBALS['pd_imaging_orden_public_error'])) {
        $code = (string) $GLOBALS['pd_imaging_orden_public_error'];
        if ($code === 'not_found') {
            $msg = __('Orden no encontrada o el enlace no es válido.', 'pd-imaging-orden');
        } elseif ($code === 'no_data') {
            $msg = __('No hay datos para esta orden.', 'pd-imaging-orden');
        } else {
            $msg = __('No se pudo mostrar la orden.', 'pd-imaging-orden');
        }
        $GLOBALS['pd_imaging_orden_embed_rendered'] = true;
        return $content . '<div class="pd-order-public-error" style="max-width:960px;margin:2rem auto;padding:1rem 1.25rem;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;color:#991b1b;font-family:system-ui,sans-serif;">'
            . '<p style="margin:0;">' . esc_html($msg) . '</p></div>';
    }

    if (empty($GLOBALS['pd_imaging_orden_embed']) || !is_array($GLOBALS['pd_imaging_orden_embed'])) {
        return $content;
    }

    $embed = $GLOBALS['pd_imaging_orden_embed'];
    $data = isset($embed['data']) && is_array($embed['data']) ? $embed['data'] : null;
    if ($data === null) {
        return $content;
    }

    $GLOBALS['pd_imaging_orden_embed_rendered'] = true;
    ob_start();
    pd_imaging_orden_render_public_order_fragment($data);
    return $content . ob_get_clean();
}

/**
 * Clase en body cuando la orden va embebida (estilos del tema + orden).
 *
 * @param string[] $classes
 * @return string[]
 */
function pd_imaging_orden_body_class_public(array $classes): array
{
    if (!empty($_GET['pd_orden']) && !is_admin() && pd_imaging_orden_get_view_page_id() > 0 && !empty($GLOBALS['pd_imaging_orden_embed'])) {
        $classes[] = 'pd-order-public';
    }
    return $classes;
}

function pd_imaging_orden_enqueue_assets(): void
{
    if (empty($_GET['pd_orden'])) {
        return;
    }
    wp_enqueue_style(
        'pd-imaging-inter',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        [],
        null
    );
    wp_enqueue_style(
        'pd-imaging-orden-form',
        PD_IMAGING_ORDEN_URL . 'assets/order-form.css',
        ['pd-imaging-inter'],
        PD_IMAGING_ORDEN_VERSION
    );
}

/**
 * Campos ocultos de nonce + honeypot (misma acción que en el procesador).
 */
function pd_imaging_orden_nonce_fields_html(): string
{
    $honeypot = '<input type="text" name="pd_hp_check" value="" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;" tabindex="-1" autocomplete="off" aria-hidden="true">';
    return wp_nonce_field('pd_procesar_orden', 'pd_orden_nonce', true, false) . $honeypot;
}

/**
 * Inserta el nonce dentro de &lt;form id="pd-form"&gt; si aún no existe.
 * Cubre páginas donde el shortcode no se ejecuta (p. ej. bloque HTML personalizado).
 */
function pd_imaging_orden_inject_nonce_into_markup(string $html): string
{
    if (stripos($html, 'pd-form') === false) {
        return $html;
    }
    if (preg_match('/name\\s*=\\s*["\']pd_orden_nonce["\']/i', $html)) {
        return $html;
    }
    if (!preg_match('/<form\\b[^>]*\\bid\\s*=\\s*["\']pd-form["\'][^>]*>/i', $html)) {
        return $html;
    }
    $inject = pd_imaging_orden_nonce_fields_html();
    $out = preg_replace(
        '/<form\\b([^>]*\\bid\\s*=\\s*["\']pd-form["\'][^>]*)>/i',
        '<form$1>' . $inject,
        $html,
        1,
        $count
    );
    return ($count > 0) ? $out : $html;
}

/**
 * Devuelve un nonce válido vía AJAX (sin caché). Sirve para páginas cacheadas donde el HTML lleva un nonce caducado.
 */
function pd_imaging_orden_ajax_issue_nonce(): void
{
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    wp_send_json_success(
        [
            'nonce' => wp_create_nonce('pd_procesar_orden'),
        ]
    );
}

/**
 * En el pie: si existe #pd-form, pide un nonce fresco a admin-ajax y lo aplica (DOMContentLoaded + siempre sobrescribe).
 */
function pd_imaging_orden_footer_nonce_fallback(): void
{
    if (is_admin()) {
        return;
    }

    $ajax_url = admin_url('admin-ajax.php');
    ?>
<script>
(function(){
var ajaxUrl=<?php echo wp_json_encode($ajax_url); ?>;
function ensureHoney(f){
var h=f.querySelector('input[name="pd_hp_check"]');
if(h)return;
h=document.createElement('input');
h.type='text';
h.name='pd_hp_check';
h.value='';
h.setAttribute('autocomplete','off');
h.setAttribute('aria-hidden','true');
h.tabIndex=-1;
h.style.cssText='position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;opacity:0!important';
f.insertBefore(h,f.firstChild);
}
function applyNonce(f,nonce){
if(!f||!nonce)return;
var i=f.querySelector('input[name="pd_orden_nonce"]');
if(!i){i=document.createElement('input');i.type='hidden';i.name='pd_orden_nonce';f.insertBefore(i,f.firstChild);}
i.value=nonce;
ensureHoney(f);
}
function refreshNonce(){
var f=document.getElementById('pd-form');
if(!f)return;
ensureHoney(f);
var url=ajaxUrl+'?action=pd_imaging_orden_nonce&_='+Date.now();
fetch(url,{credentials:'same-origin',cache:'no-store'})
.then(function(r){return r.json();})
.then(function(d){
if(d&&d.success&&d.data&&d.data.nonce)applyNonce(f,d.data.nonce);
})
.catch(function(){});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',refreshNonce);
else refreshNonce();
})();
</script>
    <?php
}

/**
 * Shortcode opcional: coloque justo después de abrir &lt;form id="pd-form"&gt; …
 * [pd_orden_nonce]
 */
function pd_imaging_orden_shortcode_nonce(): string
{
    return pd_imaging_orden_nonce_fields_html();
}

function pd_imaging_orden_register_order_settings(): void
{
    register_setting(
        'pd_imaging_orden_settings',
        'pd_imaging_orden_view_page_id',
        [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]
    );
}

function pd_imaging_orden_add_settings_page(): void
{
    add_submenu_page(
        'edit.php?post_type=pd_rd_order',
        __('Enlace público de la orden', 'pd-imaging-orden'),
        __('Enlace público', 'pd-imaging-orden'),
        'manage_options',
        'pd-imaging-orden-settings',
        'pd_imaging_orden_render_settings_page'
    );
}

function pd_imaging_orden_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Orden digital — página del enlace', 'pd-imaging-orden'); ?></h1>
        <p class="description"><?php echo esc_html__('Elija una página de WordPress para abrir las órdenes desde el correo. Podrá diseñar un hero y contenido arriba; la orden y el botón de PDF se mostrarán debajo.', 'pd-imaging-orden'); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('pd_imaging_orden_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="pd_imaging_orden_view_page_id"><?php echo esc_html__('Página de vista de la orden', 'pd-imaging-orden'); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_dropdown_pages(
                            [
                                'name'              => 'pd_imaging_orden_view_page_id',
                                'id'                => 'pd_imaging_orden_view_page_id',
                                'show_option_none'  => __('— Sin página (pantalla completa, sin tema) —', 'pd-imaging-orden'),
                                'option_none_value' => '0',
                                'selected'          => pd_imaging_orden_get_view_page_id(),
                            ]
                        );
                        ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('init', 'pd_imaging_orden_register_cpt');
add_action('admin_init', 'pd_imaging_orden_register_order_settings');
add_action('admin_menu', 'pd_imaging_orden_add_settings_page');
add_action('wp_ajax_nopriv_pd_imaging_orden_nonce', 'pd_imaging_orden_ajax_issue_nonce');
add_action('wp_ajax_pd_imaging_orden_nonce', 'pd_imaging_orden_ajax_issue_nonce');
add_action('admin_post_nopriv_procesar_orden_pd', 'pd_imaging_orden_handle_post');
add_action('admin_post_procesar_orden_pd', 'pd_imaging_orden_handle_post');
add_action('template_redirect', 'pd_imaging_orden_template_public_orden', 0);
add_action('wp_enqueue_scripts', 'pd_imaging_orden_enqueue_assets');
add_filter('body_class', 'pd_imaging_orden_body_class_public');
add_filter('the_content', 'pd_imaging_orden_append_embed_to_content', 25);
add_filter('the_content', 'pd_imaging_orden_inject_nonce_into_markup', 20);
add_filter('widget_block_content', 'pd_imaging_orden_inject_nonce_into_markup', 20);

/**
 * Bloques HTML del editor (contenido sin pasar siempre por the_content completo).
 *
 * @param string                $block_content
 * @param array<string, mixed> $block
 */
function pd_imaging_orden_render_block_inject(string $block_content, array $block): string
{
    if (($block['blockName'] ?? '') !== 'core/html') {
        return $block_content;
    }
    return pd_imaging_orden_inject_nonce_into_markup($block_content);
}

add_filter('render_block', 'pd_imaging_orden_render_block_inject', 10, 2);

add_action('elementor/loaded', static function (): void {
    add_filter('elementor/frontend/the_content', 'pd_imaging_orden_inject_nonce_into_markup');
    add_filter('elementor/frontend/the_content', 'pd_imaging_orden_append_embed_to_content', 25);
});

add_action('wp_footer', 'pd_imaging_orden_footer_nonce_fallback', 999);

add_shortcode('pd_orden_nonce', 'pd_imaging_orden_shortcode_nonce');

register_activation_hook(__FILE__, static function (): void {
    pd_imaging_orden_register_cpt();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, static function (): void {
    flush_rewrite_rules();
});
