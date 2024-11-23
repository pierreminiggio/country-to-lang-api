<?php

namespace App;

use PierreMiniggio\DatabaseConnection\DatabaseConnection;
use PierreMiniggio\DatabaseFetcher\DatabaseFetcher;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderFactory;

class App
{
    public function run(string $path, ?string $queryParameters): void
    {
        if ($path === '/') {
            http_response_code(404);

            return;
        }

        $country = strtolower(substr($path, 1));

        if (strlen($country) !== 2) {
            http_response_code(404);

            return;
        }

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

        $restCountriesEuStrategy = 'restCountriesEu';
        $ladyJsCountryLanguageStrategy = 'ladyJsCountryLanguage';

        $currentStrategy = $ladyJsCountryLanguageStrategy;

        if ($currentStrategy === $restCountriesEuStrategy) {
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
        } elseif ($currentStrategy === $ladyJsCountryLanguageStrategy) {

            $githubToken = $config['github-token'] ?? null;

            if (! $githubToken) {
                http_response_code(500);
                echo json_encode(['message' => 'Missing github token in config']);

                return;
            }

            set_time_limit(120);
            $actionRunner = (new GithubActionRunStarterAndArtifactDownloaderFactory())->make();
            $artifacts = $actionRunner->runActionAndGetArtifacts(
                $githubToken,
                'pierreminiggio',
                'lady-js-country-language-wrapper',
                'get-language-alpha2-code-by-country-alpha2-code.yml',
                30,
                0,
                [
                    'countryAlpha2Code' => $country
                ]
            );

            if (! $artifacts) {
                http_response_code(500);
                echo json_encode(['message' => 'No artifact']);

                return;
            }

            $artifact = $artifacts[0];

            if (! file_exists($artifact)) {
                http_response_code(500);
                echo json_encode(['message' => 'Missing artifact file']);

                return;
            }

            $artifactContent = trim(file_get_contents($artifact));
            unlink($artifact);

            if (! $artifactContent) {
                http_response_code(500);
                echo json_encode(['message' => 'No artifact content']);

                return;
            }

            if (strlen($artifactContent) !== 2) {
                http_response_code(500);
                echo json_encode(['message' => 'Artifact content is not a language code']);

                return;
            }
            
            $lang = $artifactContent;
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'No set strategy']);

            return;
        }

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
