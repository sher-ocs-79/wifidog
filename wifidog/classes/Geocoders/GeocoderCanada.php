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
 * @subpackage Geocoders
 * @author     Francois Proulx <francois.proulx@gmail.com>
 * @copyright  2005-2006 Francois Proulx, Technologies Coeus inc.
 * @version    Subversion $Id$
 * @link       http://www.wifidog.org/
 */

/**
 * Load required class
 */
require_once('classes/GenericObject.php');

/**
 * @package    WiFiDogAuthServer
 * @subpackage Geocoders
 * @author     Francois Proulx <francois.proulx@gmail.com>
 * @copyright  2005-2006 Francois Proulx, Technologies Coeus inc.
 */
class GeocoderCanada extends AbstractGeocoder
{
    private $cached_latitude;
    private $cached_longitude;

    public function __construct()
    {
        $this->setCountry("Canada");
        $this->setEndpointUrl("http://geocode.ca/?");
    }

    /** Run regexp to verify the postal code
     * @return boolean
     */
    private function validatePostalCode()
    {
        // Match canadian postal code (ex. J4D 3D9)
        return preg_match("/^[A-Z]\d[A-Z]\s?\d[A-Z]\d$/i", $this->getPostalCode());
    }

    /** Validates province code
     * @return boolean
     */
    private function validateProvince()
    {
        return in_array($this->getProvince(), array ("ab", "bc", "mb", "nb", "nl", "ns", "nt", "nu", "on", "pe", "qc", "sk", "yt"));
    }

    /**
     * Overriden setProvince from abstract class
     * Canadian specific tests
     */
    public function setProvince($province)
    {
        parent :: setProvince($province);
        if (!$this->validateProvince())
        {
            $province = trim(strtolower($province));
            $provinces_variants = array ("quebec" => "qc", "québec" => "qc", "ontario" => "on", "alberta" => "ab", "british columbia" => "bc", "manitoba" => "mb", "new brunswick" => "nb", "nouveau brunswick" => "nb", "saskatchewan" => "sk", "nunavut" => "nt", "nova scotia" => "ns", "nouvelle écosse" => "ns");
            if (($province_code = array_search($province, $provinces_variants)) != false)
                parent :: setProvince($province_code);
            else
            {
                // Not a valid province
                parent :: setProvince("");
                return false;
            }
        }
        return true;
    }

    /** Validate address, making sure we don't send an HTTP for nothing
     * @return boolean
     */
    public function validateAddress()
    {
        // Make sure a city or a postal code has been entered
        if (($this->getCivicNumber() == "" || $this->getStreetName() == "" || $this->getCity() == "" || !$this->validateProvince()) && !$this->validatePostalCode())
            return false;
        return true;
    }

    /** Constructs the HTTP query string that will be sent to the endpoint URL
     * @return string HTTP GET query string
     */
    private function buildQuery()
    {
        // Build HTTP GET query string containing all parameters
        $http_params = array ("geoit" => "XML", "addresst" => $this->getStreetName(), "stno" => $this->getCivicNumber(), "city" => $this->getCity(), "prov" => $this->getProvince(), "postal" => $this->getPostalCode());
        return $this->getEndpointUrl().http_build_query($http_params);
    }

    /** Runs the HTTP GET query
     * @return boolean
     */
    private function executeQuery()
    {
        // Don't send multiple queries when the input has not changed
        if ($this->shouldExecuteQuery() == true)
        {

            // Load the XML document
            if (($dom = DOMDocument :: load($this->buildQuery())) !== false)
            {
                $xpath = new DOMXpath($dom);

                // Skip if there was an error
                if ($xpath->query("/geodata/error")->length >= 1)
                    return false;

                // Run XPath quries to extract data
                $this->cached_latitude = $xpath->query("//geodata/latt")->item(0)->nodeValue;
                $this->cached_longitude = $xpath->query("//geodata/longt")->item(0)->nodeValue;

                // Prevent from sending multiple queries.
                $this->keepResponse();
            }
            else
                return false;
        }
        return true;
    }

    /** Get the latitude for enterred infos
     * @return string latitude ( decimal format 6-digits precision )
     */
    public function getLatitude()
    {
        if ($this->validateAddress())
            if ($this->executeQuery() == true)
                return $this->cached_latitude;
            else
                return null;
        else
            return null;
    }

    /** Get the longitude for enterred infos
     * @return string longitude ( decimal format 6-digits precision )
     */
    public function getLongitude()
    {
        if ($this->validateAddress())
            if ($this->executeQuery() == true)
                return $this->cached_longitude;
            else
                return null;
        else
            return null;
    }

    /** Get the altitude for enterred infos
     * @return string algitude
     */
    public function getAltitude()
    {
        // Not supported by geocode.ca
        return null;
    }

    /** Get a GIS Point instance
     * @return GisPoint
     */
    public function getGisLocation()
    {
        $lat = $this->getLatitude();
        $long = $this->getLongitude();

        if($lat !== null && $long !== null)
            return new GisPoint($lat, $long, 0);
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


