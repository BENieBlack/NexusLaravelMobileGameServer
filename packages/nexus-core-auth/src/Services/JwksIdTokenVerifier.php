<?php

namespace NexusAuth\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use NexusAuth\Contracts\ExternalIdTokenVerifierInterface;
use NexusAuth\Exceptions\ExternalIdTokenException;
use Throwable;

/**
 * JwksIdTokenVerifier
 *
 * 公開鍵セット（JWKS）を公開している外部アカウントのIDトークンを検証する。
 * Apple（Sign in with Apple）と Google（Google Sign-In）はどちらもこの形式。
 *
 * 公開鍵セットはキャッシュする。トークンの kid がキャッシュに無いときは
 * 鍵が入れ替わった直後とみなし、一度だけ取り直す。
 */
class JwksIdTokenVerifier implements ExternalIdTokenVerifierInterface
{
    /**
     * @param  array<string, array{jwks_uri: string, issuers: list<string>, audiences: list<string>}>  $providers  種類ごとの検証設定
     * @param  int  $cacheSeconds  公開鍵セットをキャッシュする秒数
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly array $providers,
        private readonly int $cacheSeconds = 3600,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function verify(string $provider, string $idToken): string
    {
        $config = $this->providers[$provider] ?? null;

        // 発行先（アプリのクライアントID）が無いと、他アプリ向けのトークンも通ってしまう
        if ($config === null || $config['audiences'] === []) {
            throw ExternalIdTokenException::unsupportedProvider($provider);
        }

        $keySet = $this->fetchKeySet($config['jwks_uri'], refresh: false);
        $keyId = $this->readKeyId($idToken);

        if ($keyId !== null && ! isset($keySet[$keyId])) {
            $keySet = $this->fetchKeySet($config['jwks_uri'], refresh: true);
        }

        try {
            // 署名と exp / nbf / iat を確かめる
            $payload = JWT::decode($idToken, $keySet);
        } catch (Throwable $e) {
            throw ExternalIdTokenException::invalidToken($provider, $e->getMessage());
        }

        if (! in_array($payload->iss ?? null, $config['issuers'], true)) {
            throw ExternalIdTokenException::invalidToken($provider, 'issuer mismatch');
        }

        $audiences = (array) ($payload->aud ?? []);

        if (array_intersect($audiences, $config['audiences']) === []) {
            throw ExternalIdTokenException::invalidToken($provider, 'audience mismatch');
        }

        $subject = $payload->sub ?? null;

        if (! is_string($subject) || $subject === '') {
            throw ExternalIdTokenException::invalidToken($provider, 'subject missing');
        }

        return $subject;
    }

    /**
     * 公開鍵セットを取得する
     *
     * @return array<string, Key> kid => 公開鍵
     */
    private function fetchKeySet(string $jwksUri, bool $refresh): array
    {
        $cacheKey = 'nexus_auth:jwks:'.sha1($jwksUri);

        if ($refresh) {
            $this->cache->forget($cacheKey);
        }

        /** @var array<string, mixed> $jwks */
        $jwks = $this->cache->remember(
            $cacheKey,
            $this->cacheSeconds,
            fn () => $this->http->timeout(5)->get($jwksUri)->throw()->json(),
        );

        // 鍵に alg が無い場合は RS256 とみなす（Apple / Google とも RS256）
        return JWK::parseKeySet($jwks, 'RS256');
    }

    /**
     * トークンのヘッダーから kid を読む。読めなければnull（検証は JWT::decode に任せる）
     */
    private function readKeyId(string $idToken): ?string
    {
        $header = explode('.', $idToken)[0];

        try {
            $decoded = json_decode(JWT::urlsafeB64Decode($header), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) && is_string($decoded['kid'] ?? null) ? $decoded['kid'] : null;
    }
}
