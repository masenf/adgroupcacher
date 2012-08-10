<?php

require_once 'array_file.php';

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');
require_once(DOKU_INC.'inc/common.php');

class admin_plugin_adgroupcacher extends DokuWiki_Admin_Plugin {

	var $groups = array();
	var $update_interval = 30;

    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function getMenuSort() {
      return 999;
    }

    /**
     * handle user request
     */
    function handle() {
        global $auth;
        $this->_load();

        $act  = $_REQUEST['cmd'];
        $gid  = $_REQUEST['gid'];
        switch ($act) {
            case 'del' :$this->del($gid);break;
            case 'add' :$this->add($gid);break;
			case 'update' :$this->update();break;
			case 'other' :$this->other();break;
        }

    }
	function update() {
		// set the update interval
        if (!checkSecurityToken()) return false;

		if (isset($_REQUEST['refreshnow'])) {
			// schedule the refresh for now!
            @unlink(GROUPFILE);
            msg($this->getLang('manual_update'),2);
			return;
		}

		$newint = $_REQUEST['newint'];
        if (!is_numeric($newint) || $newint < 0) {
            msg($this->getLang('nan'),-1);
            return;
        }
		$this->update_interval = intval($newint);
		$this->_save();
	}
	function other() {
		// save other settings
        if (!checkSecurityToken()) return false;
		$base_dn = $_REQUEST['base_dn'];
		if (empty($base_dn)) {
			msg($this->getLang('nobasedn'), -1);
			return;
		}
		$this->base_dn = $base_dn;
		if ($this->_save() != False) 
            msg($this->getLang('savebasedn'),2);
		else
			msg($this->getLang('nosave'), -1);
	}
    function del($grp) {
        if (!checkSecurityToken()) return false;
		$k = array_search($grp, $this->groups);

        // grp don't exist
        if ($k === False) {
            return;
        }

        // delete the grp
        unset($this->groups[$k]);
        $this->_save();
    }

    function add($grp) {
        if (!checkSecurityToken()) return false;
        if (empty($grp)) {
            msg($this->getLang('nogrp'),-1);
            return;
        }

        // get the groups as array
        $grp = str_replace(' ','',$grp);

		// add to list of groups
		$this->groups[] = $grp;

        // save the changes
        if ($this->_save() === False)
			msg($this->getLang('nosave'), -1);
    }
    function _save() {
        global $conf;

		$data['groups'] = $this->groups;
		$data['update_interval'] = $this->update_interval;
		$data['base_dn'] = $this->base_dn;
		array_store($data, SETTINGSFILE);
    }


    /**
     * load the users -> group connection
     */
    function _load() {
		global $conf;

		$data = array_load(SETTINGSFILE);
		if ($data) {
			$this->groups = $data['groups'];
			$this->update_interval = $data['update_interval'];
			$this->base_dn = $data['base_dn'];
		}
		if (file_exists(GROUPFILE))
			$this->last_update = filemtime(GROUPFILE);
    }

    /**
     * output appropriate html
     */
    function html() {
        global $ID;
		
		ptln($this->getLang('instructions'));

        $form = new Doku_Form(array('id' => 'adgc1', 'action' => wl($ID), 'class' => 'adgc'));
        $form->addHidden('cmd', 'add');
        $form->addHidden('sectok', getSecurityToken());
        $form->addHidden('page', $this->getPluginName());
        $form->addHidden('do', 'admin');
        $form->startFieldset($this->getLang('menu'));
		$form->addElement(form_makeField('text', 'gid', '',
										 $this->getLang('grname')));
        $form->addElement(form_makeButton('submit', '',
                                          $this->getLang('add')));
        $form->printForm();

        $form = new Doku_Form(array('id' => 'adgc2', 'action' => wl($ID), 'class' => 'adgc'));
        $form->addHidden('cmd', 'update');
        $form->addHidden('sectok', getSecurityToken());
        $form->addHidden('page', $this->getPluginName());
        $form->addHidden('do', 'admin');
        $form->startFieldset($this->getLang('cacherefresh'));
		$form->addElement(form_makeField('text', 'newint', $this->update_interval,
										 $this->getLang('refresh')));
        $form->addElement(form_makeButton('submit', '',
                                          $this->getLang('save')));
		if ($this->update_interval == 0) {
			$nxtupdate = $this->getLang('never');
		} else {
			$nxtupdate = round($this->update_interval - ((time() - $this->last_update) / 60),2) . $this->getLang('minutes');
		}
		$form->addElement("<br><br>" , sprintf($this->getLang('nxtupdate'), $nxtupdate) . "&nbsp;&nbsp;&nbsp; ");
		$form->addElement(form_makeButton('submit', '',
										  $this->getLang('refreshnow'),
										  array('name' => 'refreshnow')));
        $form->printForm();

        $form = new Doku_Form(array('id' => 'adgc3', 'action' => wl($ID), 'class' => 'adgc'));
        $form->addHidden('cmd', 'other');
        $form->addHidden('sectok', getSecurityToken());
        $form->addHidden('page', $this->getPluginName());
        $form->addHidden('do', 'admin');
        $form->startFieldset($this->getLang('other'));
		$form->addElement("<p><small>".$this->getLang('otherwarn')."</small></p>");
		$form->addElement(form_makeField('text', 'base_dn', $this->base_dn,
										 $this->getLang('basedn'),'','',array("size" => 40)));
		$form->addElement("<br><br>");
        $form->addElement(form_makeButton('submit', '',
                                          $this->getLang('save')));
        $form->printForm();

        ptln('<table class="inline" id="adgc__show">');
        ptln('  <tr>');
        ptln('    <th class="grp">'.hsc($this->getLang('grps')).'</th>');
        ptln('    <th> </th>');
        ptln('  </tr>');
        foreach ($this->groups as $grp) {
            ptln('  <tr>');
            ptln('    <td>'.hsc($grp).'</td>');
            ptln('    <td class="act">');
            ptln('      <a class="adgc_del" href="'.wl($ID,array('do'=>'admin','page'=>$this->getPluginName(),'cmd'=>'del','gid'=>$grp, 'sectok'=>getSecurityToken())).'">'.hsc($this->getLang('del')).'</a>');
            ptln('    </td>');
            ptln('  </tr>');
        }

        ptln('</table>');

        ptln('<table class="inline" id="adgc__yourgroups">');
        ptln('  <tr>');
        ptln('    <th class="grp">'.hsc($this->getLang('cur_grps')).'</th>');
        ptln('  </tr>');

        global $USERINFO;
        foreach ($USERINFO['grps'] as $grp) {
            ptln('  <tr>');
            ptln('    <td>'.hsc($grp).'</td>');
            ptln('  </tr>');
        }

        ptln('</table>');
    }
}
