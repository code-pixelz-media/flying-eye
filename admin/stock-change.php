<?php

add_action('woocommerce_order_status_changed', 'update_stock_on_order_status_change', 10, 4);

function update_stock_on_order_status_change($order_id, $old_status, $new_status, $order)
{

    // Handle virtual stock update
    $virtual_statuses = get_option('inventory_virtual_select_1');
    $virtual_status_list = array();

    if (is_array($virtual_statuses)) {
        foreach ($virtual_statuses as $status) {
            $virtual_status_list[] = str_replace('wc-', '', $status);
        }
    }

    // Handle physical stock update
    $physical_statuses = get_option('inventory_physical_select_1');
    $physical_status_list = array();

    if (is_array($physical_statuses)) {
        foreach ($physical_statuses as $status) {
            $physical_status_list[] = str_replace('wc-', '', $status);
        }
    }

    //if new satus is refunded, stock increased by the quentity refund_status_select_1
    $refund_statuses = get_option('refund_status_select_1');
    $refund_status_list = array();

    if (is_array($refund_statuses)) {
        foreach ($refund_statuses as $status) {
            $refund_status_list[] = str_replace('wc-', '', $status);
        }
    }


    // die(var_dump(in_array($old_status, $refund_status_list) && $new_status == 'refunded'));

    // check if new status is in the list of virtual staus list
    if (!in_array($old_status, $virtual_status_list) && !in_array($old_status, $physical_status_list) && in_array($new_status, $virtual_status_list)) {
        // Check if stock has already been updated for this order
        // $stock_updated = (int)get_post_meta($order_id, '_virtual_stock_updated', true);
        // if ($stock_updated) {
        //     return; // Stock has already been updated, so exit the function
        // }
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $product_id = $item->get_product_id();
            $products = wc_get_product($product_id);
            if ($product->is_type('simple')) {
                $quantity = $item->get_quantity();
                $qty_refunded = absint($item->get_meta('_restock_refunded_items', true));
                
                $remaining_quantity = $quantity - $qty_refunded; // Calculate remaining quantity
                $virtual_stock = (int)get_post_meta($product_id, '_virtual_stock', true);

                // if (empty($virtual_stock) || !is_numeric($virtual_stock) || $virtual_stock < 0) {
                //     continue; // Skip if $virtual_stock is empty, not numeric, or less than 0
                // }

                $virtual_stock = (int)$virtual_stock;
                $new_virtual_stock = max(-99999, $virtual_stock - $remaining_quantity); // Ensure stock doesn't go below 0
                update_post_meta($product_id, '_virtual_stock', $new_virtual_stock);
                update_post_meta($product_id, '_stock', $new_virtual_stock);
            } elseif ($products->get_type() == 'variable') {
                $variation_id = $item->get_variation_id();
                $quantity = $item->get_quantity();
                $qty_refunded = absint($item->get_meta('_restock_refunded_items', true));
                
                $remaining_quantity = $quantity - $qty_refunded; // Calculate remaining quantity
                $_virtual_variation_inventory = (int)get_post_meta($variation_id, '_virtual_variation_inventory', true);
                // if ($_virtual_variation_inventory && $_virtual_variation_inventory > 0) {
                    $new_inventory = max(-99999, $_virtual_variation_inventory - $remaining_quantity);
                    update_post_meta($variation_id, '_virtual_variation_inventory', $new_inventory);
                    update_post_meta($variation_id, '_stock', $new_inventory);
                // }
            }
        }

        // Mark the order as having had its stock updated
        // update_post_meta($order_id, '_virtual_stock_updated', 'yes');
    } elseif ($new_status != 'refunded') {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $product_id = $item->get_product_id();
            $products = wc_get_product($product_id);
            $quantity = $item->get_quantity();
            $qty_refunded = absint($item->get_meta('_restock_refunded_items', true));
            
            $remaining_quantity = $quantity - $qty_refunded; // Calculate remaining quantity
            if ($product->is_type('simple')) {
                $virtual_stock = (int)get_post_meta($product_id, '_virtual_stock', true);

                // if (empty($virtual_stock) || !is_numeric($virtual_stock) || $virtual_stock < 0) {
                //     continue; // Skip if $virtual_stock is empty, not numeric, or less than 0
                // }

                $virtual_stock = (int)$virtual_stock;
                update_post_meta($product_id, '_stock', $virtual_stock);
            } elseif ($products->get_type() == 'variable') {
                $variation_id = $item->get_variation_id();
                $_virtual_variation_inventory = (int)get_post_meta($variation_id, '_virtual_variation_inventory', true);
                // if ($_virtual_variation_inventory && $_virtual_variation_inventory > 0) {
                    $_virtual_variation_inventory = (int)$_virtual_variation_inventory;
                    // Assuming $_virtual_variation_inventory should be used to update _stock
                    update_post_meta($variation_id, '_stock', $_virtual_variation_inventory);
                // }
            }
        }
    }



    // check if new status is in the list of physical staus list
    if (!in_array($old_status, $physical_status_list) && in_array($new_status, $physical_status_list)) {
        // Check if stock has already been updated for this order
        // $stock_updated = (int)get_post_meta($order_id, '_physical_stock_updated', true);
        // if ($stock_updated) {
        //     return; // Stock has already been updated, so exit the function
        // }
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
            $qty_refunded = absint($item->get_meta('_restock_refunded_items', true));
            
            $remaining_quantity = $quantity - $qty_refunded; // Calculate remaining quantity
            $products = wc_get_product($product_id);
            if ($product->is_type('simple')) {
                $physical_stock = (int)get_post_meta($product_id, '_physical_stock', true);

                // if (empty($physical_stock) || !is_numeric($physical_stock) || $physical_stock < 0) {
                //     continue; // Skip if $physical_stock is empty, not numeric, or less than 0
                // }

                $physical_stock = (int)$physical_stock;
                $new_physical_stock = max(-99999, $physical_stock - $remaining_quantity); // Ensure stock doesn't go below 0
                update_post_meta($product_id, '_physical_stock', $new_physical_stock);

                //if an order is directly set from “Devis” (not listed in the parameters Inventory Setting) to “Terminé” (completed) which is listed in PHysical Stock, then both Physical and Virtual stock should be decreased.
                if (!in_array($old_status, $virtual_status_list) && in_array($new_status, $physical_status_list)) {
                    $virtual_stock = (int)get_post_meta($product_id, '_virtual_stock', true);
                    $virtual_stock = (int)$virtual_stock;
                    $new_virtual_stock = max(-99999, $virtual_stock - $remaining_quantity); // Ensure stock doesn't go below 0
                    update_post_meta($product_id, '_virtual_stock', $new_virtual_stock);
                    update_post_meta($product_id, '_stock', $new_virtual_stock);
                }
            } elseif ($products->get_type() == 'variable') {
                $variation_id = $item->get_variation_id();
                $quantity = $item->get_quantity();
                $qty_refunded = absint($item->get_meta('_restock_refunded_items', true));
                
                $remaining_quantity = $quantity - $qty_refunded; // Calculate remaining quantity
                $_physical_variation_inventory = (int)get_post_meta($variation_id, '_physical_variation_inventory', true);
                // if ($_physical_variation_inventory && $_physical_variation_inventory > 0) {
                    $new_inventory = max(-99999, $_physical_variation_inventory - $remaining_quantity);
                    update_post_meta($variation_id, '_physical_variation_inventory', $new_inventory);
                    
                    $_physical_inventory = (int)get_post_meta($product_id, '_physical_stock', true);
                    $new_physical_inventory = max(-99999, $_physical_inventory - $remaining_quantity);
                    update_post_meta($product_id, '_physical_stock', $new_physical_inventory);

                    //if an order is directly set from “Devis” (not listed in the parameters Inventory Setting) to “Terminé” (completed) which is listed in PHysical Stock, then both Physical and Virtual stock should be decreased.
                    if (!in_array($old_status, $virtual_status_list) && in_array($new_status, $physical_status_list)) {
                        $virtual_stock = (int)get_post_meta($variation_id, '_virtual_variation_inventory', true);
                        $virtual_stock = (int)$virtual_stock;
                        $new_virtual_stock = max(-99999, $virtual_stock - $remaining_quantity); // Ensure stock doesn't go below 0
                        update_post_meta($variation_id, '_virtual_variation_inventory', $new_virtual_stock);
                        update_post_meta($variation_id, '_stock', $new_virtual_stock);
                    }
                // }
            }
        }
        // Mark the order as having had its stock updated
        // update_post_meta($order_id, '_physical_stock_updated', 'yes');
    }



    // if (in_array($old_status, $refund_status_list) && $new_status == 'refunded') {
    //     foreach ($order->get_items() as $item) {
    //         $product = $item->get_product();
    //         $product_id = $item->get_product_id();
    //         $quantity = $item->get_quantity();
    //         $products = wc_get_product($product_id);
    //         if ($product->is_type('simple')) {
    //             $physical_stock = (int)get_post_meta($product_id, '_physical_stock', true);

    //             if (empty($physical_stock) || !is_numeric($physical_stock) || $physical_stock < 0) {
    //                 continue; // Skip if $physical_stock is empty, not numeric, or less than 0
    //             }
    //             $physical_stock = (int)$physical_stock;
    //             $new_physical_stock = max(-99999, $physical_stock + $quantity); // Ensure stock doesn't go below 0
    //             update_post_meta($product_id, '_physical_stock', $new_physical_stock);


    //             $virtual_stock = (int)get_post_meta($product_id, '_virtual_stock', true);
    //             $virtual_stock = (int)$virtual_stock;
    //             $new_virtual_stock = max(-99999, $virtual_stock + $quantity); // Ensure stock doesn't go below 0
    //             update_post_meta($product_id, '_virtual_stock', $new_virtual_stock);
    //             update_post_meta($product_id, '_stock', $new_virtual_stock);
    //         } elseif ($products->get_type() == 'variable') {
    //             $variation_id = $item->get_variation_id();
    //             $quantity = $item->get_quantity();

    //             $_virtual_variation_inventory = (int)get_post_meta($variation_id, '_virtual_variation_inventory', true);
    //             $new_inventory = max(-99999, $_virtual_variation_inventory + $quantity);
    //             update_post_meta($variation_id, '_virtual_variation_inventory', $new_inventory);
    //             update_post_meta($variation_id, '_stock', $new_inventory);

    //             $_physical_variation_inventory = (int)get_post_meta($variation_id, '_physical_variation_inventory', true);
    //             $new_inventory_physical = max(-99999, $_physical_variation_inventory + $quantity);
    //             update_post_meta($variation_id, '_physical_variation_inventory', $new_inventory_physical);

    //             $_physical_inventory = (int)get_post_meta($product_id, '_physical_stock', true);
    //             $new_physical_inventory = max(-99999, $_physical_inventory + $quantity);
    //             update_post_meta($product_id, '_physical_stock', $new_physical_inventory);
    //         }
    //     }
    // }

    // Handle backward status changes
    if (in_array($old_status, $physical_status_list)) {
        if (in_array($new_status, $virtual_status_list)) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();
                $qty_refunded = absint($item->get_meta('_restock_refunded_items', true));
                
                $remaining_quantity = $quantity - $qty_refunded; // Calculate remaining quantity
                $products = wc_get_product($product_id);
                if ($product->is_type('simple')) {
                    $physical_stock = (int)get_post_meta($product_id, "_physical_stock", true);

                    // if (empty($physical_stock) || !is_numeric($physical_stock) || $physical_stock < 0) {
                    //     continue; // Skip if stock is empty, not numeric, or less than 0
                    // }

                    $stock = (int)$physical_stock;
                    $new_stock = max(-99999, $stock + $remaining_quantity); // Ensure stock doesn't go below 0
                    update_post_meta($product_id, "_physical_stock", $new_stock);
                } elseif ($products->get_type() == 'variable') {
                    $variation_id = $item->get_variation_id();
                    $quantity = $item->get_quantity();
                    $qty_refunded = absint($item->get_meta('_restock_refunded_items', true));
                    
                    $remaining_quantity = $quantity - $qty_refunded; // Calculate remaining quantity
                    $_physical_variation_inventory = (int)get_post_meta($variation_id, '_physical_variation_inventory', true);
                    $stock = (int)$_physical_variation_inventory;
                    $new_stock = max(-99999, $stock + $remaining_quantity); // Ensure stock doesn't go below 0
                    update_post_meta($variation_id, "_physical_variation_inventory", $new_stock);

                    $_physical_inventory = (int)get_post_meta($product_id, '_physical_stock', true);
                    $new_physical_inventory = max(-99999, $_physical_inventory + $remaining_quantity);
                    update_post_meta($product_id, '_physical_stock', $new_physical_inventory);
                }
            }
        } elseif ((!in_array($new_status, $virtual_status_list) && !in_array($new_status, $physical_status_list)) && $new_status != 'refunded') {

            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();
                $qty_refunded = absint($item->get_meta('_restock_refunded_items', true));
                
                $remaining_quantity = $quantity - $qty_refunded; // Calculate remaining quantity
                $products = wc_get_product($product_id);
                if ($product->is_type('simple')) {
                    $physical_stock = (int)get_post_meta($product_id, "_physical_stock", true);
                    $virtual_stock = (int)get_post_meta($product_id, "_virtual_stock", true);

                    // if (empty($physical_stock) || !is_numeric($physical_stock) || $physical_stock < 0) {
                    //     continue; // Skip if stock is empty, not numeric, or less than 0
                    // }

                    $physical_stock = (int)$physical_stock;
                    $new_physical_stock = max(-99999, $physical_stock + $remaining_quantity); // Ensure stock doesn't go below 0
                    update_post_meta($product_id, "_physical_stock", $new_physical_stock);

                    // if (empty($virtual_stock) || !is_numeric($virtual_stock) || $virtual_stock < 0) {
                    //     continue; // Skip if stock is empty, not numeric, or less than 0
                    // }
                    $virtual_stock = (int)$virtual_stock;
                    $new_virtual_stock = max(-99999, $virtual_stock + $remaining_quantity); // Ensure stock doesn't go below 0
                    update_post_meta($product_id, "_virtual_stock", $new_virtual_stock);
                    update_post_meta($product_id, "_stock", $new_virtual_stock);
                } elseif ($products->get_type() == 'variable') {

                    $variation_id = $item->get_variation_id();
                    $quantity = $item->get_quantity();
                    $qty_refunded = absint($item->get_meta('_restock_refunded_items', true));
                    
                    $remaining_quantity = $quantity - $qty_refunded; // Calculate remaining quantity
                    $physical_stock = (int)get_post_meta($variation_id, "_physical_variation_inventory", true);
                    $physical_stock = (int)$physical_stock;
                    $new_physical_stock = max(-99999, $physical_stock + $remaining_quantity); // Ensure stock doesn't go below 0
                    update_post_meta($variation_id, "_physical_variation_inventory", $new_physical_stock);

                    $_physical_inventory = (int)get_post_meta($product_id, '_physical_stock', true);
                    $new_physical_inventory = max(-99999, $_physical_inventory + $remaining_quantity);
                    update_post_meta($product_id, '_physical_stock', $new_physical_inventory);


                    $virtual_stock = (int)get_post_meta($variation_id, "_virtual_variation_inventory", true);
                    $virtual_stock = (int)$virtual_stock;
                    $new_virtual_stock = max(-99999, $virtual_stock + $remaining_quantity); // Ensure stock doesn't go below 0
                    update_post_meta($variation_id, "_virtual_variation_inventory", $new_virtual_stock);
                    update_post_meta($variation_id, "_stock", $new_virtual_stock);
                }
            }
        }
    } elseif (in_array($old_status, $virtual_status_list)) {

        if ((!in_array($new_status, $virtual_status_list) && !in_array($new_status, $physical_status_list)) && $new_status != 'refunded') {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();
                $qty_refunded = absint($item->get_meta('_restock_refunded_items', true));
                
                $remaining_quantity = $quantity - $qty_refunded; // Calculate remaining quantity
                $products = wc_get_product($product_id);
                if ($product->is_type('simple')) {
                    $virtual_stock = (int)get_post_meta($product_id, "_virtual_stock", true);

                    // if (empty($virtual_stock) || !is_numeric($virtual_stock) || $virtual_stock < 0) {
                    //     continue; // Skip if stock is empty, not numeric, or less than 0
                    // }
                    $virtual_stock = (int)$virtual_stock;
                    $new_virtual_stock = max(-99999, $virtual_stock + $remaining_quantity); // Ensure stock doesn't go below 0
                    update_post_meta($product_id, "_virtual_stock", $new_virtual_stock);
                    update_post_meta($product_id, "_stock", $new_virtual_stock);
                } elseif ($products->get_type() == 'variable') {
                    $variation_id = $item->get_variation_id();
                    $quantity = $item->get_quantity();
                    $qty_refunded = absint($item->get_meta('_restock_refunded_items', true));
                    
                    $remaining_quantity = $quantity - $qty_refunded; // Calculate remaining quantity
                    $virtual_stock = (int)get_post_meta($variation_id, "_virtual_variation_inventory", true);
                    $virtual_stock = (int)$virtual_stock;
                    $new_virtual_stock = max(-99999, $virtual_stock + $remaining_quantity); // Ensure stock doesn't go below 0
                    update_post_meta($variation_id, "_virtual_variation_inventory", $new_virtual_stock);
                    update_post_meta($variation_id, "_stock", $new_virtual_stock);
                }
            }
        }
    }
}

