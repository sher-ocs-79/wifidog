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
 * @subpackage Authenticators
 * @author     Benoit Grégoire <bock@step.polymtl.ca>
 * @author     Max Horváth <max.horvath@freenet.de>
 * @copyright  2005-2006 Benoit Grégoire, Technologies Coeus inc.
 * @copyright  2006 Max Horváth, Horvath Web Consulting
 * @version    Subversion $Id$
 * @link       http://www.wifidog.org/
 */

/**
 * Load Network class
 */
require_once('classes/Network.php');
require_once('classes/Node.php');
require_once('classes/Session.php');
require_once('classes/User.php');

/**
 * Abstract class to represent an authentication source
 *
 * @package    WiFiDogAuthServer
 * @subpackage Authenticators
 * @author     Benoit Grégoire <bock@step.polymtl.ca>
 * @author     Max Horváth <max.horvath@freenet.de>
 * @copyright  2005-2006 Benoit Grégoire, Technologies Coeus inc.
 * @copyright  2006 Max Horváth, Horvath Web Consulting
 */
abstract class Authenticator
{
    /**
     * Object of current network
     */
    private $_network;
    static private $_loginLastError = null;
    /**
     * Constructor
     *
     * @param string $network_id Id of network
     *
     * @return void
     */
    public function __construct($network_id)
    {
        $this->_network = Network::getObject($network_id);
    }

    /**
     * Returns object of current network
     *
     * @return object Object of current network
     */
    public function getNetwork()
    {
        return $this->_network;
    }

    /**
     * Attempts to login a user against the authentication source
     *
     * If successfull, returns a User object and must call User::setCurrentUser($user) at the end
     */
    public function login()
    {
        // Must be defined in child class
    }

    /** Recursively converts any array to a chain of hidden form inputs
     * @param $array The array to be converted */
    static private function ArrayToHiddenInput($array) {
        $retval = '';
        if ($array != null) {
            foreach ($_POST as $key => $value) {
                if (is_array($value)) /* If the parameter is an array itself */ {
                    foreach ($value as $array_element) {
                        $retval .= "<input type='hidden' name='" . $key . "[]' value='$array_element'>\n";
                    }
                }
                else {
                    $retval .= "<input type='hidden' name='$key' value='$value'>\n";
                }
            }
        }
        return $retval;
    }
    /**
     * Get the login interface
     *  @param string $userData=null Array of contextual data optionally sent to the method.
     *  The function must still function if none of it is present.
     *
     *      * This method understands:
     *  $userData['preSelectedUser'] An optional User object.
     * @return HTML markup
     */
    static public function getLoginUI($userData=null)
    {
        require_once('classes/SmartyWifidog.php');
        $networkUserData = null;
        if(!empty($userData['preSelectedUser'])){
            $selectedUser=$userData['preSelectedUser'];
            $networkUserData['preSelectedObject']=$selectedUser;
        }
        else {
            $selectedUser=null;
        }

        $smarty=SmartyWiFiDog::getObject();
        // Set network selector
        $network_array = Network::getAllNetworks();
        $default_network = Network::getDefaultNetwork();
 
        foreach ($network_array as $network) {
                if ($network->getName() == $default_network->getName())
                        $default_network_param = $network->getId();
        }
        if (Server::getServer()->getUseGlobalUserAccounts())
            $smarty->assign('selectNetworkUI', "<input type=\"hidden\" name=\"auth_source\" value='$default_network_param' />");
        else
            $smarty->assign('selectNetworkUI', Network::getSelectUI('auth_source', $networkUserData));

        // Set user details
        $smarty->assign('user_id', $selectedUser ? $selectedUser->getId() : "");
        $smarty->assign('username', $selectedUser ? $selectedUser->getUsername() : "");

        // Set error message
        $smarty->assign('error', self::$_loginLastError);

        // Check if one of the network allow signup
        $network_array=Network::getAllNetworks();

        $networksAllowingSignup = null;
        foreach ($network_array as $network) {
            if ($network->getAuthenticator()->isRegistrationPermitted()) {
                $networksAllowingSignup[] = $network;
            }
        }
        //pretty_print_r($networksAllowingSignup);
        if (count($networksAllowingSignup)>0){
            //FIXME:  This is far from ideal, it assumes that all networks use the same signup URL, or that only one network allows signup.  
            $smarty->assign('signupUrl', $networksAllowingSignup[0]->getAuthenticator()->getSignupUrl());
        }
        // Compile HTML code
        $html = self::ArrayToHiddenInput($_POST);//This must remain BEFORE the actual form. It allws repeating the request if the login attempt is causes by a session timeout or insufficient permissions.
        $html .= $smarty->fetch("templates/classes/Authenticator_getLoginForm.tpl");
        return $html;
    }

