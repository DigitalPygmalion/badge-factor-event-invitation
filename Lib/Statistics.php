<?php
/**
 * Created by PhpStorm.
 * User: joris
 * Date: 2017-11-22
 * Time: 9:51 PM
 */

namespace BadgeFactorEvent\Lib;


use BadgeFactorEvent\Cadre21InvitationEvent;
use Mpdf\Mpdf;

class Statistics {

	public static function generateReport( $events, $start_date = false, $end_date = false  ) {

		$filename = __('report-', 'bf-events');

		foreach ($events as $eventID){

			$event = get_post($eventID);
			$filename .= $event->post_title . '-';
		}

		if($start_date){
			$filename .= $start_date->format('d-m-Y');
		}

		if($end_date){
			$filename .= $end_date->format('d-m-Y');
		}

		$html = self::generateHTML( $events, $filename, $start_date, $end_date );

		self::generatePDF( $html, $filename );
	}

	/**
	 * @param $elements
	 *
	 * @return string
	 */
	public static function innerHTML( $elements ) {

		$html = '';
		$html .= '<br /><br />';
		$html .= '<h1>' .  __("Report ", 'bf-events') . ' - ' . $elements['event']->post_title.'</h1>';


		if($elements['start_date'] && $elements['end_date']){
			$html .= '<u>' . __('Period: ', 'bf-events') . '</u> : ' . $elements['start_date']->format('j/m/Y'). ' ' . __('Until', 'bf-events') . ' ' . $elements['end_date']->format('j/m/Y');
		}

		$html .= '<p><u>' . __('Statistics', 'bf-events') . ' :</u></p>';

		$html .= '<ul>';

		if($elements['registered-users']){
			$html .= '<li>' . count($elements['registered-users']) . __(' users received the badge for the event : ', 'bf-events') . $elements['event-badge']->post_title . '</li>';
		}

		if($elements['registered-users']){
			$event_credit = get_post_meta($elements['event']->ID, '_cie_group_wallet_amount', true);
			$html .= '<li>' . count($elements['registered-users']) . __(' users received the credit of ', 'bf-events') . $event_credit .  '$ (' . __("For a total value of :", 'bf-events') . ( intval($event_credit) * intval(count($elements['registered-users'])) ) .'$)</li>';
		}

		if( !empty($elements['orders']) ){

			$moneySpendWithWallet = 0;

			foreach( $elements['orders'] as $order ){

				if($order->get_payment_method() == 'wpuw' ){
					$moneySpendWithWallet = floatval($moneySpendWithWallet) + floatval($order->get_total());
				}
			}


			$html .= '<li>' . count($elements['orders'])  . __(' orders have been placed using the credit for a total of ', 'bf-events') . '<b>'. $moneySpendWithWallet . '$</b></li>';
		}

		if( !empty($elements['obtainedBadges'])){
			$totalUsersObtainedBadges = count($elements['obtainedBadges']);
			$totalBadgesObtained = 0;

			foreach($elements['obtainedBadges'] as $item){
				$totalBadgesObtained = intval($totalBadgesObtained) + count($item['badges']);
			}

			$html .= '<li>' . $totalBadgesObtained  . __(' badges have been obtained by ', 'bf-events') . $totalUsersObtainedBadges . ' ' . __('users', 'bf-events') . '</li>';
		}


		$html .= '</ul>';


		if( !empty($elements['obtainedBadges'])){
			$html .= '<p>' . __("Badges obtained except the event badge", 'bf-events') . '</p>';
			$html .= '<ul>';

			foreach ($elements['obtainedBadges'] as $item){

				if(!empty($item['badges'])){

				$html .= '<li><b>'. $item['user']->display_name .'</b> (# '.$item['user']->ID.') '. $item['user']->user_email .' :</li>';
					$html .= '<li><ul>';
					foreach ($item['badges'] as $key => $badgeInfo){
						$badge = get_post(get_post_meta($badgeInfo->ID, '_badgeos_submission_achievement_id', true));
						$html .= '<li>'. $badge->post_title . '(' . get_the_date('d/m/Y', $badge->ID) . ')</li>';
					}
					$html .= '</ul></li>';
				}
			}

			$html .= '</ul>';
		}
		else{
			$html .= '<p>' . __("No badges other then the event badge were obtained.", 'bf-events') . '</p>';
		}

		if( !empty($elements['pendingBadges'])){
			$html .= '<p>' . __("Badges pending", 'bf-events') . '</p>';
			$html .= '<ul>';

			foreach ($elements['pendingBadges'] as $item){
				if(!empty($item['badges'])){
					$html .= '<li><b>'. $item['user']->display_name .'</b> (# '.$item['user']->ID.') '. $item['user']->user_email .' :</li>';

					$html .= '<li><ul>';
					foreach ($item['badges'] as $key => $badgeInfo){
						$badge = get_post(get_post_meta($badgeInfo->ID, '_badgeos_submission_achievement_id', true));
						$html .= '<li>'. $badge->post_title . '(' . get_the_date('d/m/Y', $badge->ID) . ')</li>';
					}
					$html .= '</ul></li>';
				}
			}

			$html .= '</ul>';
		}
		else{
			$html .= '<p>' . __("No badges other then the event badge are pending.", 'bf-events') . '</p>';
		}

		if( !empty($elements['deniedBadges'])){
			$html .= '<p>' . __("Badges denied", 'bf-events') . '</p>';
			$html .= '<ul>';

			foreach ($elements['deniedBadges'] as $item){
				if(!empty($item['badges'])){
					$html .= '<li><b>'. $item['user']->display_name .'</b> (# '.$item['user']->ID.') '. $item['user']->user_email .' :</li>';

					$html .= '<li><ul>';
					foreach ($item['badges'] as $key => $badgeInfo){
						$badge = get_post(get_post_meta($badgeInfo->ID, '_badgeos_submission_achievement_id', true));
						$html .= '<li>'. $badge->post_title . '(' . get_the_date('d/m/Y', $badge->ID) . ')</li>';
					}
					$html .= '</ul></li>';
				}
			}

			$html .= '</ul>';
		}
		else{
			$html .= '<p>' . __("No badges are denied.", 'bf-events') . '</p>';
		}

		if( !empty($elements['orders']) ){

			$html .= '<table><tr style="border-bottom: 2px solid black;">';
			$html .= '<td>'. __('Order #', 'bf-events').'</td>';
			$html .= '<td>'. __('Name', 'bf-events').'</td>';
			$html .= '<td>'. __('Email', 'bf-events') .'</td>';
			$html .= '<td>'. __('Date', 'bf-events') .'</td>';
			$html .= '<td>'. __('Amount', 'bf-events') .'</td>';
			$html .= '<td>'. __('Payment method', 'bf-events') .'</td>';
			$html .= '<td>'. __('Products', 'bf-events') .'</td>';
			$html .= '</tr>';


			foreach( $elements['orders'] as $order ){

				$method = ( $order->get_payment_method() == 'wpuw')? 'Wallet' : $order->get_payment_method();
				$products = $order->get_items();
				$user = get_user_by('id', $order->get_user_id());

				$html .= '<tr>';
				$html .= '<td>#'.$order->get_id().'</td>';
				$html .= '<td>'. $user->display_name.'</td>';
				$html .= '<td>'. $user->user_email .'</td>';
				$html .= '<td>'. $order->get_date_completed()->format('j/m/Y') .'</td>';
				$html .= '<td>'. $order->get_formatted_order_total() .'</td>';
				$html .= '<td>'. $method .'</td>';
				$html .= '<td>';

				if(!empty($products)){
					$html .= '<ul>';

					foreach ($products as $key => $product_item){
						$html .= '<li>#'.$product_item->get_product_id() .'</li>';
						//$product = get_post($product_item->get_product_id());
						//$html .= '<li>' . $product->post_title . '</li>';
					}

					$html .= '</ul>';
				}

				$html .= '</td>';
				$html .= '</tr>';
			}

			$html .= '</table>';
		}
		else{
			$html .= '<p>' . __("No orders have been placed.", 'bf-events') . '</p>';
		}

		return $html;
	}

