<?php

namespace NexusAuth\Tests\Unit\Services;

use Firebase\JWT\JWT;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use NexusAuth\Exceptions\ExternalIdTokenException;
use NexusAuth\Services\JwksIdTokenVerifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * JwksIdTokenVerifierのUnitテスト
 *
 * テスト用のRSA鍵で署名したIDトークンを、偽の公開鍵セット（JWKS）で検証する。
 * 有効期限は firebase/php-jwt が time() で確かめるため、トークンの時刻も time() から作る。
 */
class JwksIdTokenVerifierTest extends TestCase
{
    private const JWKS_URI = 'https://idp.example.com/keys';

    private const ISSUER = 'https://idp.example.com';

    private const AUDIENCE = 'com.example.game';

    private HttpFactory $http;

    private CacheRepository $cache;

    private string $privateKey;

    /** @var array<string, string> */
    private array $publicJwk;

    private int $jwksRequestCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->privateKey, $this->publicJwk] = $this->generateKeyPair('key-1');

        $this->cache = new CacheRepository(new ArrayStore);
        $this->http = new HttpFactory;
        $this->http->fake([
            self::JWKS_URI => function () {
                $this->jwksRequestCount++;

                return HttpFactory::response(['keys' => [$this->publicJwk]]);
            },
        ]);
    }

    #[Test]
    public function 正しいトークンならsubを返す(): void
    {
        $subject = $this->verifier()->verify('apple', $this->sign(['sub' => 'apple-user-1']));

        $this->assertSame('apple-user-1', $subject);
    }

    #[Test]
    public function 公開鍵セットはキャッシュして取り直さない(): void
    {
        $verifier = $this->verifier();
        $verifier->verify('apple', $this->sign(['sub' => 'a']));
        $verifier->verify('apple', $this->sign(['sub' => 'b']));

        $this->assertSame(1, $this->jwksRequestCount);
    }

    #[Test]
    public function 鍵が入れ替わったら一度だけ取り直して検証する(): void
    {
        $verifier = $this->verifier();
        $verifier->verify('apple', $this->sign(['sub' => 'a']));

        // IdP 側で鍵を入れ替える
        [$this->privateKey, $this->publicJwk] = $this->generateKeyPair('key-2');

        $this->assertSame('b', $verifier->verify('apple', $this->sign(['sub' => 'b'], 'key-2')));
        $this->assertSame(2, $this->jwksRequestCount);
    }

    #[Test]
    public function 発行者が違えば拒否する(): void
    {
        $this->expectInvalidToken();

        $this->verifier()->verify('apple', $this->sign(['sub' => 'a', 'iss' => 'https://evil.example.com']));
    }

    #[Test]
    public function 発行先が自分のアプリでなければ拒否する(): void
    {
        $this->expectInvalidToken();

        $this->verifier()->verify('apple', $this->sign(['sub' => 'a', 'aud' => 'com.other.app']));
    }

    #[Test]
    public function 発行先が配列でも自分のアプリを含めば受け付ける(): void
    {
        $subject = $this->verifier()->verify('apple', $this->sign(['sub' => 'a', 'aud' => ['x', self::AUDIENCE]]));

        $this->assertSame('a', $subject);
    }

    #[Test]
    public function 期限切れなら拒否する(): void
    {
        $this->expectInvalidToken();

        $this->verifier()->verify('apple', $this->sign(['sub' => 'a', 'exp' => time() - 3600]));
    }

    #[Test]
    public function 他の鍵で署名されていれば拒否する(): void
    {
        [$otherPrivateKey] = $this->generateKeyPair('key-1');
        $token = JWT::encode($this->claims(['sub' => 'a']), $otherPrivateKey, 'RS256', 'key-1');

        $this->expectInvalidToken();

        $this->verifier()->verify('apple', $token);
    }

    #[Test]
    public function subが無ければ拒否する(): void
    {
        $this->expectInvalidToken();

        $this->verifier()->verify('apple', $this->sign([]));
    }

    #[Test]
    public function 形式が壊れていれば拒否する(): void
    {
        $this->expectInvalidToken();

        $this->verifier()->verify('apple', 'not-a-jwt');
    }

    #[Test]
    public function 設定に無い種類は未対応として拒否する(): void
    {
        $this->expectExceptionObject(ExternalIdTokenException::unsupportedProvider('line'));

        $this->verifier()->verify('line', $this->sign(['sub' => 'a']));
    }

    #[Test]
    public function 発行先が設定されていない種類は未対応として拒否する(): void
    {
        $verifier = new JwksIdTokenVerifier($this->http, $this->cache, [
            'apple' => ['jwks_uri' => self::JWKS_URI, 'issuers' => [self::ISSUER], 'audiences' => []],
        ]);

        try {
            $verifier->verify('apple', $this->sign(['sub' => 'a']));
            $this->fail('例外が出るべき');
        } catch (ExternalIdTokenException $e) {
            $this->assertSame(ExternalIdTokenException::CODE_UNSUPPORTED_PROVIDER, $e->getErrorCode());
        }
    }

    private function verifier(): JwksIdTokenVerifier
    {
        return new JwksIdTokenVerifier($this->http, $this->cache, [
            'apple' => ['jwks_uri' => self::JWKS_URI, 'issuers' => [self::ISSUER], 'audiences' => [self::AUDIENCE]],
        ]);
    }

    private function expectInvalidToken(): void
    {
        $this->expectException(ExternalIdTokenException::class);
        $this->expectExceptionMessage('IDトークンを検証できません');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function sign(array $overrides, string $keyId = 'key-1'): string
    {
        return JWT::encode($this->claims($overrides), $this->privateKey, 'RS256', $keyId);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function claims(array $overrides): array
    {
        return [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'iat' => time(),
            'exp' => time() + 600,
            ...$overrides,
        ];
    }

    /**
     * @return array{0: string, 1: array<string, string>} 秘密鍵（PEM）と公開鍵（JWK）
     */
    private function generateKeyPair(string $keyId): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);

        return [$privateKey, [
            'kty' => 'RSA',
            'kid' => $keyId,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
            'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
        ]];
    }
}
