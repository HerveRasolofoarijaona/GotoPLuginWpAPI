<?php
/*
Plugin Name: GOTO Plugins
Description: A plugin to generate a callback and receive input variable code
Version: 1.0
Author: Herve RASOLOFOARIJAONA
Author URI: https://www.facebook.com/GoldenKode
Plugin URI: -
Text Domain: goto-plugins
Domain Path: /languages
Image: images/images.png
*/

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// === Partie 1 : Gestion des paramètres clientID et redirectUri ===

add_action('admin_menu', 'logmein_add_admin_menu');
add_action('admin_init', 'logmein_settings_init');

function logmein_add_admin_menu() {
    add_options_page(
        'GOTO Support API',    // Titre de la page
        'GOTO Support',             // Texte du menu
        'manage_options',           // Capacité requise
        'logmein-auth',             // Slug de la page
        'logmein_options_page'      // Fonction d'affichage
    );
}

function logmein_settings_init() {
    // Enregistrer les paramètres clientID et redirectUri
    register_setting('logmein_auth_group', 'logmein_client_id');
    register_setting('logmein_auth_group', 'logmein_redirect_uri');

    add_settings_section(
        'logmein_auth_section',
        'GOTO APP Settings',
        null,
        'logmein-auth'
    );

    add_settings_field(
        'logmein_client_id',
        'Client ID',
        'logmein_client_id_render',
        'logmein-auth',
        'logmein_auth_section'
    );

    add_settings_field(
        'logmein_redirect_uri',
        'Redirect URI (automatically set)',
        'logmein_redirect_uri_render',
        'logmein-auth',
        'logmein_auth_section'
    );
}

function logmein_client_id_render() {
    $client_id = get_option('logmein_client_id');
    ?>
    <input type="text" name="logmein_client_id" value="<?php echo esc_attr($client_id); ?>" />
    <?php
}

function logmein_redirect_uri_render() {
    // Utiliser l'URL du site pour définir automatiquement le redirect URI
    $redirect_uri = get_option('logmein_redirect_uri', site_url('/wp-json/data-receiver/v1/submit'));
    ?>
    <input type="text" name="logmein_redirect_uri" value="<?php echo esc_attr($redirect_uri); ?>" readonly />
    <?php
}

function logmein_options_page() {
    ?>
    <form action="options.php" method="post">
        <?php
        settings_fields('logmein_auth_group');
        do_settings_sections('logmein-auth');
        submit_button();
        ?>

        <!-- Ajouter un bouton pour lier à OAuth -->
        <h3>OAuth Authentication</h3>
        <p>
            Cliquez sur le bouton ci-dessous pour vous rediriger vers l'authentification OAuth de LogMeIn.
        </p>
    </form>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST">
            <input type="hidden" name="action" value="logmein_auth_redirect">
            <input type="submit" value="Se connecter via LogMeIn OAuth" class="button button-primary">
    </form>
    <?php
}

// === Partie 2 : Redirection vers l'URL OAuth de LogMeIn ===

add_action('admin_post_logmein_auth_redirect', 'logmein_auth_redirect');
function logmein_auth_redirect() {
    $client_id = get_option('logmein_client_id');
    $redirect_uri = get_option('logmein_redirect_uri', site_url('/wp-json/data-receiver/v1/submit'));

    if ($client_id && $redirect_uri) {
        $oauth_url = 'https://authentication.logmeininc.com/oauth/authorize';
        $oauth_url .= '?client_id=' . urlencode($client_id);
        $oauth_url .= '&response_type=code';
        $oauth_url .= '&redirect_uri=' . esc_attr($redirect_uri);

        echo '<script>
                function showAuthDialog() {
                    alert("L\'URL d\'authentification est : ' . $oauth_url . '");
                }
                showAuthDialog();
            </script>';
        exit;
    
    }
        
        // Redirection vers l'URL OAuth
        //wp_redirect($oauth_url);
        /*
        exit;
    } else {
        wp_die('Client ID or Redirect URI not set.');
    }*/
}

// === Partie 3 : Réception des données via l'API REST ===

add_action('rest_api_init', function () {
    register_rest_route('data-receiver/v1', '/submit', array(
        'methods' => 'POST',
        'callback' => 'handle_data_submission',
        'permission_callback' => '__return_true',
    ));
 });

function handle_data_submission(WP_REST_Request $request) {
    // Récupérer les données envoyées via POST
    $data = $request->get_params();

    // Si aucune donnée n'est reçue
    if (empty($data)) {
        return new WP_REST_Response('No data received', 400);
    }

    // Enregistrer les données dans une option ou une base de données personnalisée
    update_option('received_data', json_encode($data));

    // Retourner une réponse confirmant la réception des données
    return new WP_REST_Response('Data received successfully!', 200);
}

// === Partie 4 : Ajouter un menu pour tester l'OAuth ===

add_action('admin_menu', function() {
    add_submenu_page(
        'logmein-auth',             // Parent slug
        'Test OAuth',               // Page title
        'Test OAuth',               // Menu title
        'manage_options',           // Capability
        'logmein-auth-test',        // Slug
        'logmein_auth_redirect'     // Callback function
    );
});