<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\User;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

class ProductObserver
{
    protected Messaging $messaging;

    /**
     * Inject Kreait Firebase Messaging Service
     */
    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        // Only fetch users who have an active fcm_token
        User::whereNotNull('fcm_token')->chunk(100, function ($users) use ($product) {
            $messages = [];

            foreach ($users as $user) {
                // Construct FCM payload matching Flutter's showAwesomeNotificationFromFCM
                $messages[] = CloudMessage::withTarget('token', $user->fcm_token)
                    ->withData([
                        'title' => 'New Product Added!',
                        'body' => $product->name . ' is now available for $' . number_format($product->price ?? 0, 2),
                        'product_id' => (string) $product->id,
                        'image' => $product->image_url ?? '',
                    ]);
            }

            // Dispatch batch FCM messages
            if (!empty($messages)) {
                $this->messaging->sendAll($messages);
            }
        });
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        //
    }
}