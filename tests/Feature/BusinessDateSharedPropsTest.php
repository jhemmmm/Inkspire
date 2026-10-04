<?php

use Inertia\Testing\AssertableInertia as Assert;

test('shared page props use the business date across the UTC year boundary', function () {
    $this->travelTo('2026-12-31 16:30:00');

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('businessTimezone', 'Asia/Manila')
        ->where('businessDate', '2027-01-01'));
});
