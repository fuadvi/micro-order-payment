<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentLog;
use Illuminate\Http\Request;

class WebhookController extends Controller
{

    function midtransHendler(Request $request)
    {
        $data = $request->all();

        $signaturkey = $data['signature_key'];

        $orderId = $data['order_id'];
        $statusCode = $data['status_code'];
        $grossAmount = $data['gross_amount'];
        $serveKey = env('MIDTRANS_SERVER_KEY');

        $mySignaturKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serveKey);

        $transactionStatus = $data['transaction_status'];
        $type = $data['payment_type'];
        $fraudStatus = $data['fraud_status'];

        if ($signaturkey !== $mySignaturKey) {
            return response()->json(
                [
                    'status' => 'error',
                    'massage' => 'Invalid Signatur Key'
                ],
                400
            );
        }

        $realOrderId = explode('-', $orderId);
        $order = Order::find($realOrderId[0]);

        if (!$order) {
            return response()->json(
                [
                    "status" => "error",
                    "massage" => 'order not found'
                ],
                404
            );
        }

        if ($order->status === "success") {
            return response()->json(
                [
                    'status' => 'error',
                    "massage" => "operation not permitted"
                ],
                405
            );
        }

        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'challenge') {
                $order->status = 'challenge';
            } else if ($fraudStatus == 'accept') {
                $order->status = 'success';
            }
        } else if ($transactionStatus == 'settlement') {
            $order->status = 'success';
        } else if (
            $transactionStatus == 'cancel' ||
            $transactionStatus == 'deny' ||
            $transactionStatus == 'expire'
        ) {
            $order->status = 'failure';
        } else if ($transactionStatus == 'pending') {
            $order->status = 'pending';
        }

        $logData = [
            'status' => $transactionStatus,
            'raw_response' => json_encode($data),
            'order_id' => $realOrderId[0],
            'payment_type' => $type
        ];

        PaymentLog::create($logData);

        $order->save();

        if ($order->status === 'success') {
            createPremiumAccess([
                'user_id' => $order->user_id,
                'course_id' => $order->course_id
            ]);
        }

        return response()->json('ok');
    }
}
