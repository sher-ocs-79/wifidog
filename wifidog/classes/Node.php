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
 * @author     Benoit Grégoire <bock@step.polymtl.ca>
 * @copyright  2005-2006 Benoit Grégoire, Technologies Coeus inc.
 * @version    Subversion $Id$
 * @link       http://www.wifidog.org/
 */

/**
 * Load required classes
 */
require_once('classes/User.php');
require_once('classes/GisPoint.php');
require_once('classes/AbstractGeocoder.php');
require_once('classes/Utils.php');
require_once('classes/DateTimeWD.php');
require_once('classes/HotspotGraphElement.php');

/**
 * Abstract a Node.  A Node is an actual physical transmitter.
 *
 * @todo Make all the setter functions no-op if the value is the same as what
 * was already stored Use setCustomPortalReduirectUrl as an example
 *
 * @package    WiFiDogAuthServer
 * @author     Benoit Grégoire <bock@step.polymtl.ca>
 * @copyright  2005 Benoit Grégoire, Technologies Coeus inc.
 */
class Node extends HotspotGraphElement
{
    /** Object cache for the object factory (getObject())*/
    protected $_row;
    protected $mdB; /**< An AbstractDb instance */
    protected $id;
    protected static $currentNode = null;

    /**
     * List of deployment statuses
     *
     * @var array
     * @access private
     */
    protected $_deploymentStatuses = array();

    /**
     * Defines a warning message
     *
     * @var string
     *
     * @access private
     */
    protected $_warningMessage;

    /** Instantiate a node object
     * @param $id The id of the requested node
     * @return a Node object, or null if there was an error
     */
    public static function &getObject($id)
    {
        return HotspotGraphElement::getObject($id, 'Node');
    }

    /** Free an instanciated object
     * @param $id The id to free
     * Thanks and so long for all the ram.
     */
    public static function freeObject($id)
    {
        HotspotGraphElement::freeObject($id, 'Node');
    }

    /** Instantiate a node object using it's gateway id
     * @param $gwId The id of the requested node
     * @return a Node object, or null if there was an error
     */
    static function getObjectByGatewayId($gwId)
    {
        $object = null;
        $object = new self($gwId, 'GATEWAY_ID');
        return $object;
    }
    /** Get the current node for which the portal is displayed or to which a user is physically connected.
     * @param $real_node_only true or false.  If true, the real physical node where the user is connected is returned, and the node set by setCurrentNode is ignored.
     * @return a Node object, or null if it can't be found.
     */
    static function getCurrentNode($real_node_only = false)
    {
        $object = null;
        if (self :: $currentNode != null && $real_node_only == false)
        {
            $object = self :: $currentNode;
        }
        else
        {
            $object = self :: getCurrentRealNode();
        }
        return $object;
    }

    /** Set the current node where the user is to be considered connected to.  (For portal and content display purposes, among other.
     * @param $node Node object or null.  The new current node.
     * @return true	 */
    static function setCurrentNode($node)
    {
        if(empty($node) || $node instanceof Node) {
            self :: $currentNode = $node;
        }
        else {
            throw new Exception(sprintf("Parameter node must be null or of class Node but is of class %s", get_class($node)));
        }
        return true;
    }

    /** Get the current node to which a user is physically connected, if any.  This is done by an IP address lookup against the last reported IP address of the node
     * @param 	 * @return a Node object, or null if it can't be found.
     */
    public static function getCurrentRealNode()
    {
        static $currentRealNode;//For caching
        static $currentRealNodeComputed;//For caching
        $currentIp = $_SERVER['REMOTE_ADDR'];
        //For testing:
        //$currentIp = '24.201.12.219';
        if(!isset($currentRealNodeComputed))
        {
            //echo "getCurrentTealNode(): Computing for IP $currentIp<br/>";
            $currentRealNodeComputed=true;
            $db = AbstractDb::getObject();
            $sql_ip = "SELECT node_id from nodes WHERE last_heartbeat_ip='$currentIp' ORDER BY last_heartbeat_timestamp DESC";
            $node_rows = null;
            $db->execSql($sql_ip, $node_rows, false);
            $num_match = count($node_rows);
            if ($num_match == 0)
            {
                // User is not physically connected to a node
                $currentRealNode = null;
            }
            else if ($num_match == 1)
            {
                // Only a single node matches, the user is presumed to be there
                $currentRealNode = self::getObject($node_rows[0]['node_id']);
            }
            else
            {
                /* We have more than one node matching the IP (the nodes are behind the same NAT).*/
                $currentRealNode = null;
                $current_user = User :: getCurrentUser();
                if ($current_user != null)
                {
                    /* We will try to discriminate by finding which node the user last authenticated against.
                     * If the IP matches, we can be pretty certain the user is there.
                     */
                    $current_user_id = $current_user->getId();
                    $sql = "SELECT node_id, last_heartbeat_ip, name, last_updated from connections NATURAL JOIN nodes WHERE user_id='$current_user_id' AND node_id IN ($sql_ip) ORDER BY last_updated DESC ";
                    //$db->execSql($sql, $tmp, true);
                    $db->execSqlUniqueRes("$sql LIMIT 1", $node_row, false);
                    if ($node_row != null)
                    {
                        $currentRealNode = self::getObject($node_row['node_id']);
                    }
                }
                else {
                    /* Darn, the user doesn't have a session open, we can only take the first node in the list, which is marginaly better than nothing */
                    $currentRealNode = self::getObject($node_rows[0]['node_id']);
                }
            }
        }
        return $currentRealNode;
    }

    public function delete(& $errmsg)
    {
        $retval = false;
        $user = User :: getCurrentUser();
        if ($user->DEPRECATEDisSuperAdmin()) {
            $db = AbstractDb::getObject();
            $id = $db->escapeString($this->getId());
            if (!$db->execSqlUpdate("DELETE FROM nodes WHERE node_id='{$id}'", false))
            {
                $errmsg = _('Could not delete node!');
            }
            else
            {
                parent::_delete($errmsg);
                $retval = true;
            }
        }
        else
        {
            $errmsg = _('Access denied!');
        }

        return $retval;
    }

    /**
     * Create a new Node in the database
     *
     * @param string $gw_id The Id of the gatewqay to be associated with
     * thisnode. If not present, a dummy value will be assigned.
     * @param object $network Network object.  The node's network.  If not
     *                        present, the current Network will be assigned
     *
     * @return mixed The newly created Node object, or null if there was
     *               an error
     *
     * @static
     * @access public
     */
    public static function createNewObject($gw_id = null, $network = null)
    {
        $db = AbstractDb::getObject();
        if (empty ($gw_id)) {
            $gw_id = $db->escapeString(_('PUT_GATEWAY_ID_HERE'));
        }
        else
        {
            $gw_id = $db->escapeString($gw_id);
        }
        $node_id = get_guid();


        if (empty ($network)) {
            $network = Network::getCurrentNetwork();
        }

        $network_id = $db->escapeString($network->getId());

        $node_deployment_status = $db->escapeString("IN_PLANNING");
        $node_name = _("New node");
        $duplicate = null;
        try{
            $duplicate = Node::getObjectByGatewayId($gw_id);
        }
        catch (Exception $e)
        {
        }
        if ($duplicate) {
            throw new Exception(sprintf(_('Sorry, a node for the gateway %s already exists.'),$gw_id));
        }

        $sql = "INSERT INTO nodes (node_id, gw_id, network_id, creation_date, node_deployment_status, name) VALUES ('$node_id', '$gw_id', '$network_id', CURRENT_TIMESTAMP,'$node_deployment_status', '$node_name')";

        if (!$db->execSqlUpdate($sql, false)) {
            throw new Exception(_('Unable to insert new node into database!'));
        }

        HotspotGraphElement::createNewObject($node_id, 'Node', $network);
        
        $object = self::getObject($node_id);

        return $object;
    }

