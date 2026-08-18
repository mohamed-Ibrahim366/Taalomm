<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'course' => $this->whenLoaded('course', function () {
                return [
                    'id' => $this->course->id,
                    'title' => $this->course->title,
                ];
            }),
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'sender_phone' => $this->sender_phone,
            'status' => $this->status,
            'receipt_path' => $this->receipt_path ? asset('storage/'.$this->receipt_path) : null,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
        ];
    }
}
