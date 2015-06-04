<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 5/28/2015
 * Time: 11:35 AM
 */

class AdminSplitOrder {

    protected $order;
    protected $lineOrders;
    protected $aosField;
    protected $aosMetaKey;
    public function __construct(WC_Order $order, $main)
    {
        $this->order = $order;
        $this->aosField = $main->aosField;
        $this->aosMetaKey = $main->aosMetaKey;

    }

    function getItems()
    {
        $order = $this->order;
        $line_items = $this->getLineOrders();
        $line_items_shipping = $order->get_items( 'shipping' );
        $shipping = array_values($line_items_shipping);

        ?>
        <div class="woocommerce_order_items_wrapper wc-order-items-editable">
        <table cellpadding="0" cellspacing="0" class="woocommerce_order_items">
        <tbody id="order_line_items">
        <?php
        if(!empty($line_items)) {
            ?>
            <tr>
                <th>Thumb</th>
                <th width="300">Item</th>
                <th width="100">Cost</th>
                <th width="200">Shipping <br /> Method</th>
                <th>Shipping <br /> Class</th>
                <th>Notes</th>
            </tr>
            <?php
            $aosField = $this->aosField;

            $aosMetaKey = $this->aosMetaKey;
            foreach ( $line_items as $key => $item ) {
                $_product  = $order->get_product_from_item( $item );
                $item_meta = $order->get_item_meta( $item['item_id'] );
                $meta_qty = $item_meta['_qty'][0];
                $subtotal = $item_meta['_line_subtotal'][0];

                $cost = ($subtotal / $meta_qty);
                include 'admin-order-splitter-item.php';
            }
        }
        ?>

        </tbody>
        <tbody>
        <tr>
            <td colspan="4">
                <?php
                $this->groupShipMethods();
                ?>
            </td>
        </tr>
        </tbody>
        </table>
        </div>
        <?php
    }

    function getLineOrders()
    {
        $lineOrders = array();
        $order = $this->order;
        $line_items = $order->get_items( 'line_item' );

        if(!empty($line_items)) {



            $i = 0;
            foreach ( $line_items as $item_id => $item ) {
                if($item['qty'] > 1) {
                    for($x = 1; $x <= $item['qty']; $x++) {

                        $lineOrders[$i] = $item;
                        $lineOrders[$i]['item_id'] = $item_id;
                        $lineOrders[$i]['quantity_number'] = $x;
                        $lineOrders[$i]['item_meta'] = array();

                        $i++;
                    }
                } else {
                    $lineOrders[$i] = $item;
                    $lineOrders[$i]['item_id'] = $item_id;
                    $lineOrders[$i]['item_meta'] = array();
                    $i++;
                }
            }
        }

        return $lineOrders;
    }

    function groupShipMethods()
    {
        $order = $this->order;
        $post_id = $order->id;
        $aosMetaKey = $this->aosMetaKey;
        $orderMeta = get_post_meta($post_id, $aosMetaKey, true);
        $groups = array();
        if(!empty($orderMeta['method'])) {
            $methods = $orderMeta['method'];
            foreach($methods as $method) {
                $groups[$method][] = 1;
            }
        }
       if(!empty($groups)) {
           $i = 1;
           foreach($groups as $titleKey => $group) {
               echo  "<p><label>" .$titleKey . '</label> (' . count($group) . ') | new order number: <strong>' . $post_id . '-' . $i . '</strong></p>';
               $i++;
           }
       }
    }
}