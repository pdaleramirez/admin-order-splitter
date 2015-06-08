<?php
/*
	Plugin Name: Admin Order Splitter
	Description: Splits Woocommerce order at the backed order page.
	Version: 1.1.2
	Author: Precious Dale Ramirez
	Author URI: http://www.wplab.com
*/
require 'AdminSplitOrder.php';
require 'AdminCloneSplitOrder.php';
require 'AdminCloneSplitOrderItem.php';
class adminOrderSplitter {

    protected $postType = 'shop_order';
    protected $textDomain = 'admin_order_splitter';
    public $aosField = 'aos-shipping';
    protected $cloneMetaKey = 'aos-cloned-count';
    protected $origKey = '_orig_qty';
    protected $parentItemKey = '_parent_item_id';
    protected $parentId;

    public function __construct()
    {


        add_action('woocommerce_admin_order_actions_end', array($this,'woocommerce_admin_order_actions_end'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_filter("woocommerce_order_number", array($this, 'woocommerce_order_number'),10,2);
        add_filter("woocommerce_get_item_count", array($this, "woocommerce_get_item_count"), 10,3);

        add_filter("woocommerce_hidden_order_itemmeta", array($this, "woocommerce_hidden_order_itemmeta"));
        add_filter("woocommerce_attribute_label", array($this, "woocommerce_attribute_label"));
        add_filter("woocommerce_order_add_product", array($this, "woocommerce_order_add_product"), 10, 5);
        add_action("woocommerce_after_order_itemmeta", array($this, "woocommerce_after_order_itemmeta"), 10,3);
        // make sure priority is last to make this hook work
        add_action('woocommerce_process_shop_order_meta', array($this,'woocommerce_process_shop_order_meta'),999,2);
        add_filter('woocommerce_order_amount_item_total', array($this, 'woocommerce_order_amount_item_total'),10,5);
    }

    function admin_notices()
    {
        global $pagenow;
        if($pagenow == 'edit.php' ) {
            if(isset($_GET['post_type']) && $_GET['post_type'] == 'shop_order') {
                if(isset($_GET['aos-status'])) {
                    $orderId = $_GET['aos-status'];
                    $orderObj = wc_get_order( $orderId  );

                    ?>
                    <div class="updated">
                        <p><?php esc_html_e( 'Order #' . $orderObj->get_order_number($orderId) . ' has been cloned.',
                                'text-domain' ); ?></p>
                    </div>
                    <?php
                }
            }
        }

        if($pagenow == 'post.php' ) {
            if(isset($_GET['post'])) {
                if(isset($_GET['aos-status-exceed'])) {
                    ?>
                    <div class="error">
                    <?php
                    $itemIds = $_GET['aos-status-exceed'];

                    $text = '';

                    foreach($itemIds as $itemId) {


                    $product = wc_get_order_item_meta( $itemId, '_product_id' );
                    $pObj = new WC_Product($product);



                    $text.= '<p>' . __( ' Product ' . $pObj->get_title() . ' should not exceed the original
                    quantity.', $this->textDomain ) . '</p>';


                    }
                    echo $text;
                    ?>
                    </div>
                    <?php
                }
            }
        }
    }

    function woocommerce_admin_order_actions_end($order)
    {
        $id = $order->id;

        ?>
        <a href="?post_type=shop_order&aos-clone-order=<?php echo $id; ?>">Clone Order</a>
        <?php
    }

    function admin_init()
    {

        if(isset($_GET['post_type']) && $_GET['post_type'] == 'shop_order' && isset($_GET['aos-clone-order'])) {
            $orderId = $_GET['aos-clone-order'];
            $country = WC()->countries->get_address_fields( '', 'billing_' );
            $country = WC()->countries->get_base_country();
            $config = array();
            $config['cloneMetaKey'] = $this->cloneMetaKey;
            $config['origKey'] = $this->origKey;
            $config['parentItemKey'] = $this->parentItemKey;

            $cloneOrderObj = new AdminCloneSplitOrder($orderId, $config);
            $result = $cloneOrderObj->runCloneOrder();

        }
    }

    function woocommerce_order_number($number, $obj)
    {
        if(is_admin()) {
           // if((isset($_GET['post_type']) && $_GET['post_type'] == 'shop_order') ) {
                $cloneText = '';
                $orderId = $obj->id;

                $isClone = $this->isClone($orderId);
                if($isClone) {
                    $parentId = wp_get_post_parent_id($orderId);

                    $parentObj = new WC_Order($parentId);

                    $parentOrderNumber = $parentObj->get_order_number();
                    $cloneText = " is CLONED from order #" . $parentOrderNumber;
                    $cloneText.= "\n Clone Number: " . $isClone;
                }
            //}
        }
        return $number . $cloneText;
    }

    function woocommerce_get_item_count($count, $type, $obj)
    {
        $orderId = $obj->id;
        $isClone = $this->isClone($orderId);
        if($isClone) {
            $items = $obj->get_items($type);

            $count = 0;

            foreach ($items as $item) {

                if (!empty($item['qty'])) {
                    $count += $item['qty'];
                }
            }
        }
        return $count;
    }
    function woocommerce_hidden_order_itemmeta($arr)
    {
        $arr[] = $this->origKey;
        $arr[] = $this->parentItemKey;
        $arr[] = '_orig_subtotal';
        $arr[] = '_orig_subtotal_tax';
        $arr[] = '_orig_total';
        $arr[] = '_orig_tax';

        return $arr;
    }

    function woocommerce_attribute_label($label)
    {
        if($label == $this->origKey) {
            $label = "Original Qty";
        }

        if($label == '_orig_total') {
            $label = "Original Total";
        }

        if($label == '_orig_tax') {
            $label = "Original Tax";
        }
        return $label;
    }

    function woocommerce_order_add_product($id, $item_id, $product, $qty, $args)
    {
        if(isset($_GET['aos-clone-order'])) {
            $origKey = $this->origKey;

        }
    }

    function woocommerce_after_order_itemmeta($item_id, $item, $_product)
    {
        global $theorder;

        $origKey = $this->origKey;


        $origKeyParent = $this->origKey;
        $parentItemKey = $this->parentItemKey;

        $parentId = wc_get_order_item_meta($item_id, $parentItemKey);

        $origKey = substr($origKey, 1);
        if($parentId) {
            $qty = wc_get_order_item_meta($parentId, $origKeyParent);
            $total = wc_get_order_item_meta($parentId, '_orig_total');
            $tax = wc_get_order_item_meta($parentId, '_orig_tax');
        } elseif(!empty($item[$origKey])) {
            $qty = $item[$origKey];
            $total = $item['_orig_total'];
            $tax = $item['_orig_tax'];
        }
        if(!empty($qty)) {
            echo ' <strong>Orignal Qty:</strong> ' . $qty . '<br />';
            echo ' <strong>Orignal Total:</strong> ' . $total . '<br />';
            echo ' <strong>Orignal Tax:</strong> ' . $tax . '<br />';
        }


    }

    // Handles quantiy update for each item
    function woocommerce_process_shop_order_meta($post_id, $post)
    {
        global $pagenow;

        $isClone = $this->isClone($post_id); // Only clone orders are processed
        if(!$isClone) return;
        //wc_get_order_item_meta
        $itemMetas = $_POST['order_item_id'];
        $origKey = $this->origKey;
        $parentItemKey = $this->parentItemKey;
        $origKey = $this->origKey;
        $config = array();
        $config['parentItemKey'] = $parentItemKey;
        $config['origKey'] = $origKey;
        if(!empty($itemMetas)) {
            $itemObj = new AdminSplitOrderItem($config);
            $resArr = array();
            $errors = array();

            foreach($itemMetas as $metaId) {
                $itemObj->setItem($metaId);
                $arr = $itemObj->tidyLineItem();
                $resArr[] = $arr;
                $itemQty = $_POST['order_item_qty'][$metaId];
                $totalQty = $arr['child_total'] + $itemQty;

                $parentId = wc_get_order_item_meta($metaId, $parentItemKey);
                $this->parentId = $parentId;
                $parentOrigQty = wc_get_order_item_meta($parentId, $origKey);


                if($totalQty > $parentOrigQty) {
                    $errors['aos-status-exceed'][] = $metaId;

                } else {
                    $diff = $parentOrigQty - $totalQty;
                    wc_update_order_item_meta( $parentId, "_qty", $diff );

                    $this->updateLineTotals($metaId, $itemQty);
                    $this->updateLineTotals($parentId, $diff); // update parent or original id as well



                }


            }
            if(!empty($errors)) {
                $str = http_build_query($errors);
                $redUrl = admin_url($pagenow) . "?post=" . $post_id . "&action=edit&" . $str;

                wp_redirect( $redUrl );
                exit;
            }
        }




    }

    function updateLineTotals($itemId, $qty)
    {

        $origKey = $this->origKey;


        $parentId = $this->parentId;
        $parentOrigQty = wc_get_order_item_meta($parentId, $origKey);
        $parentOrigSubtotal = wc_get_order_item_meta($parentId, '_orig_subtotal');
        $parentOrigSubtotalTax = wc_get_order_item_meta($parentId, '_orig_subtotal_tax');
        $parentOrigTotal = wc_get_order_item_meta($parentId, '_orig_total');
        $parentOrigTax = wc_get_order_item_meta($parentId, '_orig_tax');

        // Calculate the one value
        $oneParentOrigSubtotal = $parentOrigSubtotal / $parentOrigQty;
        $oneParentOrigSubtotalTax = $parentOrigSubtotalTax / $parentOrigQty;
        $oneParentOrigTotal = $parentOrigTotal / $parentOrigQty;
        $oneParentOrigTax = $parentOrigTax / $parentOrigQty;

        // Stores values based on the quanity inputted
        $lineSubtotal = $oneParentOrigSubtotal * $qty;
        $lineSubtotalTax = $oneParentOrigSubtotalTax * $qty;
        $lineTotal = $oneParentOrigTotal * $qty;
        $lineTax = $oneParentOrigTax * $qty;

        wc_update_order_item_meta( $itemId, "_line_subtotal", $lineSubtotal );

        wc_update_order_item_meta( $itemId, "_line_subtotal_tax", $lineSubtotalTax );
        wc_update_order_item_meta( $itemId, "_line_total", $lineTotal );

        wc_update_order_item_meta( $itemId, "_line_tax", $lineTax );
        $lineTaxParent = wc_get_order_item_meta( $parentId, "_line_tax_data", true);
        $taxId = key($lineTaxParent['total']); // get the tax id line

        $taxData = array();
        $taxData['total'] = array($taxId => $lineTax);
        $taxData['subtotal'] = array($taxId => $lineSubtotalTax);

        wc_update_order_item_meta( $itemId, "_line_tax_data", $taxData );
    }

    function isClone($orderId)
    {
        $cloneMetaKey = $this->cloneMetaKey;
        $isClone = get_post_meta($orderId, $cloneMetaKey, true);
        if($isClone) {
            return $isClone;
        } else {
            return false;
        }
    }
    // use original cost calculation if order has been cloned or has original quantity
    function woocommerce_order_amount_item_total($price, $obj, $item, $inc_tax, $round)
    {
        $origKey = $this->origKey;
        $key = substr($origKey, 1);
        $origQty = $item[$key];

        if(isset($origQty)) {
            if ( $inc_tax ) {
                $price = ( $item['orig_total'] + $item['orig_tax'] ) / max( 1, $origQty );
            } else {
                $price = $item['orig_total'] / max( 1, $origQty );
            }

            $price = $round ? round( $price, 2 ) : $price;

        }

        return $price;
    }

}

$admin_order_splitter = new adminOrderSplitter();


if(!function_exists('dd')) {
    function dd($arr, $echo = true)
    {
        $ret = '<pre>' . print_r($arr, true) . '</pre>';
        if($echo) {
            echo $ret;
        } else {
            return $ret;
        }
    }
}

add_action('wp', 'aos_debug_code');
function aos_debug_code()
{
    if(isset($_GET['aos_debug_code'])) {
        $items = WC()->cart->get_cart();
        dd($items);
    }

}