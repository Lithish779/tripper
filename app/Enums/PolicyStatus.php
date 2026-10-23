<?php

namespace App\Enums;

enum PolicyStatus: string
{
    case RECEIVED = 'RECEIVED';
    case VALIDATED = 'VALIDATED';
    case SUBMITTED = 'SUBMITTED';
    case PENDING_CONFIRMATION = 'PENDING_CONFIRMATION';
    case ISSUED = 'ISSUED';
    case FAILED = 'FAILED';
    case NEEDS_RECONCILIATION = 'NEEDS_RECONCILIATION';

    /**
     * Determine whether this state is terminal.
     * ISSUED is strictly terminal and must never regress or be re-issued.
     */
    public function isTerminal(): bool
    {
        return $this === self::ISSUED;
    }

    /**
     * Check if transition to target state is legally permissible.
     */
    public function canTransitionTo(self $target): bool
    {
        // No self-transitions allowed unless explicitly needed
        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::RECEIVED => in_array($target, [
                self::VALIDATED,
                self::FAILED,
            ], true),

            self::VALIDATED => in_array($target, [
                self::SUBMITTED,
                self::FAILED,
            ], true),

            self::SUBMITTED => in_array($target, [
                self::ISSUED,
                self::PENDING_CONFIRMATION,
                self::NEEDS_RECONCILIATION,
                self::FAILED,
            ], true),

            self::PENDING_CONFIRMATION => in_array($target, [
                self::ISSUED,
                self::NEEDS_RECONCILIATION,
                self::FAILED,
            ], true),

            self::NEEDS_RECONCILIATION => in_array($target, [
                self::ISSUED,
                self::FAILED,
                self::SUBMITTED, // If re-submitting after clearing status
            ], true),

            self::FAILED => in_array($target, [
                self::SUBMITTED, // For authorized retries
            ], true),

            // ISSUED cannot regress to ANY previous state
            self::ISSUED => false,
        };
    }
}
