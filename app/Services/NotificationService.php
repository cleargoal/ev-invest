<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\NewPaymentNotify;
use App\Notifications\LeasingIncomeNotification;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Send new payment notification to appropriate users based on environment
     */
    public function notifyNewPayment(): void
    {
        $users = $this->getNotificationTargetUsers();
        Notification::send($users, new NewPaymentNotify());
    }

    /**
     * Send leasing income notification to appropriate users based on environment
     */
    public function notifyLeasingIncome(): void
    {
        $users = $this->getNotificationTargetUsers();
        Notification::send($users, new LeasingIncomeNotification());
    }

    /**
     * Get the appropriate users to notify based on environment
     * In production: notify company users
     * In local/development: notify admin users for testing
     * 
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function getNotificationTargetUsers(): \Illuminate\Database\Eloquent\Collection
    {
        if (config('app.env') !== 'local') {
            return User::role('company')->get();
        }
        
        return User::role('admin')->get();
    }
}