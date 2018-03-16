<?php
/**
 * Created by PhpStorm.
 * User: joris
 * Date: 2017-11-21
 * Time: 11:31 AM
 */

namespace BadgeFactorEvent\Lib;

class PostTypes {

	const ORGANISATION_POSTTYPE = 'bf_event_org';
	const GROUP_POSTTYPE = 'cie_group';
	const GROUP_MEMBER_POSTTYPE = 'cie_group_member';

	/**
	 * @var
	 */
	private $organisationPostType = null;

	/**
	 * @var
	 */
	private $groupPostType = null;

	/**
	 * @var
	 */
	private $groupMemberPostType = null;

	/**
	 * PostTypes constructor.
	 */
	public function __construct() {
		$this->updateOldPosttypes('bf_event_organisation',self::ORGANISATION_POSTTYPE);
		$this->createOrganisationPostType( self::ORGANISATION_POSTTYPE );
		$this->createGroupPostType( self::GROUP_POSTTYPE );
		$this->createGroupMemberPostType( self::GROUP_POSTTYPE );
	}

	public function updateOldPosttypes( $oldSlug, $newSlug ) {
		$args = array(
			'numberposts' => -1,
			'post_type'   => $oldSlug
		);

		$postsToUpdate = get_posts( $args );

		if($postsToUpdate){
			foreach ($postsToUpdate as $postToUpdate){
				$args = array(
					'ID'           => $postToUpdate->ID,
					'post_type'   => $newSlug,
				);

				wp_update_post( $args );
			}
		}
	}

	/**
	 * @param $slug
	 */
	public function createOrganisationPostType( $slug ){
		if(!post_type_exists( $slug )){
			$this->organisationPostType = register_post_type($slug, array(
				'labels'             =>
					array(
						'name'               => 'Organisations',
						'singular_name'      => 'Organisation',
						'menu_name'          => 'Organisations',
						'name_admin_bar'     => 'Organisation',
					),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'query_var'          => true,
				'capability_type'    => 'post',
				'has_archive'        => true,
				'hierarchical'       => true,
				'supports'           => array('title','custom-fields'),
				'menu_icon'			 => 'dashicons-location-alt',
			));
		}
	}

	/**
	 * @param $slug
	 */
	public function createGroupPostType( $slug ){
		if(!post_type_exists( $slug )){
			$this->groupPostType = register_post_type($slug, array(
				'labels'             =>
					array(
						'name'               => 'cie_group',
						'singular_name'      => 'cie_group',
						'menu_name'          => 'cie_group',
						'name_admin_bar'     => 'cie_group',
					),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'query_var'          => true,
				'capability_type'    => 'post',
				'has_archive'        => true,
				'hierarchical'       => true,
				'supports'           => array('title','custom-fields'),
				'menu_icon'			 => 'dashicons-location-alt',
			));
		}
	}

	/**
	 * @param $slug
	 */
	public function createGroupMemberPostType( $slug ){
		if(!post_type_exists( $slug )){
			$this->groupMemberPostType = register_post_type($slug, array(
				'labels'             =>
					array(
						'name'               => 'cie_group_member',
						'singular_name'      => 'cie_group_member',
						'menu_name'          => 'cie_group_member',
						'name_admin_bar'     => 'cie_group_member',
					),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'query_var'          => true,
				'capability_type'    => 'post',
				'has_archive'        => true,
				'hierarchical'       => true,
				'supports'           => array('title', 'custom-fields'),
				'menu_icon'			 => 'dashicons-location-alt',
			));
		}
	}

	/**
	 * @return string
	 */
	public function getOrganisationPostType()
	{
		if( $this->organisationPostType != null){
			return $this->organisationPostType;
		}

		return self::ORGANISATION_POSTTYPE;
	}

	/**
	 * @return string
	 */
	public function getGroupPostType()
	{
		if( $this->groupPostType != null){
			return $this->groupPostType;
		}

		return self::GROUP_POSTTYPE;
	}

	/**
	 * @return string
	 */
	public function getGroupMemberPostType()
	{
		if( $this->groupMemberPostType != null){
			return $this->groupMemberPostType;
		}

		return self::GROUP_MEMBER_POSTTYPE;
	}
}