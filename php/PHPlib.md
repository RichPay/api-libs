# 睿聚科技代收付 PHP 函式庫使用說明

本文件說明如何使用睿聚科技 (以下簡稱我司) 提供的 PHP 函式庫 (以下簡稱 PHPlib)，進行代收付作業的方式。

本文件內連至後台的超鏈結，均為測試環境。

## 變更記錄

| 日期 | 版號 | 說明 |
|:----------:|:-----:|:------|
| 2020/03/01 | 1.0.0 | 初版 |
| 2020/04/02 | 1.1.0 | 新增訂單查詢功能 |

## 名詞解釋

- 客戶：購買商品或服務之人
- 商家：提供商品或服務給客戶購買，並委託我司代為收取費用之人 (以下簡稱貴司)
- 支付供應商：與我司簽訂合約，實際執行收款作業之人，如銀行、超商等 (以下簡稱銀行)
- 支付方式：支付供應商與我司議定的收款方式，如 ATM 轉帳、信用卡或超商繳費代碼
- 訂單：我司代貴司向客戶請款的憑據，包括了客戶資料、應收金額、交易內容、支付方式
- 付款指示：提供給客戶，指示如何付款的資訊
- 付款方式：指示客戶付款的方式，目前有 `ATM` (銀行虛擬帳號，使用 ATM 轉帳繳費) 及 `TOKEN` (便利商店代碼繳費) 兩種

## 完整流程

1. 客戶在貴司的網站選擇商品後選擇結帳
2. 貴司使用 PHPlib 的建立訂單功能，產生指定支付方式及金額的訂單
3. 貴司記錄此訂單，並將 PHPlib 回傳的付款指示提供給客戶
4. 客戶依資訊進行付款
5. 支付供應商通知我司已收到指定款項，我司再通知貴司客戶已付款成功
6. 貴司處理出貨事宜，並通知客戶已收到款項

# PHPlib 說明

## TL;DR

建立訂單

```php
<?php
include('RichpayAPI.php');
$api = new RichpayAPI();
$ret = $api->createOrder([
  // 以下必填
  'amount' => 3200, // 訂單金額
  'title' => '○○商城購物', // 訂單標題
  'name' => '王大明', // 客戶姓名，依金管會要求請務必確實填寫
  'email' => 'user@example.com', // 客戶電郵，依金管會要求請務必確實填寫
  'phone' => '0987654321', // 客戸電話，依金管會要求請務必確實填寫
  'result_callback' => 'http://example.com/callback.php', // 貴司接收
                                              // 付款通知訊息的網址
  // 以下兩項擇一填寫
  'pay_type' => 'ATM', // 指定付款的方式，目前支援 ATM (銀行虛擬帳號)
                       // 或是 TOKEN (超商代碼繳費) 兩種
  'method' => '12345678ABCDEF', // 使用指定的支付方式，請從後台查詢貴司的
                                //支付方式編號填入
  // 以下為選填
  'detail' => "- 上衣 900 元 X 2 件 = 1800 元\n" . 
      "- 牛仔褲 1400 元 X 1 件 = 1400 元", // 訂單明細，可使用 markdown
  'order_id' => '12345678', // 貴司自行產生的訂單編號
]);

if ($result['resp_status'] === 'OK' and 
    $result['order']['status'] == 'CONFIRMED') {
    
    // 訂單建立成功，顯示付款資訊給客戶
    // 此範例為 ATM 轉帳繳款
    echo sprintf(
        "請在 %s 之前到 ATM 轉帳 %d 元到%s (%s) 的帳號 %s 完成繳費",
        $result['order']['due'],
        $result['order']['amount'],
        $result['payment']['bank_name'],
        $result['payment']['bank_code'],
        $result['payment']['account'],
    );
} else {
    // 建單失敗
    var_dump($result['error']); // 錯誤資訊
}
```

接收付款成功通知

