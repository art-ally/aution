<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\SeederService;
use RuntimeException;

/**
 * Pure PHP Transactional JSON Storage Engine
 * Provides ACID-like file locking (flock LOCK_EX) for concurrent auction bidding and persistence
 * without requiring an external database server.
 */
class JsonDatabase
{
    private static ?JsonDatabase $instance = null;
    private string $dataFile;
    private string $lockFile;
    private array $data = [];
    private bool $inTransaction = false;
    /** @var resource|null */
    private $lockHandle = null;

    private function __construct()
    {
        $this->dataFile = DATA_PATH . '/atelier_noir_store.json';
        $this->lockFile = DATA_PATH . '/atelier_noir_store.lock';

        if (!is_file($this->dataFile)) {
            $this->initializeEmptyStore();
            SeederService::seed($this);
        } else {
            $this->loadFromDisk();
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeEmptyStore(): void
    {
        $tables = [
            'users',
            'roles',
            'artist_profiles',
            'buyer_profiles',
            'categories',
            'artworks',
            'artwork_images',
            'artwork_documents',
            'artwork_submissions',
            'auctions',
            'bids',
            'watchlists',
            'followed_artists',
            'orders',
            'order_items',
            'payments',
            'commissions',
            'seller_payouts',
            'notifications',
            'email_verifications',
            'password_reset_tokens',
            'newsletter_subscribers',
            'editorial_articles',
            'reports',
            'audit_logs',
            'platform_settings',
        ];

        $this->data = ['_meta' => ['sequences' => []]];
        foreach ($tables as $t) {
            $this->data[$t] = [];
            $this->data['_meta']['sequences'][$t] = 0;
        }
        $this->saveToDisk();
    }

    public function loadFromDisk(): void
    {
        if (!is_file($this->dataFile)) {
            return;
        }
        $fp = fopen($this->dataFile, 'rb');
        if ($fp === false) {
            return;
        }
        if (!$this->inTransaction) {
            flock($fp, LOCK_SH);
        }
        $contents = stream_get_contents($fp);
        if (!$this->inTransaction) {
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        if ($contents !== false && $contents !== '') {
            $decoded = json_decode($contents, true);
            if (is_array($decoded)) {
                $this->data = $decoded;
            }
        }
    }

    private function saveToDisk(): void
    {
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode store data.');
        }

        $tmpFile = $this->dataFile . '.tmp.' . uniqid('', true);
        file_put_contents($tmpFile, $json, LOCK_EX);
        @rename($tmpFile, $this->dataFile);
    }

    /**
     * Execute a callback inside an exclusive file lock transaction.
     * Prevents race conditions during concurrent auction bids.
     */
    public function transaction(callable $callback): mixed
    {
        $this->lockHandle = fopen($this->lockFile, 'c+');
        if ($this->lockHandle === false) {
            throw new RuntimeException('Unable to open transaction lock file.');
        }

        if (!flock($this->lockHandle, LOCK_EX)) {
            fclose($this->lockHandle);
            $this->lockHandle = null;
            throw new RuntimeException('Unable to acquire exclusive transaction lock.');
        }

        $this->inTransaction = true;
        // Reload freshest state from disk after acquiring lock
        $this->loadFromDisk();
        $snapshot = $this->data;

        try {
            $result = $callback($this);
            $this->saveToDisk();
            return $result;
        } catch (\Throwable $e) {
            // Rollback in-memory state on exception
            $this->data = $snapshot;
            throw $e;
        } finally {
            $this->inTransaction = false;
            if (is_resource($this->lockHandle)) {
                flock($this->lockHandle, LOCK_UN);
                fclose($this->lockHandle);
            }
            $this->lockHandle = null;
        }
    }

    public function all(string $table): array
    {
        if (!$this->inTransaction) {
            $this->loadFromDisk();
        }
        return array_values($this->data[$table] ?? []);
    }

    public function find(string $table, int|string $id): ?array
    {
        if (!$this->inTransaction) {
            $this->loadFromDisk();
        }
        foreach ($this->data[$table] ?? [] as $row) {
            if ((string)($row['id'] ?? '') === (string)$id) {
                return $row;
            }
        }
        return null;
    }

    public function findBy(string $table, string $field, mixed $value): ?array
    {
        if (!$this->inTransaction) {
            $this->loadFromDisk();
        }
        foreach ($this->data[$table] ?? [] as $row) {
            if (($row[$field] ?? null) === $value) {
                return $row;
            }
        }
        return null;
    }

    public function where(string $table, callable $predicate): array
    {
        if (!$this->inTransaction) {
            $this->loadFromDisk();
        }
        $results = [];
        foreach ($this->data[$table] ?? [] as $row) {
            if ($predicate($row)) {
                $results[] = $row;
            }
        }
        return $results;
    }

    public function insert(string $table, array $record): array
    {
        if (!isset($this->data[$table])) {
            $this->data[$table] = [];
        }
        $seq = (int)($this->data['_meta']['sequences'][$table] ?? 0) + 1;
        if (isset($record['id'])) {
            $id = (int)$record['id'];
            if ($id >= $seq) {
                $seq = $id;
            }
        } else {
            $record['id'] = $seq;
        }
        $this->data['_meta']['sequences'][$table] = $seq;

        $now = gmdate('Y-m-d H:i:s');
        $record['created_at'] = $record['created_at'] ?? $now;
        $record['updated_at'] = $record['updated_at'] ?? $now;

        $this->data[$table][] = $record;
        if (!$this->inTransaction) {
            $this->saveToDisk();
        }
        return $record;
    }

    public function update(string $table, int|string $id, array $attributes): ?array
    {
        if (!isset($this->data[$table])) {
            return null;
        }
        foreach ($this->data[$table] as $idx => $row) {
            if ((string)($row['id'] ?? '') === (string)$id) {
                $updated = array_merge($row, $attributes);
                $updated['id'] = $row['id'];
                $updated['updated_at'] = gmdate('Y-m-d H:i:s');
                $this->data[$table][$idx] = $updated;
                if (!$this->inTransaction) {
                    $this->saveToDisk();
                }
                return $updated;
            }
        }
        return null;
    }

    public function delete(string $table, int|string $id): bool
    {
        if (!isset($this->data[$table])) {
            return false;
        }
        foreach ($this->data[$table] as $idx => $row) {
            if ((string)($row['id'] ?? '') === (string)$id) {
                array_splice($this->data[$table], $idx, 1);
                if (!$this->inTransaction) {
                    $this->saveToDisk();
                }
                return true;
            }
        }
        return false;
    }
}
