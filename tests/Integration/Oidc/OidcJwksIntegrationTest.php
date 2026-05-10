<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Oidc;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class OidcJwksIntegrationTest extends TestCase
{
    private string $repoRoot;
    private string $projectRoot;
    private string $publicKeyPath;
    private string $publicKeyPem;

    protected function setUp(): void
    {
        $this->repoRoot = (string) realpath(__DIR__ . '/../../..');
        $this->projectRoot = sys_get_temp_dir() . '/waaseyaa_oidc_jwks_' . uniqid();

        mkdir($this->projectRoot . '/config', 0o755, true);
        mkdir($this->projectRoot . '/storage', 0o755, true);
        mkdir($this->projectRoot . '/keys', 0o755, true);

        self::assertTrue(symlink($this->repoRoot . '/vendor', $this->projectRoot . '/vendor'));

        $this->publicKeyPath = $this->projectRoot . '/keys/integration-key.pub.pem';
        $this->publicKeyPem = $this->writeRsaPublicKey($this->publicKeyPath);

        file_put_contents($this->projectRoot . '/config/entity-types.php', "<?php\n\nreturn [];\n");
        file_put_contents($this->projectRoot . '/config/waaseyaa.php', $this->buildConfigFile());
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->projectRoot)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->projectRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());
                continue;
            }
            rmdir($item->getPathname());
        }
        rmdir($this->projectRoot);
    }

    #[Test]
    public function jwksEndpointReturnsRsaJwkDerivedFromConfiguredPublicKey(): void
    {
        $response = $this->request('/.well-known/jwks.json');

        self::assertSame(200, $response['status']);

        $body = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertArrayHasKey('keys', $body);
        self::assertCount(1, $body['keys']);

        $jwk = $body['keys'][0];
        self::assertSame('RSA', $jwk['kty']);
        self::assertSame('sig', $jwk['use']);
        self::assertSame('RS256', $jwk['alg']);
        self::assertSame('integration-key', $jwk['kid']);

        $pub = openssl_pkey_get_public($this->publicKeyPem);
        self::assertNotFalse($pub);
        $details = openssl_pkey_get_details($pub);
        self::assertIsArray($details);
        self::assertSame($this->base64UrlEncode($details['rsa']['n']), $jwk['n']);
        self::assertSame($this->base64UrlEncode($details['rsa']['e']), $jwk['e']);
    }

    /**
     * @return array{status:int,headers:list<string>,body:string}
     */
    private function request(string $uri, string $method = 'GET'): array
    {
        $runner = $this->repoRoot . '/tests/Integration/Phase13/Fixtures/http_kernel_runner.php';
        $command = sprintf(
            '%s %s %s %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($runner),
            escapeshellarg($this->repoRoot),
            escapeshellarg($this->projectRoot),
            escapeshellarg($method),
            escapeshellarg($uri),
        );

        $output = shell_exec($command);
        self::assertNotNull($output, 'Kernel runner produced no output.');

        $lines = array_values(array_filter(
            preg_split('/\R/', trim((string) $output)) ?: [],
            static fn(string $line): bool => trim($line) !== '',
        ));
        $jsonPayload = $lines !== [] ? $lines[count($lines) - 1] : '';
        $payload = json_decode($jsonPayload, true);
        self::assertIsArray($payload, 'Kernel runner returned invalid JSON: ' . $output);

        return [
            'status' => (int) ($payload['status'] ?? 0),
            'headers' => is_array($payload['headers'] ?? null) ? array_values($payload['headers']) : [],
            'body' => (string) ($payload['body'] ?? ''),
        ];
    }

    private function writeRsaPublicKey(string $target): string
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($resource, 'Failed to generate RSA key.');

        $details = openssl_pkey_get_details($resource);
        self::assertIsArray($details);

        file_put_contents($target, $details['key']);

        return $details['key'];
    }

    private function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private function buildConfigFile(): string
    {
        $databasePath = $this->projectRoot . '/storage/waaseyaa.sqlite';
        $publicKeyPath = $this->publicKeyPath;

        return <<<PHP
            <?php

            declare(strict_types=1);

            return [
                'database' => '{$databasePath}',
                'environment' => 'local',
                'app' => ['url' => 'http://localhost', 'name' => 'Waaseyaa Oidc JWKS Test'],
                'cors_origins' => ['http://localhost:3000'],
                'oidc' => [
                    'issuer' => 'https://id.example',
                    'signing_keys' => [
                        'integration-key' => [
                            'algorithm' => 'RS256',
                            'public_key_path' => '{$publicKeyPath}',
                        ],
                    ],
                ],
            ];
            PHP;
    }
}
