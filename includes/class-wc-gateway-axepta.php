<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WC_Gateway_Axepta extends WC_Payment_Gateway {

	const ID = 'axepta_gateway';
	public static $log_enabled = false;
	public static $log = false;
	public $merchant_id;
	public $hmac_key;
	public $blowfish_key;
	public $test_mode;
	public $test_merchant_id;
	public $test_hmac_key;
	public $test_blowfish_key;
	public $debug;


	/**
	 * Constructor
	 */
	public function __construct() {
		
		$this->id = self::ID;
		$this->method_title = __( 'Axepta', 'woocommerce-gateway-axepta' );
		$this->method_description = __( 'Accept card payments on your store using Axepta\'s gateway.', 'woocommerce-gateway-axepta' );
		$this->has_fields = false;
		$this->supports = array(
			'products',
		);

		// Load the form fields.
		$this->init_form_fields();
		
		// Load the settings.
		$this->init_settings();

		$this->title				= $this->get_option( 'title', __( 'Secure payment by card', 'woocommerce-gateway-axepta' ) );
		$this->description			= $this->get_option( 'description' );
		$this->order_button_text	= $this->get_option( 'order_button_text', __( 'Pay for order', 'woocommerce' ) );

		$this->test_mode			= 'yes' === $this->get_option( 'test_mode', 'yes' );

		$this->merchant_id			= $this->get_option( 'merchant_id' );
		$this->hmac_key				= $this->get_option( 'hmac_key' );
		$this->blowfish_key			= $this->get_option( 'blowfish_key' );

		$this->test_merchant_id		= $this->get_option( 'test_merchant_id', 'BNP_DEMO_AXEPTA' );
		$this->test_hmac_key		= $this->get_option( 'test_hmac_key', '4n!BmF3_?9oJ2Q*z(iD7q6[RSb5)a]A8' );
		$this->test_blowfish_key	= $this->get_option( 'test_blowfish_key', 'Tc5*2D_xs7B[6E?w' );

		$this->debug				= 'yes' === $this->get_option( 'debug', 'yes' );
		
		self::$log_enabled = $this->debug;
		
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array( $this, 'check_response' ));
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		
		require_once WC_AXEPTA_ABSPATH . '/includes/class-axepta-payssl.php';
		$this->axepta_payssl = new Axepta_PaySSL();

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		}

		if ( 'yes' === $this->enabled ) {
			add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'order_received_text' ), 10, 2 );
		}
	}


	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, array( 'source' => self::ID ) );
		}
	}


	public function is_valid_for_use() {
		$supported_currencies = $this->get_supported_currencies();
		$woocommerce_currency = get_woocommerce_currency();
		if ( ! in_array( $woocommerce_currency, $supported_currencies ) ) {
			return false;
		}
		
		if ( $this->test_mode ) {
			return ( ! empty( $this->test_merchant_id ) && ! empty( $this->test_hmac_key ) && ! empty( $this->test_blowfish_key ) );
		} else {
			return ( ! empty( $this->merchant_id ) && ! empty( $this->hmac_key ) && ! empty( $this->blowfish_key ) );
		}
	}


	public function admin_options() {
		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {

			$supported_currencies = $this->get_supported_currencies();
			$woocommerce_currency = get_woocommerce_currency();
			if ( ! in_array( $woocommerce_currency, $supported_currencies ) ) {
				?>
				<div class="inline error">
					<p>
						<strong><?php esc_html_e( 'Gateway disabled', 'woocommerce' ); ?></strong>: 
						<?php
							/* translators: 1. Plugin name */
							echo sprintf( __( '%1$s does not support your store\'s currency.', 'woocommerce-gateway-axepta' ), WC_AXEPTA_PLUGIN_NAME );
						?>
					</p>
				</div>
				<?php
			}
			
			$supported_languages = $this->get_supported_languages();
			$language = $this->get_language();
			if ( ! in_array( $language, $supported_languages ) ) {
				?>
				<div class="inline error">
					<p>
						<strong><?php esc_html_e( 'Gateway disabled', 'woocommerce' ); ?></strong>: 
						<?php
							/* translators: 1. Plugin name */
							echo sprintf( __( '%1$s does not support your store\'s language.', 'woocommerce-gateway-axepta' ), WC_AXEPTA_PLUGIN_NAME );
						?>
					</p>
				</div>
				<?php
			}
			
			if ( ( $this->test_mode == true and empty( $this->test_merchant_id ) || empty( $this->test_hmac_key ) || empty( $this->test_blowfish_key ) )
				or
				 ( $this->test_mode == false and empty( $this->merchant_id ) || empty( $this->hmac_key ) || empty( $this->blowfish_key ) ) ) {
				?>
				<div class="inline error">
					<p>
						<strong><?php esc_html_e( 'Gateway disabled', 'woocommerce' ); ?></strong>: 
						<?php
							/* translators: 1. Plugin name */
							echo sprintf( __( '%1$s is not configured.', 'woocommerce-gateway-axepta' ), WC_AXEPTA_PLUGIN_NAME );
						?>
					</p>
				</div>
				<?php
			}
		}
	}
	
	
	public function admin_scripts( $hook_suffix ) {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}
	
		wp_register_script(
			'woocommerce_axepta_admin',
			plugins_url( 'assets/js/woocommerce-gateway-axepta.js', WC_AXEPTA_MAIN_FILE ),
			array('jquery')
		);
	
		wp_enqueue_script( 'woocommerce_axepta_admin' );
	}


	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}
		return $this->is_valid_for_use();
	}
	
	
	public function init_form_fields() {
		$this->form_fields = apply_filters( 'wc_offline_form_fields',
			array(
				'enabled' => array(
					'title'			=> __( 'Enable/Disable', 'woocommerce'),
					'type'			=> 'checkbox',
					'label'			=> __( 'Enable Axepta', 'woocommerce-gateway-axepta'),
				),
				'title' => array(
					'title'			=> __( 'Title', 'woocommerce'),
					'type'			=> 'text',
					'description'	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce'),
					'desc_tip'		=> true,
				),
				'description' => array(
					'title'			=> __( 'Description', 'woocommerce'),
					'type'			=> 'text',
					'description'	=> __( 'This controls the description which the user sees during checkout.', 'woocommerce'),
					'desc_tip'		=> true,
				),
				'order_button_text' => array(
					'title'			=> __( 'Payment button', 'woocommerce-gateway-axepta'),
					'type'			=> 'text',
				),
				'test_mode' => array(
					'title'			=> __( 'Enable/Disable test mode', 'woocommerce-gateway-axepta'),
					'type'			=> 'checkbox',
					'label'			=> __( 'Enable test mode', 'woocommerce-gateway-axepta'),
				),
				'merchant_id' => array(
					'title'			=> __( 'Merchant ID', 'woocommerce-gateway-axepta'),
					'type'			=> 'text',
				),
				'hmac_key' => array(
					'title'			=> __( 'HMAC key', 'woocommerce-gateway-axepta'),
					'type'			=> 'password',
				),
				'blowfish_key' => array(
					'title'			=> __( 'Blowfish encryption key', 'woocommerce-gateway-axepta'),
					'type'			=> 'password',
				),
				'test_merchant_id' => array(
					'title'				=> __( 'TEST ', 'woocommerce-gateway-axepta') . __( 'Merchant ID', 'woocommerce-gateway-axepta'),
					'type'				=> 'text',
					'custom_attributes' => array( 'readonly' => 'readonly' ),
				),
				'test_hmac_key' => array(
					'title'				=> __( 'TEST ', 'woocommerce-gateway-axepta') . __( 'HMAC key', 'woocommerce-gateway-axepta'),
					'type'				=> 'password',
					'custom_attributes' => array( 'readonly' => 'readonly' ),
				),
				'test_blowfish_key' => array(
					'title'				=> __( 'TEST ', 'woocommerce-gateway-axepta') . __( 'Blowfish encryption key', 'woocommerce-gateway-axepta'),
					'type'				=> 'password',
					'custom_attributes' => array( 'readonly' => 'readonly' ),
				),
				'debug' => array(
					'title'			=> __( 'Debug log', 'woocommerce' ),
					'type'			=> 'checkbox',
					'label'			=> __( 'Enable logging', 'woocommerce' ),
					'default'		=> 'no',
					/* translators: 1. Plugin name */
					'description'	=> sprintf( __( 'Log %1$s events, such as server requests (see WooCommerce > Status > Logs). Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'woocommerce-gateway-axepta' ), WC_AXEPTA_PLUGIN_NAME ),
				),
			)
		);
	}


	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg( 'order-pay', $order->get_id(), add_query_arg( 'key', $order->get_order_key(), $order->get_checkout_order_received_url() ) ),
		);
	}


	public function get_supported_currencies() {
		return $this->axepta_payssl->get_supported_currencies();
	}


	public function get_language() {
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$language = strtolower( ICL_LANGUAGE_CODE );
		} else {
			$language = strtolower( substr( get_bloginfo( 'language' ), 0, 2 ) );
		}
		return $language;
	}


	public function get_supported_languages() {
		return $this->axepta_payssl->get_supported_languages();
	}


	public function receipt_page( $order_id ) {
		global $woocommerce;
		$order = wc_get_order( $order_id );
		
		try {

			$amount = $order->get_total();
			$key = $order->get_order_key();
			$currency = get_woocommerce_currency();
			$notify_url = $woocommerce->api_request_url( strtolower( __CLASS__ ) );
			$return_url = $this->get_return_url( $order );
			$return_url = str_replace("/$order_id/?key=$key", "/axepta:$order_id:$key/", $return_url);
			$language = $this->get_language();
			
			if ( $this->test_mode ) {
				$this->axepta_payssl->set_merchant_id( $this->test_merchant_id );
				$this->axepta_payssl->set_blowfish_key( $this->test_blowfish_key );
				$this->axepta_payssl->set_hmac_key( $this->test_hmac_key );
			} else {
				$this->axepta_payssl->set_merchant_id( $this->merchant_id );
				$this->axepta_payssl->set_blowfish_key( $this->blowfish_key );
				$this->axepta_payssl->set_hmac_key( $this->hmac_key );
			}
			
			$this->axepta_payssl->set_transaction_id( $order_id );
			$this->axepta_payssl->set_reference_number( $key );
			$this->axepta_payssl->set_amount( $amount );
			$this->axepta_payssl->set_currency( $currency );
			$this->axepta_payssl->set_url_back( $return_url );
			$this->axepta_payssl->set_url_success( $return_url );
			$this->axepta_payssl->set_url_failure( $return_url );
			$this->axepta_payssl->set_url_notify( $notify_url );
			$this->axepta_payssl->set_language( $language );
			
			$this->axepta_payssl->process_request();
			
			$html = array();
			
			$html[] = '<form action="' . $this->axepta_payssl->get_form_action() . '" name="axepta" method="post">';
			$html[] = '<input type="hidden" name="MerchantID" value="' . $this->axepta_payssl->get_merchant_id() . '">';
			$html[] = '<input type="hidden" name="Data" value="' . $this->axepta_payssl->get_blowfish_data() . '">';
			$html[] = '<input type="hidden" name="Len" value="' . $this->axepta_payssl->get_blowfish_length() . '">';
			$html[] = '<input type="submit" name="Go" value="' . esc_attr( $this->order_button_text ) . '" />';
			$html[] = '</form>';
			$html[] = '<script>document.axepta.submit();</script>';
	
			echo join( "\n", $html );

		} catch ( Exception $e ) {
			
			$this->log( $e->getMessage(), 'error' );
			
		}
	}
	

	public function check_response() {
		
		try {
			
			if ( $this->test_mode ) {
				$this->axepta_payssl->set_merchant_id( $this->test_merchant_id );
				$this->axepta_payssl->set_blowfish_key( $this->test_blowfish_key );
				$this->axepta_payssl->set_hmac_key( $this->test_hmac_key );
			} else {
				$this->axepta_payssl->set_merchant_id( $this->merchant_id );
				$this->axepta_payssl->set_blowfish_key( $this->blowfish_key );
				$this->axepta_payssl->set_hmac_key( $this->hmac_key );
			}
			
			$http_request = wp_unslash( $_POST );
			$this->axepta_payssl->process_response( $http_request );
			
			if ( $this->axepta_payssl->response_is_valid() ) {
				
				$order_id = $this->mercanet_paypage_post->get_response_parameter( 'TransID' );
				$order = wc_get_order( $order_id );

				if ( $order ) {

					if ( $this->axepta_payssl->response_is_successful() ) {
						
						$pay_id = $this->axepta_payssl->get_response_parameter( 'PayID' );
						$xid = $this->axepta_payssl->get_response_parameter( 'XID' );

						/* translators: 1. Pay ID 2. XID */
						$order->add_order_note( sprintf( __( 'Payment was captured - Pay ID: %1$s - XID: %2$s', 'woocommerce-gateway-axepta' ), $pay_id, $xid ) );
						$order->payment_complete();
						global $woocommerce;
						$woocommerce->cart->empty_cart();
						$this->log( 'Axepta_PaySSL response is successful - order_id=' . $order_id . ' - parameters = ' . wc_print_r( $this->axepta_payssl->get_response_parameters(), true ) );

					} else {

						$code = $this->axepta_payssl->get_response_parameter( 'Code' );
						$description = $this->axepta_payssl->get_response_parameter( 'Description' );
						
						/* translators: 1. Response code 2. Response description */
						$order->add_order_note( sprintf( __( 'Payment attempt failed - Response code: %1$s - Description: %2$s', 'woocommerce-gateway-axepta' ), $code, $description ) );
						$this->log( 'Axepta_PaySSL response is NOT successful - order_id=' . $order_id . ' - parameters = ' . wc_print_r( $this->axepta_payssl->get_response_parameters(), true ) );

					}
					
				} else {
					
					throw new Exception( 'Axepta_PaySSL response : order not found - parameters = ' . wc_print_r( $this->axepta_payssl->get_response_parameters(), true ) );
					
				}
				
			} else {
				
				throw new Exception( 'Axepta_PaySSL response is NOT valid - $_REQUEST = ' . wc_print_r( $_REQUEST, true ) );
				
			}
			
		} catch ( Exception $e ) {
			
			$this->log( $e->getMessage(), 'error' );
			
		}
		
	}


	public function order_received_text( $text, $order ) {
		if ( $order && $this->id === $order->get_payment_method() && $order->is_paid() ) {
			return esc_html__( "Thank you for your order, your payment was successful.", 'woocommerce-gateway-axepta' );
		}
		if ( $order && $this->id === $order->get_payment_method() && 'pending' === $order->get_status() ) {
			$text = esc_html__( "Your payment was not successful. Please try again.", 'woocommerce-gateway-axepta' );
			$text.= '<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">';
			$text.= '<a href="' . esc_url( $order->get_checkout_payment_url() ) . '" class="button pay">' . esc_html__( 'Pay', 'woocommerce' ) . '</a>';
			$text.= '</p>';
		}
		if ( ! $order ) {
			$text = esc_html__( "Your payment was not successful.", 'woocommerce-gateway-axepta' );
		}
		return $text;
	}
	

}

