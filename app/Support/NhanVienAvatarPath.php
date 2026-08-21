<?php

namespace App\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class NhanVienAvatarPath
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private string $prefix;

    public function __construct()
    {
        $prefix = config('nhanvien.avatar_prefix');
        if (! is_string($prefix) || ! $this->isSafePrefix($prefix)) {
            throw new InvalidArgumentException('Employee avatar prefix is invalid.');
        }

        $this->prefix = $prefix;
    }

    public function newPath(string $extension): string
    {
        return $this->prefix.'/'.Str::uuid()->toString().'.'.$this->extension($extension);
    }

    public function newTemporaryPath(string $extension): string
    {
        return $this->prefix.'/tmp/'.Str::uuid()->toString().'.'.$this->extension($extension);
    }

    public function assertOwnedFile(?string $path): ?string
    {
        return $this->assertOwned($path, false);
    }

    public function assertOwnedTemporaryFile(?string $path): ?string
    {
        return $this->assertOwned($path, true);
    }

    private function assertOwned(?string $path, bool $temporary): ?string
    {
        if ($path === null) {
            return null;
        }

        $directory = preg_quote($this->prefix.($temporary ? '/tmp' : ''), '#');
        $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';
        if (preg_match("#\\A{$directory}/{$uuid}\\.(?:jpg|jpeg|png|webp)\\z#", $path) !== 1) {
            throw new InvalidArgumentException('Employee avatar path is not owned.');
        }

        return $path;
    }

    private function extension(string $extension): string
    {
        $extension = strtolower($extension);
        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw new InvalidArgumentException('Employee avatar extension is invalid.');
        }

        return $extension;
    }

    private function isSafePrefix(string $prefix): bool
    {
        if ($prefix === ''
            || trim($prefix) !== $prefix
            || str_contains($prefix, '\\')
            || str_starts_with($prefix, '/')
            || preg_match('/\A[A-Za-z]:/', $prefix) === 1) {
            return false;
        }

        $segments = explode('/', $prefix);
        foreach ($segments as $segment) {
            if (preg_match('/\A[A-Za-z0-9_-]+\z/', $segment) !== 1) {
                return false;
            }
        }

        return true;
    }
}
