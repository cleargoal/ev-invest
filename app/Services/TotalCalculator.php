<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class TotalCalculator
{

    /**
     * Get any change of total pool from 'payments' and recalculate it
     * @param Payment $payment
     * @return bool
     */
    public function calculateTotal(Payment $payment): bool
    {
        $lastRecord = Total::orderBy('id', 'desc')->first();
        $newAmount = $lastRecord ? $lastRecord->amount + $payment->amount : $payment->amount;
        $newRecord = new Total();
        $newRecord->payment_id = $payment->id;
        $newRecord->amount = $newAmount;
        $newRecord->created_at = $payment->created_at;
        $newRecord->save();
        return $newRecord->amount;
    }

    /**
     * Calculate contributions of all investors
     *
     */
    public function contributions($paymentId, $createdAt)
    {
        $usersWithLastContributions = User::with('lastContribution')->get();
        $totalAmount = $usersWithLastContributions->sum(function ($user) {
            return optional($user->lastContribution)->amount ?? 0;
        });

        foreach ($usersWithLastContributions as $user) {
            $lastContribution = $user->lastContribution;

            if ($lastContribution && $totalAmount > 0) {
                $userContributionPercent = ($lastContribution->amount / $totalAmount) * 1000000; // Percents have precision 99.9999
                $newContribution = new Contribution();
                $newContribution->user_id = $lastContribution->user_id;
                $newContribution->payment_id = $paymentId;
                $newContribution->percents = $userContributionPercent;
                $newContribution->amount = $lastContribution->amount;
                $newContribution->created_at = $createdAt;
                $newContribution->save();
            }
        }
        return $totalAmount;
    }

    public function processing($payment): true
    {
        $lastContrib = Contribution::where('user_id', $payment->user_id )->orderBy('id', 'desc')->first();

        $newContribution = new Contribution();
        $newContribution->payment_id = $payment->id;
        $newContribution->user_id = $payment->user_id;
        $newContribution->percents = $lastContrib ? $lastContrib->percents : 0;
        $newContribution->amount = $lastContrib ? $lastContrib->amount + $payment->amount : $payment->amount;
        $newContribution->save();

        $this->calculateTotal($payment);
        $this->contributions($payment->id, $payment->created_at);
        return true;
    }

    /**
     *  Create Payment
     * @param array $payData
     * @param bool $addIncome - gets true, when income calculated; in this case 'processing' method run from sellVehicle method
     * @return Payment
     */
    public function createPayment(array $payData, bool $addIncome = false): Payment
    {
        $newPay = new Payment();
        $newPay->fill($payData);
        $newPay->save();

        if (!$addIncome && $newPay->confirmed) { // IF not add investors income; It's when car sold
            $this->processing($newPay);
        }
        return $newPay;
    }

    /**
     * Create New vehicle bought
     * @param array $vehData - data of new vehicle
     * @return Vehicle
     */
    public function buyVehicle(array $vehData): Vehicle
    {
        $vehicle = new Vehicle();
        $vehicle->fill($vehData);
        $vehicle->save();

        $payData = [
            'user_id' => $vehData['user_id'],
            'operation_id' => 2,
            'amount' => $vehData['cost'],
            'confirmed' => true,
            'created_at' => $vehData['created_at'],
        ];
        $this->createPayment($payData);

        return $vehicle;
    }

    public function sellVehicle($vehicle, $saleDate, $actualPrice): true
    {
        $vehicle->sale_date = $saleDate; // TODO: is it better to use Carbon::now()?
        $vehicle->price = $actualPrice;
        $vehicle->profit = $vehicle->price - $vehicle->cost;

        $saleDate = Carbon::parse($vehicle->sale_date);
        $createdAt = Carbon::parse($vehicle->created_at);
        $duration = $createdAt->diffInDays($saleDate); // sale duration in days

        $vehicle->sale_duration = $duration;
        $vehicle->save();

        $this->investIncome($vehicle);

        $paymentData = [
            'user_id' => $vehicle->user_id,
            'operation_id' => 3,
            'amount' => -($vehicle->price),
            'confirmed' => true,
            'created_at' => $vehicle->sale_date,
        ];
        $this->createPayment($paymentData); // data of sold car only
        return true;
    }

    /**
     * Calculate invest income for every investor
     * @param Vehicle $vehicle
     * @return int it's count investors (actually this return not necessary)
     */
    public function investIncome($vehicle): int
    {
        $investors = User::with('lastContribution')->get();
        foreach ($investors as $investor) {
            if (isset($investor->lastContribution)) {
                $payData = [
                    'user_id' => $investor->lastContribution->user_id,
                    'operation_id' => 6,
                    'amount' => $vehicle->profit * $investor->lastContribution->percents / 1000000,
                    'confirmed' => true,
                    'created_at' => $vehicle->sale_date,
                ];
                $this->createPayment($payData, true); // true prevents to change the Total until all data have been stored
            }
        }
        return $investors->count();
    }
}
