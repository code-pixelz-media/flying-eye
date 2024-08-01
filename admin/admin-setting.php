<?php




// Add Physical Stock field to the product general settings tab
add_action('woocommerce_product_options_inventory_product_data', 'add_physical_stock_field');
function add_physical_stock_field()
{
    woocommerce_wp_text_input(array(
        'id' => '_physical_stock',
        'label' => __('Physical Stock', 'woocommerce'),
        'desc_tip' => true,
        'description' => __('Enter the physical stock quantity.', 'woocommerce'),
        'type' => 'number',
        // 'custom_attributes' => array(
        //     'step' => 'any',
        //     'min' => '0'
        // )
    ));
}

// Save Physical Stock field
add_action('woocommerce_process_product_meta', 'save_physical_stock_field');
function save_physical_stock_field($post_id)
{
    $physical_stock = isset($_POST['_physical_stock']) ? wc_clean($_POST['_physical_stock']) : '';
    update_post_meta($post_id, '_physical_stock', $physical_stock);
}


add_filter('woocommerce_get_settings_pages', 'flying_eye_custom_woocommerce_settings_tab');

function flying_eye_custom_woocommerce_settings_tab($settings)
{

    if (!class_exists('WC_Settings_Inventory_Setting')) {

        class WC_Settings_Inventory_Setting extends WC_Settings_Page
        {
            function __construct()
            {
                $this->id = 'inventory_setting';
                $this->label = 'Inventory Setting';
                parent::__construct();
            }
        }
        $settings[] = new WC_Settings_Inventory_Setting();
    }

    return $settings;
}

add_filter('woocommerce_get_settings_inventory_setting', 'flying_eye_custom_woocommerce_settings_tab_settings', 10, 2);

function flying_eye_custom_woocommerce_settings_tab_settings($settings, $current_section)
{
    $order_statuses_options = get_woocommerce_order_statuses_options();
    $settings = array(
        array(
            'title' => 'Inventory Setting',
            'desc' => 'Change Inventory for Order Status:',
            'type' => 'title',
        ),
        array(
            'name' => 'Physical Stock',
            'type' => 'inventory_select2',
            'id' => 'inventory_physical_select_1',
            'default' => '',
            'options' => $order_statuses_options,
            'desc' => 'Select single (multiple) order statuses',
            'desc_tip' => 'Selected option will be the status to decrement of inventory.',
            'autoload' => false,
        ),
        array(
            'name' => 'Virtual Stock',
            'type' => 'inventory_select2',
            'id' => 'inventory_virtual_select_1',
            'default' => '',
            'options' => $order_statuses_options,
            'desc' => 'Select single (multiple) order statuses',
            'desc_tip' => 'Selected option will be the status to decrement of inventory.',
            'autoload' => false,
        ),
        array(
            'title' => 'Stock Quantity',
            'desc' => 'Select the quantity to add:',
            'type' => 'select',
            'id' => 'stock_quantity_select',
            'default' => '50',
            'options' => array(
                '20' => '20',
                '50' => '50',
                '100' => '100',
                '200' => '200',
            ),
            'desc' => 'Select the quantity to display:',
            'desc_tip' => 'Choose a stock quantity to display.',
            'autoload' => false,
        ),
        array(
            'name' => 'Refund Status',
            'type' => 'inventory_select2',
            'id' => 'refund_status_select_1',
            'default' => '',
            'options' => $order_statuses_options,
            'desc' => 'Select single (multiple) order statuses for refund',
            'desc_tip' => 'Selected option will be the status to refund.',
            'autoload' => false,
        ),
        array(
            'type' => 'sectionend',
        ),
    );

    return $settings;
}

add_action('woocommerce_admin_field_inventory_select2', 'render_inventory_select2_field');
function render_inventory_select2_field($value)
{
    $option_value = get_option($value['id'], $value['default']); ?>
    <tr valign="top">
        <th scope="row" class="titledesc">
            <label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['title']); ?></label>
            <?php echo wc_help_tip($value['desc_tip']); ?>
        </th>
        <td class="forminp">
            <select id="<?php echo esc_attr($value['id']); ?>" name="<?php echo esc_attr($value['id']); ?>[]" style="width: 100%;" multiple="multiple" class="wc-enhanced-select">
                <?php
                foreach ($value['options'] as $key => $label) {
                    echo '<option value="' . esc_attr($key) . '" ' . selected(in_array($key, (array) $option_value), true, false) . '>' . esc_html($label) . '</option>';
                }
                ?>
            </select>
            <br><span class="description"><?php echo esc_html($value['desc']); ?></span>
        </td>
    </tr>
    <script>
        jQuery(document).ready(function($) {
            $('#<?php echo esc_attr($value['id']); ?>').select2();
        });
    </script>
    <?php
}

