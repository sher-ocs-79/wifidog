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
 * @copyright  2006 Benoit Grégoire, Technologies Coeus inc.
 * @version    Subversion $Id: Content.php 1074 2006-06-18 09:53:56Z fproulx $
 * @link       http://www.wifidog.org/
 */

/**
 * Load required classes
 */
require_once ('classes/User.php');

/**
 * Abstraction and utilities to handle HyperLinks
 *
 * @package    WiFiDogAuthServer
 * @author     Benoit Grégoire <benoitg@coeus.ca>
 * @copyright  2006 Benoit Grégoire, Technologies Coeus inc.
 */
class HyperLinkUtils {
    const pattern = '/(<a\s.*?HREF=[\'"]?)((?:http|https|ftp).*?)([\'"\s].*?>)/mi';

    /**
     * Find http, https and ftp hyperlinks in a string
     *
     * @param string $string The string to parse to find hyperlinks in A
     * HREF constructs
     * @return array of URLs

     */
    private static function findHyperLinks(& $string) {
        //pretty_print_r(self::pattern);
        $matches = null;
        $num_matches = preg_match_all(self :: pattern, $string, $matches);

        return $matches;
    }

    /** Get the  clickthrough-logged equivalent of a sincle URL (http, https or ftp) */
    private static function getClickThroughLink($hyperlink, Content & $content, $node, $user) {
        $node ? $node_id = urlencode($node->getId()) : $node_id = null;
        $user ? $user_id = urlencode($user->getId()) : $user_id = null;
        return htmlspecialchars(BASE_URL_PATH . "clickthrough.php?destination_url=" . urlencode($hyperlink) . "&content_id=" . urlencode($content->getId()) . "&node_id={$node_id}&user_id={$user_id}");
    }

    /** Replace all hyperlinks in the source string with their clickthrough-logged equivalents */
    public static function replaceHyperLinks(& $string, Content & $content) {
        $matches = self :: findHyperLinks($string);
        //pretty_print_r($matches);
        if (!empty ($matches[2])) {
            $node = Node :: getCurrentNode();
            $user = User :: getCurrentUser();
            $i = 0;
            foreach ($matches[2] as $link) {
                $new_link = self :: getClickThroughLink($link, $content, $node, $user);
                $replacements[] = $matches[1][$i] . $new_link . $matches[3][$i];
                $i++;
            }
            //pretty_print_r($replacements);
            return str_replace($matches[0], $replacements, $string);
        } else {
            return $string;
        }
    }

    /** Is the entered URL a valid URL?
     * @param $url
     * @return true or false
     */
    static public function validateURL($url) {
        $retval = false;
        if (!preg_match('/^(http|https|ftp):\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)(:(\d+))?(\/)?/i', $url, $m)) {
            //URL isn't valid
        } else {
            //URL is valid
            $retval = true;
        }
        return $retval;
    }

} // End class

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */