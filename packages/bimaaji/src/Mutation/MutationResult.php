<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Mutation;

final readonly class MutationResult
{
    /** @param list<string> $errors */
    private function __construct(
        public MutationRequest $request,
        public bool $success,
        public array $errors,
    ) {}

    public static function success(MutationRequest $request): self
    {
        return new self($request, true, []);
    }

    /** @param list<string> $errors */
    public static function failure(MutationRequest $request, array $errors): self
    {
        return new self($request, false, $errors);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /** @return array{status: string, request: array<string, mixed>, errors: list<string>} */
    public function toArray(): array
    {
        return [
            'status' => $this->success ? 'success' : 'failure',
            'request' => $this->request->toArray(),
            'errors' => $this->errors,
        ];
    }
}
