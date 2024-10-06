<?php
/*
Plugin Name: GOTO Plugins
Description: A plugin to generate a callback and receive input variable code
Version: 1.0
Author: Herve RASOLOFOARIJAONA
Author URI: https://www.facebook.com/GoldenKode
Text Domain: goto-plugins
Domain Path: /languages
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
        'GOTO Support API',
        'GOTO Support',
        'manage_options',
        'logmein-auth',
        'logmein_options_page'
    );
}

function logmein_settings_init() {
    register_setting('logmein_auth_group', 'logmein_client_id');
    register_setting('logmein_auth_group', 'logmein_client_secret');
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
        'logmein_client_secret',
        'Client Secret',
        'logmein_client_secret_render',
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

function logmein_client_secret_render() {
    $client_secret = get_option('logmein_client_secret');
    ?>
    <input type="text" name="logmein_client_secret" value="<?php echo esc_attr($client_secret); ?>" />
    <?php
}

function logmein_redirect_uri_render() {
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

    <div id="php-version-dialog" title="PHP Version">
        <p>Loading...</p>
    </div>

    <br/>

    <!-- <button id="show-php-version" type="button" class="button button-primary">Show PHP Version</button> -->


    <form id="logmein-auth-form">
        <h3>OAuth Authentication</h3>
        <p>Cliquez sur le bouton ci-dessous pour vous rediriger vers l'authentification OAuth de LogMeIn.</p>
        <button id="logmein-auth-button" type="button" class="button button-primary">Tester OAuth client</button>
        <div id="logmein-auth-result"></div>
    </form>



    <?php
    $ajax_nonce = wp_create_nonce('logmein_auth_nonce');
    ?>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#logmein-auth-button').on('click', function() {
                openAuthWindow();
                var data = {
                    action: 'logmein_auth_ajax',
                    security: '<?php echo $ajax_nonce; ?>'
                };

                $.post(ajaxurl, data, function(response) {
                    $('#logmein-auth-result').html(response);
                });
            });

            // Initialize the dialog
            $('#php-version-dialog').dialog({
                autoOpen: false,
                modal: true,
                width: 400
            });

            // Handle the button click
            $('#show-php-version').click(function() {
                $.post(ajaxurl, {
                    action: 'my_custom_cron_job' // Use the correct action name
                }, function(response) {
                    // Check if the response is successful
                    if (response.success) {
                        $('#php-version-dialog p').text('PHP Version: ' + response.data);
                        $('#php-version-dialog').dialog('open');
                    } else {
                        alert('An error occurred while fetching the PHP version.');
                    }
                });
        });

        
        });

        <?php

        $client_id = get_option('logmein_client_id');
        $redirect_uri = get_option('logmein_redirect_uri', site_url('/wp-json/data-receiver/v1/submit'));

        $oauth_url = 'https://authentication.logmeininc.com/oauth/authorize';
        $oauth_url .= '?client_id=' . urlencode($client_id);
        $oauth_url .= '&response_type=code';
        $oauth_url .= '&redirect_uri=' . esc_attr($redirect_uri);

        ?>

        const authUrl = '<?php echo $oauth_url; ?>';


        // Ouvrir une fenêtre pour que l'utilisateur s'authentifie
        function openAuthWindow() {
            const authWindow = window.open(authUrl, '_blank', 'width=600,height=600');

            // Vérifier régulièrement si la fenêtre a été fermée
            const checkWindow = setInterval(() => {
                if (authWindow.closed) {
                    clearInterval(checkWindow);
                    console.log('Fenêtre fermée. Vérifiez si un code d’autorisation a été reçu.');
                    //checkForAuthCode();
                }
            }, 1000);

                  // Fermer la fenêtre automatiquement après 5 secondes
            setTimeout(() => {
                if (!authWindow.closed) {
                    authWindow.close();
                    clearInterval(checkWindow); // Arrêter la vérification
                    console.log('Fenêtre fermée automatiquement après 5 secondes.');
                }
            }, 2000); // Fermeture après 5000 millisecondes (5 secondes)

            }

    </script>


    <?php
}

// === Partie 2 : Redirection vers l'URL OAuth de LogMeIn ===

add_action('wp_ajax_logmein_auth_ajax', 'logmein_auth_ajax_handler');

function logmein_auth_ajax_handler() {
    check_ajax_referer('logmein_auth_nonce', 'security');

    $data_code = null;

    $client_id = get_option('logmein_client_id');
    $client_secret = get_option('logmein_client_secret');
    $redirect_uri = get_option('logmein_redirect_uri', site_url('/wp-json/data-receiver/v1/submit'));

    

    if ($client_id && $redirect_uri) {

        echo '<p>Requête OAuth envoyée avec succès. <br/> En attente de données...</p>';

        $received_data = get_option('received_data');

        if ($received_data) {
            $data_code = json_decode($received_data, true);

            // echo '<h3>Dernières données reçues :</h3>';
            // echo '<pre>' . print_r($data_code, true) . '</pre>';

            // echo $data_code['code'];

            $value = $data_code['code'];

            if ($data_code !== null) {
                echo '<br/> API are connected <b>successfully!</b>';

                $credentials = $client_id . ':' . $client_secret;
                $encoded_credentials = base64_encode($credentials);

                // echo '<br/>';
                // echo $value;
                // echo '<br/>';
                // echo $encoded_credentials;
                echo '<br/>';

                $url = 'https://authentication.logmeininc.com/oauth/token';
                $headers = array(
                    'Authorization' => 'Basic ' . $encoded_credentials,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                );
                $data = array(
                    'redirect_uri' => $redirect_uri,
                    'grant_type' => 'authorization_code',
                    'code' => $data_code['code']
                );

                $response = wp_remote_post($url, array(
                    'headers' => $headers,
                    'body' => $data
                ));

                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    echo "Error: <br/>";
                    echo '<pre>' . print_r(json_decode($error_message, true), true) . '</pre>';
                } else {
                    $body = wp_remote_retrieve_body($response);
                    echo "Response: <br/>";
                    echo '<pre>' . print_r(json_decode($body, true), true) . '</pre>';
                }
            }
        } else {
            echo '<p>Aucune donnée reçue pour le moment. vous devez authorizer votre site sur goto via ce lien </p><br>';
            echo '<a href="https://authentication.logmeininc.com/oauth/authorize?client_id=' . $client_id . '&redirect_uri=' . $redirect_uri . '">Authorize</a>';
        }
    } else {
        wp_die('Client ID ou Redirect URI non défini.');
    }

    wp_die();
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
    $data = $request->get_params();

    if (empty($data)) {
        return new WP_REST_Response('No data received', 400);
    }

    update_option('received_data', json_encode($data));

    return new WP_REST_Response('Data received successfully!', 200);
}



// Add a button to the plugin page that triggers the AJAX request and displays the output in a dialog box
function enqueue_jquery_ui() {
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
}

add_action('admin_enqueue_scripts', 'enqueue_jquery_ui');