    /**
     * Process the login interface
     */
    static public function processLoginUI(&$errmsg = null)
    {
        if (!empty($_REQUEST["login_form_submit"])) {
            if (isset($_REQUEST["user_id"])) {
                $username = User::getObject($_REQUEST["user_id"])->getUsername();
            }
            else if (isset($_REQUEST["username"])) {
                $username = $_REQUEST["username"];
            }

            if (isset($_REQUEST["password"])) {
                $password = $_REQUEST["password"];
            }

            // Authenticating the user through the selected auth source.
            $network = Network::processSelectUI('auth_source');
            $user = $network->getAuthenticator()->login($username, $password, $errmsg);
            self::$_loginLastError = $errmsg;
        }
    }
    /**
     * Logs out the user
     *
     * @param string $conn_id The connection id for the connection to work on.
     *                        If  it is not present, the behaviour depends if
     *                        the network supports multiple logins. If it does
     *                        not, all connections associated with the current
     *                        user will be destroyed. If it does, only the
     *                        connections tied to the current node will be
     *                        destroyed.
     *
     * @return void
     */
    public function logout($conn_id = null)
    {

        $db = AbstractDb::getObject();
        $session = Session::getObject();

        $conn_id = $db->escapeString($conn_id);

        if (!empty ($conn_id)) {
            $db->execSqlUniqueRes("SELECT CURRENT_TIMESTAMP, *, CASE WHEN ((CURRENT_TIMESTAMP - reg_date) > networks.validation_grace_time) THEN true ELSE false END AS validation_grace_time_expired FROM connections JOIN users ON (users.user_id=connections.user_id) JOIN networks ON (users.account_origin = networks.network_id) WHERE connections.conn_id='$conn_id'", $info, false);

            $user = User::getObject($info['user_id']);
            $network = $user->getNetwork();
            $splash_user_id = $network->getSplashOnlyUser()->getId();
            $this->acctStop($conn_id);
        } else {
            $user = User::getCurrentUser();
            $network = $user->getNetwork();
            $splash_user_id = $network->getSplashOnlyUser()->getId();

            if ($splash_user_id != $user->getId() && $node = Node::getCurrentNode()) {
                // Try to destroy all connections tied to the current node
                $sql = "SELECT conn_id FROM connections JOIN tokens USING (token_id) WHERE user_id = '{$user->getId()}' AND node_id='{$node->getId()}' AND token_status='".TOKEN_INUSE."';";
                $conn_rows = null;
                $db->execSql($sql, $conn_rows, false);

                if ($conn_rows) {
                    foreach ($conn_rows as $conn_row) {
                        $this->acctStop($conn_row['conn_id']);
                    }
                }
            }
        }

        if ($splash_user_id != $user->getId() && $network->getMultipleLoginAllowed() === false) {
            /*
             * The user isn't the splash_only user and the network config does
             * not allow multiple logins. Logging in with a new token implies
             * that all other active tokens should expire
             */
            $sql = "SELECT conn_id FROM connections JOIN tokens USING (token_id) WHERE user_id = '{$user->getId()}' AND token_status='".TOKEN_INUSE."';";
            $conn_rows = null;
            $db->execSql($sql, $conn_rows, false);

            if ($conn_rows) {
                foreach ($conn_rows as $conn_row) {
                    $this->acctStop($conn_row['conn_id']);
                }
            }
        }

        // Try to destroy current session
        // TODO:  This will not work if ultimately called from the gateway (ex: after abuse control was reached).  This creates a UI problem (the portal still shows the user as connected)
        if (method_exists($session, "destroy")) {
            $session->destroy();
        }
    }

