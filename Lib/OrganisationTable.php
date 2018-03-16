<?php
/**
 * Created by PhpStorm.
 * User: joris
 * Date: 2017-11-21
 * Time: 11:29 PM
 */

namespace BadgeFactorEvent\Lib;


class OrganisationTable extends \WP_List_Table {

	static private $data = array();

	/**
	 * Constructor, we override the parent to pass our own arguments
	 * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
	 */
	function __construct() {
		parent::__construct( array(
			'singular'=> 'organisation', //Singular label
			'plural' => 'organisations', //plural label, also this well be one of the table css class
			'ajax'   => false //We won't support Ajax for this table
		) );

		$args = array(
			'posts_per_page'   => -1,
			'offset'           => 0,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'post_type'        => 'organisation',
		);

		$groups_array = get_posts( $args );

		foreach ($groups_array as $item) {
			self::$data[] = array(
				'id' => $item->ID,
				'organisation'=> $item->post_title,
				'events'=> 'test',
			);
		}
	}





	/**
	 * Define the columns that are going to be used in the table
	 * @return array $columns, the array of columns to use with the table
	 */
	function get_columns() {
		return $columns= array(
			'organisation'=>__('Name'),
			'events'=>__('Events'),
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

	public function column_organisation( $item ){
		$actions = array(
			'edit'      => sprintf('<a href="'.admin_url().'/post.php?post=%s&action=edit">'. __('Edit the organisation').'</a>',$item['id'], 'edit'),
			'see_events' => sprintf('<a href="'. admin_url().'/admin.php?page=cadre21&subpage=events&org='. $item[ 'id' ] .'">'. __('See all events').'</a>',$_REQUEST['page'],'events',$item['id']),
			'add_event' => sprintf('<a href="'. admin_url().'/admin.php?page=cadre21&subpage=new-event&org='. $item[ 'id' ] .'">'. __('Add new event').'</a>',$_REQUEST['page'],'events',$item['id']),
		);

		$title = '<strong><a href="'. admin_url().'/admin.php?page=cadre21&subpage=events&org='. $item[ 'id' ] .'" class="row-title">';
		$title .= $item['organisation'];
        $title .= '</a></strong>';

		return sprintf('%1$s %2$s', $title, $this->row_actions($actions) );
	}

	public function column_events( $item ){

		$events = $this->getEvents( $item['id'] );
		$output = '';

		if( $events ){

			$output .= '<ul>';
			foreach ( $events as $event){
				$output .= sprintf('<li>%1$s</li>', $event->post_title );
			}
			$output .= '</ul>';
		}

		return sprintf('%1$s', $output );
	}

	private function getEvents( $organisation_id ){
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

		$events = get_posts( $args );

		return $events;
	}



}