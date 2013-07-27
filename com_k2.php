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
	
	private static $views = array('itemlist');

    static function getTree(&$xmap, &$parent, &$params) {
    	$uri = new JUri($parent->link);
    	
    	if(!in_array($uri->getVar('view'), self::$views)) {
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
    	
    	switch($uri->getVar('view')) {
    		case 'itemlist':
    			$categories = JFactory::getApplication()->getMenu()->getItem($parent->id)->params->get('categories');
    			if(count($categories) == 1) {
    				self::getItems($xmap, $parent, $params, $categories[0]);
    			}elseif(count($categories) > 1) {
	    			self::getCategoryTree($xmap, $parent, $params, 0, $categories);
    			}else{
	    			self::getCategoryTree($xmap, $parent, $params, 0);
    			}
    		break;
    	}
    }
    
    private static function getCategoryTree(&$xmap, &$parent, &$params, $parent_id, $ids=null) {
    	$db = JFactory::getDbo();
    
    	$query = $db->getQuery(true)
    	->select(array('id', 'name', 'parent'))
    	->from('#__k2_categories')
    	->where('published = 1')
    	->order('ordering');
    	
    	if(!empty($ids)) {
    		$query->where('id IN(' . implode(',', $db->quote($ids)) . ')');
    	}else{
    		$query->where('parent =' . $db->quote($parent_id));
    	}
    
    	if (!$params['show_unauth']) {
    		$query->where('access IN(' . $params['groups'] . ')');
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
    		$node->pid = $row->parent_id;
    		$node->link = K2HelperRoute::getCategoryRoute($row->id);
    			
    		if ($xmap->printNode($node) !== false) {
    			self::getCategoryTree($xmap, $parent, $params, $row->id);
    			if ($params['include_items']) {
    				self::getItems($xmap, $parent, $params, $row->id);
    			}
    		}
    	}
    
    	$xmap->changeLevel(-1);
    }
    
    private static function getItems(&$xmap, &$parent, &$params, $catid) {
    	$db = JFactory::getDbo();
    
    	$query = $db->getQuery(true)
    	->select(array('id', 'title'))
    	->from('#__k2_items')
    	->where('catid = ' . $db->Quote($catid))
    	->where('published = 1')
    	->where('trash = 0')
    	->order('ordering');
    
    	if (!$params['show_unauth']) {
    		$query->where('access IN(' . $params['groups'] . ')');
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
    		$node->link = K2HelperRoute::getItemRoute($row->id, $catid);
    			
    		$xmap->printNode($node);
    	}
    
    	$xmap->changeLevel(-1);
    }
}