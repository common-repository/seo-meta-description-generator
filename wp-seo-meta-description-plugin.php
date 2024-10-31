<?php
/*
Plugin Name: SEO Meta Description Generator
Plugin URI: https://laguiaseo.com/meta-descripciones-inteligentes-y-masivas-con-seo-meta-description-generator/
Description: Genera automáticamente meta descripciones optimizadas para SEO basadas en el contenido de la página o entrada, con opción de generación masiva y uso de IA.
Version: 1.0
Author: Jony Ortega
Author URI: http://jonyonlinecash.com
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: seo-meta-description-generator
*/

define('SMG_PLUGIN_VERSION', '1.0'); // Ajusta el número de versión según corresponda

// Evita el acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

// Definición de constantes
define('SMG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMG_PLUGIN_URL', plugin_dir_url(__FILE__));

// Incluye los archivos necesarios
require_once SMG_PLUGIN_DIR . 'includes/functions.php';
require_once SMG_PLUGIN_DIR . 'includes/integrations.php';
require_once SMG_PLUGIN_DIR . 'admin/admin-page.php';

// Registra la hoja de estilos para el área de administración
function smg_enqueue_admin_styles($hook) {
    if ('settings_page_seo-meta-generator' !== $hook && 'tools_page_seo-meta-bulk-generator' !== $hook) {
        return;
    }
    wp_enqueue_style('smg-admin-styles', SMG_PLUGIN_URL . 'admin/css/admin-styles.css', array(), SMG_PLUGIN_VERSION);
}
add_action('admin_enqueue_scripts', 'smg_enqueue_admin_styles');

// Agrega un enlace de configuración en la página de plugins
function smg_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=seo-meta-generator') . '">' . __('Configuración') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'smg_add_settings_link');

// Registra la función de activación
register_activation_hook(__FILE__, 'smg_activate');

// Registra la función de desactivación
register_deactivation_hook(__FILE__, 'smg_deactivate');

// Función de activación
function smg_activate() {
    $default_settings = [
        'longitud_descripcion' => 160,
        'openai_api_key' => ''
    ];
    
    add_option('smg_settings', $default_settings);
}

// Función de desactivación
function smg_deactivate() {
    // Puedes decidir si quieres eliminar las opciones al desactivar el plugin
    // delete_option('smg_settings');
}