function get_woocommerce_order_statuses_options()
{
    $order_statuses = wc_get_order_statuses();
    $options = array();
    foreach ($order_statuses as $key => $status) {
        $options[$key] = $status;
    }
    return $options;
}

// Save the settings
add_action('woocommerce_update_options_inventory', 'flying_eye_save_custom_woocommerce_settings');

function flying_eye_save_custom_woocommerce_settings()
{
    woocommerce_update_options(flying_eye_custom_woocommerce_settings_tab_settings(array(), ''));
}



//inventory for variable product
add_action('woocommerce_variation_options_inventory', 'custom_variation_inventory_field', 10, 3);
function custom_variation_inventory_field($loop, $variation_data, $variation)
{
    woocommerce_wp_text_input(
        array(
            'id' => 'physical_variation_inventory[' . $variation->ID . ']',
            'label' => __('Physical Inventory', 'woocommerce'),
            'desc_tip' => 'true',
            'description' => __('Enter the physical inventory for this variation.', 'woocommerce'),
            'value' => get_post_meta($variation->ID, '_physical_variation_inventory', true),
            'type' => 'number',
            // 'custom_attributes' => array(
            //     'step' => '1',
            //     'min' => '0'
            // )
        )
    );
    woocommerce_wp_text_input(
        array(
            'id' => 'virtual_variation_inventory[' . $variation->ID . ']',
            'label' => __('Virtual Inventory', 'woocommerce'),
            'desc_tip' => 'true',
            'description' => __('Enter the virtual inventory for this variation.', 'woocommerce'),
            'value' => get_post_meta($variation->ID, '_virtual_variation_inventory', true),
            'type' => 'number',
            // 'custom_attributes' => array(
            //     'step' => '1',
            //     'min' => '0'
            // )
        )
    );
}


// Save variable inventory fields
add_action('woocommerce_save_product_variation', 'save_variation_inventory_field', 10, 2);
function save_variation_inventory_field($variation_id, $i)
{
    if (isset($_POST['physical_variation_inventory'][$variation_id])) {
        update_post_meta($variation_id, '_physical_variation_inventory', sanitize_text_field($_POST['physical_variation_inventory'][$variation_id]));
    }
    if (isset($_POST['virtual_variation_inventory'][$variation_id])) {
        update_post_meta($variation_id, '_virtual_variation_inventory', sanitize_text_field($_POST['virtual_variation_inventory'][$variation_id]));
    }
}

// Add variation inventory field to the variation data
add_filter('woocommerce_available_variation', 'add_variation_inventory_field_to_variation_data');
function add_variation_inventory_field_to_variation_data($variation_data)
{
    $variation_data['physical_variation_inventory'] = get_post_meta($variation_data['variation_id'], '_physical_variation_inventory', true);
    return $variation_data;
}


// Add custom field for Virtual Stock if Inventory management is enabled
add_action('woocommerce_product_options_inventory_product_data', 'add_virtual_stock_custom_field');
function add_virtual_stock_custom_field()
{
    global $woocommerce, $post;

    echo '<div class="options_group" id="virtual_stock_field">';

    // Virtual Stock
    woocommerce_wp_text_input(
        array(
            'id' => '_virtual_stock',
            'label' => __('Virtual Stock', 'woocommerce'),
            'desc_tip' => 'true',
            'description' => __('Enter the virtual stock quantity for this product.', 'woocommerce'),
            'type' => 'number',
            // 'custom_attributes' => array(
            //     'step' => 'any',
            //     'min' => '0'
            // )
        )
    );

    echo '</div>';
}

// Save custom field data
add_action('woocommerce_process_product_meta', 'save_virtual_stock_custom_field');
function save_virtual_stock_custom_field($post_id)
{
    $virtual_stock = isset($_POST['_virtual_stock']) ? $_POST['_virtual_stock'] : '';
    update_post_meta($post_id, '_virtual_stock', esc_attr($virtual_stock));
}