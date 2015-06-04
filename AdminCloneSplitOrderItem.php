<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 6/4/2015
 * Time: 11:29 AM
 */

class AdminSplitOrderItem {

    protected $itemId;
    protected $config;
    protected $parentId;
    protected $childItems;
    function __construct($config = array())
    {
        $this->config = $config;

    }

    function getTotalQty()
    {

    }

    function setItem($itemId)
    {
        $this->itemId = $itemId;
        $this->getParentItemId();
    }

    // get the item parent id
    function getParentItemId()
    {
        $parentItemKey = $this->config['parentItemKey'];
        $itemId = $this->itemId;
        $parentId = wc_get_order_item_meta($itemId, $parentItemKey);
        $this->parentId = $parentId;
       return $parentId;
    }

    // get the child items using parent id
    function getChildItemsByParent()
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $table = $prefix . 'woocommerce_order_itemmeta';
        $tableOrder = $prefix . 'woocommerce_order_items';
        $tableWp = $prefix . 'posts';
        $parentItemKey = $this->config['parentItemKey'];
        $parentId = $this->parentId;
//        $sql = $wpdb->prepare("SELECT order_item_id FROM $table
//        WHERE meta_key = '$parentItemKey' AND meta_value = %d",$parentId);

        $sql = $wpdb->prepare("SELECT im.*, wp.post_status FROM $table as imm
        LEFT JOIN $tableOrder as im ON imm.order_item_id = im.order_item_id
        LEFT JOIN $tableWp wp ON im.order_id = wp.ID
        WHERE imm.meta_key = '$parentItemKey'
        AND imm.meta_value = %d
        AND wp.post_status != 'trash'",$parentId);

        $items = array();
        $rows = $wpdb->get_results($sql);
        if(!empty($rows)) {
            $i = 0;
            foreach($rows as $row) {
                $itemId = $row->order_item_id;
                if($this->itemId == $itemId) continue; // do not include current line item
                $items[$i]['item_id'] = $itemId;
                $items[$i]['qty'] = wc_get_order_item_meta($itemId, '_qty');
                $i++;
            }
        }
        $this->childItems = $items;
        return $items;
    }
    // Organize the line item for quantity total
    function tidyLineItem()
    {
        $arr = array();

        $arr['current_item_id'] = $this->itemId;
        $arr['parent_id'] = $this->parentId;
        $parentId = $this->parentId;
        $arr['parent_qty'] = wc_get_order_item_meta($parentId, '_qty');
        $arr['parent_original_qty'] = $this->getParentOrigQty();
        $arr['child_total'] = $this->getChildTotal();
        $arr['child_items'] = $this->childItems;
        return $arr;
    }

    // Get the parent order line item orginal quantity
    function getParentOrigQty()
    {

        $parentId = $this->parentId;
        $origKey = $this->config['origKey'];
        $qty = wc_get_order_item_meta($parentId, $origKey);

        return $qty;
    }

    function getChildTotal()
    {
        $childItems = $this->getChildItemsByParent();
        if(!empty($childItems)) {
            $qty = 0;
            foreach($childItems  as $child) {
                $qty = $qty + $child['qty'];
            }

            return $qty;
        } else {
            return '0';
        }
    }
}