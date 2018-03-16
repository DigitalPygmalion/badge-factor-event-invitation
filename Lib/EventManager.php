<?php
/**
 * Created by PhpStorm.
 * User: joris
 * Date: 2017-11-22
 * Time: 9:55 PM
 */

namespace BadgeFactorEvent\Lib;

class EventManager {

	/**
	 * @param $event_id
	 * @param bool $registered
	 *
	 * @return array
	 */
	public static function getEventMembers( $event_id, $registered = false ){

		$meta_query = array(
			array(
				'key'=> '_cie_group_id',
				'value'   => $event_id
			),
		);

		if($registered){
			$meta_query['relation'] = 'AND';
			$meta_query[] = array(
				'key'=> '_cie_member_registered',
				'value'   => false,
				'compare'   => '!=',
			);
		}

		//Get all the members in the group.
		$member_args = array(
			'posts_per_page'   => -1,
			'post_type'        => 'cie_group_member',
			'post_status'      => 'private',
			'meta_query'       => $meta_query
		);

		$event_members = get_posts( $member_args );


		$users = array();

		if($event_members){
			foreach ($event_members as $event_member){
				$users[] = self::getMemberById( $event_member->ID );
			}
		}

		return $users;
	}

	/**
	 * @param $event_id
	 * @param bool $registered
	 *
	 * @return int
	 */
	public static function getEventMembersAmount( $event_id, $registered = false){
		return count( self::getEventMembers( $event_id, $registered) );
	}

	/**
	 * @param $event_id
	 * @param bool $start_date
	 * @param bool $end_date
	 *
	 * @return array|bool
	 */
	public static function getRegisteredUsersByDate( $event_id, $start_date = false, $end_date = false )
	{
		$users = self::getEventMembers( $event_id, true);

		if( empty( $users) ){
			return false;
		}

		if( $users && ( $start_date || $end_date ) ){

			foreach ($users as $key => $user ){

				//$userRegisterData = get_userdata( $user->ID );
				$userRegisterDate = new \DateTime($user->data->user_registered);
				$userRegisterDate = $userRegisterDate->setTime(0,0,0);


				if($start_date && $start_date >= $userRegisterDate ){
					unset($users[$key]);
				}

				if($end_date && $end_date <= $userRegisterDate ){
					unset($users[$key]);
				}
			}
		}

		return $users;
	}

	public static function getPurchasedUsersByDate( $event_id, $start_date, $end_date)
	{
		$users = self::getEventMembers( $event_id, true);

		if( empty( $users) ){
			return false;
		}

		$users = self::filterPurchasedUsers( $users, $start_date, $end_date);

		return $users;
	}

	private static function filterPurchasedUsers( $users, $start_date = false, $end_date = false) {

		foreach ($users as $key =>$user ){
			$customer_orders = get_posts( array(
				'numberposts' => -1,
				'meta_key'    => '_customer_user',
				'meta_value'  => $user->ID,
				'post_type'   => wc_get_order_types(),
				'post_status' => 'wc-completed', //Only get the completed orders
			));

			if(count($customer_orders) > 0){

				foreach ( $customer_orders as $order_key => $customer_order ) {

					//Get the WC_Order Object
					$order = wc_get_order( $customer_order->ID );

					if( $start_date && $start_date > $order->get_date_completed() ){
						unset($customer_orders[$order_key]);
					}

					if( $end_date && $end_date < $order->get_date_completed() ) {
						unset($customer_orders[$order_key]);
					}
				}

				//The User has no orders during this period
				if( empty( $customer_orders )){
					unset($users[$key]);
				}
			}
			else{
				unset($users[$key]);
			}
		}

		return $users;
	}

	public static function getEventUsersOrders( $event_id, $start_date = false, $end_date = false) {
		$users = self::getEventMembers( $event_id, true);

		if( empty($users) ){
			return false;
		}

		$orders = array();

		foreach ($users as $key =>$user ){
			$customer_orders = get_posts( array(
				'numberposts' => -1,
				'meta_key'    => '_customer_user',
				'meta_value'  => $user->ID,
				'post_type'   => wc_get_order_types(),
				'post_status' => 'wc-completed', //Only get the completed orders
			));

			if(count($customer_orders) > 0){

				foreach ( $customer_orders as $order_key => $customer_order ) {

					$include = true;

					//Get the WC_Order Object
					$order = wc_get_order( $customer_order->ID );

					if( $start_date && $start_date > $order->get_date_completed() ){
						$include = false;
					}

					if(  $end_date && $end_date < $order->get_date_completed() ) {
						$include = false;
					}

					if($include){
						$orders[] = $order;
					}
				}
			}
		}

		return $orders;

	}

	/**
	 * @param $event_id
	 * @param string $status (approved, pending, denied)
	 * @param bool $start_date
	 * @param bool $end_date
	 *
	 * @return array
	 */
	public static function getUsersSubmissions( $event_id, $status = 'approved', $start_date = false, $end_date = false ){
		$users = self::getEventMembers( $event_id, true);
		$eventBadge = get_post(get_post_meta($event_id, '_cie_group_badge_id', true));

		if( empty($users) ){
			return false;
		}

		$badges = array();

		foreach ($users as $key => $user ) {

			$meta_query = array(
				//Status
				array(
					'key'     => '_badgeos_submission_status',
					'value'   => $status,
					'compare' => '=',
				)
			);

			$args =  array(
				'numberposts' => - 1,
				'post_type'   => 'submission',
				'post_status' => 'publish',
				'author' => $user->ID,
				'meta_query' => $meta_query,
			);

			if( $start_date ){
				$args['after'] = array(
					'year' => $start_date->format('Y'),
					'month' => $start_date->format('n'),
					'day' => $start_date->format('j')
				);
			}

			if(  $end_date ) {
				$args['before'] = array(
					'year' => $end_date->format('Y'),
					'month' => $end_date->format('n'),
					'day' => $end_date->format('j')
				);
			}

			$results = new \WP_Query( $args );

			if(!empty($results->posts)){

				$input = array(
					'user' => $user,
					'badges' => array()
				);

				foreach($results->posts as $result){

					$insert = true;
					$badgeID = get_post_meta($result->ID, '_badgeos_submission_achievement_id', true);

					if($start_date && $result->post_date < $start_date ){
						$insert = false;
					}

					if($end_date && $result->post_date > $end_date ){
						$insert = false;
					}

					if($eventBadge->ID == $badgeID){
						$insert = false;
					}

					if($insert){
						$input['badges'][$badgeID] = $result;
					}
				}

				if(!empty($input['badges'])){
					array_push($badges, $input);
				}
			}
		}

		return $badges;
	}

	private static function getMemberById( $id )
	{
		$userID = get_post_meta($id, '_cie_member_registered', true);
		$user = get_user_by('ID', $userID);

		return $user;

	}


}