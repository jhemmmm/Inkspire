<?php

namespace App\Enums;

/**
 * What a Type A file check concluded, and therefore where the job order goes.
 *
 * The distinction exists because "this file is unusable" and "this file needs
 * work" call for different people. Only the customer can supply a missing
 * source file; only an artist can raise a real one to print quality.
 */
enum FileValidationOutcome: string
{
    /** Print-ready. Straight to production, and to the Cashier for payment. */
    case Passed = 'passed';

    /**
     * Wrong format or over the size cap — nothing the shop can fix. The
     * counter asks the customer for a different file.
     */
    case Rejected = 'rejected';

    /**
     * Real artwork, but too low-resolution for the size ordered. An artist
     * can upscale, redraw or clean it up, so it joins their pool.
     */
    case NeedsArtist = 'needs_artist';
}
