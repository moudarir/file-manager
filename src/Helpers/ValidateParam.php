<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Helpers;

use DateTimeImmutable;
use DateTimeInterface;
use Moudarir\FileManager\Exceptions\FileManagerException;
use ValueError;

final class ValidateParam
{

    /**
     * @throws FileManagerException
     */
    public static function notLessThan(array $config, array|string $params, int $value = 0): void
    {
        $params = is_array($params) ? $params : [$params];

        foreach ($params as $param) {
            if (array_key_exists($param, $config) === false) {
                throw FileManagerException::invalidParam($param);
            }

            if (is_int($config[$param]) === false || $config[$param] < $value) {
                throw FileManagerException::invalidParam($param);
            }
        }
    }

    /**
     * @throws FileManagerException
     */
    public static function isBetween(array $config, array|string $params, array $values = [1, 100]): void
    {
        $params = is_array($params) ? $params : [$params];

        foreach ($params as $param) {
            if (array_key_exists($param, $config) === false) {
                throw FileManagerException::invalidParam($param);
            }

            if (
                is_int($config[$param]) === false ||
                ($config[$param] >= $values[0] && $config[$param] <= $values[1]) === false
            ) {
                throw FileManagerException::invalidParam($param);
            }
        }
    }

    /**
     * @throws FileManagerException
     */
    public static function isBoolean(array $config, array|string $params): void
    {
        $params = is_array($params) ? $params : [$params];

        foreach ($params as $param) {
            if (array_key_exists($param, $config) === false) {
                throw FileManagerException::invalidParam($param);
            }

            if (is_bool($config[$param]) === false) {
                throw FileManagerException::invalidParam($param);
            }
        }
    }

    /**
     * Checks if an array contains exclusively instances of a specific enum.
     *
     * @param string $enumClass The fully qualified class name of the enum (e.g., MyEnum::class)
     * @param array $cases The array of elements to test
     * @param bool $emptyArrayIsValid Defines whether an empty array should be considered valid
     *
     * @throws FileManagerException If the provided class name is not a valid Enum
     */
    public static function containsOnlyEnumCases(
        string $enumClass,
        array $cases = [],
        bool $emptyArrayIsValid = true
    ): bool
    {
        // Guard clause: Ensure the provided string is a valid Enum
        if (!enum_exists($enumClass)) {
            throw FileManagerException::generic(
                sprintf('"%s" is not a valid Enum.', $enumClass)
            );
        }

        if ($cases === []) {
            return $emptyArrayIsValid;
        }

        return array_all($cases, static fn ($item): bool => $item instanceof $enumClass);
    }

    /**
     * Validates the configuration custom date and date format.
     *
     * @throws FileManagerException If any of the configuration values are invalid
     */
    public static function configDate(array $config): void
    {
        $customDate = $config['customDate'] ?? null;
        $dateFormat = $config['dateFormat'] ?? null;

        self::customDate($customDate);

        if ($dateFormat !== null) {
            if (is_string($dateFormat) === false) {
                throw FileManagerException::invalidParam('dateFormat');
            }

            // Use a temporary valid date object.
            $testDate = new DateTimeImmutable();

            try {
                // PHP will throw a ValueError if the format string contains invalid characters/structures
                $testDate->format($dateFormat);
            } catch (ValueError $exception) {
                throw FileManagerException::generic(
                    sprintf('The `dateFormat` "%s" is invalid.', $dateFormat),
                    $exception
                );
            }
        }
    }

    /**
     * @throws FileManagerException
     */
    public static function customDate(mixed $customDate = null): void
    {
        if ($customDate !== null && $customDate instanceof DateTimeInterface === false) {
            throw FileManagerException::generic(
                "The `customDate` configuration must be null or an instance of DateTimeInterface."
            );
        }
    }
}
