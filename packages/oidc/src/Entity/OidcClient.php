<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Entity;

use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\Hydration\HydratableFromStorageInterface;
use Waaseyaa\Entity\Hydration\HydrationContext;

/**
 * The OidcClient content entity.
 *
 * Models a relying-party client registered with the OIDC issuer (Minoo, biindigen, etc.).
 * The `client_id` field is the stable public identifier per OIDC spec — its value is
 * user-defined at creation time and never rewritten. The `id` column is an internal
 * auto-increment primary key for entity-system consistency.
 */
final class OidcClient extends ContentEntityBase implements HydratableFromStorageInterface
{
    private const ENTITY_TYPE_ID = 'oidc_client';

    /**
     * @var array<string, string>
     */
    private const ENTITY_KEYS = [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'name',
    ];

    /**
     * @var array<string, string|array<string, mixed>>
     */
    protected array $casts = [
        'is_confidential' => 'bool',
    ];

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $entityKeys
     * @param array<string, mixed> $fieldDefinitions
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        $values += [
            'redirect_uris' => [],
            'scopes' => ['openid'],
            'grant_types' => ['authorization_code'],
            'is_confidential' => false,
            'client_secret_hash' => null,
        ];

        $entityTypeId = $entityTypeId !== '' ? $entityTypeId : self::ENTITY_TYPE_ID;
        $entityKeys = $entityKeys !== [] ? $entityKeys : self::ENTITY_KEYS;

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }

    public static function fromStorage(array $values, HydrationContext $context): static
    {
        return new self(
            values: $values,
            entityTypeId: $context->entityTypeId,
            entityKeys: $context->entityKeys,
            fieldDefinitions: [],
        );
    }

    protected function duplicateInstance(array $values): static
    {
        return new static(
            values: $values,
            entityTypeId: $this->getEntityTypeId(),
            entityKeys: $this->entityKeys,
            fieldDefinitions: $this->getFieldDefinitions(),
        );
    }

    public function getClientId(): string
    {
        return (string) ($this->get('client_id') ?? '');
    }

    public function setClientId(string $clientId): static
    {
        return $this->set('client_id', $clientId);
    }

    public function getName(): string
    {
        return (string) ($this->get('name') ?? '');
    }

    public function setName(string $name): static
    {
        return $this->set('name', $name);
    }

    /**
     * @return string[]
     */
    public function getRedirectUris(): array
    {
        $uris = $this->get('redirect_uris');

        return \is_array($uris) ? array_values(array_filter($uris, 'is_string')) : [];
    }

    /**
     * @param string[] $uris
     */
    public function setRedirectUris(array $uris): static
    {
        return $this->set('redirect_uris', array_values($uris));
    }

    /**
     * Byte-for-byte exact match per OIDC spec §3.1.2.1.
     */
    public function hasRedirectUri(string $redirectUri): bool
    {
        return \in_array($redirectUri, $this->getRedirectUris(), true);
    }

    /**
     * @return string[]
     */
    public function getScopes(): array
    {
        $scopes = $this->get('scopes');

        return \is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [];
    }

    /**
     * @param string[] $scopes
     */
    public function setScopes(array $scopes): static
    {
        return $this->set('scopes', array_values($scopes));
    }

    public function hasScope(string $scope): bool
    {
        return \in_array($scope, $this->getScopes(), true);
    }

    /**
     * @return string[]
     */
    public function getGrantTypes(): array
    {
        $grantTypes = $this->get('grant_types');

        return \is_array($grantTypes) ? array_values(array_filter($grantTypes, 'is_string')) : [];
    }

    /**
     * @param string[] $grantTypes
     */
    public function setGrantTypes(array $grantTypes): static
    {
        return $this->set('grant_types', array_values($grantTypes));
    }

    public function supportsGrantType(string $grantType): bool
    {
        return \in_array($grantType, $this->getGrantTypes(), true);
    }

    public function isConfidential(): bool
    {
        return (bool) ($this->get('is_confidential') ?? false);
    }

    public function setConfidential(bool $confidential): static
    {
        return $this->set('is_confidential', $confidential);
    }

    public function getClientSecretHash(): ?string
    {
        $hash = $this->get('client_secret_hash');

        return \is_string($hash) && $hash !== '' ? $hash : null;
    }

    public function setClientSecretHash(?string $hash): static
    {
        return $this->set('client_secret_hash', $hash);
    }
}
