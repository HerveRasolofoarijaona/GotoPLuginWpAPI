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
    </form>

        <!-- Ajouter un bouton pour lier à OAuth -->

        <form id="logmein-auth-form">
        <h3>OAuth Authentication</h3>
        <p>
            Cliquez sur le bouton ci-dessous pour vous rediriger vers l'authentification OAuth de LogMeIn.
        </p>
        <button id="logmein-auth-button" type="button" class="button button-primary">Se connecter via LogMeIn OAuth</button>
        <div id="logmein-auth-result"></div>
    </form>

    <?php

    $ajax_nonce = wp_create_nonce('logmein_auth_nonce');

    ?>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#logmein-auth-button').on('click', function() {
                var data = {
                    action: 'logmein_auth_ajax',
                    security : logemeinNonce
                };

                $.post(ajaxurl, data, function(response) {
                    $('#logmein-auth-result').html(response);
                });
            });
        });
    </script>
    <?php
}

// === Partie 2 : Redirection vers l'URL OAuth de LogMeIn ===

add_action('wp_ajax_logmein_auth_ajax', 'logmein_auth_ajax_handler');

function logmein_auth_ajax_handler() {

    check_ajax_referer('logmein_auth_nonce', 'security'); 
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

        // Envoyer la requête OAuth
        $response = wp_remote_get($oauth_url);

        if (is_wp_error($response)) {
            wp_die('Erreur lors de la requête OAuth : ' . $response->get_error_message());
        }

        // Message indiquant que la requête OAuth a été envoyée
        echo '<p>Requête OAuth envoyée avec succès. En attente de données...</p>';

        // Récupérer les dernières données soumises via l'API REST
        $received_data = get_option('received_data');

        if ($received_data) {
            // Convertir les données JSON en tableau PHP
            $data = json_decode($received_data, true);

            // Afficher les données reçues
            echo '<h3>Dernières données reçues :</h3>';
            echo '<pre>' . print_r($data, true) . '</pre>';
        } else {
            echo '<p>Aucune donnée reçue pour le moment.</p>';
        }
    } else {
        wp_die('Client ID ou Redirect URI non défini.');
    }

    wp_die(); // Terminer le script
}



// === Partie 3 : Réception des données via l'API REST ===

add_action('rest_api_init', function () {
    register_rest_route('data-receiver/v1', '/submit', array(
        'methods' => array('POST', 'GET'),
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