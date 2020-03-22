# 睿聚科技 - 支付 API

本 API 文件採用 OAS3 格式撰寫，並提供 swagger-ui 供合作商家測試

- [主要文件](https://api-test.richpay.com.tw/doc/) (swagger-ui，可線上測試)
- [OpenAPI Spec](https://raw.githubusercontent.com/RichPay/api-libs/master/richpay-api.yaml)

另外亦於管理介面提供部份開發套件

- PHP [(正式環境)](https://bo.richpay.com.tw/client-libs/php) [(測試環境)](https://bo-test.richpay.com.tw/client-libs/php)

此外，貴公司串接 API 所需的資訊皆可從管理介面取得

- APIKey 與用戶編號 [(正式環境)](https://bo.richpay.com.tw/me) [(測試環境)](https://bo-test.richpay.com.tw/me)
- Method ID [(正式環境)](https://bo.richpay.com.tw/method/list_mine) [(測試環境)](https://bo-test.richpay.com.tw/method/list_mine)

# 範例 (虛擬碼)

#### 建立訂單

```
req = new HTTPRequest("https://api-test.richpay.com.tw/api/order/create")
req.SetHeader("X-Api-Key", "your_api_key")

payload = new JSONObject()
payload.Set("pay_type", "ATM") // 若欲使用超商繳費，請把 ATM 改成 TOKEN
payload.Set("title", "○○商城購物")
payload.Set("amount", amount * 10000)
payload.Set("buyer_name", "王大明")
payload.Set("buyer_email", "daming_wang@example.com")
payload.Set("buyer_contect", "0987654321")
payload.Set("callback", "https://example.com/callback/richpay")

req.Body = payload.ToString()
result = req.Send()

data = JSONObject::FromString(result.Body)

if len(data.errors) > 0 {
    // 有錯誤發生，處理
    handle_error()
    exit
}

order_id = data.data.order.id
// 寫入資料庫
save_to_db(data.data)
// 顯示轉帳資訊給客戶 (超商繳費的顯示方式會與此不同)
echo sprintf(
    "請使用 ATM 或網路銀行轉帳 %d 元至 %s(%s) %s",
    data.data.order.amount/10000,
    data.data.payment.bank_name,
    data.data.payment.bank_id,
    data.data.payment.account
)
```

#### 接收通知

```
request = HTTPServer::Accept()

notify = JSONObject::From(request.body)
a = (new JSONObject(notify.data)).SortByKey().ToString()
b = a + "you_api_key"
checksum_expect = MD5::Sum(b).ToUpper()
if checksum_expect != notify.checksum {
    // 驗證失敗，記錄下來並人工處理
    log(notify.ToString())
    exit
}

paid_time = Time::FromTimestamp(notify.data.paid_time)
paid_amount = notify.data.amount / 10000
set_order_as_paid(notify.data.order_id, paid_amount, paid_time)

// 返回 "0000" 告知睿聚接收成功，不需再發送此通知
echo "0000"
```

# TODO

以下 API 與支付功能並無直接關聯，尚待整理進此文件

- [ ] 訂單查詢
- [ ] 付款結果查詢
- [ ] 撥款記錄查詢
