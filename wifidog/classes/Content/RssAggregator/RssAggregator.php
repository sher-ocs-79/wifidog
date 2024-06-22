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
 * @subpackage ContentClasses
 * @author     Benoit Grégoire <benoitg@coeus.ca>
 * @copyright  2005-2006 Benoit Grégoire, Technologies Coeus inc.
 * @version    Subversion $Id$
 * @link       http://www.wifidog.org/
 */

/**
 * Load required class
 */

/**
 * Defines path to cache directory of Magpie
 */
define('SIMPLEPIE_CACHE_DIR', WIFIDOG_ABS_FILE_PATH . 'tmp/simplepie_cache/');

/**
 * Interim code to display the RSS feed for a hotspot
 *
 * @package    WiFiDogAuthServer
 * @subpackage ContentClasses
 * @author     Benoit Grégoire <benoitg@coeus.ca>
 * @copyright  2005-2006 Benoit Grégoire, Technologies Coeus inc.
 */
class RssAggregator extends Content {
    /**
    
     */
    private $content_rss_aggregator_row;

    /**
    
     */
    private $content_rss_aggregator_feeds_rows;

    /**
    
     */
    private $press_review;
    /**
     * Check if this specific ContentType is usable (all Dependency
     * met,etc.
     * This method is meant to be overloaded by the different content classes
     * @return true or flase
     */
    public static function isContentTypeFunctional() {
        $retval = (@include_once ('lib/feedpressreview/FeedPressReview.inc')) && (@include_once ('lib/simplepie/simplepie.inc'));
           return  ($retval);
    } 
    
    /**
     * Constructor
     *
     * @param string $content_id Content id
     *
     * @return void     */
    protected function __construct($content_id) {
        
        $db = AbstractDb::getObject();

        // Init values
        $row = null;
        $content_rss_aggregator_rows = null;
        $this->press_review=null;
        parent :: __construct($content_id);
        $content_id = $db->escapeString($content_id);

        $sql = "SELECT *, EXTRACT(EPOCH FROM max_item_age) as max_item_age_seconds FROM content_rss_aggregator WHERE content_id='$content_id'";
        $db->execSqlUniqueRes($sql, $row, false);

        if ($row == null) {
            $sql_new = "INSERT INTO content_rss_aggregator (content_id) VALUES ('$content_id')";
            $db->execSqlUpdate($sql_new, false);
            $db->execSqlUniqueRes($sql, $row, false);
            if ($row == null) {
                throw new Exception(_("The RssAggregator content with the following id could not be found in the database: ") . $content_id);
            }
        }

        $this->content_rss_aggregator_row = $row;

        $sql = "SELECT * FROM content_rss_aggregator_feeds WHERE content_id='$content_id'";
        $db->execSql($sql, $content_rss_aggregator_rows, false);

        if ($content_rss_aggregator_rows != null) {
            $this->content_rss_aggregator_feeds_rows = $content_rss_aggregator_rows;
        } else {
            $this->content_rss_aggregator_feeds_rows = array ();
        }
    }

/** Initialize the FeedPressReview object with the Aggregator's values
 * Will set the press_review object property 
 * @return FeedPressReviewThe new press_review object property */
private function initFeedPressReview()
{
            if (self::isContentTypeFunctional() && $this->press_review==null) {
            $this->press_review = new FeedPressReview();
            $this->press_review->setConfigCacheDir(SIMPLEPIE_CACHE_DIR);
            $this->press_review->setConfigAlgorithmStrength($this->getAlgorithmStrength());
            $this->press_review->setConfigMaxItemAge($this->getMaxItemAge());
            $this->press_review->setConfigFeedOrdering($this->getFeedOrdering());
            $this->press_review->setConfigFeedExpansion($this->getFeedExpansionMode());
            $user = User :: getCurrentUser();
            $last_display_timestamp = $this->getLastDisplayedTimestamp($user, null);
            //$last_display_timestamp = time()-24*3600;
            $this->press_review->setConfigLastDisplayed($last_display_timestamp);
            
            foreach ($this->content_rss_aggregator_feeds_rows as $feed_row) {
                $this->press_review->addSourceFeed($feed_row['url'], $feed_row['default_publication_interval'], $feed_row['bias']);
                $title = $this->press_review->getFeedTitle($feed_row['url']);

                // Update the stored feed title if it changed.
                //This allows the system to know every feed's title without continuously looking them up
                if (!empty ($title) && $title != $feed_row['title']) {
                    $db=AbstractDb::getObject();
                    $title = $db->escapeString($title);
                    $url = $db->escapeString($feed_row['url']);
                    $db->execSqlUpdate("UPDATE content_rss_aggregator_feeds SET title = '$title' WHERE url='$url'", false);
                    $this->refresh();
                }
            }
        }
        return $this->press_review;
}
    /**
     * Total number of items to display (from all feeds)
     *
     * @return integer
     */
    public function getDisplayNumItems() {
        return $this->content_rss_aggregator_row['number_of_display_items'];
    }

