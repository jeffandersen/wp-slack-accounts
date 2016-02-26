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

require __DIR__ . '/vendor/autoload.php';

class SlackAccounts {
  public $settings = array(
    'slack_client_id' => "Slack Client ID",
    'slack_client_secret' => "Slack Client Secret",
    'slack_team_id' => "Slack Team ID"
  );

  public $error = null;

  private $endpoint = 'https://slack.com/api';

  public function __construct() {
    $this->slack = $this->config();
    $this->slack->token = null;
  }

  private function _slack($path, $args, $token = true) {
    if ($token) {
      $args = array_merge($args, array("token" => $this->slack->token));
    }

    $headers = array();
    $result = Requests::post($endpoint . $path, $headers, $args);

    return json_decode($result->body);
  }

  public function config($inbound = array()) {
    $values = array();

    foreach ($this->settings as $key => $value):
      if ($inbound['settings_save']):
        update_option($key, $inbound[$key]);
        $values[$key] = $inbound[$key];
      else:
        $values[$key] = get_option($key);
      endif;
    endforeach;

    return $values;
  }

  public function oauthExchange($code) {
    $config = $this->config();
    $body = array(
      'client_id' => $config['slack_client_id'],
      'client_secret' => $config['slack_client_secret'],
      'code' => $code,
      'redirect_uri' => wp_login_url()
    );

    $body = $this->_slack('/oauth.access', $body, false);
    $this->slack->token = $body->access_token;
  }

  public function loginOrSignup() {
    $profile = $this->retrieveSlackProfile();
    if (!$profile) {
      $this->error = "Cannot log you in, Slack user not found";
      return;
    }

    $email = $profile->profile->email;
    $user = $this->wpUserExists($email);
    if (!$user) {
      if (username_exists($slack->name) || email_exists($email) !== false) {
        $this->error = "Cannot perform Slack login with this account";
        return;
      } else {
        return $this->wpCreateUser($user, $profile);
      }
    }

    $slackUserId = $this->wpUserSlackId($user->id);
    if (!$slackUserId || $slackUserId !== $profile->id) {
      $this->error = "Cannot perform Slack login with this account";
      return;
    }

    $this->wpUpdateUser($user->id, $slack);
    $this->wpPerformLogin($user);
  }

  public function retrieveSlackProfile() {
    $body = $this->_slack('/auth.test');
    if (!$body->ok) {
      $this->error = "authorization test failed";
      return null;
    }

    $body = this->_slack('/users.info', array("user" => $body->user_id));
    if (!$body->ok) {
      $this->error = "could not retrieve slack profile";
      return null;
    }

    return $body->user;
  }

  private function wpCreateUser($slack) {
    if (!username_exists($slack->name) and email_exists($email) == false ) {
      $password = wp_generate_password(12, true);
      $user_id = wp_create_user($slack->name, $password, $email);
      updateUserProfile($user_id, $slack);
      $user = new WP_User($user_id);
      $user->set_role('contributor');
      wp_set_auth_cookie($user_id, true);
      header("location: " . get_admin_url());
      exit;
    } else {
      $this->error = "Email or username in use, cannot perform Slack login";
    }
  }

  private function wpPerformLogin($user) {
    wp_set_auth_cookie($user->id, true);
    header("location: " . get_admin_url());
    exit;
  }

  private function wpUserExists($email) {
    return get_user_by('email', $email);
  }

  private function wpUserSlackId($user) {
    return get_user_meta($user->id, 'slack_user_id')[0];
  }

  private function wpUpdateUser($user, $slack) {
    wp_update_user(
      array(
        'ID' => $user->id,
        'slack_user_id' => $slack->id,
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
}

function slack_accounts_login_process() {
  global $slackAccounts;
  if ($_GET['code'] && $_GET['state'] == 'slack-accounts' && !$_GET['error']):
    $slackAccounts.oauthExchange($_GET['code']);
    $slackAccounts.loginOrSignup();
  endif;
}

function slack_accounts_login() {
  global $slackAccounts;
  $values = $slackAccounts->config();
  $slack_accounts_error = $slackAccounts->error;
  include('pages/login.php');
}

function slack_accounts_settings() {
  global $slackAccounts;
  $values = $slackAccounts->config($_POST);
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

$slackAccounts = new SlackAccounts();

add_action('init', 'slack_accounts_login_process');
add_action('login_form', 'slack_accounts_login');
add_action('admin_menu', 'slack_accounts_admin_actions');
add_action('admin_head-user-edit.php', 'slack_accounts_hide_profile_fields');
add_action('admin_head-profile.php', 'slack_accounts_hide_profile_fields');
add_filter('user_contactmethods', 'slack_accounts_modify_contact_methods');
