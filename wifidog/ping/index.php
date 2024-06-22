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
 * This will respond to the gateway to tell them that the gateway is still up,
 * and also log the gateway checking in for network monitoring
 *
 * @package    WiFiDogAuthServer
 * @author     Alexandre Carmel-Veilleux <acv@acv.ca>
 * @copyright  2004-2006 Alexandre Carmel-Veilleux
 * @version    Subversion $Id$
 * @link       http://www.wifidog.org/
 */

/**
 * Load required files
 */
require_once('../include/common.php');

echo "Pong";
$db = AbstractDb::getObject();
$gw_id = $db->escapeString($_REQUEST['gw_id']);
!empty($_REQUEST['sys_uptime'])?$sysUptimeSql = ", last_heartbeat_sys_uptime=".$db->escapeString($_REQUEST['sys_uptime']):$sysUptimeSql=", last_heartbeat_sys_uptime=NULL";
!empty($_REQUEST['sys_memfree'])?$sysMemfreeSql = ", last_heartbeat_sys_memfree=".$db->escapeString($_REQUEST['sys_memfree']):$sysMemfreeSql=", last_heartbeat_sys_memfree=NULL";
!empty($_REQUEST['sys_load'])?$sysLoadSql = ", last_heartbeat_sys_load=".$db->escapeString($_REQUEST['sys_load']):$sysLoadSql=", last_heartbeat_sys_load=NULL";
!empty($_REQUEST['wifidog_uptime'])?$wifidogUptimeSql = ", last_heartbeat_wifidog_uptime=".$db->escapeString($_REQUEST['wifidog_uptime']):$wifidogUptimeSql=", last_heartbeat_wifidog_uptime=NULL";

$user_agent =  $db->escapeString($_SERVER['HTTP_USER_AGENT']);
$db->execSqlUpdate("UPDATE nodes SET last_heartbeat_ip='$_SERVER[REMOTE_ADDR]', last_heartbeat_timestamp=CURRENT_TIMESTAMP, last_heartbeat_user_agent='$user_agent' $sysUptimeSql $sysMemfreeSql $sysLoadSql $wifidogUptimeSql WHERE gw_id='$gw_id'");

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>
