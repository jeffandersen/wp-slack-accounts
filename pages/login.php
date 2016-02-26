<?php if ($slack_accounts_error): ?>
<p><?= $slack_accounts_error; ?></p>
<?php endif; ?>
<p><a href="https://slack.com/oauth/authorize?team=<?= $values['slack_team_id']; ?>&client_id=<?= $values['slack_client_id']; ?>&scope=identify,users:read&state=slack-accounts&redirect_uri=<?php echo wp_login_url(); ?>" style="display: inline-block;margin-bottom: 15px;">Log in with Slack</a></p>
