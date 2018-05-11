<?php

namespace Lullabot\Mpx\Service\AccessManagement;

use Cache\Adapter\Common\CacheItem;
use Lullabot\Mpx\DataService\IdInterface;
use Lullabot\Mpx\Normalizer\UriNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Resolve all service URLs for an account.
 *
 * @see https://docs.theplatform.com/help/wsf-resolvedomain-method
 */
class ResolveDomain extends ResolveBase
{
    /**
     * The method endpoint (this is not a true REST service).
     */
    const RESOLVE_DOMAIN_URL = 'https://access.auth.theplatform.com/web/Registry/resolveDomain';

    /**
     * The schema version of resolveDomain.
     */
    const SCHEMA_VERSION = '1.0';

    /**
     * Resolve all URLs for an account.
     *
     * @param IdInterface $account The account to resolve service URLs for.
     *
     * @return ResolveDomainResponse A response with the service URLs.
     */
    public function resolve(IdInterface $account): ResolveDomainResponse
    {
        $key = md5($account->getId().static::SCHEMA_VERSION);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $options = [
            'query' => [
                'schema' => static::SCHEMA_VERSION,
                '_accountId' => (string) $account->getId(),
                'account' => (string) $account->getId(),
            ],
        ];

        $response = $this->authenticatedClient->request('GET', static::RESOLVE_DOMAIN_URL, $options);

        $encoders = [new JsonEncoder()];
        $normalizers = [new UriNormalizer(), new ObjectNormalizer(null, null, null, new \Lullabot\Mpx\DataService\ResolveDomainResponseExtractor()), new ArrayDenormalizer()];
        $serializer = new Serializer($normalizers, $encoders);

        $response = $serializer->deserialize($response->getBody(), ResolveDomainResponse::class, 'json');
        $item = new CacheItem($key);
        $item->set($response);

        // thePlatform provides no guidance on how long we can cache this for.
        // Since many of their examples and other mpx clients hardcode these
        // values, we assume 30 days and that they will implement redirects or
        // domain aliases if required.
        $item->expiresAfter(new \DateInterval('P30D'));
        $this->cache->save($item);

        return $response;
    }
}
