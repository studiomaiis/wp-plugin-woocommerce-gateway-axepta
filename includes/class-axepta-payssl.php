<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Axepta_PaySSL {
	
	public const PAYSSL_URL = 'https://paymentpage.axepta.bnpparibas/payssl.aspx';
	
	public $supported_currencies = array(
		'EUR',
		'USD',
		'CHF',
		'GBP',
		'CAD',
		'JPY',
		'MXP',
		'TRY',
		'AUD',
		'NZD',
		'NOK',
		'BRC',
		'ARP',
		'KHR',
		'TWD',
		'SEK',
		'DKK',
		'KRW',
		'SGD',
		'XPF',
		'XOF',
	);
	
	private $supported_languages = array(
		'de',
		'en',
		'fr',
		'it',
		'pt',
		'es',
		'nl',
	);

	private $form_action = self::PAYSSL_URL;
	
	private $merchant_id;
	private $hmac_key;
	private $blowfish_key;
	
	private $message_version = '2.0';
	private $transaction_id;
	private $reference_number;
	private $amount;
	private $amount_in_cents;
	private $currency;
	private $order_description;
	private $url_notify;
	private $url_back;
	private $url_success;
	private $url_failure;
	private $user_data;
	private $response = 'encrypt';
	private $language;
	
	private $hmac_signature = '';
	
	private $blowfish_parameters = array();
	private $blowfish_mandatory_request_parameters = array( 
		'MsgVer',
		'TransID',
		'Amount',
		'Currency',
		'RefNr',
		'URLNotify',
		'URLBack',
		'URLSuccess',
		'URLFailure',
		'OrderDesc',
		'UserData',
		'Response',
	);
	private $blowfish_mandatory_response_parameters = array( 
		'PayID',
		'TransID',
		'mid',
		'Status',
		'Code',
	);
	private $blowfish_data = '';
	private $blowfish_length = '';
	
	// private $response_data = '';
	// private $response_seal = '';
	
	private $response_parameters = array();
	private $response_is_valid = false;
	private $response_code = '';
	private $response_is_successful = false;


	public function __construct() { }

	
	public function get_supported_currencies() {
		return $this->supported_currencies;
	}


	public function get_supported_languages() {
		return $this->supported_languages;
	}


	public function set_merchant_id( $merchant_id ) {
		$this->merchant_id = $merchant_id;
		$this->blowfish_parameters[ 'MerchantID' ] = $merchant_id;
	}


	public function set_hmac_key( $hmac_key ) {
		$this->hmac_key = $hmac_key;
	}


	public function set_blowfish_key( $blowfish_key ) {
		$this->blowfish_key = $blowfish_key;
	}


	public function set_transaction_id( $transaction_id ) {
		$this->transaction_id = $transaction_id;
		$this->blowfish_parameters[ 'TransID' ] = $transaction_id;
		$this->order_description = "Transaction $transaction_id";
		if ($this->merchant_id == 'BNP_DEMO_AXEPTA') {
			$this->order_description = 'Test:0000';
		}
		$this->blowfish_parameters[ 'OrderDesc' ] = $this->order_description;
	}


	public function set_reference_number( $reference_number ) {
		$this->reference_number = $reference_number;
		$this->blowfish_parameters[ 'RefNr' ] = $reference_number;
		$this->user_data = $reference_number;
		$this->blowfish_parameters[ 'UserData' ] = $reference_number;
	}


	public function set_amount( $amount ) {
		$this->amount = $amount;
		$this->amount_in_cents = intval( floatval( $amount ) * 100 );
		$this->blowfish_parameters[ 'Amount' ] = $this->amount_in_cents;
	}


	public function set_currency( $currency ) {
		if ( ! in_array( $currency, $this->supported_currencies ) ) {
			throw new Exception( "Currency not supported" );
		}
		$this->currency = $currency;
		$this->blowfish_parameters[ 'Currency' ] = $currency;
	}


	public function set_url_notify( $url_notify ) {
		$this->url_notify = $url_notify;
		$this->blowfish_parameters[ 'URLNotify' ] = $url_notify;
	}


	public function set_url_back( $url_back ) {
		$this->url_back = $url_back;
		$this->blowfish_parameters[ 'URLBack' ] = $url_back;
	}


	public function set_url_success( $url_success ) {
		$this->url_success = $url_success;
		$this->blowfish_parameters[ 'URLSuccess' ] = $url_success;
	}


	public function set_url_failure( $url_failure ) {
		$this->url_failure = $url_failure;
		$this->blowfish_parameters[ 'URLFailure' ] = $url_failure;
	}


	public function set_language( $language ) {
		if ( in_array( $language, $this->supported_languages ) ) {
			$this->language = $language;
		}
	}


	public function process_request() {
		
		if ( empty( $this->transaction_id ) ) throw new Exception( "transaction_id missing" );
		if ( empty( $this->merchant_id ) ) throw new Exception( "merchant_id missing" );
		if ( empty( $this->amount ) ) throw new Exception( "amount missing" );
		if ( empty( $this->currency ) ) throw new Exception( "currency missing" );

		$this->generate_hmac_signature( '', $this->transaction_id, $this->merchant_id, $this->amount_in_cents, $this->currency );
		$this->generate_blowfish_data();
		$this->generate_form_action();
	}


	public function generate_hmac_signature( ...$parameters ) {
		$sha_string = join( '*', $parameters );

		if ( empty( $this->hmac_key ) ) throw new Exception( "hmac_key missing" );
		
		$this->hmac_signature = strtoupper( hash_hmac( 'sha256', $sha_string, $this->hmac_key ) );
	}


	public function generate_blowfish_data() {
		$data = array();
		
		$this->blowfish_parameters[ 'MsgVer' ] = $this->message_version;
		$this->blowfish_parameters[ 'Response' ] = $this->response;
		
		foreach ( $this->blowfish_mandatory_request_parameters as $key) {
			if ( ! isset( $this->blowfish_parameters[ $key ] ) ) throw new Exception( "$key missing" );
		}
		foreach ( $this->blowfish_parameters as $key => $value ) {
			$data[] = $key . '=' . $value;
		}
		if ( empty( $this->hmac_signature ) ) throw new Exception( "hmac_signature missing" );
		$data[] = 'MAC=' . $this->hmac_signature;
		
		$blowfish_string = join( '&', $data );
		$this->blowfish_length = strlen( $blowfish_string );
		$this->blowfish_data = bin2hex( $this->blowfish_encrypt( $blowfish_string, $this->blowfish_key ) );
	}


	public function generate_form_action() {
		$args = array(
			'CustomField1' => $this->amount . ' ' . $this->currency,
		);
		if ( !empty( $this->language ) ) $args[ 'Language' ] = $this->language;

		$this->form_action = add_query_arg( $args, $this->form_action );
	}


	public function get_form_action() {
		return $this->form_action;
	}


	public function get_merchant_id() {
		return $this->merchant_id;
	}


	public function get_blowfish_data() {
		return $this->blowfish_data;
	}


	public function get_blowfish_length() {
		return $this->blowfish_length;
	}


	private function blowfish_encrypt(string $data, string $key) {
		$l = strlen($key);
		if ($l < 16) {
			$key = str_repeat($key, (int) ceil(16 / $l));
		}
	
		if (($m = strlen($data) % 8) > 0) {
			$data .= str_repeat("\x00", 8 - $m);
		}
	
		$val = openssl_encrypt($data, 'BF-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
	
		return (string) $val;
	}


	private function blowfish_decrypt(string $data, string $key) {
		$l = strlen($key);
		if ($l < 16) {
			$key = str_repeat($key, (int) ceil(16 / $l));
		}
	
		$val = openssl_decrypt($data, 'BF-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
	
		return rtrim((string) $val, "\0");
	}


	public function process_response( $http_request ) {
		
		$hmac_parameters = array();
		
		$data = $http_request[ 'Data' ];
		$length = $http_request[ 'Len' ];
		
		$plain_text = $this->blowfish_decrypt( (string) hex2bin( $data ), $this->blowfish_key );

		$a = explode( '&', $plain_text );
		
		if ( is_array( $a ) and count( $a ) ) {
			foreach ( $a as $string ) {
				if ( preg_match( '/=/', $string ) ) {
					list( $key, $value ) = explode( '=', $string, 2 );
					if ( $key == 'MIB' ) $key = 'mib';
					$this->response_parameters[ $key ] = (string) $value;
					if ( in_array( $key, $this->blowfish_mandatory_response_parameters ) ) {
						$hmac_parameters[ $key ] = (string) $value;
					}
				}
			}
		}
		
		foreach ( $this->blowfish_mandatory_response_parameters as $key ) {
			if ( ! isset( $hmac_parameters[ $key ] ) ) {
				throw new Exception( "$key missing " . print_r( $hmac_parameters, true ) . print_r( $this->blowfish_mandatory_response_parameters, true ) . print_r( $this->response_parameters, true ) );
			}
		}
		
		if ( ! isset( $this->response_parameters[ 'MAC' ] ) and empty( $this->response_parameters[ 'MAC' ] ) ) {
			throw new Exception( "MAC missing" );
		}
		
		$this->generate_hmac_signature( $hmac_parameters[ 'PayID' ], $hmac_parameters[ 'TransID' ], $hmac_parameters[ 'mid' ], $hmac_parameters[ 'Status' ], $hmac_parameters[ 'Code' ] );
		
		if ( $this->hmac_signature != $this->response_parameters[ 'MAC' ] ) {
			throw new Exception( 'Wrong MAC signature '.$this->hmac_signature.' ?= '.$this->response_parameters[ 'MAC' ] . ' hmac_parameters = ' . print_r( $hmac_parameters, true ));
			$this->response_is_valid = false;
		}
		
		$this->response_is_valid = true;
		
		$this->response_is_successful = ($this->response_parameters[ 'Code' ] === '00000000');
	}

	
	public function get_response_parameter( $key ) {
		if ( empty( $this->response_parameters ) ) {
			throw new Exception( "Response parameters empty" );
		}
		if ( ! isset( $this->response_parameters[ $key ] ) or empty( $this->response_parameters[ $key ] ) ) {
			throw new Exception( "Response parameter $key not set or empty" );
		}
		return $this->response_parameters[ $key ];
	}


	public function get_response_parameters() {
		if ( empty( $this->response_parameters ) ) {
			throw new Exception( "Response parameters empty" );
		}
		return $this->response_parameters;
	}


	public function response_is_valid() {
		return $this->response_is_valid;
	}


	public function response_is_successful() {
		if ( ! $this->response_is_valid ) {
			throw new Exception( "Response is not valid, cannot call response_is_successful()" );
		}
		return $this->response_is_successful;
	}
}

