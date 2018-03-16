<?php
/**
 * Created by PhpStorm.
 * User: joris
 * Date: 2017-11-21
 * Time: 11:23 AM
 */

namespace BadgeFactorEvent\Lib;


class OrganisationManager {

	const POSTTYPE = 'bf_event_org';

	public function __construct() {

	}

	public function create( $title ) {
		$args = array(
			'post_title'    => wp_strip_all_tags( $title ),
			'post_status'   => 'private',
			'post_type'     => 'cie_group'
		);

		$post_id = wp_insert_post( $args );

		return $post_id;
	}
}