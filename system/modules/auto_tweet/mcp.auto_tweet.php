<?php

/**
 * Allows you to set up automated tweeting of entries,
 * based on a schedule
 *
 * @package		ExpressionEngine
 * @author		Aaron Gustafson, Easy-Designs LLC
 * @copyright	Copyright (c) 2010 Easy-Designs LLC
 * @since		  Version 1.0
 * 
 * ToDo
 * ----
 * Nothing
 * 
 * Change Log
 * ----------
 * 1.0	| 2010-04-06 | Initial Version
 *
 */

if ( ! defined('EXT'))
{
  exit('Invalid file request');
}


class Auto_tweet_CP {

  var $version    = '1.0';
  var $key        = FALSE;
  var $secret     = FALSE;
  var $token_url  = 'http://twitter.com/oauth/request_token';
  var $auth_url   = 'http://twitter.com/oauth/authorize';
  var $access_url = 'http://twitter.com/oauth/access_token';
  var $verify_url = 'https://twitter.com/account/verify_credentials.json';
  var $post_url   = 'http://twitter.com/statuses/update.json';
  var $module     = 'auto_tweet';
  
  var $site_id        = 1;
  var $settings_table = "auto_tweet_settings";
  var $posts_table    = 'auto_tweet_posts';
  var $settings       = FALSE;
  var $crons          = array();
  var $duration_types = array( 'day', 'week' );
  var $duration_sides = array( '-' => 'before', '+' => 'after' );
  var $acceptable_times = array();
  
  function Auto_tweet_CP( $cp=TRUE )
  {
    global $PREFS, $IN, $DB;
    
    $this->settings_table = "{$PREFS->ini('db_prefix')}_{$this->settings_table}";
    $this->posts_table    = "{$PREFS->ini('db_prefix')}_{$this->posts_table}";
    $this->site_id        = $PREFS->ini('site_id');
    
    $this->key    = $PREFS->ini('twitter_oauth_consumer_key');
    $this->secret = $PREFS->ini('twitter_oauth_consumer_secret');
    
    # set acceptable times
    for ( $j=0; $j<24; $j++ )
    {
      $this->acceptable_times[] = ( $j < 10 ? '0'.$j : $j ) . '00';
    }
    
    # check to see if we're installed yet
    if ( $DB->query("SHOW TABLES LIKE '{$this->settings_table}'")->num_rows > 0 )
    {
      # grab the settings
      $query = $DB->query(
        "SELECT `settings`, `crons`
         FROM   `{$this->settings_table}`
         WHERE  `site_id` = $this->site_id"
      );
      if ( $query->num_rows > 0 )
      {
        if ( ! empty( $query->row['settings'] ) )
        {
          $this->settings = unserialize( $query->row['settings'] );
        }
        if ( ! empty( $query->row['crons'] ) )
        {
          $this->crons = unserialize( $query->row['crons'] );
        }
      }
    
      # page?
      switch ( TRUE )
      {
        case empty( $this->key ) || empty( $this->secret ) || ! class_exists( 'OAuth' ):
          $page = 'install';
          break;
        case empty( $this->settings ) || ! isset( $this->settings['username'] ):
          $page = 'authorize';
          break;
        case $page = $IN->GBL( 'P', 'GET' ):
          break;
        default:
          $page = 'home';
          break;
      }
    
      if ( $cp )
      {
        $this->css();
        $this->pageTop();
        $this->$page();
      }
    }
    
  }
  
