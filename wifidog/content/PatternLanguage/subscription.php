<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

// +-------------------------------------------------------------------+
// | WiFiDog Authentication Server                                     |
// | =============================                                     |
// |                                                                   |
// | The WiFiDog Authentication Server is part of the WiFiDog captive  |
// | portal suite.                                                     |
// +-------------------------------------------------------------------+
// | PHP version 5 required.                                           |
// +-------------------------------------------------------------------+
// | Homepage:     http://www.wifidog.org/                             |
// | Source Forge: http://sourceforge.net/projects/wifidog/            |
// +-------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or     |
// | modify it under the terms of the GNU General Public License as    |
// | published by the Free Software Foundation; either version 2 of    |
// | the License, or (at your option) any later version.               |
// |                                                                   |
// | This program is distributed in the hope that it will be useful,   |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of    |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the     |
// | GNU General Public License for more details.                      |
// |                                                                   |
// | You should have received a copy of the GNU General Public License |
// | along with this program; if not, contact:                         |
// |                                                                   |
// | Free Software Foundation           Voice:  +1-617-542-5942        |
// | 59 Temple Place - Suite 330        Fax:    +1-617-542-2652        |
// | Boston, MA  02111-1307,  USA       gnu@gnu.org                    |
// |                                                                   |
// +-------------------------------------------------------------------+

/**
 * @package    WiFiDogAuthServer
 * @author     Francois Proulx <francois.proulx@gmail.com>
 * @copyright  2005-2006 Francois Proulx, Technologies Coeus inc.
 * @version    Subversion $Id$
 * @link       http://www.wifidog.org/
 */

/**
 * Load required files
 */
require_once('../../include/common.php');

require_once('classes/User.php');
require_once('classes/Content/PatternLanguage/PatternLanguage.php');
require_once('classes/MainUI.php');
$smarty = SmartyWifidog::getObject();
$session = Session::getObject();
// This trick is done to allow displaying of Pattern Language right away if there is only one available.
if(!empty($_REQUEST['content_id']))
{
    $content_id = $_REQUEST['content_id'];
    $pattern_language = PatternLanguage::getObject($content_id);
}
else
{
    $content_id = "";
    $pattern_languages = PatternLanguage :: getAllContent();
    if(count($pattern_languages) >= 1)
        $pattern_language = $pattern_languages[0];
    else
        exit;
}

// The Pattern Language toolbar
$tool_html = "<h1>{$pattern_language->getTitle()->__toString()}</h1>";
$tool_html .= '<ul class="pattern_language_menu">'."\n";
$node_id = $session->get(SESS_NODE_ID_VAR);
if(!empty($node_id))
    $tool_html .= "<li><a href='/portal/?node_id=$node_id'>"._("Go back to this hotspot portal page")."</a></li>";
$tool_html .= '<li><a href="'.BASE_SSL_PATH.'content/PatternLanguage/index.php?content_id='.$content_id.'">'._("About Pattern Language").'</a><br>'."\n";
$tool_html .= '<li><a href="'.BASE_SSL_PATH.'content/PatternLanguage/narrative.php?content_id='.$content_id.'">'._("Read narrative").'</a><br>'."\n";
$tool_html .= '<li><a href="'.BASE_SSL_PATH.'content/PatternLanguage/archives.php?content_id='.$content_id.'">'._("Archives").'</a><br>'."\n";
$tool_html .= '<li><a href="'.BASE_SSL_PATH.'content/PatternLanguage/hotspots.php?content_id='.$content_id.'">'._("Participating hotspots").'</a><br>'."\n";
$tool_html .= '<li><a href="'.BASE_SSL_PATH.'content/PatternLanguage/subscription.php?content_id='.$content_id.'">'._("Subscription").'</a><br>'."\n";
$tool_html .= '</ul>'."\n";

$tool_html .= "<div class='pattern_language_credits'>";
$tool_html .=  $pattern_language->getSponsorInfo()->__toString();
$tool_html .= "</div>";

// Body
$body_html = "<img src='images/header.gif'>\n";
$body_html .= "<h1>"._("Pattern Language Subscription")."</h1>\n";
$body_html .= "<div class='pattern_language_body'>\n";

$current_user = User::getCurrentUser();
if($current_user)
{
    if(!empty($_REQUEST['subscribe']) || !empty($_REQUEST['unsubscribe']))
    {
        if(!empty($_REQUEST['subscribe']))
        {
            $pattern_language = PatternLanguage::getObject($_REQUEST['content_id']);
            if(!$pattern_language->isUserSubscribed($current_user))
            {
                $pattern_language->subscribe($current_user);
                $body_html .= _("Thank you for subscribing");
                $node_id = $session->get(SESS_NODE_ID_VAR);
                if(!empty($node_id))
                    $body_html .= "<p><a href='/portal/?node_id=$node_id'>"._("Go back to this hotspot portal page")."</a>";
            }
        }
        else if(!empty($_REQUEST['unsubscribe']))
        {
            $pattern_language = PatternLanguage::getObject($_REQUEST['content_id']);
            if($pattern_language->isUserSubscribed($current_user))
                $pattern_language->unsubscribe($current_user);
            $body_html .= _("You are now unsubscribed");
            $node_id = $session->get(SESS_NODE_ID_VAR);
            if(!empty($node_id))
                $body_html .= "<p><a href='/portal/?node_id=$node_id'>"._("Go back to this hotspot portal page")."</a>";
        }
    }
    else
    {
        if(!$pattern_language->isUserSubscribed($current_user))
        {
            // Subscription
            $body_html .= _("Subscribe to Pattern Language by clicking the link below. Once you have subscribed you will receive a fragment of text each time you log in to a participating hotspot in the city of Montreal. These text fragments will accumulate to form a unique narrative for every user. You can read your narrative anytime by clicking on \"Read Narrative\", or you can read the narratives generated by other users by going to \"Archives\".");
            $body_html .= "<br><a href='subscription.php?subscribe=true&content_id={$pattern_language->getId()}'>"._("Subscribe now")."</a>";
        }
        else
        {
            // Unsubscription
            $body_html .= _("You are already subscribed to Pattern Language, you can terminate your participation by clicking below.");
            $body_html .= "<br><a href='subscription.php?unsubscribe=true&content_id={$pattern_language->getId()}'>"._("Unsubscribe now")."</a>";
        }
    }
}
else
{
    $body_html .= _("You must be logged in to subscribe !");
}

$body_html .= "</div>\n";

$ui = MainUI::getObject();
$ui->addContent('left_area_middle', $tool_html);
$ui->setTitle(_("Pattern Language - Subscription"));
$ui->addContent('main_area_middle', $body_html);
$ui->display();

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>
