<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

final class PaginationTest extends TestCase
{
    public function test_the_iterator_walks_every_page_and_skips_objects_it_already_gave(): void
    {
        [$first, $second, $third] = array_map(
            static fn (string $id): array => ['id' => $id] + self::fixture('charge'),
            ['charge000001', 'charge000002', 'charge000003'],
        );

        $this->http
            ->json(self::listResponse([$first, $second], currentPage: 1, lastPage: 2, perPage: 2, total: 4))
            ->json(self::listResponse([$second, $third], currentPage: 2, lastPage: 2, perPage: 2, total: 4));

        $ids = [];

        foreach ($this->client()->charges->iterator(['status' => 'paid', 'per_page' => 2]) as $charge) {
            $ids[] = $charge->id;
        }

        $this->assertSame(['charge000001', 'charge000002', 'charge000003'], $ids);
        $this->assertCount(2, $this->http->requests);
        $this->assertSame(['status' => 'paid', 'per_page' => '2', 'page' => '2'], self::query($this->http->requests[1]));
    }

    public function test_the_last_page_has_no_next_page(): void
    {
        $this->http->json(self::listResponse([self::fixture('charge')]));

        $page = $this->client()->charges->list();

        $this->assertFalse($page->hasMore());
        $this->assertNull($page->nextPage());
        $this->assertCount(1, $this->http->requests);
    }
}
