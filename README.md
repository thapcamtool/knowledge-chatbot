Normal chat from facebook webhook
```
{
	"entry": [
		{
			"id": 110620843802028,
			"time": 1578845572975,
			"messaging"	: [
				{
					"sender": {
						"id": 2649817768468983
					},
					"recipient": {
						"id": 110620843802028
					},
					"message": {
						"mid":"m_DpPFPZUG8cxwINwnoHj67_UAOK5hAqEOwb_qpbfvVeWtKwhp3y8pvfbF-OxlKXnCdiEMARCgASozvDcRqtmYDQ",
						"text": "Xin chao"
					}
				}	
			]
		}
	]
}
```


Sample like (image) from facebook webhook

```{
	"entry": [
		{
			"id": 110620843802028,
			"time": 1578845572975,
			"messaging"	: [
				{
					"sender": {
						"id": 2649817768468983
					},
					"recipient": {
						"id": 110620843802028
					},
					"message": {
						"mid":"m_DpPFPZUG8cxwINwnoHj67_UAOK5hAqEOwb_qpbfvVeWtKwhp3y8pvfbF-OxlKXnCdiEMARCgASozvDcRqtmYDQ",
						"attachments": [
							{
								"type": "image",
								"payload": {
									"url": "https://scontent.xx.fbcdn.net/v/t39.1997-6/39178562_1505197616293642_5411344281094848512_n.png?_nc_cat=1&_nc_oc=AQkvhykv1AUw44bJWysYBbiCI2oR6UgvlkDu9XhY83a-pBKSHHmXstGvKy8Bwy6NwgMz3WgXl-WrRZJ-qNYEqNi1&_nc_ipfwd=1&_nc_ad=z-m&_nc_cid=0&_nc_zor=9&_nc_ht=scontent.xx&oh=0ca1a53ba860345aaebd5090f74ba4be&oe=5E91FC75",
									"sticker_id": 369239263222822
								}
							}
						],

						"sticker_id": 369239263222822
					}
				}	
			]
		}
	]
}
```

Postback
```
(
    [object] => page
    [entry] => Array
        (
            [0] => Array
                (
                    [id] => 100161904867171
                    [time] => 1582570192518
                    [messaging] => Array
                        (
                            [0] => Array
                                (
                                    [sender] => Array
                                        (
                                            [id] => 2756500614416883
                                        )

                                    [recipient] => Array
                                        (
                                            [id] => 100161904867171
                                        )

                                    [timestamp] => 1582570190818
                                    [postback] => Array
                                        (
                                            [title] => title 2 of button
                                            [payload] => STRING_SENT_TO_WEBHOOK2
                                        )

                                )

                        )

                )

        )

)

```

Sample post to send user

```
{
  "recipient":{
    "id":"{{PSID}}"
  },
  "messaging_type": "response",
  "message":{
	"text": "Hello, world!"
  }
}
```

###  pattern 

```
/(anh|em).*(yêu|thương|thích|mến) (anh|em)/
(anh|em).*(yêu|thương|thích|mến) (anh|em).(?!không|chứ)
--> don't have không, chứ in last sentence
```

```
làm vợ anh nhé
lấy anh nhé
lấy anh đi
anh muốn nói điều này quan trọng với em
anh bảo cái này
biến đi
cút đi
biến mẹ mày đi
tạm biệt nhé
bye
mai gặp lại
thằng chó
vì sao chứ
có lẽ  mình không hợp nhau
là mình không hợp nhau
quên em đi anh
quên anh đi em
chia tay đi
chúc em ngủ ngon
anh ngủ đi mai còn đi làm
chào em
em có yêu anh không
em có yêu anh chứ
em yêu anh
anh yêu em nhiều lắm

```
==============

NOTE: 

remove null value json encode to post API
```
$json_str = json_encode($obj);
$json_str = preg_replace('/,\s*"[^"]+":null|"[^"]+":null,?/', '', $json_str);
print_r($json_str);
```

Structure Postback of intent:
```
INTENT::learn_word|CUSTOM
INTENT::learn_word|SYSTEM
```


Intent learn Japanese: learn_word
List state (addition)
```
CUSTOM
SYSTEM
ERROR_FORMAT
NOT_JAPANESE
SUCCESS
END
```

```
[
	{
		"type": "postback",
		"title": "Nhập dữ liệu",
		"payload": "INTENT::learn_word|CUSTOM"
	},
	{
		"type": "postback",
		"title": "Học từ trong hệ thống",
		"payload": "INTENT::learn_word|SYSTEM"
	}
]

[
	{
		"type": "postback",
		"title": "Nhập tiếp",
		"payload": "INTENT::learn_word|CUSTOM"
	},
	{
		"type": "postback",
		"title": "Dừng",
		"payload": "INTENT::learn_word|END"
	}
]

TYPE_IMPORT_WORD
[
	{
		"type": "postback",
		"title": "Học toàn bộ",
		"payload": "INTENT::learn_word|ALL_WORD"
	},
	{
		"type": "postback",
		"title": "Chọn từ muốn học",
		"payload": "INTENT::learn_word|CHOICE_WORD"
	}
]

review_word
[
	{
		"type": "postback",
		"title": "Kiểm tra nghĩa",
		"payload": "INTENT::review_word|MEANS"
	},
	{
		"type": "postback",
		"title": "Kiểm tra âm hán",
		"payload": "INTENT::review_word|NAMEWORD"
	},
	{
		"type": "postback",
		"title": "Kiểm tra cách đọc",
		"payload": "INTENT::review_word|PRONOUNCE"
	}
]

review_word DONE
[
	{
		"type": "postback",
		"title": "Reset Ôn tập",
		"payload": "INTENT::review_word|RESET"
	},
	{
		"type": "postback",
		"title": "Hoàn thành ôn tập",
		"payload": "INTENT::review_word|COMPLETE"
	}
]
```

### REcheck database musics
- remove mashup: 
```delete FROM `musics` WHERE name like '%mashup%'```
- remove duplicate by `link_origin`
```
SELECT id, link_origin FROM musics WHERE link_origin in (
SELECT link_origin FROM `musics` 
GROUP by link_origin
HAVING count(1) > 1)
```
- trim: &nbsp
- unidecode name_short

```
[
	{
		"type": "postback",
		"title": "Đoán tên bài hát",
		"payload": "INTENT::music_game|NAME_SONG"
	},
	{
		"type": "postback",
		"title": "Đoán câu hát tiếp theo",
		"payload": "INTENT::music_game|NEXT_SENTENCE"
	},
	{
		"type": "postback",
		"title": "Xem thông tin bài hát",
		"payload": "INTENT::music_game|INFO_SONG"
	}
]
```

Attachment
```
{
    "recipient_id": "2756500614416883",
    "message_id": "m_EFDgtpS6X2sHfdqcig15s1g7muOG2BeJ6PFPqtlJdqZTHkmWzM1ROItAwvP_AESEHW1-lkCg2ngMD0hrzDg_eA",
    "attachment_id": "611068736409219"
}

{
  "recipient":{
    "id":"2756500614416883"
  },
  "messaging_type": "response",
  "message":{
    "attachment":{
      "type":"audio", 
      "payload":{
        "url":"https://translate.google.com/translate_tts?ie=UTF-8&q=xin%20ch%C3%A0o%20b%E1%BA%A1n&tl=vi&total=1&idx=0&textlen=12&tk=561568.927394&client=webapp&prev=input", 
        "is_reusable":true
      }
    }
  }
}
```

Sing intent 

```
(hát|hát bài) (.*) đi
```

message
```hát bài chúc mừng sinh nhật đi```
