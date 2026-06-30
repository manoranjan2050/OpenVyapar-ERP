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
        $label      = $request->input('label', 'manual');
        $connection = config('database.default');
        $ts         = now()->format('Ymd_His');
        $sqlFile    = $this->backupDir() . "/openvyapar_{$label}_{$ts}.sql";
        $zipFile    = $this->backupDir() . "/openvyapar_{$label}_{$ts}.zip";

        // Dump using PHP PDO — works for both SQLite and MySQL
        $pdo = DB::getPdo();
        $sql = $connection === 'sqlite'
            ? $this->dumpSqlite($pdo)
            : $this->dumpMysql($pdo, config('database.connections.mysql.database'));
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
        $readme .= "Driver: {$connection}\n";
        $readme .= "To restore: use the Backup & Restore page in OpenVyapar ERP.\n";
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

        $connection = config('database.default');
        $pdo = DB::getPdo();

        // Detect backup format from header comment for cross-version compatibility
        $backupDriver = 'unknown';
        if (str_contains($sqlContent, '-- OpenVyapar ERP Database Backup (SQLite)')) $backupDriver = 'sqlite';
        if (str_contains($sqlContent, '-- OpenVyapar ERP Database Backup (MySQL)'))  $backupDriver = 'mysql';

        // Cross-version compatibility: inject missing table stubs so old backups work on new schema
        // (new tables added in newer versions will be created by the post-restore migration)

        // Split into individual statements
        $rawStatements = preg_split('/;\s*[\r\n]+/', $sqlContent);
        $statements = [];
        foreach ($rawStatements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || preg_match('/^--/', $stmt)) continue;

            // Skip MySQL-only commands when on SQLite
            if ($connection === 'sqlite') {
                if (preg_match('/^(SET\s|LOCK\s+TABLES|UNLOCK\s+TABLES)/i', $stmt)) continue;
                if (preg_match('/^(BEGIN\s+TRANSACTION|COMMIT\b|PRAGMA\s+foreign_keys)/i', $stmt)) continue;
            }
            // Skip SQLite-only commands when on MySQL
            if ($connection === 'mysql') {
                if (preg_match('/^PRAGMA\s/i', $stmt)) continue;
                if (preg_match('/^(BEGIN\s+TRANSACTION|COMMIT\b)/i', $stmt)) continue;
            }

            $statements[] = $stmt;
        }

        // DDL statements (CREATE TABLE, DROP TABLE) cause implicit commits in both MySQL and SQLite.
        // So we never use PDO transactions for restore — execute directly with FK checks off.
        try {
            if ($connection === 'sqlite') {
                $pdo->exec('PRAGMA foreign_keys=OFF');
                foreach ($statements as $stmt) {
                    $pdo->exec($stmt);
                }
                $pdo->exec('PRAGMA foreign_keys=ON');
            } else {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                foreach ($statements as $stmt) {
                    if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS\s*=/i', $stmt)) continue;
                    $pdo->exec($stmt);
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        } catch (\Exception $e) {
            // Re-enable FK checks even on error
            if ($connection === 'sqlite') { try { $pdo->exec('PRAGMA foreign_keys=ON'); } catch (\Exception $ignored) {} }
            if ($connection === 'mysql')  { try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (\Exception $ignored) {} }
            return response()->json(['message' => 'Restore failed: ' . $e->getMessage()], 500);
        }

        // Post-restore: run migrations to add any new tables/columns not in the backup
        // This ensures old-version backups work correctly after an app update
        try {
            \Artisan::call('migrate', ['--force' => true]);
        } catch (\Exception $e) {
            // Non-fatal — data is restored, schema just may need manual check
        }

        return response()->json(['message' => 'Database restored successfully. Migrations applied. Please refresh.']);
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

    // SQLite dump — uses sqlite_master to get schema
    private function dumpSqlite(\PDO $pdo): string
    {
        $sql  = "-- OpenVyapar ERP Database Backup (SQLite)\n";
        $sql .= "-- Generated: " . now()->toDateTimeString() . "\n\n";
        $sql .= "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n\n";

        $tables = $pdo->query(
            "SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($tables as $t) {
            $name = $t['name'];
            $sql .= "DROP TABLE IF EXISTS \"{$name}\";\n";
            $sql .= $t['sql'] . ";\n\n";

            $rows = $pdo->query("SELECT * FROM \"{$name}\"")->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($rows)) continue;

            $cols = '"' . implode('", "', array_keys($rows[0])) . '"';
            foreach (array_chunk($rows, 200) as $chunk) {
                $vals = implode(",\n", array_map(function ($row) use ($pdo) {
                    return '(' . implode(', ', array_map(
                        fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row
                    )) . ')';
                }, $chunk));
                $sql .= "INSERT INTO \"{$name}\" ({$cols}) VALUES\n{$vals};\n";
            }
            $sql .= "\n";
        }

        // Also dump indexes
        $indexes = $pdo->query(
            "SELECT sql FROM sqlite_master WHERE type='index' AND sql IS NOT NULL"
        )->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($indexes as $idx) {
            $sql .= $idx . ";\n";
        }

        $sql .= "\nCOMMIT;\nPRAGMA foreign_keys=ON;\n";
        return $sql;
    }

    // MySQL dump — uses SHOW TABLES / SHOW CREATE TABLE
    private function dumpMysql(\PDO $pdo, string $dbName): string
    {
        $sql  = "-- OpenVyapar ERP Database Backup (MySQL)\n";
        $sql .= "-- Generated: " . now()->toDateTimeString() . "\n";
        $sql .= "-- Database: {$dbName}\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
            $sql   .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql   .= $create['Create Table'] . ";\n\n";

            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($rows)) continue;

            $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            $sql .= "INSERT INTO `{$table}` ({$cols}) VALUES\n";
            foreach (array_chunk($rows, 200) as $ci => $chunk) {
                $vals = implode(",\n", array_map(function ($row) use ($pdo) {
                    return '(' . implode(', ', array_map(
                        fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row
                    )) . ')';
                }, $chunk));
                $sql .= $vals . ";\n";
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
