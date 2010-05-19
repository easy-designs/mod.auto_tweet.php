<?php

$L = array(

"auto_tweet_module_name" => "Auto Tweet",
"auto_tweet_module_description" => "Allows you to set up automated tweeting of entries, based on a schedule.",

# install
'module_requires_oAuth' => '
<p>This module requires <a href="http://pecl.php.net/oauth">the oAuth PECL extension</a>.</p>',
'installation_instructions' => '
<p>To install this module fully, you need to take the following steps. <strong>If you already have a consumer key and consumer secret from Twitter, skip to step 2.</strong></p>
<ol>
  <li><a href="http://twitter.com/oauth_clients/new">Register this application with Twitter</a>, setting "Application Type" set to "Client" and "Default Access Type" set to "Read &#38; Write". The rest of the details are up to you.</li>
  <li>Add the "Consumer Key" and "Consumer Secret" you received from Twitter to your EE configuration file:
    <pre># Twitter oAuth config
$conf[\'twitter_oauth_consumer_key\'] = \'myconsumerkey\';
$conf[\'twitter_oauth_consumer_secret\'] = \'myconsumersecret\';</pre>
  </li>
  <li>Create a new template whose contents are a single tag: 
    <pre>{exp:auto_tweet:cron}</pre>
  </li>
  <li>Set up a cron job to ping that URL every hour. For example:
    <pre>* */1 * * * wget -q -O /dev/null http://my-site.com/cron/auto_tweet</pre>
  </li>
</ol>',

# oAuth
'oAuth_instructions' => '
<p>In order to use this application, you must authorize it to post messages to Twitter. To do so, click the link below, accept the authorization, and then enter the 7-digit pin returned to you in the field below.</p>',
'authorize' => 'Authenticate with Twitter',
'pin' => 'Your 7-digit PIN',
'submit' => 'Complete Authentication',

# home
'tweets_made_as_start' => '(Note: Tweets will be made under the Twitter account ',
'tweets_made_as_end' => ')',
'content' => 'Content',
'when' => 'When',
'delete' => 'Delete?',
'add_cron' => 'Create a new auto-tweet',
'select' => '-- select --',
'sticky_only' => 'Sticky entries only',
'content_notes' => 'You can incorporate {title} and {link} into your tweet',
'day' => 'day(s)',
'week' => 'week(s)',
'before' => 'before',
'after' => 'after',
'posting_at' => 'the entry date, at',
'no_crons' => 'No auto-tweets are currently scheduled.',
'add' => 'Add it',

# add
'weblog_not_allowed' => 'You are not allowed to use that ',
'weblog_is_required' => 'You must choose a ',
'content_is_required' => 'You must set some content',
'content_is_too_long' => 'Tweets are limited to 140 characters and yours will be too long',
'duration_is_required' => 'You must choose a number of days or weeks to offset the post. 0 is acceptable.',
'duration_type_is_required' => 'Please choose a type of offset (days or weeks)',
'duration_side_is_required' => 'Please choose whether this tweet should be made before or after the entry date',
'time_is_required' => 'Please set a time of day to make this tweet.',
'duration_type_is_unacceptable' => 'The duration type you submitted is unacceptable',
'duration_side_is_unacceptable' => 'The duration side you submitted is unacceptable',
'time_is_unacceptable' => 'The time of day you submitted is unacceptable',
'duration_must_be_a_number' => 'The offset needs to be a number',

# delete
'delete_confirmation' => 'Are you sure you want to delete this auto-tweet?',
'deletion_error' => 'There was an error deleting that auto-tweet.',

/* END */
''=>''
);
?>