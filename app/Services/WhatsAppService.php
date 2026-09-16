<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Send order confirmation notification.
     */
    public function sendOrderConfirmation(Order $order): WhatsAppMessage
    {
        $customer = $order->customer;
        $recipient = $customer?->whatsapp_number ?? $customer?->phone ?? '971500000000';

        $body = "Baqqala — Order Confirmation\n\n"
              . "Your order #{$order->order_number} has been received successfully.\n\n"
              . "Total: ₹{$order->total_amount}\n"
              . "Payment: {$order->payment_method}\n"
              . "Villa: " . ($order->customer_villa ?? 'N/A') . "\n\n"
              . "Thank you for shopping with Baqqala. Your Everyday Grocery, Delivered.";

        return $this->sendMessage([
            'customer_id' => $customer?->id,
            'order_id' => $order->id,
            'message_type' => 'order_confirmation',
            'recipient' => $recipient,
            'message_body' => $body,
        ]);
    }

    /**
     * Send order status update (Accepted, Out for Delivery, Delivered).
     */
    public function sendOrderStatusUpdate(Order $order, string $status): WhatsAppMessage
    {
        $customer = $order->customer;
        $recipient = $customer?->whatsapp_number ?? $customer?->phone ?? '971500000000';

        $statusText = match ($status) {
            'accepted' => "is accepted and is being prepared.",
            'preparing' => "is currently being packed with fresh grocery items.",
            'out_for_delivery' => "is out for delivery and will arrive shortly!",
            'delivered' => "has been delivered! Thank you for shopping with Baqqala.",
            'cancelled' => "has been cancelled. Reason: " . ($order->cancel_reason ?? 'Customer request'),
            default => "status updated to {$status}."
        };

        $body = "Baqqala — Order Update\n\n"
              . "Order #{$order->order_number} {$statusText}\n"
              . "Total: ₹{$order->total_amount}";

        return $this->sendMessage([
            'customer_id' => $customer?->id,
            'order_id' => $order->id,
            'message_type' => "order_{$status}",
            'recipient' => $recipient,
            'message_body' => $body,
        ]);
    }

    /**
     * Send a general text message or marketing message via Meta API / log fallback.
     */
    public function sendMessage(array $data): WhatsAppMessage
    {
        $token = BusinessSetting::get('whatsapp_access_token');
        $phoneId = BusinessSetting::get('whatsapp_phone_number_id');
        $recipient = preg_replace('/[^0-9]/', '', $data['recipient']);

        $messageRecord = WhatsAppMessage::create([
            'customer_id' => $data['customer_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'message_type' => $data['message_type'] ?? 'transactional',
            'recipient' => $recipient,
            'message_body' => $data['message_body'] ?? '',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        if ($token && $phoneId) {
            try {
                $response = Http::withToken($token)->post("https://graph.facebook.com/v18.0/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipient,
                    'type' => 'text',
                    'text' => [
                        'body' => $data['message_body']
                    ]
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $msgId = $json['messages'][0]['id'] ?? null;
                    $messageRecord->update([
                        'status' => 'delivered',
                        'provider_message_id' => $msgId,
                        'delivered_at' => now(),
                    ]);
                } else {
                    $messageRecord->update([
                        'status' => 'failed',
                        'error_message' => $response->body(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("WhatsApp API Error: " . $e->getMessage());
                $messageRecord->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        } else {
            // Simulated delivery mode for local testing
            $messageRecord->update([
                'status' => 'delivered',
                'provider_message_id' => 'sim_' . uniqid(),
                'delivered_at' => now(),
            ]);
        }

        return $messageRecord;
    }
}
