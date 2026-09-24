<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Enums;

enum ExceptionState: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Posted = 'posted';
    case PostingFailed = 'posting_failed';
    case Resolved = 'resolved';
    case ResolvedNoAction = 'resolved_no_action';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InReview => 'In review',
            self::PendingApproval => 'Pending approval',
            self::Approved => 'Approved',
            self::Posted => 'Posted to ERP',
            self::PostingFailed => 'Posting failed',
            self::Resolved => 'Resolved',
            self::ResolvedNoAction => 'Resolved (no action)',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Resolved, self::ResolvedNoAction], true);
    }

    public function hasAdjustmentInFlight(): bool
    {
        return in_array($this, [self::PendingApproval, self::Approved, self::Posted, self::PostingFailed], true);
    }

    public static function openStates(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s): bool => ! $s->isClosed()));
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InReview, self::ResolvedNoAction, self::Resolved],
            self::InReview => [self::PendingApproval, self::ResolvedNoAction, self::Resolved],
            self::PendingApproval => [self::Approved, self::InReview],
            self::Approved => [self::Posted, self::PostingFailed],
            self::PostingFailed => [self::Posted, self::PostingFailed],
            self::Posted => [self::Resolved],
            self::Resolved, self::ResolvedNoAction => [self::Open],
        };
    }
}
