<?php

namespace App\Service\List;

use Symfony\Component\HttpFoundation\Request;

final readonly class ActiveStatusFilter
{
    private function __construct(
        public bool $includeActive,
        public bool $includeInactive,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $includeActive = $request->query->getInt('active', 1) === 1;
        $includeInactive = $request->query->getInt('inactive', 0) === 1;

        if (!$includeActive && !$includeInactive) {
            return new self(true, true);
        }

        return new self($includeActive, $includeInactive);
    }

    /**
     * @return array{active: int, inactive: int}
     */
    public function queryParameters(): array
    {
        return [
            'active' => $this->includeActive ? 1 : 0,
            'inactive' => $this->includeInactive ? 1 : 0,
        ];
    }
}