  function home( $error=FALSE )
  {
    global $DSP, $LANG, $PREFS, $DB, $SESS, $IN;

    $r  = $DSP->heading($LANG->line('auto_tweet_module_name'));
    
    if ( $error )
    {
      $r .= $DSP->qdiv('highlight', $error);
    }
    
    # the form
    $r .= $DSP->div('box');
    $r .= $DSP->form_open( array( 'action' => $this->getUrl('add',FALSE) ) );
    $r .= $DSP->table('new', '0', '', '100%');
    $r .= '<caption>' . $LANG->line( 'add_cron' ) . ' <span>' .
          $LANG->line('tweets_made_as_start').$this->settings['username'].$LANG->line('tweets_made_as_end') .
          '</span></caption>';
    $r .= $DSP->tr();
    # sections
    $section   = $IN->GBL('section','POST');
    $sections  = $DSP->input_select_header( 'section' );
    $sections .= $DSP->input_select_option( '', $LANG->line('select') );
    foreach ($SESS->userdata['assigned_weblogs'] as $weblog_id => $weblog_title)
    { 
      $sections .= $DSP->input_select_option( $weblog_id, $weblog_title, ( $section == $weblog_id ? 'y' : '' ) );
    }
    $sections .= $DSP->input_select_footer();
    $sections .= '<label>';
    $sections .= $DSP->input_checkbox( 'sticky_only', 'y', $IN->GBL('sticky_only','POST') );
    $sections .= $LANG->line('sticky_only') . '</label>';
    $r .= $DSP->table_qcell('sections', $sections);
    # content
    $content  = $DSP->input_text( 'tweet_format', $IN->GBL('tweet_format','POST'), 80, 140, 'input', 'auto' );
    $content .= $DSP->qdiv( 'notes', $LANG->line('content_notes') );
    $r .= $DSP->table_qcell('content', $content);
    # when
    $d      = $IN->GBL('duration','POST');
    $d_type = $IN->GBL('duration_type','POST');
    $d_side = $IN->GBL('duration_side','POST');
    $t      = $IN->GBL('time','POST');
    $when   = $DSP->input_text( 'duration', ( ! empty($d) ? $d : 1 ), 2, 2, 'input', 'auto' );
    $when  .= $DSP->input_select_header( 'duration_type' );
    foreach ( $this->duration_types as $type )
    {
      $when .= $DSP->input_select_option( $type, $LANG->line($type), ( $d_type == $type ? 'y' : '' ) );
    } 
    $when .= $DSP->input_select_footer();
    $when .= $DSP->input_select_header( 'duration_side' );
    foreach ( $this->duration_sides as $k => $v )
    {
      $when .= $DSP->input_select_option( $k, $LANG->line($v), ( $k == $d_side ? 'y' : '' ) );
    }
    $when .= $DSP->input_select_footer();
    $when .= $LANG->line('posting_at');
    $when .= $DSP->input_select_header( 'time' );
    foreach ( $this->acceptable_times as $time )
    {
      $value = substr( $time, 0, 2 ) . ':' . substr( $time, 2, 2 );
      $when .= $DSP->input_select_option( $time, $value, ( $t == $time ? 'y' : '' ) );
    } 
    $when .= $DSP->input_select_footer();
    $r .= $DSP->table_qcell('when', $when);
    $r .= $DSP->table_qcell('', $DSP->input_submit( $LANG->line('add') ));
    $r .= $DSP->tr_c();
    $r .= $DSP->table_c();
    $r .= $DSP->form_c();
    $r .= $DSP->div_c();
    
    # the table
    $r .= $DSP->table('tableBorderNoTop crons', '0', '', '100%');
    if ( count( $this->crons) )
    {
      $r .= '<thead>';
      $r .= $DSP->tr();
      $r .= $DSP->table_qcell('tableHeadingAlt section', ucfirst($PREFS->ini('weblog_nomenclature')));
      $r .= $DSP->table_qcell('tableHeadingAlt content', $LANG->line('content'));
      $r .= $DSP->table_qcell('tableHeadingAlt when', $LANG->line('when'));
      $r .= $DSP->table_qcell('tableHeadingAlt delete', $LANG->line('delete'));
      $r .= $DSP->tr_c();
      $r .= '</thead><tbody>';
      foreach ( $this->crons as $id => $cron )
      {
        $tr  = '<tr id="'. $id .'">';
        $weblog = $SESS->userdata['assigned_weblogs'][$cron['weblog_id']];
        if ( $cron['sticky'] == 'y' ) $weblog .= ' (sticky only)';
        $tr .= $DSP->table_qcell('tableCellTwo', $weblog);
        $tr .= $DSP->table_qcell('tableCellTwo', $cron['content']);
        $tr .= $DSP->table_qcell('tableCellTwo', $this->whenInEnglish($cron['when']));
        $tr .= $DSP->table_qcell('tableCellTwo delete', '');
        $tr .= $DSP->tr_c();
        $r .= $tr;
      }
      $r .= '</tbody>';
    }
    else
    {
      $r .= $DSP->tr();
      $r .= $DSP->table_qcell('tableHeadingAlt', '&#160;' );
      $r .= $DSP->tr_c();
      $r .= $DSP->tr();
      $r .= '<td class="empty">' . $LANG->line( 'no_crons' ) . '</td>';
      $r .= $DSP->tr_c();
    }
    $r .= $DSP->table_c();
    
    $r .= $this->js();
    
    $DSP->body = $r;
  }
  
