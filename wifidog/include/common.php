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
 * @author     Benoit Grégoire <benoitg@coeus.ca>
 * @copyright  2004-2006 Benoit Grégoire, Technologies Coeus inc.
 * @version    Subversion $Id$
 * @link       http://www.wifidog.org/
 */

/**
 * Include PHP initialization file file
 */
require_once ('init_php.php');

/**
 * Include configuration file
 */

function cmnSearchParentDirectories($dirname, $searchfor)
{
    $pieces = explode(DIRECTORY_SEPARATOR, $dirname);
    $is_absolute = substr($dirname, 0, 1) === DIRECTORY_SEPARATOR ? 1 : 0;

    for ($i = count($pieces); $i > $is_absolute; $i--) {
        $filename = implode(DIRECTORY_SEPARATOR, array_merge(array_slice($pieces, 0, $i), array ($searchfor)));

        if (file_exists($filename)) {
            return $filename;
        }
    }

    return false;
}

function cmnRequireConfig($config_file = 'config.php')
{
    $config_path = cmnSearchParentDirectories(dirname(__FILE__), $config_file);

    if (!empty ($config_path)) {
        require_once ($config_path);
    }
}

cmnRequireConfig();

/**
 * Include path detection code
 */
require_once ('path_defines_base.php');


/** Include custom exceptions.  Note that the custom exception handler is in classes/MainUI.php */
require_once ('classes/Exceptions/SecurityException.php');

/**
 * Load required classes
 */
require_once ('classes/AbstractDb.php');
AbstractDb::getObject();//This is here so we get reliable SQL vs PHP profiling

require_once ('classes/Locale.php');
require_once ('classes/Dependency.php');


if (EVENT_LOGGING == true) {
    require_once ('classes/EventLogging.php');
    EventLogging :: SetupErrorHandling("strict~/var:\sDeprecated/(off)", array (
    'print' => new PrintChannel(new HTMLFormatter(), 'warning,notice', null, true), 'debug' => new PrintChannel(new HTMLCommentsFormatter(), '=debug', null, false)));
}

/**
 * Filter super globals
 */
undo_magic_quotes();

/**
 * Set default timezone
 */
dateFix();

/**
 * Set the URL paths, but only if we are NOT called from the command line
 */
if (defined('SYSTEM_PATH')) {
    require_once ('path_defines_url_content.php');
}

/* Constant shared with the gateway
 * NEVER edit these, as they mush match the C code of the gateway */
define('ACCOUNT_STATUS_ERROR', -1);
define('ACCOUNT_STATUS_DENIED', 0);
define('ACCOUNT_STATUS_ALLOWED', 1);
define('ACCOUNT_STATUS_VALIDATION', 5);
define('ACCOUNT_STATUS_VALIDATION_FAILED', 6);
define('ACCOUNT_STATUS_LOCKED', 254);

$account_status_to_text[ACCOUNT_STATUS_ERROR] = "Error";
$account_status_to_text[ACCOUNT_STATUS_DENIED] = "Denied";
$account_status_to_text[ACCOUNT_STATUS_ALLOWED] = "Allowed";
$account_status_to_text[ACCOUNT_STATUS_VALIDATION] = "Validation";
$account_status_to_text[ACCOUNT_STATUS_VALIDATION_FAILED] = "Validation Failed";
$account_status_to_text[ACCOUNT_STATUS_LOCKED] = "Locked";

define('TOKEN_UNUSED', 'UNUSED');
define('TOKEN_INUSE', 'INUSE');
define('TOKEN_USED', 'USED');

$token_to_text[TOKEN_UNUSED] = _("Unused");
$token_to_text[TOKEN_INUSE] = _("In use");
$token_to_text[TOKEN_USED] = _("Used");

define('STAGE_LOGIN', "login");
define('STAGE_LOGOUT', "logout");
define('STAGE_COUNTERS', "counters");

define('ONLINE_STATUS_ONLINE', 1);
define('ONLINE_STATUS_OFFLINE', 2);

/* End Constant shared with the gateway*/

/* Other constants */
define('LOGOUT_REASON_GARBAGE_COLLECTED', 3);
define('LOGOUT_REASON_UNKNOWN', 0);
/* End of Other constants */

