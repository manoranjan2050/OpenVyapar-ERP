<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BackupController extends Controller
{
    private function backupDir(): string
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir;
    }

    // List all backup files
    public function index(): \Illuminate\Http\JsonResponse
    {
        $dir   = $this->backupDir();
        $files = glob($dir . '/*.zip');
        if (!$files) return response()->json([]);

        $items = collect($files)->map(function ($path) {
            return [
                'filename'   => basename($path),
                'size'       => filesize($path),
                'size_human' => $this->humanSize(filesize($path)),
                'created_at' => date('Y-m-d H:i:s', filemtime($path)),
                'type'       => str_contains(basename($path), '_auto_') ? 'auto' : 'manual',
            ];
        })->sortByDesc('created_at')->values();

        return response()->json($items);
    }

    // Create a new backup
    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        $label = $request->input('label', 'manual');
        $db    = config('database.connections.mysql.database');
        $host  = config('database.connections.mysql.host');
        $port  = config('database.connections.mysql.port', 3306);
        $user  = config('database.connections.mysql.username');
        $pass  = config('database.connections.mysql.password');

        $ts      = now()->format('Ymd_His');
        $sqlFile = $this->backupDir() . "/openvyapar_{$label}_{$ts}.sql";
        $zipFile = $this->backupDir() . "/openvyapar_{$label}_{$ts}.zip";

        // Dump using PHP PDO (works without mysqldump binary)
        $pdo = DB::getPdo();
        $sql = $this->dumpDatabase($pdo, $db);
        file_put_contents($sqlFile, $sql);

        // Zip
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
            return response()->json(['message' => 'Could not create zip file.'], 500);
        }
        $zip->addFile($sqlFile, basename($sqlFile));

        // Include a README
        $readme  = "OpenVyapar ERP Backup\n";
        $readme .= "Created: " . now()->toDateTimeString() . "\n";
        $readme .= "Database: $db\n";
        $readme .= "To restore: import the .sql file into your MySQL database.\n";
        $zip->addFromString('README.txt', $readme);
        $zip->close();

        unlink($sqlFile); // Remove raw SQL after zipping

        $this->pruneOldBackups(20); // Keep max 20 backups

        return response()->json([
            'message'    => 'Backup created successfully.',
            'filename'   => basename($zipFile),
            'size_human' => $this->humanSize(filesize($zipFile)),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    // Download a backup file
    public function download(Request $request, string $filename): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $path = $this->backupDir() . '/' . basename($filename);
        if (!file_exists($path)) return response()->json(['message' => 'File not found.'], 404);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, basename($filename), ['Content-Type' => 'application/zip']);
    }

    // Delete a specific backup
    public function destroy(Request $request, string $filename): \Illuminate\Http\JsonResponse
    {
        $path = $this->backupDir() . '/' . basename($filename);
        if (!file_exists($path)) return response()->json(['message' => 'File not found.'], 404);
        unlink($path);
        return response()->json(['message' => 'Backup deleted.']);
    }

    // Restore from uploaded file
    public function restore(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:zip,sql']);
        $file = $request->file('file');

        $tmpDir = sys_get_temp_dir() . '/ov_restore_' . time();
        mkdir($tmpDir, 0755, true);

        $sqlContent = '';
        if ($file->getClientOriginalExtension() === 'zip') {
            $zip = new ZipArchive();
            $zip->open($file->path());
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_ends_with($name, '.sql')) {
                    $sqlContent = $zip->getFromIndex($i);
                    break;
                }
            }
            $zip->close();
        } else {
            $sqlContent = file_get_contents($file->path());
        }

        if (empty($sqlContent)) return response()->json(['message' => 'No SQL found in the backup file.'], 422);

        // Execute SQL statements
        DB::unprepared($sqlContent);

        return response()->json(['message' => 'Database restored successfully. Please refresh the app.']);
    }

    // Settings (auto backup config)
    public function getSettings(): \Illuminate\Http\JsonResponse
    {
        $val = DB::table('settings')
            ->where('key', 'backup_settings')
            ->value('value');
        return response()->json($val ? json_decode($val, true) : [
            'auto_backup_enabled'  => false,
            'auto_backup_interval' => 'daily',
            'keep_last'            => 10,
            'backup_on_close'      => true,
            'backup_locations'     => ['local'],
        ]);
    }

    public function saveSettings(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'auto_backup_enabled'  => 'boolean',
            'auto_backup_interval' => 'string|in:hourly,daily,weekly',
            'keep_last'            => 'integer|min:1|max:100',
            'backup_on_close'      => 'boolean',
            'backup_locations'     => 'array',
        ]);

        $existing = DB::table('settings')->where('key', 'backup_settings')->first();
        if ($existing) {
            DB::table('settings')->where('key', 'backup_settings')->update(['value' => json_encode($data), 'updated_at' => now()]);
        } else {
            DB::table('settings')->insert(['key' => 'backup_settings', 'value' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);
        }
        return response()->json(['message' => 'Settings saved.']);
    }

    // Stats
    public function stats(): \Illuminate\Http\JsonResponse
    {
        $dir   = $this->backupDir();
        $files = glob($dir . '/*.zip') ?: [];
        $total_size = array_sum(array_map('filesize', $files));

        return response()->json([
            'total_backups' => count($files),
            'total_size'    => $this->humanSize($total_size),
            'latest'        => count($files) ? date('Y-m-d H:i:s', filemtime($files[0])) : null,
            'backup_dir'    => $dir,
        ]);
    }

    // PHP-based database dump (no mysqldump required)
    private function dumpDatabase(\PDO $pdo, string $dbName): string
    {
        $sql  = "-- OpenVyapar ERP Database Backup\n";
        $sql .= "-- Generated: " . now()->toDateTimeString() . "\n";
        $sql .= "-- Database: {$dbName}\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // Structure
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
            $sql   .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql   .= $create['Create Table'] . ";\n\n";

            // Data
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($rows)) continue;

            $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            $sql .= "INSERT INTO `{$table}` ({$cols}) VALUES\n";
            $chunks = array_chunk($rows, 200);
            foreach ($chunks as $ci => $chunk) {
                $vals = implode(",\n", array_map(function ($row) use ($pdo) {
                    return '(' . implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row)) . ')';
                }, $chunk));
                $sql .= $vals . ($ci < count($chunks) - 1 ? ",\n" : ";\n");
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    private function pruneOldBackups(int $keep): void
    {
        $dir   = $this->backupDir();
        $files = glob($dir . '/*.zip') ?: [];
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        foreach (array_slice($files, $keep) as $f) unlink($f);
    }
}
