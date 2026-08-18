<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Payment::query()->with([
            'user',
            'course:id,title,teacher_id',
        ]);

        if ($user->isStudent()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isTeacher()) {
            $query->whereHas('course', function ($courseQuery) use ($user) {
                $courseQuery->where('teacher_id', $user->id);
            });
        } elseif (! $user->isAdmin()) {
            abort(403, 'Forbidden.');
        }

        $payments = $query->latest()->paginate($request->input('per_page', 15));

        return PaymentResource::collection($payments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'sender_phone' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:255',
            'receipt' => 'nullable|file|mimetypes:image/jpeg,image/png,application/pdf|max:10240', // max 10MB
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts', 'public');
        }

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'course_id' => $request->course_id,
            'sender_phone' => $request->sender_phone,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'receipt_path' => $receiptPath,
            'status' => 'pending',
        ]);

        return new PaymentResource($payment->load(['user', 'course:id,title,teacher_id']));
    }

    public function approve(Request $request, Payment $payment)
    {
        $this->authorizePaymentProcessing($request, $payment);

        if ($payment->status !== 'pending') {
            return response()->json(['message' => 'This payment is already processed.'], 400);
        }

        $payment->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return new PaymentResource($payment->load(['user', 'course:id,title,teacher_id']));
    }

    public function reject(Request $request, Payment $payment)
    {
        $this->authorizePaymentProcessing($request, $payment);

        if ($payment->status !== 'pending') {
            return response()->json(['message' => 'This payment is already processed.'], 400);
        }

        $payment->update([
            'status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return new PaymentResource($payment->load(['user', 'course:id,title,teacher_id']));
    }

    private function authorizePaymentProcessing(Request $request, Payment $payment): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $user->isTeacher() && $payment->course?->teacher_id === $user->id,
            403,
            'You can only manage payments for your own courses.'
        );
    }
}
