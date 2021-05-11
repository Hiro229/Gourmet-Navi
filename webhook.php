<?php

/**
 * Copyright 2016 LINE Corporation
 *
 * LINE Corporation licenses this file to you under the Apache License,
 * version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at:
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

require_once('./LINEBotTiny.php');
require_once('./hotpepper_api_key.php');

$client = new LINEBotTiny($channelAccessToken, $channelSecret);
foreach ($client->parseEvents() as $event) {
	switch ($event['type']) {
		case 'message':
			$message = $event['message'];
			switch ($message['type']) {
				//位置情報が送られてきたとき
				case 'location':
					//APIから店情報を取得
					$url = 'http://webservice.recruit.co.jp/hotpepper/gourmet/v1/';
					$url .= '?key=' . $hotpepper_key;
					$url .= '&format=json';
					$url .= '&lat=' . $message['latitude'];
					$url .= '&lng=' . $message['longitude'];
					$url .= '&range=3';
					$url .= '&order=4';
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					$html = curl_exec($ch);
					curl_close($ch);
					$json = json_decode($html);
					if (!empty($json->results->shop)) {
						//カルーセルの準備
						$columns = [];
						$counts = count($json->results->shop);
						for ($i = 0; $i < $counts; $i++) {
							$title = mb_strimwidth($json->results->shop[$i]->name, 0, 40, '...');
							$text = mb_strimwidth($json->results->shop[$i]->genre->catch, 0, 60, '...');
							$shop = [
								'thumbnailImageUrl' => $json->results->shop[$i]->photo->mobile->l,
								'title' => $title,
								'text' => $text,
								'actions' => [
									['type' => 'message', 'label' => '住所', 'text' => $json->results->shop[$i]->address],
									['type' => 'uri', 'uri' => $json->results->shop[$i]->urls->pc, 'label' => 'HotPepperで見る'],
									['type' => 'uri', 'uri' => $json->results->shop[$i]->coupon_urls->pc, 'label' => 'クーポン']
								]
							];
							array_push($columns, $shop);
							if ($i === 9) {
								break;
							}
						}
						$template = [
							'type' => 'carousel',
							'columns' => $columns,
						];
						//返信内容の設定
						$client->replyMessage([
							//返信先を設定
							'replyToken' => $event['replyToken'],
							//返信メッセージの設定
							'messages' => [
								[
									'type' => 'template',
									'altText' => 'お店を検索したよ！',
									'template' => $template
								]
							]
						]);
					} else {
						$client->replyMessage([
							'replyToken' => $event['replyToken'],
							'messages' => [
								[
									'type' => 'text',
									'text' => 'お店を検索できませんでした'
								]
							]
						]);
					}
					break;
				default:
					error_log('Unsupported message type: ' . $message['type']);
					break;
			}
			break;
		default:
			error_log('Unsupported event type: ' . $event['type']);
			break;
	}
};
