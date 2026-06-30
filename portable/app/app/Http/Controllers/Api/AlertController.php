<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class AlertController extends Controller
{
    // GET/POST notification settings
    public function settings(Request $request)
    {
        $cid = $request->user()->company_id;
        $settings = DB::table('notification_settings')->where('company_id', $cid)->first();
        return response()->json($settings ?? (object)[]);
    }

    public function saveSettings(Request $request)
    {
        $cid = $request->user()->company_id;
        $data = $request->validate([
            'telegram_bot_token' => 'nullable|string',
            'telegram_chat_id'   => 'nullable|string',
            'telegram_enabled'   => 'boolean',
            'alert_email'        => 'nullable|email',
            'email_enabled'      => 'boolean',
            'smtp_host'          => 'nullable|string',
            'smtp_port'          => 'nullable|integer',
            'smtp_username'      => 'nullable|string',
            'smtp_password'      => 'nullable|string',
            'smtp_from_name'     => 'nullable|string',
        ]);

        $existing = DB::table('notification_settings')->where('company_id', $cid)->first();
        if ($existing) {
            DB::table('notification_settings')->where('company_id', $cid)->update(array_merge($data, ['updated_at' => now()]));
        } else {
            DB::table('notification_settings')->insert(array_merge($data, ['company_id' => $cid, 'created_at' => now(), 'updated_at' => now()]));
        }
        return response()->json(['message' => 'Settings saved.']);
    }

    // Alert rules
    public function rules(Request $request)
    {
        $cid = $request->user()->company_id;
        return response()->json(DB::table('alert_rules')->where('company_id', $cid)->orderBy('id')->get());
    }

    public function saveRule(Request $request)
    {
        $cid = $request->user()->company_id;
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'event'        => 'required|string|in:low_stock,overdue_payment,daily_summary,new_sale,new_purchase,backup_done,credit_note,new_user',
            'conditions'   => 'nullable|array',
            'via_telegram' => 'boolean',
            'via_email'    => 'boolean',
            'is_active'    => 'boolean',
        ]);
        $data['company_id']  = $cid;
        $data['conditions']  = json_encode($data['conditions'] ?? []);
        $data['created_at']  = now();
        $data['updated_at']  = now();
        $id = DB::table('alert_rules')->insertGetId($data);
        return response()->json(DB::table('alert_rules')->find($id), 201);
    }

    public function updateRule(Request $request, $id)
    {
        $cid = $request->user()->company_id;
        $data = $request->only(['name', 'event', 'conditions', 'via_telegram', 'via_email', 'is_active']);
        if (isset($data['conditions'])) $data['conditions'] = json_encode($data['conditions']);
        $data['updated_at'] = now();
        DB::table('alert_rules')->where('company_id', $cid)->where('id', $id)->update($data);
        return response()->json(DB::table('alert_rules')->find($id));
    }

    public function deleteRule(Request $request, $id)
    {
        $cid = $request->user()->company_id;
        DB::table('alert_rules')->where('company_id', $cid)->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // Test alert
    public function test(Request $request)
    {
        $cid     = $request->user()->company_id;
        $channel = $request->input('channel', 'telegram'); // telegram | email
        $settings = DB::table('notification_settings')->where('company_id', $cid)->first();

        if (!$settings) return response()->json(['message' => 'No settings configured.'], 422);

        $msg = "✅ *OpenVyapar ERP Test Alert*\n\nYour alert channel is working correctly!\nCompany: {$request->user()->company?->name}\nTime: " . now()->format('d M Y H:i');

        if ($channel === 'telegram') {
            return $this->sendTelegram($settings, $msg);
        }
        return $this->sendEmail($settings, 'Test Alert — OpenVyapar ERP', strip_tags(str_replace('*', '', $msg)));
    }

    // Dispatch alert (called from other controllers)
    public static function dispatch(int $companyId, string $event, string $message): void
    {
        $settings = DB::table('notification_settings')->where('company_id', $companyId)->first();
        if (!$settings) return;

        $rules = DB::table('alert_rules')
            ->where('company_id', $companyId)
            ->where('event', $event)
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            if ($rule->via_telegram && $settings->telegram_enabled) {
                (new self)->sendTelegramRaw($settings, $message);
            }
            if ($rule->via_email && $settings->email_enabled && $settings->alert_email) {
                (new self)->sendEmailRaw($settings, "OpenVyapar Alert: {$event}", $message);
            }
        }
    }

    // Run stock check and alert
    public function runStockCheck(Request $request)
    {
        $cid      = $request->user()->company_id;
        $settings = DB::table('notification_settings')->where('company_id', $cid)->first();
        if (!$settings) return response()->json(['message' => 'Configure notification settings first.'], 422);

        $lowStock = Product::where('company_id', $cid)
            ->where('track_inventory', true)
            ->whereRaw('opening_stock <= low_stock_alert')
            ->get(['name', 'sku', 'opening_stock', 'low_stock_alert']);

        if ($lowStock->isEmpty()) return response()->json(['message' => 'No low stock items. All good!', 'count' => 0]);

        $lines = $lowStock->map(fn($p) => "⚠️ *{$p->name}* ({$p->sku}): {$p->opening_stock} left (min: {$p->low_stock_alert})")->join("\n");
        $msg = "🔴 *Low Stock Alert — OpenVyapar ERP*\n\n{$lines}\n\nTotal: {$lowStock->count()} items need restocking.";

        if ($settings->telegram_enabled) $this->sendTelegramRaw($settings, $msg);
        if ($settings->email_enabled && $settings->alert_email) {
            $this->sendEmailRaw($settings, "Low Stock Alert — {$lowStock->count()} items", strip_tags(str_replace(['*', '_'], '', $msg)));
        }

        return response()->json(['message' => "Alert sent for {$lowStock->count()} low stock items.", 'count' => $lowStock->count(), 'items' => $lowStock]);
    }

    // Run overdue payment check
    public function runOverdueCheck(Request $request)
    {
        $cid      = $request->user()->company_id;
        $settings = DB::table('notification_settings')->where('company_id', $cid)->first();
        if (!$settings) return response()->json(['message' => 'Configure notification settings first.'], 422);

        $overdue = SalesInvoice::where('company_id', $cid)
            ->whereIn('status', ['confirmed', 'partially_paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->with('customer:id,name')
            ->get(['invoice_number', 'due_date', 'balance_amount', 'customer_id']);

        if ($overdue->isEmpty()) return response()->json(['message' => 'No overdue payments.', 'count' => 0]);

        $lines = $overdue->map(fn($i) => "📌 *{$i->invoice_number}* — {$i->customer?->name}\n   Due: {$i->due_date} | ₹{$i->balance_amount}")->join("\n\n");
        $msg   = "🔔 *Overdue Payments — OpenVyapar ERP*\n\n{$lines}\n\nTotal overdue: {$overdue->count()} invoices.";

        if ($settings->telegram_enabled) $this->sendTelegramRaw($settings, $msg);
        if ($settings->email_enabled && $settings->alert_email) {
            $this->sendEmailRaw($settings, "Overdue Payment Alert — {$overdue->count()} invoices", strip_tags(str_replace(['*'], '', $msg)));
        }

        return response()->json(['message' => "Alert sent for {$overdue->count()} overdue invoices.", 'count' => $overdue->count()]);
    }

    // Private helpers
    private function sendTelegram($settings, string $msg)
    {
        if (!$settings->telegram_bot_token || !$settings->telegram_chat_id) {
            return response()->json(['message' => 'Telegram bot token or chat ID missing.'], 422);
        }
        $res = $this->sendTelegramRaw($settings, $msg);
        return response()->json(['message' => $res ? 'Telegram message sent!' : 'Telegram send failed. Check token and chat ID.']);
    }

    private function sendTelegramRaw($settings, string $msg): bool
    {
        try {
            $resp = Http::post("https://api.telegram.org/bot{$settings->telegram_bot_token}/sendMessage", [
                'chat_id'    => $settings->telegram_chat_id,
                'text'       => $msg,
                'parse_mode' => 'Markdown',
            ]);
            return $resp->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function sendEmail($settings, string $subject, string $body)
    {
        if (!$settings->alert_email) {
            return response()->json(['message' => 'Alert email address missing.'], 422);
        }
        $ok = $this->sendEmailRaw($settings, $subject, $body);
        return response()->json(['message' => $ok ? 'Email sent!' : 'Email send failed. Check SMTP settings.']);
    }

    private function sendEmailRaw($settings, string $subject, string $body): bool
    {
        try {
            $mailer = app('mailer');
            $mailer->raw($body, function ($msg) use ($settings, $subject) {
                $msg->to($settings->alert_email)
                    ->subject($subject)
                    ->from($settings->smtp_username ?? config('mail.from.address'), $settings->smtp_from_name ?? 'OpenVyapar ERP');
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
