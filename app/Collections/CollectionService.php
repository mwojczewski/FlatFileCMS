<?php

declare(strict_types=1);

namespace FlatFileCms\Collections;

use FlatFileCms\Domain\Content\Page;
use FlatFileCms\Domain\Localization\LanguageConfig;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;

final readonly class CollectionService
{
    public function __construct(private LocalizedDataResolver $localization) {}

    /**
     * @param list<Page> $pages
     * @param array<string, mixed> $query
     */
    public function query(
        Collection $collection,
        array $pages,
        array $query,
        string $locale,
        LanguageConfig $languages,
    ): CollectionResult {
        $pageNumber = $this->pageNumber($query['page'] ?? null);
        $activeFilters = $this->activeFilters($collection, $query);
        $items = array_values(array_filter(
            $pages,
            fn(Page $page): bool => $page->enabled()
                && $this->isDirectChild($page, $collection)
                && $this->matches($page, $activeFilters, $collection, $locale, $languages),
        ));

        usort(
            $items,
            fn(Page $left, Page $right): int => $this->compare(
                $left,
                $right,
                $collection,
                $locale,
                $languages,
            ),
        );

        $totalItems = count($items);
        $totalPages = $totalItems === 0 ? 0 : (int) ceil($totalItems / $collection->perPage());
        $offset = ($pageNumber - 1) * $collection->perPage();
        $pagedItems = array_slice($items, $offset, $collection->perPage());
        $modifiedAt = $collection->modifiedAt();
        foreach ($items as $item) {
            $modifiedAt = max($modifiedAt, $item->modifiedAt());
        }

        return new CollectionResult(
            $collection,
            $pagedItems,
            $pageNumber,
            $collection->perPage(),
            $totalItems,
            $totalPages,
            $activeFilters,
            $modifiedAt,
        );
    }

    private function pageNumber(mixed $value): int
    {
        if ($value === null) {
            return 1;
        }
        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new InvalidCollectionQueryException('Page must be a positive integer.');
        }

        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($page)) {
            throw new InvalidCollectionQueryException('Page is outside the supported integer range.');
        }

        return $page;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, string>
     */
    private function activeFilters(Collection $collection, array $query): array
    {
        $active = [];
        foreach ($collection->filters() as $filter) {
            $value = $query[$filter->parameter()] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if (!is_string($value)) {
                throw new InvalidCollectionQueryException('Collection filter value must be a string.');
            }
            if ($filter->allowedValues() !== [] && !in_array($value, $filter->allowedValues(), true)) {
                throw new InvalidCollectionQueryException(sprintf(
                    'Value for filter "%s" is not allowed.',
                    $filter->parameter(),
                ));
            }

            $active[$filter->parameter()] = $value;
        }

        return $active;
    }

    private function isDirectChild(Page $page, Collection $collection): bool
    {
        $prefix = $collection->identity()->value() . '/';
        $identity = $page->identity()->value();
        if (!str_starts_with($identity, $prefix)) {
            return false;
        }

        return !str_contains(substr($identity, strlen($prefix)), '/');
    }

    /** @param array<string, string> $activeFilters */
    private function matches(
        Page $page,
        array $activeFilters,
        Collection $collection,
        string $locale,
        LanguageConfig $languages,
    ): bool {
        foreach ($collection->filters() as $filter) {
            $expected = $activeFilters[$filter->parameter()] ?? null;
            if ($expected === null) {
                continue;
            }

            $actual = $this->fieldValue($page, $filter->field(), $locale, $languages);
            if (is_array($actual)) {
                $matches = false;
                foreach ($actual as $value) {
                    if ($this->scalarString($value) === $expected) {
                        $matches = true;
                        break;
                    }
                }
                if (!$matches) {
                    return false;
                }
            } elseif ($this->scalarString($actual) !== $expected) {
                return false;
            }
        }

        return true;
    }

    private function compare(
        Page $left,
        Page $right,
        Collection $collection,
        string $locale,
        LanguageConfig $languages,
    ): int {
        $leftValue = $this->fieldValue($left, $collection->sortField(), $locale, $languages);
        $rightValue = $this->fieldValue($right, $collection->sortField(), $locale, $languages);

        if ($leftValue === null && $rightValue !== null) {
            return 1;
        }
        if ($leftValue !== null && $rightValue === null) {
            return -1;
        }

        $comparison = $this->compareValues($leftValue, $rightValue);
        if ($collection->sortDirection() === 'desc') {
            $comparison *= -1;
        }

        return $comparison !== 0
            ? $comparison
            : $left->identity()->value() <=> $right->identity()->value();
    }

    private function fieldValue(
        Page $page,
        string $field,
        string $locale,
        LanguageConfig $languages,
    ): mixed {
        $value = match ($field) {
            'id' => $page->identity()->value(),
            'title' => $page->title($locale, $languages->default()),
            'modifiedAt' => $page->modifiedAt(),
            default => $this->nestedValue($page->attributes(), $field),
        };

        return $this->localization->resolve($value, $locale, $languages);
    }

    /** @param array<string, mixed> $attributes */
    private function nestedValue(array $attributes, string $field): mixed
    {
        $value = $attributes;
        foreach (explode('.', $field) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function compareValues(mixed $left, mixed $right): int
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        $leftString = $this->scalarString($left);
        $rightString = $this->scalarString($right);
        if ($leftString === null && $rightString !== null) {
            return 1;
        }
        if ($leftString !== null && $rightString === null) {
            return -1;
        }

        return ($leftString ?? '') <=> ($rightString ?? '');
    }

    private function scalarString(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            default => null,
        };
    }
}
