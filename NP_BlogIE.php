<?php

/*                                       */
/* NP_BlogIE                             */
/* ------------------------------------  */
/* Import and export of blog data        */
/* Export of media                       */
/*                                       */
/* code by Jeff MacMichael               */
/* http://wiki.gednet.com/               */
/*                                       */

/* Requires: xsp.php and phpZipClass.php  */
/* both should be present in this package */

/* This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 */
 
/* Changes:
 *
 * v0.7b  [ged] - added NP_TrackBack >=v1.5 detection & support
 * v0.71b [ged] - corrected security catches in index.php & NP_BlogIE.php
 *
 */
 
class NP_BlogIE extends NucleusPlugin {

	function getName() 		{ return 'BlogIE'; }
	function getAuthor()  		{ return 'Jeff MacMichael'; }
	function getURL()  		{ return 'http://wiki.gednet.com/'; }
	function getVersion() 		{ return '0.71 beta'; }
	function getDescription() 	{ return 'Import and export blog data, and export media.'; }

	function supportsFeature($what) {
		switch($what)
		{ case 'SqlTablePrefix':
				return 1;
			default:
				return 0; }
	}

	function install() {
	}
	
	function unInstall() {
	}

	function getEventList() {
		return array('QuickMenu');
	}
	
	function hasAdminArea() {
		return 1;
	}
	
	function event_QuickMenu(&$data) {
		global $member, $nucleus, $blogid;
		// only show to admins
		if (preg_match("/MD$/", $nucleus['version'])) {
			$isblogadmin = $member->isBlogAdmin(-1);
		} else {
			$isblogadmin = $member->isBlogAdmin($blogid);
		}
		if (!($member->isLoggedIn() && ($member->isAdmin() | $isblogadmin))) return;
		array_push(
			$data['options'], 
			array(
				'title' => 'Blog Backup',
				'url' => $this->getAdminURL(),
				'tooltip' => 'Import and export blog items/comments'
			)
		);
	}
}
?>