<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfInvoiceService
{
    /**
     * Generate DOMPDF instance for an order invoice receipt.
     */
    public function generateInvoicePdf(Order $order)
    {
        $order->load(['items', 'customer']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'order' => $order,
        ])->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Download invoice receipt response.
     */
    public function downloadInvoice(Order $order): Response
    {
        $pdf = $this->generateInvoicePdf($order);
        return $pdf->download("Baqqala-Invoice-{$order->order_number}.pdf");
    }

    /**
     * Stream invoice inline in browser.
     */
    public function streamInvoice(Order $order)
    {
        $pdf = $this->generateInvoicePdf($order);
        return $pdf->stream("Baqqala-Invoice-{$order->order_number}.pdf");
    }
}
