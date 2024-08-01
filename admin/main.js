jQuery(document).ready(function () {
    jQuery(document).on('click','.inventory_number_product_list',function(){
        jQuery(this).parent().find('.admin-tooltip').css('display','flex');
    });
  jQuery(".inven-submit-btn").on("click", function () {
    var inventory_number = jQuery(this).parent().parent().find('.inv_number').val();
    var product_id = jQuery(this).parent().parent().find('.inv_number').attr('data-product_id');
    jQuery.ajax({
      type: "post",
      url: admin_ajax.ajaxurl,
      data: { 
        action: "update_inventory_number", 
        inventory_number: inventory_number,
        product_id: product_id,
    },
      success: function (response) {
        // console.log('response',response);
        if (response.success) {
            jQuery('#inventory_number_product_list_'+response.data.product).text(response.data.number)
          jQuery('#admin-tooltip_'+response.data.product).css('display','none');
        }
      },
    });
  });

  jQuery('.inven-cancel-btn').on('click',function(){
    jQuery(this).parent().parent().css('display','none');
  });
});

jQuery(document).ready(function($) {
  $('body').on('change', '.variable_manage_stock', function() {
      var parent = $(this).closest('.woocommerce_variation');
      if ($(this).is(':checked')) {
          parent.find('.form-row[data-id^="physical_variation_inventory"]').show();
      } else {
          parent.find('.form-row[data-id^="physical_variation_inventory"]').hide();
      }
  }).change();
});

jQuery(document).ready(function($) {
  function toggleVirtualStockField() {
      if ($('#_manage_stock').is(':checked')) {
          $('#virtual_stock_field').show();
      } else {
          $('#virtual_stock_field').hide();
      }
  }

  // Initial check
  toggleVirtualStockField();

  // Check on change
  $('#_manage_stock').change(function() {
      toggleVirtualStockField();
  });
});