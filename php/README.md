### Callback 

#### 範例程式

成功時以 HTTP STATUS 200 回傳任何以 "0000" 開頭的文字即可，其他內容或 status 都會視為失敗。

```php
<?php
include_once('RichpayAPI.php');
$api = new RichpayAPI(); // 使用正式站
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

echo '0000'; // 回傳資料只要是以 '0000' 開頭，就會視為成功
// 匯富的通知程式會忽略以下訊息
var_dump($data['data']); // 付款結果
var_dump($data['checksum']); // 檢核碼
```

#### 格式說明

```php
[
    "data" => [
        "result_id" => "123ABC", // 付款結果的編號
        "amount => 100.0000, // 付款金額
        "paid_time" => "1234-12-23 12:34:56", // 付款狀態發生的時間
        "order_id" => "123ABC", // 原訂單編號 (匯富)
        "store_order_id" => "ABCD1234", // 您提供的原訂單編號
        "order_token" => "000000000000000", // 訂單代碼 (ATM 為虛擬帳號，TOKEN 為繳費代碼)
        "order_create_at" => "1234-12-23 12:34:56", // 訂單建立時間
        "pay_type" => "ATM", // 原訂單指定的付款方式 (ATM 或 TOKEN)
        "param" => [ // 供應商提供的付款細節
            // ATM (第一銀行) 提供以下資訊
            "source_account": "007-12345", // 格式為 匯款銀行代碼-匯款帳號末五碼
            
            // 代碼繳費 (四大超商) 提供以下資訊
            "terminal_no": "1234", // 繳款門市/收銀機的編號或名稱
            "serial": "abc123",    // 交易序號
            
            // 代碼繳費 (GMP) 提供以下資訊
            "shop_address": "○○門市", // 繳款門市的名稱或地址
            "barcode": "abc123",         // 第二段條碼
        ],
    ],
    "checksum" => "123abc", // 檢查碼
]
```

### 付款結果的狀態

- `PAID`: 您的客戶已完成付款
- `CONFIRMED`: 匯富已將此款項撥到與您約定的帳戶
- `FAILED`: 交易交敗
- `PENDING`: 等待客戶付款中

### 訂單的狀態

- `CREATED`: 訂單確認中
- `CONFIRMED`: 訂單確認完成，可以向您的客戶請款
- `CANCELED`: 匯富拒絕此訂單 (如金額超過限額)
- `REJECTED`: 支付模組供應商 (如銀行) 拒絕此訂單 (如系統維護中)

### 建立訂單

#### 範例程式

`pay_type` / `method` 兩個參數至少要傳一個

- `pay_type`: 指定客戶付款的方式 (如 `ATM` 或 `TOKEN`)
- `method`: 指定付款方式的編號 (請在後台 `我的支付方式` 頁面查詢編號)

```php
<?php
include_once('RichpayAPI.php');
$api = new RichpayAPI();

$result = $api->createOrder([
    // 以下必填
    "amount" => $price, // 訂單金額 (元
    "title"  => "測試標題", // 訂單標題
    "detail" => "XXXOOO", // 訂單明細
    "name"   => "測試客戶", // 客戶名稱
    "email"  => "guest@example.com", // 客戶 mail
    "phone"  => "0987654321", // 客戶電話
    
    "pay_type" => "ATM", // 繳款方式
    
    // 以下選填
    "due"             => '1234-12-23 12:34:56', // 繳款期限，請注意格式是 Y-m-d H:i:s
    "return_url"      => "http://xxx.ooo/return_url", // 導回網址 (留白使用預設
    "result_callback" => "http://xxx.ooo/result_url", // 付款結果通知網址 (留白使用預設
    "order_id"        => "ABCD1234", // 您自行產生的訂單編號 (留白使用匯富自動產生的編號
]);

if ($result['resp_status'] === 'OK') {
    // 有訂單資訊回傳，請使用 $result["order"]["status"] 確認訂單狀態
    var_dump($result['order']); // 訂單資訊
    var_dump($result['payment']); // 支付資訊

    // 此範例為 ATM 轉帳繳款
    echo sprintf(
        "請在 %s 之前到 ATM 轉帳 %d 元到%s(%s) 的帳號 %s 完成繳費",
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

#### 回傳的訂單資訊

```php
[
    "order: [
        "id" => 1234, // 訂單編號
        "status" => "CREATED" // 訂單狀態 (必定會是 CREATED)
        "amount" => 100.0000, // 訂單金額
        "token" => "", // 代碼 (依支付方式不同，有可能是空字串)
        "title" => "", // 您提供的訂單標題
        "detail" => "", // 您提供的訂單明細
        "due" => '1234-12-23 12:34:56', // 繳款期限
        "request_id" => 1234, // 支付通道編號
        "method_id" => 1234, // 支付方式的編號
    ],
    "payment" => [
        "type" => "ATM", // 支付資訊的類型
        "data" => [], // 相關的資料 (不同支付方式的資料也不同)
    ]
]
```

目前匯富支援以下兩種類型

```php
[
    "type" => "ATM", // ATM 轉帳
    "data" => [
        "bank_name" => "○○銀行", // 銀行名稱
        "bank_code" => "987", // 銀行轉帳代碼
        "account" => "1234", // 帳號
    ]
]
```

```php
[
    "type" => "TOKEN", // 代碼繳費
    "data" => [
        "token" => "abc123", // 繳費代碼
        "description" => "test", // 繳費方式說明
    ]
]
```

#### 回傳的錯誤訊息

```php
[
    "code" => "", // 錯誤代碼
    "detail" => "", // 錯誤訊息 (英文)
]
```

### 備註

正常使用 `RichpayAPI` 呼叫 API 時，內部會自動轉換，您不需要任何特別處理

但若您需要手動解析 json 資料時，請注意

- 金額類的資料 (如訂單金額) 都是以長整數格式來表示浮點數。若有必要自行解析 json 的時候請透過 `RichpayAPI::toAmount()` 轉換成正常的浮點數
- 時間類的資料都是以 unix timestamp 秒數傳遞。若有必要自行解析 json 的時候請透過 `RichpayAPI::toDate()` 轉換成 `DateTime` 物件
- 若有必要，`RichpayAPI` 也提供了 `updateOrder` 和 `updateResult` 讓您可以把 json 格式自動轉成 php 格式

