<?php


class RichpayAPI
{
    const SITE = '{{SITE}}'; // 睿聚提供的 API 主機位址
    const KEY = '{{KEY}}'; // 睿聚提供的 API KEY
    const UID = '{{UID}}'; // 貴公司的用戶編號
    // 您可以從管理介面下載此程式，系統將會為您填好以上三個必要參數，方便您使用

    public function checkHashsum($arr) {
        // 檢查 hashsum
        $data = json_encode($arr['data']);
        $expect = $arr['checksum'];
        $str = $data.",".static::UID;
        $actual = strtolower(md5($str));
        return $expect == $actual;
    }

    /**
     * 接收付款通知，並轉成方便 PHP 使用的格式
     *
     * @param $str string 睿聚傳來的 post body，一般使用 file_get_contents('php://input') 取得
     * @return 付款通知，詳見 Readme
     */
    public function acceptNotify($str)
    {
        $ret = json_decode($str, true);
        if ($ret === null) {
            return [
                'error' => [
                    'detail' => 'MALFORMED DATA',
                    'code' => 'E999-DATA',
                ],
            ];
        }

        if (! $this->checkHashsum($ret)) {
            $ret['error'] = [
                'detail' => 'CHECKSUM MISMATCH',
                'code' => 'E999-CHCK',
            ];
            return $ret;
        }

        if (isset($ret['data'])) {
            self::updateAmount($ret['data'], 'amount');
            self::updateDate($ret['data'], 'paid_time');
            self::updateDate($ret['data'], 'order_create_at');
        }

        return $ret;
    }