/* defin error codes */
define ('ERR_UNKNOWN_USERNAME', 20001);
define ('ERR_WRONG_PASSWORD', 20002);
define ('ERR_NO_USERNAME', 20003);
define ('ERR_VALIDATION_EXPIRED', 20004);
define ('ERR_ACCOUNT_INVALID', 20005);

function getErrorText($errorCode) {
    switch ($errorCode) {
        case ERR_UNKNOWN_USERNAME: $text = _('Unknown username or email'); break;
        case ERR_WRONG_PASSWORD: $text = _('Incorrect password (Maybe you have CAPS LOCK on?)'); break;
        case ERR_NO_USERNAME: $text = _("Fatal error:  Username cannot be empty"); break;
        case ERR_VALIDATION_EXPIRED: $text = _("Sorry, your %.0f minutes grace period to retrieve your email and validate your account has now expired. You will have to connect to the internet and validate your account from another location."); break;
        case ERR_ACCOUNT_INVALID: $text = _("Sorry, your account is not valid: "); break;
        default: $text = $errorCode; break;
    }
    return $text;
}
/* End error code definitions */

/** Convert a password hash form a NoCat passwd file into the same format as get_password_hash().
 * @return The 32 character hash.
 */
function convert_nocat_password_hash($hash) {
    return $hash . '==';
}

function cmp_query_time($a, $b) {
    if ($a['total_time'] == $b['total_time']) {
        return 0;
    }
    return ($a['total_time'] < $b['total_time']) ? -1 : 1;
}

/** Cleanup dangling tokens and connections from the database, left if a gateway crashed, etc. */
function garbage_collect() {
    $db = AbstractDb::getObject();

    // 10 minutes
    $expiration = '10 minutes';
    $sql = null;
    $sql .= "BEGIN;\n"; 
    $sql .= "UPDATE connections SET logout_reason=" . LOGOUT_REASON_GARBAGE_COLLECTED . ", timestamp_out=(CURRENT_TIMESTAMP - interval '$expiration') FROM tokens WHERE connections.token_id=tokens.token_id AND last_updated < (CURRENT_TIMESTAMP - interval '$expiration') AND token_status = '" . TOKEN_INUSE . "';";
    $sql .= "UPDATE tokens SET token_status='" . TOKEN_USED . "' FROM connections WHERE connections.token_id=tokens.token_id AND last_updated < (CURRENT_TIMESTAMP - interval '$expiration') AND token_status = '" . TOKEN_INUSE . "';";
    $sql .= "COMMIT;\n";
    $db->execSqlUpdate($sql, false);
}

/** Return a 32 byte guid valid for database use */
function get_guid() {
    return md5(uniqid(rand(), true));
}

/** like the php function print_r(), but the way it was meant to be... */
function pretty_print_r($param) {
    echo "\n<pre>\n";
    print_r($param);
    echo "\n</pre>\n";
}

/** pop directory path */
function cmnPopDir($dirname = null, $popcount = 1) {
    if (empty ($dirname))
    $dirname = dirname($_SERVER['PHP_SELF']);
    if ($dirname === DIRECTORY_SEPARATOR)
    return DIRECTORY_SEPARATOR;
    if (substr($dirname, -1, 1) === DIRECTORY_SEPARATOR)
    $popcount++;

    $popped = implode(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, $dirname), 0, - $popcount));

    return empty ($popped) ? DIRECTORY_SEPARATOR : substr($popped, -1, 1) === DIRECTORY_SEPARATOR ? $popped : $popped . DIRECTORY_SEPARATOR;
}

function cmnDirectorySlash($dirname) {
    return empty ($dirname) ? DIRECTORY_SEPARATOR : substr($dirname, -1, 1) === DIRECTORY_SEPARATOR ? $dirname : $dirname . DIRECTORY_SEPARATOR;
}

/** join file path pieces together */
function cmnJoinPath() {
    $fullpath = '';

    //$arguments = func_get_args();

    for ($i = 0; $i < func_num_args(); $i++) {
        $pathelement = func_get_arg($i);
        if ($pathelement == '')
        continue;

        if ($fullpath == '')
        $fullpath = $pathelement;
        elseif (substr($fullpath, -1, 1) == DIRECTORY_SEPARATOR) {
            if (substr($pathelement, 0, 1) == DIRECTORY_SEPARATOR)
            $fullpath .= substr($pathelement, 1);
            else
            $fullpath .= $pathelement;
        } else {
            if (substr($pathelement, 0, 1) == DIRECTORY_SEPARATOR)
            $fullpath .= $pathelement;
            else
            $fullpath .= DIRECTORY_SEPARATOR . $pathelement;
        }
    }

    return $fullpath;
}

