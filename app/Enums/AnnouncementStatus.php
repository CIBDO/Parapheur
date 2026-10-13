<?php

namespace App\Enums;

enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case PendingValidation = 'pending_validation';
    case ToCorrect = 'to_correct';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Expired = 'expired';
    case Archived = 'archived';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isPublic(): bool
    {
        return in_array($this, [self::Published, self::Expired, self::Archived], true);
    }
}
