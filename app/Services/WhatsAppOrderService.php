<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WhatsAppMessage;

class WhatsAppOrderService
{
    /**
     * Build server-authoritative formatted WhatsApp message text (PRD Section 21)
     */
    public function buildOrderMessage(Order $order): string
    {
        $itemsText = "";
        foreach ($order->items as $item) {
            $lineTotalFormatted = number_format((float) $item->total, 2);
            $itemsText .= "• {$item->product_name} × {$item->quantity}\n";
        }

        $subtotalFormatted = number_format((float) $order->subtotal, 2);
        $deliveryFormatted = (float) $order->delivery_charge > 0 
            ? "AED " . number_format((float) $order->delivery_charge, 2) 
            : "FREE";
        $totalFormatted = number_format((float) $order->total_amount, 2);

        $customerOrderNum = $order->customer_order_number ?: 1;
        $customerName = $order->customer_name_snapshot ?: ($order->customer->name ?? 'Guest Customer');
        $customerPhone = PhoneNumberService::formatForWhatsApp($order->customer_phone_snapshot ?: ($order->customer->phone ?? ''));
        
        $canonicalAddress = PhoneNumberService::formatCanonicalAddress($order->customer_villa, $order->customer_address);
        $notes = $order->customer_notes_snapshot ?: $order->notes ?: '';

        $msg = "Hello Baqqala,\n\n";
        $msg .= "I would like to place an order.\n\n";
        $msg .= "Customer Order: #{$customerOrderNum}\n\n";
        $msg .= "Customer:\n{$customerName}\n{$customerPhone}\n\n";
        $msg .= "Delivery Address:\n{$canonicalAddress}\n";
        if (!empty($notes)) {
            $msg .= "Notes: {$notes}\n";
        }
        $msg .= "\nItems:\n{$itemsText}\n";
        $msg .= "Subtotal: AED {$subtotalFormatted}\n";
        $msg .= "Delivery: {$deliveryFormatted}\n";
        $msg .= "Total: AED {$totalFormatted}\n\n";
        $msg .= "Payment Method:\nCash on Delivery\n\n";
        $msg .= "Please confirm my order.\n\n";
        $msg .= "Thank you.";

        return $msg;
    }

    /**
     * Generate WhatsApp Click-To-Chat URL & Record WhatsAppMessage Entry
     */
    public function generateClickToChat(Order $order, ?string $adminPhone = null): array
    {
        $targetPhone = $adminPhone ?: config('app.whatsapp_admin_number', env('WHATSAPP_ADMIN_NUMBER', '971501112233'));
        $recipient = PhoneNumberService::formatForWhatsApp($targetPhone);
        $messageBody = $this->buildOrderMessage($order);
        $encodedMessage = rawurlencode($messageBody);
        $whatsappUrl = "https://wa.me/{$recipient}?text={$encodedMessage}";

        // Store or update WhatsAppMessage record
        $waRecord = WhatsAppMessage::updateOrCreate(
            [
                'order_id' => $order->id,
                'message_type' => 'order_confirmation',
            ],
            [
                'customer_id' => $order->customer_id,
                'recipient' => $recipient,
                'message_body' => $messageBody,
                'status' => 'prepared',
                'sent_at' => now(),
            ]
        );

        return [
            'whatsapp_url' => $whatsappUrl,
            'message_body' => $messageBody,
            'recipient' => $recipient,
            'status' => 'prepared',
            'record_id' => $waRecord->id,
        ];
    }
}