    /**
     * Sets the number of items to be displayed
     *
     * @param int $num_items Total number of items to display (from all feeds).
     * @param string $errormsg Reference to error message
     *
     * @return bool True if successfull
     */
    public function setDisplayNumItems($num_items, & $errormsg = null) {
        // Init values
        $retval = false;

        if (($num_items >= 1) && $num_items != $this->getDisplayNumItems()) {
            /*
             * Only update database if the mode is valid and there is an actual change
             */
            $db = AbstractDb::getObject();

            $num_items = $db->escapeString($num_items);
            $db->execSqlUpdate("UPDATE content_rss_aggregator SET number_of_display_items = $num_items WHERE content_id = '$this->id'", false);
            $this->refresh();

            $retval = true;
        }
        elseif ($num_items < 1) {
            $errormsg = _("You must display at least one element");

            $retval = false;
        } else {
            /*
             * Successfull, but nothing modified
             */
            $retval = true;
        }

        return $retval;
    }

    /** Should empty feeds be shown?
     *
     * @return boolean
     */
    public function getDisplayEmptyFeed() {
        if ($this->content_rss_aggregator_row['display_empty_feeds'] == 't') {
            $retval = true;
        } else {
            $retval = false;
        }
        return $retval;
    }

    /** Should empty feeds be shown?
     * @param boolean $value 
     * @return true if successfull
     */
    public function setDisplayEmptyFeed($value) {
        $retval = true;  
            if ($value != $this->getDisplayEmptyFeed()) /* Only update database if there is an actual change */ {
            $value ? $value_sql = 'TRUE' : $value_sql = 'FALSE';

            $db = AbstractDb::getObject();
            $retval = $db->execSqlUpdate("UPDATE content_rss_aggregator SET display_empty_feeds = $value_sql WHERE content_id = '$this->id'", false);
            $this->refresh();
        }
        return $retval;
    }
    
    /** In what order should feeds be displayed?
     *
     * @return text
     */
    public function getFeedOrdering() {
        return $this->content_rss_aggregator_row['feed_ordering'];
    }

    /** In what order should feeds be displayed?
     * @param text $value 
     * @return true if successfull
     */
    public function setFeedOrdering($value) {
        // Init values
        $retval = false;
        if ($value != $this->getFeedOrdering()) {
            /*
             * Only update database if the mode is valid and there is an actual change
             */
            $db = AbstractDb::getObject();
            $value = $db->escapeString($value);
            $db->execSqlUpdate("UPDATE content_rss_aggregator SET feed_ordering = '$value' WHERE content_id = '$this->id'", false);
            $this->refresh();
            $retval = true;
        } else {
            /*
             * Successfull, but nothing modified
             */
            $retval = true;
        }
        return $retval;
    }

    /** Which feed items should be expanded
     *
     * @return text
     */
    public function getFeedExpansionMode() {
        return $this->content_rss_aggregator_row['feed_expansion'];
    }

