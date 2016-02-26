<?php if ($_POST['settings_save']): ?>
<div class="updated"><p><strong>Options Saved</strong></p></div>
<?php endif; ?>
<div class="wrap">
  <h2>Slack Accounts</h2>
  <p>Allow users of your Slack group to log in as contributors.</p>

  <h3>OAuth Settings</h3>
  <form name="slack_login_form" method="post" action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>">
    <?php foreach ($slackAccounts->settings as $key => $title): ?>
    <p><label for="<?= $key; ?>"><strong><?= $title; ?></strong></label><br>
    <input type="text" name="<?= $key; ?>" id="<?= $key; ?>" value="<?php echo $values[$key]; ?>"></p>
    <?php endforeach; ?>
    <p class="submit"><input type="submit" name="settings_save" value="Update Settings"></p>
  </form>
</div>
