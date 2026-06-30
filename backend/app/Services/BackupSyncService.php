<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class BackupSyncService
{
    /**
     * Push a backup zip file to all enabled providers for a company.
     * Returns array of per-provider results.
     */
    public function syncToAll(int $companyId, string $zipPath, string $filename): array
    {
        $providers = DB::table('backup_sync_providers')
            ->where('company_id', $companyId)
            ->where('enabled', true)
            ->get();

        $results = [];
        foreach ($providers as $p) {
            $config = json_decode($p->config ?? '{}', true);
            try {
                $this->syncToProvider($p->provider, $config, $zipPath, $filename);
                $this->updateStatus($p->id, 'success', 'Synced successfully');
                $results[$p->provider] = ['ok' => true];
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                $this->updateStatus($p->id, 'error', $msg);
                $results[$p->provider] = ['ok' => false, 'error' => $msg];
                Log::error("BackupSync [{$p->provider}] failed: $msg");
            }
        }
        return $results;
    }

    /**
     * Push to a single named provider with the given config.
     */
    public function syncToProvider(string $provider, array $config, string $zipPath, string $filename): void
    {
        match ($provider) {
            'email'        => $this->syncEmail($config, $zipPath, $filename),
            'local'        => $this->syncLocal($config, $zipPath, $filename),
            'google_drive' => $this->syncGoogleDrive($config, $zipPath, $filename),
            'dropbox'      => $this->syncDropbox($config, $zipPath, $filename),
            'onedrive'     => $this->syncOneDrive($config, $zipPath, $filename),
            'github'       => $this->syncGitHub($config, $zipPath, $filename),
            default        => throw new \InvalidArgumentException("Unknown provider: $provider"),
        };
    }

    // ─── Email ──────────────────────────────────────────────────────────────

    private function syncEmail(array $cfg, string $zipPath, string $filename): void
    {
        $this->configureMail($cfg);

        $to = $cfg['to_email'] ?? throw new \RuntimeException('Recipient email not configured');
        $from = $cfg['from_email'] ?? $cfg['to_email'];
        $fromName = $cfg['from_name'] ?? 'OpenVyapar ERP';

        Mail::send([], [], function ($m) use ($to, $from, $fromName, $zipPath, $filename) {
            $m->from($from, $fromName)
              ->to($to)
              ->subject("OpenVyapar Backup – $filename")
              ->text("Please find attached your OpenVyapar ERP backup: $filename\n\nGenerated: " . now()->toDateTimeString())
              ->attach($zipPath, ['as' => $filename, 'mime' => 'application/zip']);
        });
    }

    private function configureMail(array $cfg): void
    {
        $driver = $cfg['driver'] ?? 'smtp';
        config([
            'mail.default'                     => $driver,
            'mail.mailers.smtp.host'           => $cfg['host']       ?? 'smtp.gmail.com',
            'mail.mailers.smtp.port'           => (int)($cfg['port'] ?? 587),
            'mail.mailers.smtp.encryption'     => $cfg['encryption'] ?? 'tls',
            'mail.mailers.smtp.username'       => $cfg['username']   ?? '',
            'mail.mailers.smtp.password'       => $cfg['password']   ?? '',
            'mail.mailers.smtp.timeout'        => 30,
        ]);
    }

    // ─── Local Folders ──────────────────────────────────────────────────────

    private function syncLocal(array $cfg, string $zipPath, string $filename): void
    {
        $paths = $cfg['paths'] ?? [];
        if (empty($paths)) throw new \RuntimeException('No local folder paths configured');

        $errors = [];
        foreach ($paths as $i => $dir) {
            $dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $errors[] = "Cannot create directory: $dir";
                    continue;
                }
            }
            if (!is_writable($dir)) {
                $errors[] = "Directory not writable: $dir";
                continue;
            }
            $dest = $dir . DIRECTORY_SEPARATOR . $filename;
            if (!copy($zipPath, $dest)) {
                $errors[] = "Copy failed to: $dir";
            }
        }

        if ($errors) throw new \RuntimeException(implode('; ', $errors));
    }

    // ─── Google Drive ───────────────────────────────────────────────────────

    private function syncGoogleDrive(array $cfg, string $zipPath, string $filename): void
    {
        $token = $cfg['access_token'] ?? throw new \RuntimeException('Google Drive access token not configured');
        $folderId = $cfg['folder_id'] ?? null;

        // Check token validity
        $meta = $this->curlGet('https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . urlencode($token));
        if (isset($meta['error'])) {
            throw new \RuntimeException('Google Drive token invalid or expired. Please re-authorise.');
        }

        // Multipart upload
        $boundary = 'OV_BACKUP_' . uniqid();
        $meta = json_encode(['name' => $filename, 'parents' => $folderId ? [$folderId] : []]);
        $fileContent = file_get_contents($zipPath);
        $body = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n"
              . "--$boundary\r\nContent-Type: application/zip\r\n\r\n$fileContent\r\n--$boundary--";

        $result = $this->curlPost(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart',
            $body,
            [
                "Authorization: Bearer $token",
                "Content-Type: multipart/related; boundary=$boundary",
            ]
        );

        if (isset($result['error'])) {
            throw new \RuntimeException('Google Drive upload failed: ' . ($result['error']['message'] ?? json_encode($result['error'])));
        }
    }

    // ─── Dropbox ────────────────────────────────────────────────────────────

    private function syncDropbox(array $cfg, string $zipPath, string $filename): void
    {
        $token = $cfg['access_token'] ?? throw new \RuntimeException('Dropbox access token not configured');
        $folder = rtrim($cfg['folder'] ?? '/OpenVyapar Backups', '/');
        $path = "$folder/$filename";

        $result = $this->curlPost(
            'https://content.dropboxapi.com/2/files/upload',
            file_get_contents($zipPath),
            [
                "Authorization: Bearer $token",
                'Content-Type: application/octet-stream',
                'Dropbox-API-Arg: ' . json_encode([
                    'path'       => $path,
                    'mode'       => 'add',
                    'autorename' => true,
                ]),
            ]
        );

        if (isset($result['error_summary'])) {
            throw new \RuntimeException('Dropbox upload failed: ' . $result['error_summary']);
        }
    }

    // ─── OneDrive ───────────────────────────────────────────────────────────

    private function syncOneDrive(array $cfg, string $zipPath, string $filename): void
    {
        $token = $cfg['access_token'] ?? throw new \RuntimeException('OneDrive access token not configured');
        $folder = trim($cfg['folder'] ?? 'OpenVyapar Backups', '/');
        $path = rawurlencode($folder) . '/' . rawurlencode($filename);

        // Simple upload (< 4 MB works without session, larger needs upload session)
        $size = filesize($zipPath);
        if ($size > 4 * 1024 * 1024) {
            $this->oneDriveLargeUpload($token, $folder, $filename, $zipPath);
            return;
        }

        $result = $this->curlPut(
            "https://graph.microsoft.com/v1.0/me/drive/root:/{$path}:/content",
            file_get_contents($zipPath),
            [
                "Authorization: Bearer $token",
                'Content-Type: application/zip',
            ]
        );

        if (isset($result['error'])) {
            throw new \RuntimeException('OneDrive upload failed: ' . ($result['error']['message'] ?? json_encode($result)));
        }
    }

    private function oneDriveLargeUpload(string $token, string $folder, string $filename, string $zipPath): void
    {
        $path = rawurlencode($folder) . '/' . rawurlencode($filename);
        $session = $this->curlPost(
            "https://graph.microsoft.com/v1.0/me/drive/root:/{$path}:/createUploadSession",
            json_encode(['item' => ['@microsoft.graph.conflictBehavior' => 'rename']]),
            ["Authorization: Bearer $token", 'Content-Type: application/json']
        );
        if (!isset($session['uploadUrl'])) {
            throw new \RuntimeException('OneDrive upload session failed: ' . json_encode($session));
        }

        $uploadUrl = $session['uploadUrl'];
        $fileSize = filesize($zipPath);
        $chunkSize = 3 * 1024 * 1024;
        $handle = fopen($zipPath, 'rb');
        $offset = 0;
        while ($chunk = fread($handle, $chunkSize)) {
            $len = strlen($chunk);
            $end = $offset + $len - 1;
            $this->curlPut($uploadUrl, $chunk, [
                "Content-Length: $len",
                "Content-Range: bytes $offset-$end/$fileSize",
            ]);
            $offset += $len;
        }
        fclose($handle);
    }

    // ─── GitHub ─────────────────────────────────────────────────────────────

    private function syncGitHub(array $cfg, string $zipPath, string $filename): void
    {
        $token = $cfg['access_token'] ?? throw new \RuntimeException('GitHub personal access token not configured');
        $owner = $cfg['owner'] ?? throw new \RuntimeException('GitHub owner not configured');
        $repo  = $cfg['repo']  ?? throw new \RuntimeException('GitHub repo not configured');
        $tag   = 'backup-' . now()->format('Y-m-d');

        $auth = ["Authorization: token $token", 'Content-Type: application/json', 'User-Agent: OpenVyapar-ERP'];

        // Get or create release for today's tag
        $existing = $this->curlGet("https://api.github.com/repos/$owner/$repo/releases/tags/$tag", $auth);
        if (isset($existing['id'])) {
            $releaseId = $existing['id'];
        } else {
            // Create release
            $created = $this->curlPost(
                "https://api.github.com/repos/$owner/$repo/releases",
                json_encode(['tag_name' => $tag, 'name' => "Backup $tag", 'body' => 'Auto-backup by OpenVyapar ERP']),
                $auth
            );
            if (!isset($created['id'])) {
                throw new \RuntimeException('GitHub release create failed: ' . json_encode($created));
            }
            $releaseId = $created['id'];
        }

        // Upload asset
        $uploadUrl = "https://uploads.github.com/repos/$owner/$repo/releases/$releaseId/assets?name=" . rawurlencode($filename);
        $result = $this->curlPost(
            $uploadUrl,
            file_get_contents($zipPath),
            ["Authorization: token $token", 'Content-Type: application/zip', 'User-Agent: OpenVyapar-ERP']
        );
        if (isset($result['errors'])) {
            throw new \RuntimeException('GitHub asset upload failed: ' . json_encode($result['errors']));
        }
    }

    // ─── HTTP helpers ───────────────────────────────────────────────────────

    private function curlGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res ?: '{}', true) ?? [];
    }

    private function curlPost(string $url, $body, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res ?: '{}', true) ?? [];
    }

    private function curlPut(string $url, $body, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res ?: '{}', true) ?? [];
    }

    private function updateStatus(int $id, string $status, string $message): void
    {
        DB::table('backup_sync_providers')->where('id', $id)->update([
            'last_synced_at'     => now(),
            'last_sync_status'   => $status,
            'last_sync_message'  => $message,
        ]);
    }
}
