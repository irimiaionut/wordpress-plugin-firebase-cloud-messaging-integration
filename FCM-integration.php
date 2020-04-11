<?php

/*
Plugin Name: FCM Integration
Plugin URI: https://www.irimia.tech
Description: Firebase push notification service with some options
Version: 0.1
Author: Irimia Doru Ionut
Author URI: https://www.irimia.tech
*/

require_once plugin_dir_path( __DIR__ ) . 'FCM-integration/FCMNotificationManager.php';


class FCMIntegration {

    private $options = null;
    private $notificationManager = null;

    private $GOOGLE_APPLICATION_CREDENTIALS_PATH = null;

    // Constructor
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        add_action('admin_init', array($this, 'fcm_register_settings') );
        add_action('admin_menu',  array($this, 'add_admin_page'));

        $this->options = get_option('fcm_integration_plugin_options');

        //validate mandatory settings
        if(
            !isset($this->options['project_id']) ||
            !isset($this->options['accepted_categories'])
        ){
            error_log('[FCMIntegration] [ERROR] Mandatory fields not validated. Please provide them in FCMIntegration settings page.');
            return;
        }

        $this->GOOGLE_APPLICATION_CREDENTIALS_PATH = plugin_dir_path( __DIR__ ) . '/FCM-integration/client_credentials.json';
        if(!file_exists($this->GOOGLE_APPLICATION_CREDENTIALS_PATH)) {
            error_log('[FCMIntegration] [ERROR] Mandatory client credentials file cannot be located. Please provide GOOGLE_APPLICATION_CREDENTIALS file in root plugin.');
            return;
        }

        //add functionality to posts table
        add_filter('manage_posts_columns', array($this, 'post_push_notification_column'));
        add_action('manage_posts_custom_column', array($this, 'post_push_notification_column_action'), 10, 2);

        //add functionality for backend manual post
        add_action( 'wp_ajax_nopriv_request_push_notification', array( $this, 'request_push_notification' ) );
        add_action( 'wp_ajax_request_push_notification', array( $this, 'request_push_notification' ) );

        //add functionality for automatic push notification
        add_action( 'publish_post' , array($this, 'automatic_request_push_notification') , 10 , 2 );

