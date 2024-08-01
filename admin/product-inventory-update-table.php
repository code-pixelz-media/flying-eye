<?php

// Add a custom column to the product listing page
add_filter('manage_edit-product_columns', 'add_inventory_column', 10, 1);
function add_inventory_column($columns)
{
    // Add a new column after the stock column
    if (current_user_can('read_write_physical_stock_inventory') || current_user_can('read_physical_stock_inventory')) {
        $new_columns = [];
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ('is_in_stock' === $key) {
                $new_columns['inventory'] = __('Inventory', 'woocommerce');
            }
        }
        return $new_columns;
    }
    return $columns;
}

// Populate the custom column with inventory data
add_action('manage_product_posts_custom_column', 'populate_inventory_column', 10, 2);
function populate_inventory_column($column, $post_id)
{
    if (current_user_can('read_write_physical_stock_inventory')) { ?>
        <style>
            #the-list tr:hover .inventory.column-inventory span::after {
                content: 'Edit';
                margin-left: 10px;
                color: #2271B1;
            }
        </style>
    <?php
    }
    if ('inventory' === $column) {
        $physical_stock = get_post_meta($post_id, '_physical_stock', true) ? get_post_meta($post_id, '_physical_stock', true) : get_post_meta($post_id, '_stock', true); ?>
        <span style="width:55px;cursor: pointer;padding: 10px 25px;" id="inventory_number_product_list_<?php echo $post_id; ?>" data-product_id="<?php echo $post_id; ?>" class="inventory_number_product_list"><?php echo $physical_stock; ?> </span>
        <?php if (current_user_can('read_write_physical_stock_inventory')) { ?>
            <div class="admin-tooltip" id="admin-tooltip_<?php echo $post_id; ?>">
                <input type="number" name="inv_number" value="<?php echo $physical_stock; ?>" class="inv_number" data-product_id="<?php echo $post_id; ?>">
                <div class="admin-tooltip-btns">
                    <input type="button" class="button button-danger inven-cancel-btn" value="close">
                    <input type="button" class="button button-primary inven-submit-btn" value="update">
                </div>
            </div>
    <?php
        }
    }
}

add_action("wp_ajax_update_inventory_number", "update_inventory_number");
add_action("wp_ajax_nopriv_update_inventory_number", "update_inventory_number");

function update_inventory_number()
{
    $inventory_number = $_POST['inventory_number'];
    $product_id = $_POST['product_id'];
    update_post_meta($product_id, '_physical_stock', $inventory_number);
    $response = array('number' => $inventory_number, 'product' => $product_id);
    wp_send_json_success($response, 200);
    die();
}

function add_theme_caps()
{
    // gets the author role
    $role = get_role('administrator');

    $role->add_cap('read_write_physical_stock_inventory');
    $role->add_cap('read_physical_stock_inventory');
}
add_action('admin_init', 'add_theme_caps');


function add_custom_submenu()
{
    add_submenu_page(
        'edit.php?post_type=product', // Parent slug
        'Inventory Update Page',        // Page title
        'Inventory Update',             // Menu title
        'manage_options',             // Capability
        'inventory-update',             // Menu slug
        'inventory_update_page_callback' // Callback function
    );
}
add_action('admin_menu', 'add_custom_submenu');

