<?php

namespace App\Helpers;
use GuzzleHttp\Client;
use App\Models\Intent;
use App\Models\Answer;
use App\Models\Session;
use App\Models\Word;
use App\Models\Learn;
use App\Models\Fb\FbAnswer;
use App\Models\Fb\TextMessage;
use App\Models\Fb\ButtonMessage;
use App\Models\Fb\ButtonTemplate;

class NyHelper extends KnowledgeHelper
{
	public static function answer($query, $page_id, $PID) {

		$result = [];

		$current_intent = null;

		$session = Session::where('PID', $PID)->whereNull('expired_at')->first();

		if (array_key_exists('type', $query)) {
			if ($query['type'] == 'text') {
				$message = mb_strtolower($query['content']);

				$intents = Intent::with('answers')->where('page_id', $page_id)->get();
				

				$matches = null;

				foreach ($intents as $intent) {
					$sentences = $intent->sentences;
					// print($sentences);
					if (!is_null($sentences)) {
						$list_sentence = explode(';', $sentences);
						// print_r($list_sentence);
						foreach ($list_sentence as $sen) {
							if ($sen == $message) {
								$current_intent = $intent;
								break;
							}
						}
					}

					$patterns = $intent->patterns;

					if (!is_null($patterns) && is_null($current_intent)) {
						$list_pattern = explode(';', $patterns);
						foreach ($list_pattern as $pat) {
							try {
								$pat = "/" . $pat . "/";
								if (preg_match($pat, $message, $matches, PREG_OFFSET_CAPTURE)) {
									$current_intent = $intent;
									break;
								}
							} catch (\Exception $e) {
								// print($e->getMessage());
							}
							
						}
					}
				}

				if (!is_null($current_intent)) {
					$answers = $current_intent->answers->toArray();

					$answers = array_filter($answers, function($item) use (&$page_id)  {
					  	return $item['page_id'] == $page_id && $item['state'] == NULL;
					});

					if (count($answers) > 0) {
						$rand = array_rand($answers);

						$result[] = $answers[$rand];

						static::updateSession($session, $PID, $current_intent->name, NULL, NULL);
					}
				} else {
					if ($session) {
						if ($session->intent_name == 'learn_word') {

							$sentence = $query['content'];
							$result = static::intentLearnWord($session, $PID, $page_id, $sentence);
						}

						if ($session->intent_name == 'review_word') {

							$sentence = $query['content'];
							$result = static::intentReviewWord($session, $PID, $page_id, $sentence);
						}
					}

					

				}
			}

			// process postback
			if ($query['type'] == 'postback' && $session) {
				$payload = $query['content'];

				if (strpos($payload, 'INTENT::') !== false) {
					$intent_string = explode("::", $payload)[1];

					$session_slot = explode(';', $session->slot);
					$data_slot = [];
					foreach ($session_slot as $slot) {
						$split_slot = explode(':', $slot);
						if (count($split_slot) > 1) {
							$data_slot[$split_slot[0]] = $split_slot[1];
						}
					}
					if (strpos($intent_string, 'learn_word|') !== false) {

						// process learn word: choice word want to learn
						$result = static::learnWordPostback($session, $PID, $page_id, $session_slot, $intent_string);
					}

					if (strpos($intent_string, 'review_word|') !== false) {

						// process review word: review word added in system (STATUS: LEARNING)
						$result = static::reviewWordPostback($session, $PID, $page_id, $session_slot, $intent_string);
					}
					
				}
			}
		}

		if (count($result) == 0) {
			$result = [
				[
					'id'	=>	null,
					'type'	=>	'text',
					'message'	=>	'I love you'
				]
			];
		}

		return $result;
	}

