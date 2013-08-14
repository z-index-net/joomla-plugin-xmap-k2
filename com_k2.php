<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

require_once JPATH_SITE . '/components/com_k2/helpers/route.php';
 
defined('_JEXEC') or die;

final class xmap_com_k2 {
	
	private static $layouts = array('category', 'tag', 'user');

    public static function getTree(XmapDisplayer &$xmap, stdClass &$parent, array &$params) {
    	$uri = new JUri($parent->link);
    	
    	if(!in_array($uri->getVar('layout'), self::$layouts)) {
    		return;
    	}
    	
    	$include_items = JArrayHelper::getValue($params, 'include_items');
    	$include_items = ($include_items == 1 || ($include_items == 2 && $xmap->view == 'xml') || ($include_items == 3 && $xmap->view == 'html'));
    	$params['include_items'] = $include_items;
    	
    	$show_unauth = JArrayHelper::getValue($params, 'show_unauth');
    	$show_unauth = ($show_unauth == 1 || ( $show_unauth == 2 && $xmap->view == 'xml') || ( $show_unauth == 3 && $xmap->view == 'html'));
    	$params['show_unauth'] = $show_unauth;
    	
    	$params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());
    	
    	$priority = JArrayHelper::getValue($params, 'category_priority', $parent->priority);
    	$changefreq = JArrayHelper::getValue($params, 'category_changefreq', $parent->changefreq);
    	
    	if($priority == -1) {
    		$priority = $parent->priority;
    	}
    	
    	if($changefreq == -1) {
    		$changefreq = $parent->changefreq;
    	}
    		
    	$params['category_priority'] = $priority;
    	$params['category_changefreq'] = $changefreq;
    	
    	$priority = JArrayHelper::getValue($params, 'item_priority', $parent->priority);
    	$changefreq = JArrayHelper::getValue($params, 'item_changefreq', $parent->changefreq);
    	
    	if($priority == -1) {
    		$priority = $parent->priority;
    	}
    	
    	if($changefreq == -1) {
    		$changefreq = $parent->changefreq;
    	}
    	
    	$params['item_priority'] = $priority;
    	$params['item_changefreq'] = $changefreq;
    	
    	switch($uri->getVar('layout')) {
    		case 'category':
    			$categories = JFactory::getApplication()->getMenu()->getItem($parent->id)->params->get('categories');
    			if(count($categories) == 1) {
    				self::getItems($xmap, $parent, $params, 'category', $categories[0]);
    			}elseif(count($categories) > 1) {
	    			self::getCategoryTree($xmap, $parent, $params, 0, $categories);
    			}else{
	    			self::getCategoryTree($xmap, $parent, $params, 0);
    			}
    		break;
    		
    		case 'tag': 
    			self::getItems($xmap, $parent, $params, 'tag', $uri->getVar('tag'));
    		break;
    		
    		case 'user':
    			self::getItems($xmap, $parent, $params, 'user', $uri->getVar('id'));
    		break;
    	}
    }
    
    private static function getCategoryTree(XmapDisplayer &$xmap, stdClass &$parent, array &$params, $parent_id, $ids=null) {
    	$db = JFactory::getDbo();
    
    	$query = $db->getQuery(true)
		    	->select(array('c.id', 'c.name', 'c.parent'))
		    	->from('#__k2_categories AS c')
		    	->where('c.published = 1')
		    	->where('c.trash = 0')
		    	->order('c.ordering');
    	
    	if(!empty($ids)) {
    		$query->where('c.id IN(' . implode(',', $db->quote($ids)) . ')');
    	}else{
    		$query->where('c.parent =' . $db->quote($parent_id));
    	}
    
    	if (!$params['show_unauth']) {
    		$query->where('c.access IN(' . $params['groups'] . ')');
    	}
    
    	$db->setQuery($query);
    	$rows = $db->loadObjectList();
    
    	if(empty($rows)) {
    		return;
    	}
    
    	$xmap->changeLevel(1);
    
    	foreach($rows as $row) {
    		$node = new stdclass;
    		$node->id = $parent->id;
    		$node->name = $row->name;
    		$node->uid = $parent->uid . '_cid_' . $row->id;
    		$node->browserNav = $parent->browserNav;
    		$node->priority = $params['category_priority'];
    		$node->changefreq = $params['category_changefreq'];
    		$node->pid = $row->parent;
    		$node->link = K2HelperRoute::getCategoryRoute($row->id);
    			
    		if ($xmap->printNode($node) !== false) {
    			self::getCategoryTree($xmap, $parent, $params, $row->id);
    			if ($params['include_items']) {
    				self::getItems($xmap, $parent, $params, 'category', $row->id);
    			}
    		}
    	}
    
    	$xmap->changeLevel(-1);
    }
    
    private static function getItems(XmapDisplayer &$xmap, stdClass &$parent, array &$params, $mode, $linkId) {
    	$db = JFactory::getDbo();
    	$now = JFactory::getDate()->toSql();
    	
    	$query = $db->getQuery(true)
		    	->select(array('i.id', 'i.title', 'i.catid'))
		    	->from('#__k2_items AS i')
		    	->where('i.published = 1')
		    	->where('i.trash = 0')
		    	->where('(i.publish_up = ' . $db->quote($db->getNullDate()) . ' OR i.publish_up <= ' . $db->quote($now) . ')')
		    	->where('(i.publish_down = ' . $db->quote($db->getNullDate()) . ' OR i.publish_down >= ' . $db->quote($now) . ')')
		    	->order('i.ordering');
    
    	if (!$params['show_unauth']) {
    		$query->where('i.access IN(' . $params['groups'] . ')');
    	}
    	
    	switch($mode) {
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
    	$rows = $db->loadObjectList();
    
    	if(empty($rows)) {
    		return;
    	}
    
    	$xmap->changeLevel(1);
    
    	foreach($rows as $row) {
    		$node = new stdclass;
    		$node->id = $parent->id;
    		$node->name = $row->title;
    		$node->uid = $parent->uid . '_' . $row->id;
    		$node->browserNav = $parent->browserNav;
    		$node->priority = $params['item_priority'];
    		$node->changefreq = $params['item_changefreq'];
    		$node->link = K2HelperRoute::getItemRoute($row->id, $row->catid);
    			
    		$xmap->printNode($node);
    	}
    
    	$xmap->changeLevel(-1);
    }
}