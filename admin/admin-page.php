<?php
// Evita el acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

// Agrega el menú de opciones
function smg_add_admin_menu() {
    add_options_page(
        'SEO Meta Description Generator',
        'SEO Meta Desc',
        'manage_options',
        'seo-meta-generator',
        'smg_options_page'
    );
    
    add_submenu_page(
        'tools.php',
        'Generar Meta Descripciones',
        'Generar Meta Desc',
        'manage_options',
        'seo-meta-bulk-generator',
        'smg_bulk_generator_page'
    );
}
add_action('admin_menu', 'smg_add_admin_menu');

// Registra las opciones
function smg_settings_init() {
    register_setting('smgPlugin', 'smg_settings');

    add_settings_section(
        'smg_plugin_section',
        __('Configuración de SEO Meta Description Generator', 'seo-meta-description-generator'),
        'smg_settings_section_callback',
        'smgPlugin'
    );

    add_settings_field(
        'smg_longitud_descripcion',
        __('Longitud de la descripción', 'seo-meta-description-generator'),
        'smg_longitud_descripcion_render',
        'smgPlugin',
        'smg_plugin_section'
    );

    add_settings_field(
        'smg_openai_api_key',
        __('Clave API de OpenAI', 'seo-meta-description-generator'),
        'smg_openai_api_key_render',
        'smgPlugin',
        'smg_plugin_section'
    );

    add_settings_field(
        'smg_update_other_plugins',
        __('Actualizar otros plugins SEO', 'seo-meta-description-generator'),
        'smg_update_other_plugins_render',
        'smgPlugin',
        'smg_plugin_section'
    );
}
add_action('admin_init', 'smg_settings_init');

// Renderiza el campo de longitud de descripción
function smg_longitud_descripcion_render() {
    $options = get_option('smg_settings');
    $value = isset($options['longitud_descripcion']) ? intval($options['longitud_descripcion']) : 160;
    echo '<input type="number" name="smg_settings[longitud_descripcion]" value="' . esc_attr($value) . '" min="50" max="320">';
}

// Renderiza el campo de la clave API de OpenAI
function smg_openai_api_key_render() {
    $options = get_option('smg_settings');
    $value = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
    echo '<input type="text" name="smg_settings[openai_api_key]" value="' . esc_attr($value) . '" style="width: 300px;">';
}

// Renderiza el campo para actualizar otros plugins SEO
function smg_update_other_plugins_render() {
    $options = get_option('smg_settings');
    $value = isset($options['update_other_plugins']) ? $options['update_other_plugins'] : [];
    ?>
    <input type="checkbox" name="smg_settings[update_other_plugins][]" value="yoast" <?php checked(in_array('yoast', $value), true); ?>> Yoast SEO<br>
    <input type="checkbox" name="smg_settings[update_other_plugins][]" value="rank_math" <?php checked(in_array('rank_math', $value), true); ?>> Rank Math
    <?php
}

// Callback para la sección de configuración
function smg_settings_section_callback() {
    echo esc_html__('Configura las opciones para el generador de meta descripciones.', 'seo-meta-description-generator');
}

// Renderiza la página de opciones
function smg_options_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action='options.php' method='post'>
            <?php
            settings_fields('smgPlugin');
            do_settings_sections('smgPlugin');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function smg_bulk_generator_page() {
    // Verificar nonce
    $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
    if (!wp_verify_nonce($nonce, 'smg_bulk_generate')) {
        // Si el nonce no es válido, continúa con la renderización normal de la página
        // pero no proceses ningún dato del formulario
    } else {
        // El nonce es válido, procesa los datos del formulario si es necesario
        if (isset($_GET['updated'])) {
            $updated = intval($_GET['updated']);
// translators: %s: número de meta descripciones actualizadas
            $message = sprintf(
                esc_html(_n(
                    '%s meta descripción actualizada.',
                    '%s meta descripciones actualizadas.',
                    $updated,
                    'seo-meta-description-generator'
                )),
                number_format_i18n($updated)
            );
            echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post($message) . '</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php
            wp_nonce_field('smg_bulk_generate', 'smg_bulk_generate_nonce');
            ?>
            <input type="hidden" name="action" value="smg_bulk_generate">
            
            <h2>Filtrar por categorías:</h2>
            <?php
            $categories = get_categories(array('hide_empty' => false));
            if (!empty($categories)) {
                echo '<select name="smg_category_filter" id="smg_category_filter">';
                echo '<option value="">' . esc_html__('Todas las categorías', 'seo-meta-description-generator') . '</option>';
                foreach ($categories as $category) {
                    echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                }
                echo '</select>';
            }
            ?>
            
            <h2><?php esc_html_e('Selecciona las entradas y páginas para generar meta descripciones:', 'seo-meta-description-generator'); ?></h2>
            
            <?php
            $post_types = array('post', 'page');
            foreach ($post_types as $post_type) {
                $posts = get_posts(array(
                    'post_type' => $post_type,
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                ));
                
                if (!empty($posts)) {
                    echo '<h3>' . esc_html(ucfirst($post_type) . 's') . ':</h3>';
                    // translators: %s: tipo de post (por ejemplo, 'posts' o 'pages')
                    $button_text = sprintf(
                        esc_html__('Seleccionar todos los %s', 'seo-meta-description-generator'),
                        esc_html($post_type . 's')
                    );
                    echo '<button type="button" class="button select-all-' . esc_attr($post_type) . 's">' . 
                         esc_html($button_text) . 
                         '</button>';
                    echo '<ul class="' . esc_attr($post_type) . '-list">';
                    foreach ($posts as $post) {
                        $post_categories = get_the_category($post->ID);
                        $category_ids = wp_list_pluck($post_categories, 'term_id');
                        echo '<li class="post-item" data-categories="' . esc_attr(implode(',', $category_ids)) . '">';
                        echo '<input type="checkbox" name="smg_posts[]" value="' . esc_attr($post->ID) . '" id="post-' . esc_attr($post->ID) . '">';
                        echo '<label for="post-' . esc_attr($post->ID) . '">' . esc_html($post->post_title) . '</label>';
                        echo '</li>';
                    }
                    echo '</ul>';
                }
            }
            ?>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Generar Meta Descripciones', 'seo-meta-description-generator'); ?>">
            </p>
        </form>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.select-all-posts').click(function() {
            $('.post-list input[type="checkbox"]:visible').prop('checked', true);
        });
        $('.select-all-pages').click(function() {
            $('.page-list input[type="checkbox"]:visible').prop('checked', true);
        });

        $('#smg_category_filter').change(function() {
            var selectedCategory = $(this).val();
            if (selectedCategory === '') {
                $('.post-item').show();
            } else {
                $('.post-item').each(function() {
                    var postCategories = $(this).data('categories').toString().split(',');
                    if (postCategories.indexOf(selectedCategory) > -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        });
    });
    </script>
    <?php
}