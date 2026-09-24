<?php

namespace App\Services;

use App\Support\CycleStatus;
use App\Support\ReviewStatus;
use Illuminate\Support\Facades\DB;

/**
 * Domain service encapsulating review authority, status evaluation,
 * and review cycle eligibility calculations.
 */
class PerformanceService
{
    /**
     * Constrain a review query to reviews visible to the actor.
     */
    public function scopeToVisibleReviews($query, ?string $actorId)
    {
        $actor = (string) ($actorId ?? '');

        return $query->where(function ($q) use ($actor) {
            $q->where('pr.employee_id', $actor)
                ->orWhere('pr.reviewer_id', $actor)
                ->orWhere('e.supervisor_id', $actor);
        });
    }

    /**
     * Determine if an employee ID can view a given review record.
     */
    public function canViewReview(object $review, ?string $actorId): bool
    {
        if (! $actorId) {
            return false;
        }

        if ($review->employee_id === $actorId || ($review->reviewer_id ?? null) === $actorId) {
            return true;
        }

        return DB::table('employees')->where('employee_id', $review->employee_id)->value('supervisor_id') === $actorId;
    }

    /**
     * Determine if an actor employee ID may score a review.
     */
    public function canScoreReview(object $review, ?string $actorId): bool
    {
        return $actorId !== null
            && ($review->reviewer_id ?? null) === $actorId
            && $review->employee_id !== $actorId;
    }

    /**
     * Check if a review's cycle has ended, freezing it from edits.
     */
    public function reviewIsFrozen(object $review): bool
    {
        return ReviewStatus::cycleHasEnded($review->cycle_end_date ?? null);
    }

    /**
     * Stamp review collection or paginator with effective status and can_score flag.
     */
    public function markScoreable($reviews, ?string $actorId)
    {
        $stamp = function ($review) use ($actorId) {
            $review->effective_status = ReviewStatus::of($review->status ?? null, $review->cycle_end_date ?? null);
            $review->can_score = $review->effective_status !== ReviewStatus::COMPLETED
                && $this->canScoreReview($review, $actorId);

            return $review;
        };

        return method_exists($reviews, 'through') ? $reviews->through($stamp) : $reviews->map($stamp);
    }

    /**
     * Stamp cycle collection or paginator with effective status.
     */
    public function markCycleStatus($cycles)
    {
        $stamp = function ($cycle) {
            $cycle->effective_status = CycleStatus::of($cycle->status ?? null, $cycle->end_date ?? null);

            return $cycle;
        };

        return method_exists($cycles, 'through') ? $cycles->through($stamp) : $cycles->map($stamp);
    }
}
