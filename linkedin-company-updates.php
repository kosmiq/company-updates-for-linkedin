<?php
/*
Plugin Name: LinkedIn Company Updates
Plugin URI:  http://www.rockwellgrowth.com/linkedin-company-updates/
Description: Get your company's recent updates with PHP or [shortcodes]
Version:     1.4.3
Author:      Andrew Rockwell
Author URI:  http://www.rockwellgrowth.com/
Text Domain: linkedin-company-updates
License:     GPL2v2

LinkedIn Company Updates is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

LinkedIn Company Updates is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with LinkedIn Company Updates. If not, see https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html.
*/

defined( 'ABSPATH' ) or die( 'Plugin file cannot be accessed directly.' );

if ( ! class_exists( 'LIupdates' ) ) {

	class LIupdates {

		//---- Set up variables
		protected $tag = 'linkedin_company_updates_options';
		protected $name = 'LIupdates';
		protected $version = '1.2';
		protected $options = array();
		protected $settings = array(
			'Client ID' => array(
				'description' => 'Your LinkedIn App Client ID.',
				'placeholder' => 'Client ID'
			),
			'Client Secret' => array(
				'description' => 'Your LinkedIn App Client Secret.',
				'placeholder' => 'Client Secret'
			),
			'Limit' => array(
				'description' => 'If no amount is specified in the shortcode, then this amount will be used.',
				'validator' => 'numeric',
				'placeholder' => 8
			),
			'Update Items Container Class' => array(
				'description' => 'This class will be added to the container of the update items. Leave a space between each class.',
				'placeholder' => 'li-updates-container'
			),
			'Update Item Class' => array(
				'description' => 'This class will be added to each update item. Leave a space between each class.',
				'placeholder' => 'li-updates-card'
			),
			'Include Default Styling' => array(
				'description' => 'Checking this will include the plugin\'s default styling for the feed.',
				'type' => 'checkbox'
			),
			'Redirect URL' => array(
				'description' => 'Fully qualified URLs to define valid OAuth 2.0 callback paths, as defined in your LinkedIn App.',
				'placeholder' => 'Redirect URL'
			),
			'Authorize Me' => array(
				'description' => 'Authorize Me.',
				'type' => 'authorize'
			),
			'Company ID' => array(
				'description' => 'Your LinkedIn Company ID.',
			)
		);

		//---- Initiate plugin
		public function __construct() {
			if ( $options = get_option( $this->tag ) ) {
				$this->options = $options;
			}
			if ( is_admin() ) {
				add_action( 'admin_init', array( &$this, 'settings' ) );
			}
		}

		//---- Make settings section for plugin fields
		public function settings() {
			$section = 'LinkedIn';
			add_settings_section(
				$this->tag . '_settings_section',
				null,
				function () {
					echo '<p>' . __('Configuration options for the LinkedIn Company Updates plugin.', 'linkedin-company-updates') . '</p>';
				},
				$section
			);
			foreach ( $this->settings AS $id => $options ) {
				$options['id'] = $id;
				add_settings_field(
					$this->tag . '_' . $id . '_settings',
					$id,
					array( &$this, 'settings_field' ),
					$section,
					$this->tag . '_settings_section',
					$options
				);
			}
			register_setting(
				$section,
				$this->tag,
				array( &$this, 'settings_validate' )
			);
		}

		//---- Add input fields to the settings section
		public function settings_field( array $options = array() ) {

			// Set up variables
			$redirect_url = get_bloginfo( 'wpurl' ) . '/wp-admin/options-general.php?page=linkedin_recent_updates';
			$db_options = get_option( 'linkedin_company_updates_options' );
			$lioauth_options = get_option( 'linkedin_company_updates_authkey' );

			$client_secret = $db_options['Client Secret'];
			$client_id = $db_options['Client ID'];
			$access_token = $lioauth_options['access_token'];

			//---- Output fields
			if ( isset( $options['id'] ) && $options['id'] == 'Authorize Me' ) {

				// Build parameters for the authorize link
				$_SESSION['state'] = $state = substr(md5(rand()), 0, 7);
			    $params = array(
			        'response_type' => 'code',
			        'client_id' => $client_id,
			        'state' => $state,
			        'redirect_uri' => $redirect_url,
			    );

			    // Determine whether authorizing for the first time, or make a regenerate button
				if( $lioauth_options ) {
					$authorize_string = __('Regenerate Access Token', 'linkedin-company-updates');

				    $dDiff = strtotime( $lioauth_options['expires_in'] ) - strtotime( date('Y-m-d H:m:s') );
				    $datetime = new DateTime('@' . $dDiff, new DateTimeZone('UTC'));
				    $times = array('days' => $datetime->format('z'), 'hours' => $datetime->format('G'), 'minutes' => $datetime->format('i'), 'seconds' => $datetime->format('s'));
                	$date = new DateTime();
					$date->modify('+' . $times['days'] . ' days');

					if( $dDiff > 0 ) {
						$authorization_message = '<p style=" display: inline-block; margin-left: 10px; "> ' . __('Authorization token expires in', 'linkedin-company-updates') . ' ' . $times['days'] . ' ' . __('days', 'linkedin-company-updates') . ', ' . $times['hours'] . ' ' . __('hours', 'linkedin-company-updates') . ' ( <i>' . $date->format('m / d / Y') . '</i> ) </p>';
					} else {
						$authorization_message = '<p style=" display: inline-block; margin-left: 10px; "> ' . __('Authorization token has expired, please regenerate.', 'linkedin-company-updates') . '</p>';
					}
				} else {
					$authorize_string = 'Authorize Me';
					$authorization_message = '<p style=" display: inline-block; margin-left: 10px; "> ' . __('You must authorize first to create a shortcode.', 'linkedin-company-updates') . '</p>';
				}

				// Output all the things
				echo '<a href="https://www.linkedin.com/uas/oauth2/authorization?' . http_build_query($params) . '" id="authorize-linkedin" class="button-secondary">' . $authorize_string . '</a>';
				echo $authorization_message;

			} elseif ( isset( $options['id'] ) && $options['id'] == 'Redirect URL' ) {
					echo '<p style=" display: inline-block; ">Add <a href="' . $redirect_url . '">' . $redirect_url . '</a> to the Authorized Redirect URLs in your LinkedIn Application.</p>';
			} else {

				$atts = array(
					'id' => $this->tag . '_' . str_replace(' ', '-', $options['id']),
					'name' => $this->tag . '[' . $options['id'] . ']',
					'type' => ( isset( $options['type'] ) ? $options['type'] : 'text' ),
					'class' => 'small-text',
					'value' => ( array_key_exists( 'default', $options ) ? $options['default'] : null )
				);
				if ( isset( $this->options[$options['id']] ) ) {
					$atts['value'] = $this->options[$options['id']];
				}
				if ( isset( $options['placeholder'] ) ) {
					$atts['placeholder'] = $options['placeholder'];
				}
				if ( isset( $options['type'] ) && $options['type'] == 'checkbox' ) {
					$stylings = 'style="width:16px;"';
					if ( $atts['value'] ) {
						$atts['checked'] = 'checked';
					}
					$atts['value'] = true;
				} else {
					$stylings = 'style="width:320px;"';
				}

				array_walk( $atts, function( &$item, $key ) {
					$item = esc_attr( $key ) . '="' . esc_attr( $item ) . '"';
				} );
				?>
					<label>
						<input <?php echo $stylings; echo implode( ' ', $atts ); ?> />
						<?php if ( array_key_exists( 'description', $options ) ) : ?>
						<?php esc_html_e( $options['description'] ); ?>
						<?php endif; ?>
					</label>
				<?php
			}
		}

		// Validate saved settings
		public function settings_validate( $input ) {
			$errors = array();
			foreach ( $input AS $key => $value ) {
				if ( $value == '' ) {
					unset( $input[$key] );
					continue;
				}
				$validator = false;
				if ( isset( $this->settings[$key]['validator'] ) ) {
					$validator = $this->settings[$key]['validator'];
				}
				switch ( $validator ) {
					case 'numeric':
						if ( is_numeric( $value ) ) {
							$input[$key] = intval( $value );
						} else {
							$errors[] = $key . ' must be a numeric value.';
							unset( $input[$key] );
						}
					break;
					default:
						 $input[$key] = strip_tags( $value );
					break;
				}
			}
			if ( count( $errors ) > 0 ) {
				add_settings_error(
					$this->tag,
					$this->tag,
					implode( '<br />', $errors ),
					'error'
				);
			}
			return $input;
		}

	}
	new LIupdates;

	class options_page {
		function __construct() {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		}
		function admin_menu () {
			add_options_page( 'LinkedIn Company Updates','LinkedIn Company Updates','manage_options','linkedin_recent_updates', array( $this, 'settings_page' ) );
		}
		function  settings_page () {
			$redirect_url = get_bloginfo( 'wpurl' ) . '/wp-admin/options-general.php?page=linkedin_recent_updates';
			$db_options = get_option( 'linkedin_company_updates_options' );
			if($db_options) {
				$client_id = $db_options['Client ID'];
				$client_secret = $db_options['Client Secret'];
			}

			if ( isset($_GET['code']) ) {
				$redirect_url = get_bloginfo( 'wpurl' ) . '/wp-admin/options-general.php?page=linkedin_recent_updates';
			    $params = array(
			        'grant_type' => 'authorization_code',
			        'client_id' => $client_id,
			        'client_secret' => $client_secret,
			        'code' => $_GET['code'],
			        'redirect_uri' => $redirect_url,
			    );
			    $url = 'https://www.linkedin.com/uas/oauth2/accessToken?' . http_build_query($params);
			    $context = stream_context_create(
			        array('http' =>
			            array('method' => 'POST',
			            )
			        )
			    );

			    if ( function_exists('curl_version') ) {
			    	$curl = curl_init();
			    	curl_setopt($curl, CURLOPT_URL, $url);
			    	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			    	$response = curl_exec($curl);
			    	curl_close($curl);
			    } else if ( file_get_contents(__FILE__) && ini_get('allow_url_fopen') ) {
			    	$response = file_get_contents($url, false, $context);
			    } else {
			    	echo '<div class="updated error"><p>' . __('You have neither cUrl installed nor allow_url_fopen activated. Please setup one of those!', 'linkedin-company-updates') . '</p></div>';
			    	die();
			    }
			    $token = json_decode($response, 1);

			    $end_date = date('Y-m-d H:m:s', time() + 86400 * 60);
			    $auth_options = array( 'access_token' => $token["access_token"], 'expires_in' => $end_date );
			    if(isset($token["access_token"]) && '' != $token["access_token"] && 5 < strlen( $token["access_token"] ) ) {
					update_option( 'linkedin_company_updates_authkey', $auth_options );

			        echo '<div class="updated"><p>' . __('Your LinkedIn authorization token has been successfully updated!', 'linkedin-company-updates') . '</p></div>';

					$_SESSION['access_token'] = $token->access_token;
					$_SESSION['expires_in']   = $token->expires_in;
					$_SESSION['expires_at']   = time() + $_SESSION['expires_in'];

				    echo "<script>";
					echo "	window.location.replace('" . get_bloginfo( 'wpurl' ) . "/wp-admin/options-general.php?page=linkedin_recent_updates');";
				    echo "</script>";
				} else {

			        echo '<div class="updated error"><p>' . __('Did not recieve an access token!', 'linkedin-company-updates') . '</p></div>';

				}

			}
		    ?>
		     <div class='wrap'>
		          <h2><?php _e( 'LinkedIn Company Updates', 'linkedin-company-updates' ); ?></h2>
		          <form method='post' action='options.php'>
		          <?php
		               settings_fields( 'LinkedIn' );
		               do_settings_sections( 'LinkedIn' );
		          ?>
		               <p class='submit'>
		                    <input name='submit' type='submit' id='submit' class='button-primary' value='<?php _e("Save Changes") ?>' />
		               </p>
		          </form>
		          <?php

					$lioauth_options = get_option( 'linkedin_company_updates_authkey' );

					$access_token = $lioauth_options['access_token'];
					$dDiff = strtotime( $lioauth_options['expires_in'] ) - strtotime( date('Y-m-d H:m:s') );

					if( isset($access_token) && $access_token != '' && $dDiff > 0 ) {

					    $i = 1;
					    $array = file_get_contents('https://api.linkedin.com/v1/companies?format=json&is-company-admin=true&oauth2_access_token='.$access_token, false);
					    $array = json_decode($array, 1);

					    if( 0 != $array['_total'] ) {
						    $array_companies = $array['values'];

						    echo '<span style="display:inline-block; width: 300px;">' . __('Find Your Company ID : ', 'linkedin-company-updates') . '</span><select id="select-company" name="select-company" value="select-company">';
						    foreach($array_companies as $company) {
						    	if($i == 1) {
						    		$company_id = $company['id'];
						    		$i++;
						    	}
						    	echo '<option value="' . $company['id'] . '" ' . selected( $company['id'] == $options['slider-posts'] ) . '>' . $company['name'] . ' - ' . $company['id'] . '</option>';
							}

						    echo '</select>';
					        echo '<p><span style="display:inline-block; width: 300px;"><b>' . __('Use this shortcode: ', 'linkedin-company-updates') . '</b></span><input style="display:inline-block; width: 300px;" type="text" id="linkedin_company_updates_shortcode" value="[li-company-updates limit=\'5\' company=\'' . $company_id . '\']"></p>';
					        echo '<p><span>' . __('Use shortcode [li-company-updates] to put the feed into content. For further documentation of shortcodes, go <a href="#">Here.</a>', 'linkedin-company-updates') . '</span></p>';
					    } else {
					    	echo '<b>' . __('No companies retrieved! Make sure you\'re the owner of the company via LinkedIn', 'linkedin-company-updates') . '</b>';
					    }

					} else {
						echo '<p style=" display: inline-block; ">' . __('Need to authorize first', 'linkedin-company-updates') . '</p>';
					}
					?>

		     </div>
		     <script>
			     jQuery(document).ready(function ($) {

			     	// Update Auth Button
			     	$('#linkedin_company_updates_options_Client-ID').on('input', function () {
			     		var linkedin_authorize_url = 'https://www.linkedin.com/uas/oauth2/authorization?response_type=code&client_id=' + $('#linkedin_company_updates_options_Client-ID').val() + '&state=<?php echo substr(md5(rand()), 0, 7); ?>&redirect_uri=<?php echo urlencode($redirect_url); ?>';
			     	});

			     	// Setup Shortcode Values
			     	window.linkedin_shortcode = 'li-company-updates';
			     	window.linkedin_company_id = window.linkedin_post_num = window.linkedin_conclass = window.linkedin_itemclass = '';

			     	if( $('#select-company').find('option:selected').val() ) {
			     		window.linkedin_company_id = ' company="' + $('#select-company').find('option:selected').val() + '"';
			     	}
			     	if( $('#linkedin_company_updates_options_Limit').val() ) {
			     		window.linkedin_post_num = ' limit="' + $('#linkedin_company_updates_options_Limit').val() + '"';
			     	}
			     	if($('#linkedin_company_updates_options_Update-Items-Container-Class').val()) {
			     		window.linkedin_conclass = ' con_class="' + $('#linkedin_company_updates_options_Update-Items-Container-Class').val() + '"';
			     	}
			     	if($('#linkedin_company_updates_options_Update-Item-Class').val()) {
			     		window.linkedin_itemclass = ' item_class="' + $('#linkedin_company_updates_options_Update-Item-Class').val() + '"';
			     	}

			     	// Update shortcode field
	     			$('#linkedin_company_updates_shortcode').val( '[' + window.linkedin_shortcode + window.linkedin_post_num + window.linkedin_company_id + window.linkedin_conclass + window.linkedin_itemclass + ']' );

	     			// Set up event listeners
			     	$('#select-company').live('change', function () {
			     		window.linkedin_company_id = $('#select-company').find('option:selected').val();
		     			$('#linkedin_company_updates_shortcode').val( '[' + window.linkedin_shortcode + window.linkedin_post_num + window.linkedin_company_id + window.linkedin_conclass + window.linkedin_itemclass + '"]' );
			     	});

			     	$('#linkedin_company_updates_options_Update-Items-Container-Class, #linkedin_company_updates_options_Update-Item-Class, #linkedin_company_updates_options_Limit').on('input', function () {
			     		if( $('#linkedin_company_updates_options_Update-Items-Container-Class').val() ) {
			     			window.linkedin_conclass = ' con_class="' + $('#linkedin_company_updates_options_Update-Items-Container-Class').val() + '"';
			     		}
			     		if( $('#linkedin_company_updates_options_Update-Item-Class').val() ) {
			     			window.linkedin_itemclass = ' item_class="' + $('#linkedin_company_updates_options_Update-Item-Class').val() + '"';
			     		}
			     		if( $('#linkedin_company_updates_options_Limit').val() ) {
			     			window.linkedin_post_num = ' limit="' + $('#linkedin_company_updates_options_Limit').val() + '"';
			     		}
		     			$('#linkedin_company_updates_shortcode').val( '[' + window.linkedin_shortcode + window.linkedin_post_num + window.linkedin_company_id + window.linkedin_conclass + window.linkedin_itemclass + ']' );
			     	});

			     });
		     </script>
		     <?php
		}
	}
	new options_page;

	//---- The shortcode
	function get_linkedin_company_updates( $atts ){

		// Set up shortcode attributes
		$args = shortcode_atts( array(
				'con_class' => '',
				'item_class' => '',
				'company' => '',
				'limit' => '',
			), $atts );

		// Set up options
		$db_options = get_option( 'linkedin_company_updates_options' );
		$lioauth_options = get_option( 'linkedin_company_updates_authkey' );

		if( isset($args['con_class']) && $args['con_class'] != '' ) {
			$container_class = $args['con_class'];
		} elseif( isset($db_options['Update Items Container Class']) ) {
			$container_class = $db_options['Update Items Container Class'];
		} else {
			$container_class = '';
		}

		if( isset($args['item_class']) && $args['item_class'] != '' ) {
			$card_class = $args['item_class'];
		} elseif( isset($db_options['Update Item Class']) ) {
			$card_class = $db_options['Update Item Class'];
		} else {
			$card_class = '';
		}

		if( isset($args['company']) && $args['company'] != '' ) {
			$linkedin_company_id = $args['company'];
		} elseif( isset($db_options['Company ID']) && isset($db_options['Company ID']) != '' ) {
			$linkedin_company_id = $db_options['Company ID'];
		} else {
			echo __('Company ID not set', 'linkedin-company-updates');
			return;
		}

		if( isset($args['limit']) && $args['limit'] != '' ) {
			$linkedin_limit = $args['limit'];
		} elseif( isset($db_options['Limit']) ) {
			$linkedin_limit = $db_options['Limit'];
		} else {
			$linkedin_limit = 5;
		}

		// Make sure auth token isn't expired
	    $dDiff = strtotime( $lioauth_options['expires_in'] ) - strtotime( date('Y-m-d H:m:s') );
	    if( $dDiff > 0 ) {

	    	// Set up dem vars
			$access_token = $lioauth_options['access_token'];
			$api_response = file_get_contents('https://api.linkedin.com/v1/companies/' . $linkedin_company_id . '/updates?count=' . $linkedin_limit . '&format=json&oauth2_access_token='.$access_token, false);
			$array = json_decode($api_response, 1);
			$array_updates = $array['values'];

			$company_info = file_get_contents('https://api.linkedin.com/v1/companies/' . $linkedin_company_id . ':(id,name,ticker,description,square-logo-url)?format=json&oauth2_access_token='.$access_token, false);
			$company_info = json_decode($company_info, 1);
			$logo_url = $company_info['squareLogoUrl'];

			// Build the list of updates
			$company_updates = '<ul id="linkedin-con" class="' . $container_class . '">';
			$company_updates .= '	<h2><img src="' . get_bloginfo('url') . '/wp-content/plugins/company-updates-for-linkedin/linkedin-logo.gif" />' . __( 'LinkedIn Company Updates', 'linkedin-company-updates' ) . '</h2>';
			if( is_array($array_updates) && !empty($array_updates) ) {
				foreach ($array_updates as $update) {

					// Set up the time ago strings
					$update_share = $update['updateContent']['companyStatusUpdate']['share'];
					$d1 = new DateTime( date('m/d/Y', time() ) );
					$d2 = new DateTime( date('m/d/Y', $update_share['timestamp'] / 1000 ) );
					$months = $d1->diff($d2)->m;
					$month_string = $months . ' ' . __('Months', 'linkedin-company-updates');
					$days = $d1->diff($d2)->d;
					$days_string = $days . ' ' . __('Days', 'linkedin-company-updates');

					if( 0 == $d1->diff($d2)->days ) {
						$time_ago = __('Today', 'linkedin-company-updates');
					} elseif( isset($months) && $months > 0 ) {
						$time_ago = $month_string;
						if( isset($days) && $days > 0 ) {
							$time_ago .= ', ' . $days_string;
						}
						$time_ago .= ' ' . __('Ago', 'linkedin-company-updates');
					} else {
						$time_ago = $days_string . ' ' . __('Ago', 'linkedin-company-updates');
					}

					echo '<script>console.log(' . json_encode($update) . ');</script>';

					// Add picture if there is one
					$img = '';
					if( array_key_exists( 'content', $update_share ) ) {
						$shared_content = $update_share['content'];

						if( array_key_exists( 'submittedImageUrl', $shared_content )
							&& 'https://static.licdn.com/scds/common/u/img/spacer.gif' !== $shared_content['submittedImageUrl'] ) {

								$update_image_url = $shared_content['submittedImageUrl'];
								$update_image_link = $shared_content['submittedUrl'];

								$img = '<a target="_blank" href="' . $update_image_link . '"><img alt="" class="linkedin-update-image" src="' . $update_image_url . '" /></a>';

						}

					}

					// Filter the content for links
					$update_content = preg_replace('!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i', '<a target="_blank" href="$1">$1</a>', $update_share['comment']);

					// Create the link to the post
					$update_pieces = explode( '-', $update['updateKey'] );
					$update_id = end( $update_pieces );
					$update_url = 'https://www.linkedin.com/nhome/updates?topic=' . $update_id;

					// Add this item to the update string
					$company_updates .= '<li id="linkedin-item" class="' . $card_class . '">';
					$company_updates .= 	'<img class="linkedin-update-logo" src="' . $logo_url . '" />';
					$company_updates .= 	'<span>';
					$company_updates .= 		'<i>' . $time_ago . '</i>';
					$company_updates .= 		'<a target="_blank" href="' . $update_url . '">' . __('View on LinkedIn', 'linkedin-company-updates') . '</a>';
					$company_updates .= 	'</span>';
					$company_updates .= 	'<div>';
					$company_updates .= 		$img;
					$company_updates .= 		'<h3><a target="_blank" href="https://www.linkedin.com/company/' . $linkedin_company_id . '">' . $update['updateContent']['company']['name'] . '</a></h3>';
					$company_updates .= 		'<p>' . $update_content . '</p>';
					$company_updates .= 	'</div>';
					$company_updates .= '</li>';
				}
			} else {
				$company_updates .= '<div style="display: none !important"><input type="hidden" id="linkedin-company-debugging" value="' . $linkedin_company_id . '" /><input type="hidden" id="linkedin-updates-debugging" value="' . htmlspecialchars($api_response) . '" /></div>';
				$company_updates .= '<li>' . __('Sorry, no posts were received from LinkedIn!', 'linkedin-company-updates') . '</li>';
			}
			$company_updates .= '</ul>';

			return $company_updates;
		} else {
			return __("Authorization has expired", 'linkedin-company-updates');
		}
	}
	add_shortcode('li-company-updates', 'get_linkedin_company_updates');

	function linkedin_company_updates( $atts ) {
		echo get_linkedin_company_updates( $atts );
	}

	//---- Add the handy settings link to the plugins page
	function add_company_updates_plugin_action_links ( $links ) {
		$links[] = '<a href="' . admin_url( 'options-general.php?page=linkedin_recent_updates' ) . '">Settings</a>';
   		return $links;
	}
	add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'add_company_updates_plugin_action_links' );

	//---- If there is a custom stylesheet, enqueue it
	function add_company_updates_stylesheet() {
		wp_enqueue_style( 'company_updates_style', plugins_url( 'company-updates-for-linkedin/style.css' ) );
	}
	$stylingBool = get_option( 'linkedin_company_updates_options' );
	if( $stylingBool['Include Default Styling'] ) {
		add_action( 'wp_enqueue_scripts', 'add_company_updates_stylesheet' );
	}

	function linkedin_company_updates_load_textdomain() {
	 $plugin_dir = basename(dirname(__FILE__));
	 load_plugin_textdomain( 'linkedin-company-updates', false, $plugin_dir . '/languages' );
	}
	add_action('plugins_loaded', 'linkedin_company_updates_load_textdomain');

}
