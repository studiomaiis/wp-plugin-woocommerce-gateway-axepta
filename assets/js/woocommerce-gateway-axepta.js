jQuery(document).ready(function() {
	jQuery('#woocommerce_axepta_gateway_test_mode').on('change', function() {
		if (jQuery(this).is(':checked')) {
			jQuery('#woocommerce_axepta_gateway_merchant_id, #woocommerce_axepta_gateway_hmac_key, #woocommerce_axepta_gateway_blowfish_key').parents('tr').hide();
			jQuery('#woocommerce_axepta_gateway_test_merchant_id, #woocommerce_axepta_gateway_test_hmac_key, #woocommerce_axepta_gateway_test_blowfish_key').parents('tr').show();
		} else {
			jQuery('#woocommerce_axepta_gateway_test_merchant_id, #woocommerce_axepta_gateway_test_hmac_key, #woocommerce_axepta_gateway_test_blowfish_key').parents('tr').hide();
			jQuery('#woocommerce_axepta_gateway_merchant_id, #woocommerce_axepta_gateway_hmac_key, #woocommerce_axepta_gateway_blowfish_key').parents('tr').show();
		}
	});
	jQuery('#woocommerce_axepta_gateway_test_mode').trigger('change');
});