    /** Which feed items should be expanded
     * @param text $value 
	 * @return true if successfull
     */
    public function setFeedExpansionMode($value) {
        // Init values
        $retval = false;
        if ($value != $this->getFeedExpansionMode()) {
            /*
             * Only update database if the mode is valid and there is an actual change
             */
            $db = AbstractDb::getObject();
            $value = $db->escapeString($value);
            $db->execSqlUpdate("UPDATE content_rss_aggregator SET feed_expansion = '$value' WHERE content_id = '$this->id'", false);
            $this->refresh();
            $retval = true;
        } else {
            /*
             * Successfull, but nothing modified
             */
            $retval = true;
        }
        return $retval;
    }

    /**
     * How much bonus feeds that do not publish as often get over feed that
     * publish more often.
     *
     * @return int
     */
    public function getAlgorithmStrength() {
        return $this->content_rss_aggregator_row['algorithm_strength'];
    }

    /**
     * How much bonus feeds that do not publish as often get over feed that
     * publish more often.
     *
     * @param int    $strength The default is 0.75, with a typical range
     *                         between 0 and 1.
     *                         At 0, you have a classic RSS aggregator, meaning
     *                         the n most recent entries picked from all feeds
     *                         will be displayed. 1 is usually as high as
     *                         you'll want to go:  Assuming that all an
     *                         homogenous internal distribution (ex: one feed
     *                         publishes exactly one entry a day, the second
     *                         once every two days, and the third once every
     *                         three days), and you ask for 15 entries, there
     *                         will be 5 of each.  While that may not sound
     *                         usefull, it still is, as the feed's
     *                         distribution is usually not homogenous.
     * @param string $errormsg Reference to error message
     *
     * @return true if successfull
     */
    public function setAlgorithmStrength($strength, & $errormsg = null) {
        // Init values
        $retval = false;

        if ($strength != $this->getAlgorithmStrength()) {
            /*
             * Only update database if the mode is valid and there is an actual change
             */
            $db = AbstractDb::getObject();

            $strength = $db->escapeString($strength);
            $db->execSqlUpdate("UPDATE content_rss_aggregator SET algorithm_strength = '$strength' WHERE content_id = '$this->id'", false);
            $this->refresh();

            $retval = true;
        } else {
            /*
             * Successfull, but nothing modified
             */
            $retval = true;
        }
        return $retval;
    }

    /**
     * The maximum age of the items displayed
     *
     * @return mixed (integer or null) Maximum age of an item in seconds
     */
    public function getMaxItemAge() {
        $retval = $this->content_rss_aggregator_row['max_item_age_seconds'];

        if (empty ($retval)) {
            $retval = null;
        }

        return $retval;
    }

    /**
     * Set the oldest entries (in seconds) you are willing to see.
     *
     * Any entries older than this will not be considered at all for display,
     * even if it means that the configured number of items to be displayed
     * isn't reached. It's only usefull if all your feeds publish very rarely,
     * and you don't want very old entries to show up.
     *
     * @param int    $max_item_age Null or maximum age in seconds
     * @param string $errormsg     Reference to error message
     *
     * @return bool True if successfull
     */
    public function setMaxItemAge($max_item_age, & $errormsg = null) {
        // Init values
        $retval = false;

        if (empty ($max_item_age)) {
            $max_item_age = null;
        }

        if (($max_item_age == null || is_numeric($max_item_age) && ($max_item_age > 0)) && $max_item_age != $this->getMaxItemAge()) {
            /*
             * Only update database if the mode is valid and there is an actual change
             */
            $db = AbstractDb::getObject();

            if ($max_item_age == null) {
                $max_item_age = 'NULL';
            }
            else {
                $max_item_age = "$max_item_age seconds";
            }

            $max_item_age = $db->escapeString($max_item_age);
            $db->execSqlUpdate("UPDATE content_rss_aggregator SET max_item_age = '$max_item_age' WHERE content_id = '$this->id'", false);
            $this->refresh();

            $retval = true;
        }
        elseif ($max_item_age <= 0) {
            $errormsg = _("The maximum age must be a positive integer or null");

            $retval = false;
        } else {
            /*
             * Successfull, but nothing modified
             */
            $retval = true;
        }

        return $retval;
    }

