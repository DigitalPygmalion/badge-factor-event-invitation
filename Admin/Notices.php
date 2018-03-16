<?php
/**
 * Created by PhpStorm.
 * User: joris
 * Date: 2017-11-21
 * Time: 10:33 PM
 */

namespace BadgeFactorEvent\Admin;

class Notices {

	/**
	 * Stores notices.
	 * @var array
	 */
	private static $notices = array();

	public static function init() {
		add_action( 'admin_print_styles', array( __CLASS__, 'display_notices' ) );
	}

	/**
	 * @param $type
	 * @param $message
	 */
	public static function add_notice( $type, $message ) {

		switch ( $type ){
			case 'alert':
				$class = 'notice notice-alert';
				break;
			case 'error':
				$class = 'notice notice-error';
				break;
			case 'success':
				$class = 'notice notice-success';
				break;
			default:
				$class = 'notice notice-error';
				break;
		}

		$notice = array(
			'class' => $class,
			'message' => $message
		);

		self::set_notices($notice);
	}

	/**
	 * @return array
	 */
	public static function set_notices($notice) {

		return self::$notices[] = $notice;
	}

	/**
	 * @return array
	 */
	public static function get_notices() {

		return self::$notices;

	}

	public static function display_notices() {

		$notices = self::$notices;

		if( !empty($notices) ){
			foreach ( $notices as $notice ) {
				add_action( 'admin_notices',  self::print_notice($notice) );
			}
		}
	}

	public static function print_notice( $notice ) {
		return '<div class="'.$notice['class'].'"><p>'.$notice['message'].'</p></div>';
	}
}