/** find a named file in the include path */
function cmnFindPackage($rel_path, $private = false) {

    $paths = isset ($private) && ($private === true || $private === 'PRIVATE') ? array (
    WIFIDOG_ABS_FILE_PATH
    ) : explode(PATH_SEPARATOR, get_include_path());

    foreach ($paths as $topdir) {
        $package = cmnJoinPath($topdir, $rel_path);
        if (file_exists($package)) {
            if ($private)
            return $package;
            else
            return $rel_path;
        }
    }

    return false; // package was not found
}

/** require_once a named file */
function cmnRequirePackage($rel_path, $private = false) {

    $paths = isset ($private) && ($private === true || $private === 'PRIVATE') ? array (
    WIFIDOG_ABS_FILE_PATH
    ) : explode(PATH_SEPARATOR, get_include_path());

    foreach ($paths as $topdir) {
        $package = cmnJoinPath($topdir, $rel_path);
        if (file_exists($package)) {
            if ($private)
            @ require_once $package;
            else
            @ require_once $rel_path;

            return true; // package was found
        }
    }

    return false; // package was not found
}

/** include_once a named file */
function cmnIncludePackage($rel_path, $private = false) {

    $paths = isset ($private) && ($private === true || $private === 'PRIVATE') ? array (
    WIFIDOG_ABS_FILE_PATH
    ) : explode(PATH_SEPARATOR, get_include_path());

    foreach ($paths as $topdir) {
        $package = cmnJoinPath($topdir, $rel_path);
        if (file_exists($package)) {
            if ($private)
            @ include_once $package;
            else
            @ include_once $rel_path;

            return true; // package was found
        }
    }

    return false; // package was not found
}

if (defined("EVENT_LOGGING") && EVENT_LOGGING == true) {
    class WifidogSyslogFormatter extends EventFormatter {
        public function formatEvent($event, $info = null) {
            $dt = date("Y-m-d H:i:s (T)", $event->getTimestamp());

            $myFilename = $event->getFilename();
            $myLinenum = $event->getLinenum();

            // Get information about node
            $myCurrentNode = Node :: getCurrentNode();
            if (empty ($myCurrentNode))
            $myNodeName = '*nonode*';
            else
            $myNodeName = $myCurrentNode->getName();

            // Get information about network
            $myNetwork = Network :: getCurrentNetwork();
            if (empty ($myNetwork))
            $myNetworkName = '*nonetwork*';
            else
            $myNetworkName = $myNetwork->getName();

            // Get information about user
            $myCurrentUser = User :: getCurrentUser();
            if (empty ($myCurrentUser))
            $myUserName = '*nouser*';
            else
            $myUserName = $myCurrentUser->getUsername();

            $string = "$dt " . EventObject :: PrettyErrorType($event->getLayoutType()) . " >$myNetworkName >${myUserName}@$myNodeName [" . $_SERVER['REQUEST_URI'] . "]" . ": " . $event->getMessage() . (!empty ($myFilename) ? " in $myFilename" . (!empty ($myLinenum) ? " on line $myLinenum" : "") : "") . "\n";

            if ($event->classifyErrorType() == 'error') {
                $string .= "   Stack Backtrace\n" .
                self :: FormatBacktrace($event->getContext()) .
                "\n";
            }

            return $string;
        }
    }
    $myLogfile = !defined('WIFIDOG_LOGFILE') ? "tmp/wifidog.log" : constant('WIFIDOG_LOGFILE');
    if (!empty ($myLogfile)) {
        if (substr($myLogfile, 0, 1) != '/')
            $myLogfile = WIFIDOG_ABS_FILE_PATH . $myLogfile;

        EventLogging :: stAddChannel(new FileChannel($myLogfile, new WifidogSyslogFormatter(), 'warning,notice'), 'logfile');
    }

// trigger_error("here i am", E_USER_NOTICE);
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */
