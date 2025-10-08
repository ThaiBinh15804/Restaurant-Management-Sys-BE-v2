<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'customer@restaurant.com')->first();
        
        if (!$user) {
            throw new \Exception('User with email customer@restaurant.com not found');
        }

        $customer = $user->customerProfile;

        $reservations = [
            [
                'id' => 'RES1',
                'customer_id' => $customer->id,
                'reserved_at' => '2025-10-07 18:00:00',
                'number_of_people' => 4,
                'status' => 0, // Pending
                'notes' => 'Window seat preferred',
            ],
        ];

        foreach ($reservations as $reservation) {
            Reservation::create($reservation);
        }
    }
}