<?php

namespace App\Http\Controllers\Pay;

use Illuminate\Http\Request;
use App\Http\Controllers\PayController;

class TheadpayController extends PayController
{
    protected function getSDK($config) {
        // 实例化SDK
        return new THeadPaySDK([
            'theadpay_url'      => $config->merchant_key ? $config->merchant_key : 'https://jk.theadpay.com/v1/jk/orders',
            'theadpay_mchid'    => $config->merchant_id,
            'theadpay_key'      => $config->merchant_pem,
        ]);
    }

    public function gateway(string $payway, string $orderSN)
    {
        $this->loadGateWay($orderSN, $payway);
        $theadpay = $this->getSDK($this->payGateway);

        try {
            $res = $theadpay->pay([
                'trade_no'      => $this->order->order_sn,
                'total_fee'     => $this->order->actual_price * 100,            // 订单金额，单位为分
                'notify_url'    => url($this->payGateway->pay_handleroute . '/notify_url'),
                'return_url'    => url('detail-order-sn', ['orderSN' => $this->order->order_sn]),
            ]);
            return redirect()->away($res['redirect_url']);
        } catch (\Exception $e) {
            // 出错之后的处理
            $msg = __('dujiaoka.prompt.abnormal_payment_channel') . $e->getMessage();
            return $this->err($msg);
        }
    }

    public function notifyUrl(Request $request)
    {
        $data = $request->all();
        $order = $this->orderService->detailOrderSN($data['out_trade_no']);
        if (!$order) {
            return 'fail';
        }
        $payGateway = $this->payService->detail($order->pay_id);
        if (!$payGateway) {
            return 'fail';
        }

        $theadpay = $this->getSDK($payGateway);
        if ($theadpay->verify($data)) {
            $this->orderProcessService->completedOrder($data['out_trade_no'], $order->actual_price, $data['order_id']);
            return 'success';
        } else {
            // 数据未通过验证不可信
            die('fail');
        }
    }
}
