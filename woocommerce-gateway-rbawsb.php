<?php
/*
Plugin Name: WSB eToMiTreba
Plugin URI: https://www.webstudiobrana.com/wsb-etomitreba
Description: Extends WooCommerce with an eToMiTreba payment gateway.
Text Domain: wsb-rba
Domain Path: /languages
Version: 1.1
Author: Branko Borilović
Author URI: http://www.webstudiobrana.com
WC requires at least: 4.0
WC tested up to: 7.2
Copyright: © 2020 Branko Borilović.
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
add_action('plugins_loaded', 'woocommerce_rbawsb_init', 0);

if ( is_admin() ) {
function rbawsb_settings( $links ) {
		$settings_link = '<a href="'.esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_rbawsb' ) ).'">'.__( "Setup", "wsb-rba" ).'</a>';
  		array_push( $links, $settings_link );
  		return $links;
	}
}
$plugin = plugin_basename( __FILE__ );
	add_filter( "plugin_action_links_$plugin", 'rbawsb_settings' );
	
function woocommerce_rbawsb_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Localisation
	 */
	load_plugin_textdomain('wsb-rba', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
	/**
 	 * Gateway class
 	 */
	class WC_Gateway_Rbawsb extends WC_Payment_Gateway {
		
			/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			
			global $woocommerce;

			$this->plugin_url = plugin_dir_url(__FILE__);
			$this->icon				  = '';
			$this->id                 = 'rbawsb';
			$this->has_fields         = false;
			$this->method_title       = __( 'RBA e-ToMiTreba', 'wsb-rba' );
			$this->method_description = __( 'RBA payment gateway for Visa and Mastercard cards.', 'wsb-rba' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
			
			if($this->get_option( 'showlogo' ) === "yes") {
				$this->icon         = apply_filters( 'woocommerce_rbawsb_icon', esc_url($this->plugin_url.'/images/etomitreba-logo.png' ));
			}
			$this->title        		= $this->get_option( 'title' );
			$this->description  		= $this->get_option( 'description' );
			$this->successmessage		= $this->get_option( 'successmessage' );
			$this->failedmessage 		= $this->get_option( 'failedmessage' );
			$this->checkout_url     	= esc_url("https://secure.rba.hr/rba/enter");
			$this->checkout_url_test	= esc_url("https://uat.rba.hr/rba/enter");

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_'.$this->id, array( $this, 'thankyou_page' ) );
			add_action('woocommerce_receipt_'.$this->id, array($this, 'receipt_page'));
			add_action( 'woocommerce_api_wc_gateway_rbawsb', array($this, 'rbawsb_response' ) );
				
		}

		
		public function thankyou_page($orderID) {
			$order = wc_get_order( $orderID );	
		}
		
		function receipt_page($order){
			echo $this -> generate_rbawsb_form($order);
		}
		
		/**
		 * Generate eToMiTreba form
		 **/
		public function generate_rbawsb_form($order_id){

			global $woocommerce;
			$this->order = new WC_Order($order_id);	
			$Testiranje = $this->get_option( 'testing' );
			$TotalAmount = $this->order->get_total() * 100;
			$Opis = get_option( 'blogname' )." ".__( 'Order', 'wsb-rba' ) ;
			$RBA_Locale = "en";
			$lang_tag = get_bloginfo("language");
			if($lang_tag == "hr") $RBA_Locale = "hr";
			$OrderID = $order_id;
			$Delay = $OrderID;
			if($this->get_option( 'delay' ) == "yes") 
			{
				$Delay = $OrderID.",1";
			}
			$MerchantID = $this->get_option( 'merchantid' );
			$TerminalID = $this->get_option( 'terminalid' );
			$PurchaseTime = date("ymdHis");
			$putanja = plugin_dir_path(__FILE__).'keys/'.$MerchantID.'.pem';
			
			if($Testiranje == "yes")
				{
					$Currency = '980';
					$gateway = $this->checkout_url_test;
				} else {
					$datetime = strtotime("now");
					$eurdatetime = strtotime("2023-01-01 00:00:01");
					if($datetime >= $eurdatetime){
						$Currency = '978';
					} else {
						$Currency = '191';
					}
					$gateway = $this->checkout_url;
				} 
				
			if( !session_id() ) session_start();
			$sd = session_id();
			$verzija = 1;
			$data = "$MerchantID;$TerminalID;$PurchaseTime;$Delay;$Currency;$TotalAmount;$sd;";
			$fp = fopen("$putanja", "r");
			$priv_key = fread($fp, 8192);
			fclose($fp);
			$pkeyid = openssl_get_privatekey($priv_key);
			openssl_sign($data, $signature, $pkeyid);
			openssl_free_key($pkeyid);
			$b64sign = base64_encode($signature);	

			$post_variables = Array('Version'                  => $verzija,
									'MerchantID'               => $MerchantID,
									'TerminalID'               => $TerminalID,
									'TotalAmount'              => $TotalAmount,
									'Currency'                 => $Currency,
									'locale'                   => $RBA_Locale,
									'SD'                       => $sd,
									'OrderID'                  => $OrderID,
									'PurchaseTime'             => $PurchaseTime,
									'PurchaseDesc'             => $Opis,
									'Signature'                => $b64sign);
									if($this->get_option( 'delay' ) == "yes") 
									{
										$post_variables['Delay'] = 1;
									}						
			$url = $gateway;
			
			/*************************** F O R M ******************************/
			$html = '<form action="' . esc_url($url) . '" method="post" name="rbawsb_form"  accept-charset="UTF-8">';
			$html .= '<input type="submit"  value="' . __( 'Pay order', 'wsb-rba' ) . '" />';
			$html .= '<input type="hidden" name="charset" value="utf-8">';
			foreach ($post_variables as $name => $value) {
				$html .= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
			}
			$html .= '</form>';
			$html .= ' <script type="text/javascript">';
			$html .= ' document.rbawsb_form.submit();';
			$html .= ' </script>';
			/*************************** F O R M ******************************/
			
			return $html;
		}
		
		public function init_form_fields() {

			$this->form_fields = array(
				'enabled'      => array(
					'title'   => __( 'Enable/Disable', 'wsb-rba' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enabled', 'wsb-rba' ),
					'default' => 'no',
				),
				'title'        => array(
					'title'       => __( 'Title', 'wsb-rba' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'wsb-rba' ),
					'default'     => _x( 'Visa / Mastercard card', 'Credit Card payment', 'wsb-rba' ),
					'desc_tip'    => true,
				),
				'description'  => array(
					'title'       => __( 'Description', 'wsb-rba' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wsb-rba' ),
					'default'     => __( 'Credit Card payment via secure gateway of RBA bank.', 'wsb-rba' ),
					'desc_tip'    => true,
				),
				'merchantid' => array(
					'title'       => __( 'Merchant ID', 'wsb-rba' ),
					'type'        => 'text',
					'description' => __( 'Merchant identification number you received from the bank (MerchantID).', 'wsb-rba' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'terminalid' => array(
					'title'       => __( 'Terminal ID', 'wsb-rba' ),
					'type'        => 'text',
					'description' => __( 'Terminal code you received from the bank (TerminalID).', 'wsb-rba' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'testing'      => array(
					'title'   => __( 'Testing', 'wsb-rba' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enabled', 'wsb-rba' ),
					'description' => __( 'Check if you want to work in test mode.', 'wsb-rba' ),
					'desc_tip'    => true,
					'default' => 'no',
				),
				'delay'      => array(
					'title'   => __( 'Authorization', 'wsb-rba' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enabled', 'wsb-rba' ),
					'description' => __( 'In authorization mode you need to approve transaction manually. Useful when you need to check your stock before final payment. You need to request enabling a "Delay" parameter from bank if you want to use this feature.', 'wsb-rba' ),
					'desc_tip'    => true,
					'default' => 'no',
				),
				'showlogo'      => array(
					'title'   => __( 'Show logo', 'wsb-rba' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enabled', 'wsb-rba' ),
					'description' => __( 'If enabled, eToMiTreba logo will be shown on payment method list in frontend.', 'wsb-rba' ),
					'desc_tip'    => true,
					'default' => 'yes',
					),
				'custompages'      => array(
					'title'   => __( 'Custom redirect pages', 'wsb-rba' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enabled', 'wsb-rba' ),
					'description' => __( 'If enabled, customer will be redirected to the custom pages after transaction. These pages need to be defined in merchant interface (Success URL and Failure URL)', 'wsb-rba' ),
					'desc_tip'    => true,
					'default' => 'no',
					),
				'statussuccess'         => array(
					'title'       => __( 'Status for successful transaction', 'wsb-rba' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Order status for successful transaction.', 'wsb-rba' ),
					'default'     => 'processing',
					'desc_tip'    => true,
					'options'     => array(
							'processing'  => _x( 'Processing', 'Order status', 'woocommerce' ),
							'completed'	  => _x( 'Completed', 'Order status', 'woocommerce' ),
						),
				)
				);
		} 
		
		public function process_payment( $order_id ) {
			global $woocommerce;
			$order = wc_get_order( $order_id );
			return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));
		}
			/**
			 * Check for eToMiTreba Response
			 *
			 * @access public
			 * @return void
			 */
			function rbawsb_response() {

				global $woocommerce;
				@ob_clean();
				$Testiranje = $this->get_option( 'testing' );
				$RBA_Putanja = plugin_dir_path(__FILE__).'keys/';
				if($Testiranje == "yes")
					{
						$CertPath = $RBA_Putanja.'test-server.crt';
						$gateway = $this->checkout_url_test;
					} else {
						$CertPath = $RBA_Putanja.'work-server.crt';
						$gateway = $this->checkout_url;
				}
				$url = $gateway;
				if( !isset($_POST['OrderID']) || !isset($_POST['MerchantID']) || !isset($_POST['TerminalID']) || !isset($_POST['Currency']) || !isset($_POST['XID']) || !isset($_POST['TranCode']) ){
					wp_die();
				}
				
				$OrderID 	= sanitize_text_field($_POST['OrderID']);
				$MerchantID = sanitize_text_field($_POST['MerchantID']);
				$TerminalID = sanitize_text_field($_POST['TerminalID']);
				$Currency = sanitize_text_field($_POST['Currency']);
				$XID = sanitize_text_field($_POST['XID']);
				$ApprovalCode = sanitize_text_field($_POST['ApprovalCode']);
				$Rrn = sanitize_text_field($_POST['Rrn']);
				$ProxyPan = sanitize_text_field($_POST['ProxyPan']);
				$TranCode = sanitize_text_field($_POST['TranCode']);
				$PurchaseTime = sanitize_text_field($_POST['PurchaseTime']);
				$TotalAmount = sanitize_text_field($_POST['TotalAmount']);
				$SD = sanitize_text_field($_POST['SD']);

				if( !$this->is_valid_order($OrderID) || !$this->is_valid_merchant($MerchantID) || !$this->is_valid_terminal($TerminalID) || !$this->is_valid_currency($Currency) || !$this->is_valid_xid($XID) || !$this->is_valid_trancode($TranCode) || !$this->is_valid_totalamount($TotalAmount) ){
					wp_die();
				}

				$Delay = $OrderID;
				if(isset($_POST['Delay'])){
					if(sanitize_text_field($_POST['Delay'])=="1" || sanitize_text_field($_POST['Delay'])=="0") 
					{
						$Delay = $OrderID.",".sanitize_text_field($_POST['Delay']);
					}
				}	
				$data = "$MerchantID;$TerminalID;$PurchaseTime;$Delay;$XID;$Currency;$TotalAmount;$SD;$TranCode;$ApprovalCode;";
				$sign64 = sanitize_text_field($_POST["Signature"]);
				$signature = base64_decode($sign64);
				$fp = fopen($CertPath, "r");
				$cert = fread($fp, 8192);
				fclose($fp);
				$pubkeyid = openssl_get_publickey($cert);
				$ok = openssl_verify($data, $signature, $pubkeyid);
				$wc_order = wc_get_order( $OrderID );
				if($ok != 1)
					{
						$wc_order->add_order_note( sprintf(__('Digital signature error. Transaction number: %d', 'wsb-rba'), $XID ));
						$woocommerce->cart->empty_cart();
							echo "MerchantID=".$MerchantID."\n";
							echo "TerminalID=".$TerminalID."\n";
							echo "OrderID=".$OrderID."\n";
							echo "Currency=".$Currency."\n";
							echo "TotalAmount=".$TotalAmount."\n";
							echo "XID=".$XID."\n";
							echo "PurchaseTime=".$PurchaseTime."\n";
							echo "response.action=reverse\n";
							if($this->get_option( 'custompages' ) != "yes"){
								echo "Response.forwardUrl=".$this->get_return_url( $wc_order )."\n";
							}
							wp_die();
							
					} else {
						
						if( $TranCode == '000' ) {
							
							$wc_order->add_order_note( sprintf(__('Payment completed. Approval code: %d', 'wsb-rba'), $ApprovalCode) );
							$wc_order->payment_complete();
							if($this->get_option( 'statussuccess' ) === "completed") {
									$wc_order->update_status( 'completed', '', false );
							}
							$woocommerce->cart->empty_cart();
							
							echo "MerchantID=".$MerchantID."\n";
							echo "TerminalID=".$TerminalID."\n";
							echo "OrderID=".$OrderID."\n";
							echo "Currency=".$Currency."\n";
							echo "TotalAmount=".$TotalAmount."\n";
							echo "XID=".$XID."\n";
							echo "PurchaseTime=".$PurchaseTime."\n";
							echo "response.action=approve\n";
							if($this->get_option( 'custompages' ) != "yes"){
								echo "Response.forwardUrl=".$this->get_return_url( $wc_order )."\n";
							}
							wp_die();
							
						} else {
							$wc_order->add_order_note( sprintf(__('Forbidden transaction! Code: %d', 'wsb-rba'), $XID) );
							$wc_order->update_status( 'failed', '', false );
							$woocommerce->cart->empty_cart();
							
							echo "MerchantID=".$MerchantID."\n";
							echo "TerminalID=".$TerminalID."\n";
							echo "OrderID=".$OrderID."\n";
							echo "Currency=".$Currency."\n";
							echo "TotalAmount=".$TotalAmount."\n";
							echo "XID=".$XID."\n";
							echo "PurchaseTime=".$PurchaseTime."\n";
							echo "response.action=error\n";
							if($this->get_option( 'custompages' ) != "yes"){
								echo "Response.forwardUrl=".$this->get_return_url( $wc_order )."\n";
							}
							wp_die();
						} 
					} 
				
			}
		
		public function admin_options(){
			echo '<h3>'.$this->title.'</h3>';
			echo '<table class="form-table">';
			$this -> generate_settings_html();
			echo '</table>';
		}

		function payment_fields(){
				if($this -> description) echo wpautop(wptexturize($this -> description));
		}


	/**************** Validation start****************/

		public function is_valid_merchant($value){
			if (empty($value)) {
				return false;
			}
			if (!preg_match("/^[0-9]{5,15}$/", $value)) {
				return false;
			}
			return true;
		}
		public function is_valid_terminal($value){
			if (empty($value)) {
				return false;
			}
			if (!preg_match("/^[0-9A-Za-z]{8}$/", $value)) {
				return false;
			}
			return true;
		}
		public function is_valid_order($value){
			if (empty($value)) {
				return false;
			}
			if (!preg_match("/^[0-9A-Za-z\/-_.]{1,20}$/", $value)) {
				return false;
			}
			return true;
		}
		public function is_valid_currency($value){
			if (empty($value)) {
				return false;
			}
			if (!preg_match("/^[0-9]{3}$/", $value)) {
				return false;
			}
			return true;
		}
		public function is_valid_xid($value){
			if (empty($value)) {
				return false;
			}
			return true;
		}
		public function is_valid_trancode($value){
			if (empty($value)) {
				return false;
			}
			if (!preg_match("/^[0-9]{3}$/", $value)) {
				return false;
			}
			return true;
		}
		public function is_valid_totalamount($value){
			if (empty($value)) {
				return false;
			}
			if (!preg_match("/^[0-9]{1,12}$/", $value)) {
				return false;
			}
			return true;
		}

	/**************** Validation end****************/
		
	}
	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_rbawsb_gateway($methods) {
		$methods[] = 'WC_Gateway_Rbawsb';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_rbawsb_gateway' );
}