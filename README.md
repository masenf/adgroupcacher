adgroupcacher
=============

Dokuwiki plugin for recursively enumerating and caching groups from an LDAP directory
By Masen Furer

Based on virtualgroup By Dominik Eckelmann (https://www.dokuwiki.org/plugin:virtualgroup) [GPL]

The purpose of this plugin is two-fold:
  1) To reduce the strain on the directory server from the current group 
  implementation in the ad auth backend in Dokuwiki, which does not 
  play nicely with large numbers of nested groups.
  2) To speed up login over the built-in ad auth backend, and use our 
  in-house CAS Single Sign-On system with Active Directory groups.
  
What does it do?
----------------
This plugin caches a mapping of username to specified AD groups. Whenever the cache 
becomes stale, the next page load will serve to update the cache to the latest 
entries.

Usage
-----

When installed as a Dokuwiki plugin, a new admin option will appear to configure the plugin.
(At this time, the LDAP server details are not exposed in the user interface, but should be
configured in action.php around line 60). From here, groups can be added that you would like
to use in ACLs. Groups which are not specifically added, included by wildcard expansion,
or nested within an included group will not be available for permission purposes.

Here you can also set the refresh interval, which is the maximum length of time to keep a 
cache before updating. The cache will be updated on the next page load regardless of who 
initiates it. This can cause quite a long delay for the unlucky user who happens to trigger
the cache refresh. The time it takes to update depends on how many groups are being cached,
how many nested groups need to be fetched that are NOT listed in the group list or included by 
wildcards, and whether or not the users have been cached yet. Until the cache is updated, 
no one will be able to continue. It will be helpful to determine how long it takes to 
perform a refresh with your configuration and adapt accordingly (either in behavior or code).
Set the refresh interval with regard to how long it takes to update vs. how frequently your 
AD groups will change. If you prefer to only update manually, set the refresh interval to 0.

If you are using large numbers of nested groups, it may improve performance to explicity add 
all of the nested groups to the cache list. The reason is all groups listed on the settings
page are fetched in a single LDAP query. Any nested groups which are not explicity listed
(or included by wildcard) will have to be fetched separately in their own LDAP query. For example,
having 60 unspecified groups which need to be fetched separately can increase the refresh time by several
seconds depending on the speed of and traffic to the directory server.
(TODO: add a url that can be accessed to update the cache via a cron job.)

Storage
-------

All cached data is stored on disk under 
    dokuwiki/data/adgroup*.php
When making major changes to users or AD, it may be useful to delete adgroupusers.php and adgroupdata.php
and initiate a manual refresh and recache of all user distinguished names. This may take
a minute or more if there are a lot of unique users in AD, once all the user distinguished names 
are fetched, they are stored indefinitely so that future updates are speedy.

This plugin is not totally complete or polished because it was modified and largely rewritten
to serve a personal need. I hope that you can find a good use for it as well. If you have
any questions, submit an issue or email <masenf@gmail.com>. Patches welcome.
  

  