  function install()
  {
    global $DSP, $LANG;

    $r  = $DSP->heading($LANG->line('auto_tweet_module_name'));
    $r .= $DSP->div('box');
    if ( ! class_exists( 'OAuth' ) )
    {
      $r .= $LANG->line('module_requires_oAuth');
    }
    $r .= $LANG->line('installation_instructions');
    $r .= $DSP->div_c();
    $DSP->body = $r;
  }
  
  function authorize()
  {
    global $IN, $LANG, $DSP;
    
    # oAuth setup
    $oAuth = new OAuth( $this->key, $this->secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI );
    $oAuth->enableDebug();
    
    $r = $DSP->heading($LANG->line('auto_tweet_module_name'));
    
    # reset?
    $pin = $IN->GBL( 'pin', 'POST' );
    if ( $this->settings['state'] == 1 &&
         empty( $pin ) )
    {
      $this->settings['state'] = 0;
    }
    
    if ( $this->settings['state'] == 0 )
    {
      # get the token
      $response = $oAuth->getRequestToken( $this->token_url );
      $this->settings = array(
        'state'  => 1,
        'token'  => $response['oauth_token'],
        'secret' => $response['oauth_token_secret']
      );
      # build the page
      $r .= $DSP->div('box');
      $r .= $LANG->line('oAuth_instructions');
      $r .= '<p><a target="_blank" class="button" href="' . $this->auth_url . '?oauth_token=' . $response['oauth_token'] . '">';
      $r .= $LANG->line('authorize') . '</a></p>';
      $r .= $DSP->form_open( array( 'action' => $this->getUrl('authorize') ) );
      $r .= $DSP->div('form');
      $r .= '<label for="pin">' . $LANG->line( 'pin' ) . '</label> ' . $DSP->input_text( 'pin' );
      $r .= $DSP->input_submit( $LANG->line( 'submit' ) );
      $r .= $DSP->div_c();
      $r .= $DSP->form_close();
      $r .= $DSP->div_c();
    }
    elseif ( $this->settings['state'] == 1 )
    {
      $oAuth->setToken( $this->settings['token'], $this->settings['secret'] );
      $response = $oAuth->getAccessToken( $this->access_url, NULL, $pin );
      $this->settings = array(
        'state'  => 2,
        'token'  => $response['oauth_token'],
        'secret' => $response['oauth_token_secret']
      );
    }
    
    if ( $this->settings['state'] == 2 )
    {
      $oAuth->setToken( $this->settings['token'], $this->settings['secret'] );
      $oAuth->fetch( $this->verify_url ); 
      $response = json_decode( $oAuth->getLastResponse() );
      $this->settings['username'] = (string)$response->screen_name;
      $this->saveSettings();
      $this->home();
    }
    else
    {
      $this->saveSettings();
      $DSP->body = $r;
    }
    
  }
  