	public static function intentReviewWord($session, $PID, $page_id, $sentence) {
		$result = [];
		$flag_right = false;
		$split_addition = explode(':', $session->addition);
		$type_learn = $split_addition[0];
		$id_learn = $split_addition[1];

		$learn = Learn::find($id_learn);
		
		// addition, can update if next word
		$addition = $session->addition;

		// if find learn
		if ($learn) {

			$word = $learn->word;
			if (strpos($session->addition, 'MEANS:') !== false) {
				
				$means = array_map('trim', explode(',', $word->means));

				foreach ($means as $mean) {
					if (mb_strtolower($mean) == $sentence) {
						//flag right
						$flag_right = true;
						break;
					}
				}
			}

			if (strpos($session->addition, 'NAMEWORD:') !== false) {

				$name_word = mb_strtolower($word->name_word);

				if ($name_word == $sentence) {
					//flag right
					$flag_right = true;
				}
			}

			if (strpos($session->addition, 'PRONOUNCE:') !== false) {
				
				$pronounces = array_map('trim', explode(',', $word->pronounce));

				foreach ($pronounces as $pronounce) {
					if (mb_strtolower($pronounce) == $sentence) {
						//flag right
						$flag_right = true;
						break;
					}
				}
			}

			if ($flag_right) {
				// answer mean right
				$result[] = [
					'id'	=>	null,
					'type'	=>	'text',
					'message'	=>	'Bạn đã trả lời đúng!'
				];
				$result = array_merge($result, static::createInfoWord($word));

				// update status learn
				$learn->status = 'REVIEWED';
				$learn->save();

				// continue review
				$review_word = Learn::where([
					'status'	=>	'LEARNING',
					'PID'		=>	$PID
				])->with('word')->first();

				if ($review_word) {
					$word_review = $review_word->word;

					$message_word = "Từ " . $word_review->word . " nghĩa là gì nhỉ?";

					$result[] = [
						'id'	=>	null,
						'type'	=>	'text',
						'message'	=>	$message_word
					];

					// set addition to update session addition
					$addition = $type_learn . ':' . $review_word->id;

				} else {
					$intent_string = 'review_word|DONE';
					$answerDb = static::getAnswerDb('review_word', 'DONE', $page_id);
					if ($answerDb) {
						$result[] = $answerDb;
					}
					// TODO: add postback ask reset review again next time
				}
			} else {
				// wrong
				$result[] = [
					'id'	=>	null,
					'type'	=>	'text',
					'message'	=>	'Sai rồi, vui lòng trả lời lại!'
				];

				//button view answers
				$result[] = [
					'id'	=>	null,
					'type'	=>	'button',
					'message'	=>	'Xem đáp án?',
					'buttons' => json_encode([
						[
							"type"		=> "postback",
							"title"		=> "Xem",
							"payload"	=> "INTENT::review_word|". $type_learn .":" . $id_learn
						]
					])
				];
			}
		}

		static::updateSession($session, $PID, $session->intent_name, $addition, NULL);

		return $result;

	}

	public static function intentLearnWord($session, $PID, $page_id, $sentence) {
		$result = [];

		if ($session->addition == 'CUSTOM') {

			// TODO: check limit word by PID --> spam
			$word_split = explode(";", $sentence);
			if (strpos($sentence, ';') !== false && count($word_split) > 2) {

				if (static::isJapanese($word_split[0])) {

					// TODO: check word if exists
					$newWord = new Word;
					$newWord->word = trim($word_split[0]);
					$newWord->name_word = trim($word_split[1]);
					$newWord->means = trim($word_split[2]);
					$newWord->language = 'JA';
					$newWord->page_id = $page_id;
					$newWord->created_by_PID = $PID;

					$newWord->save();

					$addition = 'SUCCESS';
					$result[] = static::getAnswerDb($session->intent_name, $addition, $page_id);
				} else {
					$addition = 'NOT_JAPANESE';
					$result[] = static::getAnswerDb($session->intent_name, $addition, $page_id);
				}
			} else {
				$addition = 'ERROR_FORMAT';
				$result[] = static::getAnswerDb($session->intent_name, $addition, $page_id);
			}
		}

		// TODO Process with SYSTEM, need find lesson --> create slot
		if ($session->addition == 'SYSTEM' || $session->addition ==  'WAIT_LESSON') {

			$re = '/(bài|bài số) (\d)/im';

			preg_match_all($re, $sentence, $matches_lesson, PREG_SET_ORDER, 0);

			$number_lesson = 0;
			if (count($matches_lesson)) {
				$number_lesson = $matches_lesson[0][2];
			}

			if($number_lesson) {
				// init word to learn
				$words = Word::where([
					'lesson'	=>	$number_lesson,
					'language'	=>	'JA'
				])->get();

				// delete all status NEW of PID
				$learn_news = Learn::where([
					'status'	=>	'NEW',
					'lesson'	=>	$number_lesson,
					'PID'		=>	$PID
				])->delete();

				foreach ($words as $w) {
					$learn = new Learn;
					$learn->PID = $PID;
					$learn->word_id = $w->id;
					$learn->page_id = $page_id;
					$learn->lesson = $number_lesson;
					$learn->status = 'NEW';
					$learn->save();
				}

				$addition = 'TYPE_IMPORT_WORD';
				$slot = $session->slot . ':' . $number_lesson;
				$result[] = static::getAnswerDb($session->intent_name, $addition, $page_id);
				static::updateSession($session, $PID, $session->intent_name, $addition, $slot);

			} else {
				$addition = 'WAIT_LESSON';
				$result[] = static::getAnswerDb($session->intent_name, $addition, $page_id);
			}
		}

		return $result;
	}

