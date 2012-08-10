<?php

// admin.php
// headers
$lang['menu'] = 'AD Group Cacher';
$lang['cacherefresh'] = 'Refresh Cache';
$lang['grps'] = 'Groups';
$lang['cur_grps'] = 'Current Groups';
$lang['other'] = 'Other Settings';

// buttons
$lang['save'] = 'Save';
$lang['add'] = 'Add';
$lang['refreshnow'] = 'Refresh Now';
$lang['del'] = 'Delete';

// labels
$lang['refresh'] = 'Refresh Interval (minutes)';
$lang['grname'] = 'Group name: ';
$lang['basedn'] = 'Base DN ';
$lang['nxtupdate'] = 'Next update in %s';

// messages
$lang['nan'] = 'Please enter a positive number or 0';
$lang['nogrp'] = 'Group name must not be blank';
$lang['manual_update'] = 'Cache updated manually';
$lang['nobasedn'] = 'Base DN must not be empty';
$lang['savebasedn'] = 'Base DN saved successfully';
$lang['nosave'] = 'Could not save settings, check permissions in data folder';

// words
$lang['never'] = 'Never';
$lang['minutes'] = 'minutes';

// instructions
$lang['instructions'] = '<div><p>This plugin caches Active Directory groups to improve '.
	  'the responsiveness of the wiki with AD group ACLs. <p><b>Add</b> any groups '.
	  'you would like to use in ACLs below and their members will be '.
	  'periodically fetched and stored whenever someone loads a page and '.
	  'the cache is older than the <b>Refresh Interval</b>. You may '.
	  'also initiate a manual refresh (<b>Refresh Now</b>) in order to update the groups '.
	  'immediately. <p>Wildcards are allowed in group names such as '.
	  '<b>grp.housing.*</b> <p>Below you will find a list of groups which '.
	  'are currently being stored/refreshed. Beneath that is a list of '.
	  'your current groups as seen by Dokuwiki. Dokuwiki will be unaware '.
	  'of any membership, direct or indirect, in groups which are not '.
	  'added to the cache list below.</p></div>';
$lang['otherwarn'] = 'These settings control how the plugin searches AD, consult your '.
					 'network administrator for more information';
// action.php
$lang['log'] = '[dokuwiki] Regenerated group cache in %01.2f seconds after %01.2f minutes';

