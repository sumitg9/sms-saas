<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CollectPaymentRequest;
use App\Http\Requests\Finance\FeeRefundRequest;
use App\Http\Requests\Finance\RazorpayOrderRequest;
use App\Http\Requests\Finance\RazorpayVerifyRequest;
use App\Services\Finance\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['student_id', 'payment_method', 'status', 'date']);
        $payments = $this->paymentService->listPayments($filters);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $payment = $this->paymentService->getPayment($id);

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }

    public function collect(CollectPaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->collectPayment(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment collected and receipt generated successfully.',
            'data' => $payment,
        ], 201);
    }

    public function initiateRazorpay(RazorpayOrderRequest $request): JsonResponse
    {
        $orderData = $this->paymentService->initiateRazorpayOrder($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Razorpay order initiated successfully.',
            'data' => $orderData,
        ]);
    }

    public function verifyRazorpay(RazorpayVerifyRequest $request): JsonResponse
    {
        $payment = $this->paymentService->verifyRazorpayPayment(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment verified and credited successfully.',
            'data' => $payment,
        ]);
    }

    public function refund(FeeRefundRequest $request, string $paymentId): JsonResponse
    {
        $refund = $this->paymentService->processRefund(
            $paymentId,
            (float) $request->input('amount'),
            $request->input('reason'),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund processed successfully.',
            'data' => $refund,
        ], 201);
    }
}

