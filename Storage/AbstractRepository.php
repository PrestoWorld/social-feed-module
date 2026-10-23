<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Storage;

use Cycle\Database\DatabaseInterface;

abstract class AbstractRepository
{
    public function __construct(
        protected DatabaseInterface $db,
        protected string $prefix,
    ) {}

    protected function table(string $name): string { return $this->prefix . $name; }
    protected function encode(mixed $value): string { $json = json_encode($value, JSON_UNESCAPED_UNICODE); return $json === false ? '{}' : $json; }
    protected function decode(?string $value): array { if (!$value) return []; $d = json_decode($value, true); return is_array($d) ? $d : []; }
    protected function rows(mixed $rows): array { if (!is_array($rows)) return []; return array_map(fn ($r) => $this->decoded($r), $rows); }
    protected function decoded(mixed $row): array { if (!is_array($row)) return []; if (isset($row['payload'])) $row['payload'] = $this->decode($row['payload']); return $row; }
    protected function int(mixed $row, string $key, int $default = 0): int { if (!is_array($row) || !isset($row[$key])) return $default; $v = $row[$key]; return is_int($v) ? $v : (is_numeric($v) ? (int)$v : $default); }
    protected function decodeJson(?string $v): array { if (!$v) return []; $d = json_decode($v, true); return is_array($d) ? $d : []; }
}
