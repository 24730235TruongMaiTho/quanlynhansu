<?php

namespace App\Auth;

use App\Contracts\NhanVienRepositoryContract;
use App\Enums\NhanVienStatus;
use App\Exceptions\NhanVienDomainException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NhanVienUserProvider implements UserProvider
{
    /** @var array<string, string> */
    private array $validatedHashes = [];

    public function __construct(
        private NhanVienRepositoryContract $repository,
        private Hasher $hasher,
    ) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        if (! is_string($identifier) || $identifier === '') {
            return null;
        }

        try {
            return $this->activeAccount($this->repository->findAccountByIdentifier($identifier));
        } catch (Throwable) {
            return null;
        }
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $identifier = $credentials['dinh_danh'] ?? null;

        if (! is_string($identifier) || $identifier === '') {
            return null;
        }

        return $this->activeAccount($this->repository->findAccountByIdentifier($identifier));
    }

    public function validateCredentials(
        Authenticatable $user,
        #[\SensitiveParameter] array $credentials,
    ): bool {
        $identifier = $user->getAuthIdentifier();
        unset($this->validatedHashes[$identifier]);

        if ($this->isTerminated($user)) {
            return false;
        }

        $password = $credentials['password'] ?? null;
        $currentHash = $user->getAuthPassword();

        if (! is_string($password) || ! is_string($currentHash) || ! $this->hasher->check($password, $currentHash)) {
            return false;
        }

        $this->validatedHashes[$user->getAuthIdentifier()] = $currentHash;

        return true;
    }

    public function rehashPasswordIfRequired(
        Authenticatable $user,
        #[\SensitiveParameter] array $credentials,
        bool $force = false,
    ): void {
        $identifier = $user->getAuthIdentifier();
        $currentHash = $this->validatedHashes[$identifier] ?? null;

        if ($currentHash === null) {
            return;
        }

        try {
            if ($this->isTerminated($user)) {
                return;
            }

            if (! $force && ! $this->hasher->needsRehash($currentHash)) {
                return;
            }

            $password = $credentials['password'] ?? null;

            if (! is_string($password)) {
                return;
            }

            $newHash = $this->hasher->make($password);
            $this->repository->rehashAuthenticatedPassword($identifier, $currentHash, $newHash);
        } catch (NhanVienDomainException $exception) {
            if ($exception->domainCode !== 'NV_AUTH_HASH_STALE') {
                throw $exception;
            }

            try {
                Log::warning('employee_auth_rehash_stale', [
                    'event_code' => 'NV_AUTH_HASH_STALE',
                    'ma_nv' => $identifier,
                ]);
            } catch (Throwable) {
                // A logger outage must not turn a valid password into a failed login.
            }
        } finally {
            unset($this->validatedHashes[$identifier]);
        }
    }

    private function activeAccount(?Authenticatable $user): ?Authenticatable
    {
        if ($user === null || $this->isTerminated($user)) {
            return null;
        }

        return $user;
    }

    private function isTerminated(Authenticatable $user): bool
    {
        return $user instanceof \App\Models\NhanVien
            && (int) $user->getAttribute('ma_tt') === NhanVienStatus::Terminated->value;
    }
}
