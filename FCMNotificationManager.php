<?php

require_once plugin_dir_path( __DIR__ ) . '/FCM-integration/vendor/autoload.php';


class FCMNotificationManager {
    private $projectID = null;
    private $applicationCredentialsPath = null;

    // Constructor
    public function __construct( $applicationCredentialsPath, $projectID ) {
        $this->applicationCredentialsPath = $applicationCredentialsPath;
        $this->projectID = $projectID;
    }

    function getAccessToken() {
        $client = new Google_Client();
        $client->setAuthConfig($this->applicationCredentialsPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->useApplicationDefaultCredentials();
        return $client->fetchAccessTokenWithAssertion();
    }

    function sendNotification($payload) {

        $token = $this->getAccessToken();
        $headers = array
        (
            'Authorization: ' . $token['token_type'] . ' ' . $token['access_token'],
            'Content-Type: application/json'
        );
        $ch = curl_init();
        curl_setopt( $ch,CURLOPT_URL, 'https://fcm.googleapis.com/v1/projects/'.$this->projectID.'/messages:send' );
        curl_setopt( $ch,CURLOPT_POST, true );
        curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $payload ) );
        $result = curl_exec($ch );
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close( $ch );

        if($httpcode === 200){
            return true;
        }else{
            error_log('[FCMIntegration] [ERROR] sendNotification response is '. $httpcode);
            error_log('[FCMIntegration] [ERROR] sendNotification result is '. print_r($result,1));
            return false;
        }
    }
}