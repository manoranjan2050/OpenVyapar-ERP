<?php

namespace App\Services;

class GstService
{
    // Indian state codes for GSTIN-based intra/inter detection
    private const STATE_CODES = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab',
        '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana',
        '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
        '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram',
        '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam',
        '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha',
        '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli', '27' => 'Maharashtra', '28' => 'Andhra Pradesh',
        '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep',
        '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar', '36' => 'Telangana', '37' => 'Andhra Pradesh (New)',
    ];

    public function isIntraState(string $sellerGstin, string $buyerGstin): bool
    {
        return substr($sellerGstin, 0, 2) === substr($buyerGstin, 0, 2);
    }

    public function calculateTax(float $taxableAmount, float $gstRate, bool $isIntra): array
    {
        $totalTax = round($taxableAmount * $gstRate / 100, 2);

        if ($isIntra) {
            $half = round($totalTax / 2, 2);
            return [
                'cgst_rate' => $gstRate / 2,
                'sgst_rate' => $gstRate / 2,
                'igst_rate' => 0,
                'cgst_amount' => $half,
                'sgst_amount' => $totalTax - $half,
                'igst_amount' => 0,
                'total_tax' => $totalTax,
            ];
        }

        return [
            'cgst_rate' => 0,
            'sgst_rate' => 0,
            'igst_rate' => $gstRate,
            'cgst_amount' => 0,
            'sgst_amount' => 0,
            'igst_amount' => $totalTax,
            'total_tax' => $totalTax,
        ];
    }

    public function calculateInvoiceTotals(array $items, bool $isIntra): array
    {
        $subtotal = 0;
        $discountAmount = 0;
        $taxableAmount = 0;
        $cgst = 0;
        $sgst = 0;
        $igst = 0;
        $processedItems = [];

        foreach ($items as $item) {
            $lineSubtotal = round($item['quantity'] * $item['rate'], 2);
            $lineDiscount = round($lineSubtotal * ($item['discount_pct'] ?? 0) / 100, 2);
            $lineTaxable = $lineSubtotal - $lineDiscount;
            $tax = $this->calculateTax($lineTaxable, $item['gst_rate'], $isIntra);

            $subtotal += $lineSubtotal;
            $discountAmount += $lineDiscount;
            $taxableAmount += $lineTaxable;
            $cgst += $tax['cgst_amount'];
            $sgst += $tax['sgst_amount'];
            $igst += $tax['igst_amount'];

            $processedItems[] = array_merge($item, [
                'discount_amount' => $lineDiscount,
                'taxable_amount' => $lineTaxable,
                'cgst_rate' => $tax['cgst_rate'],
                'sgst_rate' => $tax['sgst_rate'],
                'igst_rate' => $tax['igst_rate'],
                'cgst_amount' => $tax['cgst_amount'],
                'sgst_amount' => $tax['sgst_amount'],
                'igst_amount' => $tax['igst_amount'],
                'total_amount' => round($lineTaxable + $tax['total_tax'], 2),
            ]);
        }

        $grandTotal = $taxableAmount + $cgst + $sgst + $igst;
        $roundOff = round(round($grandTotal) - $grandTotal, 2);

        return [
            'items' => $processedItems,
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'taxable_amount' => round($taxableAmount, 2),
            'cgst_amount' => round($cgst, 2),
            'sgst_amount' => round($sgst, 2),
            'igst_amount' => round($igst, 2),
            'round_off' => $roundOff,
            'total_amount' => round($grandTotal) + $roundOff,
        ];
    }

    public function validateGstin(string $gstin): bool
    {
        return (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', strtoupper($gstin));
    }

    public function stateFromGstin(string $gstin): ?string
    {
        return self::STATE_CODES[substr($gstin, 0, 2)] ?? null;
    }
}
