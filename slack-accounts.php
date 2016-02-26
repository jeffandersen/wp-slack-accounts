<?php
/*
Plugin Name: Slack Accounts
Plugin URI: http://launch.chat
Description: Create contributor accounts through Slack OAuth
Version: 1.0.0
Author: Jeff Andersen
Author URI: http://twitter.com/jeffandersen
Update Server: 
Min WP Version: 4.4.0
Max WP Version: 
*/

include('vendor/requests/library/Requests.php');
Requests::register_autoloader();

$slack_accounts_error = null;
$settings = array(
  'slack_client_id' => "Slack Client ID",
  'slack_client_secret' => "Slack Client Secret",
  'slack_team_id' => "Slack Team ID"
);

function slack($path, $token = null, $args = array()) {
  if (!is_null($token)):
    $args['token'] = $token;
  endif;

  $endpoint = 'https://slack.com/api';
  $headers = array();
  $result = Requests::post($endpoint . $path, $headers, $args);
  return json_decode($result->body);
}

function saveAndRead() {
  global $settings;
  $values = array();
  foreach ($settings as $key => $value):
    if ($_POST['settings_save']):
      update_option($key, $_POST[$key]);
      $values[$key] = $_POST[$key];
    else:
      $values[$key] = get_option($key);
    endif;
  endforeach;
  return $values;
}

function lookupProfile($token) {
  $body = slack('/auth.test', $token);
  if (!$body->ok) {
    echo "Auth test failed";
    return null;
  }

  $body = slack('/users.info', $token, array("user" => $body->user_id));
  if (!$body->ok) {
    echo "User lookup failed";
    return null;
  }

  return $body->user;
}

function checkForAccount($token) {
  global $slack_accounts_error;
  $slack = lookupProfile($token);
  if (!$slack) {
    $slack_accounts_error = "Cannot log you in, Slack user not found";
    return;
  }

  $email = $slack->profile->email;
  $user = get_user_by('email', $email);
  if ($user) {
    $slack_user_id = get_user_meta($user->id, 'slack_user_id')[0];
    if ($slack->id == $slack_user_id) {
      updateUserProfile($user->id, $slack);
      wp_set_auth_cookie($user->id, true);
      header("location: " . get_admin_url());
      exit;
    } else {
      $slack_accounts_error = "Cannot log you in, account exists for your email";
    }
  } else {
    if (!username_exists($slack->name) and email_exists($email) == false ) {
      $password = wp_generate_password(12, true);
      $user_id = wp_create_user($slack->name, $password, $email);
      updateUserProfile($user_id, $slack);
      $user = new WP_User($user_id);
      $user->set_role('contributor');
      wp_set_auth_cookie($user_id, true);
      header("location: " . get_admin_url());
      exit;
    }
  }
}

function updateUserProfile($wp_user_id, $slack) {
  wp_update_user(
    array(
      'ID'       => $wp_user_id,
      'slack_user_id'    => $slack->id,
      'slack_user_avatar' => $slack->profile->image_original,
      'slack_user' => json_encode($slack),
      'first_name' => $slack->profile->first_name,
      'last_name' => $slack->profile->last_name,
      'nickname' => $slack->profile->real_name,
      'display_name' => $slack->profile->real_name,
      'description' => $slack->profile->title
    )
  );
}

function exchangeCode($code) {
  global $settings;
  $values = saveAndRead();

  $body = array(
    'client_id' => $values['slack_client_id'],
    'client_secret' => $values['slack_client_secret'],
    'code' => $code,
    'redirect_uri' => wp_login_url()
  );

  return slack('/oauth.access', null, $body);
}

function slack_accounts_login_process() {
  if ($_GET['code'] && $_GET['state'] == 'slack-accounts' && !$_GET['error']):
    $info = exchangeCode($_GET['code']);
    checkForAccount($info->access_token);
  endif;
}

function slack_accounts_login() {
  global $slack_accounts_error;
  global $settings;
  $values = saveAndRead();
  include('pages/login.php');
}

function slack_accounts_settings() {
  global $settings;
  $values = saveAndRead();
  include('pages/settings.php');
}

function slack_accounts_admin_actions() {
  add_menu_page('Slack Login', 'Slack Login', 'manage_options', 'slack-login', 'slack_accounts_settings');
}

function slack_accounts_modify_contact_methods($profile_fields) {
  $profile_fields['slack_user_id'] = 'Slack User ID';
  $profile_fields['slack_user_avatar'] = 'Slack User Avatar';
  $profile_fields['slack_user'] = 'Slack User JSON';
  return $profile_fields;
}

function slack_accounts_hide_profile_fields() {
  echo '<style>.user-slack_user_id-wrap, .user-slack_user_avatar-wrap, .user-slack_user-wrap { display: none; }</style>';
}

add_action('init', 'slack_accounts_login_process');
add_action('login_form', 'slack_accounts_login');
add_action('admin_menu', 'slack_accounts_admin_actions');
add_action('admin_head-user-edit.php', 'slack_accounts_hide_profile_fields');
add_action('admin_head-profile.php', 'slack_accounts_hide_profile_fields');
add_filter('user_contactmethods', 'slack_accounts_modify_contact_methods');
