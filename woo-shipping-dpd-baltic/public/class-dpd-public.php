<?php
 add_action( 'wp_head', function () { ?>
	<script>
			 
		<?php if (is_checkout()) {
		?>
		jQuery(document).on( 'change', '.wc_payment_methods input[name="payment_method"]', function() {
					jQuery('body').trigger('update_checkout');
				});
		<?php
	}
		?>
	</script>
	<?php } );
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://dpd.com
 * @since      1.0.0
 *
 * @package    Dpd
 * @subpackage Dpd/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Dpd
 * @subpackage Dpd/public
 * @author     DPD
 */



class Dpd_Baltic_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 *
	 * @param      string $plugin_name The name of the plugin.
	 * @param      string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Dpd_Baltic_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Dpd_Baltic_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/dpd-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Dpd_Baltic_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Dpd_Baltic_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/dpd-public-dist.js', array( 'jquery' ), $this->version, false );

		$google_map_api = get_option( 'dpd_google_map_key' );

		if ( $google_map_api != '' ) {
			wp_enqueue_script( 'dpd-gmaps-markerclusterer', plugin_dir_url( __FILE__ ) . 'js/dpd-gmaps-markerclusterer-dist.js', array( 'jquery' ), $this->version, true );
//			wp_enqueue_script( 'dpd-postcode', plugin_dir_url( __FILE__ ) . 'js/dpd-postcode-dist.js', array( 'jquery' ), $this->version, true );
			wp_enqueue_script( 'dpd-parcel', plugin_dir_url( __FILE__ ) . 'js/dpd-parcel-dist.js', array( 'jquery' ), $this->version, true );
			wp_enqueue_script( 'google-maps-api', 'https://maps.googleapis.com/maps/api/js?key=' . $google_map_api, array( 'jquery' ), $this->version, true );

			wp_localize_script( 'dpd-gmaps-markerclusterer', 'dpd', array(
				'ajax_url'     => WC()->ajax_url(),
				'wc_ajax_url'  => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'ajax_nonce'   => wp_create_nonce( 'save-terminal' ),
				'theme_uri'    => plugin_dir_url( __FILE__ ) . 'images/',
				'gmap_api_key' => $google_map_api,
			) );
		}

	}

	public function locate_template( $template, $template_name, $template_path ) {
		// Tmp holder
		$_template = $template;

		if ( ! $template_path ) {
			$template_path = WC_TEMPLATE_PATH;
		}

		// Set our base path
		$plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/woocommerce/';

		// Look within passed path within the theme - this is priority
		$template = locate_template(
			array(
				trailingslashit( $template_path ) . $template_name,
				$template_name,
			)
		);

		// Get the template from this plugin, if it exists
		if ( ! $template && file_exists( $plugin_path . $template_name ) ) {
			$template = $plugin_path . $template_name;
		}

		// Use default template
		if ( ! $template ) {
			$template = $_template;
		}

		// Return what we found
		return $template;
	}

	public function set_checkout_session() {

		$cod = filter_var( $_REQUEST['cod'], FILTER_SANITIZE_NUMBER_INT );

		if ( is_numeric( $cod ) ) {
			WC()->session->set( 'cod_for_parcel', $cod );
		}

		wp_die();
	}



	public function available_payment_gateways( $available_gateways ) {
		global $wpdb;
		
		if ( isset( $available_gateways['cod'] ) ) {
			if ( WC()->session ) {
				$cod                     = WC()->session->get( 'cod_for_parcel' );
				$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

				if ( $cod === '0'  ) {
					unset( $available_gateways['cod'] );

					return $available_gateways;
				} elseif ( ! empty( $chosen_shipping_methods ) ) {
					$selected_terminal = null;

					if ( substr( $chosen_shipping_methods[0], 0, strlen( 'dpd_parcels' ) ) === 'dpd_parcels' ) {
						$selected_terminal = WC()->session->get( 'wc_shipping_dpd_parcels_terminal' );
					} elseif ( substr( $chosen_shipping_methods[0], 0, strlen( 'dpd_sameday_parcels' ) ) === 'dpd_sameday_parcels' ) {
						$selected_terminal = WC()->session->get( 'wc_shipping_dpd_sameday_parcels_terminal' );
					}

					if ( $selected_terminal && ! empty( $selected_terminal ) ) {
						

						$terminal = $wpdb->get_row( "SELECT cod FROM {$wpdb->prefix}dpd_terminals WHERE parcelshop_id = '{$selected_terminal}'" );
						$terminal_country = $wpdb->get_row( "SELECT country FROM {$wpdb->prefix}dpd_terminals WHERE parcelshop_id = '{$selected_terminal}'" );

						// 		&& $terminal->country != 'LV'				
						if ( $terminal->cod == 0 ) {

							unset( $available_gateways['cod'] );

							return $available_gateways;
						}
						
					}
				}
			} else {
				unset( $available_gateways['cod'] );

				return $available_gateways;
			}

			if ( WC()->cart && WC()->customer ) {
				$total = WC()->cart->get_displayed_subtotal();

				if ( WC()->cart->display_prices_including_tax() ) {
					$total = round( $total - ( WC()->cart->get_discount_total() + WC()->cart->get_discount_tax() ), wc_get_price_decimals() );
				} else {
					$total = round( $total - WC()->cart->get_discount_total(), wc_get_price_decimals() );
				}

				switch ( WC()->customer->get_shipping_country() ) {
					case 'LT':
						$max_total = 1000;
						break;
					case 'LV':
						$max_total = 1200;
						break;
					case 'EE':
						$max_total = 1278;
						break;
					default:
						$max_total = - 1;
				}

				if ( $total > $max_total ) {
					unset( $available_gateways['cod'] );
				}
			}
		}

		return $available_gateways;
	}

	public function add_cod_fee( WC_Cart $cart, $apply_fee = true ) {
		if ( $apply_fee && ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return;
		}

		$payment_gateway = isset( $_POST['payment_method'] ) && $_POST['payment_method'] === 'cod' ? 'cod' : '';

		if ( ! $payment_gateway ) {

			$payment_gateway = WC()->session->get( 'chosen_payment_method' );

			// WooCommerce issue when it's only one gateway

			if ( ! $payment_gateway ) {

				$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
				if ( ! empty( $available_gateways ) && current( array_keys( $available_gateways ) ) === 'cod' ) {
					$payment_gateway = 'cod';
				}

			}

		}

		$chosen_shipping_methods = isset( $_POST['shipping_method'] ) && ! empty( $_POST['shipping_method'] ) ? $_POST['shipping_method'] : '';

		if ( ! $chosen_shipping_methods ) {
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		}

		$current_shipping_method = explode( ':', $chosen_shipping_methods[0] );

		if ( ! empty( $chosen_shipping_methods ) && ! in_array( $current_shipping_method[0], [
				'dpd_home_delivery',
				'dpd_sat_home_delivery',
				'dpd_parcels',
				'dpd_sameday_parcels',
				'dpd_sameday_delivery'
			] ) ) {
			return;
		}

		if ( $payment_gateway !== 'cod' && $apply_fee ) {
			return;
		}

		global $woocommerce;
		$has_tax = true;

		$fee         = get_option( 'dpd_cod_fee' );
		$fee_p       = get_option( 'dpd_cod_fee_percentage' );
		$extra_fee   = is_numeric( $fee ) ? $fee : 0;
		$extra_fee_p = is_numeric( $fee_p ) ? $fee_p : 0;

		if ( $extra_fee <= 0 && $extra_fee_p <= 0 ) {
			return;
		}

		if ( $apply_fee ) {
			if ( $extra_fee_p > 0 ) {
				$extra_fee = $extra_fee + WC()->cart->get_cart_contents_total() * ($extra_fee_p / 100);
			}

			$woocommerce->cart->add_fee( __( 'Cash on delivery fee', 'woo-shipping-dpd-baltic' ), $extra_fee, $has_tax );
		} else {
			return $extra_fee;
		}
	}
}
