<?php

class NP_BlogIE extends NucleusPlugin {

    function getName()           { return 'BlogIE'; }
    function getAuthor()         { return 'Jeff MacMichael'; }
    function getURL()            { return 'http://wiki.gednet.com/'; }
    function getVersion()        { return '0.71 beta'; }
    function getDescription()    { return 'Import and export blog data, and export media.'; }
    function getEventList()      { return array('QuickMenu');}
    function hasAdminArea()      { return 1;}
    function supportsFeature($w) { return $w=='SqlTablePrefix';}
    
    function event_QuickMenu(&$data) {
        global $member, $nucleus, $blogid;
        
        // only show to admins
        $isblogadmin = (preg_match('/MD$/', $nucleus['version'])) ? $member->isBlogAdmin(-1) : $member->isBlogAdmin($blogid);
        
        if (!($member->isLoggedIn() && ($member->isAdmin() | $isblogadmin))) return;
        
        $params = array(
                'title' => 'Blog Backup',
                'url' => $this->getAdminURL(),
                'tooltip' => 'Import and export blog items/comments');
        
        array_push($data['options'], $params);
    }

    function install() {
    }
    
    function unInstall() {
    }
}
