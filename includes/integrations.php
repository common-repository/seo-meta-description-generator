<?php
// Evita el acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Actualiza la meta descripción para Yoast SEO
 *
 * @param int $post_id ID del post
 * @param string $meta_description La meta descripción generada
 */
function smg_update_yoast_meta_description($post_id, $meta_description) {
    if (defined('WPSEO_VERSION')) {
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
    }
}

/**
 * Actualiza la meta descripción para Rank Math
 *
 * @param int $post_id ID del post
 * @param string $meta_description La meta descripción generada
 */
function smg_update_rank_math_meta_description($post_id, $meta_description) {
    if (class_exists('RankMath')) {
        update_post_meta($post_id, 'rank_math_description', $meta_description);
    }
}

/**
 * Función principal para manejar la integración con otros plugins SEO
 *
 * @param int $post_id ID del post
 * @param string $meta_description La meta descripción generada
 */
function smg_handle_seo_plugin_integrations($post_id, $meta_description) {
    $options = get_option('smg_settings');
    $update_plugins = isset($options['update_other_plugins']) ? $options['update_other_plugins'] : [];

    if (in_array('yoast', $update_plugins)) {
        smg_update_yoast_meta_description($post_id, $meta_description);
    }

    if (in_array('rank_math', $update_plugins)) {
        smg_update_rank_math_meta_description($post_id, $meta_description);
    }
}

// Añade la función de integración al hook personalizado
add_action('smg_after_update_meta_description', 'smg_handle_seo_plugin_integrations', 10, 2);

/**
 * Comprueba si hay conflictos con otros plugins SEO
 *
 * @return array Array con los nombres de los plugins SEO activos
 */
function smg_check_seo_plugins_conflicts() {
    $active_seo_plugins = [];

    if (defined('WPSEO_VERSION')) {
        $active_seo_plugins[] = 'Yoast SEO';
    }

    if (class_exists('RankMath')) {
        $active_seo_plugins[] = 'Rank Math';
    }

    // Puedes añadir más comprobaciones para otros plugins SEO aquí

    return $active_seo_plugins;
}

/**
 * Muestra una notificación de admin si se detectan conflictos con otros plugins SEO
 */
function smg_admin_notices_seo_conflicts() {
    $active_seo_plugins = smg_check_seo_plugins_conflicts();

    if (!empty($active_seo_plugins)) {
        $plugin_list = implode(', ', $active_seo_plugins);
        ?>
        <div class="notice notice-warning is-dismissible">
            /* translators: %s: lista de plugins SEO activos */
            $message = sprintf(
                esc_html__('SEO Meta Description Generator ha detectado los siguientes plugins SEO activos: %s. Asegúrate de configurar correctamente la integración para evitar conflictos.', 'seo-meta-generator'),
                $plugin_list
            );
            ?>
            <p><?php echo wp_kses_post($message); ?></p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'smg_admin_notices_seo_conflicts');