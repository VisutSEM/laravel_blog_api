<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewProductNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Product $product)
    {
        // Public property promotion automatically sets $this->product
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Send via mail and store in the database notification table
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Product Available: ' . $this->product->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A new product has been added to our catalog.')
            ->line('**Product:** ' . $this->product->name)
            ->line('**Price:** $' . number_format($this->product->price, 2))
            ->action('View Product', url('/products/' . $this->product->id))
            ->line('Thank you for shopping with us!');
    }

    /**
     * Get the array representation for database or API broadcast notifications.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'price' => $this->product->price,
            'message' => 'New product ' . $this->product->name . ' is now available.',
            'url' => url('/products/' . $this->product->id),
        ];
    }
}