    /** Get an interface to pick a node.
     * @param $user_prefix A identifier provided by the programmer to recognise it's generated html form
     *
     * @param string $userData=null Array of contextual data optionally sent to the method.
     *  The function must still function if none of it is present.
     * This method understands:
     *  $userData['preSelectedObject'] An optional object to pre-select.
     *	$userData['additionalWhere'] Additional SQL conditions for the
     *                                    objects to select
     *  $userData['additionalJoin'] Additional SQL JOIN conditions for the
     *                                    objects to select
     *	$userData['preSelectedObjects'] An optional object or array of objects to pre-select. (not
     * supported by type_interface=table)
     *  $userData['typeInterface'] select, select_multiple or table.  Default is "select"
     *
     * * @return html markup
     */
    public static function getSelectUI($user_prefix, $userData=null)
    {
        !empty($userData['additionalJoin'])?$sql_additional_join=$userData['additionalJoin']:$sql_additional_join=null;
        !empty($userData['additionalWhere'])?$sql_additional_where=$userData['additionalWhere']:$sql_additional_where=null;
        !empty($userData['preSelectedObjects'])?$selectedNodes=$userData['preSelectedObjects']:$selectedNodes=null;
        !empty($userData['typeInterface'])?$type_interface=$userData['typeInterface']:$type_interface="select";

        $db = AbstractDb::getObject();
        $html = '';
        $name = "{$user_prefix}";

        $_deploymentStatuses = array(
        "DEPLOYED" => _("Deployed"),
        "IN_PLANNING" => _("In planning"),
        "IN_TESTING" => _("In testing"),
        "NON_WIFIDOG_NODE" => _("Non-Wifidog node"),
        "PERMANENTLY_CLOSED" => _("Permanently closed"),
        "TEMPORARILY_CLOSED" => _("Temporarily closed")
        );

        $sql = "SELECT nodes.node_id, nodes.name, nodes.gw_id, nodes.node_deployment_status, nodes.is_splash_only_node from nodes $sql_additional_join WHERE 1=1 $sql_additional_where ORDER BY lower(nodes.node_id)";
        $node_rows = null;
        $db->execSql($sql, $node_rows, false);

        if ($node_rows != null) {
            Utils :: natsort2d($node_rows, "name");
            if ($type_interface != "table") {
                $i = 0;
                foreach ($node_rows as $node_row)
                {
                    $tab[$i][0] = $node_row['node_id'];
                    //$tab[$i][1] = sprintf(_("%s (gw: %s)"),$node_row['name'],$node_row['gw_id']);
                    $tab[$i][1] = $node_row['name'];
                    $i ++;
                }
                if($type_interface == "select_multiple"){
                    $select_options="MULTIPLE SIZE=6";
                }
                else
                {
                    $select_options=null;
                }
                //pretty_print_r($selectedNodes);
                if(is_array($selectedNodes)){
                    $selectedPrimaryKey=array();
                    foreach($selectedNodes as $node){
                        $selectedPrimaryKey[]=$node->getId();
                    }

                }
                else if($selectedNodes instanceof Node){
                    $selectedPrimaryKey=$selectedNodes->getId();
                }
                else{
                    $selectedPrimaryKey=null;
                }
                $html .= FormSelectGenerator :: generateFromArray($tab, $selectedPrimaryKey, $name, null, false, null, $select_options);
            } else {
                $html .= "<fieldset>\n    <legend>Node List</legend>\n";
                $html .= "    <span class='node_admin'>"._("Filter:")."<input type=\"text\" tabindex=\"1\" maxlength=\"40\" size=\"40\" id=\"nodes_list_filter\" name=\"nodes_list_filter\" /></span>\n    <br/>\n";
                $html .= "    <!--[if IE]><style type='text/css'>#node_list_div table.scrollable>tbody { height: 15px; }</style><![endif]-->\n";
                $html .= "    <script src='" . BASE_URL_PATH . "js/filtertable.js' type='text/javascript' language='javascript' charset='utf-8'></script>\n";
                $html .= "    <script src='" . BASE_URL_PATH . "js/sorttable.js' type='text/javascript' language='javascript' charset='utf-8'></script>\n";
                $html .= "    <div id='node_list_div' class='node_admin tableContainer'>\n";
                $html .= "        <table id='nodes_list' class='node_admin filterable scrollable sortable'>\n\n";
                $html .= "            <thead class='fixedHeader'>\n";
                $html .= "<tr class='nofilter'>\n";
                $html .= "<th>"._("Node Name")."</th>\n";
                $html .= "<th>"._("Gateway ID")."</th>\n";
                $html .= "<th>"._("Deployment Status")."</th>\n";
                $html .= "</tr>\n";
                $html .= "</thead>\n";
                $html .= "<tbody>";

                $i = 0;
                foreach ($node_rows as $node_row)
                {
                    $href = GENERIC_OBJECT_ADMIN_ABS_HREF."?object_id={$node_row['node_id']}&object_class=Node&action=edit";
                    $_deployStatusNode = $node_row['node_deployment_status'];
                    $html .= "<tr class='row' onclick=\"javascript:location.href='{$href}'\">\n";
                    $html .= "<td>{$node_row['name']}<noscript>(<a href='{$href}'>edit</a>)</noscript></td>\n";
                    $html .= "<td>{$node_row['gw_id']}</td>\n";
                    $html .= "<td>{$_deploymentStatuses[$_deployStatusNode]}</td>\n";
                    $html .= "</tr>\n";
                }
                $html .= "            </tbody>\n        </table>\n";
                $html .= "    </div>\n";
                $html .= "</fieldset>\n";

            }
        } else {
            $html .= "<div class='warningmsg'>"._("Sorry, no nodes available in the database")."</div>\n";
        }
        return $html;
    }


    /** Get the selected Network object.
     * @param $user_prefix A identifier provided by the programmer to recognise it's generated form
     * @return the node object
     */
    static function processSelectUI($user_prefix)
    {
        $object = null;
        $name = "{$user_prefix}";
        return self::getObject($_REQUEST[$name]);
    }

    /** Get an interface to create a new node.
     * @param $network Optional:  The network to which the new node will belong,
     * if absent, the user will be prompted.
     * @return html markup
     */
    public static function getCreateNewObjectUI($network = null)
    {
        $html = '';
        $html .= _("Add a new node for the gateway ID")." \n";
        $name = "new_node_gw_id";
        $html .= "<input type='text' size='10' name='{$name}'>\n";
        if ($network)
        {
            $name = "new_node_network_id";
            $html .= "<input type='hidden' name='{$name}' value='{$network->getId()}'>\n";
        }
        else
        {
            $html .= " "._("in ")." \n";
            $html .= Network :: getSelectUI('new_node');
        }
        return $html;

    }

    /**
     * Process the new object interface.
     *
     * Will return the new object if the user has the credentials and the form was fully filled.
     * @return the node object or null if no new node was created.
     */
    public static function processCreateNewObjectUI()
    {
        // Init values
        $retval = null;
        $name = "new_node_gw_id";

        if (!empty ($_REQUEST[$name])) {
            $gw_id = $_REQUEST[$name];
        }
        else
        {
            $gw_id = null;
        }
        $name = "new_node_network_id";

        if (!empty ($_REQUEST[$name])) {
            $network = Network::getObject($_REQUEST[$name]);
        } else {
            $network = Network::processSelectUI('new_node');
        }

        if ($network) {
            Security::requirePermission(Permission::P('NETWORK_PERM_ADD_NODE'), $network);
            $retval = self::createNewObject($gw_id, $network);
        }

        return $retval;
    }


