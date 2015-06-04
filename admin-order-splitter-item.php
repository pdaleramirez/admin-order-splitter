<?php
    $post_id = $order->id;


    $metaMethod = '';
    $metaClass = '';
    $metaNotes = '';
    $orderMeta = get_post_meta($post_id, $aosMetaKey, true);
    if(!empty($orderMeta)) {
        $metaMethod = $orderMeta['method'][$key];
        $metaClass = $orderMeta['class'][$key];
        $metaNotes = $orderMeta['notes'][$key];
    }



?>
<tr class="item" data-order_item_id="<?php echo $item_id; ?>">
    <td class="thumb">
        <?php if ( $_product ) : ?>
            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $_product->id ) . '&action=edit' ) ); ?>" class="tips" data-tip="<?php

            echo '<strong>' . __( 'Product ID:', 'woocommerce' ) . '</strong> ' . absint( $item['product_id'] );

            if ( $item['variation_id'] && 'product_variation' === get_post_type( $item['variation_id'] ) ) {
                echo '<br/><strong>' . __( 'Variation ID:', 'woocommerce' ) . '</strong> ' . absint( $item['variation_id'] );
            } elseif ( $item['variation_id'] ) {
                echo '<br/><strong>' . __( 'Variation ID:', 'woocommerce' ) . '</strong> ' . absint( $item['variation_id'] ) . ' (' . __( 'No longer exists', 'woocommerce' ) . ')';
            }

            if ( $_product && $_product->get_sku() ) {
                echo '<br/><strong>' . __( 'Product SKU:', 'woocommerce' ).'</strong> ' . esc_html( $_product->get_sku() );
            }

            if ( $_product && isset( $_product->variation_data ) ) {
                echo '<br/>' . wc_get_formatted_variation( $_product->variation_data, true );
            }

            ?>"><?php echo $_product->get_image( 'shop_thumbnail', array( 'title' => '' ) ); ?></a>
        <?php else : ?>
            <?php echo wc_placeholder_img( 'shop_thumbnail' ); ?>
        <?php endif; ?>

    </td>
    <td class="name" data-sort-value="<?php echo esc_attr( $item['name'] ); ?>">

        <?php echo ( $_product && $_product->get_sku() ) ? esc_html( $_product->get_sku() ) . ' &ndash; ' : ''; ?>
        <?php $qtnum = (isset($item['quantity_number']))? ' | ' . $item['quantity_number'] : ''; ?>
        <?php if ( $_product ) : ?>
            <a target="_blank" href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $_product->id ) . '&action=edit' ) ); ?>">
                <?php echo esc_html( $item['name'] ); ?>
            </a>
        <?php else : ?>
            <?php echo esc_html( $item['name'] ); ?>
        <?php endif; ?>
        <?php echo $qtnum; ?>

    </td>
    <td class="price">
        <?php

            echo get_woocommerce_currency_symbol() . $cost;
        ?>
    </td>
    <td class="shipping-method">
        <?php

        $methodTitles = array();
        $currentMethod = $shipping[0]['name'];
        $shipping_methods = WC()->shipping->load_shipping_methods();

        if(!empty($shipping_methods)) {
            foreach($shipping_methods as $method) {
                $methodTitles[] = $method->get_title();
            }
        }


        ?>
        <select name="<?php echo $aosField; ?>[method][<?php echo $key; ?>]">
            <?php
            if(!empty($methodTitles)) {
                foreach($methodTitles as $title) {
                    $selected = '';
                    if(!empty($metaMethod)) {
                        if($metaMethod == $title) {
                            $selected = "selected='selected'";
                        }
                    } else {
                        $selected = ($currentMethod == $title) ? "selected='selected'" : '';
                    }
                    echo '<option ' . $selected . ' value="' . $title . '">' . $title . '</option>';
                }
            }
            ?>
        </select>
    </td>
    <td class="shipping-class">
        <?php
        $shippingClasses = array();
        $productClass = $_product->get_shipping_class();
        $get_classes = WC()->shipping->get_shipping_classes();


        if(!empty($get_classes)) {
            $shippingClasses[] = 'none';
            foreach($get_classes as $class) {
                $shippingClasses[] = $class->slug;
            }
        }

        ?>
        <select name="<?php echo $aosField; ?>[class][<?php echo $key; ?>]">

            <?php
            if(!empty($shippingClasses)) {
                foreach($shippingClasses as $class) {
                    $selected = '';
                    if(!empty($metaClass)) {
                        if($metaClass == $class) {
                            $selected = "selected='selected'";
                        }

                    } else {
                        $selected = ($productClass == $class) ? "selected='selected'" : '';
                    }
                    echo '<option ' . $selected . ' value="' . $class . '">' . $class . '</option>';
                }
            }
            ?>
        </select>
    </td>
    <td class="shipping-notes">
        <?php



        ?>
        <textarea name="<?php echo $aosField; ?>[notes][<?php echo $key; ?>]"><?php echo $metaNotes; ?></textarea>
    </td>
</tr>