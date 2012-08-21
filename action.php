<?php
//apd_set_pprof_trace();
require_once 'LDAP.php';
require_once 'array_file.php';

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

global $conf;
define("SETTINGSFILE",$conf['savedir'] . "/adgroupsettings.php");
define("USERFILE",$conf['savedir'] . "/adgroupusers.php");
define("GROUPFILE",$conf['savedir'] . "/adgroupdata.php");

require_once DOKU_PLUGIN.'action.php';
class action_plugin_adgroupcacher extends DokuWiki_Action_Plugin {

    var $users = array();
	var $groups = array();
	var $all_grps = array();
	var $raw_grps = array();
	var $user_dn = array();
	var $update_interval = 30;
	var $last_update = 0;

    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function register(&$controller) {
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this,'start');
    }

    function start(&$event, $param) {
        global $USERINFO;
        global $auth;
        global $INFO;
        if (!$_SERVER['REMOTE_USER']) {
            return;
        }

        $this->_load();
		if ($this->update_interval != 0 || $this->last_update == 0) {
			if ((time() - $this->last_update) / 60 > $this->update_interval) {
				// cache is stale, refresh it
				$this->update_data();
			}
		}

        if (!isset($this->users[$_SERVER['REMOTE_USER']])) {
            return;
        }
//		if (!isset($USERINFO['grps']))
//			$USERINFO['grps'] = array();
//
//        $grps = array_unique(array_merge($USERINFO['grps'],$this->users[$_SERVER['REMOTE_USER']]));
//        $USERINFO['grps']       = $grps;
//        $_SESSION[DOKU_COOKIE]['auth']['info']['grps'] = $grps;
        $INFO = pageinfo();
    }
	function update_data () {
		if (empty($this->base_dn)) {
			msg("Please set a base DN for adgroupcacher in admin area",2);
			return;
		}

		// load cached user_dn => sAMAccountName data
		$this->user_dn = (($data = array_load(USERFILE)) == False) ? array() : $data;

		$this->users = array();							// empty current group db

		// We're using a custom LDAP wrapper here, but this could
		// easily be replaced by a standard ldap connection:
		//	$this->ctx = ldap_connect("ldaps://ad.university.edu");
		//	if (!ldap_bind($this->ctx, $bind_dn, $bind_pw))
		//		die("Could not connect to LDAP directory");
		$this->ldc = shared_LDAP::getInstance("wwu");	// connect to ldap
		$this->ctx = $this->ldc->getConnection();

		$staleness = (time() - $this->last_update) / 60;
		$start = microtime(True);

		// get group data for all CNs specified in settings file
		$filter = "(|(CN=" . implode (")(CN=", $this->groups) . "))";
		$res = ldap_search($this->ctx,$this->base_dn,$filter,array("member"));
		if ($res) 
			$raw_res = ldap_get_entries($this->ctx,$res);
		else
			return;

		// load raw data
		for ($i=0;$i<$raw_res['count'];$i++) 
			$this->raw_grps[$raw_res[$i]['dn']] = $raw_res[$i]['member'];

		// recursively lookup member DNs from raw data
		foreach ($this->raw_grps as $k=>$m)
			$this->get_group_members($k);

		// store data as array('user' => array(/*groups*/), ...)
		$this->groups_to_users();

		// save and log
		$elapsed = microtime(True) - $start;
		syslog(LOG_INFO, sprintf($this->getLang('log'),$elapsed, $staleness));
		$this->_save();
	}
	/* get_group_members -- fill (array) $this->all_grps with 
	 *		grp name => [ user1, user2, ... ]
	 * recursively called to expand nested subgroups
	 * return an array of usernames present in the group $dn
	 */
	function get_group_members ( $dn ) {
		// get the short group name
		$cn = substr(explode(",",$dn)[0],3);

		// If the group has not been cached, do it now:
		if (!array_key_exists($cn, $this->all_grps)) {
			$this->all_grps[$cn] = array();

			// If the group members have not been fetched, get from LDAP:
			if (!array_key_exists($dn, $this->raw_grps)) {
				$res = ldap_read($this->ctx,$dn,"(objectClass=*)",array("member","cn"));
				if ($res) {
					$raw_result = ldap_get_entries($this->ctx,$res);
					$this->raw_grps[$dn] = $raw_result[0]['member'];
				} else {
					$this->raw_grps[$dn] = array('count' => 0);		// DN not found
				}
			}

			// Expand each group member recursively
			for($i=0;$i<$this->raw_grps[$dn]['count'];$i++) {
				$entry = $this->raw_grps[$dn][$i];
				$mem = explode(",",$entry);
				if (stripos(substr($mem[0],0,7),"cn=grp.") === False) {
					// $entry is a user, add to current grp
					if (array_key_exists($entry, $this->user_dn)) {
						$this->all_grps[$cn][] = $this->user_dn[$entry];
					} else {
						$this->all_grps[$cn][] = $this->get_sAMAccountName( $entry );
					}
				} else {
					// $entry is another group, merge its members with current grp
					$this->all_grps[$cn] = array_merge($this->all_grps[$cn],$this->get_group_members( $entry )); 
				}
			}
		}
		return $this->all_grps[$cn];
	}
	/* get_sAMAccountName -- return the login name of a given DN, $dn
	 * after retrieving, the DN and sAMAccountName are stored in the
	 * user_dn cache to avoid LDAP lookups in the future
	 */
	function get_sAMAccountName ( $dn ) {
		$res = ldap_read($this->ctx,$dn,"(objectClass=*)",array("sAMAccountName"));
		if (!$res) {
			$this->user_dn[$dn] = $dn;
		} else {
			$san = ldap_get_entries($this->ctx,$res);
			$this->user_dn[$dn] = $san[0]['samaccountname'][0];
		}
		return $this->user_dn[$dn];
	}
	/* groups_to_users -- build the $this->users array by 
	 * looping through each group and adding the group name
	 * to an array keyed by username
	 *		[ user1 => [grp1, grp2], user2 => [grp2, grp3] ]
	 */
	function groups_to_users ( ) {
		foreach ($this->all_grps as $grp => $members) {
			$members = array_unique($members);		// no duplicate members in a grp
			foreach ($members as $m) {
				if ( !array_key_exists($m, $this->users) ) {
					$this->users[$m] = array();
				}
				$this->users[$m][] = $grp;
			}
		}
	}

    function _save() {
        global $conf;

		array_store($this->users, GROUPFILE);
		array_store($this->user_dn, USERFILE);
    }

    /**
     * load the users -> group connection
     */
    function _load() {
		$data = array_load(SETTINGSFILE);
		if ($data) {
			$this->groups = $data['groups'];
			$this->update_interval = $data['update_interval'];
			$this->base_dn = $data['base_dn'];
		}
		$this->users = (($data = array_load(GROUPFILE)) == False) ? array() : $data;
		if (file_exists(GROUPFILE))
			$this->last_update = filemtime(GROUPFILE);
    }
}
?>
