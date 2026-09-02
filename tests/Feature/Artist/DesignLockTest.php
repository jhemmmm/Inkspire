<?php

use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('sending a design for review while the design file is locked returns 422 and creates no new revision_logs row', function () {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_design']);
    DesignFile::factory()->for($jobOrder)->locked()->create();

    $countBefore = RevisionLog::where('job_order_id', $jobOrder->id)->count();

    $response = $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), [
        'file' => UploadedFile::fake()->image('design.png'),
    ]);

    $response->assertStatus(422);
    expect(RevisionLog::where('job_order_id', $jobOrder->id)->count())->toBe($countBefore);
});
