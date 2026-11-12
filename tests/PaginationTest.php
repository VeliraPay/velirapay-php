<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use GuzzleHttp\Psr7\Response;
use LogicException;
use VeliraPay\Http\ApiResponse;
use VeliraPay\Page;

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

    public function test_a_page_keeps_the_meta_its_answer_carried(): void
    {
        $this->http
            ->json(self::listResponse([self::fixture('charge')], currentPage: 1, lastPage: 2, perPage: 1, total: 2))
            ->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0, 'mode' => 'test']]);

        $charges = $this->client()->charges->list(['per_page' => 1]);

        $this->assertSame(2, $charges->meta['last_page']);
        $this->assertNull($charges->mode);
        $this->assertSame([], $charges->currencies);

        $activity = $this->client()->activity->list();

        $this->assertSame('test', $activity->mode);
        $this->assertSame('test', $activity->meta['mode']);
    }

    public function test_the_coins_on_offer_say_their_mode_and_the_currencies_prices_can_be_asked_in(): void
    {
        $this->http->json(['data' => [['id' => 'BTC', 'object' => 'asset']], 'meta' => ['mode' => 'live', 'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF', 'TRY']]]);

        $assets = $this->client()->assets->list();

        $this->assertSame('live', $assets->mode);
        $this->assertSame(['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF', 'TRY'], $assets->currencies);
    }

    public function test_the_meta_of_a_list_returned_as_an_array_is_read_from_the_last_response(): void
    {
        $client = $this->client();
        $this->http
            ->json(['data' => [['object' => 'payout_address', 'network' => 'bitcoin', 'name' => 'Bitcoin', 'address' => null, 'assets' => ['BTC']]], 'meta' => ['mode' => 'live', 'api_payout_address_changes' => true]])
            ->json(['data' => [], 'meta' => ['mode' => 'live']]);

        $client->payoutAddresses->list();
        $addresses = $client->lastResponse();
        $client->payouts->summary();
        $summary = $client->lastResponse();

        $this->assertTrue($addresses?->meta()['api_payout_address_changes']);
        $this->assertSame('live', $summary?->meta()['mode']);
        $this->assertSame([], ApiResponse::fromPsr(new Response(204))->meta());
    }

    public function test_a_page_built_without_meta_has_none(): void
    {
        $page = new Page([], 1, 1, 25, 0, static fn (int $number): Page => throw new LogicException("No page {$number}."));

        $this->assertSame([], $page->meta);
        $this->assertNull($page->mode);
        $this->assertSame([], $page->currencies);
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
