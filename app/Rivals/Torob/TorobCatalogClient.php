<?php

namespace App\Rivals\Torob;

use App\Enums\RivalSource;
use App\Rivals\Contracts\RivalCatalogClient;
use App\Rivals\Data\RivalPriceReport;
use App\Rivals\Data\RivalSearchCandidate;
use App\Support\Decimal;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Live Torob HTTP adapter. Disabled unless RIVALS_TOROB_ENABLED=true.
 *
 * @see docs/spikes/torob-snapp-rival-fetch.md
 */
class TorobCatalogClient implements RivalCatalogClient
{
    public function source(): RivalSource
    {
        return RivalSource::Torob;
    }

    public function search(string $query): array
    {
        if (! config('rivals.torob_enabled')) {
            return [];
        }

        $this->throttle();

        try {
            $response = $this->http()->get('/v4/base-product/search/', [
                'q' => $query,
                'page' => 0,
            ]);

            if (! $response->successful()) {
                Log::warning('Torob search failed', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return [];
            }

            /** @var list<array<string, mixed>> $results */
            $results = $response->json('results') ?? [];

            return array_values(array_filter(array_map(
                function (array $row): ?RivalSearchCandidate {
                    $identity = (string) ($row['random_key'] ?? $row['prk'] ?? '');
                    $title = (string) ($row['name1'] ?? $row['name'] ?? '');
                    if ($identity === '' || $title === '') {
                        return null;
                    }

                    $price = isset($row['price']) ? (string) $row['price'] : null;

                    return new RivalSearchCandidate(
                        listingIdentity: $identity,
                        title: $title,
                        price: $price !== null && $price !== '' ? Decimal::normalize($price) : null,
                        url: isset($row['web_client_absolute_url']) ? (string) $row['web_client_absolute_url'] : null,
                        imageUrl: isset($row['image_url']) ? (string) $row['image_url'] : null,
                        searchId: isset($row['search_id']) ? (string) $row['search_id'] : null,
                    );
                },
                $results,
            )));
        } catch (Throwable $e) {
            Log::warning('Torob search exception', ['message' => $e->getMessage()]);

            return [];
        }
    }

    public function fetchPrices(string $listingIdentity, ?string $searchId = null): ?RivalPriceReport
    {
        if (! config('rivals.torob_enabled')) {
            return null;
        }

        $this->throttle();

        try {
            $query = ['prk' => $listingIdentity];
            if ($searchId !== null && $searchId !== '') {
                $query['search_id'] = $searchId;
            }

            $response = $this->http()->get('/v4/base-product/details/', $query);

            if (! $response->successful()) {
                Log::warning('Torob details failed', [
                    'status' => $response->status(),
                    'listing' => $listingIdentity,
                ]);

                return null;
            }

            $payload = $response->json() ?? [];
            $sellers = $payload['products_info']['result'] ?? $payload['products'] ?? [];
            $prices = [];

            if (is_array($sellers)) {
                foreach ($sellers as $seller) {
                    if (! is_array($seller)) {
                        continue;
                    }
                    $price = $seller['price'] ?? $seller['discounted_price'] ?? null;
                    if ($price !== null && $price !== '' && preg_match('/^\d+(\.\d+)?$/', (string) $price)) {
                        $prices[] = Decimal::normalize((string) $price);
                    }
                }
            }

            if ($prices === [] && isset($payload['price'])) {
                $prices[] = Decimal::normalize((string) $payload['price']);
            }

            if ($prices === []) {
                return null;
            }

            sort($prices, SORT_STRING);
            usort($prices, fn (string $a, string $b): int => Decimal::compare($a, $b));

            $cheapest = $prices[0];
            $median = $this->median($prices);
            $title = (string) ($payload['name1'] ?? $payload['name'] ?? $listingIdentity);

            return new RivalPriceReport(
                listingIdentity: $listingIdentity,
                cheapestPrice: $cheapest,
                medianPrice: $median,
                competitorCount: count($prices),
                listingTitle: $title,
                currency: 'IRR',
            );
        } catch (Throwable $e) {
            Log::warning('Torob details exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('rivals.http.torob_base_url'))
            ->timeout((int) config('rivals.http.timeout'))
            ->withHeaders([
                'User-Agent' => (string) config('rivals.http.user_agent'),
                'Accept' => 'application/json',
            ]);
    }

    private function throttle(): void
    {
        $ms = (int) config('rivals.http.min_interval_ms', 1000);
        $key = 'rivals:torob:last_request_at';
        $last = Cache::get($key);
        if (is_int($last)) {
            $elapsed = (int) ((microtime(true) * 1000) - $last);
            if ($elapsed < $ms) {
                usleep(($ms - $elapsed) * 1000);
            }
        }
        Cache::put($key, (int) (microtime(true) * 1000), 60);
    }

    /**
     * @param  list<string>  $prices
     */
    private function median(array $prices): string
    {
        $n = count($prices);
        if ($n === 0) {
            return '0';
        }

        $mid = intdiv($n, 2);
        if ($n % 2 === 1) {
            return $prices[$mid];
        }

        return Decimal::div(Decimal::add($prices[$mid - 1], $prices[$mid]), '2');
    }
}
