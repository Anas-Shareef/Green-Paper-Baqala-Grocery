<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WhatsAppMessage;

class WhatsAppOrderService
{
    /**
     * Build server-authoritative formatted WhatsApp message text
     */
    public function buildOrderMessage(Order $order): string
    {
        $itemsText = "";
        foreach ($order->items as $item) {
            $unitPriceFormatted = number_format((float) $item->unit_price, 2);
            $lineTotalFormatted = number_format((float) $item->total, 2);
            $itemsText .= "• {$item->product_name} × {$item->quantity} — ₹{$lineTotalFormatted}\n";
        }

        $subtotalFormatted = number_format((float) $order->subtotal, 2);
        $deliveryFormatted = (float) $order->delivery_charge > 0 
            ? "₹" . number_format((float) $order->delivery_charge, 2) 
            : "FREE";
        $totalFormatted = number_format((float) $order->total_amount, 2);

        $customerName = $order->customer_name_snapshot ?: ($order->customer->name ?? 'Valued Customer');
        $customerPhone = $order->customer_phone_snapshot ?: ($order->customer->phone ?? '');
        $villa = $order->customer_villa ?: 'Villa N/A';
        $address = $order->customer_address ?: 'Standard Villa Delivery';
        $notes = $order->customer_notes_snapshot ?: $order->notes ?: '';

        $msg = "🛒 *BAQQALA GROCERY ORDER*\n\n";
        $msg .= "Order Number: *{$order->order_number}*\n\n";
        $msg .= "👤 *Customer:* {$customerName}\n";
        $msg .= "📱 *Phone:* {$customerPhone}\n\n";
        $msg .= "📍 *Delivery Location:*\n{$villa}\n{$address}\n\n";
        $msg .= "🧺 *Order Items:*\n{$itemsText}\n";
        $msg .= "Subtotal: ₹{$subtotalFormatted}\n";
        $msg .= "Delivery: {$deliveryFormatted}\n";
        $msg .= "💰 *Total Amount:* ₹{$totalFormatted}\n";
        $msg .= "💵 *Payment Method:* " . strtoupper($order->payment_method) . " (COD)\n";

        if (!empty($notes)) {
            $msg .= "\n📝 *Delivery Notes:* {$notes}\n";
        }

        $msg .= "\nThank you for shopping with Baqqala Grocery!";

        return $msg;
    }

    /**
     * Generate WhatsApp Click-To-Chat URL & Record WhatsAppMessage Entry
     */
    public function generateClickToChat(Order $order, string $adminPhone = '971501112233'): array
    {
        $recipient = PhoneNumberService::formatForWhatsApp($adminPhone);
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
