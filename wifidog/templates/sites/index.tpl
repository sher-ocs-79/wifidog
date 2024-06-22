{*

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
 * WiFiDog Authentication Server home page
 *
 * @package    WiFiDogAuthServer
 * @subpackage Templates
 * @author     Max Horváth <max.horvath@freenet.de>
 * @copyright  2006 Max Horváth, Horvath Web Consulting
 * @version    Subversion $Id: $
 * @link       http://www.wifidog.org/
 */

*}

{if $sectionMAINCONTENT}
{*
    BEGIN section MAINCONTENT
*}
	<p>
		{if $networkNumValidUsers == 1}
			{"The %s network currently has one valid user."|_|sprintf:$networkName}
		{else}
			{"The %s network currently has %d valid users."|_|sprintf:$networkName:$networkNumValidUsers}
		{/if}

		{if $networkNumOnlineUsers == 1}
			{"One user is currently online."|_|sprintf:$networkNumOnlineUsers}
		{else}
			{"%d users are currently online."|_|sprintf:$networkNumOnlineUsers}
		{/if}
		<br/>
		{if $networkNumDeployedNodes == 1}
        		{"This network currently has 1 deployed hotspot."|_}
        {else}
        		{"This network currently has %d deployed hotspots."|_|sprintf:$networkNumDeployedNodes}
        {/if}

        {if $networkNumOnlineNodes == 1}
            {"One hotspot is currently operational."|_}
        {else}
            {"%d hotspots are currently operational."|_|sprintf:$networkNumOnlineNodes}
        {/if}

        {if $networkNumNonMonitoredNodes > 0}
            {if $networkNumNonMonitoredNodes == 1}
                {"One hotspot isn't monitored so we don't know if it's currently operational."|_}
            {else}
                {"%d hotspots aren't monitored so we don't know if they are currently operational."|_|sprintf:$networkNumNonMonitoredNodes}
            {/if}
        {/if}
    </p>
{*
    END section MAINCONTENT
*}
{/if}
