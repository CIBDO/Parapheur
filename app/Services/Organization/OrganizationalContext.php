<?php

namespace App\Services\Organization;

use App\Enums\AccessScopeType;

class OrganizationalContext
{
    /**
     * @param  list<int>  $structureIds
     */
    public function __construct(
        public readonly ?int $assignmentId,
        public readonly AccessScopeType $scope,
        public readonly ?int $structureId,
        public readonly ?int $unitStructureId,
        public readonly array $structureIds,
        public readonly bool $synthetic,
    ) {}
}
