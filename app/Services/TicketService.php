<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Ticket;
use Illuminate\Support\Str;

class TicketService
{
    public function createTicket(int $registrationId): Ticket
    {
        $registration = Registration::query()->findOrFail($registrationId);

        $existingTicket = $registration->ticket;
        if ($existingTicket !== null) {
            return $existingTicket;
        }

        $ticketNumber = $this->generateUniqueTicketNumber();

        $ticket = Ticket::query()->create([
            'registration_id' => $registration->id,
            'ticket_number' => $ticketNumber,
        ]);

        $registration->forceFill([
            'ticket_number' => $ticketNumber,
        ])->save();

        return $ticket;
    }

    private function generateUniqueTicketNumber(): string
    {
        do {
            $ticketNumber = 'TKT-' . strtoupper(Str::random(10));
        } while (Ticket::query()->where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
    }
}
