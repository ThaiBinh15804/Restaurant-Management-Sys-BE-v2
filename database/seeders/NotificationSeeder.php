<?php

namespace Database\Seeders;

use App\Models\Notification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $notifications = [
            [
                'id' => 'NOTIF1',
                'event_code' => 'NEWSLETTER_SUBSCRIBE',
                'title' => 'Newsletter Subscription',
                'message' => 'Thank you for subscribing to our newsletter!',
                'type' => 0, // System
                'priority' => 1, // Normal
                'channel' => 1, // Email
                'status' => 0, // Pending
            ],
        ];

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }
    }
}