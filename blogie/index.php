<?php

/*                                       */
/* NP_BlogIE's 0.71b admin page           */
/* ------------------------------------  */
/* Import and export of blog data        */
/* Export of media                       */
/*                                       */
/* code by Jeff MacMichael               */
/* http://wiki.gednet.com/               */
/*                                       */
/* See NP_BlogIE.php for details         */

	$strRel = '../../../'; 
	include($strRel . 'config.php');

	include($DIR_LIBS . 'PLUGINADMIN.php');
	
	if (preg_match("/MD$/", $nucleus['version'])) {
		$isblogadmin = $member->isBlogAdmin(-1);
	} else {
		$isblogadmin = $member->isBlogAdmin($blogid);
	}
	if (!($member->isAdmin() || $isblogadmin)) {
		$oPluginAdmin = new PluginAdmin('SkinFiles');
		$oPluginAdmin->start();
		echo "<p>"._ERROR_DISALLOWED."</p>";
		$oPluginAdmin->end();
		exit;
	}

	global $pluginsblogie, $CONF, $manager;
	$pluginsblogie=$CONF['PluginURL']."blogie";

	// create the admin area page
	$oPluginAdmin = new PluginAdmin('BlogIE');
	
	if ($manager->pluginInstalled("NP_TrackBack")) $trackback = TRUE;

	if (isset($_POST['action'])) { $action = $_POST['action']; }

	if ($action == 'import') { 
		$toBlog = new BLOG($_POST['intoblog']);
		$blogInfo['blogid'] = $_POST['intoblog'];
		blogieimport(); 
	} elseif ($action == 'export') {
		blogieexport();
	} elseif ($action == 'exportmedia') {
		blogieexportmedia();
	} else {
		blogieoverview();
	}
 	
		
	function listblogs() {
		// return <option> tags for this member's owned blogs; or all blogs if admin
		global $member;
		if ($member->isAdmin()) {
			$query =  'SELECT bnumber, bname'
				. ' FROM ' . sql_table('blog')
				. ' ORDER BY bname';
		} else {
			$query =  'SELECT bnumber, bname'
				. ' FROM ' . sql_table('blog') . ', ' . sql_table('team')
				. ' WHERE tblog=bnumber and tmember=' . $member->getID()
				. ' ORDER BY bname';
		}
		$res = sql_query($query);
		while ($blogObj = mysql_fetch_object($res)) {
			$blogname = htmlspecialchars($blogObj->bname);
			$blogid = $blogObj->bnumber;
			echo "<option value=\"$blogid\">$blogname</option>";
		}
	}

	function blogieoverview($msg = '') {
		global $pluginsblogie, $CONF, $oPluginAdmin, $trackback;
		
		$oPluginAdmin->start();

		if ($msg) echo "<hr><p><b>$msg</b></p><hr>";
		echo "<h2>Import Blog</h2>";
	
		?><form method="POST" enctype="multipart/form-data" action="<?php echo $pluginsblogie ?>/">
			<input type="hidden" name="action" value="import" />
			<?php echo "Select a blog to import into: "; ?>
	
			<select name="intoblog" id="blogie_import_name">
			<?php listblogs() ?>
			</select><br /><br />
	
			<input type="checkbox" name="clearfirst" value="1" id="cb_clearfirst" />
			<label for="cb_clearfirst"><?php echo "Delete all existing categories, bans, items, and comments first?<br />(Make sure you have a backup before selecting this!)" ?></label>
			<br /><br />
			
			<p><b>Note</b>: When importing a blog item: if the author's name in the import file is not on this blog's team,<br />the item will be added in <b>your</b> name.</p>
			<br />
			<?php echo "Choose a file to import:<br />"; ?>
			<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $CONF['MaxUploadSize']?>" />
			<input type="file" name="filename" size="40"><br /><br />
			<input type="submit" value="Import" />
		</form><?PHP
	
		echo "<br /><h2>Export Blog</h2>";
	
		?><form method="POST" enctype="multipart/form-data" action="<?php echo $pluginsblogie ?>/">
			<input type="hidden" name="action" value="export" />
			<?php echo "Select a blog to export: "; ?>
	
			<select name="fromblog" id="blogie_export_name">
			<?php listblogs() ?> 
			</select><br />
	
			<input type="checkbox" name="exportbans" value="1" id="cb_expbans" />
			<label for="cb_expbans"><?php echo "Export this blog's bans<br />" ?></label>
			<input type="checkbox" name="exportcomments" value="1" id="cb_expcomments" />
			<label for="cb_expcomments"><?php echo "Export comments with items<br />" ?></label>
			<input type="checkbox" name="exportkarma" value="1" id="cb_expkarma" />
			<label for="cb_expkarma"><?php echo "Export karma votes with items<br />" ?></label>
			<?php if ($trackback) { ?>
				<input type="checkbox" name="exporttb" value="1" id="cb_exptb" />
				<label for="cb_exptb"><?php echo "Export trackbacks  with items<br />" ?></label>
			<?php } ?>
			<br /><input type="submit" value="Export Blog" />
			</form><?PHP
		
		echo "<br /><h2>Export Media Files</h2>";
		echo "<p>Export all of your media to a .ZIP file</p>";
		
		?><form method="POST" enctype="multipart/form-data" action="<?php echo $pluginsblogie ?>/">
			<input type="hidden" name="action" value="exportmedia" />
			<input type="submit" value="Export Media" />
		</form><?PHP
		
		$oPluginAdmin->end();
	}	

	function blogieexport() {
		global $member;
		$blogid = $_POST['fromblog'];
		$exportbans = $_POST['exportbans'];
		$exportkarma = $_POST['exportkarma'];
		$exportcomments = $_POST['exportcomments'];
		$exporttb = $_POST['exporttb'];
		
		if (!$member->isBlogAdmin($blogid)) { 
			global $oPluginAdmin;
			$oPluginAdmin->start();
			blogieoverview();
			$oPluginAdmin->end();
			return;
			break;
		}
		
		header('Content-Type: text/xml');
		header('Content-Disposition: attachment; filename="blogbackup.xml"');
		header('Expires: 0');
		header('Pragma: no-cache');

		
		// save the item author names for later lookup (reduce queries)
		$query = 'SELECT DISTINCT iauthor FROM ' . sql_table('item')
			. ' WHERE iblog='.$blogid;
		$res = sql_query($query);
		while ($obj = mysql_fetch_object($res)) {
			$mem = MEMBER::createFromID($obj->iauthor);
			$iauthname[$obj->iauthor] = $mem->getDisplayName();
			unset ($mem);
		}

		// same for the comment authors
		$query = 'SELECT DISTINCT cmember FROM ' . sql_table('comment')
			. ' WHERE cblog='.$blogid;
		$res = sql_query($query);
		while ($obj = mysql_fetch_object($res)) {
			$mem = MEMBER::createFromID($obj->cmember);
			$cauthname[$obj->cmember] = $mem->getDisplayName();
			unset ($mem);
		}

		$expblog = new BLOG($blogid);
		// add other blog options here if desired (time offset, default skin...)
		// for now we're just exporting the name & description
		echo  '<nucleusblogexport'
			. ' exportdatetime = "'.date("F j, Y  g:i a").'">';
		echo  '<blogname><![CDATA['.$expblog->getName().']]></blogname>'
			. '<blogdesc><![CDATA['.$expblog->getDescription().']]></blogdesc>';
			
		// output the bans for this blog
		if ($exportbans) {
			$query = 'SELECT * FROM ' . sql_table('ban')
				. ' WHERE blogid='.$blogid;
			$res = sql_query($query);
			while ($obj = mysql_fetch_object($res)) {
				echo  '<ban>'
					. '<iprange><![CDATA['.$obj->iprange.']]></iprange>'
					. '<reason><![CDATA['.$obj->reason.']]></reason>'
					. '</ban>';
			}
		}
			
		// output the categories for this blog
		$query = 'SELECT * FROM ' . sql_table('category')
			. ' WHERE cblog='.$blogid;
		$res = sql_query($query);
		while ($obj = mysql_fetch_object($res)) {
			echo  '<category>'
				. '<name><![CDATA['.$obj->cname.']]></name>'
				. '<desc><![CDATA['.$obj->cdesc.']]></desc>'
				. '</category>';
			// save category ids => names
			$catname[$obj->catid] = addslashes($obj->cname);
		}
		
		$query =  'SELECT * FROM ' . sql_table('item')
			. ' WHERE iblog='.$blogid
			. ' ORDER BY inumber';
		$res = sql_query($query);
		while ($itemObj = mysql_fetch_object($res)) { 
			echo '<item'
				. ' author="'.$iauthname[$itemObj->iauthor].'"' 
				. ' datetime="'.$itemObj->itime.'"' 
				. ' closed="'.$itemObj->iclosed.'"' 
				. ' draft="'.$itemObj->idraft.'"' 
				. ' karmapos="'.$itemObj->ikarmapos.'"' 
				. ' karmaneg="'.$itemObj->ikarmaneg.'">'
				. '<itemcat><![CDATA['.$catname[$itemObj->icat].']]></itemcat>'
				. '<title><![CDATA[' . $itemObj->ititle . ']]></title>'
				. '<body><![CDATA[' . $itemObj->ibody . ']]></body>'
				. '<more><![CDATA[' . $itemObj->imore . ']]></more>';
			
			// output karma votes if requested
			if ($exportkarma) {
				$subquery = 'SELECT * FROM ' . sql_table('karma')
					. ' WHERE itemid='.$itemObj->inumber;
				$subres = sql_query($subquery);
				while ($subObj = mysql_fetch_object($subres)) {
					echo '<karmavote ip="'.$subObj->ip.'" />'; 
				}
			}

			// output trackbacks if requested
			if ($exporttb) {
				$subquery = 'SELECT * FROM ' . sql_table('plugin_tb')
					. ' WHERE tb_id='.$itemObj->inumber;
				$subres = sql_query($subquery);
				while ($subObj = mysql_fetch_object($subres)) {
					echo '<trackback>'
					. '<tburl><![CDATA[' . $subObj->url . ']]></tburl>'
					. '<tbtitle><![CDATA[' . $subObj->title . ']]></tbtitle>'
					. '<tbexcerpt><![CDATA[' . $subObj->excerpt . ']]></tbexcerpt>'
					. '<tbblogname><![CDATA[' . $subObj->blog_name . ']]></tbblogname>'
					. '<tbtimestamp><![CDATA[' . $subObj->timestamp . ']]></tbtimestamp>'
					. '</trackback>';
				}
			}

			// output comments if requested
			if ($exportcomments) {
				$commentquery = 'SELECT * FROM ' . sql_table('comment')
					. ' WHERE citem='.$itemObj->inumber
					. ' ORDER BY cnumber';
				$commentres = sql_query($commentquery);
				while ($commentObj = mysql_fetch_object($commentres)) {
					echo '<comment ' 
						. ' member="'.$cauthname[$commentObj->cmember].'"' 
						. ' datetime="'.$commentObj->ctime.'"' 
						. ' host="'.$commentObj->chost.'"' 
						. ' ip="'.$commentObj->cip.'">'
						. '<user><![CDATA['.$commentObj->cuser.']]></user>' 
						. '<mail><![CDATA['.$commentObj->cmail.']]></mail>' 
						. '<body><![CDATA['.$commentObj->cbody.']]></body></comment>';
				}
			}
			echo '</item>';
		}
			
		echo '</nucleusblogexport>';

		// that's all
	}

	function blogieexportmedia() {
		global $member, $DIR_MEDIA;
		
		if ($dh = opendir($DIR_MEDIA.$member->getID().'/')) { 
			while (($file = readdir($dh)) !== false) { 
				if(!preg_match("/^\.{1,2}/", $file)){
					if (!is_dir($currdir.$file)) $files[] = $file;
				}
			}
			closedir($dh); 
			if (count($files)) {
				$zipfile = $DIR_MEDIA.'member'.$member->getID().'-media.zip';
				$mediafiles = $DIR_MEDIA.$member->getID().'/*';

				if (file_exists($zipfile)) unlink($zipfile);
				
				$zipcmd = "zip -j $zipfile $mediafiles";
				exec($zipcmd, $cmdoutput);
	
				header("Content-type: application/octet-stream");    
				header("Content-disposition: attachment; filename=media.zip");
				header("Content-length: ".filesize($zipfile));
				header('Expires: 0');
				header('Pragma: no-cache');
				readfile ($zipfile);
				unlink ($zipfile);
 			}
		}
	}

	
	
	function blogieimport() {
		global $member, $HTTP_POST_FILES, $DIR_PLUGINS, $trackback;
		$blogid = $_POST['intoblog'];
		$clearfirst = $_POST['clearfirst'];
		$filename = $HTTP_POST_FILES['filename']['tmp_name'];
		
		if (!$member->isBlogAdmin($blogid)) { 
			global $oPluginAdmin;
			$oPluginAdmin->start();
			blogieoverview();
			$oPluginAdmin->end();
			return;
			break;
		}
		
		// let's begin!
		include_once($DIR_PLUGINS.'blogie/xsp.php');
		$xsp = XSP::createFileParser($filename);
		if (!$xsp) {
			blogieoverview('Error: Could not create file parser.');
			return;
		}

		$blog = new BLOG($blogid);
		$report = 'Results of import:<br /><ul>';

		// if user wants to delete everything first, then...
		if ($clearfirst) {
			$res = sql_query('SELECT inumber FROM '.sql_table('item').' WHERE iblog='.$blogid);
			while ($obj = mysql_fetch_object($res)) {
				$kres = sql_query('DELETE FROM '.sql_table('karma').' WHERE itemid='.$obj->inumber);
				if ($trackback) 
					$kres = sql_query('DELETE FROM '.sql_table('plugin_tb').' WHERE tb_id='.$obj->inumber);
			}
			$res = sql_query('DELETE FROM '.sql_table('item').' WHERE iblog='.$blogid);
			$res = sql_query('DELETE FROM '.sql_table('comment').' WHERE cblog='.$blogid);
			$res = sql_query('DELETE FROM '.sql_table('ban').' WHERE blogid='.$blogid);
			$res = sql_query('DELETE FROM '.sql_table('category')
					. ' WHERE cblog='.$blogid.' AND catid<>'.$blog->getDefaultCategory());
			$report .= '<li>Removed all of blog\'s items, comments, bans, and karma votes</li>';
		}

		// create/update categories
		$xsp->reset();
		$cats = 0;
		$cat = $xsp->getNextElement('category', $nothing, $cdata);
		while (strpos($cat, 'ERROR') !== 0) {
			$xspcat = XSP::createMemoryParser($cat);
			$xspcat->reset(); 
			$xspcat->getNextElement('name', $nothing, $name);
			$xspcat->reset();
			$xspcat->getNextElement('desc', $nothing, $desc);
			$xspcat->cleanup; 
			unset($xspcat);
 			$res = mysql_query('SELECT catid FROM '.sql_table('category')
				. ' WHERE cblog='.$blogid.' and cname="'.addslashes($name).'"');
			if (!mysql_num_rows($res)) {
				$blog->createNewCategory($name, $desc);
				$cats++;
			}
			$cat = $xsp->getNextElement('category', $nothing, $cdata);
		}		
		$report .= "<li>Imported $cats new categories</li>";

		// create bans (don't update existing)
		$xsp->reset();
		$bans = 0;
		$ban = $xsp->getNextElement('ban', $nothing, $cdata);
		while (strpos($ban, 'ERROR') !== 0) {
			$xspban = XSP::createMemoryParser($ban);
			$xspban->reset(); 
			$xspban->getNextElement('iprange', $nothing, $iprange);
			$xspban->reset();
			$xspban->getNextElement('reason', $nothing, $reason);
			$xspban->cleanup; 
			unset($xspban);
			if (!BAN::isBanned($blogid, $iprange)) {
				BAN::addBan($blogid, $iprange, $reason);
				$bans++;
			}
			$ban = $xsp->getNextElement('ban', $nothing, $cdata);
		}	
		$report .= "<li>Imported $bans new bans</li>";

		// start adding items
		$xsp->reset();
		$items = 0; $karmavotes = 0; $comments = 0;
		$authors = new MEMBER();
		$pref_convertbreaks = $blog->convertBreaks;
		$blog->setConvertBreaks(0);
		$blog->writeSettings;
		$item = $xsp->getNextElement('item', $attribs, $cdata);
		while (strpos($item, 'ERROR') !== 0) {
			$itemelements = XSP::createMemoryParser($item);
			$itemelements->reset();
			$itemelements->getNextElement('itemcat', $nothing, $icat);
			$itemelements->reset();
			$itemelements->getNextElement('title', $nothing, $title);
			$itemelements->reset();
			$itemelements->getNextElement('body', $nothing, $body);
			$itemelements->reset();
			$itemelements->getNextElement('more', $nothing, $more);
 			if ($title || $body || $more) {
				$catid = $blog->getCategoryIdFromName($icat);
				$authors->readFromName($attribs['author']);
				$authorid = $authors->getID();
				if ((!$authorid) || (!$authors->isTeamMember($blogid))) {
					$authorid = $member->getID();
				}
 				$itemid = $blog->additem($catid, $title, $body, $more, $blogid, 
					$authorid, strtotime($attribs['datetime']), $attribs['closed'], 
					$attribs['draft']);
				$items++;
				unset($nothing);

				// add karma votes for this item
				$itemelements->reset();
				$karmavote = $itemelements->getNextElement('karmavote', $ip, $nothing);
				while (strpos($karmavote, 'ERROR') !== 0) {
					$query = 'SELECT * FROM '.sql_table('karma')
						. ' WHERE itemid='.$itemid.' and ip="'.$ip['ip'].'"';
					$res = sql_query($query);
					if (!mysql_num_rows($res)) {
						$query = 'INSERT INTO '.sql_table('karma').' (itemid, ip)'
							. ' VALUES ('.$itemid.",'".$ip['ip']."')";
						sql_query($query);
						$karmavotes++;
					}
					$karmavote = $itemelements->getNextElement('karmavote', $ip, $nothing);
				}
				
				// add trackbacks for this item
				if ($trackback) {
					$itemelements->reset();
					$trackback = $itemelements->getNextElement('trackback', $attribs, $nothing);
					while (strpos($trackback, 'ERROR') !== 0) {
						$tbinfo = XSP::createMemoryParser($trackback);
						$tbinfo->reset();
						$tbinfo->getNextElement('tburl', $nothing, $tburl);
						$tbinfo->reset();
						$tbinfo->getNextElement('tbtitle', $nothing, $tbtitle);
						$tbinfo->reset();
						$tbinfo->getNextElement('tbexcerpt', $nothing, $tbexcerpt);
						$tbinfo->reset();
						$tbinfo->getNextElement('tbblogname', $nothing, $tbblogname);
						$tbinfo->reset();
						$tbinfo->getNextElement('tbtimestamp', $nothing, $tbtimestamp);
						$query = 'INSERT INTO '.sql_table('plugin_tb')
							. ' (tb_id, url, title, excerpt, blog_name, timestamp) VALUES ('
							. $itemid.', "'.addslashes($tburl).'", "'.addslashes($tbtitle).'", "'
							. addslashes($tbexcerpt).'", "'.addslashes($tbblogname).'", "'.$tbtimestamp.'")';
						sql_query($query);
						$trackbacks++;
						$trackback = $itemelements->getNextElement('trackback', $attribs, $nothing);
					}
				}

				// add comments for this item
				$itemelements->reset();
				$comment = $itemelements->getNextElement('comment', $attribs, $nothing);
				while (strpos($comment, 'ERROR') !== 0) {
					$commentinfo = XSP::createMemoryParser($comment);
					$commentinfo->reset();
					$commentinfo->getNextElement('user', $nothing, $cuser);
					$commentinfo->reset();
					$commentinfo->getNextElement('mail', $nothing, $cmail);
					$commentinfo->reset();
					$commentinfo->getNextElement('body', $nothing, $cbody);
					$commentinfo->cleanup;
					unset($commentinfo);
 					if ($cbody) {
						$authors->readFromName($attribs['member']);
						$authorid = $authors->getID();
						if ($authorid == "") $authorid = "''";  // non-member authors return blank
						$query = 'INSERT INTO '.sql_table('comment')
							. ' (CUSER, CMAIL, CMEMBER, CBODY, CITEM, CTIME, CHOST, CIP, CBLOG)'
							. " VALUES ('$cuser', '$cmail', $authorid, '".addslashes($cbody)."', $itemid,"
							. " '".strtotime($attribs['datetime'])."', '".$attribs['host']."',"
							. " '".$attribs['ip']."', '$blogid')";
						sql_query($query);
						$comments++;
					}
					$comment = $itemelements->getNextElement('comment', $attribs, $nothing);
				}
			}
			$item = $xsp->getNextElement('item', $attribs, $cdata);
		}
		$blog->setConvertBreaks($pref_convertbreaks);
		$blog->writeSettings;
		$report .= "<li>Imported $items items with $comments comments";
		if ($trackback) $report .= ", $trackbacks trackbacks,";
		$report .= " and $karmavotes karma votes</li></ul>";
		blogieoverview($report);
	}

?>