  function add()
  {
    global $IN, $SESS, $LANG, $DB, $PREFS;
    
    $errors = array();
    $id     = 'cron-' . time();
    
    # get the section
    $weblog_id = $IN->GBL('section','POST');
    if ( empty( $weblog_id ) )
    {
      $errors[] = $LANG->line('weblog_is_required') . $PREFS->ini('weblog_nomenclature');
    }
    elseif ( ! in_array( $weblog_id, array_keys( $SESS->userdata['assigned_weblogs'] ) ) )
    {
      $errors[] = $LANG->line('weblog_not_allowed') . $PREFS->ini('weblog_nomenclature');
    }
    
    # get the sticky status
    $sticky = $IN->GBL('sticky_only','POST');
    if ( $sticky !== 'y' )
    {
      $sticky = 'n';
    }
    
    # get the content
    $content = $IN->GBL('tweet_format','POST');
    if ( empty( $content ) )
    {
      $errors[] = $LANG->line('content_is_required');
    }
    elseif ( strlen( $content ) > 140 )
    {
      $errors[] = $LANG->line('content_is_too_long');
    }
    
    # get the timeframe
    $time_fields = array( 'duration', 'duration_type', 'duration_side', 'time' );
    foreach ( $time_fields as $f )
    {
      $$f = $IN->GBL($f,'POST');
      $plural = "{$f}s";
      if ( $$f == '' )
      {
        $errors[] = $LANG->line($f.'_is_required');
      }
      elseif ( isset( $this->$plural ) &&
               ! in_array( $$f, $this->$plural ) &&
               ! in_array( $$f, array_keys( $this->$plural ) ) )
      {
        $errors[] = $LANG->line($f.'_is_unacceptable');
      }
    }
    if ( ! is_numeric( $duration ) )
    {
      $errors[] = $LANG->line('duration_must_be_a_number');
    }
    
    if ( count( $errors ) )
    {
      $r  = '<ul>';
      foreach ( $errors as $error ) $r .= "<li>{$error}</li>";
      $r .= '</ul>';
      
      $this->home( $r );
    }
    else
    {
      $when = "INTERVAL {$duration_side} {$duration} " . strToUpper($duration_type) . "@{$time}";
      $this->crons[$id] = array(
        'weblog_id' => $weblog_id,
        'sticky'    => $sticky,
        'content'   => $content,
        'when'      => $when
      );
      
      $DB->query(
        $DB->update_string(
          "{$this->settings_table}",
          array( 'crons' => serialize( $this->crons ) ),
          array( 'site_id' => $this->site_id )
        )
      );
      
      header('Location: ' . html_entity_decode($this->getUrl()) );
      exit;
    }
    
  }
  
  function delete()
  {
    global $IN, $LANG, $DB;
    
    $cron = $IN->GBL('cron','GET');
    
    $response = array(
      'success' => FALSE,
      'message' => $LANG->line('deletion_error')
    );
    
    if ( ! empty( $cron ) )
    {
      unset( $this->crons[$cron] );
      
      $DB->query(
        $DB->update_string(
          "{$this->settings_table}",
          array( 'crons' => serialize( $this->crons ) ),
          array( 'site_id' => $this->site_id )
        )
      );
      if ( $DB->affected_rows > 0 )
      {
        $response['success'] = TRUE;
        $response['message'] = '';
      }
    }
    
    exit( json_encode($response) );
    
  }
  
  function cron()
  {
    global $DB, $PREFS, $FNS;
    
    # oAuth setup
    $oAuth = new OAuth( $this->key, $this->secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_FORM );
    $oAuth->setToken( $this->settings['token'], $this->settings['secret'] );
    $oAuth->enableDebug();
    
    # white time is it?
    $hour = date('H') . '00';
    $now  = time();
    
    foreach( $this->crons as $cron_id => $cron )
    {
    
      list( $lookup, $time ) = explode( '@', $cron['when'] );
      $sticky = $cron['sticky'] == 'y' ? "AND `t`.`sticky` = 'y'" : '';
      
      if ( $hour == $time )
      {
        $result = $DB->query(
          "SELECT `t`.`entry_id` AS `entry_id`,
                  `t`.`title` AS `title`,
                  CONCAT( `w`.`blog_url`, `t`.`url_title` ) AS `link`
           FROM   `{$PREFS->ini('db_prefix')}_weblog_titles` AS `t`
             INNER JOIN
                  `{$PREFS->ini('db_prefix')}_weblogs` AS `w` ON `t`.`weblog_id` = `w`.`weblog_id`
           WHERE DATE_FORMAT( DATE_ADD( FROM_UNIXTIME( `t`.`entry_date` ), {$lookup} ), '%Y%m%d' ) = 
                 DATE_FORMAT( NOW(), '%Y%m%d' )
             AND `t`.`weblog_id` = {$cron['weblog_id']}
             {$sticky}
             AND `t`.`entry_id` NOT IN ( SELECT `entry_id`
                                         FROM   `{$this->posts_table}`
                                         WHERE  `cron_id` = '{$cron_id}' )"
        )->result;
        
        if ( count( $result ) )
        {
          foreach ( $result as $entry )
          {
            $text = $FNS->var_swap( $cron['content'], $entry );
            $oAuth->fetch( $this->post_url . '?' . http_build_query( array(
              'status' => $text
            )));
            $response = json_decode( $oAuth->getLastResponse(), TRUE );
            if ( isset( $response['id'] ) )
            {
              $DB->query(
                $DB->insert_string(
                  $this->posts_table,
                  array(
                    'entry_id'   => $entry['entry_id'],
                    'site_id'    => $this->site_id,
                    'weblog_id'  => $cron['weblog_id'],
                    'cron_id'    => $cron_id,
                    'content'    => $text,
                    'tweeted_at' => $now
                  )
                )
              );
            }
            # sleeping seems to keep errors from happening
            sleep(5);
          }
        }
      }
    }
  }
  