	public static function reviewWordPostback($session, $PID, $page_id, $data_slot, $intent_string) {
		$result = [];

		$intent_split = explode("|", $intent_string);

		$intent_name = $intent_split[0];
		$intent_addition = NULL;

		if (count($intent_split) == 2) {
			$intent_addition = $intent_split[1];
		}

		$answerDb = static::getAnswerDb($intent_name, $intent_addition, $page_id);
		if ($answerDb) {
			$result[] = $answerDb;
		}

		$id_learn_view = NULL;
		$type_learn_view = NULL;
		if ($intent_addition) {
			$addition_split = explode(':', $intent_addition);
			if (count($addition_split) > 1) {
				$id_learn_view = $addition_split[1];
				$type_learn_view = $addition_split[0];
			}
		}

		//view answer
		if ($id_learn_view) {
			$learn = Learn::find($id_learn_view);

			if ($learn) {
				$word_view = $learn->word;

				$result = array_merge($result, static::createInfoWord($word_view));

				// update status learn
				$learn->status = 'FAILED';
				$learn->save();

				// continue review
				$review_word = Learn::where([
					'status'	=>	'LEARNING',
					'PID'		=>	$PID
				])->with('word')->first();

				if ($review_word) {
					$word_review = $review_word->word;

					$message_word = "Từ " . $word_review->word . " nghĩa là gì nhỉ?";

					$result[] = [
						'id'	=>	null,
						'type'	=>	'text',
						'message'	=>	$message_word
					];

					// set addition to update session addition
					$intent_addition = $type_learn_view . ':' . $review_word->id;

				} else {
					$intent_string = 'review_word|DONE';
					$answerDb = static::getAnswerDb('review_word', 'DONE', $page_id);
					if ($answerDb) {
						$result[] = $answerDb;
					}
					// TODO: add postback ask reset review again next time
				}

			}
		}

		if ($intent_string == 'review_word|MEANS') {
			$learn_word = Learn::where([
				'status'	=>	'LEARNING',
				'PID'		=>	$PID
			])->with('word')->first();

			if ($learn_word) {
				$word_review = $learn_word->word;

				$message_word = "Từ " . $word_review->word . " nghĩa là gì nhỉ?";

				$result[] = [
					'id'	=>	null,
					'type'	=>	'text',
					'message'	=>	$message_word
				];

				// set intent_addtion to update session addition
				$intent_addition = 'MEANS:' . $learn_word->id;

			} else {
				$intent_string = 'review_word|DONE';
				$answerDb = static::getAnswerDb('review_word', 'DONE', $page_id);
				if ($answerDb) {
					$result[] = $answerDb;
				}
			}
		}

		if ($intent_string == 'learn_word|END') {
			$session->expired_at = date('Y-m-d H:i:s');
			$session->save();
		} else {
			static::updateSession($session, $PID, $intent_name, $intent_addition, NULL);
		}

		return $result;
	}