    /**
     * Add a new feed to the aggregator
     *
     * @param string $url Feed's url
     *
     * @return bool True on success, false on failure
     */
    public function addFeed($url) {
        
        $db = AbstractDb::getObject();

        // Init values
        $retval = false;

        if (!empty ($url)) {
            $url = $db->escapeString($url);
            $sql = "INSERT INTO content_rss_aggregator_feeds (content_id, url) VALUES ('{$this->id}', '$url') ";
            $content_rss_aggregator_rows = null;
            $retval = $db->execSqlUpdate($sql, false);
            $this->refresh();
        }

        return $retval;
    }

    /**
     * Remove a feed from the aggregator
     *
     * @param string $url Feed's url
     *
     * @return bool True on success, false on failure
     */
    public function removeFeed($url) {
        
        $db = AbstractDb::getObject();

        // Init values
        $retval = false;

        if (!empty ($url)) {
            $url = $db->escapeString($url);
            $sql = "DELETE FROM content_rss_aggregator_feeds WHERE content_id='{$this->id}' AND url = '$url' ";
            $content_rss_aggregator_rows = null;
            $retval = $db->execSqlUpdate($sql, false);
            $this->refresh();
        }

        return $retval;
    }

    /**
     * Shows the administration interface for RssAggregator
     *
     * @param string $subclass_admin_interface HTML code to be added after the
     *                                         administration interface
     *
     * @return string HTML code for the administration interface
     */
    public function getAdminUI($subclass_admin_interface = null, $title = null) {
        $this->initFeedPressReview();
        $db = AbstractDb::getObject();

        // Init values
        $html = '';
        $feed_urls = null;
        $tab = array ();
        $i = 0;

        $html .= "<ul class='admin_element_list'>\n";
        /*
         * number_of_display_items
         */
        $html .= "<li class='admin_element_item_container'>\n";
        $html .= "<div class='admin_element_label'>" . _("Total number of items to display (from all feeds)") . ": </div>\n";
        $html .= "<div class='admin_element_data'>\n";

        $name = "rss_aggregator_" . $this->id . "_display_num_items";
        $value = $this->getDisplayNumItems();
        $html .= "<input type='text' size='2' value='$value' name='$name'>\n";

        $html .= "</div>\n";
        $html .= "</li>\n";

        /*
         * algorithm_strength
         */
        $html .= "<li class='admin_element_item_container'>\n";
        $html .= "<div class='admin_element_label'>\n";
        $title = _("How much bonus feeds that do not publish as often get over feed that publish more often.
                                            The default is 0.75, with a typical range between 0 and 1.
                                            At 0, you have a classic RSS aggregator, meaning the n most recent entries picked from all feeds
                                            will be displayed. 1 is usually as high as you'll want to go:  Assuming that all feeds have 
                                            an homogenous internal distribution (ex:  one feed publishes exactly one entry a day, the
                                            second once every two days, and the third once every three days), and you ask for 15 entries,
                                            there will be 5 of each.  While that may not sound usefull, it still is, as the feed's distribution is
                                            usually not homogenous.");
        $html .= "<a href=\"#\" title=\"$title\">"._("Algorithm Strength")."</a>\n";                                    
        $html .= ": </div>\n";
        $html .= "<div class='admin_element_data'>\n";

        $name = "rss_aggregator_" . $this->id . "_algorithm_strength";
        $value = $this->getAlgorithmStrength();
        $html .= "<input type='text' size='2' value='$value' name='$name'>\n";

        $html .= "</div>\n";
        $html .= "</li>\n";
        
        /*
         * feed_expansion
         */
        $html .= "<li class='admin_element_item_container'>\n";
        $html .= "<div class='admin_element_label'>\n";
        $title = _("Set the criteria that determines which feed items will be shown expanded by default.");
        $html .= "<a href=\"#\" title=\"$title\">"._("Feed item expansion criteria")."</a>\n";                                    
        $html .= ": </div>\n";
        $html .= "<div class='admin_element_data'>\n";
        $array = FeedPressReview::getFeedExpansionAllowedOptions();
        $value = $this->getFeedExpansionMode();
        $name = "rss_aggregator_" . $this->id . "_feed_expansion";
        $html .= FormSelectGenerator::generateFromKeyLabelArray($array, $value, $name, null, false);
        $html .= "</div>\n";
        $html .= "</li>\n";
        /*
         * feed_ordering
         */
        $html .= "<li class='admin_element_item_container'>\n";
        $html .= "<div class='admin_element_label'>\n";
        $title = _("Set in which order the feeds are displayed, and if items from all source should be merged together");
        $html .= "<a href=\"#\" title=\"$title\">"._("Item ordering")."</a>\n";                                    
        $html .= ": </div>\n";
        $html .= "<div class='admin_element_data'>\n";
        $array = FeedPressReview::getFeedOrderingAllowedOptions();
        $value = $this->getFeedOrdering();
        $name = "rss_aggregator_" . $this->id . "_feed_ordering";
        $html .= FormSelectGenerator::generateFromKeyLabelArray($array, $value, $name, null, false);
        $html .= "</div>\n";
        $html .= "</li>\n";

        /*
         * display_empty_feeds
         */
                $html .= "<li class='admin_element_item_container'>\n";
                $html .= "<div class='admin_element_label'>" . _("Display empty feeds?") . ": </div>\n";
                $html .= "<div class='admin_element_data'>\n";
                $name = "rss_aggregator_" . $this->id . "_display_empty_feeds";
                $this->getDisplayEmptyFeed() ? $checked = 'CHECKED' : $checked = '';
                $html .= "<input type='checkbox' name='$name' $checked>\n";
                $html .= "</div>\n";
                $html .= "</li>\n";

        /*
         * max_item_age
         */
        $html .= "<li class='admin_element_item_container'>\n";
        $html .= "<div class='admin_element_label'>\n";
        $title = _("Set the oldest entries (in seconds) you are willing to see.  Any entries older than this will not
                                            be considered at all for display, even if it means that the configured number of items to be displayed isn't reached.
                                            It's only usefull if all your feed publish very rarely, and you don't want very old entries to show up.");
        $html .= "<a href=\"#\" title=\"$title\">"._("Maximum age (seconds)")."</a>\n";     
        $html .= ": </div>\n";
        $html .= "<div class='admin_element_data'>\n";

        $name = "rss_aggregator_" . $this->id . "_max_item_age";
        $value = $this->getMaxItemAge();
        $html .= "<input type='text' size='10' value='$value' name='$name'>\n";

        $html .= _("seconds");
        $html .= "</div>\n";
        $html .= "</li>\n";

        /*
         * rss_aggregator_element (table)
         */
        $html .= "<li class='admin_element_item_container'>\n";
        $html .= "<div class='admin_element_label'>" . _("Feeds:") . "</div>\n";

        $html .= "<ul class='admin_element_list'>\n";

        foreach ($this->content_rss_aggregator_feeds_rows as $feed_row) {
            $html .= "<li class='admin_element_item_container'>\n";

            $html .= "<div class='admin_element_data'>\n";
            $html .= $this->getFeedAdminUI($feed_row);
            $html .= "</div'>\n";
            $html .= "<div class='admin_element_tools'>\n";

            /*
             * Delete feeds
             */
            $name = "rss_aggregator_" . $this->id . "_feed_" . md5($feed_row['url']) . "_delete";
            $html .= "<input type='submit' name='$name' value='" . _("Delete") . "'>";
            $html .= "</div>\n";
            $html .= "</li>\n";
        }

        /*
         * Add new feed
         */
        $html .= "<li class='admin_element_item_container'>\n";
        $html .= "<b>" . _("Add a new feed or pick one from the other feeds in the system (most_popular_first)") . "</b><br>";

        $sql = "SELECT DISTINCT ON (count, content_rss_aggregator_feeds.url) count, content_rss_aggregator_feeds.url, title FROM content_rss_aggregator_feeds
                                        JOIN (SELECT url, count(content_rss_aggregator_feeds.url) as count
                                        FROM content_rss_aggregator_feeds
                                        WHERE content_rss_aggregator_feeds.url NOT IN (SELECT url FROM content_rss_aggregator_feeds WHERE content_id='{$this->id}')
                                        GROUP BY content_rss_aggregator_feeds.url, content_rss_aggregator_feeds.url)
                                        AS available_feeds
                                        ON (available_feeds.url=content_rss_aggregator_feeds.url)
                                        ORDER by count desc, content_rss_aggregator_feeds.url DESC";

        $db->execSql($sql, $feed_urls, false);
        if ($feed_urls) {
            foreach ($feed_urls as $feed_row) {
                $tab[$i][0] = $feed_row['url'];
                empty ($feed_row['title']) ? $title = $feed_row['url'] : $title = $feed_row['title'];
                $tab[$i][1] = sprintf(_("%s, used %d times"), $title, $feed_row['count']);
                $i++;
            }
        }
        $name = "rss_aggregator_{$this->id}_feed_add";
        $html .= "<input type='text' size='60' value='' name='$name' id='$name'>\n";
        $html .= FormSelectGenerator :: generateFromArray($tab, null, 'existing_feeds', 'RssAggregator', true, _('Type URL manually'), "onchange='this.form.$name.value=this.value;'");

        $name = "rss_aggregator_{$this->id}_feed_add_button";
        $html .= "<input type='submit' name='$name' value='" . _("Add") . "'>";

        $html .= "</li>\n";
        $html .= "</ul>\n";
        $html .= "</li>\n";
        $html .= "</ul>\n";
        $html .= $subclass_admin_interface;

        return parent :: getAdminUI($html, $title);
    }

    /**
     * Processes the input of the administration interface for RssAggregator
     *
     * @return void
     */
    public function processAdminUI() {
        if ($this->DEPRECATEDisOwner(User :: getCurrentUser()) || User :: getCurrentUser()->DEPRECATEDisSuperAdmin()) {
            parent :: processAdminUI();

            /*
             * number_of_display_items
             */
            $name = "rss_aggregator_" . $this->id . "_display_num_items";
            $this->setDisplayNumItems($_REQUEST[$name]);

            /*
             * algorithm_strength
             */
            $name = "rss_aggregator_" . $this->id . "_algorithm_strength";
            $this->setAlgorithmStrength($_REQUEST[$name]);
       /*
         * feed_expansion
         */

        $name = "rss_aggregator_" . $this->id . "_feed_expansion";
            $this->setFeedExpansionMode($_REQUEST[$name]);
        /*
         * feed_ordering
         */
        $name = "rss_aggregator_" . $this->id . "_feed_ordering";
            $this->setFeedOrdering($_REQUEST[$name]);

        /*
         * display_empty_feeds
         */
                $name = "rss_aggregator_" . $this->id . "_display_empty_feeds";
                !empty ($_REQUEST[$name]) ? $this->setDisplayEmptyFeed(true) : $this->setDisplayEmptyFeed(false);

            /*
             * max_item_age
             */
            $name = "rss_aggregator_" . $this->id . "_max_item_age";
            $this->setMaxItemAge($_REQUEST[$name]);

            foreach ($this->content_rss_aggregator_feeds_rows as $feed_row) {
                $this->processFeedAdminUI($feed_row);

                           /*
                 * Delete feeds
                 */
                $name = "rss_aggregator_" . $this->id . "_feed_" . md5($feed_row['url']) . "_delete";

                if (isset ($_REQUEST[$name])) {
                    $this->removeFeed($feed_row['url']);
                }
            }
            /*
             * Add new feed
             */
            $name = "rss_aggregator_{$this->id}_feed_add";
            if (!empty ($_REQUEST[$name])) {
                $this->addFeed($_REQUEST[$name]);
            }
        }
    }

    /**
     * Feed-specific section of the admin interface
     *
     * @param  array $feed_row The database row of the content_rss_aggregator_feeds table
     *
     * @return string HTML code for the administration interface
    
     */
    private function getFeedAdminUI($feed_row) {
        // Init values
        $this->initFeedPressReview();
        $html = '';
        $html .= "<fieldset class='admin_container " . get_class($this) . "'>\n";
        if (!empty ($feed_row['title'])) {
            $html .= "<legend>{$feed_row['title']}</legend>\n";
        }
        $html .= "<ul class='admin_element_list'>\n";
        /*
         * URL
         */
        $html .= "<li class='admin_element_item_container'>\n";
        $html .= "<div class='admin_element_label'>" . _("URL") . ": \n";

        if (!$this->press_review->isFeedAvailable($feed_row['url'])) {
            $html .= "<br/><span class='warningmsg'>" . _("WARNING:  Either the feed couldn't be retrieved, or it couldn't be parsed.  Please double check the URL.") . "</span>";
        }

        $html .= "</div>\n";
        $html .= "<div class='admin_element_data'>\n";

        $name = "rss_aggregator_" . $this->id . "_feed_" . md5($feed_row['url']) . "_url";
        $value = $feed_row['url'];
        $html .= "<input type='text' size='60' value='$value' name='$name'>\n";

        $html .= "</div>\n";
        $html .= "</li>\n";

        /*
         * default_publication_interval
         */
        $html .= "<li class='admin_element_item_container'>\n";
        $calculated_pub_interval = $this->press_review->getFeedPublicationInterval($feed_row['url']);

        if ($calculated_pub_interval == true) {
            $html .= sprintf(_("The feed publishes an item every %.2f day(s)"), $calculated_pub_interval / (60 * 60 * 24));
        } else {
            $html .= "<div class='admin_element_label'><span class='warningmsg'>" . _("WARNING:  This feed does not include the publication dates.
                                                                                                                             The system needs to be able to compute approximate publication
                                                                                                                             date for each entry, so the entry can be weighted against the
                                                                                                                             others. In order for the aggregator to do a good job, you need
                                                                                                                             to estimate fublication frequency of the items, in days.
                                                                                                                             If unset, defaults to one day.") . ": </span></div>\n";
            $html .= "<div class='admin_element_data'>\n";
            $name = "rss_aggregator_" . $this->id . "_feed_" . md5($feed_row['url']) . "_default_publication_interval";

            if (!empty ($feed_row['default_publication_interval'])) {
                $value = $feed_row['default_publication_interval'] / (60 * 60 * 24);
            } else {
                $value = '';
            }

            $html .= "<input type='text' size='60' value='$value' name='$name'>\n";
            $html .= "</div>\n";
        }

        $html .= "</li>\n";

        /*
         * bias
         */
        $html .= "<li class='admin_element_item_container'>\n";
        $html .= "<div class='admin_element_label'>";
        $title = _("The bias to be given to the source by the selection algorithm.
                                                                                    Bias must be > 0 , typical values would be between 0.75 and 1.5
                                                                                    and default is 1 (no bias).  A bias of 2 will cause the items
                                                                                    to look twice as recent to the algorithm. A bias of 0.5 to
                                                                                    look twice as old. Be carefull, a bias of 2 will statistically
                                                                                    cause the feed to have MORE than twice as many items displayed.");
       
        $html .= "<a href=\"#\" title=\"$title\">"._("Algorithm bias for this feed")."</a>\n";      
        $html .= ": </div>\n";
        $html .= "<div class='admin_element_data'>\n";

        $name = "rss_aggregator_" . $this->id . "_feed_" . md5($feed_row['url']) . "_bias";
        $value = $feed_row['bias'];
        $html .= "<input type='text' size='60' value='$value' name='$name'>\n";

        $html .= "</div>\n";
        $html .= "</li>\n";
        $html .= "</ul>\n";
        $html .= "</fieldset>\n";
        return $html;
    }

    /**
     * Feed-specific section of the admin interface
     *
     * @param array $feed_row The database row of the content_rss_aggregator_feeds table
     *
     * @return void
    
     */
    private function processFeedAdminUI($feed_row) {
        
        $db = AbstractDb::getObject();

        $original_url = $db->escapeString($feed_row['url']);

        /*
         * bias
         */
        $name = "rss_aggregator_" . $this->id . "_feed_" . md5($feed_row['url']) . "_bias";
        $original_bias = $db->escapeString($feed_row['bias']);
        $bias = $db->escapeString($_REQUEST[$name]);

        if (is_numeric($bias) && $bias > 0 && $bias != $original_bias) {
            /*
             * Only update database if the mode is valid and there is an actual change
             */
            $db->execSqlUpdate("UPDATE content_rss_aggregator_feeds SET bias = '$bias' WHERE content_id = '$this->id' AND url='$original_url'", false);
            $this->refresh();
        }
        elseif (!is_numeric($bias) || $bias <= 0) {
            echo _("The bias must be a positive real number");
        } else {
            /*
             * Successfull, but nothing modified
             */
        }

        /*
         * default_publication_interval
         */
        $name = "rss_aggregator_" . $this->id . "_feed_" . md5($feed_row['url']) . "_default_publication_interval";

        if (isset ($_REQUEST[$name])) {
            $original_default_publication_interval = $db->escapeString($feed_row['default_publication_interval']);
            $default_publication_interval = $db->escapeString($_REQUEST[$name] * (60 * 60 * 24));

            if ((empty ($default_publication_interval) || (is_numeric($default_publication_interval) && $default_publication_interval > 0)) && $default_publication_interval != $original_default_publication_interval) {
                /*
                 * Only update database if the mode is valid and there is an actual change
                 */
                if (empty ($default_publication_interval)) {
                    $default_publication_interval = 'NULL';
                }

                $db->execSqlUpdate("UPDATE content_rss_aggregator_feeds SET default_publication_interval = $default_publication_interval WHERE content_id = '$this->id' AND url='$original_url'", false);
                $this->refresh();
            }
            elseif (!is_numeric($bias) || $bias <= 0) {
                echo _("The default publication must must be a positive integer or empty");
            } else {
                /*
                 * Successfull, but nothing modified
                 */
            }
        }

        /*
         * URL, we must change it last or we won't find the row again
         */
        $name = "rss_aggregator_" . $this->id . "_feed_" . md5($feed_row['url']) . "_url";
        $url = $db->escapeString($_REQUEST[$name]);

        if (!empty ($url) && $url != $feed_row['url']) {
            /*
             * Only update database if the mode is valid and there is an actual change
             */
            $db->execSqlUpdate("UPDATE content_rss_aggregator_feeds SET url = '$url' WHERE content_id = '$this->id' AND url='$original_url'", false);
            $this->refresh();
        }
        elseif (empty ($url)) {
            echo _("The URL cannot be empty!");
        } else {
            /*
             * Successfull, but nothing modified
             */
        }
    }
    /** This function will be called by MainUI for each Content BEFORE any getUserUI function is called to allow two pass Content display.
     * Two pass Content display allows such things as modyfying headers, title, creating content type that accumulate content from other pieces (like RSS feeds)
     * @return null
     */
    public function prepareGetUserUI() {
         if (self::isContentTypeFunctional()) {
                            $this->initFeedPressReview();
                                   static $headerWritten = false;
        if(!$headerWritten) {
        $mainui = MainUI::getObject();
        //$mainui->appendStylesheetURL(BASE_URL_PATH . 'lib/feedpressreview/feedpressreview.css');
        $mainui->appendHtmlHeadContent("<script type='text/javascript' src='".BASE_URL_PATH . 'lib/feedpressreview/feedpressreview.js'."'></script>");
        }
         }
        return parent :: prepareGetUserUI();
    }
    

    /**
     * Retreives the user interface of this object.
     *
     * @return string The HTML fragment for this interface
     */
    public function getUserUI() {
        // Init values
        $html = '';
        if (self::isContentTypeFunctional()) {
            $this->initFeedPressReview();
            try {
                $html = $this->press_review->getOutputHtml($this->content_rss_aggregator_row['number_of_display_items'], $this->getDisplayEmptyFeed(), _("See more"), _("-"));
            } catch (Exception $e) {
                $html = sprintf(_("Could not get RSS feed: %s"), $feed_row['url']);
            }
            /* Handle hyperlink clicktrough logging */
            $html = $this->replaceHyperLinks($html);

        } else {
            $html = _("RSS support is disabled");
        }
        $this->setUserUIMainDisplayContent($html);
        return parent :: getUserUI();
    }

    /**
     * Reloads the object from the database.
     *
     * Should normally be called after a set operation.
     *
     * This function is private because calling it from a subclass will call
     * the constructor from the wrong scope
     *
     * @return void
    
     */
    private function refresh() {
        $this->__construct($this->id);
    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */