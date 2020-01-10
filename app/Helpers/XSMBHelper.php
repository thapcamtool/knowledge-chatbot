<?php

namespace App\Helpers;
use GuzzleHttp\Client;

class XSMBHelper
{
	public static function answer($query) {

		$result = '';

		$client = new Client(['headers' => ['Authorization' => 'Bearer WBXXNZRSGZBH7WLHKOAUGVT6J3PTRQ3Q']]);
		$response = $client->request('GET', 'https://api.wit.ai/message', ['query' => ['q' => $query]]);
		$body = $response->getBody();
		$obj = json_decode($body, true);
		// print_r($obj);
		$entities = $obj['entities'];

		if (count($entities) > 0) {

			$intent = 'UNKNOWN';

			if (array_key_exists('intent', $entities)) {
				$intent = $entities['intent'][0]['value'];
			}

			$datetime = 'UNKNOWN';

			if (array_key_exists('datetime', $entities)) {
				$datetime = $entities['datetime'][0]['value'];
			}

			switch ($intent) {
				case 'UNKNOWN':
					$result = 'bạn hỏi khó quá';
					break;

				case 'check_xsmb':

					if (array_key_exists('number', $entities)) {
						$number = $entities['number'][0]['value'];

						$result = 'bạn đánh con ' . $number . ' thì chúc mừng bạn, bạn tạch cmnr';
					} else {
						$result = 'bạn phải hỏi đánh con bao nhiêu chứ';
					}
					
					break;
				case 'query_xsmb_special':
					$xsmb = rand (10, 99);

					if ($datetime != 'UNKNOWN') {
						$string_datetime = date("d/m/Y", strtotime($datetime));
					} else {
						$string_datetime = 'hôm nay';
					}

					$homnay = date("d/m/Y", time());
					$homqua = date("d/m/Y", time() - 86400 * 1);
					$homkia = date("d/m/Y", time() - 86400 * 2);

					switch ($string_datetime) {
						case $homnay:
							$string_datetime = 'hôm nay';
							break;
						case $homqua:
							$string_datetime = 'hôm qua';
							break;
						case $homkia:
							$string_datetime = 'hôm kia';
							break;
					}

					$result = $string_datetime . ' đề về ' . $xsmb . '. Ra đê chứ???';
					
					break;
				
				case 'recommend': 
					$xsmb = rand (10, 99);
					$result = 'Tôi đoán đề hôm nay sẽ không phải là ' . $xsmb . '. Hehe';
					break;
				default:
					$result = 'bạn hỏi khó quá';
					break;
			}
		}
		return $result;
	}
}
?>