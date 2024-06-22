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
 * Configuration file of WiFiDog Authentication Server
 *
 * The configure the WiFiDOG auth server you can either use this configuration
 * file or make a local copy of it named local.config.php.
 *
 * @package    WiFiDogAuthServer
 * @author     Benoit Grégoire <benoitg@coeus.ca>
 * @author     Max Horváth <max.horvath@freenet.de>
 * @copyright  2004-2006 Benoit Grégoire, Technologies Coeus inc.
 * @copyright  2005-2006 Max Horváth, Horvath Web Consulting
 * @version    Subversion $Id$
 * @link       http://www.wifidog.org/
 */

/**
 * In case this is the local.config.php you should remove the next lines.
 */

if (file_exists(dirname(__FILE__) . "/local.config.php")) {
    // Use a local copy of the configuration if found instead of the distro's.
    require dirname(__FILE__) . "/local.config.php";
} else {

/**
 * In case this is the local.config.php stop removing the lines.
 */

/********************************************************************\
 * DATABASE CONFIGURATION                                           *
\********************************************************************/

/**
 * Configuration values needed to access the database.
 * ===================================================
 */

// Host of the database server.
define('CONF_DATABASE_HOST', 'localhost');

// Port of the database server.
define('CONF_DATABASE_PORT', '5432');

// Username for database access.
define('CONF_DATABASE_USER', 'wifidog');

// Password for database access.
define('CONF_DATABASE_PASSWORD', 'wifidogtest');

// Name of database used by WiFiDOG auth server.
define('CONF_DATABASE_NAME', 'wifidog');

/**
 * Database cleanup
 * ================
 *
 * Normally,  the database cleanup routines will be called everytime a portal
 * page is displayed. If you set this to true, you must set a cron job on the
 * server which will execute the script "cron/cleanup.php".
 */
define('CONF_USE_CRON_FOR_DB_CLEANUP', false);

/**
 * SQL queries profiling.  This will output all SQL queries performed to 
 * generate the page, as well as the relative time used by each.
 */

define('LOG_SQL_QUERIES', false);

/**
 * Log content display.  If set to true, every piece of content displayed to a user will
 * be logged in a log table.
 */

define('LOG_CONTENT_DISPLAY', false);

/********************************************************************\
 * WEBSERVER CONFIGURATION                                          *
\********************************************************************/

/**
 * Caching
 * =======
 *
 * Experimental:  If you installed PEAR::Cache_Lite and set this value to true, additional caching
 * will be enabled.
 *
 * If you haven't installed PEAR::Cache_Lite, caching won't be enabled at all.
 */
define('USE_CACHE_LITE', false);

/**
 * Timezone
 * ========
 *
 * Since PHP 5.1.0 date functions have been rewritten and require to set
 * a valid timezone.  This is ONLY used on PHP >=5.1
 *
 * You'll find a list of valid identifiers at:
 * http://www.php.net/manual/en/timezones.php
 */
define('DATE_TIMEZONE', 'Canada/Eastern');

/********************************************************************\
 * WIFIDOG BASIC CONFIGURATION                                      *
\********************************************************************/

/**
 * Custom signup system
 * ====================
 *
 * If you wanto to use a custom signup system instead of the built in signup
 * page uncomment the next line and enter the URL to the system.
 */
//define("CUSTOM_SIGNUP_URL","https://www.bcwireless.net/hotspot/signup.php");

/**
 * Default language
 * ================
 *
 * Define the default language of the WiFiDOG auth server.  The language code
 * (the part before the _) must be part of the array above (the country
 * subcode may differ, and should be set to your country subcode)
 */
define('DEFAULT_LANG', 'fr_CA');

/********************************************************************\
 * WIFIDOG FEATURES CONFIGURATION                                   *
\********************************************************************/

/**
 * Google Maps support
 * ===================
 *
 * Enable Google Maps mapping using "hotspots_map.php".
 */
define('GMAPS_HOTSPOTS_MAP_ENABLED', true);

/********************************************************************\
 * ADVANCED CONFIGURATION                                           *
 *                                                                  *
 * You should normally not have to edit anything below this!        *
\********************************************************************/

/**
 * Path of WiFiDOG auth server installation
 * ========================================
 *
 * SYSTEM_PATH must be set to the url path needed to reach the wifidog
 * directory.
 *
 * Normally '/' or '/wifidog/', depending on where configure your
 * document root.
 *
 * Gateway configuration must match this as well.
 *
 * CONFIGURATION FLAG REQUIRED IF PATH DETECTION FAILS, ONLY!
 */
// define('SYSTEM_PATH', '/');

/**
 * WiFiDOG configuration
 * =====================
 *
 * Name and version of the WiFiDOG auth server.
 */
define('WIFIDOG_NAME', 'WiFiDog Authentication server');
define('WIFIDOG_VERSION', '(Development)');

/**
 * WiFiDOG internals configuration
 * ===============================
 *
 * Internal configuration values for WiFiDog - don't touch!
 */

// Filenames and directories
define('NETWORK_THEME_PACKS_DIR', 'media/network_theme_packs/');
define('STYLESHEET_NAME', 'stylesheet.css');
define('PRINT_STYLESHEET_NAME', 'printer.css');
define('LOGIN_PAGE_NAME', 'login.html');
define('HOTSPOT_STATUS_PAGE', 'hotspot_status.php');
define('NODE_PUBLIC_STATS_DIR', "public_stats/");

// Source URL for the hotspot status page
define('GMAPS_XML_SOURCE_URL', 'hotspot_status.php?format=XML');

//Enable logging by the EventLogging class
define('EVENT_LOGGING',false);
// Declare warning/error/notice logging to a file.
// Set this to false to disable logging to a file.
// By default, logging is enabled, to file tmp/wifidog.log.
// define('WIFIDOG_LOGFILE', 'tmp/wifidog.log');

 /**
  * Email configuration
  * ===============================
  *
  * Internal configuration values for WiFiDog - don't touch!
  */

define('EMAIL_MAILER', 'mail'); // "mail", "sendmail", or "smtp"

// Valid only for SMTP
define('EMAIL_HOST', '');
define('EMAIL_AUTH', false);

// Valid if EMAIL_AUTH is true
define('EMAIL_USERNAME', '');
define('EMAIL_PASSWORD', '');

/**
 * In case this is the local.config.php you should remove the next lines.
 */

}

/**
 * In case this is the local.config.php stop removing the lines.
 */

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>
