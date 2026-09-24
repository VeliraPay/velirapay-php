<?php

declare(strict_types=1);

namespace VeliraPay;

use ArrayIterator;
use Closure;
use Countable;
use Generator;
use IteratorAggregate;
use VeliraPay\Resources\ApiResource;

/**
 * One page of a list, able to fetch the pages after it.
 *
 * @template TResource of ApiResource
 *
 * @implements IteratorAggregate<int, TResource>
 */
final class Page implements Countable, IteratorAggregate
{
    /**
     * Create a new page.
     *
     * @param  list<TResource>  $data
     * @param  Closure(int): Page<TResource>  $fetch  Fetches another page of the same list by number.
     */
    public function __construct(
        /** The objects on this page. */
        public readonly array $data,
        /** The number of this page, from 1. */
        public readonly int $currentPage,
        /** The number of the last page. */
        public readonly int $lastPage,
        /** How many objects a page holds. */
        public readonly int $perPage,
        /** How many objects the whole list holds. */
        public readonly int $total,
        private readonly Closure $fetch,
    ) {
        //
    }

    /**
     * Determine whether there are pages after this one.
     */
    public function hasMore(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Fetch the next page, or null when this is the last.
     *
     * @return Page<TResource>|null
     */
    public function nextPage(): ?self
    {
        return $this->hasMore() ? ($this->fetch)($this->currentPage + 1) : null;
    }

    /**
     * Iterate over the objects on this page and every page after it, fetching each as it is reached.
     *
     * @return Generator<int, TResource>
     */
    public function autoPagingIterator(): Generator
    {
        $seen = [];
        $page = $this;

        while (true) {
            foreach ($page->data as $object) {
                $id = $object->get('id');

                // A record created while paging pushes older ones onto the next page, so they can come round twice.
                if (is_string($id)) {
                    if (isset($seen[$id])) {
                        continue;
                    }

                    $seen[$id] = true;
                }

                yield $object;
            }

            $next = $page->nextPage();

            if ($next === null || $next->currentPage <= $page->currentPage) {
                return;
            }

            $page = $next;
        }
    }

    /**
     * Iterate over the objects on this page.
     *
     * @return ArrayIterator<int, TResource>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Count the objects on this page.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Determine whether this page holds no objects.
     */
    public function isEmpty(): bool
    {
        return $this->data === [];
    }
}
