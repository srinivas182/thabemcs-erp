<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Contracts;

use App\Domains\Workflow\Models\ApprovalRequest;

/**
 * Anything that goes through the approval engine (requisitions, purchase orders,
 * and later variations and payments).
 */
interface Approvable
{
    public function approvalTitle(): string;

    public function approvalUrl(): string;

    public function onApprovalGranted(ApprovalRequest $request): void;

    public function onApprovalRejected(ApprovalRequest $request, ?string $comment): void;
}
