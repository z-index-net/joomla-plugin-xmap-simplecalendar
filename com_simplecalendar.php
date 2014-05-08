<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2014 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

final class xmap_com_simplecalendar
{

    private static $views = array('events');

    private static $enabled = false;

    public function __construct()
    {
        self::$enabled = JComponentHelper::isEnabled('com_simplecalendar');

        if (self::$enabled) {
            JLoader::register('SimpleCalendarHelperRoute', JPATH_SITE . '/components/com_simplecalendar/helpers/route.php');
        }
    }

    public static function getTree(XmapDisplayer &$xmap, stdClass &$parent, array &$params)
    {
        $uri = new JUri($parent->link);

        if (!self::$enabled || !in_array($uri->getVar('view'), self::$views)) {
            return;
        }

        $params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());

        $params['include_events'] = JArrayHelper::getValue($params, 'include_events', 1);
        $params['include_events'] = ($params['include_events'] == 1 || ($params['include_events'] == 2 && $xmap->view == 'xml') || ($params['include_events'] == 3 && $xmap->view == 'html'));

        $params['show_unauth'] = JArrayHelper::getValue($params, 'show_unauth', 0);
        $params['show_unauth'] = ($params['show_unauth'] == 1 || ($params['show_unauth'] == 2 && $xmap->view == 'xml') || ($params['show_unauth'] == 3 && $xmap->view == 'html'));

        $params['category_priority'] = JArrayHelper::getValue($params, 'category_priority', $parent->priority);
        $params['category_changefreq'] = JArrayHelper::getValue($params, 'category_changefreq', $parent->changefreq);

        if ($params['category_priority'] == -1) {
            $params['category_priority'] = $parent->priority;
        }

        if ($params['category_changefreq'] == -1) {
            $params['category_changefreq'] = $parent->changefreq;
        }

        $params['event_priority'] = JArrayHelper::getValue($params, 'event_priority', $parent->priority);
        $params['event_changefreq'] = JArrayHelper::getValue($params, 'event_changefreq', $parent->changefreq);

        if ($params['event_priority'] == -1) {
            $params['event_priority'] = $parent->priority;
        }

        if ($params['event_changefreq'] == -1) {
            $params['event_changefreq'] = $parent->changefreq;
        }

        self::getEvents($xmap, $parent, $params, $uri->getVar('catid'));
    }

    private static function getCategoryTree(XmapDisplayer &$xmap, stdClass &$parent, array &$params, array $catids)
    {
        $db = JFactory::getDBO();

        JArrayHelper::toInteger($catids);

        if (count($catids) == 1 && end($catids) == 0) {
            $catids = array(1);
        }

        $query = $db->getQuery(true)
            ->select(array('id', 'title', 'parent_id'))
            ->from('#__categories')
            ->where('extension = ' . $db->quote('com_simplecalendar'))
            ->order('lft');

        if (!empty($catids)) {
            $query->where('parent_id IN(' . implode(',', $catids) . ')');
        }

        if (!$params['show_unauth']) {
            $query->where('access IN(' . $params['groups'] . ')');
        }

        $db->setQuery($query);

        $rows = $db->loadObjectList();

        if (empty($rows)) {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row) {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->title;
            $node->uid = $parent->uid . '_cid_' . $row->id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['category_priority'];
            $node->changefreq = $params['category_changefreq'];
            $node->pid = $row->parent_id;
            $node->link = SimpleCalendarHelperRoute::getCategoryRoute($row->id);

            if ($xmap->printNode($node) !== false) {
                self::getCategoryTree($xmap, $parent, $params, array($row->id));
                if ($params['include_events']) {
                    self::getEvents($xmap, $parent, $params, array($row->id));
                }
            }
        }

        $xmap->changeLevel(-1);
    }

    private static function getEvents(XmapDisplayer &$xmap, stdClass &$parent, array &$params, array $catids)
    {
        $db = JFactory::getDBO();

        JArrayHelper::toInteger($catids);

        self::getCategoryTree($xmap, $parent, $params, $catids);

        if (count($catids) == 1 && end($catids) == 0) {
            return;
        }

        if (!$params['include_events']) {
            return;
        }

        $query = $db->getQuery(true)
            ->select('a.*')
            ->from('#__simplecalendar as a')
            ->join('INNER', '#__categories AS c ON a.catid = c.id')
            ->where('a.catid IN(' . implode(',', $catids) . ')')
            ->where('a.state = 1')
            ->order('a.start_dt');

        if (!$params['show_unauth']) {
            $query->where('c.access IN(' . $params['groups'] . ')');
        }

        $db->setQuery($query);

        $rows = $db->loadObjectList();

        if (empty($rows)) {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row) {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->name;
            $node->uid = $parent->uid . '_' . $row->id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['event_priority'];
            $node->changefreq = $params['event_changefreq'];
            $node->link = SimpleCalendarHelperRoute::getEventRoute($row->id . ':' . $row->alias, $row->catid);

            $xmap->printNode($node);
        }

        $xmap->changeLevel(-1);
    }
}