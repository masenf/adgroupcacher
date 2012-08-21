<?php
/**
 * mod_auth_tkt auth backend
 *
 * Uses external Trust mechanism to check against mod_auth_tkt's
 * ENV variable. 
 *
 * @author    Qiang Li <qiangli at cpan.org>
 */
 
define('DOKU_AUTH', dirname(__FILE__));
define('AUTH_USERFILE',DOKU_CONF.'users.auth.php');
 
class auth_passthrough extends auth_basic {
 
  /**
   * Constructor.
   *
   * Sets additional capabilities and config strings
   */
  function auth_passthrough(){
    $this->cando['external'] = true;
  }
 
  /**
   * Just checks against the $pun_user variable
   */
  function trustExternal($user,$pass,$sticky=false){
    global $USERINFO;
    global $conf;
    $sticky ? $sticky = true : $sticky = false; //sanity check
 
    if( isset($_SERVER['REMOTE_USER']) && $_SERVER['REMOTE_USER'] != 'guest' ){
      // okay we're logged in - set the globals
      $groups = $this->_getUserGroups($_SERVER['REMOTE_USER']);
 
      $USERINFO['name'] = $_SERVER['REMOTE_USER'];
      $USERINFO['pass'] = '';
      $USERINFO['mail'] = $_SERVER['REMOTE_USER'] . '@wwu.edu';
      $USERINFO['grps'] = $groups;
 
//    $_SERVER['REMOTE_USER'] = $_SERVER['HTTP_REMOTE_USER'];
      $_SESSION[$conf['title']]['auth']['user'] = $_SERVER['REMOTE_USER'];
      $_SESSION[$conf['title']]['auth']['info'] = $USERINFO;
      return true;
    }
 
    return false;
  } 
 
  function _getUserGroups($user){
	  $groups = array();
      if(!@file_exists(AUTH_USERFILE)) return;
 
      $lines = file(AUTH_USERFILE);
      foreach($lines as $line){
        $line = preg_replace('/#.*$/','',$line); //ignore comments
        $line = trim($line);
        if(empty($line)) continue;
 
        $row    = split(":",$line,5);
        $grps   = split(",",$row[4]);
 
        if($user == $row[0]) {
			$groups = $grps;
			break;
		}	
      }

	  // early load AD groups
	  require_once DOKU_INC.'lib/plugins/adgroupcacher/array_file.php';
	  require_once DOKU_INC.'lib/plugins/adgroupcacher/action.php';
	  $users = (($data = array_load(DOKU_INC . GROUPFILE)) == False) ? array() : $data;
	  if (array_key_exists($user, $users))
		  $groups = array_unique(array_merge($groups,$users[$user]));
      return $groups;
    }
}