	private static function generateHTML( $events, $filename, $start_date = false, $end_date = false  ) {

		$html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
			"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
		$html .= '<html><head>';
			$html .= '<link rel="stylesheet" href="'. Cadre21InvitationEvent::$pluginDir .'dist/css/print.css" type="text/css" />';
		    $html .= '<link rel="stylesheet" href="'. Cadre21InvitationEvent::$pluginDir .'dist/css/template.css" type="text/css" />';
			$html .= '<link rel="stylesheet" href="'. Cadre21InvitationEvent::$pluginDir .'dist/css/pdf-template.css" type="text/css" />';



			$html .= '<title>' . $filename .'</title>';
		$html .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
		$html .= '</head><body>';
		$html .= '<img src="'. get_stylesheet_directory(). '/dist/images/logo-cadre21.png" class="main-logo" />';
		$html .= '<div class="clearall"></div>';


		foreach ($events as $eventID){

			$event = get_post($eventID);
			$eventBadge = get_post(get_post_meta($eventID, '_cie_group_badge_id', true));

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

			$html .= self::innerHTML($elements);

		}

		$html .= self::generateFooter();

		$html .= '</body></html>';

		return $html;
	}

	public static function generateFooter() {

		$products = get_posts(array(
			'numberposts' => -1,
			'post_type'   => 'product',
			'post_status' => 'publish', //Only get the completed orders
		));

		$html = '<div style="text-align: left;" id="legend"><h2>' . __("legend", 'bf-events') . '</h2>';
		$html .= '<ul>';

		foreach ($products as $key => $product){
			$html .= '<li>#'.$product->ID . ' : ' . $product->post_title. '</li>';
		}

		$html .= '<ul></div>';

		return $html;
	}

	private static function generatePDF( $html, $filename ){

		$options = array(
			'margin_left' => 10,
			'margin_right' => 10,
			'margin_top' => 20,
			'margin_bottom' => 25,
			'margin_header' => 10,
			'margin_footer' => 10,
			'mode' => 'utf-8',
			'format' => 'A4-L'
		);

		ob_clean();
		$mpdf = new Mpdf( $options );
		$mpdf->WriteHTML($html);
		$mpdf->Output( $filename . '.pdf','D');
		$mpdf->Output(wp_upload_dir()['path'] . $filename . '.pdf','F');
	}
}