```php
<?php
include('RichpayAPI.php');
$api = new RichpayAPI();
$data = $api->acceptNotify(file_get_contents('php://input'));
if (isset($data['error'])) {
    switch ($data['error']['code']) {
    case 'E999-CHCK':
        // 檢核碼錯誤，可能受到攻擊
        die("I AM ATTACKED");
    default:
        // 內容格式錯誤，可能連線有問題或受到攻擊
        die("I AM ATTACKED");
    }
}

// 更新貴司資料庫
saveToDB($data['data']);
// 記 log (以 ATM 轉帳為例)
saveToLog(sprintf(
  '訂單 %s 已於 %s 付款 %d 元完成，來源銀行及帳號末五碼為 %s',
  $data['data']['order_id'],
  $data['data']['paid_time'],
  $data['data']['amount'],
  $data['data']['param']['source_account']
));

echo '0000'; // 回傳資料只要是以 '0000' 開頭，就會視為成功
```

## 建立訂單

```php
$result = $api->createOrder($param);
```

參數為 array 格式，可使用的 array key 如下

| 欄位 | 說明 |
|:------|:------|
| `amount` | 訂單金額，必填 |
| `title`  | 訂單標題，部份支付方式會顯示此欄位給客戶以便確認，請簡述本次請款的原因，並避免使用特殊符號，必填 | 
| `name`   | 客戶姓名，此項目為金管會要求之資訊，請務必確實填寫，必填 |
| `email`  | 客戶電郵，此項目為金管會要求之資訊，請務必確實填寫，必填 |
| `phone`  | 客戶聯絡電話，此項目為金管會要求之資訊，請務必確實填寫，必填 |
| `result_callback` | 貴司接收付款成功通知的完整網址 |
| `pay_type` | 收款的方式，我司會依與貴司訂定的合約，選擇適當的支付方式。可使用的值有 `ATM` (銀從虛擬帳號) 及 `TOKEN` 超商代碼繳費。若貴司有填寫 `method` 的話，此欄位可不填 |
| `method` | 指定支付方式的編號。貴司可從 [後台查看已開通的支付方式](https://bo-test.richpay.com.tw/method/list_mine)，並抄錄編號填入此欄位。若貴司有填寫 `pay_type` 欄位的話，此欄位可不填 |
| `order_id` | 貴司自訂的訂單編號，可不填 |
| `detail` | 訂單明細，會顯示在後台，但不會出現在收款過程。可使用 markdown 語法 |

回傳值為訂單資訊及付款指示

```php
[
    "resp_status": "OK", // OK 代表我司有回傳訂單資訊，
                         // 其他代表貴司的 $param 內容有誤，請確認後重試
    "order: [
        "id" => '12345678ABCDEF', // 我司訂的訂單編號，唯一
        "status" => "CREATED" // 訂單狀態 (若是 CONFIRMED 代表訂單已建立，
                              // 其他代表失敗)
        "amount" => 100.0000, // 訂單金額
        "token" => "", // 代碼 (不同付款方式會有不同的值，有可能是空字串)
        "title" => "", // 您提供的訂單標題
        "detail" => "", // 您提供的訂單明細
        "due" => '1234-12-23 12:34:56', // 繳款期限
        "request_id" => 1234, // 支付通道編號
        "method_id" => 1234, // 支付方式的編號
    ],
    "payment" => [
        // 付款指示，不同的付款方式會有不同的內容
    ], 
]
```

若是此訂單是使用 `ATM` 付款，付款指示的內容如下

```php
[
    "type" => "ATM", // ATM 轉帳
    "data" => [
        "bank_name" => "○○銀行", // 銀行名稱
        "bank_code" => "987", // 銀行轉帳代碼
        "account" => "1234", // 虛擬帳號
    ]
]
```

若是此訂單是使用 `TOKEN` 付款，付款指示的內容如下

```php
[
    "type" => "TOKEN", // 代碼繳費
    "data" => [
        "token" => "abc123", // 繳費代碼
        "description" => "請至四大超商機台 (下略)", // 繳費方式說明
    ]
]
```

## 接收付款成功通知

```php
$result = $api->acceptNotify($http_body);
```

**注意** ：為避免惡意人士透過貴司接收付款的網址攻擊貴司，請務必設定防火牆，只允許我司 IP 呼叫該網址。

通知內容格式

```php
[
    "data" => [
        "result_id" => "123ABC", // 付款結果的編號
        "amount => 100.0000, // 付款金額
        "paid_time" => "1234-12-23 12:34:56", // 付款狀態發生的時間
        "order_id" => "123ABC", // 我司訂的訂單編號 (唯一)
        "store_order_id" => "ABCD1234", // 貴司提供的自訂訂單編號
        "order_token" => "", // 代碼 (不同付款方式會有不同內容)
        "order_create_at" => "1234-12-23 12:34:56", // 訂單建立時間
        "pay_type" => "ATM", // 原訂單指定的付款方式 (ATM 或 TOKEN)
        "param" => [ // 支付供應商提供的付款細節
            // ATM 提供以下資訊
            "source_account": "007-12345", // 格式為 
                                           // 付款銀行代碼-付款帳號末五碼

            // TOKEN 提供以下資訊 (視超商提供之資訊，部份欄位可能會是空白
            "from": "711", // 超商名稱，可能是 711/fami/ok/hilife
            "terminal_no": "1234", // 繳款門市/收銀機的編號
            "terminal_name": "○○門市", // 門市名稱
            "terminal_tel": "04-22343234", // 門市電話
            "terminal_addr": "台中市○○路○號",  // 門市地址
            "serial": "abc123",    // 交易序號
            "barcode2": "12345678", // 第二段條碼
        ],
    ],
    "checksum" => "123abc", // 檢查碼
    "error" => [ // 此項僅發生錯誤時才會出現
        "code" => "E1234-ABCD", // 錯誤碼
    ],
]
```

為避免惡意人士攻擊，此函式會計算通知內容的驗證碼。若驗證錯誤或是解析通知內容失敗， `$result['error']['code']` 的內容將會有錯誤代碼。

- `E999-CHCK`: 驗證碼不符，可能原因有三: 遭受惡意人士攻擊、使用錯誤的 PHPlib 版本 (正式環境與測試環境的 PHPlib 版本不同，請至 [後台下載](https://bo-test.richpay.com.tw/client-libs/php))
- 其他: 內容解析錯誤，請自行 dump http body 的內容確認

## 取得特定訂單的付款指示

```php
$result = $api->getPaymentInfo($order_id);
```

`$order_id` 是睿聚產生的唯一訂單編號

回應格式如下

```php
[
    "due" => "2020/04/02 12:34:56", // 繳款截止時間
    "type" => "ATM", // 付款方式 (ATM 或 TOKEN)
    "data" => [
        // 此項內容與建立訂單回應的格式相同，以 ATM 為例
        "bank_name": "○○銀行",
        "bank_id": "987",
        "account": "1234",
    ],
]
```

- 若 `$result == null` 代表訂單編號錯誤

## 取得訂單的付款結果 (單筆)

```php
$result = $api->listResults($order_id, $page);
```

`$order_id` 是睿聚產生的唯一訂單編號，`$page` 是頁數 (每頁固定 50 筆)

回應格式如下

```php
[
    "data" => [ // 通常只有一筆，但部份情況可能會有多筆
    [ // 第一筆
        "id" => "12345ABCDEF", // 付款結果編號
        "key" => "1234ABC", // 代碼，不同支付方式的內容會不一樣
        "amount" => 3200, // 付款金額
        "paid_time" => "2020/04/02 12:34:56", // 付款時間
        "status" => "PAID", // 付款狀態，PAID 或 TRANSFERRED 表示已付款，
                            // CONFIRMED 表示我司已撥款給貴司
        "title" => "匯款銀行代碼 987 帳號末五碼 12345", // 簡述付款資訊
        "summary" => "ATM 交易資訊\n\n- 交易時間: 202003141531", 
                                 // 本次付款的詳細資訊 (markdown 格式)
        "order_id" => "123456ABCDEF", // 訂單編號
        "fixed_fee" => 30, // 定額手續費，手續費相關說明請見後台
        "fee_ratio" => 0.8, // 比例手續費
        "min_fee" => 10, // 最低手續費
        "max_fee" => 150, // 最高手續費
        "fee" => 36, // 本次付款實收手續費
    ],
    ],
]
```

- 若 `$result == null` 代表訂單編號錯誤
- 若沒有錯誤，但 `count($result['data'])`，代表尚無繳款記錄
- 若 `status` 欄位的內容與以上說明不同，代表尚未收到客戶款項

## 搜尋付款記錄

本 API 與 [後台付款記錄](https://bo-test.richpay.com.tw/report/result) 功能相同，建議您使用後台功能即可

```php
$result = $api->searchResults($param);
```

參數格式 (各欄位皆為選填)

```php
[
    "page" => 1, // 頁數
    "page_size" => 10, // 每頁幾筆 (最大 50)
    "result_keywords" => ["1234", "5678"], // 用關鍵字搜尋付款記錄
                          // 含標題、明細、代碼、訂單號、編號及其他資訊
    "method_ids" => ["12345ABCDE", "12346ABCDF"],  // 支付方式的編號
    "order_ids" => ["12345ABCDE", "12346ABCDF"],  // 訂單編號
    "result_ids" => ["12345ABCDE", "12346ABCDF"],  // 付款記錄編號
    "result_states" => [
        "PAID", "TRANSFERRED", "CONFIRMED",  // 付款狀態
    ],
    "order_store_id" => "1234ABC", // 貴司自訂的訂單編號
    "order_amount_min" => 100, // 最小訂單金額
    "order_amount_max" => 100, // 最大訂單金額
    "result_amount_min" => 100, // 最小付款金額
    "result_amount_max" => 100, // 最大付款金額
    "confirm_date_min" => "2020/03/01 00:00:00", // 撥款日起始點 (含)
    "confirm_date_max" => "2020/03/01 00:00:00", // 撥款日結束點 (不含)
    "paid_date_min" => "2020/03/01 00:00:00", // 付款日起始點 (含)
    "paid_date_max" => "2020/03/01 00:00:00", // 付款日結束點 (不含)
    
    // 以下為排序參數，1 代表由小至大，-1 代表由大至小，0 代表不使用此欄位排序
    "by_amount" => 0, // 訂單金額
    "by_confirm_date" => 0,  // 撥款日期
    "by_date" => 0,  // 訂單建立時間
    "by_due" => 0, // 繳款期限
    "by_id" => 0, // 我司產生的唯一訂單編號
    "by_store_id" => 0, // 貴司自訂的訂單編號
    "by_key" => 0, // 訂單的代碼欄位
    "by_method" => 0, // 支付方式
    "by_result_amount" => 0, // 付款金額
    "by_result_id" => 0, // 付款記錄編號
    "by_result_status" => 0, // 付款記錄的狀態
    "by_result_token" => 0,  // 付款記錄的代碼
    "by_result_date" => 0, // 付款日期
]
```

回應格式如下

```php
[
    "summary" => [
        "count" => 100, // 總共幾筆
        "amount" => 100, // 訂單金額總計
        "result_amount" => 100, // 付款記錄的金額總計
        "confirmed" => 100, // 已撥款的付款記錄的金額總計
    ],
    "records" => [ // 會有多筆
        [
            "id" => "12345ABCDE", // 付款記錄編號
            "key" => "1234", // 付款記錄代碼
            "amount" => 1234, // 付款金額
            "paid_time" => "2020/04/01 12:34:56", 
                                           // 付款時間，可能是 null
            "status" => "PAID", // 付款記錄狀態
            "title" => "匯款銀行代碼 987 帳號末五碼 12345", 
                                                  // 簡述本次付款的資訊
            "summary" => "ATM 交易資訊\n\n- 交易時間: 202003141531",
                                    // 本次付款的詳細資訊 (markdown 格式)
            "order_id" => "123456ABCDEF", // 訂單編號
            "fixed_fee" => 30, // 定額手續費，手續費相關說明請見後台
            "fee_ratio" => 0.8, // 比例手續費
            "min_fee" => 10, // 最低手續費
            "max_fee" => 150, // 最高手續費
            "fee" => 36, // 本次付款實收手續費
            "order_amount" => 1234, // 訂單金額
            "order_token" => "1234ABC", // 訂單代碼
            "order_store_id" => "1234", // 貴司自訂的訂單編號
            "order_title" => "test", // 訂單標題
            "order_detail" => "test", // 訂單明細
            "order_due" => "2020/04/02 12:34:56", // 繳款期限
            "order_created" => "2020/03/31 12:34:56", // 訂單建立日期
            "pay_type" => "ATM", // 付款方式
            "confirm_at" => "2020/04/07 12:34:56",
                                          // 撥款時間，可能是 null
        ],
    ],
]
```

若 `$result == null` 代表參數錯誤
