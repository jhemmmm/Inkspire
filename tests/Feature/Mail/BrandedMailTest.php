<?php

use App\Mail\PaymentRequested;
use App\Models\JobOrder;

test('a customer email carries the shop logo, the ink bar and the shop footer', function () {
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1500]);

    $html = (new PaymentRequested($jobOrder))->render();

    expect($html)
        ->toContain('src="'.asset('logo.png').'"')
        ->toContain('Squarefoot Graphics &amp; Ads')
        // The theme is inlined, so the brand colours show up as styles: the
        // primary button and the first cell of the four-colour bar.
        ->toContain('background-color: #1a3a8f')
        ->toContain('background-color: #0a87a3')
        // The message itself is untouched by the new chrome.
        ->toContain('Pay online')
        ->toContain('1,500.00');
});
