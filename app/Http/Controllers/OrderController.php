<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user_id;
        $order = Order::query();

        $order->when($userId, function ($query) use ($userId) {
            $query->where('user_id', '=', $userId);
        });

        return response()->json(
            [
                'status' => 'success',
                'data' => $order->get()
            ]
        );
    }

    public function create(Request $request)
    {
        $user = $request->input('user');
        $course = $request->input('course');

        $order = Order::create([
            'user_id' => $user['id'],
            'course_id' => $course['id']
        ]);

        $transactionDetails = [
            "order_id" => $order['id'] . '-' . Str::random(5),
            "gross_amount" => $course['price']
        ];

        $itemDetails = [
            [
                'id' => $course['id'],
                'price' => $course['price'],
                'quantity' => 1,
                'name' => $course['name'],
                'brand' => 'siFun.id',
                'category' => 'Online Course'
            ]
        ];

        $costumerDetails = [
            'first_name' => $user['name'],
            'email' => $user['email']
        ];

        $midtransParams = [
            'transaction_details' => $transactionDetails,
            'item_details' => $itemDetails,
            'costumer_details' => $costumerDetails
        ];

        $midtransSnapUrl = $this->getMidtranSnapUrl($midtransParams);

        $order->snap_url = $midtransSnapUrl;

        $order->metadata = [
            "course_id" => $course['id'],
            "course_price" => $course['price'],
            "course_name" => $course['name'],
            "course_thumbnail" => $course['thumbnail'],
            "course_level" => $course['level']
        ];

        $order->save();

        return response()->json(
            [
                'status' => 'success',
                'data' => $order
            ]
        );
    }

    private function getMidtranSnapUrl($params)
    {
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = (bool) env('MIDTRANS_PRODUCTION');
        \Midtrans\Config::$is3ds = (bool) env('MIDTRANS_3DS');

        $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
        return $snapUrl;
    }
}
