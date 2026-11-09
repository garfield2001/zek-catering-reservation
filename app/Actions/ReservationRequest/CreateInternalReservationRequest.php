<?php

namespace App\Actions\ReservationRequest;

use App\Models\PencilBooking;
use App\Models\ReservationRequest;
use App\Models\User;
use Illuminate\Http\Request;

class CreateInternalReservationRequest
{
    public function __construct(private SubmitReservationRequest $submit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $selection
     * @return array{request: ReservationRequest, created: bool, access_code: ?string}
     */
    public function handle(Request $httpRequest, User $actor, array $data, array $selection, string $origin = 'manual', ?PencilBooking $pencilBooking = null): array
    {
        return $this->submit->handle($httpRequest, $data, $selection, $actor, $origin, $pencilBooking);
    }
}