    /**
     * Start accounting traffic for the user
     *
     * @param string $conn_id The connection id for the connection to work on
     *
     * @return void
     */
    public function acctStart($conn_id)
    {

        $db = AbstractDb::getObject();

        $conn_id = $db->escapeString($conn_id);
        $db->execSqlUniqueRes("SELECT CURRENT_TIMESTAMP, *, CASE WHEN ((CURRENT_TIMESTAMP - reg_date) > networks.validation_grace_time) THEN true ELSE false END AS validation_grace_time_expired FROM connections JOIN users ON (users.user_id=connections.user_id) JOIN networks ON (users.account_origin = networks.network_id) WHERE connections.conn_id='$conn_id'", $info, false);
        $network = Network::getObject($info['network_id']);
        $splash_user_id = $network->getSplashOnlyUser()->getId();
        $auth_response = $info['account_status'];

        // Login the user
        $mac = $db->escapeString($_REQUEST['mac']);
        $ip = $db->escapeString($_REQUEST['ip']);
        $sql = "BEGIN;\n";
        $sql .= "UPDATE connections SET user_mac='$mac',user_ip='$ip',last_updated=CURRENT_TIMESTAMP WHERE conn_id='{$conn_id}';";
        $sql .= "UPDATE tokens SET token_status='".TOKEN_INUSE."' FROM connections WHERE connections.token_id=tokens.token_id AND conn_id='{$conn_id}';";
        $sql .= "COMMIT;\n";
        
        $db->execSqlUpdate($sql, false);

        if ($splash_user_id != $info['user_id'] && $network->getMultipleLoginAllowed() === false) {
            /*
             * The user isn't the splash_only user and the network config does
             * not allow multiple logins. Logging in with a new token implies
             * that all other active tokens should expire
             */
            $token = $db->escapeString($_REQUEST['token']);
            $sql = "SELECT * FROM connections JOIN tokens USING (token_id) WHERE user_id = '{$info['user_id']}' AND token_status='".TOKEN_INUSE."' AND token_id!='$token';";
            $conn_rows = array ();
            $db->execSql($sql, $conn_rows, false);

            if (isset ($conn_rows)) {
                foreach ($conn_rows as $conn_row) {
                    $this->acctStop($conn_row['conn_id']);
                }
            }
        }
    }

    /**
     * Update traffic counters
     *
     * @param string $conn_id  The connection id for the connection to work on
     * @param int    $incoming Incoming traffic in bytes
     * @param int    $outgoing Outgoing traffic in bytes
     *
     * @return void
     */
    public function acctUpdate($conn_id, $incoming, $outgoing)
    {

        $db = AbstractDb::getObject();

        // Write traffic counters to database
        $conn_id = $db->escapeString($conn_id);
        $db->execSqlUpdate("UPDATE connections SET "."incoming='$incoming',"."outgoing='$outgoing',"."last_updated=CURRENT_TIMESTAMP "."WHERE conn_id='{$conn_id}'");
    }

    /**
     * Final update and stop accounting
     *
     * @param string $conn_id The connection id (the token id) for the
     *                        connection to work on
     *
     * @return void
     * */
    public function acctStop($conn_id)
    {

        $db = AbstractDb::getObject();

        // Stop traffic counters update
        $conn_id = $db->escapeString($conn_id);
        $sql = "UPDATE connections SET timestamp_out=CURRENT_TIMESTAMP WHERE conn_id='{$conn_id}';\n";
        $sql .= "UPDATE tokens SET token_status='".TOKEN_USED."' FROM connections WHERE connections.token_id=tokens.token_id AND conn_id='{$conn_id}';\n";
        
        $db->execSqlUpdate($sql, false);
    }

    /**
     * Property method that tells if the class allows registration
     *
     * @return bool Returns if the class allows registration
     */
    final public function isRegistrationPermitted()
    {
        return $this->getSignupUrl()?true:false;
    }
    /**
     * If the authenticator allows new users to register new account, this must return the URL at which they must do so.
     *
     * @return text The URL to register at or NULL.  NULL means that the Authenticator does not allow new users to self-register.
     */
    public function getSignupUrl()
    {
        return null;
    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

