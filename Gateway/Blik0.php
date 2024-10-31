<?php

class Paylane_Gateway_Blik0 extends Paylane_Gateway_Base
{
	/**
	 * @var string
	 */
	protected $form_name = 'blik0';

	/**
	 * @var string
	 */
	protected $gateway_id = 'paylane_blik0';

	/**
	 * @return mixed
	 */
	protected function getMethodTitle()
	{
		if(!is_admin()){
			return $this->modTitle(__( 'BLIK', 'wc-gateway-paylane' ), $this->get_paylane_option( 'blik0_name'));
		}
		return __( 'BLIK level 0', 'wc-gateway-paylane' );
	}

	/**
	 * @return mixed
	 */
	protected function getGatewayTitle()
	{
		return __('BLIK level 0', 'wc-gateway-paylane'); 
	}

	public function get_icon()
    {
		$iconHtml = '';
		if ($this->get_paylane_option('display_payment_methods_logo','yes') == 'yes') {
			$iconHtml = '<img src="' . plugins_url('../assets/images/banks/BLIK.png', __FILE__) . '" class="paylane-payment-method-label-logo" alt="BLIK">';
		}
        return apply_filters('woocommerce_gateway_icon', $iconHtml, $this->id);
    }
}