	public static function learnWordPostback($session, $PID, $page_id, $data_slot, $intent_string) {

		$result = [];

		if ($intent_string == 'learn_word|ALL_WORD') {

			if (array_key_exists('lesson', $data_slot)) {
				// update all word of PID, lesson, status = NEW
				Learn::where([
					'status'	=>	'NEW',
					'lesson'	=>	$data_slot['lesson'],
					'PID'		=>	$PID
				])->update(['status' => 'LEARNING']);

				$intent_string = 'learn_word|END';
			}
		}

		$intent_split = explode("|", $intent_string);

		$intent_name = $intent_split[0];
		$intent_addition = NULL;

		if (count($intent_split) == 2) {
			$intent_addition = $intent_split[1];
		}

		$answerDb = static::getAnswerDb($intent_name, $intent_addition, $page_id);
		if ($answerDb) {
			$result[] = $answerDb;
		}
		// process confirm
		if (strpos($intent_string, 'learn_word|confirm_word') !== false) {
			$split_confirm = explode(":", $intent_string);
			$yes_or_no = $split_confirm[1];

			if ($yes_or_no == 'stop') {
				// update CANCEL in  all word of PID, lesson, status = NEW
				Learn::where([
					'status'	=>	'NEW',
					'lesson'	=>	$data_slot['lesson'],
					'PID'		=>	$PID
				])->update(['status' => 'CANCEL']);

				$intent_string = 'learn_word|END';
				$answerDb = static::getAnswerDb('learn_word', 'END', $page_id);
				if ($answerDb) {
					$result[] = $answerDb;
				}

			} else {
				$id_learn = $split_confirm[2];
				$learn_confirm = Learn::find($id_learn);

				if ($yes_or_no == 'yes') {
					$learn_confirm->status = 'LEARNING';
				} else {
					$learn_confirm->status = 'CANCEL';
				}

				$learn_confirm->save();

				$intent_string = 'learn_word|CHOICE_WORD';
			}
		}

		if ($intent_string == 'learn_word|CHOICE_WORD') {
			$learn_word_confirm = Learn::where([
				'status'	=>	'NEW',
				'lesson'	=>	$data_slot['lesson'],
				'PID'		=>	$PID
			])->with('word')->first();

			if ($learn_word_confirm) {
				$word_confirm = $learn_word_confirm->word;

				$result = array_merge($result, static::createInfoWord($word_confirm));
				
				// confirm payload 
				$result[] = [
					'id'	=>	null,
					'type'	=>	'button',
					'message'	=>	'Học từ này chứ?',
					'buttons' => json_encode([
						[
							"type"		=> "postback",
							"title"		=> "Có",
							"payload"	=> "INTENT::learn_word|confirm_word:yes:" . $learn_word_confirm->id
						],
						[
							"type"		=> "postback",
							"title"		=> "Không",
							"payload"	=> "INTENT::learn_word|confirm_word:no:" . $learn_word_confirm->id
						],
						[
							"type"		=> "postback",
							"title"		=> "Dừng",
							"payload"	=> "INTENT::learn_word|confirm_word:stop"
						]
					])
				];
			} else {
				$intent_string = 'learn_word|END';
				$answerDb = static::getAnswerDb('learn_word', 'END', $page_id);
				if ($answerDb) {
					$result[] = $answerDb;
				}
			}
		}

		if ($intent_string == 'learn_word|END') {
			$session->expired_at = date('Y-m-d H:i:s');
			$session->save();
		} else {
			$slot = null;
			if (array_key_exists('slot', $result[0])) {
				$slot = $result[0]['slot'];
			}
			static::updateSession($session, $PID, $intent_name, $intent_addition, $slot);
		}

		return $result;
	}

	public static function updateSession($session, $PID, $intent_name, $addition, $slot = null) {

		// TODO: add expired_at adn where condition
		if ($session) {
			$session->started_at = date('Y-m-d H:i:s');
		} else {
			$session = new Session;
			$session->PID = $PID;
		}


		$session->intent_name = $intent_name;
		$session->addition = $addition;
		if ($slot) {
			$session->slot = $slot;
		}
		$session->save();
	}

	public static function getAnswerDb($intent_name, $state, $page_id) {
		$result = null;

		$answers = Answer::where([
			'intent_name' => $intent_name,
			'state' => $state,
			'page_id' => $page_id
		])->get()->toArray();

		if (count($answers) > 0) {
			$rand = array_rand($answers);

			$result = $answers[$rand];
		}

		return $result;
	}

	public static function isKanji($str) {
	    return preg_match('/[\x{4E00}-\x{9FBF}]/u', $str) > 0;
	}

	public static function isHiragana($str) {
	    return preg_match('/[\x{3040}-\x{309F}]/u', $str) > 0;
	}

	public static function isKatakana($str) {
	    return preg_match('/[\x{30A0}-\x{30FF}]/u', $str) > 0;
	}

	public static function isJapanese($str) {
	    return static::isKanji($str) || static::isHiragana($str) || static::isKatakana($str);
	}

	public static function createInfoWord($word) {
		$result = [];
		$message_word = $word->word;
		$message_word .= ' - ' . $word->name_word;
		$message_word .= ' - ' . $word->means;
		$message_word .= "\nPhát âm: " . $word->pronounce;
		$message_word .= "\nMẹo nhớ: " . $word->tip_memory;
		$message_word .= "\nTừ: " . $word->addition;
		$result[] = [
			'id'	=>	null,
			'type'	=>	'text',
			'message'	=>	$message_word
		];

		return $result;
	}

}
?>