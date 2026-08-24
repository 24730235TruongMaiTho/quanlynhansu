<?php

namespace App\Authorization;

use App\Contracts\PermissionDefinitionContract;
use App\Contracts\PermissionRegistryContract;
use App\Enums\PermissionAction;
use Throwable;

/**
 * Static authorization contract for the application-owned web modules.
 *
 * Construction has no database side effects. Database rows are checked by
 * PermissionService at request time against these stable definitions.
 */
final class PermissionRegistry implements PermissionRegistryContract
{
    /** @var array<string, PermissionDefinitionContract>|null */
    private ?array $definitionsBySymbol = null;

    /** @var array<string, PermissionDefinitionContract>|null */
    private ?array $definitionsByModuleAction = null;

    private bool $valid = true;

    /** @param list<class-string> | null $definitionClasses */
    public function __construct(private ?array $definitionClasses = null) {}

    public function forAbility(string $ability): ?PermissionDefinitionContract
    {
        if (! $this->isValid()) {
            return null;
        }

        $definitions = $this->definitionsBySymbol();

        return $definitions[$ability] ?? null;
    }

    public function forModuleAction(string $module, PermissionAction $action): ?PermissionDefinitionContract
    {
        if (! $this->isValid()) {
            return null;
        }

        return $this->definitionsByModuleAction()[$this->moduleActionKey($module, $action)] ?? null;
    }

    /** @return list<PermissionDefinitionContract> */
    public function all(): array
    {
        return $this->isValid() ? array_values($this->definitionsBySymbol()) : [];
    }

    private function definitionsBySymbol(): array
    {
        if ($this->definitionsBySymbol !== null) {
            return $this->definitionsBySymbol;
        }

        $definitions = [];
        $bySymbol = [];
        $byId = [];
        $byModuleAction = [];

        foreach ($this->definitionClasses() as $definitionClass) {
            if (! is_string($definitionClass)
                || ! is_a($definitionClass, PermissionDefinitionContract::class, true)
                || ! is_callable([$definitionClass, 'cases'])) {
                $this->valid = false;
                break;
            }

            try {
                $cases = $definitionClass::cases();
            } catch (Throwable) {
                $this->valid = false;
                break;
            }
            if (! is_array($cases)) {
                $this->valid = false;
                break;
            }
            $definitions = array_merge($definitions, $cases);
        }

        foreach ($definitions as $definition) {
            $metadata = $this->metadata($definition);
            if ($metadata === null) {
                $this->valid = false;
                continue;
            }

            [$id, $symbol, $module, $action] = $metadata;
            if (isset($bySymbol[$symbol]) || isset($byId[$id])) {
                $this->valid = false;
                continue;
            }

            $bySymbol[$symbol] = $definition;
            $byId[$id] = $definition;

            if ($action !== null) {
                $key = $this->moduleActionKey($module, $action);
                if (isset($byModuleAction[$key])) {
                    $this->valid = false;
                    continue;
                }
                $byModuleAction[$key] = $definition;
            }
        }

        $this->definitionsByModuleAction = $byModuleAction;

        return $this->definitionsBySymbol = $bySymbol;
    }

    private function isValid(): bool
    {
        $this->definitionsBySymbol();

        return $this->valid;
    }

    /** @return list<class-string> */
    private function definitionClasses(): array
    {
        if ($this->definitionClasses !== null) {
            return $this->definitionClasses;
        }

        $configured = config('permissions.definitions', []);

        return $this->definitionClasses = is_array($configured) ? array_values($configured) : [];
    }

    /** @return array{0: int, 1: string, 2: string, 3: ?PermissionAction}|null */
    private function metadata(mixed $definition): ?array
    {
        if (! $definition instanceof PermissionDefinitionContract) {
            return null;
        }

        try {
            $id = $definition->id();
            $symbol = $definition->symbol();
            $module = $definition->module();
            $action = $definition->action();
        } catch (Throwable) {
            return null;
        }

        if ($id < 1
            || preg_match('/\A[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)*\z/', $symbol) !== 1
            || preg_match('/\A[A-Za-z][A-Za-z0-9]*\z/', $module) !== 1) {
            return null;
        }

        $parsedAction = PermissionAction::fromSymbol($symbol);

        return (($action === null && $parsedAction === null)
            || ($action !== null && $action === $parsedAction))
            ? [$id, $symbol, $module, $action]
            : null;
    }

    /** @return array<string, PermissionDefinitionContract> */
    private function definitionsByModuleAction(): array
    {
        if ($this->definitionsByModuleAction === null) {
            $this->definitionsBySymbol();
        }

        return $this->definitionsByModuleAction ?? [];
    }

    private function moduleActionKey(string $module, PermissionAction $action): string
    {
        return $module.'|'.$action->value;
    }
}
