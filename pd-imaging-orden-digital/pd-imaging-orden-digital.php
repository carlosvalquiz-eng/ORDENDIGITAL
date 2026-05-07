<?php
/**
 * Plugin Name: PD Imaging — Orden radiológica digital
 * Description: Procesa el envío del formulario, guarda la orden con enlace único y notifica por correo al dueño, paciente y doctor (compatible con WP Mail SMTP).
 * Version: 1.0.0
 * Author: PD Imaging
 * Text Domain: pd-imaging-orden
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PD_IMAGING_ORDEN_VERSION', '1.0.0');
define('PD_IMAGING_ORDEN_PATH', plugin_dir_path(__FILE__));
define('PD_IMAGING_ORDEN_URL', plugin_dir_url(__FILE__));

require_once PD_IMAGING_ORDEN_PATH . 'includes/order-view.php';

/**
 * Correo del dueño del sitio (filtro para sobrescribir).
 */
function pd_imaging_orden_owner_email(): string
{
    return apply_filters('pd_imaging_orden_owner_email', get_option('admin_email'));
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
 * Plantilla HTML de correo (tablas + estilos inline para clientes de correo).
 */
function pd_imaging_orden_email_wrap(string $heading, string $body_html, string $button_url, string $button_label): string
{
    $safe_heading = esc_html($heading);
    $site = esc_html(get_bloginfo('name'));
    $year = (int) gmdate('Y');

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
                . '<p>' . esc_html__('Puede abrir o imprimir la orden desde el siguiente enlace.', 'pd-imaging-orden') . '</p>',
            $public_url,
            __('Ver / descargar orden', 'pd-imaging-orden')
        );
        wp_mail($owner, $subject, $body, $headers);
    }

    $patient_mail = isset($data['paciente_correo']) ? sanitize_email($data['paciente_correo']) : '';
    if ($patient_mail && is_email($patient_mail)) {
        $subject = __('Su orden radiológica PD Imaging — enlace de descarga', 'pd-imaging-orden');
        $body = pd_imaging_orden_email_wrap(
            __('Gracias por completar su orden', 'pd-imaging-orden'),
            '<p>' . esc_html__('Adjuntamos el enlace para ver e imprimir su orden radiológica en el mismo formato del sitio web (use Imprimir / Guardar como PDF en su navegador).', 'pd-imaging-orden') . '</p>',
            $public_url,
            __('Abrir mi orden', 'pd-imaging-orden')
        );
        wp_mail($patient_mail, $subject, $body, $headers);
    }

    $doctor_mail = isset($data['doctor_correo']) ? sanitize_email($data['doctor_correo']) : '';
    if ($doctor_mail && is_email($doctor_mail)) {
        $subject = sprintf(
            /* translators: %s patient name */
            __('Orden radiológica — paciente %s', 'pd-imaging-orden'),
            $patient_name !== '' ? $patient_name : __('(sin nombre)', 'pd-imaging-orden')
        );
        $body = pd_imaging_orden_email_wrap(
            __('Orden radiológica digital', 'pd-imaging-orden'),
            '<p>' . esc_html__('Puede consultar la orden completa y descargarla en PDF desde el enlace.', 'pd-imaging-orden') . '</p>',
            $public_url,
            __('Ver orden', 'pd-imaging-orden')
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

function pd_imaging_orden_maybe_render(): void
{
    if (empty($_GET['pd_orden']) || is_admin()) {
        return;
    }

    $token = sanitize_text_field(wp_unslash((string) $_GET['pd_orden']));
    if ($token === '') {
        return;
    }

    $post = pd_imaging_orden_find_by_token($token);
    if (!$post) {
        status_header(404);
        nocache_headers();
        wp_die(esc_html__('Orden no encontrada o el enlace ha caducado.', 'pd-imaging-orden'), 404);
    }

    $data = pd_imaging_orden_get_data($post);
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
    echo '<div class="pd-view-actions" style="max-width:1600px;margin:0 auto;padding:1rem;text-align:center;font-family:Inter,system-ui,sans-serif;">';
    echo '<button type="button" class="pd-download-btn" onclick="window.print()">' . esc_html__('Descargar / imprimir PDF', 'pd-imaging-orden') . '</button>';
    echo '</div>';
    pd_imaging_render_order_view($data, ['readonly' => true]);
    wp_footer();
    echo '</body></html>';
    exit;
}

/**
 * Shortcode: coloque justo después de abrir &lt;form id="pd-form"&gt; …
 * [pd_orden_nonce]
 */
function pd_imaging_orden_shortcode_nonce(): string
{
    $field = wp_nonce_field('pd_procesar_orden', 'pd_orden_nonce', true, false);
    $honeypot = '<input type="text" name="pd_hp_check" value="" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;" tabindex="-1" autocomplete="off" aria-hidden="true">';
    return $field . $honeypot;
}

add_action('init', 'pd_imaging_orden_register_cpt');
add_action('admin_post_nopriv_procesar_orden_pd', 'pd_imaging_orden_handle_post');
add_action('admin_post_procesar_orden_pd', 'pd_imaging_orden_handle_post');
add_action('template_redirect', 'pd_imaging_orden_maybe_render', 0);
add_action('wp_enqueue_scripts', 'pd_imaging_orden_enqueue_assets');

add_shortcode('pd_orden_nonce', 'pd_imaging_orden_shortcode_nonce');

register_activation_hook(__FILE__, static function (): void {
    pd_imaging_orden_register_cpt();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, static function (): void {
    flush_rewrite_rules();
});
