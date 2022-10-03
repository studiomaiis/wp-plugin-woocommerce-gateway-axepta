<?php


namespace Axepta;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Updater {

	private $file;
	private $server_url;
	private $plugin;
	private $basename;
	private $active;
	private $server_response;

	public function __construct( $file, $server_url ) {

		$this->file = $file;
		$this->server_url = $server_url;

		add_action( 'admin_init', array( $this, 'set_plugin_properties' ) );

		return $this;
	}

	public function set_plugin_properties() {
		$this->plugin	= get_plugin_data( $this->file );
		$this->basename = plugin_basename( $this->file );
		$this->active	= is_plugin_active( $this->basename );
	}

	private function get_server_info() {
		$this->set_plugin_properties();
		
		if ( is_null( $this->server_response ) ) {
			$slug = current( explode( '/' , $this->basename ) );
			$request_uri = $this->server_url.'/'.$slug.'.json';
			
			$response = json_decode( wp_remote_retrieve_body( wp_remote_get( $request_uri ) ), true ); // Get JSON and parse it

			$this->server_response = $response;
		}
	}

	public function initialize() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3);
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
	}

	public function modify_transient( $transient ) {

		if ( property_exists( $transient, 'checked') ) {

			if ( $checked = $transient->checked ) {

				$this->get_server_info();
				
				
				$out_of_date = version_compare( $this->server_response['version'], $checked[ $this->basename ], 'gt' ); // Check if we're out of date

				if ( $out_of_date ) {
					
					$slug = current( explode( '/' , $this->basename ) );
					
					$plugin = array(
						'url' => $this->plugin["PluginURI"],
						'slug' => $slug,
						'package' => $this->server_response['download_url'],
						'new_version' => $this->server_response['version']
					);
					
					$transient->response[$this->basename] = (object) $plugin;

				}
			}
		}

		return $transient;
	}

	public function plugin_popup( $result, $action, $args ) {

		if ( ! empty( $args->slug ) ) {
			
			if ( $args->slug == current( explode( '/' , $this->basename ) ) ) {
				
				$this->get_server_info();
				
				return (object) $this->server_response;

			}

		}
		
		return $result;
	}
	
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem; // Get global FS object

		$install_directory = plugin_dir_path( $this->file ); // Our plugin directory
		$wp_filesystem->move( $result['destination'], $install_directory ); // Move files to the plugin dir
		$result['destination'] = $install_directory; // Set the destination for the rest of the stack

		if ( $this->active ) { // If it was active
			activate_plugin( $this->basename ); // Reactivate
		}

		return $result;
	}

}

