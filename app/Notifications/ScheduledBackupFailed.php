<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\BackupJob;
use App\Models\DatabaseConnection;

class ScheduledBackupFailed extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public BackupJob $backupJob)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $connection = DatabaseConnection::findOrFail($this->backupJob->database_connection_id);
        return (new MailMessage)
            ->error()
            ->subject('❌ Scheduled Backup Failed')
            ->line("A scheduled backup for Database #{$connection->db_name} has failed.")
            ->line('Error: ' . $this->backupJob->error_message)
            ->line('Backup ID: ' . $this->backupJob->id)
            ->line('Time: ' . $this->backupJob->completed_at);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
