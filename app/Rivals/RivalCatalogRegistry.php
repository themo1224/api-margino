<?php

namespace App\Rivals;

use App\Enums\RivalSource;
use App\Rivals\Contracts\RivalCatalogClient;
use App\Rivals\Fake\FakeRivalCatalogClient;
use App\Rivals\Snapp\SnappCatalogClient;
use App\Rivals\Torob\TorobCatalogClient;
use InvalidArgumentException;

class RivalCatalogRegistry
{
    public function __construct(
        private readonly TorobCatalogClient $torob,
        private readonly SnappCatalogClient $snapp,
        private readonly FakeRivalCatalogClient $fake,
    ) {}

    /**
     * @return list<RivalCatalogClient>
     */
    public function enabledClients(): array
    {
        $driver = (string) config('rivals.driver', 'fake');

        if ($driver === 'fake') {
            $clients = [new FakeRivalCatalogClient(RivalSource::Torob)];
            if (config('rivals.snapp_enabled')) {
                $clients[] = new FakeRivalCatalogClient(RivalSource::Snapp);
            }

            return $clients;
        }

        $clients = [];
        if (config('rivals.torob_enabled')) {
            $clients[] = $this->torob;
        }
        if (config('rivals.snapp_enabled')) {
            $clients[] = $this->snapp;
        }

        return $clients !== [] ? $clients : [new FakeRivalCatalogClient(RivalSource::Torob)];
    }

    public function clientFor(RivalSource $source): RivalCatalogClient
    {
        foreach ($this->enabledClients() as $client) {
            if ($client->source() === $source) {
                return $client;
            }
        }

        throw new InvalidArgumentException("No rival catalog client for {$source->value}");
    }
}
