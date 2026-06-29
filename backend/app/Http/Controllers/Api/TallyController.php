<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;

class TallyController extends Controller
{
    public function export(Request $request)
    {
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);
        $cid = $request->user()->company_id;

        $sales = SalesInvoice::where('company_id', $cid)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('invoice_date', [$request->from, $request->to])
            ->with(['customer', 'items'])
            ->get();

        $purchases = PurchaseInvoice::where('company_id', $cid)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('invoice_date', [$request->from, $request->to])
            ->with(['supplier', 'items'])
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<ENVELOPE>' . "\n";
        $xml .= '  <HEADER><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER>' . "\n";
        $xml .= '  <BODY><IMPORTDATA><REQUESTDESC><REPORTNAME>All Masters</REPORTNAME></REQUESTDESC><REQUESTDATA>' . "\n";

        // Sales vouchers
        foreach ($sales as $inv) {
            $xml .= $this->salesVoucher($inv);
        }

        // Purchase vouchers
        foreach ($purchases as $inv) {
            $xml .= $this->purchaseVoucher($inv);
        }

        $xml .= '  </REQUESTDATA></IMPORTDATA></BODY>' . "\n";
        $xml .= '</ENVELOPE>';

        return response($xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="tally_export_' . $request->from . '_to_' . $request->to . '.xml"',
        ]);
    }

    private function salesVoucher(SalesInvoice $inv): string
    {
        $esc = fn($v) => htmlspecialchars((string) $v, ENT_XML1);
        $date = str_replace('-', '', $inv->invoice_date);

        $v  = "    <TALLYMESSAGE><VOUCHER VCHTYPE=\"Sales\" ACTION=\"Create\">\n";
        $v .= "      <DATE>{$date}</DATE>\n";
        $v .= "      <VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>\n";
        $v .= "      <VOUCHERNUMBER>{$esc($inv->invoice_number)}</VOUCHERNUMBER>\n";
        $v .= "      <PARTYLEDGERNAME>{$esc($inv->customer?->name)}</PARTYLEDGERNAME>\n";
        $v .= "      <NARRATION>{$esc($inv->notes ?? '')}</NARRATION>\n";

        foreach ($inv->items as $item) {
            $v .= "      <ALLINVENTORYENTRIES.LIST>\n";
            $v .= "        <STOCKITEMNAME>{$esc($item->product_name)}</STOCKITEMNAME>\n";
            $v .= "        <BILLEDQTY>{$item->quantity}</BILLEDQTY>\n";
            $v .= "        <ACTUALQTY>{$item->quantity}</ACTUALQTY>\n";
            $v .= "        <RATE>{$item->rate}</RATE>\n";
            $v .= "        <AMOUNT>-{$item->total_amount}</AMOUNT>\n";
            $v .= "      </ALLINVENTORYENTRIES.LIST>\n";
        }

        // GST ledgers
        if ($inv->cgst_amount > 0) {
            $v .= "      <ALLLEDGERENTRIES.LIST><LEDGERNAME>CGST</LEDGERNAME><AMOUNT>-{$inv->cgst_amount}</AMOUNT></ALLLEDGERENTRIES.LIST>\n";
            $v .= "      <ALLLEDGERENTRIES.LIST><LEDGERNAME>SGST</LEDGERNAME><AMOUNT>-{$inv->sgst_amount}</AMOUNT></ALLLEDGERENTRIES.LIST>\n";
        }
        if ($inv->igst_amount > 0) {
            $v .= "      <ALLLEDGERENTRIES.LIST><LEDGERNAME>IGST</LEDGERNAME><AMOUNT>-{$inv->igst_amount}</AMOUNT></ALLLEDGERENTRIES.LIST>\n";
        }

        $v .= "      <ALLLEDGERENTRIES.LIST><LEDGERNAME>{$esc($inv->customer?->name)}</LEDGERNAME><AMOUNT>{$inv->total_amount}</AMOUNT></ALLLEDGERENTRIES.LIST>\n";
        $v .= "    </VOUCHER></TALLYMESSAGE>\n";

        return $v;
    }

    private function purchaseVoucher(PurchaseInvoice $inv): string
    {
        $esc = fn($v) => htmlspecialchars((string) $v, ENT_XML1);
        $date = str_replace('-', '', $inv->invoice_date);

        $v  = "    <TALLYMESSAGE><VOUCHER VCHTYPE=\"Purchase\" ACTION=\"Create\">\n";
        $v .= "      <DATE>{$date}</DATE>\n";
        $v .= "      <VOUCHERTYPENAME>Purchase</VOUCHERTYPENAME>\n";
        $v .= "      <VOUCHERNUMBER>{$esc($inv->invoice_number)}</VOUCHERNUMBER>\n";
        $v .= "      <PARTYLEDGERNAME>{$esc($inv->supplier?->name)}</PARTYLEDGERNAME>\n";

        foreach ($inv->items as $item) {
            $v .= "      <ALLINVENTORYENTRIES.LIST>\n";
            $v .= "        <STOCKITEMNAME>{$esc($item->product_name)}</STOCKITEMNAME>\n";
            $v .= "        <BILLEDQTY>{$item->quantity}</BILLEDQTY>\n";
            $v .= "        <ACTUALQTY>{$item->quantity}</ACTUALQTY>\n";
            $v .= "        <RATE>{$item->rate}</RATE>\n";
            $v .= "        <AMOUNT>{$item->total_amount}</AMOUNT>\n";
            $v .= "      </ALLINVENTORYENTRIES.LIST>\n";
        }

        if ($inv->cgst_amount > 0) {
            $v .= "      <ALLLEDGERENTRIES.LIST><LEDGERNAME>CGST</LEDGERNAME><AMOUNT>{$inv->cgst_amount}</AMOUNT></ALLLEDGERENTRIES.LIST>\n";
            $v .= "      <ALLLEDGERENTRIES.LIST><LEDGERNAME>SGST</LEDGERNAME><AMOUNT>{$inv->sgst_amount}</AMOUNT></ALLLEDGERENTRIES.LIST>\n";
        }
        if ($inv->igst_amount > 0) {
            $v .= "      <ALLLEDGERENTRIES.LIST><LEDGERNAME>IGST</LEDGERNAME><AMOUNT>{$inv->igst_amount}</AMOUNT></ALLLEDGERENTRIES.LIST>\n";
        }

        $v .= "      <ALLLEDGERENTRIES.LIST><LEDGERNAME>{$esc($inv->supplier?->name)}</LEDGERNAME><AMOUNT>-{$inv->total_amount}</AMOUNT></ALLLEDGERENTRIES.LIST>\n";
        $v .= "    </VOUCHER></TALLYMESSAGE>\n";

        return $v;
    }
}
