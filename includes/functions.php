<?php
// Evita el acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

function smg_save_meta_description($post_id, $meta_description) {
    // Guarda la meta descripción en nuestro propio campo personalizado
    update_post_meta($post_id, '_smg_meta_description', $meta_description);

    // Permite que otros plugins modifiquen o utilicen la meta descripción
    $meta_description = apply_filters('smg_meta_description', $meta_description, $post_id);

    // Actualiza las integraciones con otros plugins SEO
    do_action('smg_after_update_meta_description', $post_id, $meta_description);

    return $meta_description;
}

// Función para generar la meta descripción
function smg_generar_meta_descripcion($contenido, $longitud = 160) {
    $ai_description = smg_generate_ai_description($contenido, $longitud);
    
    if ($ai_description) {
        return $ai_description;
    }
    
    // Si la IA falla, volvemos al método original
    $texto_limpio = wp_strip_all_tags($contenido);
    $texto_limpio = preg_replace('/\s+/', ' ', $texto_limpio);
    $descripcion = substr($texto_limpio, 0, $longitud);
    $descripcion = preg_replace('/\s+?(\S+)?$/', '', $descripcion);
    
    if (strlen($texto_limpio) > $longitud) {
        $descripcion .= '...';
    }
    
    return $descripcion;
}

function smg_generate_ai_description($content, $max_length = 160) {
    $options = get_option('smg_settings');
    $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';

    if (empty($api_key)) {
        return false;
    }

    $api_url = 'https://api.openai.com/v1/chat/completions';

    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    );

    $prompt = "Genera una meta descripción SEO atractiva y relevante basada en el siguiente contenido, en no más de {$max_length} caracteres:\n\n" . substr($content, 0, 1000);

    $body = wp_json_encode(array(
        'model' => 'gpt-3.5-turbo',
        'messages' => array(
            array('role' => 'system', 'content' => 'Eres un asistente experto en SEO que crea meta descripciones concisas y atractivas.'),
            array('role' => 'user', 'content' => $prompt)
        ),
        'max_tokens' => 100,
        'temperature' => 0.7
    ));

    $response = wp_remote_post($api_url, array(
        'headers' => $headers,
        'body' => $body,
        'timeout' => 30
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if (isset($result['choices'][0]['message']['content'])) {
        $description = trim($result['choices'][0]['message']['content']);
        return substr($description, 0, $max_length);
    }

    return false;
}

// Función para agregar la meta descripción al head del sitio
function smg_agregar_meta_descripcion() {
    if (is_singular()) {
        global $post;
        $meta_descripcion = get_post_meta($post->ID, '_smg_meta_description', true);
        if (empty($meta_descripcion)) {
            $settings = get_option('smg_settings');
            $longitud = isset($settings['longitud_descripcion']) ? $settings['longitud_descripcion'] : 160;
            $meta_descripcion = smg_generar_meta_descripcion($post->post_content, $longitud);
            smg_save_meta_description($post->ID, $meta_descripcion);
        }
        echo '<meta name="description" content="' . esc_attr($meta_descripcion) . '">' . "\n";
    }
}
add_action('wp_head', 'smg_agregar_meta_descripcion', 1);

// Función para guardar la meta descripción al guardar/actualizar un post
function smg_guardar_meta_descripcion($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
        return;
    
    if (!current_user_can('edit_post', $post_id))
        return;
    
    $post = get_post($post_id);
    if (!$post) return;

    $settings = get_option('smg_settings');
    $longitud = isset($settings['longitud_descripcion']) ? $settings['longitud_descripcion'] : 160;
    $meta_descripcion = smg_generar_meta_descripcion($post->post_content, $longitud);
    
    smg_save_meta_description($post_id, $meta_descripcion);
}
add_action('save_post', 'smg_guardar_meta_descripcion');
add_action('wp_insert_post', 'smg_guardar_meta_descripcion');

// Función para manejar la generación masiva
function smg_handle_bulk_generate() {
    if (!isset($_POST['smg_bulk_generate_nonce']) || !wp_verify_nonce($_POST['smg_bulk_generate_nonce'], 'smg_bulk_generate')) {
        wp_die('Acceso no autorizado', 'Error', array('response' => 403));
    }

    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción', 'Error', array('response' => 403));
    }

    $post_ids = isset($_POST['smg_posts']) ? (array) $_POST['smg_posts'] : array();
    $category_filter = isset($_POST['smg_category_filter']) ? intval($_POST['smg_category_filter']) : 0;
    $updated = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post) continue;

        // Si hay un filtro de categoría, verifica que el post pertenezca a esa categoría
        if ($category_filter > 0) {
            $post_categories = wp_get_post_categories($post_id);
            if (!in_array($category_filter, $post_categories)) {
                continue;
            }
        }

        $meta_descripcion = smg_generar_meta_descripcion($post->post_content);
        smg_save_meta_description($post_id, $meta_descripcion);
        $updated++;
    }

    wp_redirect(add_query_arg(array(
        'page' => 'seo-meta-bulk-generator',
        'updated' => $updated
    ), admin_url('tools.php')));
    exit;
}
add_action('admin_post_smg_bulk_generate', 'smg_handle_bulk_generate');