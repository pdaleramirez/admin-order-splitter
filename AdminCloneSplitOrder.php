<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 6/2/2015
 * Time: 2:00 PM
 */

class AdminCloneSplitOrder {

    protected $orderObj;
    protected $orderId;
    protected $newOrderObj;
    protected $newOrderId;
    protected $config;
    function __construct($orderId, $config = array())
    {
        $this->orderObj = wc_get_order( $orderId  );
        $this->orderId = $orderId;
        $this->config = $config;



    }

    function runCloneOrder()
    {
        global $pagenow;


        $orderData = $this->getOrderData();
        $metas = $this->getOrderMeta();
        $products = $this->getOrderProducts();

        $newOrderId = wp_insert_post( apply_filters( 'woocommerce_new_order_data', $orderData ), true );

        if ( is_wp_error( $newOrderId ) ) {
            return $newOrderId;
        }
        if(!empty($metas)) {
            foreach($metas as $metaKey => $metaValue) {
                update_post_meta($newOrderId, $metaKey, $metaValue);
            }
        }
        update_post_meta( $newOrderId, '_customer_user', get_current_user_id() );

        // Initial total values for 0 quantity on cloned orders

        $newOrderObj = new WC_Order($newOrderId);
        if(!empty($products)) {
            foreach($products as $product) {

                $taxId = key($product['totals']['tax_data']['total']);

                $emptyTotals = array('subtotal' => 0, 'subtotal_tax' => 0,
                    'total' => 0, 'tax' => 0, 'tax_data' => array( 'total' => array( $taxId => 0 ), 'subtotal' =>
                    array( $taxId => 0 )));

                $item_id = $newOrderObj->add_product(
                    $product['data'],
                    //$product['quantity'],
                    0,
                    array(
                        'variation' => $product['variation'],
                        'totals'    => $emptyTotals
                    )
                );



                $parentItemKey = $this->config['parentItemKey'];
                // Stores parent or original item id for the cloned line
                wc_update_order_item_meta( $item_id, $parentItemKey, $product['item_id'] );

                // Store original values for the item line
                $origKey = $this->config['origKey'];
                if(!wc_get_order_item_meta( $product['item_id'], $origKey, $product['quantity'] )) {
                    wc_update_order_item_meta( $product['item_id'], $origKey, $product['quantity'] );
                }

                $origSubTotalKey = '_orig_subtotal';
                if(!wc_get_order_item_meta( $product['item_id'], $origSubTotalKey, $product['totals']['subtotal'] )) {
                    wc_update_order_item_meta( $product['item_id'], $origSubTotalKey, $product['totals']['subtotal'] );
                }

                $origSubTotalTaxKey = '_orig_subtotal_tax';
                if(!wc_get_order_item_meta( $product['item_id'], $origSubTotalTaxKey, $product['totals']['subtotal_tax'] )) {
                    wc_update_order_item_meta( $product['item_id'], $origSubTotalTaxKey, $product['totals']['subtotal_tax'] );
                }

                $origTotalKey = '_orig_total';
                if(!wc_get_order_item_meta( $product['item_id'], $origTotalKey, $product['totals']['total'] )) {
                    wc_update_order_item_meta( $product['item_id'], $origTotalKey, $product['totals']['total'] );
                }

                $origTaxKey = '_orig_tax';
                if(!wc_get_order_item_meta( $product['item_id'], $origTaxKey, $product['totals']['tax'] )) {
                    wc_update_order_item_meta( $product['item_id'], $origTaxKey, $product['totals']['tax'] );
                }

                if ( ! $item_id ) {
                    throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 402 ) );
                }
            }
            //$newOrderObj->add_tax($taxId);
            $orderObj = $this->orderObj;

            $newOrderObj->add_tax( $taxId, $orderObj->get_total_tax(), $orderObj->get_shipping_tax() );
        }

        $billing_fields = WC()->countries->get_address_fields( $this->get_address_value( 'billing_country' ),
            'billing_' );
        $shipping_fields = WC()->countries->get_address_fields( $this->get_address_value( 'shipping_country' ),
            'shipping_' );

        $orderId = $this->orderId;
        // Billing address
        $billing_address = array();
        if ( $billing_fields ) {
            foreach ( array_keys( $billing_fields ) as $field ) {
                $field_name = str_replace( 'billing_', '', $field );
                $metaKey = '_' . $field;
                $billing_address[ $field_name ] = get_post_meta($orderId, $metaKey, true);
            }
        }

        // Shipping address.
        $shipping_address = array();
        if ( $shipping_fields ) {
            foreach ( array_keys( $shipping_fields ) as $field ) {
                $field_name = str_replace( 'shipping_', '', $field );
                $metaKey = '_' . $field;
                $shipping_address[ $field_name ] = get_post_meta($orderId, $metaKey, true);
            }
        }

        $payment_method = $this->orderObj->payment_method;
        $shipping_total = $this->orderObj->get_total_shipping();
        $cart_discount = $this->orderObj->get_cart_discount();
        $discount_total = $this->orderObj->get_total_discount();
        $tax_total = $this->orderObj->get_total_tax();
        $shipping_tax_total = $this->orderObj->get_shipping_tax();
        $total = $this->orderObj->get_total();

        $newOrderObj->set_address( $billing_address, 'billing' );
        $newOrderObj->set_address( $shipping_address, 'shipping' );
        $newOrderObj->set_payment_method( $payment_method );