        $this->init();
    }

    public function init(){
        $this->notificationManager = new FCMNotificationManager($this->GOOGLE_APPLICATION_CREDENTIALS_PATH, $this->options['project_id']);
    }

    public function admin_enqueue_scripts(){
        wp_enqueue_style('fcm_integration_backend_css', plugins_url('assets/backend/css/styles.css', __FILE__), null, '');
        wp_enqueue_script( 'fcm_integration_backend_js', plugin_dir_url( __FILE__ ) . 'assets/backend/js/scripts.js', array( 'jquery' ), null, true );

        // set variables for script
        wp_localize_script( 'fcm_integration_backend_js', 'settings', array(
            'ajaxurl'    => admin_url( 'admin-ajax.php' ),
        ) );
    }

    public function  add_admin_page(){
        add_menu_page( 'FCM Integration', 'FCM Integration', 'manage_options', 'fcm-plugin-settings', array($this, 'load_settings_page'));
    }

    public function load_settings_page(){
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'fcm_integration_plugin_options' );
            do_settings_sections( 'fcm_integration_plugin' ); ?>
            <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
        </form>
        <?php
    }

    public function fcm_register_settings(){
        register_setting( 'fcm_integration_plugin_options', 'fcm_integration_plugin_options', array($this, 'fcm_integration_plugin_options_validate') );

        add_settings_section( 'api_settings', 'Firebase Cloud Messaging Settings', array($this, 'fcm_integration_plugin_section_text'), 'fcm_integration_plugin' );
        add_settings_field( 'fcm_integration_plugin_setting_project_id', '* Project ID', array($this, 'fcm_integration_plugin_setting_project_id'), 'fcm_integration_plugin', 'api_settings' );
        add_settings_field( 'fcm_integration_plugin_setting_accepted_categories', '* Accepted Categories (ex:12,13,14)', array($this, 'fcm_integration_plugin_setting_accepted_categories'), 'fcm_integration_plugin', 'api_settings' );
        add_settings_field( 'fcm_integration_plugin_setting_topic', 'Topic (default: all)', array($this, 'fcm_integration_plugin_setting_topic'), 'fcm_integration_plugin', 'api_settings' );
        add_settings_field( 'fcm_integration_plugin_setting_auto_push',  'Auto Push Notification', array($this, 'fcm_integration_plugin_setting_auto_push'), 'fcm_integration_plugin', 'api_settings' );

        add_settings_section( 'api_settings_notification', 'Notification Settings', array($this, 'fcm_integration_plugin_section_notification'), 'fcm_integration_plugin' );
        add_settings_field( 'fcm_integration_plugin_setting_notification_title', 'Notification Title', array($this, 'fcm_integration_plugin_setting_notification_title'), 'fcm_integration_plugin', 'api_settings_notification' );
        add_settings_field( 'fcm_integration_plugin_setting_notification_description', 'Notification Description', array($this, 'fcm_integration_plugin_setting_notification_description'), 'fcm_integration_plugin', 'api_settings_notification' );
    }

    public function fcm_integration_plugin_section_text() {
        echo '<p>Here you can set all the options for using the Firebase Cloud Messaging API.<br> Fields marked with * are mandatory.</p>';
    }

    public function fcm_integration_plugin_setting_project_id() {
        $options = get_option( 'fcm_integration_plugin_options' );
        echo "<input  class='fcm_settings_input' id='fcm_integration_plugin_setting_project_id' name='fcm_integration_plugin_options[project_id]' type='text' value='". esc_attr( $options['project_id'] ) ."' />";
    }

    public function fcm_integration_plugin_setting_topic() {
        $options = get_option( 'fcm_integration_plugin_options' );
        echo "<input  class='fcm_settings_input' id='fcm_integration_plugin_setting_topic' name='fcm_integration_plugin_options[topic]' type='text' value='". esc_attr( $options['topic'] ) ."' />";
    }

    public function fcm_integration_plugin_setting_accepted_categories() {
        $options = get_option( 'fcm_integration_plugin_options' );
        echo "<input  class='fcm_settings_input' id='fcm_integration_plugin_setting_accepted_categories' name='fcm_integration_plugin_options[accepted_categories]' type='text' value='". ($options['accepted_categories'] ? $options['accepted_categories'] : '') ."' />";
    }

    public function fcm_integration_plugin_section_notification() {
        echo '<p>Here you can customize the notification delivered to the user. Special values: %post_title% </p>';
    }

    public function fcm_integration_plugin_setting_notification_title() {
        $options = get_option( 'fcm_integration_plugin_options' );
        echo "<input  class='fcm_settings_input' id='fcm_integration_plugin_setting_notification_title' name='fcm_integration_plugin_options[notification_title]' type='text' value='". esc_attr( $options['notification_title'] ) ."' />";
    }

    public function fcm_integration_plugin_setting_notification_description() {
        $options = get_option( 'fcm_integration_plugin_options' );
        echo "<input  class='fcm_settings_input' id='fcm_integration_plugin_setting_notification_description' name='fcm_integration_plugin_options[notification_description]' type='text' value='". esc_attr( $options['notification_description'] ) ."' />";
    }

    public function fcm_integration_plugin_setting_auto_push() {
        $options = get_option( 'fcm_integration_plugin_options' );
        $checked = ( isset($options['auto_push']) && $options['auto_push'] == 1) ? 1 : 0;
        echo "<input  class='fcm_settings_input' id='fcm_integration_plugin_setting_auto_push' name='fcm_integration_plugin_options[auto_push]' type='checkbox' value='1' " . checked( 1, $checked, false ) . "/><label for=\"checkbox_example\">This will allow users to receive notification when a post is updated or published </label>";
    }

    // add a push notification button column to the edit posts screen
    function post_push_notification_column($cols) {
        $cols['push_notification'] = 'Push Notification';
        return $cols;
    }

    function post_push_notification_column_action($column_name, $post_id){
        $validated = $this->validate_post($post_id);
        if ('push_notification' === $column_name && $validated) {
            echo "
             <div>
                <button class=\"push_notification_button\" data-post_id=\"$post_id\">
                        Push Notification
                </button>
             </div>
             ";
        }
    }

    function automatic_request_push_notification($ID , $post){
        if($this->options['auto_push'] != 1){
            return;
        }

        //will not work on post update
        //only for first publish
        if ( $post->post_date == $post->post_modified ){
            $validated = $this->validate_post($post->ID);
            if($validated){

                $topic = isset($this->options['topic']) ? $this->options['topic'] : 'all';

                $title = isset($this->options['notification_title']) ? $this->options['notification_title'] : 'New article';
                $title = $this->replaceWildcard($title, $post);

                $body = isset($this->options['notification_description']) ? $this->options['notification_description'] : 'Read all about it! ';
                $body = $this->replaceWildcard($body, $post);

                $payload =  array(
                    "message" => array(
                        "topic" => $topic,
                        "notification" => array(
                            "title" => $title,
                            "body"  => $body,
                        ),
                        "data" => array(
                            "postID" => strval($post->ID)
                        )
                    )
                );
                if($this->notificationManager->sendNotification($payload)){
                   error_log('[FCMIntegration] [AutoPush] Successfully pushed notification for post '.$post->ID);
                }else{
                    error_log('[FCMIntegration] [ERROR] Could NOT push notification for post '.$post->ID);
                }
            }
        }
    }

    function request_push_notification(){
        $data = $_POST;
        $validated = $this->validate_post($data['post_id']);
        if (!$validated) {
            wp_send_json_error('Not validated');
            return;
        }

        $topic = isset($this->options['topic']) ? $this->options['topic'] : 'all';

        $title = isset($this->options['notification_title']) ? $this->options['notification_title'] : 'New article';
        $title = $this->replaceWildcard($title, get_post($data['post_id']));

        $body = isset($this->options['notification_description']) ? $this->options['notification_description'] : 'Read all about it! ';
        $body = $this->replaceWildcard($body, get_post($data['post_id']));

        $payload =  array(
            "message" => array(
                "topic" => $topic,
                "notification" => array(
                    "title" => $title,
                    "body"  => $body,
                ),
                "data" => array(
                    "postID" => $data['post_id']
                )
            )
        );

        if($this->notificationManager->sendNotification($payload)){
            wp_send_json_success( true );
            return;
        }else{
            wp_send_json_error('Something went wrong.');
            return;
        }
    }

    function replaceWildcard($value, $post){
        if($value === '%post_title%') return $post->post_title;
        return $value;
    }

    function validate_post($post_id){
        $categories = wp_get_post_categories($post_id);
        if(array_intersect($categories, explode(',', $this->options['accepted_categories']))){
            return true;
        }
        return false;
    }
}

// Add a Global variable if you need to use outside of instantiated scope
Global $FCMIntegration;
// Instantiate
$FCMIntegration = new FCMIntegration();
?>