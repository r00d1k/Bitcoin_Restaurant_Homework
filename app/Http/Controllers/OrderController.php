<?php

namespace App\Http\Controllers;

use App\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $orders = Order::opened()->paginate();
        return view('order.index', ['orders' => $orders]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $id = \DB::table('orders')->max('id');
        return view('order.create', [
            'order_number' => ++$id,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'order_number' => 'required|alpha_num|unique:orders,order_number',
            'amount' => 'required|numeric',
            'description' => 'max:200',
        ]);

        $inputData = $request->all();

        $address = \Helper::getBTCAddress();
        $btcAmount = \Helper::getUAHToBTC($inputData['amount']);

        if ($address && $btcAmount) {
            $order = Order::create([
                'address' => $address,
                'order_number' => $inputData['order_number'],
                'amount_uah' => $inputData['amount'],
                'amount_btc' => $btcAmount,
                'status' => Order::STATUS_NEW,
                'description' => $inputData['description'],
            ]);

            $data = [
                'order' => $order,
                'QRCode' => \Helper::getQRCode($address, $order->amount_btc, $order->order_number, $order->description),
            ];
            return view('order.qr', $data);
        } else {
            return view('error', ['message' => 'Try again later.']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Order $order
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {
        return view('order.show', ['order' => $order]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Order $order
     * @return \Illuminate\Http\Response
     */
    public function edit(Order $order)
    {
        return view('order.edit', ['order' => $order]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Order $order
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Order $order)
    {
        $data = [
            'order' => $order,
            'QRCode' => \Helper::getQRCode($order->address, $order->getUnpaidAmountBTC(), $order->order_number, $order->description),
        ];
        return view('order.edit', $data);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Order $order
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order)
    {
        if ($order->status == Order::STATUS_NEW) {
            $order->status = Order::STATUS_HISTORY_CANCELLED;
        } else if(in_array($order->status, [Order::STATUS_UNCONFIRMED_WRONG, Order::STATUS_CONFIRMED_WRONG])) {
            $order->status = Order::STATUS_HISTORY_WRONG;
        } else if(in_array($order->status, [Order::STATUS_UNCONFIRMED_OK, Order::STATUS_CONFIRMED_OK])) {
            $order->status = Order::STATUS_HISTORY_OK;
        }
        $order->save();
        return redirect()->route('orders.index');
    }

    public function history() {
        $orders = Order::history()->paginate();
        return view('order.history', ['orders' => $orders]);
    }
}