//        $newOrderObj->set_total( $shipping_total, 'shipping' );
//        $newOrderObj->set_total( $cart_discount, 'cart_discount' );
//        $newOrderObj->set_total( $discount_total, 'cart_discount_tax' );
//        $newOrderObj->set_total( $tax_total, 'tax' );
//        $newOrderObj->set_total( $shipping_tax_total, 'shipping_tax' );
//        $newOrderObj->set_total( $total );
        $this->newOrderObj = $newOrderObj;
        $this->newOrderId = $newOrderId;
        $cloneCount = $this->getCloneCount();
        $cloneMetaKey = $this->config['cloneMetaKey'];
        update_post_meta($newOrderId, $cloneMetaKey, $cloneCount );

        if($newOrderId) {
            $redUrl = admin_url($pagenow) . "?post_type=shop_order&aos-status=" . $this->orderId;
            wp_redirect( $redUrl );
            exit;
        }
    }

    function getOrderData()
    {

        $orderId = $this->orderId;
        $orderObj = $this->orderObj;


        $post = get_post($orderId);
        $status = $post->post_status;


        $customer_note = $post->post_excerpt;

        $order_data = array();
        $order_data['post_type']     = 'shop_order';
        $order_data['post_status']   = 'wc-' . apply_filters( 'woocommerce_default_order_status', 'on-hold' );
        $order_data['ping_status']   = 'closed';
        $order_data['post_author']   = 1;
        $order_data['post_password'] = uniqid( 'order_' );
        $order_data['post_title']    = sprintf( __( 'Order &ndash; %s', 'woocommerce' ), strftime( _x( '%b %d, %Y @ %I:%M %p', 'Order date parsed by strftime', 'woocommerce' ) ) );
        $order_data['post_parent']   = $orderId;
        $order_data['post_excerpt'] = $customer_note;



        return $order_data;
    }

    function getOrderMeta()
    {
        $keys = array('_order_key', '_order_currency', '_prices_include_tax', '_customer_ip_address',
        '_customer_ip_address', '_customer_user_agent', '_created_via');
        $metas = array();
        $orderId = $this->orderId;
        if(!empty($keys)) {
            foreach($keys as $key) {
                $metas[$key] = get_post_meta($orderId, $key, true);
            }
        }

        return $metas;
    }

    function getOrderProducts()
    {
        $values = array();
        $orderObj = $this->orderObj;

        $line_items  = $orderObj->get_items( apply_filters( 'woocommerce_admin_order_item_types', 'line_item' ) );

        if(!empty($line_items)) {
            $i = 0;
            foreach ( $line_items as $item_id => $item ) {
                $values[$i]['item_id'] = $item_id;
                $_product  = $orderObj->get_product_from_item( $item );
                $variation_data = $_product->variation_data;
                $item_meta = $orderObj->get_item_meta( $item_id );

                $values[$i]['data'] = $_product;
                $values[$i]['quantity'] = $item_meta['_qty'][0];
                if(!empty($variation_data)) {
                    $values[$i]['variation'] = $variation_data;
                } else {
                    $values[$i]['variation'] = array();
                }
                $values[$i]['totals']['subtotal'] = $item_meta['_line_subtotal'][0];
                $values[$i]['totals']['subtotal_tax'] = $item_meta['_line_subtotal_tax'][0];
                $values[$i]['totals']['total'] = $item_meta['_line_total'][0];
                $values[$i]['totals']['tax'] = $item_meta['_line_tax'][0];

                $tax_data = maybe_unserialize($item_meta['_line_tax_data'][0]);

                if(!empty($tax_data['total'])) {

                    $values[$i]['totals']['tax_data'] = $tax_data;
                }
//                $values[$i]['totals']['subtotal'] = 0;
//                $values[$i]['totals']['subtotal_tax'] = 0;
//                $values[$i]['totals']['total'] = 0;
//                $values[$i]['totals']['tax'] = 0;


                $i++;
            }

        }

        return $values;
    }

    function get_address_value($name)
    {
        $orderId = $this->orderId;
        $country = get_post_meta($orderId, '_' . $name, true);
        if(empty($country)) {
           $country = WC()->countries->get_base_country();
        }
        return $country;
    }

    function getCloneCount()
    {
        $orderId = $this->orderId;
        $args = array(
            'post_parent' => $orderId,
            'post_type'   => 'shop_order',
            'posts_per_page' => -1,
            'post_status' => 'any' );

        $children = get_children( $args );
        $size = count($children);
        return $size;
    }
}