<?php

namespace App\Services;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use PDO;
use Pdo\Sqlite;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class LocalBackup
{
    private const string PRIVATE_DIRECTORY = 'storage/app/private';

    public function __construct(private readonly Filesystem $files) {}

    public function create(string $destination): string
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Yedekleme için dosya tabanlı bir SQLite veritabanı gerekir.');
        }
        if (config('filesystems.disks.private.driver') !== 'local') {
            throw new RuntimeException('Yedekleme için private diski yerel bir dizin olmalıdır.');
        }
        $database = $connection->getPdo();
        $databasePath = $database->query("SELECT file FROM pragma_database_list WHERE name = 'main'")->fetchColumn();
        if (! is_string($databasePath) || ! is_file($databasePath)) {
            throw new RuntimeException('Yedekleme için dosya tabanlı bir SQLite veritabanı gerekir.');
        }

        $root = $this->privateRoot();
        $disks = ['private'];
        if (config('filesystems.disks.local.driver') === 'local'
            && realpath((string) config('filesystems.disks.local.root')) === $root) {
            $disks[] = 'local';
        }

        return $this->inNewDirectory($destination, function (string $target) use ($database, $root, $disks): void {
            $snapshot = $database->prepare('VACUUM INTO ?');
            $snapshot->execute([$target.'/database.sqlite']);
            $this->copyPrivateFiles($root, $target.'/'.self::PRIVATE_DIRECTORY);
            $checksums = $this->checksums($target);
            $this->validateDatabase($target, $checksums, $disks);

            $manifest = json_encode(['version' => 1, 'sha256' => $checksums], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if ($this->files->put($target.'/manifest.json', $manifest.PHP_EOL) === false) {
                throw new RuntimeException('Yedek doğrulama dosyası yazılamadı.');
            }
        });
    }

    public function restore(string $backup, string $destination): string
    {
        $source = realpath($backup);
        if ($source === false || ! is_file($source.'/manifest.json') || is_link($source.'/manifest.json')) {
            throw new RuntimeException('Yedek doğrulama dosyası bulunamadı.');
        }
        $manifest = json_decode($this->files->get($source.'/manifest.json'), true);
        if (($manifest['version'] ?? null) !== 1 || ! is_array($manifest['sha256'] ?? null)) {
            throw new RuntimeException('Yedek doğrulama dosyası geçersiz.');
        }
        $expected = $manifest['sha256'];
        ksort($expected);
        $private = $source.'/'.self::PRIVATE_DIRECTORY;
        if (realpath($private) !== $private || ! is_dir($private)) {
            throw new RuntimeException('Yedekteki özel dosya dizini geçersiz.');
        }

        return $this->inNewDirectory($destination, function (string $target) use ($source, $private, $expected): void {
            $this->copyFile($source.'/database.sqlite', $target.'/database.sqlite');
            $this->copyPrivateFiles($private, $target.'/'.self::PRIVATE_DIRECTORY);

            $checksums = $this->checksums($target);
            if ($checksums !== $expected) {
                throw new RuntimeException('Yedekteki dosyalar doğrulama toplamlarıyla eşleşmiyor.');
            }
            $this->validateDatabase($target, $checksums, ['private', 'local']);
        }, [$source]);
    }

    private function privateRoot(): string
    {
        $root = config('filesystems.disks.private.root');
        if (! is_string($root) || $root === '') {
            throw new RuntimeException('Özel dosya dizini yapılandırılmalıdır.');
        }

        return realpath($root) ?: $root;
    }

    /**
     * @param  array<string>  $excludedRoots
     */
    private function inNewDirectory(string $destination, Closure $write, array $excludedRoots = []): string
    {
        $parent = realpath(dirname($destination));
        if ($destination === '' || $parent === false || ! is_dir($parent)) {
            throw new RuntimeException('Hedef dizinin üst dizini mevcut olmalıdır.');
        }

        $target = rtrim($parent, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename($destination);
        $roots = [public_path(), (string) config('filesystems.disks.public.root'), $this->privateRoot(), ...$excludedRoots];
        foreach (array_filter($roots) as $root) {
            $root = realpath($root) ?: $root;
            $root = rtrim($root, DIRECTORY_SEPARATOR);
            if ($target === $root || str_starts_with($target, $root.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Yedekleme ve geri yükleme için kaynak ve web dizinlerinin dışında bir hedef seçin.');
            }
        }
        if (! $this->files->makeDirectory($target, 0700, force: true)) {
            throw new RuntimeException('Hedef dizin mevcut veya oluşturulamıyor.');
        }

        try {
            $write($target);
        } catch (Throwable $error) {
            $this->files->deleteDirectory($target);

            throw $error;
        }

        return $target;
    }

    private function copyPrivateFiles(string $source, string $target): void
    {
        $this->files->ensureDirectoryExists($target, 0700);
        if (! file_exists($source)) {
            return;
        }

        foreach ($this->privateFiles($source) as $relative => $file) {
            $output = $target.'/'.$relative;
            $this->files->ensureDirectoryExists(dirname($output), 0700);
            $this->copyFile($file, $output);
        }
    }

    private function copyFile(string $source, string $target): void
    {
        if (is_link($source) || ! is_file($source) || ! $this->files->copy($source, $target)) {
            throw new RuntimeException('Yedek dosyası okunamadı veya kopyalanamadı.');
        }
    }

    /**
     * @return iterable<string, string>
     */
    private function privateFiles(string $path): iterable
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($entries as $entry) {
            if ($entry->isLink() || (! $entry->isFile() && ! $entry->isDir())) {
                throw new RuntimeException('Özel dosya dizininde bağlantı veya özel bir dosya türü bulundu.');
            }
            if ($entry->isFile()) {
                yield str_replace(DIRECTORY_SEPARATOR, '/', $entries->getSubPathname()) => $entry->getPathname();
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function checksums(string $target): array
    {
        $paths = ['database.sqlite' => $target.'/database.sqlite'];
        foreach ($this->privateFiles($target.'/'.self::PRIVATE_DIRECTORY) as $relative => $file) {
            $paths[self::PRIVATE_DIRECTORY.'/'.$relative] = $file;
        }
        ksort($paths);

        return array_map(function (string $file): string {
            return hash_file('sha256', $file) ?: throw new RuntimeException('Yedek dosyasının doğrulama toplamı hesaplanamadı.');
        }, $paths);
    }

    /**
     * @param  array<string, string>  $checksums
     * @param  array<string>  $disks
     */
    private function validateDatabase(string $target, array $checksums, array $disks): void
    {
        $database = new PDO('sqlite:'.$target.'/database.sqlite', options: [
            Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY,
        ]);
        if ($database->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN) !== ['ok']) {
            throw new RuntimeException('SQLite bütünlük kontrolü başarısız.');
        }

        foreach ($database->query('SELECT disk, path FROM message_attachments') as $attachment) {
            if (! in_array($attachment['disk'], $disks, true) || ! isset($checksums[self::PRIVATE_DIRECTORY.'/'.$attachment['path']])) {
                throw new RuntimeException('Yedekte veritabanına bağlı bir görüşme dosyası eksik.');
            }
        }
    }
}
