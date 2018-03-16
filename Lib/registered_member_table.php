<?php
/**
 * Created by PhpStorm.
 * User: joris
 * Correcteur: Alexandre Dumouchel
 * Date: 02/02/17
 * Time: 3:00 PM
 */


class Registered_Member_List_Table extends WP_List_Table {

	static private $data = array();

	/**
	 * Constructor, we override the parent to pass our own arguments
	 * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
	 */
	function __construct($id, $beginDate, $endDate) {
		parent::__construct( array(
				'singular'=> 'wp_group', //Singular label
				'plural' => 'wp_groups', //plural label, also this well be one of the table css class
				'ajax'   => false //We won't support Ajax for this table
			)
		);

		$group_member_array =  get_posts(  array(
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
		));

		if($beginDate != null && $endDate != null){

			foreach ( $group_member_array as $m_key => $item){

				$unsetMember = false;
				$userID = get_post_meta($item->ID, '_cie_member_registered', true);

				//Exclude the user of the list if he is not registered or he didn't buy something.
				$userRegisterData = get_userdata( $userID );
				$userRegisterDate = new DateTime($userRegisterData->user_registered);
				$userRegisterDate = $userRegisterDate->setTime(0,0,0);

				if( $userRegisterDate < $beginDate || $userRegisterDate > $endDate ){
					$unsetMember = true;
				}

				//Include the member if he did a purchase during the period.
				$customer_orders = get_posts( array(
					'numberposts' => -1,
					'meta_key'    => '_customer_user',
					'meta_value'  => $userID,
					'post_type'   => wc_get_order_types(),
					'post_status' => 'wc-completed', //Only get the completed orders
				));

				if(count($customer_orders) > 0){

					//There are orders. Check if they are between the start and end date.
					foreach($customer_orders as $order){

						$oderDate = new DateTime($order->post_date);
						$oderDate = $oderDate->setTime(0,0,0);

						if($oderDate >= $beginDate && $oderDate <= $endDate){
							$unsetMember = false;
						}
					}
				}

				if($unsetMember === true){
					unset($group_member_array[$m_key]);
				}
			}
		}

		/*usort($group_member_array, function ($a, $b)
		{
			$user_a = get_user_by('id', get_post_meta($a->ID, '_cie_member_registered', true));
			$user_b = get_user_by('id', get_post_meta($a->ID, '_cie_member_registered', true));

			if($user_a === false){
				return 1;
			}

			if ($user_a === $user_b) {
				return 0;
			}

			return ($user_a < $user_b) ? 1 : -1;
		});*/

		foreach ($group_member_array as $item) {
			self::$data[] = array(
				'col_member_id'=> $item->ID,
				'col_member_email'=> get_post_meta($item->ID, '_cie_member_email', true),
				'col_member_lastname'=> get_post_meta($item->ID, '_cie_member_lastname', true),
				'col_member_firstname'=> get_post_meta($item->ID, '_cie_member_firstname', true),
				'col_member_wallet_amount'=> get_post_meta($item->ID, '_cie_member_registered', true),
				'col_member_registered' => get_post_meta($item->ID, '_cie_member_registered', true),
			);
		}
	}



	/**
	 * Define the columns that are going to be used in the table
	 * @return array $columns, the array of columns to use with the table
	 */
	function get_columns() {
		return $columns= array(
			'col_member_id'=>__('ID', 'cie'),
			'col_member_email'=>__('Email', 'cie'),
			'col_member_lastname'=> __('Lastname', 'cie'),
			'col_member_firstname'=> __('Firstname', 'cie'),
			'col_member_wallet_amount'=>__('Current wallet amount', 'cie'),
			'col_member_registered' => __('Registered member', 'cie'),
		);
	}

	/**
	 * Decide which columns to activate the sorting functionality on
	 * @return array $sortable, the array of columns that can be sorted by the user
	 */
	public function get_sortable_columns() {
		return $sortable = array(

		);
	}


	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = self::$data;
	}

	public function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'col_member_email':
			case 'col_member_lastname':
			case 'col_member_firstname':
				return $item[ $column_name ];
			case 'col_member_registered':

				if( $item[ $column_name ] !== false && $item[ $column_name ] != "" && !empty($item[ $column_name ]) ){

					$user = get_user_by( 'ID', $item[ $column_name ] );
					return '<a href='. admin_url().'user-edit.php?user_id='. $item[ $column_name ] .' class="trigger-user-modal" data-userid='.$item[ $column_name ].'>'. $user->display_name .'</a>';
				}
				else{

					return __('Not registered', 'cie');
				}


			case 'col_member_wallet_amount':
				if( $item[ $column_name ] !== false && $item[ $column_name ] != "" && !empty($item[ $column_name ])){
					$user_id = $item[ $column_name ];

					$wallet_amount = get_user_meta($user_id, '_uw_balance', true);

					return '$ ' . $wallet_amount;

				}
				else{
					return __('Not registered', 'cie');

				}

			case 'col_member_id':
				return '<a href="'. admin_url().'post.php?post='.$item[ $column_name ].'&action=edit">' .$item[ $column_name ].'</a>';


			default:
				return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
		}
	}

}