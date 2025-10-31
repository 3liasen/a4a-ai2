<?php

declare(strict_types=1);

namespace Axs4allAi\Classification;

final class DecisionSets
{
    public const BINARY = 'binary';
    public const ACCESSIBILITY = 'accessibility';

    /**
     * @return array<int, string>
     */
    public static function options(string $set): array
    {
        $normalized = strtolower(trim($set));

        switch ($normalized) {
            case self::ACCESSIBILITY:
                return ['none', 'limited', 'full'];
            case self::BINARY:
            default:
                return ['yes', 'no'];
        }
    }

    public static function defaultSet(): string
    {
        return self::BINARY;
    }

    public static function normalize(string $set, string $value): ?string
    {
        $options = self::options($set);
        $needle = strtolower(trim($value));

        foreach ($options as $option) {
            if ($needle === $option) {
                return $option;
            }
        }

        return null;
    }
}