// Add filter to prevent WooCommerce from reducing stock automatically
add_filter('woocommerce_can_reduce_order_stock', 'filter_woocommerce_can_reduce_order_stock', 10, 2);

function filter_woocommerce_can_reduce_order_stock($can_reduce_stock, $order)
{
    // Get the chosen virtual statuses from the option
    $virtual_statuses = get_option('inventory_virtual_select_1');
    $virtual_status_list = array();

    if (is_array($virtual_statuses)) {
        foreach ($virtual_statuses as $status) {
            $virtual_status_list[] = str_replace('wc-', '', $status);
        }
    }

    // Check if the current order status is one of the chosen statuses
    // Allow manual stock deduction for specific order statuses
    if (!in_array($order->get_status(), $virtual_status_list)) {
        $can_reduce_stock = false;
    }

    return $can_reduce_stock; // Allow WooCommerce to handle stock reduction for the chosen statuses
}


function custom_refund_and_update_inventory($order_id, $refund_id)
{
    // Get the refund object
    $refund = wc_get_order($refund_id);

    // Loop through each refunded item
    foreach ($refund->get_items() as $item_id => $item) {
        // Get the product ID
        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);

        // Get the quantity refunded
        $quantity_refunded = $item->get_quantity();

        if ($product->is_type('simple')) {
            $quantity_refunded = -$quantity_refunded;
            $default_stock = (int)get_post_meta($product_id, "_stock", true);

            // Update the physical stock quantity
            update_post_meta($product_id, '_physical_stock', $default_stock);

            // Update the virtual stock quantity
            update_post_meta($product_id, '_virtual_stock', $default_stock);

            // Update the total stock quantity
        } elseif ($product->is_type('variable')) {
            $quantity_refunded = -$quantity_refunded;
            $variation_id = $item->get_variation_id();

            // Get the current stock levels for the variation
            $default_variation_stock = (int)get_post_meta($variation_id, "_stock", true);

            // Update the virtual variation inventory
            update_post_meta($variation_id, '_virtual_variation_inventory', $default_variation_stock);

            // Update the physical variation inventory
            update_post_meta($variation_id, '_physical_variation_inventory', $default_variation_stock);

            // Update the parent product's physical stock
            $_physical_inventory = (int)get_post_meta($product_id, '_physical_stock', true);
            $new_physical_inventory = max(-99999, (int)$_physical_inventory + $quantity_refunded);
            update_post_meta($product_id, '_physical_stock', $new_physical_inventory);
        }
    }
}

add_action('woocommerce_order_refunded', 'custom_refund_and_update_inventory', 10, 2);