function inventory_update_page_callback()
{
    if (!class_exists('WooCommerce')) {
        echo 'WooCommerce is not active.';
        get_footer();
        exit;
    }

    // Handle form submission
    if (isset($_POST['update_inventory']) && check_admin_referer('update_inventory_nonce')) {
        if (isset($_POST['inventory']) && is_array($_POST['inventory'])) {
            foreach ($_POST['inventory'] as $id => $stock) {
                $product = wc_get_product($id);
                $stock = intval($stock);
                if ($product && $product->is_type('variation')) {
                    update_post_meta($id, '_physical_variation_inventory', $stock);
                } else {
                    update_post_meta($id, '_physical_stock', $stock);
                }
            }
        }
        if (isset($_POST['virtual_inventory']) && is_array($_POST['virtual_inventory'])) {
            foreach ($_POST['virtual_inventory'] as $id => $stock) {
                $product = wc_get_product($id);
                $stock = intval($stock);
                if ($product && $product->is_type('variation')) {
                    update_post_meta($id, '_virtual_variation_inventory', $stock);
                } else {
                    update_post_meta($id, '_virtual_stock', $stock);
                }
            }
        }
        if (isset($_POST['default_inventory']) && is_array($_POST['default_inventory'])) {
            foreach ($_POST['default_inventory'] as $id => $stock) {
                $stock = intval($stock);
                update_post_meta($id, '_stock', $stock);
            }
        }
        echo '<div class="notice notice-success"><p>Inventory updated successfully.</p></div>';
    }

    // Set the number of products per page
    $products_per_page = get_option('stock_quantity_select');

    // Get the current page number
    $paged = (isset($_GET['paged']) && is_numeric($_GET['paged'])) ? intval($_GET['paged']) : 1;

    // Get the search query and selected category
    $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    $selected_category = isset($_GET['product_cat']) ? sanitize_text_field($_GET['product_cat']) : '';

    // Add SKU search filter
    add_filter('posts_search', 'search_by_sku', 10, 2);

    // Query WooCommerce products
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => $products_per_page,
        'paged' => $paged,
        's' => $search_query,
    );

    if ($selected_category) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $selected_category,
            ),
        );
    }

    $loop = new WP_Query($args);
    // Remove SKU search filter after query
    remove_filter('posts_search', 'search_by_sku', 10); ?>

    <form method="get" class="inventory-update-search-form">
        <input type="hidden" name="post_type" value="product">
        <input type="hidden" name="page" value="inventory-update">
        <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="Search Products by Title, SKU...">

        <select name="product_cat">
            <option value="">Filter by category</option>
            <?php
            $categories = get_terms('product_cat', array('hide_empty' => false));
            foreach ($categories as $category) {
                echo '<option value="' . esc_attr($category->slug) . '" ' . selected($selected_category, $category->slug, false) . '>' . esc_html($category->name) . '</option>';
            }
            ?>
        </select>

        <input type="submit" value="Search" class="button">
    </form>
    <div class="inventory-stock-sync">
        <a href="javascript:void(0);" class="button" id="sync-virtual-defalut">Sync</a>
        <a href="javascript:void(0);" class="button" id="sync-physical-virtual">Copy Physical -> Virtual</a>
    </div>

    <?php if ($loop->have_posts()) { ?>
        <form method="post" class="shop-inventory-form">
            <?php wp_nonce_field('update_inventory_nonce'); ?>
            <table class="wp-list-table widefat striped table-view-list posts shop-inventory-table">
                <thead class="shop-inventory-head">
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Physical Inventory</th>
                        <th>Default Stock</th>
                        <th>Virtual Stock</th>
                    </tr>
                </thead>
                <tbody class="shop-inventory-body">
                    <?php while ($loop->have_posts()) : $loop->the_post();
                        global $product;
                        $product_id = $product->get_id();
                        $product_name = $product->get_name();
                        $product_type = $product->get_type(); ?>
                        <tr>
                            <td><?php echo $product_id; ?></td>
                            <td>
                                <?php echo $product_name; ?>
                                <?php if ($product_type === 'variable') :
                                    $available_variations = $product->get_available_variations(); ?>
                                    <ul>
                                        <?php foreach ($available_variations as $variation) {
                                            $attributes = implode(', ', $variation['attributes']);
                                            echo  '<li> ' . esc_html($attributes) . '</li>';
                                        } ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($product_type === 'variable') :
                                    // Input field for the whole product
                                    // $product_physical_stock = get_post_meta($product_id, '_physical_stock', true); 
                                    ?>
                                    <!-- <div>
                                        <input type="number" name="inventory[<?php //echo $product_id; ?>]" value="<?php //echo $product_physical_stock; ?>">
                                    </div> -->
                                    <?php foreach ($available_variations as $variation) {
                                        $variation_id = $variation['variation_id'];
                                        $attributes = implode(', ', $variation['attributes']); ?>
                                        <div>
                                            <input type="number" name="inventory[<?php echo $variation_id; ?>]" id="inventory_<?php echo $variation_id; ?>" value="<?php echo get_post_meta($variation_id, '_physical_variation_inventory', true); ?>">
                                        </div>
                                    <?php }

                                else : ?>
                                    <input type="number" name="inventory[<?php echo $product_id; ?>]" value="<?php echo get_post_meta($product_id, '_physical_stock', true); ?>">
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($product_type === 'variable') :
                                    foreach ($available_variations as $variation) {
                                        $variation_id = $variation['variation_id'];
                                        $attributes = implode(', ', $variation['attributes']); ?>
                                        <div>
                                            <input type="number" name="default_inventory[<?php echo $variation_id; ?>]" id="default_inventory_<?php echo $variation_id; ?>" value="<?php echo intval(get_post_meta($variation_id, '_stock', true)); ?>">
                                        </div>
                                    <?php }
                                else : ?>
                                    <input type="number" name="default_inventory[<?php echo $product_id; ?>]" value="<?php echo $product->get_stock_quantity(); ?>">
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($product_type === 'variable') :
                                    foreach ($available_variations as $variation) {
                                        $variation_id = $variation['variation_id'];
                                        $attributes = implode(', ', $variation['attributes']); ?>
                                        <div>
                                            <input type="number" name="virtual_inventory[<?php echo $variation_id; ?>]" id="virtual_inventory_<?php echo $variation_id; ?>" value="<?php echo intval(get_post_meta($variation_id, '_virtual_variation_inventory', true)); ?>">
                                        </div>
                                    <?php }
                                else : ?>
                                    <input type="number" name="virtual_inventory[<?php echo $product_id; ?>]" value="<?php echo get_post_meta($product_id, '_virtual_stock', true); ?>">
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <input type="submit" name="update_inventory" value="Update" class="button button-primary button-large">
        </form>
<?php
        $total_pages = $loop->max_num_pages;
        if ($total_pages > 1) {
            $current_page = max(1, $paged);

            // Determine the number of pages to display initially and in the middle section
            $end_size = 1;
            $mid_size = 7;

            if ($total_pages <= 7) {
                // Show all pages if total pages are 7 or less
                $end_size = 7;
                $mid_size = 0;
            }

            $pagination_links = paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'current' => $current_page,
                'total' => $total_pages,
                'prev_text' => __('« Prev'),
                'next_text' => __('Next »'),
                'type' => 'array', // Generate an array of pagination links
                'end_size' => $end_size,
                'mid_size' => $mid_size,
            ));

            if (!empty($pagination_links)) {
                echo '<div class="tablenav bottom shop-inventory-table-pagination">';
                echo '<div class="tablenav-pages">';
                echo '<span class="pagination-links">';

                foreach ($pagination_links as $link) {
                    // Add appropriate classes to the pagination links
                    if (strpos($link, 'prev') !== false) {
                        $link = str_replace('prev page-numbers', 'prev-page button page-numbers', $link);
                    } elseif (strpos($link, 'next') !== false) {
                        $link = str_replace('next page-numbers', 'next-page button page-numbers', $link);
                    } elseif (strpos($link, 'current') !== false) {
                        $link = str_replace('page-numbers current', 'page-numbers tablenav-pages-navspan button disabled current', $link);
                    } else {
                        $link = str_replace('page-numbers', 'page-numbers button', $link);
                    }
                    echo $link;
                }

                echo '</span>';
                echo '</div>';
                echo '</div>';
            }
        }
    } else {
        echo __('No products found');
    }

    // Reset Query
    wp_reset_postdata();
}

function search_by_sku($search, $wp_query)
{
    global $wpdb;
    if (!$search)
        return $search;
    $search = $search_query = $wp_query->query_vars['s'];
    if (is_admin() && $wp_query->query_vars['post_type'] === 'product') {
        $search = " AND (
                ({$wpdb->posts}.post_title LIKE '%{$wpdb->esc_like($search_query)}%')
                OR ({$wpdb->posts}.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value LIKE '%{$wpdb->esc_like($search_query)}%'
                ))
            )";
    }
    return $search;
}
