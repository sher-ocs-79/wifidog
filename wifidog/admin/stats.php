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
 * @author     Philippe April
 * @copyright  2005-2006 Philippe April
 * @version    Subversion $Id$
 * @link       http://www.wifidog.org/
 */

/**
 * Load common include file
 */
require_once('admin_common.php');

require_once('classes/MainUI.php');
require_once('classes/Utils.php');
require_once('classes/Statistics.php');

$current_user = User :: getCurrentUser();
$db = AbstractDb::getObject(); 

$statistics = new Statistics();
$statistics->processAdminUI();

try
{
	if (!empty($_REQUEST['selected_nodes'])&& count($_REQUEST['selected_nodes']) == 1)
	{
		$node_id = $db->escapeString($_REQUEST['selected_nodes'][0]);
		$nodeObject = Node :: getObject($node_id);
		$stats_title = _("Connections at")." '".$nodeObject->getName()."'";
	}
	else if (isset ($_REQUEST['user_id']))
	{
		$user_id = $db->escapeString($_REQUEST["user_id"]);
		$userObject = User :: getObject($user_id);
		$stats_title = _("User information for")." '".$userObject->getUsername()."'";
	}
	elseif (isset ($_REQUEST['user_mac']))
	{
		$user_mac = $db->escapeString($_REQUEST["user_mac"]);
		$stats_title = _("Connections from MAC")." '".$user_mac."'";
	}
	elseif (isset ($_REQUEST['network_id']))
	{
		$network_id = $db->escapeString($_REQUEST["network_id"]);
		$networkObject = Network :: getObject($network_id);
		$stats_title = _("Network information for")." '".$networkObject->getName()."'";
	}
	elseif (isset($_REQUEST['file']) && isset($_REQUEST['type'])) {
	    $filename = $_REQUEST['file'];
	    $type = $_REQUEST['type'];
	    if (User :: getCurrentUser()->DEPRECATEDisSuperAdmin())  {
    	    // The file is valid for one hour, because it contains sensitive data and we don't want to open a security breach
    	    if (file_exists($filename) && (filectime($filename) > (time() - 60*60)) ) {
    	        header('Content-Type: application/octet-stream');
              header('Content-Disposition: inline; filename="anonymised_'.$type.'.sql"');
              header("Content-Transfer-Encoding: binary");
              header("Pragma: no-cache");
              header("Expires: 0");
              $fp=fopen($filename,"r");
              print fread($fp,filesize($filename));
              fclose($fp);
              exit();
    	    } else {
    	        if (!file_exists($filename)) {
    	            throw new Exception(sprintf(_("File %s does not exist"),  $filename));
    	        }
    	        if (filectime($filename) > (time() - 60*60)) {
    	            throw new Exception(sprintf(_("The statistics file for anonymised_%s.sql has expired."), $type));
    	        }
    	    }
	    } else {
	        throw new Exception(_("These reports are only available to server administrators."));
	    }
	}
	else {
	    $stats_title = null;
	}
    
	$html = '';
if($stats_title){
		$html .= "<h2>{$stats_title}</h2>";
}
	$html .= "<form name='stats_form'>";
	$html .= $statistics->getAdminUI();
	$html .= "<input type='hidden' name='action' value='generate'>";

	$html .= "<input type='submit' value='"._("Generate statistics")."'>";
	$html .= "<hr>";
	$html .= $statistics->getReportUI();
    $html .= "</form>";
}
catch (exception $e)
{
	$html = "<p class='error'>";
	$html .= $e->getMessage();
	$html .= "</p>";
}
$ui = MainUI::getObject();
$ui->setTitle($stats_title);
$ui->addContent('main_area_middle', $html);
$ui->display();

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */
?>