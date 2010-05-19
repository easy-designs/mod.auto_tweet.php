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

if ( ! defined('EXT')) exit('No direct script access allowed');

class Auto_tweet {
  
  /**
   * Constructor 
   */
  function Auto_tweet(){}
    
  /**
   * Installation instructions
   */
  function cron()
  {
    require_once PATH_MOD . 'auto_tweet/mcp.auto_tweet' . EXT;
    $AutoTweet = new Auto_tweet_CP(FALSE);
    $AutoTweet->cron();
  }

}
// END CLASS
?>