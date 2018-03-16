<?php
/**
 * Created by PhpStorm.
 * User: joris
 * Date: 2017-11-21
 * Time: 11:19 PM
 */

namespace BadgeFactorEvent\Lib;


class EventTable extends \WP_List_Table {

	static private $data = array();

	/**
	 * Constructor, we override the parent to pass our own arguments
	 * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
	 */
	function __construct($organisation_id) {
		parent::__construct( array(
			'singular'=> 'wp_group', //Singular label
			'plural' => 'wp_groups', //plural label, also this well be one of the table css class
			'ajax'   => false //We won't support Ajax for this table
		) );

		$args = array(
			'posts_per_page'   => -1,
			'offset'           => 0,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'post_type'        => 'cie_group',
			'post_status'      => 'private',
			'meta_query' => array(
				array(
					'key'     => 'organisation',
					'value'   => $organisation_id,
					'compare' => '=',
				),
			),
		);

		$groups_array = get_posts( $args );

		foreach ($groups_array as $item) {

			//@todo: Need to get the posttype from badgefactor. Prevent hardcoded.
			$badge = get_post( get_post_meta($item->ID, '_cie_group_badge_id', true) );
			$badge = (get_post_type( $badge ) == 'badges')? $badge : null;

			self::$data[] = array(
				'id'=> $item->ID,
				'name'=> $item->post_title,
				'wallet_amount'=> '$ '.get_post_meta($item->ID, '_cie_group_wallet_amount', true),
				'badge'=> $badge,
				'users_registered'=> self::get_registered_members($item->ID),
				'users_purchased'=> self::get_purchased_members($item->ID),
			);
		}
	}

	/**
	 *
	 * Get amount of members for a group
	 */
	static function get_event_invitations($id){
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
	 */
	static function get_registered_members($id){
		//Get all the members in the group.
		$member_args = array(
			'posts_per_page'   => -1,
			'meta_key'         => '_cie_group_id',
			'meta_value'       => $id,
			'post_type'        => 'cie_group_member',
			'post_status'      => 'private',
		);

		$group_member_array = get_posts( $member_args );

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
					'compare' => '!='
				),
			)
		);

		$group_member_registered_array = get_posts( $member_args );



		return count($group_member_registered_array) . '/' . count($group_member_array);
	}

	/**
	 *
	 * Get amount of members that registered.
	 */
	static function get_purchased_members($id){
		//Get all the members in the group.
		$group_member_array = self::get_event_invitations($id);

		$orders = 0;

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
					'post_status' => array_keys( wc_get_order_statuses() ),
				));

				if(count($customer_orders) > 0){
					$orders++;
				}
			}
		}


		return $orders . '/' . count($group_member_array);
	}



	/**
	 * Define the columns that are going to be used in the table
	 * @return array $columns, the array of columns to use with the table
	 */
	function get_columns() {
		return $columns= array(
			'cb'      => '<input type="checkbox" />',
			'name'=>__('Name'),
			'wallet_amount'=>__('Montant du porte-feuille'),
			'badge' => __('Badge associé'),
			'users_registered'=>__('Membres inscrit'),
			'users_purchased'=>__('Membres qui ont acheté un produit')
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

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-stats[]" value="%s" />', $item['id']
		);
	}

	public function column_name( $item ) {

		$delete_nonce = wp_create_nonce( 'event_delete');

		$actions = array(
			'stats' => sprintf('<a href="%s/admin.php?page=cadre21&subpage=%s&event='. $item[ 'id' ] .'">'. __('Statistics').'</a>', admin_url(),'event-stats',$item['id']),
			'edit'      => sprintf('<a href="%s/post.php?post=%s&action=edit">'. __('Edit the event').'</a>', admin_url(),$item['id'], 'edit'),
			'delete' => sprintf( '<a href="%s/post.php?post=%s&action=trash&_wpnonce=%s">' . __("Delete") . '</a>', admin_url(), absint( $item['id'] ), $delete_nonce ),
		);

		$title = '<strong>';
		$title .= sprintf('<a href="%s/admin.php?page=cadre21&subpage=%s&event=%s">%s</a>', admin_url(),'event-stats',$item['id'], $item['name']);
		$title .= '</a></strong>';

		return sprintf('%1$s %2$s', $title, $this->row_actions($actions) );
	}

	public function column_badge( $item ) {

		return sprintf('<a href="'.admin_url().'/post.php?post=%s&action=edit">%s</a>', $item['badge']->ID, $item['badge']->post_title);
	}

	public function column_wallet_amount( $item ) {

		return sprintf('<span>%s</span>', $item['wallet_amount']);
	}

	public function column_users_registered( $item ) {

		return sprintf('<span>%s</span>', $item['users_registered']);
	}

	public function column_users_purchased( $item ) {

		return sprintf('<span>%s</span>', $item['users_purchased']);
	}





	public function column_default( $item, $column_name ) {
		switch( $column_name ) {


			case 'col_group_name':
				return '<a href="'. admin_url().'/admin.php?page=cadre21&group='. $item[ 'col_group_id' ] .'">'. $item[ $column_name ] .'</a>';
			case 'col_group_id':
				return '<a href="'. admin_url().'post.php?post='.$item[ $column_name ].'&action=edit">' .$item[ $column_name ].'</a>';
		}
	}



}