    /** Get an interface to deal with missing nodes.  If the user has the permissions, he will be asked to create a new node for that gateway id, or assign that gateway id to an existing node.
     * @param $gwId The unknown gwId
     * @return html markup
     */
    public static function getStealOrCreateNewUI($gwId)
    {
        $permissionArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), null);
        $permissionArray[]=array(Permission::P('NODE_PERM_EDIT_GATEWAY_ID'), null);
        Security::requireAnyPermission($permissionArray);
        $db = AbstractDb::getObject();
        $html = '';
        $allowedNetworks = Security::getObjectsWithPermission(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'));
        $allowedNodes = Security::getObjectsWithPermission(Permission::P('NODE_PERM_EDIT_GATEWAY_ID'));
        $html .= "<p>"._("Here is what you can do to fix this:")."</p>\n";
        $html .= "<ul>\n";
        if($allowedNetworks) {
            //Add a new node for unknown node id
            $html .= "<li>".sprintf(_("You can create a new node with %s as it's associated gateway id.  This is typical for new installations."), $gwId)."<br/>\n";

            $networkAdditionalWhere=" AND (FALSE\n";
            foreach ($allowedNetworks as $network) {
                $idStr = $db->escapeString($network->getId());
                $networkAdditionalWhere .= " OR network_id='$idStr'\n";
            }
            $networkAdditionalWhere .= ")\n";
            $userData['preSelectedObject']=null;
            $userData['allowEmpty']=true;
            $userData['additionalWhere']=$networkAdditionalWhere;
            $name = "{$gwId}_new_node_network";
            $networkSelectUI = Network :: getSelectUI($name, $userData);
            $html .= sprintf(_("Add a new node in %s"), $networkSelectUI)." \n";
            $name = "{$gwId}_new_node_submit";
            $value = _("Add node");
            $html .= "<input type='submit' size='10' name='{$name}' value='$value'>\n";
            $html .= "</li>\n";
        }

        if($allowedNetworks || $allowedNodes){
            //"Steal" an existing node for this ID (typically for hardware replacement)
            $html .= "<li>".sprintf(_("You can \"steal\" an existing node.  The node's gateway id will be replaced with %s.  This is typical when replacing hardware."), $gwId)."<br/>\n";
            if($allowedNetworks) {
                $additionalWhere=$networkAdditionalWhere;
            }
            else {
                $additionalWhere=" AND (FALSE\n";
                foreach ($allowedNetworks as $network) {
                    $idStr = $db->escapeString($node->getId());
                    $additionalWhere .= " OR node_id='$idStr'\n";
                }
                $additionalWhere .= ")\n";
            }

            $userData['preSelectedObject']=null;
            $userData['allowEmpty']=true;
            $userData['additionalWhere']=$additionalWhere;
            $name = "{$gwId}_steal_node";
            $html .= Node :: getSelectUI($name, $userData);
            $name = "{$gwId}_steal_node_submit";
            $value = _("Steal node");
            $html .= "<input type='submit' size='10' name='{$name}' value='$value'>\n";
            $html .= "</li>\n";
        }
        $html .= "</ul>\n";
        return $html;

    }

    /**
     * Process the interface to deal with missing nodes.
     * @param $gwId The unknown gwId
     * @param $nodeIsNew Output parameter.  Will be set to true if a new node was created to resolve the situation
     * @return the created or stolen node object, or null if none was created (or stolen).
     */
    public static function processStealOrCreateNewUI($gwId, &$nodeIsNew=null)
    {
        // Init values
        $retval = null;
        $nodeIsNew = false;
        $name = "{$gwId}_new_node_submit";
        if(!empty($_REQUEST[$name])) {
            //Create new node
            $name = "{$gwId}_new_node_network";
            $network = Network :: processSelectUI($name);
            if($network) {
                Security::requirePermission(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $network);
                //echo  _("Adding node");
                $node = Node::createNewObject($gwId, $network);
                $nodeIsNew = true;
                $retval = $node;
            }
        }
        $name = "{$gwId}_steal_node_submit";
        if(!empty($_REQUEST[$name])){
            //"Steal" an existing node for this ID (typically for hardware replacement)
            $name = "{$gwId}_steal_node";
            $node = Node :: processSelectUI($name);
            if($node) {
                $permissionArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $node->getNetwork());
                $permissionArray[]=array(Permission::P('NODE_PERM_EDIT_GATEWAY_ID'), $node);
                Security::requireAnyPermission($permissionArray);
                //echo _("Stealing node $node");
                $node->setGatewayId($gwId);
                $retval = $node;
            }
        }


        return $retval;
    }



    /**
     * Get an interface to select the deployment status
     *
     * @param string $user_prefix A identifier provided by the programmer to
     *                            recognise it's generated html form
     *
     * @return string HTML markup
     */
    public function getSelectDeploymentStatus($user_prefix)
    {
         
        $db = AbstractDb::getObject();

        // Init values
        $html = "";
        $status_list = null;
        $tab = array();

        $name = "{$user_prefix}";
        $db->execSql("SELECT node_deployment_status FROM node_deployment_status", $status_list, false);

        if ($status_list == null) {
            throw new Exception(_("No deployment statuses could be found in the database"));
        }

        foreach ($status_list as $status) {
            $_statusvalue = $status['node_deployment_status'];
            $tab[] = array($_statusvalue, $this->_deploymentStatuses["$_statusvalue"]);
        }

        $html .= FormSelectGenerator::generateFromArray($tab, $this->getDeploymentStatus(), $name, null, false);

        return $html;
    }

    /**
     * Get the selected deployment status
     *
     * @param string $user_prefix An identifier provided by the programmer to
     *                            recognise it's generated form
     *
     * @return string The deployment status

     */
    public function processSelectDeploymentStatus($user_prefix)
    {
        $object = null;
        $name = "{$user_prefix}";
        return $_REQUEST[$name];
    }

    /**
     * Get the selected network ID
     *
     * @param string $user_prefix An identifier provided by the programmer to
     *                            recognise it's generated form
     *
     * @return string The deployment status

     */
    public function processSelectNetworkId($user_prefix)
    {
        $object = null;
        $name = "{$user_prefix}";
        return $_REQUEST[$name];
    }

    /** @param $id The id of the node
     * @param $idType 'NODE_ID' or 'GATEWAY_ID'*/
    protected function __construct($id, $idType='NODE_ID')
    {
        $db = AbstractDb::getObject();
        $this->mDb = & $db;

        $id_str = $db->escapeString($id);
        switch ($idType) {
            case 'NODE_ID': $sqlWhere = "node_id='$id_str'";
            break;
            case 'GATEWAY_ID': $sqlWhere = "gw_id='$id_str'";
            break;
            default:
                throw new exception('Unknown idType parameter');
        }
        $sqlWhere =
        $sql = "SELECT * FROM nodes WHERE $sqlWhere";
        $row = null;
        $db->execSqlUniqueRes($sql, $row, false);
        if ($row == null)
        {
            throw new Exception(sprintf(_("The node with %s: %s could not be found in the database!"), $idType, $id_str));
        }

        $this->_deploymentStatuses = array(
        "DEPLOYED" => _("Deployed"),
        "IN_PLANNING" => _("In planning"),
        "IN_TESTING" => _("In testing"),
        "NON_WIFIDOG_NODE" => _("Non-Wifidog node"),
        "PERMANENTLY_CLOSED" => _("Permanently closed"),
        "TEMPORARILY_CLOSED" => _("Temporarily closed")
        );

        $this->_row = $row;
        $this->id = $row['node_id'];
        
        parent::__construct($this->id, 'Node');
    }

    function __toString() {
        return $this->getName();
    }

    function getId()
    {
        return $this->id;
    }

    /** Get the id of the gateway associated with this node */
    function getGatewayId()
    {
        return $this->_row['gw_id'];
    }
    /** Change the gateway ID of the gateway asociated with this node.
     * @param $id, string, the new node id.
     * @return true on success, false on failure. Check this,
     * as it's possible that someone will enter an existing id, especially
     * if the MAC address is used and hardware is recycled.
     */
    function setGatewayId($id)
    {
        $id = $this->mDb->escapeString($id);
        $retval = $this->mDb->execSqlUpdate("UPDATE nodes SET gw_id = '{$id}' WHERE node_id = '{$this->getId()}'");
        if ($retval)
        {
            $this->refresh();
        }
        return $retval;
    }

    /** Gets the Network to which the node belongs
     * @return Network object (never returns null)
     */
    public function getNetwork()
    {
        return Network :: getObject($this->_row['network_id']);
    }


    function setNetwork(Network $network)
    {
        $net = $this->mDb->escapeString($network->getId());
        $this->mDb->execSqlUpdate("UPDATE nodes SET network_id = '{$net}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }


    /** Get a GisPoint object ; altide is not supported yet
     */
    function getGisLocation()
    {
        // Altitude is not supported yet
        return new GisPoint($this->_row['latitude'], $this->_row['longitude'], 0);
    }

    function setGisLocation($pt)
    {
        if (!empty ($pt))
        {
            $lat = $this->mDb->escapeString($pt->getLatitude());
            $long = $this->mDb->escapeString($pt->getLongitude());

            if (!empty ($lat) && !empty ($long))
                $this->mDb->execSqlUpdate("UPDATE nodes SET latitude = $lat, longitude = $long WHERE node_id = '{$this->getId()}'");
            else
                $this->mDb->execSqlUpdate("UPDATE nodes SET latitude = NULL, longitude = NULL WHERE node_id = '{$this->getId()}'");
            $this->refresh();
        }
    }

    /** Return the name of the node
     */
    function getName()
    {
        return $this->_row['name'];
    }

    function setName($name)
    {
        $name = $this->mDb->escapeString($name);
        $this->mDb->execSqlUpdate("UPDATE nodes SET name = '{$name}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    function getCreationDate()
    {
        return $this->_row['creation_date'];
    }

    function setCreationDate($creation_date)
    {
        $creation_date = $this->mDb->escapeString($creation_date);
        $this->mDb->execSqlUpdate("UPDATE nodes SET creation_date = '{$creation_date}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    function getWebSiteURL()
    {
        return $this->_row['home_page_url'];
    }

    function setWebSiteUrl($url)
    {
        $url = $this->mDb->escapeString($url);
        $this->mDb->execSqlUpdate("UPDATE nodes SET home_page_url = '{$url}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    function getDescription()
    {
        return $this->_row['description'];
    }

    function setDescription($description)
    {
        $description = $this->mDb->escapeString($description);
        $this->mDb->execSqlUpdate("UPDATE nodes SET description = '{$description}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    function getMapURL()
    {
        return $this->_row['map_url'];
    }

    function setMapURL($url)
    {
        $url = $this->mDb->escapeString($url);
        $this->mDb->execSqlUpdate("UPDATE nodes SET map_url = '{$url}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }
    
    /**
     * Returns whether the node should be shown on a map or is invisible
     *
     */

    public function showOnMap()
    {
        return (($this->_row['show_node_on_map'] == 't') ? true : false);
    }

    /** Set if this node be shown on map
     * @param $value The new value, true or false
     * @return true on success, false on failure */
    function setShowOnMap($value)
    {
        $retval = true;
        if ($value != $this->showOnMap())
        {
            $db = AbstractDb::getObject();
            $value ? $value = 'TRUE' : $value = 'FALSE';
            $retval = $db->execSqlUpdate("UPDATE nodes SET show_node_on_map = {$value} WHERE node_id = '{$this->getId()}'", false);
            $this->refresh();
        }
        return $retval;
    }
    
    public function getCivicNumber()
    {
        return $this->_row['civic_number'];
    }

    public function setCivicNumber($civic_number)
    {
        $civic_number = $this->mDb->escapeString($civic_number);
        $this->mDb->execSqlUpdate("UPDATE nodes SET civic_number = '{$civic_number}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    public function getStreetName()
    {
        return $this->_row['street_name'];
    }

    public function setStreetName($street_name)
    {
        $street_name = $this->mDb->escapeString($street_name);
        $this->mDb->execSqlUpdate("UPDATE nodes SET street_name = '{$street_name}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    public function getCity()
    {
        return $this->_row['city'];
    }

    public function setCity($city)
    {
        $city = $this->mDb->escapeString($city);
        $this->mDb->execSqlUpdate("UPDATE nodes SET city = '{$city}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    public function getProvince()
    {
        return $this->_row['province'];
    }

    public function setProvince($province)
    {
        $province = $this->mDb->escapeString($province);
        $this->mDb->execSqlUpdate("UPDATE nodes SET province = '{$province}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    public function getCountry()
    {
        return $this->_row['country'];
    }

    protected function setCountry($country)
    {
        $country = $this->mDb->escapeString($country);
        $this->mDb->execSqlUpdate("UPDATE nodes SET country = '{$country}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    public function getPostalCode()
    {
        return $this->_row['postal_code'];
    }

    public function setPostalCode($postal_code)
    {
        $postal_code = $this->mDb->escapeString($postal_code);
        $this->mDb->execSqlUpdate("UPDATE nodes SET postal_code = '{$postal_code}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    function getTelephone()
    {
        return $this->_row['public_phone_number'];
    }

    function setTelephone($phone)
    {
        $phone = $this->mDb->escapeString($phone);
        $this->mDb->execSqlUpdate("UPDATE nodes SET public_phone_number = '{$phone}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    function getTransitInfo()
    {
        return $this->_row['mass_transit_info'];
    }

    function setTransitInfo($transit_info)
    {
        $transit_info = $this->mDb->escapeString($transit_info);
        $this->mDb->execSqlUpdate("UPDATE nodes SET mass_transit_info = '{$transit_info}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    function getEmail()
    {
        return $this->_row['public_email'];
    }

    function setEmail($email)
    {
        $email = $this->mDb->escapeString($email);
        $this->mDb->execSqlUpdate("UPDATE nodes SET public_email = '{$email}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    function getDeploymentStatus()
    {
        return $this->_row['node_deployment_status'];
    }

    function setDeploymentStatus($status)
    {
        $status = $this->mDb->escapeString($status);
        $this->mDb->execSqlUpdate("UPDATE nodes SET node_deployment_status = '{$status}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    function getAllowsPublicStats()
    {
        return (($this->_row['allows_public_stats'] == 't') ? true : false);
    }

    function setAllowsPublicStats($allowed)
    {
        $allowed = $allowed ? 't':'f';
        $allowed = $this->mDb->escapeString($allowed);
        $this->mDb->execSqlUpdate("UPDATE nodes SET allows_public_stats = BOOL('$allowed') WHERE node_id = '{$this->getId()}'");
        $this->refresh();

        //FIXME: Delete the folder if necessary?
    }

    function hasPublicStats()
    {
        return file_exists($this->getPublicStatsDir() . $this->getPublicStatsFile());
    }

    function getPublicStatsDir()
    {
        return WIFIDOG_ABS_FILE_PATH . NODE_PUBLIC_STATS_DIR . $this->getId() . "/";
    }

    function getPublicStatsFile()
    {
        return "index.html";
    }

    function getLastPaged()
    {
        return $this->_row['last_paged'];
    }

    function setLastPaged($last_paged)
    {
        $this->mDb->execSqlUpdate("UPDATE nodes SET last_paged = {$last_paged}::abstime WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    function getLastHeartbeatIP()
    {
        return $this->_row['last_heartbeat_ip'];
    }

    function getLastHeartbeatUserAgent()
    {
        return $this->_row['last_heartbeat_user_agent'];
    }

    function getLastHeartbeatWifidogUptime()
    {
        return $this->_row['last_heartbeat_wifidog_uptime'];
    }

    function getLastHeartbeatSysUptime()
    {
        return $this->_row['last_heartbeat_sys_uptime'];
    }

    function getLastHeartbeatSysLoad()
    {
        return $this->_row['last_heartbeat_sys_load'];
    }

    function getLastHeartbeatSysMemfree()
    {
        return $this->_row['last_heartbeat_sys_memfree'];
    }

    function getLastHeartbeatTimestamp()
    {
        return $this->_row['last_heartbeat_timestamp'];
    }

    function setLastHeartbeatTimestamp($timestamp)
    {
        $this->mDb->execSqlUpdate("UPDATE nodes SET last_heartbeat_timestamp = '{$timestamp}' WHERE node_id = '{$this->getId()}'");
        $this->refresh();
    }

    /** Is the node a Splash Only node?  Will only return true if the Network configuration allows it.
     * @return true or false */
    public function isSplashOnly()
    {
        return $this->getNetwork()->getSplashOnlyNodesAllowed() && $this->isConfiguredSplashOnly();
    }

    /** Is the node configured as a Splash Only node?  This is NOT the same as isSplashOnly().
     * This is the getter for the configuration set in the database for this node.
     * For the node to actually be splash only, this AND the network
     * gonfiguration must match.
     * @return true or false */
    public function isConfiguredSplashOnly()
    {
        return (($this->_row['is_splash_only_node'] == 't') ? true : false);
    }

    /** Set if this node should be a splash-only (no login) node (if enabled in Network configuration)
     * @param $value The new value, true or false
     * @return true on success, false on failure */
    function setIsConfiguredSplashOnly($value)
    {
        $retval = true;
        if ($value != $this->isConfiguredSplashOnly())
        {
            $db = AbstractDb::getObject();
            $value ? $value = 'TRUE' : $value = 'FALSE';
            $retval = $db->execSqlUpdate("UPDATE nodes SET is_splash_only_node = {$value} WHERE node_id = '{$this->getId()}'", false);
            $this->refresh();
        }
        return $retval;
    }

    /** The url to show instead of the portal.  If empty, the portal is shown
     Must be enabled in the Network configuration to have any effect
     @return a string */
    function getCustomPortalRedirectUrl()
    {
        return $this->_row['custom_portal_redirect_url'];
    }

    /** The url to show instead of the portal.  If empty, the portal is shown
     Must be enabled in the Network configuration to have any effect
     @return true on success, false on failure */
    function setCustomPortalRedirectUrl($value)
    {
        $retval = true;
        if ($value != $this->getCustomPortalRedirectUrl())
        {
            $db = AbstractDb::getObject();
            $value = $db->escapeString($value);
            $retval = $db->execSqlUpdate("UPDATE nodes SET custom_portal_redirect_url = '{$value}' WHERE node_id = '{$this->getId()}'", false);
            $this->refresh();
        }
        return $retval;
    }

    /** redirect users to the original requested web page instead of portal
     Must be enabled in the Network configuration to have any effect
     @return a string */
    function getPortalOriginalUrlAllowed()
    {
        return (($this->_row['allow_original_url_redirect'] == 't') ? true : false);
    }

    /** redirect users to the original requested web page instead of portal
     Must be enabled in the Network configuration to have any effect
     @return true on success, false on failure */
    function setPortalOriginalUrlAllowed($value)
    {
        $retval = true;
        if ($value != $this->getPortalOriginalUrlAllowed())
        {
            $db = AbstractDb::getObject();
            $value ? $value = 'TRUE' : $value = 'FALSE';
            $retval = $db->execSqlUpdate("UPDATE nodes SET allow_original_url_redirect = '{$value}' WHERE node_id = '{$this->getId()}'", false);
            $this->refresh();
        }
        return $retval;
    }



    /**
     * Retrieves the admin interface of this object
     *
     * @return string The HTML fragment for this interface
     *
     * @access public
     *
     * @todo Most of this code will be moved to Hotspot class when the
     *       abtraction will be completed
     */
    public function getAdminUI()
    {
        $permArray=null;
        $permArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $this->getNetwork());
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_CONFIG'), $this);
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_GATEWAY_ID'), $this);
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_DEPLOYMENT_DATE'), $this);
        Security::requireAnyPermission($permArray);
        require_once('classes/InterfaceElements.php');
        require_once('classes/Stakeholder.php');
        // Init values
        $html = '';

        // Get information about the network
        $network = $this->getNetwork();

        $node_id = $this->getId();

        /*
         * Check for a warning message
         */
        if ($this->_warningMessage != "") {
            $html .= "<div class='errormsg'>".$this->_warningMessage."</div>\n";
        }

        /*
         * Begin with admin interface
         */
        $html .= "<fieldset class='admin_container ".get_class($this)."'>\n";
        $html .= "<legend>"._("Edit a node")."</legend>\n";
        $html .= "<ul class='admin_element_list'>\n";

        /*
         * Display stats
         */
        $permArray = null;
        $permArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $network);
        $permArray[]=array(Permission::P('NODE_PERM_ALLOW_GENERATING_PUBLIC_STATS'), $this);
        if (Security::hasAnyPermission($permArray)) {
            $_title = _("Statistics");
            $_data = InterfaceElements::generateInputCheckbox("allows_public_stats","", _("Allow public access to some node statistics."), $this->getAllowsPublicStats(), "allows_public_stats");
            $_data .= InterfaceElements::generateInputSubmit("node_" . $this->id . "_get_stats", _("Get access statistics"), "node_get_stats_submit");
            $html .= InterfaceElements::generateAdminSectionContainer("node_get_stats", $_title, $_data);
        }

        /*
         * Information about the node
         */
        $_html_node_information = array();

        // Gateway ID
        $_title = _("Gateway ID");
        $permArray = null;
        $permArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $network);
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_GATEWAY_ID'), $this);
        if (Security::hasAnyPermission($permArray)) {
            $_data = InterfaceElements::generateInputText("node_" . $node_id . "_gw_id", $this->getGatewayId(), "gw_id_input");
        } else {
            $_data  = htmlspecialchars($this->getGatewayId(), ENT_QUOTES);
            $_data .= InterfaceElements::generateInputHidden("node_" . $node_id . "_gw_id", $this->getGatewayId());
        }
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("gateway_id", $_title, $_data);

        //Node content
       
        $html .= parent::getContentAdminUI();
            
        // Name
        $permArray = null;
        $permArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $network);
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_NAME'), $this);
        if (Security::hasAnyPermission($permArray)) {
            $_title = _("Name");
            $_data = InterfaceElements::generateInputText("node_" . $node_id . "_name", $this->getName(), "node_name_input");
            $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_name", $_title, $_data);
        }
        else {
            $_title = _("Name");
            $_data = $this->getName();
            $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_name", $_title, $_data);
        }

        // Creation date
        $_title = _("Creation date");
        $permArray = null;
        $permArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $network);
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_DEPLOYMENT_DATE'), $this);
        if (Security::hasAnyPermission($permArray)) {
            $_data = DateTimeWD::getSelectDateTimeUI(new DateTimeWD($this->getCreationDate()), "node_" . $node_id . "_creation_date", DateTimeWD::INTERFACE_DATETIME_FIELD, "node_creation_date_input");
        } else {
            $_data  = htmlspecialchars($this->getCreationDate(), ENT_QUOTES);
            $_data .= InterfaceElements::generateInputHidden("node_" . $node_id . "_creation_date", $this->getCreationDate());
        }
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_creation_date", $_title, $_data);

        // Description
        $_title = _("Description");
        $name = "node_" . $node_id . "_description";
        $_data = "<textarea name='$name' cols=80 rows=5 id='node_description_textarea'>\n".$this->getDescription()."\n</textarea>\n";
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_description", $_title, $_data);

        // Civic number
        $_title = _("Civic number");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_civic_number", $this->getCivicNumber(), "node_civic_number_input");
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_civic_number", $_title, $_data);

        // Street name
        $_title = _("Street name");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_street_name", $this->getStreetName(), "node_street_name_input");
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_street_name", $_title, $_data);

        // City
        $_title = _("City");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_city", $this->getCity(), "node_city_input");
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_city", $_title, $_data);

        // Province
        $_title = _("Province / State");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_province", $this->getProvince(), "node_province_input");
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_province", $_title, $_data);

        // Postal Code
        $_title = _("Postal code");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_postal_code", $this->getPostalCode(), "node_postal_code_input");
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_postal_code", $_title, $_data);

        // Country
        $_title = _("Country");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_country", $this->getCountry(), "node_country_input");
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_country", $_title, $_data);

        // Public phone #
        $_title = _("Public phone number");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_public_phone", $this->getTelephone(), "node_public_phone_input");
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_public_phone", $_title, $_data);

        // Public mail
        $_title = _("Public email");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_public_email", $this->getEmail(), "node_public_email_input");
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_public_email", $_title, $_data);

        // Homepage URL
        $_title = _("Homepage URL");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_homepage_url", $this->getWebSiteURL(), "node_homepage_url_input");
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_homepage_url", $_title, $_data);

        // Mass transit info
        $_title = _("Mass transit info");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_mass_transit_info", $this->getTransitInfo(), "node_mass_transit_info_input");
        $_html_node_information[] = InterfaceElements::generateAdminSectionContainer("node_mass_transit_info", $_title, $_data);

        // Build section
        $html .= InterfaceElements::generateAdminSectionContainer("node_information", _("Information about the node"), implode(null, $_html_node_information));

        /*
         * Node GIS data
         */
        $_html_node_gis_data = array();
        $gis_point = $this->getGisLocation();

        // Latitude
        $_title = _("Latitude");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_gis_latitude", $gis_point->getLatitude(), "node_" . $node_id . "_gis_latitude");
        $_html_node_gis_data[] = InterfaceElements::generateAdminSectionContainer("node_gis_latitude", $_title, $_data);

        // Latitude
        $_title = _("Longitude");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_gis_longitude", $gis_point->getLongitude(), "node_" . $node_id . "_gis_longitude");
        $_html_node_gis_data[] = InterfaceElements::generateAdminSectionContainer("node_gis_longitude", $_title, $_data);

        // Call the geocoding service, if Google Maps is enabled then use Google Maps to let the user choose a more precise location
        if (defined('GMAPS_HOTSPOTS_MAP_ENABLED') && GMAPS_HOTSPOTS_MAP_ENABLED === true) {
            $_data  = InterfaceElements::generateInputSubmit("geocode_only", _("Geocode the address or postal code above"), "geocode_only_submit");
            $_data .= InterfaceElements::generateInputButton("google_maps_geocode", _("Check using Google Maps"), "google_maps_geocode_button", "submit", array("onclick" => "window.open('hotspot_location_map.php?node_id={$this->getId()}', 'hotspot_location', 'toolbar = 0, scrollbars = 1, resizable = 1, location = 0, statusbar = 0, menubar = 0, width = 600, height = 600');"));
            $_data .= "<div class='admin_section_hint' id='node_gis_geocode_hint'>". "(" . _("Use a geocoding service, then use Google Maps to pinpoint the exact location.") . ")" ."</div>\n";
        } else {
            $_data  = InterfaceElements::generateInputSubmit("geocode_only", _("Geocode the address or postal code above"), "geocode_only_submit");
            $_data .= "<div class='admin_section_hint' id='node_gis_geocode_hint'>". "(" . _("Use a geocoding service") . ")" ."</div>\n";
        }

        $_html_node_gis_data[] = InterfaceElements::generateAdminSectionContainer("node_gis_geocode", "", $_data);

        // Map URL
        $_title = _("Map URL");
        $_data = InterfaceElements::generateInputText("node_" . $node_id . "_map_url", $this->getMapURL(), "node_map_url_input");
        $_html_node_gis_data[] = InterfaceElements::generateAdminSectionContainer("node_map_url", $_title, $_data);
        
        $_title = _("Show node on map");
        $help = _("Should this node be visible on the map when deployed?");
        $_data = InterfaceElements::generateInputCheckbox("node_" . $node_id . "_show_on_map", "", _("Yes"), $this->showOnMap(), "node_show_on_map_input");
        $_html_node_gis_data[] = InterfaceElements::generateAdminSectionContainer("node_show_on_map", $_title, $_data, $help);

        // Build section
        $html .= InterfaceElements::generateAdminSectionContainer("node_gis_data", _("GIS data"), implode(null, $_html_node_gis_data));

        /*
         * Node configuration section
         */
        $_html_node_config = array();

        // Deployment status
        $_title = _("Node deployment status");
        $_data = $this->getSelectDeploymentStatus("node_" . $node_id . "_deployment_status");
        $_html_node_config[] = InterfaceElements::generateAdminSectionContainer("node_deployment_status", $_title, $_data);

        // Network selection
        $_title = _("Node Network");
        $_data = Network::getSelectUI("node_" . $node_id . "_network_id", array('preSelectedObject'=>$this->getNetwork()));
        $_html_node_config[] = InterfaceElements::generateAdminSectionContainer("node_network", $_title, $_data);

        //  is_splash_only_node
        if ($network->getSplashOnlyNodesAllowed()) {
            $_title = _("Is this node splash-only (no login)?");
            $_data = InterfaceElements::generateInputCheckbox("node_" . $node_id . "_is_splash_only_node", "", _("Yes"), $this->isConfiguredSplashOnly(), "node_is_splash_only_node_radio");
            $_html_node_config[] = InterfaceElements::generateAdminSectionContainer("node_is_splash_only_node", $_title, $_data);
        }

        // custom_portal_redirect_url
        if ($network->getCustomPortalRedirectAllowed()) {
            $_title = _("URL to show instead of the portal");
            $_data = InterfaceElements::generateInputText("node_" . $node_id . "_custom_portal_redirect_url", $this->getCustomPortalRedirectUrl(), "node_custom_portal_redirect_url_input");
            $_data .= _("If this is not empty, the portal will be disabled and this URL will be shown instead");
            $_html_node_config[] = InterfaceElements::generateAdminSectionContainer("node_custom_portal_redirect_url", $_title, $_data);
        }

        //  allow_original_URL_redirect
        $title = _("Original URL redirection");
        $help = _("Are nodes allowed to redirect users to the web page they originally requested instead of the portal? this will overide the custom portal URL");
        $data = InterfaceElements::generateInputCheckbox("node_" . $node_id . "_allow_original_URL_redirect", "", _("Yes"), $this->getPortalOriginalUrlAllowed(), "node_allow_original_URL_redirect_radio");
        $_html_node_config[] = InterfaceElements::generateAdminSectionContainer("node_allow_original_URL_redirect", $title, $data, $help);

        // Build section
        $html .= InterfaceElements::generateAdminSectionContainer("node_config", _("Node configuration"), implode(null, $_html_node_config));

        /*
         * Access rights
         */
        if (User::getCurrentUser()->DEPRECATEDisSuperAdmin()) {
            require_once('classes/Stakeholder.php');
            $html_access_rights = Stakeholder::getAssignStakeholdersUI($this);
            $html .= InterfaceElements::generateAdminSectionContainer("access_rights", _("Access rights"), $html_access_rights);
        }
        
        //Node hierarchy
        $html .= parent::getGraphAdminUI($network);

        $html .= "</ul>\n";
        $html .= "</fieldset>";

        return $html;
    }

    /**
     * Process admin interface of this object.
     *
     * @return void
     *
     * @access public
     */
    public function processAdminUI()
    {
        require_once('classes/Stakeholder.php');
        $user = User::getCurrentUser();
        // Get information about the network
        $network = $this->getNetwork();
        //pretty_print_r($_REQUEST);
        $permArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $this->getNetwork());
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_CONFIG'), $this);
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_GATEWAY_ID'), $this);
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_DEPLOYMENT_DATE'), $this);
        Security::requireAnyPermission($permArray);
        // Check if user is a admin
        $_userIsAdmin = User::getCurrentUser()->DEPRECATEDisSuperAdmin();

        // Information about the node

        $node_id = $this->getId();

        // Gateway Id
        $permArray = null;
        $permArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $network);
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_GATEWAY_ID'), $this);
        if (Security::hasAnyPermission($permArray)) {
            $name = "node_" . $node_id . "_gw_id";
            $this->setGatewayId($_REQUEST[$name]);
        }
        // Content processing
        parent::processContentAdminUI();

        // Name
        $permArray = null;
        $permArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $network);
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_NAME'), $this);
        if (Security::hasAnyPermission($permArray)) {
            $name = "node_".$node_id."_name";
            $this->setName($_REQUEST[$name]);
        }

        // Creation date
        $permArray = null;
        $permArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $network);
        $permArray[]=array(Permission::P('NODE_PERM_EDIT_DEPLOYMENT_DATE'), $this);
        if (Security::hasAnyPermission($permArray)) {
            $name = "node_".$node_id."_creation_date";
            $this->setCreationDate(DateTimeWD::processSelectDateTimeUI($name, DateTimeWD :: INTERFACE_DATETIME_FIELD)->getIso8601FormattedString());
        }

        // Homepage URL
        $name = "node_".$node_id."_homepage_url";
        $this->setWebSiteUrl($_REQUEST[$name]);

        // Description
        $name = "node_".$node_id."_description";
        $this->setDescription($_REQUEST[$name]);

        // Map URL
        $name = "node_".$node_id."_map_url";
        $this->setMapUrl($_REQUEST[$name]);

        // Civic number
        $name = "node_".$node_id."_civic_number";
        $this->setCivicNumber($_REQUEST[$name]);

        // Street name
        $name = "node_".$node_id."_street_name";
        $this->setStreetName($_REQUEST[$name]);

        // City
        $name = "node_".$node_id."_city";
        $this->setCity($_REQUEST[$name]);

        // Province
        $name = "node_".$node_id."_province";
        $this->setProvince($_REQUEST[$name]);

        // Postal Code
        $name = "node_".$node_id."_postal_code";
        $this->setPostalCode($_REQUEST[$name]);

        // Country
        $name = "node_".$node_id."_country";
        $this->setCountry($_REQUEST[$name]);

        // Public phone #
        $name = "node_".$node_id."_public_phone";
        $this->setTelephone($_REQUEST[$name]);

        // Public mail
        $name = "node_".$node_id."_public_email";
        $this->setEmail($_REQUEST[$name]);

        // Mass transit info
        $name = "node_".$node_id."_mass_transit_info";
        $this->setTransitInfo($_REQUEST[$name]);

        // GIS data
        // Get a geocoder for a given country
        if (!empty ($_REQUEST['geocode_only']))
        {
            if ($geocoder = AbstractGeocoder :: getGeocoder($this->getCountry()) != null)
                $geocoder = AbstractGeocoder :: getGeocoder($this->getCountry());          
            else
                $geocoder = AbstractGeocoder :: getGeocoder('Earth');

            if ($geocoder != null)
            {
                $geocoder->setCivicNumber($this->getCivicNumber());
                $geocoder->setStreetName($this->getStreetName());
                $geocoder->setCity($this->getCity());
                $geocoder->setProvince($this->getProvince());
                $geocoder->setPostalCode($this->getPostalCode());
                if ($geocoder->validateAddress() == true)
                {
                    if (($point = $geocoder->getGisLocation()) !== null)
                    $this->setGisLocation($point);
                    else
                    $this->_warningMessage = _("It appears that the Geocoder could not be reached or could not geocode the given address.");
                }
                else
                $this->_warningMessage = _("You must enter a valid address.");
            }
            else
            {
                $this->_warningMessage = _("Unable to create geocoder.  Are you sure you set the country?");
            }
        }
        else
        {
            // Use what has been set by the user.
            $gis_lat_name = "node_".$node_id."_gis_latitude";
            $gis_long_name = "node_".$node_id."_gis_longitude";
            $this->setGisLocation(new GisPoint($_REQUEST[$gis_lat_name], $_REQUEST[$gis_long_name], .0));
        }
        
        $name = "node_".$node_id."_show_on_map";
        $this->setShowOnMap(empty ($_REQUEST[$name]) ? false : true);

        // Statistics
        $name = "node_{$this->id}_get_stats";
        if (!empty ($_REQUEST[$name]))
        header("Location: stats.php?".urlencode("selected_nodes[]")."=".urlencode($this->getId()));
        $permArray = null;
        $permArray[]=array(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG'), $network);
        $permArray[]=array(Permission::P('NODE_PERM_ALLOW_GENERATING_PUBLIC_STATS'), $this);
        if (Security::hasAnyPermission($permArray)) {

            if (isset($_REQUEST['allows_public_stats'])){
                $this->setAllowsPublicStats($_REQUEST['allows_public_stats']=='on');
            } else {
                $this->setAllowsPublicStats(false);
            }
        }
         
        // Node configuration section

        $network = $this->getNetwork();

        // Deployment status
        $name = "node_".$node_id."_deployment_status";
        $this->setDeploymentStatus(self :: processSelectDeploymentStatus($name));

        // Network selection
        $name = "node_".$node_id."_network_id";
        $new_network=Network :: processSelectUI($name);
        if($new_network!=$this->getNetwork()) {
            Security::requirePermission(Permission::P('NETWORK_PERM_ADD_NODE'), $new_network);
            $this->setNetwork($new_network);
        }

        //  is_splash_only_node
        if ($network->getSplashOnlyNodesAllowed())
        {
            $name = "node_".$node_id."_is_splash_only_node";
            $this->setIsConfiguredSplashOnly(empty ($_REQUEST[$name]) ? false : true);
        }

        // custom_portal_redirect_url
        if ($network->getCustomPortalRedirectAllowed())
        {
            $name = "node_".$node_id."_custom_portal_redirect_url";
            $this->setCustomPortalRedirectUrl($_REQUEST[$name]);
        }

        // allow_original_URL_redirect
        if ($network->getPortalOriginalUrlAllowed())
        {
            $name = "node_" . $node_id . "_allow_original_URL_redirect";
            $this->setPortalOriginalUrlAllowed(empty ($_REQUEST[$name]) ? false : true);
        }

        // End Node configuration section
        
        parent::processGraphAdminUI($errMsg, $network);
        if(!empty($errMsg)) {
            echo $errMsg;
            $errMsg = null;
        }

        // Access rights
        Stakeholder::processAssignStakeholdersUI($this, $errMsg);
        if(!empty($errMsg)) {
            echo $errMsg;
        }
    }

    // Redirect to this node's portal page
    public function getUserUI()
    {
        header("Location: ".BASE_SSL_PATH."portal/?node_id=".$this->getId());
    }

    /**
     * The list of the 5 most recent users who have logged into this node in the past week,
     * excluding those that are currently connected.
     *
     * @return array An array of User object, or an empty array
     *
     * @access public
     */
    public function getRecentUsers()
    {
        $numUsers = 5;
        $db = AbstractDb::getObject();

        // Init values
        $retval = array();
        $users = null;
        $anonUsers = 0;
        $weekAgoDate = strftime("%Y-%m-%d 00:00", strtotime("-1 week"));

        $sql = null;
        $sql .= "SELECT user_id, timestamp_in FROM connections \n";
        $sql .= "WHERE connections.node_id='{$this->id}' \n";
        $sql .= "AND connections.user_id NOT IN (".$this->getOnlineUsersSql().")  \n";
        $sql .= "AND connections.timestamp_in>'{$weekAgoDate}' \n";

        $sql .= "ORDER BY connections.timestamp_in DESC\n";
        $sql .= "LIMIT $numUsers * 4 \n";
        $db->execSql($sql, $users, false);

        if ($users != null) {
            $alreadyPresentArray[] = array(); //Only keep the top $num
            $count = 0;
            foreach ($users as $user_row) {
                if(empty($alreadyPresentArray[$user_row['user_id']])) {
                    $retval[] = User::getObject($user_row['user_id']);
                    $alreadyPresentArray[$user_row['user_id']]=true;
                    $count++;
                    if($count>=$numUsers) {
                        break;
                    }
                }
            }
        }

        return $retval;
    }

    /**
     * The list of the 5 users who have logged into this node the most different days during the last 3 months
     *
     * @return array An array of User object, or an empty array
     *
     * @access public
     */
    public function getActiveUsers()
    {
        $numUsers = 5;
        $db = AbstractDb::getObject();

        // Init values
        $retval = array();
        $users = null;
        $anonUsers = 0;
        $sql = null;
        $sql .= "SELECT DISTINCT connections.user_id, count(distinct date_trunc('day', timestamp_in)) as connections FROM connections \n";
        $sql .= " WHERE connections.node_id='{$this->id}' \n";
        $sql .= " AND timestamp_in > (CURRENT_TIMESTAMP - interval '3 month') \n";
        $sql .= " GROUP BY connections.user_id  \n";
        $sql .= "ORDER BY connections desc \n";
        $sql .= " LIMIT $numUsers\n";
        $db->execSql($sql, $users, false);

        if ($users != null) {
            foreach ($users as $user_row) {
                $retval[] = User::getObject($user_row['user_id']);
            }
        }

        return $retval;
    }

    private function getOnlineUsersSql() {
        return "SELECT users.user_id FROM users,connections JOIN tokens USING (token_id) WHERE tokens.token_status='".TOKEN_INUSE."' AND users.user_id=connections.user_id AND connections.node_id='{$this->id}'";
    }
    /**
     * The list of users online at this node
     *
     * @return array An array of User object, or an empty array
     *
     * @access public
     */
    public function getOnlineUsers()
    {
         
        $db = AbstractDb::getObject();

        // Init values
        $retval = array();
        $users = null;
        $anonUsers = 0;
        $db->execSql($this->getOnlineUsersSql(), $users, false);
        if ($users != null) {
            foreach ($users as $user_row) {
                $retval[] = User::getObject($user_row['user_id']);
            }
        }

        return $retval;
    }

    /**
     * Find out how many users are online this specific Node
     * Counts every user account connected (once for every account), except the splash-only user + every mac adresses connecting as the splash-only user
     * @return int Number of online users
     *
     * @access public
     */
    public function getNumOnlineUsers()
    {
        $db = AbstractDb::getObject();
        // Init values
        $retval = array ();
        $row = null;
        $splashOnlyUserId = $this->getNetwork()->getSplashOnlyUser()->getId();
        $sql = "SELECT ((SELECT COUNT(DISTINCT users.user_id) as count FROM users,connections JOIN tokens USING (token_id) WHERE tokens.token_status='".TOKEN_INUSE."' AND users.user_id=connections.user_id AND connections.node_id='{$this->id}' AND users.user_id!='{$splashOnlyUserId}') + (SELECT COUNT(DISTINCT connections.user_mac) as count FROM users,connections JOIN tokens USING (token_id) WHERE tokens.token_status='".TOKEN_INUSE."' AND users.user_id=connections.user_id AND connections.node_id='{$this->id}' AND users.user_id='{$splashOnlyUserId}')) AS count";
        $db->execSqlUniqueRes($sql, $row, false);

        return $row['count'];
    }


    /** The list of all Technical officers of this node.
     * Technical officers are displayed highlited and in the online user's list,
     * and are contacted when the Node goes down.
     * @return An array of User object, or en empty array */
    function DEPRECATEDgetTechnicalOfficers()
    {
        $db = AbstractDb::getObject();
        $retval = array ();
        $officers = null;
        $db->execSql("SELECT user_id FROM node_stakeholders WHERE role_id = 'NODE_TECH_OFFICER' AND object_id='{$this->id}'", $officers, false);
        if ($officers != null)
        {
            foreach ($officers as $officer_row)
            {
                $retval[] = User :: getObject($officer_row['user_id']);
            }
        }
        return $retval;
    }

    /** Reloads the object from the database.  Should normally be called after a set operation */
    protected function refresh()
    {
        $this->__construct($this->id);
    }
    /** Menu hook function */
    static public function hookMenu() {
        $items = array();
        if(Security::getObjectsWithPermission(Permission::P('NETWORK_PERM_EDIT_ANY_NODE_CONFIG')))
        {
            $items[] = array('path' => 'node/node_edit',
            'title' => _("Edit nodes"),
            'url' => BASE_URL_PATH.htmlspecialchars("admin/generic_object_admin.php?object_class=Node&action=list")
            );
        }
        else if($nodes = Security::getObjectsWithPermission(Permission::P('NODE_PERM_EDIT_CONFIG'))) {
             
            foreach ($nodes as $nodeId => $node) {
                $items[] = array('path' => 'node/node_'.$nodeId.'edit',
                'title' => sprintf(_("Edit %s"), $node->getName()),
                'url' => BASE_URL_PATH.htmlspecialchars("admin/generic_object_admin.php?object_class=Node&action=edit&object_id=$nodeId")
                );
            }
        }
        if(Security::hasPermission(Permission::P('NETWORK_PERM_ADD_NODE'))){
            $items[] = array('path' => 'node/node_add_new',
                'title' => sprintf(_("Add a new node")),
                'url' => BASE_URL_PATH.htmlspecialchars("admin/generic_object_admin.php?object_class=Node&action=new_ui")
            );
        }
        $items[] = array('path' => 'node',
        'title' => _('Node administration'),
        'type' => MENU_ITEM_GROUPING);
        return $items;
    }
    /**
     * Assigns values about node to be processed by the Smarty engine.
     *
     * @param object $smarty Smarty object
     * @param object $node    Node object, if unset, the current node will be used
     *
     * @return void
     */
    public static function assignSmartyValues($smarty, $node = null)
    {
        if (!$node) {
            $node = self::getCurrentNode();
        }

        // Set node details
        $smarty->assign('nodeId', $node ? $node->getId() : '');
        $smarty->assign('nodeName', $node ? $node->getName() : '');
        $smarty->assign('nodeLastHeartbeatIP', $node ? $node->getLastHeartbeatIP() : '');
        $smarty->assign('nodeNumOnlineUsers', $node ? $node->getNumOnlineUsers() : '');
        $smarty->assign('nodeWebSiteURL', $node ? $node->getWebSiteURL() : '');
        $node = self::getCurrentRealNode();
        // Set node details
        $smarty->assign('realNodeId', $node ? $node->getId() : '');
        $smarty->assign('realNodeName', $node ? $node->getName() : '');
        $smarty->assign('realNodeLastHeartbeatIP', $node ? $node->getLastHeartbeatIP() : '');
    }
    
    /**
     * Get the type of graph element (read-only for now)
     * 
     * @return string
     */
    protected function getType() {
        return 'Node';
    }
  
    /**
     * Return whether this element is a root or has parent (Network is root)
     * @return boolean
     */
    public function isRoot(){
        return false;
    }
    
		/**
     * Return whether this element is a leaf or has children (Node is leaf)
     * @return boolean
     */
    public function isLeaf() {
        return true;
    }
    
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */
