<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;

class LoadPackageInfo extends Command
{
    /**
     * Count pages
     */
    const PAGES = 1;

    /**
     * Count elements for page
     */
    const PER_PAGE = 100;

    /**
     * @var string
     */
    protected $signature = 'package:load';

    /**
     * @var string
     */
    protected $description = 'Load top 1000 packages for packagist';

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $packages;

    /**
     * LoadPackageInfo constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->packages = collect();
    }

    /**
     *
     */
    public function handle()
    {
        $client = new Client();

        $requests = function ($total) use ($client) {
            $uri = 'https://packagist.org/explore/popular.json?';
            for ($i = 0; $i < $total; $i++) {
                yield function() use ($client, $uri, $i) {
                    $params = http_build_query([
                        'per_page' => self::PER_PAGE,
                        'page' => $i + 1,
                    ]);

                    return $client->getAsync($uri.$params);
                };
            }
        };

        $pool = new Pool($client, $requests(self::PAGES), [
            'concurrency' => 5,
            'fulfilled'   => function (Response $response, $index) {
                $content = $response->getBody()->getContents();
                $packages = json_decode($content,true);
                $this->packages = $this->packages->merge($packages['packages']);
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $this->packages;
    }
}