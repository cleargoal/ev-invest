<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationType;
use App\Models\Leasing;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\LeasingIncomeNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeasingService
{

    public function __construct(
        protected PaymentService $paymentService,
        protected TotalService $totalService,
        protected Leasing $leasing
    ) {}


    public function getLeasing(array $data): Leasing
    {
        return DB::transaction(function () use ($data) {
            $data['created_at'] = $data['created_at'] ?? Carbon::now();
            $leasing = $this->createLeasing($data);

            $payment = $this->companyCommissions($leasing);
            $totalAmount = $this->totalService->createTotal($payment);
            $this->investIncome($leasing);

            // Notifications are sent after successful transaction
            $this->notify();
            
            return $leasing;
        });
    }

    protected function createLeasing(array $data): Leasing
    {
        $leasing = new Leasing();
        $leasing->fill($data);
        $leasing->save();
        return $leasing;
    }

    protected function notify(): void
    {
        if (config('app.env') !== 'local') {
            $users = User::role('investor')->get();
        }
        else {
            $users = User::role('admin')->get();
        }
        Notification::send($users, new LeasingIncomeNotification());
    }
    /**
     * Company commissions add to Payment
     * @param Leasing $leasing
     * @return Payment
     */
    public function companyCommissions(Leasing $leasing): Payment
    {
        $companyId = User::role('company')->first()->id;
        $commissions = $leasing->price / 2; // 1/2 of profit is company's commissions
        $payData = [
            'user_id' => $companyId,
            'operation_id' => OperationType::C_LEASING, // company commissions
            'amount' => $commissions,
            'confirmed' => true,
            'created_at' => $leasing->created_at, // TODO: date depends on leasing date
        ];

        return $this->paymentService->createPayment((array)$payData, true); // true prevents to change the Total until all data have been stored
    }

    /**
     * Calculate invest income for every investor
     * @param Leasing $leasing
     * @return int it's count investors (actually this return not necessary)
     */
    public function investIncome(Leasing $leasing): int
    {
        $profitForShare = $leasing->price / 2; // 1/2 of profit is company's commissions
        $investors = User::with('lastContribution')->get();
        foreach ($investors as $investor) {
            if (isset($investor->lastContribution)) {
                $payData = [
                    'user_id' => $investor->lastContribution->user_id,
                    'operation_id' => OperationType::I_LEASING,
                    'amount' => $profitForShare * $investor->lastContribution->percents / 1000000,
                    'confirmed' => true,
                    'created_at' => $leasing->created_at,
                ];
                $this->paymentService->createPayment((array)$payData, true); // true prevents to change the Total until all data have been stored
            }
        }
        return $investors->count();
    }

}