    private function callapi($endpoint, $param)
    {
        $data = json_encode($param);
        $h = curl_init(static::SITE . $endpoint);
        curl_setopt($h, \CURLOPT_POST, true);
        curl_setopt($h, \CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($h, \CURLOPT_POSTFIELDS, $data);
        curl_setopt($h, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($h, \CURLOPT_HTTPHEADER, array(
            'X-Api-Key: ' . static::KEY,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data))
        );

        curl_exec($h);
        $result = curl_multi_getcontent($h);
        curl_close($h);

        $res = json_decode($result, true);

        $ret = [null, null];
        if (isset($res["data"])) {
            $ret[0] = $res["data"];
        }

        if (isset($res["errors"])) {
            $ret[1] = $res["errors"][0];
        }

        return $ret;
    }

    // 處理 order 格式轉換
    public function updateOrder(&$data)
    {
        self::updateAmount($data, 'amount');
        self::updateDate($data, 'due');
        self::updateAmount($data['request'], 'min_amount');
        self::updateAmount($data['request'], 'max_amount');
        self::updateAmount($data['method'], 'min_amount');
        self::updateAmount($data['method'], 'max_amount');
    }

    // 處理 result 格式轉換
    public function updateResult(&$data)
    {
        self::updateAmount($data, 'amount');
        self::updateDate($data, 'time');

        if (isset($data['order'])) {
            updateOrder($data['order']);
        }

        // 處理狀態轉換
        switch ($data['status']) {
            case 'CREATED':
                $data['status'] = 'PENDING';
                break;
            case 'PAID':
            case 'TANSFERRED':
                $data['status'] = 'PAID';
                break;
            case 'CONFIRMED':
                break;
            default:
                $data['status'] = 'FAILED';
                break;
        }
    }

    /**
     * 把 json 格式的付款結果轉成 php 格式
     */
    public function parseResult($content)
    {
        $ret = json_decode($content, true);
        if (isset($ret['id'])) {
            $this->updateResult($ret);
        }

        return $ret;
    }

    /**
     * 把 API 使用的時間格式 (unix timestamp) 轉成 DateTime 物件
     */
    public static function toDate($ts)
    {
        $ret = new DateTime('@' . $ts);
        $ret->setTimezone(new DateTimeZone('Asia/Taipei'));
        return $ret;
    }

    private static function updateDate(&$arr, $key)
    {
        if (!isset($arr[$key])) {
            return;
        }
        $arr[$key] = self::toDate($arr[$key])->format('Y-m-d H:i:s');
    }

    /**
     * 把 API 使用的金額格式 (小數點後四位) 轉成浮點數格式
     */
    public static function toAmount($num)
    {
        return $num/10000.0;
    }

    private static function updateAmount(&$arr, $key)
    {
        if (!isset($arr[$key])) {
            return;
        }
        $arr[$key] = self::toAmount($arr[$key]);
    }

    /**
     * 把浮點數格式轉成 API 使用的金額格式 (小數點後四位)
     *
     * 一般使用此 API 物件的不會用到這個函式
     */
    public static function fromAmount($num)
    {
        return floor($num*10000.0);
    }

    private function paramerr($name)
    {
        return [
            'resp_status' => 'ERROR',
            'error' => [
                'code' => 'E418-9998',
                'detail' => "MISSING PARAMETER: ".$name,
            ],
        ];
    }

    private function muststr($opt, $name)
    {
        if (!isset($opt[$name]) or !$opt[$name]) {
            return paramerr($name);
        }
    }

    /**
     * 產生一筆新的訂單
     *
     * @param $amount int|float 金額 (以元為單位)
     * @param $title string 訂單標題
     * @param $detail string 訂單說明
     * @param $name string 客戶名稱
     * @param $email string 客戶電郵
     * @param $phone string 客戶電話
     * @param $due DateTime|string 繳款期限(實際繳款期限可能因供應商不同而與您設定的稍有出入，請以回傳的時間為準) (null 表示以系統預設時間為準)
     * @param $pay_type string 收款方式 (目前只支援 "ATM" 和 "TOKEN" 兩種)
     * @param $request int 支付通道的編號 (請從貴司後台取得)
     * @param $method int 支付方式的編號 (請貴司從後台取得，設為 null 會由系統自動選擇可用的支付方式)
     * @param $result_callback string 匯富回傳支付結果的網址，空白代表使用您從後台設定的值
     * @param $return_url string 特定支付方式 (如信用卡)，匯富在客戶付款完成後，將客戶導回此網址
     * @param $order_id string 廠商的訂單編號，留白將由系統自動產生
     *
     * @return array 訂單資料
     */
    public function createOrder($opt)
    {
        // 檢查必要的參數
        $amount = $opt["amount"] * 1.0;
        if ($amount <= 0) {
            return $this->paramerr("amount");
        }
        foreach (["title","name","email","phone"] as $k => $v) {
            $ret = $this->muststr($opt, $v);
            if (is_array($ret)) {
                return $ret;
            }
        }
        // request/method/pay_type 三個至少要選一個傳
        $req = null;
        $met = null;
        $pay = null;
        if (isset($opt['request'])) {
            $req = $opt['request'];
        }
        if (isset($opt['method'])) {
            $met = $opt['method'];
        }
        if (isset($opt['pay_type']) and $opt['pay_type']) {
            $pay = $opt['pay_type'];
        }
        if ($req === null and $met === null and $pay === null) {
            return $this->paramerr("pay_type OR request OR method");
        }

        $due = null;
        $regexp = '/[0-9]{4}-[0-9]{1,2}-[0-9]{1,2} [0-9]{1,2}:[0-9]{1,2}:[0-9]{1,2}/';
        if (isset($opt['due'])) {
            if (! $opt['due'] instanceof DateTime and preg_match($regexp, $opt['due']) == 1) {
                $x = new DateTime($opt['due'], new DateTimeZone('Asia/Taipei'));
                if (!$x) {
                    return $this->paramerr("due");
                }
                $opt['due'] = $x;
            }
            $due = $opt['due'];
        }
        // 檢查 due 的值是否合理
        if ($due instanceof DateTime) {
            // 有值就檢查
            $tnow = new DateTime();
            if ($tnow->getTimestamp() >= $due->getTimestamp()) {
                // 現在已超過繳款期限，不合理
                return $this->paramerr("due");
            }
        }

        $retcb = (isset($opt["return_url"]))?$opt["return_url"]:"";
        $rescb = (isset($opt["result_callback"]))?$opt["result_callback"]:"";
        $oid = (isset($opt["order_id"]))?$opt["order_id"]:"";

        return $this->doCreateOrder(
            $amount,
            $opt['title'],
            $opt['detail'],
            $opt['name'],
            $opt['email'],
            $opt['phone'],
            $due,
            $pay,
            $req,
            $met,
            $rescb,
            $retcb,
            $oid
        );
    }

    private function doCreateOrder($amount, $title, $detail, $name, $mail, $phone, $due = null, $pay_type = null, $req_id = null, $method = null, $rescb = "", $retcb = "", $oid = "")
    {
        $param = [
            'amount' => self::fromAmount($amount),
            'title' => (string)$title,
            'detail' => (string)$detail,
            "buyer_name" => (string)$name,
            "buyer_email" => (string)$mail,
            "buyer_contact" => (string)$phone,
            "store_order_id" => (string)$oid,
            "result_callback" => (string)$rescb,
            "return_callback" => (string)$retcb,
        ];
        if ($due instanceof DateTime) {
            $param["due"] = (float)$due->getTimestamp();
        }
        if (!$pay_type and !$req_id) {
            return [
                'resp_status' => 'ERROR',
                'error' => [
                    "code" => "",
                    "detail" => 'MUST SET $pay_type or $req_id'
                ],
            ];
        }

        if (!!$pay_type) {
            $param["pay_type"] = (string)$pay_type;
        }
        if (!!$req_id) {
            $param['request_id'] = (string)$req_id;
        }
        if (!!$method) {
            $param['method_id'] = (string)$method;
        }

        list($data, $err) = $this->callapi('/api/order/create', $param);
        $status = 'OK';
        if ($err === null) {
            if ($data === null) {
                // 完全沒取到回傳值
                return [
                    'resp_status' => 'ERROR',
                    'error' => [
                        'code' => 'E500-9801',
                        'detail' => 'NO RESPONSE FROM RICHPAY',
                    ],
                ];
            }
            $this->updateOrder($data['order']);
            if ($data['order']['status'] != 'CONFIRMED') {
                $msgs = [
                    'CREATED' => 'FAILED TO SAVE ORDER, TRY AGAIN LATER',
                    'CANCELED' => 'FAILED TO SAVE ORDER, TRY AGAIN LATER',
                    'REJECTED' => 'ORDER IS REJECT BY THIS METHOD, CHECK YOUR PARAMETER AGAIN',
                ];
                return [
                    'resp_status' => 'ERROR',
                    'error' => [
                        'code' => 'E418-9999',
                        'detail' => $data,
                    ],
                ];
            }
            $data['resp_status'] = 'OK';
            return $data;
        }

        return array(
            'resp_status' => 'ERROR',
            'error' => $err
        );
    }

    /**
     * 取得某筆訂單的支付資訊
     */
    public function getPaymentInfo($order_id)
    {
        list($data, $err) = $this->callapi('/api/order/payment', $order_id);
        if (!$err) {
            return $data;
        }

        return null;
    }
}