  # UTILS
  function saveSettings()
  {
    global $DB, $PREFS;
    
    $DB->query(
      $DB->update_string(
        "{$this->settings_table}",
        array( 'settings' => serialize( $this->settings ) ),
        array( 'site_id' => $this->site_id )
      )
    );
  }
  function getUrl( $page='', $base=TRUE )
  {
    return ( $base ? BASE . AMP : '' ) . 'C=modules' . AMP . 'M=' . $this->module . ( ! empty( $page ) ? AMP . 'P=' . $page : '' );
  }
  function whenInEnglish( $when=FALSE )
  {
    global $LANG;
    if ( ! empty( $when ) )
    {
      $when = explode( '@', $when );
      $day  = explode( ' ', $when[0] );
      $time = $when[1];
      $when = $day[2] . ' ' . ( $day[2] == '1' ? $day[3] : $day[3] . 's' ) . ' ' .
              ( $day[1] == '-' ? 'before' : 'after' ) . ' ' . $LANG->line('posting_at') . ' ';
      $hour = (int)substr( $time, 0, 2 );
      $time = ( $hour > 12 ? $hour - 12 : $hour ) . ':' . substr( $time, 2, 2 ) . ( $hour < 12 ? 'AM' : 'PM' );
      $when = strToLower( $when ) . $time;
    }
    return $when;
  }
  
