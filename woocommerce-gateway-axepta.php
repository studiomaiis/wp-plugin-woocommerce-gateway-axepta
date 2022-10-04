<?php

/**
 * Plugin Name: WooCommerce Axepta Gateway
 * Description: Accept card payments on your store using Axepta's gateway.
 * Plugin URI: https://github.com/studiomaiis/wp-plugin-woocommerce-gateway-axepta
 * Version: 0.3
 * Requires at least: 5.6.8
 * Tested up to: 6.0.2
 * Requires PHP: 7.3
 * Author: Pierre BASSON
 * Author URI: https://www.studiomaiis.net
 * Text Domain: woocommerce-gateway-axepta
 * Domain Path: /languages
 *
 * WC requires at least: 5.8
 * WC tested up to: 6.9.4
 *
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Required minimums and constants
 */
define( 'WC_AXEPTA_SERVER_URL', 'https://depot.studiomaiis.net/wordpress' );
define( 'WC_AXEPTA_PREFIX', 'wc_axepta' );
define( 'WC_AXEPTA_PLUGIN_NAME', __( 'WooCommerce Axepta Gateway', 'woocommerce-gateway-axepta') );
define( 'WC_AXEPTA_MAIN_FILE', __FILE__ );
define( 'WC_AXEPTA_ABSPATH', __DIR__ . '/' );
define( 'WC_AXEPTA_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_AXEPTA_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );


function woocommerce_gateway_axepta() {

	static $plugin;
	
	if ( ! isset( $plugin ) ) {
	
		class WC_Axepta {
	
			/**
			 * The *Singleton* instance of this class
			 *
			 * @var Singleton
			 */
			private static $instance;


			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return Singleton The *Singleton* instance.
			 */
			public static function get_instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}
				return self::$instance;
			}


			/**
			 * The main Axepta gateway instance. Use get_main_axepta_gateway() to access it.
			 *
			 * @var null|WC_Axepta_Payment_Gateway
			 */
			protected $gateway = null;


			/**
			 * Private clone method to prevent cloning of the instance of the
			 * *Singleton* instance.
			 *
			 * @return void
			 */
			public function __clone() {}


			/**
			 * Private unserialize method to prevent unserializing of the *Singleton*
			 * instance.
			 *
			 * @return void
			 */
			public function __wakeup() {}


			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			public function __construct() {
				add_action( 'admin_init', [ $this, 'install' ] );
				
				$this->init();
			}


			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 */
			public function init() {
				require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-axepta.php';
				require_once dirname( __FILE__ ) . '/includes/class-axepta-updater.php';

				add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateways' ] );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ] );
				
				$updater = new Axepta\Updater( __FILE__, WC_AXEPTA_SERVER_URL );
				$updater->initialize();
			}


			/**
			 * Updates the plugin version in db
			 */
			public function update_plugin_version() {
				delete_option( WC_AXEPTA_PREFIX . '_version' );

				if( ! function_exists('get_plugin_data') ){
					require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				}
				$plugin_data = get_plugin_data( __FILE__ );

				update_option( WC_AXEPTA_PREFIX . '_version', $plugin_data['Version'] );
			}


			/**
			 * Handles upgrade routines.
			 */
			public function install() {
				if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					return;
				}
	
				$this->update_plugin_version();
			}


			/**
			 * Add plugin action links.
			 */
			public function plugin_action_links( $links ) {
				$plugin_links = array(
					'<a href="admin.php?page=wc-settings&tab=checkout&section=axepta_gateway">' . esc_html__( 'Settings', 'woocommerce' ) . '</a>',
				);
				return array_merge( $plugin_links, $links );
			}


			/**
			 * Add the gateways to WooCommerce.
			 */
			public function add_gateways( $methods ) {
				$methods[] = $this->get_main_axepta_gateway();
	
				return $methods;
			}


			/**
			 * Returns the main Axepta payment gateway class instance.
			 *
			 * @return WC_Gateway_Axepta
			 */
			public function get_main_axepta_gateway() {
				if ( ! is_null( $this->gateway ) ) {
					return $this->gateway;
				}
	
				$this->gateway = new WC_Gateway_Axepta();
	
				return $this->gateway;
			}
		}
	
		$plugin = WC_Axepta::get_instance();
	
	}
	
	return $plugin;
}


add_action( 'plugins_loaded', 'woocommerce_gateway_axepta_init' );
function woocommerce_gateway_axepta_init() {
	load_plugin_textdomain( 'woocommerce-gateway-axepta', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_axepta_missing_wc_notice' );
		return;
	}

	woocommerce_gateway_axepta();
}


function woocommerce_axepta_missing_wc_notice() {
	/* translators: 1. Plugin name 2. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( '%1$s requires WooCommerce to be installed and active. You can download %2$s here.', 'woocommerce-gateway-axepta' ), WC_AXEPTA_PLUGIN_NAME, '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}


add_action( 'parse_request', 'woocommerce_axepta_parse_request' );
function woocommerce_axepta_parse_request( &$wp ) {
	if ( isset( $wp->query_vars[ 'pagename' ] ) ) {
		if ( isset( $wp->query_vars[ 'order-received' ] ) ) {
			if ( preg_match( '/^axepta:([^:]+):([^:]+)$/', $wp->query_vars[ 'order-received' ], $matches ) ) {
				$order_received_url = wc_get_endpoint_url( 'order-received', $matches[ 1 ], wc_get_checkout_url() );
				$order_received_url = add_query_arg( 'key', $matches[ 2 ], $order_received_url );
				wp_redirect( $order_received_url );
				exit;
			}
		}
	}
}

