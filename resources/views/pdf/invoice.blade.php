<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }} - Baqqala</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #1e293b;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #e2e8f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .brand {
            font-size: 26px;
            font-weight: 800;
            color: #166534;
            letter-spacing: 1px;
            margin: 0;
        }
        .tagline {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            vertical-align: top;
            padding: 4px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #f8fafc;
            color: #475569;
            text-align: left;
            padding: 8px 10px;
            font-size: 11px;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .text-right { text-align: right; }
        .totals-table {
            width: 40%;
            margin-left: auto;
            margin-bottom: 30px;
        }
        .totals-table td {
            padding: 6px 10px;
        }
        .grand-total {
            font-size: 16px;
            font-weight: bold;
            color: #166534;
            border-top: 2px solid #e2e8f0;
        }
        .footer {
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 40px;
            border-top: 1px solid #f1f5f9;
            padding-top: 15px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1 class="brand">BAQQALA</h1>
        <div class="tagline">Your Everyday Grocery, Delivered</div>
        <div style="font-size: 11px; color: #64748b; margin-top: 6px;">Fresh & Everyday Essentials Store</div>
    </div>

    <table class="info-table">
        <tr>
            <td style="width: 50%;">
                <strong>Customer Info:</strong><br>
                Name: {{ $order->customer?->name ?? 'Walk-in Customer' }}<br>
                Phone: {{ $order->customer?->phone ?? 'N/A' }}<br>
                Villa / Location: {{ $order->customer_villa ?? $order->customer?->villa_number ?? 'N/A' }}<br>
                Address: {{ $order->customer_address ?? 'N/A' }}
            </td>
            <td style="width: 50%; text-align: right;">
                <strong>Order Details:</strong><br>
                Order No: <strong>{{ $order->order_number }}</strong><br>
                Date: {{ $order->created_at->format('d M Y, h:i A') }}<br>
                Payment Method: <strong>{{ $order->payment_method }}</strong><br>
                Payment Status: <span style="text-transform: uppercase; color: {{ $order->payment_status === 'paid' ? '#166534' : '#d97706' }}; font-weight: bold;">{{ $order->payment_status }}</span>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Item Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $index => $item)
            <tr>
                <td style="width: 30px;">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->product_name }}</strong>
                </td>
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">₹{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">₹{{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Subtotal:</td>
            <td class="text-right">₹{{ number_format($order->subtotal, 2) }}</td>
        </tr>
        @if($order->discount_amount > 0)
        <tr>
            <td>Discount:</td>
            <td class="text-right">-₹{{ number_format($order->discount_amount, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td>Delivery Fee:</td>
            <td class="text-right">₹{{ number_format($order->delivery_charge, 2) }}</td>
        </tr>
        <tr class="grand-total">
            <td>Grand Total:</td>
            <td class="text-right">₹{{ number_format($order->total_amount, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        <p>Thank you for shopping with <strong>Baqqala</strong>!</p>
        <p>For support or delivery inquiries, contact us on WhatsApp.</p>
    </div>

</body>
</html>