  # Display Stuff
  function css()
  {
    global $DSP;
    
    $DSP->manual_css .= "strong, .box p { font-size: inherit; }";
    $DSP->manual_css .= ".box li { margin-bottom: 1em; }";
    $DSP->manual_css .= ".button { display: block; font-size: 2em; width: 300px; text-align: center; margin: 1em auto; padding: .5em; background: grey; color: #fff; -webkit-transition: .5s; -webkit-border-radius: 10px; }";
    $DSP->manual_css .= ".button:hover { background: rgb(29, 127, 198); color: white; }";
    $DSP->manual_css .= ".form { width: 300px; margin: 1em auto; text-align: center; }";
    $DSP->manual_css .= ".form label { display: block; font-size: 1.5em; font-weight: bold; text-align: center; margin-bottom: .5em; }";
    $DSP->manual_css .= ".form input { font-size: 2em; }";
    $DSP->manual_css .= ".form .submit { margin-top: .5em; }";
    $DSP->manual_css .= ".crons { margin-bottom: 1em; }";
    $DSP->manual_css .= ".crons .section { width: 10%; }";
    $DSP->manual_css .= ".crons .content { width: 50%; }";
    $DSP->manual_css .= ".crons .delete { width: 1%; }";
    $DSP->manual_css .= ".crons .empty { padding: 1em; text-align: center; }";
    $DSP->manual_css .= ".crons tr:nth-child(2n+2) td { background-color: rgb(238, 244, 249); }";
    $DSP->manual_css .= ".new caption { font-size: 1.25em; font-weight: bold; text-align: left; margin-bottom: .5em; }";
    $DSP->manual_css .= ".new caption span { font-size: .8em; font-weight: normal; }";
    $DSP->manual_css .= ".new td { vertical-align: top; }";
    $DSP->manual_css .= ".new label { display: block; }";
    $DSP->manual_css .= ".new .content { padding: 2px 6px; }";
    $DSP->manual_css .= ".new .notes { margin-top: 5px; }";
    $DSP->manual_css .= ".new .when { white-space: nowrap; }";

  }
  function js()
  {
    global $LANG;
    return '<script type="text/javascript">
            //<![CDATA[
            (function($){
              if ( $ !== undefined )
              {
                $del = $(\'<input type="checkbox" name="cron"/>\').change(function(){
                  var
                  $this = $(this),
                  $row  = $this.parents("tr");
                  if ( confirm( "' . $LANG->line('delete_confirmation') . '" ) )
                  {
                    $.get("' . html_entity_decode( $this->getURL('delete') ) . '", { cron: $row.attr("id") }, function(data){
                      if ( data.success )
                      {
                        $row.fadeOut("slow",function(){
                          $row.remove();
                        });
                      }
                      else
                      {
                        alert( data.message );
                      }
                    },"json");
                  }
                  else
                  {
                    $this.removeAttr("checked");
                  }
                });
                $("#contentNB .crons tbody td.delete").append($del.clone(true));
              }
            })(jQuery);
            //]]>
            </script>';
  }
  function pageTop()
  {
    global $DSP, $LANG;
    # basic page
    $DSP->title = $LANG->line('auto_tweet_module_name');
    $DSP->crumb = $DSP->anchor($this->getUrl(),$LANG->line('auto_tweet_module_name'));
  }
  
  
  /** --------------------------------
  /**  Module installer
  /** --------------------------------*/
  function auto_tweet_module_install()
  {
    global $DB, $PREFS;
    
    $base_class = str_replace( '_CP', '', __CLASS__ );
    
    $DB->query(
      $DB->insert_string(
		    "{$PREFS->ini('db_prefix')}_modules",
		    array(
		      'module_id'      => '',
		      'module_name'    => $base_class,
		      'module_version' => $this->version,
		      'has_cp_backend' => 'y'
		    )
		  )
    );
    
    # activate the module
    $template = array(
  		'action_id' => '',
      'class'     => $base_class,
  		'method'    => FALSE
  	);
  	$actions = array(
  		// auto_tweet::cron
			array(
			  'method' => 'cron'
			),
		);
		foreach ( $actions as $a )
  	{
  		$a = array_merge( $template, $a );
  		$DB->query(
  		  $DB->insert_string(
  		    "{$PREFS->ini('db_prefix')}_actions",
  		    $a
  		  )
  		);
  	}
  	
  	# add our db table
  	$sql = array(
  	  "CREATE TABLE `{$this->posts_table}` (
         `entry_id` int(10) UNSIGNED NOT NULL,
         `site_id` int(10) UNSIGNED NOT NULL,
         `cron_id` varchar(100) UNSIGNED NOT NULL,
         `weblog_id` int(10) UNSIGNED NOT NULL,
         `content` varchar(140) NOT NULL,
         `tweeted_at` int(10) UNSIGNED NOT NULL,
         PRIMARY KEY  (`entry_id`)
       )",
       "CREATE TABLE `{$this->settings_table}` (
          `site_id` int(10) UNSIGNED NOT NULL,
          `settings` longtext NOT NULL,
          `crons` longtext NOT NULL,
          PRIMARY KEY  (`site_id`)
        )"
  	);
  	foreach ( $sql as $query ) $DB->query( $query );
  	
  	# create the initial settings
  	$DB->query(
  	  $DB->insert_string(
  	    $this->settings_table,
  	    array(
  	      'settings' => serialize( array( 'state' => 0 ) ),
  	      'crons'    => serialize( array() ),
  	      'site_id'  => $this->site_id
  	    )
  	  )
  	);
  	
    return TRUE;
  }
  /* END */
  
  
  /** -------------------------
  /**  Module de-installer
  /** -------------------------*/
  function auto_tweet_module_deinstall()
  {
    global $DB, $PREFS;
    
    $classes = implode( "', '", array( __CLASS__, str_replace( '_CP', '', __CLASS__ ) ) );
    
    $sql = array(
  	  "DELETE FROM `{$PREFS->ini('db_prefix')}_modules`
       WHERE       `module_name` IN ( '{$classes}' )",
      "DELETE FROM `{$PREFS->ini('db_prefix')}_actions`
       WHERE       `class` IN ( '{$classes}' )",
      "DELETE FROM `{$PREFS->ini('db_prefix')}_actions`
       WHERE       `class` IN ( '{$classes}' )",
      "DROP TABLE `{$this->posts_table}`",
      "DROP TABLE `{$this->settings_table}`",
  	);
  	foreach ( $sql as $query ) $DB->query( $query );
    
    return TRUE;
  }
  /* END */


}
// END CLASS
?>