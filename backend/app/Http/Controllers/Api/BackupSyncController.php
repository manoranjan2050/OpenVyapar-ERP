<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackupSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BackupSyncController extends Controller
{
    public function __construct(private BackupSyncService $sync) {}

    // List all provider configs for this company
    public function index(Request $request)
    {
        $cid = $request->user()->company_id;
        $rows = DB::table('backup_sync_providers')
            ->where('company_id', $cid)
            ->get()
            ->keyBy('provider');

        $providers = ['email', 'local', 'google_drive', 'dropbox', 'onedrive', 'github'];
        $result = [];
        foreach ($providers as $p) {
            $row = $rows[$p] ?? null;
            $config = $row ? json_decode($row->config ?? '{}', true) : [];

            // Mask secrets in response
            foreach (['password', 'access_token', 'client_secret'] as $secret) {
                if (!empty($config[$secret])) $config[$secret] = '••••••••';
            }

            $result[] = [
                'provider'          => $p,
                'enabled'           => (bool) ($row->enabled ?? false),
                'config'            => $config,
                'last_synced_at'    => $row->last_synced_at ?? null,
                'last_sync_status'  => $row->last_sync_status ?? null,
                'last_sync_message' => $row->last_sync_message ?? null,
            ];
        }
        return response()->json($result);
    }

    // Save/update a provider config
    public function save(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|in:email,local,google_drive,dropbox,onedrive,github',
            'enabled'  => 'boolean',
            'config'   => 'nullable|array',
        ]);

        $cid = $request->user()->company_id;
        $existing = DB::table('backup_sync_providers')
            ->where('company_id', $cid)
            ->where('provider', $data['provider'])
            ->first();

        // Merge new config over existing (so masked "••••••••" values don't overwrite real ones)
        $oldConfig = $existing ? json_decode($existing->config ?? '{}', true) : [];
        $newConfig = $data['config'] ?? [];
        foreach ($newConfig as $k => $v) {
            if ($v !== '••••••••') $oldConfig[$k] = $v;
        }

        $payload = [
            'company_id' => $cid,
            'provider'   => $data['provider'],
            'enabled'    => $data['enabled'] ?? false,
            'config'     => json_encode($oldConfig),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('backup_sync_providers')->where('id', $existing->id)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('backup_sync_providers')->insert($payload);
        }

        return response()->json(['message' => 'Provider settings saved.']);
    }

    // Test a provider connection without actually syncing
    public function test(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|in:email,local,google_drive,dropbox,onedrive,github',
            'config'   => 'required|array',
        ]);

        // For test, use a tiny temp file
        $tmpZip = sys_get_temp_dir() . '/ov_test_' . time() . '.zip';
        $zip = new \ZipArchive();
        $zip->open($tmpZip, \ZipArchive::CREATE);
        $zip->addFromString('test.txt', "OpenVyapar ERP connection test – " . now()->toDateTimeString());
        $zip->close();

        try {
            $cid = $request->user()->company_id;
            // Merge with saved config so masked values use real saved values
            $saved = DB::table('backup_sync_providers')
                ->where('company_id', $cid)
                ->where('provider', $data['provider'])
                ->value('config');
            $savedConfig = $saved ? json_decode($saved, true) : [];
            $newConfig = $data['config'];
            foreach ($newConfig as $k => $v) {
                if ($v === '••••••••') $newConfig[$k] = $savedConfig[$k] ?? '';
            }

            $this->sync->syncToProvider($data['provider'], $newConfig, $tmpZip, 'ov_connection_test.zip');
            return response()->json(['message' => 'Connection test successful!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Test failed: ' . $e->getMessage()], 422);
        } finally {
            if (file_exists($tmpZip)) unlink($tmpZip);
        }
    }

    // Manually trigger sync of a specific backup to all enabled providers
    public function syncNow(Request $request)
    {
        $data = $request->validate(['filename' => 'required|string']);
        $cid = $request->user()->company_id;

        $backupDir = storage_path('app/backups');
        $zipPath = $backupDir . '/' . basename($data['filename']);

        if (!file_exists($zipPath)) {
            return response()->json(['message' => 'Backup file not found.'], 404);
        }

        $results = $this->sync->syncToAll($cid, $zipPath, basename($data['filename']));

        $failed = array_filter($results, fn($r) => !$r['ok']);
        if ($failed) {
            return response()->json([
                'message' => count($failed) . ' provider(s) failed.',
                'results' => $results,
            ], 207);
        }

        return response()->json(['message' => 'Synced to all providers.', 'results' => $results]);
    }

    // Generate Google Drive OAuth URL
    public function googleAuthUrl(Request $request)
    {
        $clientId = $request->validate(['client_id' => 'required|string'])['client_id'];
        $redirect = url('/api/backup-sync/google/callback');
        $scopes = 'https://www.googleapis.com/auth/drive.file';
        $url = "https://accounts.google.com/o/oauth2/v2/auth?"
             . http_build_query([
                 'client_id'     => $clientId,
                 'redirect_uri'  => $redirect,
                 'response_type' => 'code',
                 'scope'         => $scopes,
                 'access_type'   => 'offline',
                 'prompt'        => 'consent',
             ]);
        return response()->json(['url' => $url]);
    }

    // Exchange Google OAuth code for token
    public function googleCallback(Request $request)
    {
        $code = $request->query('code');
        if (!$code) return response('<script>window.close()</script>', 200)->header('Content-Type', 'text/html');

        $cid = $request->user()?->company_id;
        $saved = DB::table('backup_sync_providers')
            ->where('company_id', $cid)
            ->where('provider', 'google_drive')
            ->value('config');
        $cfg = $saved ? json_decode($saved, true) : [];

        $res = $this->sync->syncToProvider; // just to silence lint
        $body = http_build_query([
            'code'          => $code,
            'client_id'     => $cfg['client_id'] ?? '',
            'client_secret' => $cfg['client_secret'] ?? '',
            'redirect_uri'  => url('/api/backup-sync/google/callback'),
            'grant_type'    => 'authorization_code',
        ]);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_SSL_VERIFYPEER => false]);
        $tokens = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (!empty($tokens['access_token'])) {
            $cfg['access_token']  = $tokens['access_token'];
            $cfg['refresh_token'] = $tokens['refresh_token'] ?? ($cfg['refresh_token'] ?? '');
            DB::table('backup_sync_providers')->updateOrInsert(
                ['company_id' => $cid, 'provider' => 'google_drive'],
                ['config' => json_encode($cfg), 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return response('<script>window.opener.postMessage("google_auth_done","*");window.close();</script>', 200)
            ->header('Content-Type', 'text/html');
    }
}
