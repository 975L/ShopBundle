<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

// Reads the JSON-LD a product sheet actually serves, which is what the search engine sees - the builder being right proves nothing about a site whose own template stopped calling product_json_ld()
class ProductJsonLdClient
{
    private const string SCRIPT_PATTERN = '#<script[^>]*type\s*=\s*["\']application/ld\+json["\'][^>]*>(.*?)</script>#is';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * The structured data blocks of a page: how many it carries, how many of them do not parse, and the
     * "@type" of everything that does.
     *
     * @return array{blocks: int, invalid: int, types: list<string>}
     */
    public function readStructuredData(string $url): array
    {
        $html = $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; c975l-health-check)'],
            'timeout' => 30,
        ])->getContent();

        $blocks = 0;
        $invalid = 0;
        $types = [];

        if (preg_match_all(self::SCRIPT_PATTERN, $html, $matches)) {
            foreach ($matches[1] as $raw) {
                ++$blocks;
                $decoded = json_decode(trim($raw), true);

                if (!\is_array($decoded)) {
                    ++$invalid;
                    continue;
                }

                $types = [...$types, ...$this->typesOf($decoded)];
            }
        }

        return ['blocks' => $blocks, 'invalid' => $invalid, 'types' => array_values(array_unique($types))];
    }

    /**
     * The "@type" of a decoded block, wherever they sit: on the node itself, on the nodes of a "@graph", or
     * on those of a plain list - the three shapes a valid document is allowed to take.
     *
     * @return list<string>
     */
    private function typesOf(array $decoded): array
    {
        $types = [];

        foreach ((array) ($decoded['@type'] ?? []) as $type) {
            if (\is_string($type)) {
                $types[] = $type;
            }
        }

        foreach ($decoded['@graph'] ?? [] as $node) {
            if (\is_array($node)) {
                $types = [...$types, ...$this->typesOf($node)];
            }
        }

        // A list of nodes rather than a single one: the numeric keys are the nodes themselves
        foreach ($decoded as $key => $node) {
            if (\is_int($key) && \is_array($node)) {
                $types = [...$types, ...$this->typesOf($node)];
            }
        }

        return $types;
    }
}
