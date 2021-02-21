<?php

namespace App;

use PierreMiniggio\DatabaseConnection\DatabaseConnection;
use PierreMiniggio\DatabaseFetcher\DatabaseFetcher;

class App
{
    public function run(string $path, ?string $queryParameters): void
    {
        if ($path === '/') {
            http_response_code(404);

            return;
        }

        $country = strtolower(substr($path, 1));

        $config = require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config.php';
        $dbConfig = $config['db'];
        $fetcher = new DatabaseFetcher(new DatabaseConnection(
            $dbConfig['host'],
            $dbConfig['database'],
            $dbConfig['username'],
            $dbConfig['password']
        ));

        $queriedIds = $fetcher->query(
            $fetcher
                ->createQuery('unprocessable_request')
                ->select('id')
                ->where('request = :request')
            ,
            ['request' => $country]
        );

        if ($queriedIds) {
            http_response_code(404);

            return;
        }

        $fetchedLang = $this->findLangIfPresent($fetcher, $country);

        if ($fetchedLang) {
            http_response_code(200);
            echo json_encode($fetchedLang);

            return;
        }

        $curl = curl_init('https://restcountries.eu/rest/v2/alpha/' . $country);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);

        if ($result === false) {
            http_response_code(500);

            return;
        }

        $jsonResponse = json_decode($result);
        if (! empty($jsonResponse->status)) {
            $fetcher->exec(
                $fetcher
                    ->createQuery('unprocessable_request')
                    ->insertInto('request', ':request')
                ,
                ['request' => $country]
            );
            http_response_code(404);

            return;
        }

        if (
            ! isset($jsonResponse->languages)
            || ! isset($jsonResponse->languages[0])
            || ! isset($jsonResponse->languages[0]->iso639_1)
        ) {
            http_response_code(500);

            return;
        }

        $lang = $jsonResponse->languages[0]->iso639_1;

        $fetcher->exec(
            $fetcher
                ->createQuery('country_lang')
                ->insertInto(
                    'country, lang',
                    ':country, :lang'
                )
            ,
            [
                'country' => $country,
                'lang' => $lang
            ]
        );
        
        $fetchedLang = $this->findLangIfPresent($fetcher, $country);

        if ($fetchedLang) {
            http_response_code(200);
            echo json_encode($fetchedLang);

            return;
        }

        http_response_code(500);
    }

    protected function findLangIfPresent(DatabaseFetcher $fetcher, string $country): ?array
    {
        $fetchedCountries = $fetcher->query(
            $fetcher
                ->createQuery('country_lang')
                ->select('lang')
                ->where('country = :country')
            ,
            ['country' => $country]
        );

        if (! $fetchedCountries) {
            return null;
        }

        return ['lang' => $fetchedCountries[0]['lang']];
    }
}
