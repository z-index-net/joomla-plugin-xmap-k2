<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 - 2015 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

class xmap_com_k2
{
    /**
     * @var array
     */
    protected static $layouts = array('category', 'tag', 'user');

    /**
     * @var bool
     */
    protected static $enabled = false;

    public function __construct()
    {
        self::$enabled = JComponentHelper::isEnabled('com_k2');

        JLoader::register('K2HelperRoute', JPATH_SITE . '/components/com_k2/helpers/route.php');
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     *
     * @return bool
     */
    public static function getTree($xmap, stdClass $parent, array &$params)
    {
        $uri = new JUri($parent->link);

        if (!self::$enabled || !in_array($uri->getVar('layout'), self::$layouts))
        {
            return false;
        }

        $params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());

        $params['language_filter'] = JFactory::getApplication()->getLanguageFilter();

        $params['include_items'] = JArrayHelper::getValue($params, 'include_items', 1);
        $params['include_items'] = ($params['include_items'] == 1 || ($params['include_items'] == 2 && $xmap->view == 'xml') || ($params['include_items'] == 3 && $xmap->view == 'html'));

        $params['show_unauth'] = JArrayHelper::getValue($params, 'show_unauth', 0);
        $params['show_unauth'] = ($params['show_unauth'] == 1 || ($params['show_unauth'] == 2 && $xmap->view == 'xml') || ($params['show_unauth'] == 3 && $xmap->view == 'html'));

        $params['category_priority'] = JArrayHelper::getValue($params, 'category_priority', $parent->priority);
        $params['category_priority'] = ($params['category_priority'] == -1) ? $parent->priority : $params['category_priority'];

        $params['category_changefreq'] = JArrayHelper::getValue($params, 'category_changefreq', $parent->changefreq);
        $params['category_changefreq'] = ($params['category_changefreq'] == -1) ? $parent->changefreq : $params['category_changefreq'];

        $params['item_priority'] = JArrayHelper::getValue($params, 'item_priority', $parent->priority);
        $params['item_priority'] = ($params['item_priority'] == -1) ? $parent->priority : $params['item_priority'];

        $params['item_changefreq'] = JArrayHelper::getValue($params, 'item_changefreq', $parent->changefreq);
        $params['item_changefreq'] = ($params['item_changefreq'] == -1) ? $parent->changefreq : $params['item_changefreq'];

        switch ($uri->getVar('layout'))
        {
            case 'category':
                $categories = JFactory::getApplication()->getMenu()->getItem($parent->id)->params->get('categories');
                if (count($categories) == 1)
                {
                    return self::getItems($xmap, $parent, $params, 'category', $categories[0]);
                } elseif (count($categories) > 1)
                {
                    return self::getCategoryTree($xmap, $parent, $params, 0, $categories);
                } else
                {
                    return self::getCategoryTree($xmap, $parent, $params, 0);
                }
                break;

            case 'tag':
                return self::getItems($xmap, $parent, $params, 'tag', $uri->getVar('tag'));
                break;

            case 'user':
                return self::getItems($xmap, $parent, $params, 'user', $uri->getVar('id'));
                break;
        }
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     * @param string $mode
     * @param int $linkId
     *
     * @return bool
     */
    protected static function getItems($xmap, stdClass $parent, array &$params, $mode, $linkId)
    {
        if ($mode == 'category')
        {
            self::getCategoryTree($xmap, $parent, $params, $linkId);
        }

        if (!$params['include_items'])
        {
            return false;
        }

        $db = JFactory::getDbo();
        $now = JFactory::getDate('now', 'UTC')->toSql();

        $query = $db->getQuery(true)
            ->select(array('i.id', 'i.title', 'i.alias', 'i.catid', 'i.modified', 'i.metakey'))
            ->from('#__k2_items AS i')
            ->where('i.published = 1')
            ->where('i.trash = 0')
            ->where('(i.publish_up = ' . $db->quote($db->getNullDate()) . ' OR i.publish_up <= ' . $db->quote($now) . ')')
            ->where('(i.publish_down = ' . $db->quote($db->getNullDate()) . ' OR i.publish_down >= ' . $db->quote($now) . ')');

        if ($xmap->isNews)
        {
            $query->order('i.created DESC');
        } else
        {
            $query->order('i.ordering');
        }

        if (!$params['show_unauth'])
        {
            $query->where('i.access IN(' . $params['groups'] . ')');
        }

        if ($params['language_filter'])
        {
            $query->where('i.language IN(' . $db->quote(JFactory::getLanguage()->getTag()) . ', ' . $db->quote('*') . ')');
        }

        switch ($mode)
        {
            case 'category':
                $query->where('i.catid = ' . $db->Quote($linkId));
                break;

            case 'tag':
                $query->join('INNER', '#__k2_tags_xref AS x ON(x.itemID = i.id)');
                $query->join('INNER', '#__k2_tags AS t ON(t.id = x.tagID)');
                $query->where('t.name = ' . $db->Quote($linkId));
                $query->where('t.published = 1');
                break;

            case 'user':
                $query->where('i.created_by = ' . $db->Quote($linkId));
                break;
        }

        $db->setQuery($query);

        try
        {
            $rows = $db->loadObjectList();

        } catch (RuntimeException $e)
        {
            return false;
        }

        if (empty($rows))
        {
            return false;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row)
        {
            $node = new stdClass;
            $node->id = $parent->id;
            $node->name = $row->title;
            $node->title = $row->title;
            $node->modified = $row->modified;
            $node->keywords = $row->metakey;
            $node->newsItem = 1;
            $node->uid = $parent->uid . '_' . $row->id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['item_priority'];
            $node->changefreq = $params['item_changefreq'];
            $node->link = K2HelperRoute::getItemRoute($row->id . ':' . $row->alias, $row->catid);

            $xmap->printNode($node);
        }

        $xmap->changeLevel(-1);

        return true;
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     * @param int $parent_id
     * @param int[]|null $ids
     *
     * @return bool
     */
    protected static function getCategoryTree($xmap, stdClass $parent, array &$params, $parent_id, $ids = null)
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true)
            ->select(array('c.id', 'c.name', 'c.alias', 'c.parent'))
            ->from('#__k2_categories AS c')
            ->where('c.published = 1')
            ->where('c.trash = 0')
            ->order('c.ordering');

        if (!empty($ids))
        {
            $query->where('c.id IN(' . implode(',', $db->quote($ids)) . ')');
        } else
        {
            $query->where('c.parent =' . $db->quote($parent_id));
        }

        if (!$params['show_unauth'])
        {
            $query->where('c.access IN(' . $params['groups'] . ')');
        }

        if ($params['language_filter'])
        {
            $query->where('c.language IN(' . $db->quote(JFactory::getLanguage()->getTag()) . ', ' . $db->quote('*') . ')');
        }

        $db->setQuery($query);

        try
        {
            $rows = $db->loadObjectList();

        } catch (RuntimeException $e)
        {
            return false;
        }

        if (empty($rows))
        {
            return false;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row)
        {
            $node = new stdClass;
            $node->id = $parent->id;
            $node->name = $row->name;
            $node->uid = $parent->uid . '_cid_' . $row->id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['category_priority'];
            $node->changefreq = $params['category_changefreq'];
            $node->pid = $row->parent;
            $node->link = K2HelperRoute::getCategoryRoute($row->id . ':' . $row->alias);

            if ($xmap->printNode($node) !== false)
            {
                self::getItems($xmap, $parent, $params, 'category', $row->id);
            }
        }

        $xmap->changeLevel(-1);

        return true;
    }
}
