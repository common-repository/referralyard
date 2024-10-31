<?php

class referralyardSettingsPage {
    /**
     * Holds the values to be used in the fields callbacks
     */
    public $options;

    /**
     * Start up
     */
    public function __construct(){
        add_action( 'admin_menu', array( $this, 'referralyard_menu' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    function referralyard_menu(){
        add_menu_page( 'ReferralYard', 'ReferralYard', 'manage_options', 'referralyard-menu', array( $this, 'create_admin_page' ), plugins_url( '/assets/img/icon.svg', __FILE__ ) );
    }

    /**
     * Options page callback
     */
    public function create_admin_page() {
        global $referralBaseUrl;
        // Set class property
        $this->options = get_option( 'referralyard_options' );
        ?>
        <div class="wrap">
            <div style="text-align: center; margin-top: 20px;">
                <img style="max-height: 50px;" src="<?php echo plugins_url( '/assets/img/logo.png', __FILE__ );?>" />
                <p class="description" style="padding-bottom: 10px; margin-top: 20px;">
                    <?php _e("Don't have a ReferralYard account? Sign up Free in seconds: ");?>
                    <button class="button-secondary" type='button' onclick="location.href='<?php echo $referralBaseUrl; ?>'" style="margin-left: 10px; margin-top: -5px;">
                        <?php _e("Create a Free Account");?>                            
                    </button>
                </p>
            </div>
            <hr style="border-bottom: 1px black solid;"/>
            <form method="post" action="options.php">
                <?php
                    // This prints out all hidden setting fields
                    settings_fields( 'my_option_group' );   
                    do_settings_sections( 'referralyard-settings' );
                    submit_button(); 
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init(){        
        register_setting(
            'my_option_group', // Option group
            'referralyard_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            __('Api Settings'), // Title
            array( $this, 'print_section_info' ), // Callback
            'referralyard-settings' // Page
        );  

        add_settings_field(
            'referralyard_api_key', 
            'API Key', 
            array( $this, 'referralyard_api_key' ), 
            'referralyard-settings', 
            'setting_section_id'
        );

        add_settings_field(
            'referralyard_api_secret', 
            'API Secret', 
            array( $this, 'referralyard_api_secret' ), 
            'referralyard-settings', 
            'setting_section_id'
        );
        
        add_settings_section(
            'setting_section_two', // ID
            __('Order Settings'), // Title
            array( $this, 'print_section_info_two' ), // Callback
            'referralyard-settings' // Page
        );
        
        add_settings_field(
            'referralyard_order_status', 
            __('Order Status:'), 
            array( $this, 'referralyard_order_status' ), 
            'referralyard-settings', 
            'setting_section_two'
        );        
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input ){
        $new_input = array();

        if( isset( $input['referralyard_api_key'] ) )
            $new_input['referralyard_api_key'] = sanitize_text_field( $input['referralyard_api_key'] );
        
        if( isset( $input['referralyard_api_secret'] ) )
            $new_input['referralyard_api_secret'] = sanitize_text_field( $input['referralyard_api_secret'] );
        
        if( isset( $input['referralyard_success_info'] ) )
            $new_input['referralyard_success_info'] = $input['referralyard_success_info'];
                
        if( isset( $input['referralyard_order_status'] ) )
            $new_input['referralyard_order_status'] = sanitize_text_field( $input['referralyard_order_status'] );
        
        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info(){
        print '<p>' . __('Enter in your Account Information and than click "Verify Settings" button to connect your WooCommerce Site to your ReferralYard account. Be sure to click save at the bottom after successfully verifying.') . '</p>';
    }
    
    public function print_section_info_two(){
        print '<p>' . __('Select the order status below when you would like WooCommerce orders to be sent to ReferralYard.') . '</p>';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function referralyard_api_key(){
        global $referralBaseUrl;
        printf(
            '<input type="text" id="referralyard_api_key" name="referralyard_options[referralyard_api_key]" value="%s" style="min-width:450px;" />',
            isset( $this->options['referralyard_api_key'] ) ? esc_attr( $this->options['referralyard_api_key']) : ''
        );
        print '<div class="description" style="margin-top: 5px;">' . __( 'Your API Key can be found within your ') . '<a href="'.$referralBaseUrl.'account/settings" target="_blank">' . __('Account.') . '</a></div>';
    }
    
    public function referralyard_api_secret(){
        global $referralBaseUrl;
        printf(
            '<input type="text" id="referralyard_api_secret" name="referralyard_options[referralyard_api_secret]" value="%s" style="min-width:450px;" />',
            isset( $this->options['referralyard_api_secret'] ) ? esc_attr( $this->options['referralyard_api_secret']) : ''
        );
        print '<div class="description" style="margin-top: 5px;">' . __( 'Your API Secret can be found below the API Key within your ') . '<a href="'.$referralBaseUrl.'account/settings" target="_blank">' . __('Account.') . '</a></div>';        
        $this->print_verify_button();
    }

    public function referralyard_order_status(){
        $completed = ( $this->options['referralyard_order_status'] == "wc-completed" ) ? 'selected' : '';
        $processing = ( $this->options['referralyard_order_status'] == "wc-processing" ) ? 'selected' : '';
        $select = '<select name="referralyard_options[referralyard_order_status]" id="referralyard_order_status" style="min-width:450px;" >';
        $select .= '<option value="wc-completed" ' . $completed . ' >' . __('Completed') . '</option>';
        $select .= '<option value="wc-processing" ' . $processing . '>' . __('Processing') . '</option>';
        $select .= '</select>';
        print $select;
    }
    
    public function print_verify_button(){
        $referralyard_success_info = isset( $this->options['referralyard_success_info'] ) ? esc_attr( $this->options['referralyard_success_info']) : '';
        $api_status = ($this->options['referralyard_success_info']) ? __('<span style="color: #109810;">Connected</span>') : __('<span style="color: red;">Disconnected</span>');

        ?> <script>
            jQuery(document).ready(function(){
                jQuery('#referralyard_verify_button').click(function(e){
                    e.preventDefault();
                    var api_key = jQuery('#referralyard_api_key').val();
                    var api_secret = jQuery('#referralyard_api_secret').val();
                    if(!api_key || !api_secret ){
                        alert('All fields are required!');
                        return false;
                    }
                    jQuery('#verify_estatus').html('Loading...');
                    jQuery.ajax({
                        data: {
                            'action': 'referralyard_verify_account',
                            'api_key': api_key,
                            'api_secret': api_secret
                        },
                        dataType: 'json',
                        url: ajaxurl,
                        type: 'post',
                        success: function(response) {
                            if(response == 200) {
                                jQuery('#verify_estatus').html('<span style="color: #109810;">Connected</span>');
                                jQuery('#referralyard_success_info').val('1');
                            } else {
                                jQuery('#verify_estatus').html('<span style="color: red;">Failed, Please re-check your entry and try again</span>');
                                jQuery('#referralyard_success_info').val('0');
                            }
                        },
                        error: function(response) {
                            jQuery('#verify_estatus').html('<span style="color: red;">Failed, Please try again</span>');
                        }
                    });
                });
            });
        </script>
        <tr>
            <th scope='row'></th>
            <td>
                <input type="hidden" 
                       id="referralyard_success_info" 
                       name="referralyard_options[referralyard_success_info]" 
                       value="<?php echo $referralyard_success_info; ?>"/>

                <div style="display: flex; align-items: center;">
                    <span>
                        <button class="button-secondary" 
                                type="button" 
                                name="referralyard_verify_button" 
                                id="referralyard_verify_button">Verify Settings</button>
                    </span>
                    <div class="description" style="margin-left: 12px">
                        Connection Status: <span id="verify_estatus" style="font-weight: 600;margin-left: 5px;"><?php echo $api_status; ?></span>
                    </div>                    
                </div>
            </td>
        </tr>
    <?php }
}