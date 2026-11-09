<?php

namespace Tests\Feature;

use App\Actions\PencilBookings\ConvertPencilBookingToReservationRequest;
use App\Models\PencilBooking;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PencilBookingConversionConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_concurrent_conversion_attempt_cannot_pass_the_booking_row_lock(): void
    {
        $original = DB::getDefaultConnection();
        config()->set('database.connections.pgsql_conversion_owner', config('database.connections.pgsql'));
        config()->set('database.connections.pgsql_conversion_attempt', config('database.connections.pgsql'));
        $owner = DB::connection('pgsql_conversion_owner');
        $attempt = DB::connection('pgsql_conversion_attempt');
        $booking = null;
        $actor = null;

        try {
            DB::setDefaultConnection('pgsql_conversion_owner');
            $actor = User::factory()->create();
            $booking = PencilBooking::create([
                'idempotency_key' => (string) Str::uuid(),
                'status' => 'active',
                'customer_name' => 'Concurrent Customer',
                'customer_mobile' => '09170000000',
                'event_date' => now('Asia/Manila')->addDays(20)->toDateString(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $owner->beginTransaction();
            $owner->table('pencil_bookings')->where('id', $booking->id)->lockForUpdate()->first();
            $attempt->statement("SET lock_timeout TO '200ms'");
            DB::setDefaultConnection('pgsql_conversion_attempt');

            try {
                app(ConvertPencilBookingToReservationRequest::class)->handle(request(), $actor, $booking, [], []);
                $this->fail('A concurrent conversion must wait for the pencil booking row lock.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('lock timeout', strtolower($exception->getMessage()));
            }
        } finally {
            DB::setDefaultConnection('pgsql_conversion_owner');
            if ($owner->transactionLevel() > 0) {
                $owner->rollBack();
            }
            if ($booking !== null) {
                $owner->table('pencil_bookings')->where('id', $booking->id)->delete();
            }
            if ($actor !== null) {
                $owner->table('users')->where('id', $actor->id)->delete();
            }
            $attempt->statement("SET lock_timeout TO '0'");
            DB::setDefaultConnection($original);
            DB::purge('pgsql_conversion_owner');
            DB::purge('pgsql_conversion_attempt');
        }
    }
}
