<?php
/*
Plugin Name: Badge Factor events
Plugin URI: https://github.com/DigitalPygmalion
Description: Un plugin qui permet de créer un événement, importer des utilisateurs et leur attribuer un badge et eventuellement un montant dans le wallet.
Version: 0.1.0
Author: Parkour3
License: GPL2
Text Domain: bf-events
Domain Path: /languages/
*/

namespace BadgeFactorEvent;

use BadgeFactorEvent\Admin\Notices;
use BadgeFactorEvent\Lib\PostTypes;
use BadgeFactorEvent\Lib\OrganisationManager;
use BadgeFactorEvent\Lib\EventTable;
use BadgeFactorEvent\Lib\OrganisationTable;
use BadgeFactorEvent\Lib\Statistics;
use BadgeFactorEvent\Lib\EventManager;

// If this file is accessed directory, then abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Include the autoloader so we can dynamically include the rest of the classes.
require_once( trailingslashit( dirname( __FILE__ ) ) . 'Inc/autoloader.php' );
require_once( trailingslashit( dirname( __FILE__ ) ) . 'vendor/autoload.php' );

class Cadre21InvitationEvent
{
	/**
	 * @var
	 */
    public static $pluginDir;

	/*
	 *
	 * Constructor of the class
	 */
	public function __construct()
	{

	    self::$pluginDir = plugin_dir_url( __FILE__ );
		add_action('init', array($this, 'register_post_types'));
		add_action('admin_menu', array($this, 'add_admin_menu'));

		//catch all the events
		add_action('init', array($this, 'cie_catch_events'));

		//Check if the registered user is in a member list.
		add_action( 'user_register', array($this, 'check_user'), 10, 1 );

		//Jquery UI
		add_action( 'admin_enqueue_scripts', array($this,'add_jquery_ui') );
		add_action( 'wp_ajax_cie_user_info', array($this,'get_user_information') );


		add_action( 'plugins_loaded', array($this,'load_textdomain') );

		Notices::init();

		if (isset($_GET['page']) && ($_GET['page'] == 'cadre21'))
		{
			add_action('admin_print_styles', array( $this, 'plugin_styles'));
			add_action('admin_enqueue_scripts', array( $this, 'plugin_scripts'));
        }
	}


	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'bf-events' , false, basename( dirname( __FILE__ ) ) . '/languages' );
	}


	/**
	 * Load the styles of the plugin
	 */
	public function plugin_styles() {
		wp_enqueue_style('bf-events',  self::$pluginDir . 'dist/css/styles.css');
    }

	/**
	 * Load the scripts of the plugin
	 */
	public function plugin_scripts() {
		wp_enqueue_script('bf-events', self::$pluginDir . 'dist/js/main.js', array('jquery','jquery-ui-datepicker' ), false);
	}

	/**
	 *
	 * Register the posttypes for this plugin.
	 * Prefix of the posttypes: cie_
	 */
	public function register_post_types()
    {
        $plugin_posttypes = new PostTypes();

       if(is_wp_error($plugin_posttypes->getOrganisationPostType())){
	        $message = __( 'Could not create the Organisation PostType.', '' );
	        Notices::add_notice('error', $message);
        }

		if(is_wp_error($plugin_posttypes->getGroupPostType())){
			$message = __( 'Could not create the Group PostType.', '' );
			Notices::add_notice('error', $message);
		}

		if(is_wp_error($plugin_posttypes->getGroupMemberPostType())){
			$message = __( 'Could not create the Group Member PostType.', '' );
			Notices::add_notice('error', $message);
		}
	}

	/**
	 * @param $message
	 */
	public function display_admin_error( $message )
    {
	    add_action( 'admin_notices', function ($message){
		    $class = 'notice notice-error';

		    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	    });
    }

	/*
	 * Function to create a new group.
	 */
	public function create_new_organisation(){

	    $organisationManager = new OrganisationManager();


	    $title = (isset($_POST['cie_group_name']))? $_POST['cie_group_name'] : false;

	    if( $title ){
		    $organisation = $organisationManager->create( $title );

		    if( is_wp_error($organisation) ){
			    $message = __( 'Failed to create the new Organisation.', '' );
			    $this->display_admin_error( $message );
            }
        }
	}

	/*
	 * Function to create a new group.
	 */
	public function create_new_group(){

		if(!isset($_POST['cie_organisation_id']) || empty($_POST['cie_organisation_id'])){
			wp_die( esc_html( "You can't add a new event without an organisation ID" ) );
		}

		$args = array(
		  'post_title'    => wp_strip_all_tags( $_POST['cie_group_name'] ),
		  'post_status'   => 'private',
		  'post_type'     => 'cie_group'
		);

		$post_id = wp_insert_post( $args );

		//Adding the amount for the wallet and the badgeID
		add_post_meta($post_id, '_cie_group_wallet_amount', $_POST['cie_group_wallet'], false);

		//Adding the organisation ID to the event.
		add_post_meta($post_id, 'organisation', $_POST['cie_organisation_id'], false);

		if($_POST['cie_group_badge'] != ''){
			add_post_meta($post_id, '_cie_group_badge_id', $_POST['cie_group_badge'], false);
		}
		else{
			add_post_meta($post_id, '_cie_group_badge_id', '0', false);
		}

		//Now we import all the users off the importatin file.
		if ( isset($_FILES['cie_group_import_file'])) {
			if ($_FILES['cie_group_import_file']['error'] > 0) {	            
				echo 'An error occured, code: ' . $_FILES['cie_group_import_file']['error'] . '<br />';
	        }
	        else{
	        	//No errors, import the file.

	        	$storagename = 'cie_import_file.csv';
	        	$uploadDir = wp_upload_dir();
            	move_uploaded_file($_FILES['cie_group_import_file']["tmp_name"], $uploadDir['path']. '/' . $storagename);

	        	if (($handle = fopen($uploadDir['path']. '/' . $storagename, "r")) !== FALSE) {

	        		$row = 1;
	        		while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
				        $row++;

				        if($data[0] != '' || !empty($data[0])){
				        
						    $new_group_member_args = array(
						      	'post_title'    => strtolower($data[0]),
								'post_status'   => 'private',
								'post_type'     => 'cie_group_member'
						    );
						        
						    //email (0), lastname (1), firstname (2)
						    $member_id = wp_insert_post( $new_group_member_args );
						    add_post_meta($member_id, '_cie_group_id', $post_id, false);
						    add_post_meta($member_id, '_cie_member_registered', false, false);
						    add_post_meta($member_id, '_cie_member_purchased', false, false);
						    add_post_meta($member_id, '_cie_member_email', strtolower($data[0]), false);
						    add_post_meta($member_id, '_cie_member_lastname', $data[1], false);
						    add_post_meta($member_id, '_cie_member_firstname', $data[2], false);


						    //Check if the user is already registered.
					        $user = get_user_by('email', $data[0]);

						   	if($user){
						   		//Set the user to the member post
						   		update_post_meta($member_id, '_cie_member_registered', $user->ID, false);



						   		/////////////////////////////////////////////////////
						   		// Comment this section if adding the GRMS members //
						   		/////////////////////////////////////////////////////

						   		//update the users wallet
						   		$current_wallet = intval(get_user_meta($user->ID, '_uw_balance', true));
						   		$new_wallet = $current_wallet + intval($_POST['cie_group_wallet']);
								update_user_meta($user->ID, '_uw_balance', $new_wallet);

								//Give the user the badge
								//Set the badge for the user.
								if($_POST['cie_group_badge'] != '0' ){
									$response = bos_gform_create_submission( $_POST['cie_group_badge'],wp_strip_all_tags( $_POST['cie_group_name'] ),'', $user->ID );
								}

								/////////////////////////////////////////////////////
						   		//                    END Comment                  //
						   		/////////////////////////////////////////////////////


								//@TODO: Send a notificatin to the user to inform he has been granted a new badge and an amount to his wallet.
				    		}
			    		}
				        
				    }

				    fclose($handle);
				}
	        }
		}

		//redirect to the organisation page.

        $redirect_url = admin_url().'/admin.php?page=cadre21&subpage=events&org='. $_POST['cie_organisation_id'];
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * This function catches the events to create groups or add members
	 */
	public function cie_catch_events(){
		if (isset($_POST['cie_group_create_submit']) && !empty($_POST['cie_group_create_submit'])) {
				$this->create_new_group();
		}

		if( isset($_POST['cie_add_member_submit']) && !empty($_POST['cie_add_member_email']) && !empty($_POST['cie_add_member_lastname']) && !empty($_POST['cie_add_member_firstname']) ){
			$this->add_member_to_group();
		}
	}

	/**
	 *
	 * This function is triggered just after the user registration.
	 * It checks if the new is part of a group.
	 * If so, the user gets a badge assigned and an certain amount in his wallet. 
	 *
	 */
	public function check_user($user_id) {

	    //get the email of the user
	    $registered_user = get_userdata( $user_id );
	    $user_mail = strtolower($registered_user->user_email);


	    //Get all the registered members in the group.
      	$member_args = array(
	        'posts_per_page'   => 1,
	        'post_type'        => 'cie_group_member',
	        'post_status'      => 'private',
	        'meta_query' => array(
	          array(  
	            'key'=> '_cie_member_email',
	            'value'   => $user_mail
	          ),

	        )
      	);

      	$member_post = get_posts( $member_args );
      	if(is_array($member_post)){
      		$member_post = array_shift($member_post);
      	}

	    if($member_post && $member_post != ''){

	    	//Setting the post meta for the registered user. (input the id of the new user.)
	    	update_post_meta($member_post->ID, '_cie_member_registered', $user_id, false);

	    	//Getting the group for the member
	    	$group_id = get_post_meta($member_post->ID, '_cie_group_id', true);
	    	$group = get_post( $group_id );

	    	//Getting the amount for the wallet.
	    	$wallet_amount = get_post_meta($group_id, '_cie_group_wallet_amount', true);
	    	$badge_id = get_post_meta($group_id, '_cie_group_badge_id', true);
	    	

	    	//update the user info
	    	$userdata = array(
		    	'ID' => $user_id,
			    'first_name'  =>  get_post_meta($member_post->ID, '_cie_member_firstname', true),
			    'last_name'    =>  get_post_meta($member_post->ID, '_cie_member_lastname', true),
			);
	    	wp_update_user( $userdata );

	    	//update the users wallet
			update_user_meta($user_id, '_uw_balance', floatval($wallet_amount));


			//Set the group name as the user organisation
			$return = xprofile_set_field_data( 2, $user_id, $group->post_title);
			$return = xprofile_set_field_data( 5, $user_id, get_post_meta($group_id, '_cie_group_website', true));

			//Set the badge for the user.
			if($badge_id != '0' || !empty($badge_id) ){
				$response = bos_gform_create_submission( $badge_id, $title = $group->post_title, $content = '', $user_id );
			}
	    }
	}


	/**
	 *
	 * Adding a member to a created group.
	 *
	 */
	public function add_member_to_group(){


		$new_group_member_args = array(
        	'post_title'    => strtolower($_POST['cie_add_member_email']),
			'post_status'   => 'private',
			'post_type'     => 'cie_group_member'
        );
	        
	    $member_id = wp_insert_post( $new_group_member_args );
	    add_post_meta($member_id, '_cie_group_id', $_POST['cie_add_member_group_id'], false);
	    add_post_meta($member_id, '_cie_member_purchased', false, false);
	    add_post_meta($member_id, '_cie_member_email', strtolower($_POST['cie_add_member_email']), false);
	    add_post_meta($member_id, '_cie_member_lastname', $_POST['cie_add_member_lastname'], false);
	    add_post_meta($member_id, '_cie_member_firstname', $_POST['cie_add_member_firstname'], false);


	    $user = get_user_by('email', strtolower($_POST['cie_add_member_email']));
		

		if($user){

			//Getting the group for the member
	    	$group_id = get_post_meta($member_id, '_cie_group_id', true);
	    	$group = get_post( $group_id );

	    	//Getting the amount for the wallet.
	    	$wallet_amount = get_post_meta($group_id, '_cie_group_wallet_amount', true);
	    	$badge_id = get_post_meta($group_id, '_cie_group_badge_id', true);
						   		
			update_post_meta($member_id, '_cie_member_registered', $user->ID, false);

			//update the users wallet
	   		$current_wallet = intval(get_user_meta($user->ID, '_uw_balance', true));
	   		$new_wallet = $current_wallet + intval($wallet_amount);
			update_user_meta($user->ID, '_uw_balance', $new_wallet);

			//Give the user the badge
			//Set the badge for the user.
			if($badge_id != '0' ){
				$response = bos_gform_create_submission( $badge_id, wp_strip_all_tags( $group->post_title),'', $user->ID );
			}
		}
		else{
			add_post_meta($member_id, '_cie_member_registered', false, false);
        }
	}



	/////////////////////////////////////////////
	///  All functions for the admin display  ///
	/////////////////////////////////////////////


	/**
	 *
	 * Add the admin menu for the plugin. 
	 *
	 */
	public function add_admin_menu(){
		add_menu_page('Invitation d\'événement', 'Event Invitation', 'manage_options', 'cadre21', array($this, 'plugin_routing'), 'dashicons-groups',30);
		add_submenu_page('cadre21', 'Créer un nouveau groupe', 'Créer un nouveau groupe', 'manage_options', 'cadre21-creation', array($this, 'creation_html'));
	}


	/**
	 * Routing of the plugin pages
	 */
	public function plugin_routing()
    {

        $pageSlug =( isset($_GET['subpage']))? $_GET['subpage'] : false;

        switch($pageSlug){
            case 'events':
                if(isset($_GET['org'])){
                    $this->organisation_events_html($_GET['org']);
                }
                else{
	                wp_die( esc_html( "You can't access this page without an organisation ID" ) );
                }
                break;

	        case 'new-event':
		        if(isset($_GET['org'])){
			        $this->create_new_organisation_event( $_GET['org'] );
		        }
		        else{
			        wp_die( esc_html( "You can't access this page without an organisation ID" ) );
		        }

		        break;
	        case 'event-stats':
		        if(isset($_GET['event'])){
			        $this->single_event_html( $_GET['event'] );
		        }
		        else{
			        wp_die( esc_html( "You can't access this page without an event ID" ) );
		        }

		        break;

            default:
                $this->home_html();
                break;
        }
    }

	/**
	 *
	 * The homepage for the plugin.
	 *
	 */
	public function home_html()
	{
		echo '<style> .column-organisation{ vertical-align: middle;} </style>';
        echo '<h1>'.get_admin_page_title().'</h1>';
        echo '<p>Bienvenue sur la page d\'accueil du plugin d\'invitatin de Cadre21</p>';
        //echo '<a href="'. admin_url().'admin.php?page=cadre21-creation" class="button primary-button">'. __('Add a new group', 'cie') .'</a>';

        //Show a table with all the groups that are created.
        $groupTable = new OrganisationTable();
        $groupTable->prepare_items();
        $groupTable->display();
	}

	/**
	 *
	 */
	public function organisation_events_html( $organisation_id ){

	    $organisation = get_post( $organisation_id );

	    if( get_post_type( $organisation ) != 'organisation' ){
		    wp_die( esc_html( "The given ID is not of a valid organisation" ) );
        }

        if( isset($_GET['bulk-stats']) || isset($_GET['stats_start_date']) || isset($_GET['stats_end_date']) ){
            $start_date = (isset($_GET['stats_start_date']) && !empty($_GET['stats_start_date']) )? \DateTime::createFromFormat('d/m/Y', $_GET['stats_start_date']) : false;
            $end_date = (isset($_GET['stats_end_date']) && !empty($_GET['stats_end_date']))? \DateTime::createFromFormat('d/m/Y', $_GET['stats_end_date']) : false;
            $events = (isset($_GET['bulk-stats']))? $_GET['bulk-stats'] : false;

            Statistics::generateReport( $events, $start_date, $end_date );
        }

        $output = '<div data-interface="events">';
		$output .= '<h1>' . $organisation->post_title . '</h1>';
		$output .= '<a href="'. admin_url().'admin.php?page=cadre21&subpage=new-event&org='.$organisation_id.'" class="button primary-button">'. __('Add a new group', 'bf-events') .'</a>';

		$output .= '<form action="" method="get">';
		$output .= '<input type="hidden" name="page" value="cadre21">';
		$output .= '<input type="hidden" name="subpage" value="events">';
		$output .= '<input type="hidden" name="org" value="'.$organisation_id.'">';

		$output .= '<div class="stats-form">';
		$output .= '<div class="form-group"><label>' . __('Start date', 'bf-events') . '</label><input type="text" class="datepicker form-field" name="stats_start_date" autocomplete="off"/></div>';
        $output .= '<div class="form-group"><label>' . __('End date', 'bf-events') . '</label><input type="text" class="datepicker form-field" name="stats_end_date" autocomplete="off" /></div>';
        $output .= '<input type="submit" value="' . __("Export the statistics", 'bf-events') .'" class="button button-primary button-large">';
        $output .= '</div>';

		ob_start();

		$eventTable = new EventTable( $organisation_id );
		$eventTable->prepare_items();
		$eventTable->display();

		$output .= ob_get_clean();

		$output .= '</form>';

		echo $output;
    }

	/**
	 *
	 * The page to create a new group.
	 *
	 */
	public function create_new_organisation_event( $organisation_id )
	{

	    $organisation = get_post($organisation_id);

		$badges_args = array(
	        'posts_per_page'   => -1,
	        'post_type'        => 'badges',
	    );

      	$badges = get_posts( $badges_args );

	    $output = '<h1>'. __('Create a new event for ', 'bf-events'). $organisation->post_title . '</h1>';
	    $output .= '<p>'. __('Please complete the form below in order to create a new event', 'bf-events').'</p>';
	    $output .= '<form action="'. admin_url().'admin.php?page=cadre21" method="post" enctype="multipart/form-data">';
	    $output .= __('Event name', 'bf-events').':<br><div class="form-group"><input type="text" name="cie_group_name" class="form-field"></div><br>';
	    $output .= __('Amount of the wallet (optional)', 'bf-events').':<br><div class="form-group"><input type="number" name="cie_group_wallet" class="form-field"></div><br>';
	    $output .= __('Associated badge', 'bf-events').':<br><div class="form-group"><select name="cie_group_badge" class="form-field">';
	    $output .= '<option value="">'.__('No badge', 'bf-events').'</option>';
		    foreach ($badges as $badge) {
		    	$output .= '<option value="'.$badge->ID.'">'.$badge->post_title.'</option>';
		    }
	    $output .= '</select></div><br>';
		$output .= '<input type="hidden" name="cie_organisation_id" value="' .  $organisation_id . '">';
	    $output .= __('CSV file', 'bf-events').':<br><input type="file" name="cie_group_import_file"><br>';
	    $output .= '<input type="submit" name="cie_group_create_submit" value="Créer le groupe" class="button button-primary button-large"/>';
        $output .= '</form>';

        echo $output;
	}

	public function single_event_html( $eventID ) {

		$event = get_post($eventID);
		$eventBadge = get_post(get_post_meta($eventID, '_cie_group_badge_id', true));
        $start_date = (isset($_GET['stats_start_date']) && !empty($_GET['stats_start_date']) )? \DateTime::createFromFormat('d/m/Y', $_GET['stats_start_date']) : false;
        $end_date = (isset($_GET['stats_end_date']) && !empty($_GET['stats_end_date']))? \DateTime::createFromFormat('d/m/Y', $_GET['stats_end_date']) : false;


		$output = '<div data-interface="event-stats">';
		$output .= '<h1>' . $event->post_title . '</h1>';
		$output .= '<form action="" method="get">';
		$output .= '<input type="hidden" name="page" value="cadre21">';
		$output .= '<input type="hidden" name="subpage" value="event-stats">';
		$output .= '<input type="hidden" name="event" value="'.$eventID.'">';

		$output .= '<div class="stats-form">';
		$output .= '<div class="form-group"><label>' . __('Start date', 'bf-events') . '</label><input type="text" class="datepicker form-field" name="stats_start_date" autocomplete="off" value=""/></div>';
		$output .= '<div class="form-group"><label>' . __('End date', 'bf-events') . '</label><input type="text" class="datepicker form-field" name="stats_end_date" autocomplete="off" /></div>';
		$output .= '<input type="submit" value="' . __("Export the statistics", 'bf-events') .'" class="button button-primary button-large">';


		$elements = array(
			'start_date' => $start_date,
			'end_date' => $end_date,
			'event' => $event,
			'event-badge' => $eventBadge,
			//Get registered users ( obtained badge + wallet amount )
			'registered-users' => EventManager::getRegisteredUsersByDate( $eventID, $start_date, $end_date),
			//Get total members that purchased
			'purchased-users' => EventManager::getPurchasedUsersByDate( $eventID, $start_date, $end_date),
			//Get all the orders for the event
			'orders' => EventManager::getEventUsersOrders( $eventID, $start_date, $end_date ),
			'obtainedBadges' => EventManager::getUsersSubmissions( $eventID, 'approved', $start_date, $end_date),
			'pendingBadges' => EventManager::getUsersSubmissions( $eventID, 'pending', $start_date, $end_date),
			'deniedBadges' => EventManager::getUsersSubmissions( $eventID, 'denied', $start_date, $end_date)
		);

		$output .= Statistics::innerHTML($elements);
		$output .= Statistics::generateFooter();

		$output .= '</div>';

		echo $output;
    }

	/**
	 *
	 * The page to show an existing group.
	 *
	 */
	public function event_stats_html($id)
    {

		if(get_post_type($id) != 'cie_group'){
			die('This is not a group post!');
		}

	    $group = get_post($id);
		$beginDate = null;
		$endDate = null;

	    echo '<h1>Le groupe: ' . $group->post_title .'</h1>';

        //Check if we display the overall stats or stats ordered by date.
		if(isset($_GET['date_stats_start_date']) && isset( $_GET['date_stats_end_date']) && !empty($_GET['date_stats_end_date']) && !empty($_GET['date_stats_start_date'])){

            $beginDate = DateTime::createFromFormat('d/m/Y', $_GET['date_stats_start_date']);
            $beginDate = $beginDate->setTime(0,0,0);
			$endDate = DateTime::createFromFormat('d/m/Y', $_GET['date_stats_end_date']);
			$endDate = $endDate->setTime(0,0,0);

            if( $beginDate > $endDate){
               echo '<h2>' . __('You need to choose a begin date before then the end date !') . '</h2>';
            }
            else{
	            echo '<h2>' . __('Stats from').' : '. $beginDate->format('d/m/Y') .' '.__('till').' '. $endDate->format('d/m/Y') .'</h2>';
            }
        }


        $groupMembers = self::get_group_members($id);
		$registeredMembers = self::get_registered_members($id, $beginDate, $endDate);
		$purchasedMembers = self::get_purchased_members($id, $beginDate, $endDate);


    ?>
	   		<style type="text/css">




            </style>

            <div class="date-form">
                <form action="" method="get">
                    <input type="hidden" name="page" value="cadre21">
                    <input type="hidden" name="group" value="<?php echo $group->ID; ?>">
                    <label><?php _e('Start date', 'cie'); ?></label>
                    <input type="text" class="datepicker" name="date_stats_start_date" <?php if( $beginDate != null){ echo 'value="'.$beginDate->format('m/d/Y').'"';} ?> autocomplete="off"/>
                    <label><?php _e('End date', 'cie'); ?></label>
                    <input type="text" class="datepicker" name="date_stats_end_date" <?php if( $endDate != null ){ echo 'value="'.$endDate->format('m/d/Y').'"';} ?> autocomplete="off" />
                    <input type="submit" value="submit">
                </form>

                <form action="" method="get">
                    <input type="hidden" name="page" value="cadre21">
                    <input type="hidden" name="group" value="<?php echo $group->ID ?>">
                    <input type="hidden" name="date_stats_start_date" value="" autocomplete="off"/>
                    <input type="hidden" name="date_stats_end_date" value="" autocomplete="off" />
                    <input type="submit" value="<?php _e('reset', 'cie'); ?>">
                </form>
            </div>

	   		<div class="statistics-wrapper">
	   			<div class="item">
	   				<h2><?php _e('number of members', 'cie'); ?></h2>
	   				<span><?php echo count($groupMembers); ?></span>
	   			</div>
	   			<div class="item">
	   				<h2><?php _e('number of registered members', 'cie'); ?></h2>
	   				<span><?php echo count($registeredMembers).'/'.count($groupMembers); ?></span>
	   			</div>
	   			<div class="item">
	   				<h2><?php _e('number of members that purchased', 'cie'); ?></h2>
	   				<span><?php echo count($purchasedMembers).'/'.count($groupMembers); ?></span>
	   			</div>
	   			<div class="item">
	   				<h2><?php _e('Total group wallet purchased', 'cie'); ?></h2>
	   				<span><?php echo '$ '. self::get_group_purchases($id, $beginDate, $endDate, true); ?></span>
	   			</div>
	   			<div class="item">
	   				<h2><?php _e('Total group personal purchased', 'cie'); ?></h2>
	   				<span><?php echo '$ '. self::get_group_purchases($id, $beginDate, $endDate, false); ?></span>
	   			</div>
	   		</div>

	   		<a href="#" id="add-member-to-group" class="button primary-button"><?php _e('Add a member to this group', 'cie'); ?></a>
	   		<div id="add-member-form">
	   			<form action="" method="post">
	   				<input type="hidden" name="cie_add_member_group_id" value="<?php echo $id; ?>">
	   				<input type="email" name="cie_add_member_email" placeholder="<?php _e('Email', 'cie'); ?>">
	   				<input type="text" name="cie_add_member_lastname" placeholder="<?php _e('Lastname', 'cie'); ?>">
	   				<input type="text" name="cie_add_member_firstname" placeholder="<?php _e('Firstname', 'cie'); ?>">
	   				<input type="submit" name="cie_add_member_submit" class="button primary-button" value="<?php _e('Add member', 'cie'); ?>">
	   			</form>
	   		</div>

	   		<div class="registered-member-table">
                <h2><?php _e('Registered members', 'cie'); ?></h2>

                <?php
                    //Show a table with all the groups that are created.
                    include_once plugin_dir_path( __FILE__ ).'/lib/registered_member_table.php';
                    $memberTable = new Registered_Member_List_Table($id, $beginDate, $endDate);
                    $memberTable->prepare_items();
                    $memberTable->display();
                ?>
	   		</div>

            <div class="invited-member-table">
                <h2><?php _e('Unregistered members', 'cie'); ?></h2>

                <?php
                //Show a table with all the groups that are created.
                include_once plugin_dir_path( __FILE__ ).'/lib/invited_member_table.php';
                $memberTable = new Invited_Member_List_Table($id);
                $memberTable->prepare_items();
                $memberTable->display();
                ?>
            </div>

            <div id="dialog" title="User information">
                <div class="loader"></div>
            </div>

	   <?php    
	}

	/**
     *
     * Get amount of members for a group
     *
     * @return array of all the invited persons posts
     */
    static function get_group_members($id){
      //Get all the members in the group.
      $member_args = array(
        'posts_per_page'   => -1,
        'meta_key'         => '_cie_group_id',
        'meta_value'       => $id,
        'post_type'        => 'cie_group_member',
        'post_status'      => 'private',
      );

      $group_member_array = get_posts( $member_args );

      return $group_member_array;
    }

	/**
     *
     * Get amount of members that registered.
     *
     * @return array of the registered users.
     */
    static function get_registered_members($id, $beginDate = null, $endDate = null){
      //Get all the members in the group.
      $group_member_array = self::get_group_members($id);

      //Get all the registered members in the group.
      $member_args = array(
        'posts_per_page'   => -1,
        'post_type'        => 'cie_group_member',
        'post_status'      => 'private',
        'meta_query' => array(
          array(  
            'key'=> '_cie_group_id',
            'value'   => $id
          ),
          array(  
            'key'=> '_cie_member_registered',
            'value'   => false,
            'compare'   => '!=',
          ),

        )
      );

      $group_member_registered_array = get_posts( $member_args );


      if( $beginDate != null && $endDate != null){

          $users = array();

          foreach ( $group_member_registered_array as $user){
              $userID = get_post_meta( $user->ID, '_cie_member_registered', true);

              if($userID !== false){
                  $userRegisterData = get_userdata($userID);
                  $userRegisterDate = new DateTime($userRegisterData->user_registered);
	              $userRegisterDate = $userRegisterDate->setTime(0,0,0);

	              if($userRegisterDate >= $beginDate && $userRegisterDate <= $endDate){
		              array_push( $users, $user);
                  }
              }
          }


      }
      else{
          $users = $group_member_registered_array ;
      }



      return $users;
    }

    /**
     *
     * Get amount of members that registered.
     *
     *
     * @return array of orders placed by the users.
     */
    static function get_purchased_members($id, $beginDate = null, $endDate = null){
      	//Get all the members in the group.
      	$group_member_array = self::get_registered_members($id, null, null );

        $orders = array();

	    foreach ($group_member_array as $item) {
	      	//Get the user related to the post.
	      	$user_id = get_post_meta($item->ID, '_cie_member_registered', true);
	      	$user = get_user_by('id', $user_id);

	      	if($user){
	      		$customer_orders = get_posts( array(
				    'numberposts' => -1,
				    'meta_key'    => '_customer_user',
				    'meta_value'  => $user_id,
				    'post_type'   => wc_get_order_types(),
				    'post_status' => 'wc-completed', //Only get the completed orders
				));		

				if(count($customer_orders) > 0){

				    //There are orders. Check if they are between the start and end date.


                    if($beginDate != null && $endDate != null){
	                    foreach($customer_orders as $order){

		                    $oderDate = new DateTime($order->post_date);
		                    $oderDate = $oderDate->setTime(0,0,0);

		                    if($oderDate >= $beginDate && $oderDate <= $endDate){
			                    array_push($orders, $customer_orders);
		                    }
	                    }
                    }
                    else{
	                    array_push($orders, $customer_orders);
                    }
				}
	      	}
		}



      	return $orders;
    }

	/**
	 *
	 * Get the total of purchases made by the group.
     * @param $id of the group
     * @param $beginDate
     * @param $endDate
     * @param $method bool if true use the wallet, false use other payment methods
     *
     * @return float the money spend by the group.
	 */
    public function get_group_purchases($id, $beginDate = null, $endDate = null, $method = false){


	    $group_member_array = self::get_registered_members($id, null , null );

	    //initialize the amount of money spend at 0
	    $moneySpend = floatval(0);

	    foreach ($group_member_array as $item) {
		    //Get the user related to the post.
		    $user_id = get_post_meta($item->ID, '_cie_member_registered', true);
		    $user = get_user_by('id', $user_id);

		    if($user){
			    $customer_orders = get_posts( array(
				    'numberposts' => -1,
				    'meta_key'    => '_customer_user',
				    'meta_value'  => $user_id,
				    'post_type'   => wc_get_order_types(),
				    'post_status' => 'wc-completed', //Only get the completed orders
			    ));

			    if(count($customer_orders) > 0){

				    foreach ( $customer_orders as $order ) {

					    $wc_order = wc_get_order($order->ID);


					    if($beginDate != null && $endDate != null){

					        $orderDate = new DateTime($wc_order->order_date);
					        $orderDate = $orderDate->setTime(0,0,0);

                            if($orderDate >= $beginDate && $orderDate <= $endDate  ){
	                            if($method === true){
		                            if( $wc_order->payment_method == 'wpuw' ){
			                            $moneySpend = $moneySpend + $wc_order->get_total();
		                            }
	                            }
	                            else{
		                            if( $wc_order->payment_method != 'wpuw' && $wc_order->payment_method != ''){
			                            $moneySpend = $moneySpend + floatval($wc_order->get_total());
		                            }
	                            }
                            }
                        }
                        else{
	                        if($method === true){
		                        if( $wc_order->payment_method == 'wpuw' ){
			                        $moneySpend = $moneySpend + $wc_order->get_total();
		                        }
	                        }
	                        else{
		                        if( $wc_order->payment_method != 'wpuw' && $wc_order->payment_method != ''){
			                        $moneySpend = $moneySpend + floatval($wc_order->get_total());
		                        }
	                        }
                        }
				    }
			    }
		    }
	    }

		return $moneySpend;
    }


    /**
     *
     * Enqueue the Jquery Core scripts
     *
     */
    public function add_jquery_ui() {
	    wp_enqueue_script( 'jquery-ui-datepicker' );
	    wp_register_style('jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css');
	    wp_enqueue_style( 'jquery-ui' );
    }

    /**
     *
     * The function to find the user information via AJAX
     *
     * @return string HTML to display in the dialog
     */
    public function get_user_information(){

        $userID = $_POST['userID'];

        $user = get_user_by('id', $userID);
        $lastLogin = new DateTime(get_user_meta( $userID, 'last_activity', true));


	    $output = '<div class="user-info"><h1 class="dialog-title">'. $user->display_name.'</h1> <span>'. __('Last login', 'cie').' ' . $lastLogin->format('d/m/Y').'</span><div><h2>'. __('Orders', 'cie') .'</h2>';

            //get the orders of the user.
            $customer_orders = get_posts( array(
                'numberposts' => -1,
                'meta_key'    => '_customer_user',
                'meta_value'  => $userID,
                'post_type'   => wc_get_order_types(),
                'post_status' => 'wc-completed', //Only get the completed orders
            ));

            if(count($customer_orders) > 0){

	            $output .= '<ul class="orders">';

                //There are orders. Check if they are between the start and end date.
                foreach($customer_orders as $order){
                    $wc_order = wc_get_order($order);
                    $orderDate = new DateTime($wc_order->order_date);
                    $items = $wc_order->get_items();


                    $output .= '<li class="order"><h4>'. __('Order placed on', 'cie').' '. $orderDate->format('d/m/Y') .'</h4><ul class="items">';

	                foreach ($items as $item){
		                $output .= '<li>'. $item['name'].': '.$item['qty'].'X '. $item['line_total'].'$ </li>';
	                }
                    
                    $output .= '</ul></li><p><b>'. __('Total', 'cie').': '. $wc_order->order_total.'$</b></p>';
                }

	            $output .= '</ul>';
            }


        //Get the user achievements.
	    $currentUserBadgeList = $GLOBALS['badgefactor']->get_user_achievements($userID);

            if(!empty($currentUserBadgeList)){
                $output .= '<div><h2>'. __('Obtained badges', 'cie') .'</h2><ul class="badges">';

                foreach ($currentUserBadgeList as $achievement){
                    $achievementPost = get_post($achievement->achievement_id);
	                $badgePost = get_post($achievement->badge_id);

	                $obtainedDate = new DateTime($achievementPost->post_date);

                    $output .= '<li><h4>'. $badgePost->post_title.'</h4>'.badgeos_get_achievement_post_thumbnail( $achievement->achievement_id ).'<p>'. $obtainedDate->format('d/m/Y') .'</p></li>';
                }

		        $output .= '</ul></div>';

            }


        $output .= '</div></div>';

        echo $output;
        exit;

    }
}

new Cadre21InvitationEvent();
