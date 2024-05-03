<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

class CalculationService
{

    /**
     * Get any change of total pool from 'payments' and recalculate it
     * @param Payment $payment
     * @return bool
     */
    public function calculateTotal(Payment $payment): int
    {
        $lastRecord = Total::orderBy('id', 'desc')->first();
        $newRecord = new Total();
        $newRecord->amount = $lastRecord ? $lastRecord->amount + $payment->amount : $payment->amount;;
        $newRecord->payment_id = $payment->id;
        $newRecord->created_at = $payment->created_at;
        $newRecord->save();
        return $newRecord->amount;
    }

    /**
     * Calculate contributions of all investors
     * @param int $paymentId
     * @param Date $createdAt
     * @return int
     */
    public function contributions(int $paymentId, Date $createdAt): int
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

    /**
     * Process calculation of Total and contributions
     * @param Payment $payment
     * @return true
     */
    public function processing(Payment $payment): true
    {
        $this->createContribution($payment);
        $this->calculateTotal($payment);
        $this->contributions($payment->id, $payment->created_at);
        return true;
    }

    /**
     * Create contribution
     * @param Payment $payment
     * @return Contribution
     */
    protected function createContribution(Payment $payment): Contribution
    {
        $lastContrib = Contribution::where('user_id', $payment->user_id )->orderBy('id', 'desc')->first();

        $newContribution = new Contribution();
        $newContribution->payment_id = $payment->id;
        $newContribution->user_id = $payment->user_id;
        $newContribution->percents = $lastContrib ? $lastContrib->percents : 0;
        $newContribution->amount = $lastContrib ? $lastContrib->amount + $payment->amount : $payment->amount;
        $newContribution->save();
        return $newContribution;
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
        $vehicle = $this->createVehicle($vehData);

        $payData = [
            'user_id' => $vehData['user_id'],
            'operation_id' => 2,
            'amount' => $vehData['cost'],
            'confirmed' => true,
        ];

        if (isset($vehData['created_at'])) {
            $payData['created_at'] = $vehData['created_at'];
        }

        $this->createPayment($payData);

        return $vehicle;
    }

    /**
     * Create Vehicle
     * @param array $vehData
     * @return Vehicle
     */
    protected function createVehicle(array $vehData): Vehicle
    {
        $vehicle = new Vehicle();
        $vehicle->fill($vehData);
        $vehicle->save();
        return $vehicle;
    }

    /**
     * Sell Vehicle
     * @param Vehicle $vehicle
     * @param int $actualPrice
     * @param Date|null $saleDate - is for seeding only
     * @return Vehicle
     */
    public function sellVehicle(Vehicle $vehicle, int $actualPrice, Date $saleDate = null): Vehicle
    {
        $this->updateVehicleWhenSold($vehicle, $actualPrice, $saleDate);
        $this->investIncome($vehicle);

        $paymentData = [
            'user_id' => $vehicle->user_id,
            'operation_id' => 3,
            'amount' => -($vehicle->price),
            'confirmed' => true,
            'created_at' => $vehicle->sale_date,
        ];
        $this->createPayment($paymentData); // data of sold car only
        return $vehicle;
    }

    /**
     * Update Vehicle when sold
     * @param Vehicle $vehicle
     * @param $actualPrice
     * @param $saleDate
     * @return Vehicle
     */
    protected function updateVehicleWhenSold(Vehicle $vehicle, $actualPrice, $saleDate): Vehicle
    {
        $saleDate = $saleDate ? Carbon::parse($saleDate) : Carbon::now();
        $createdAt = Carbon::parse($vehicle->created_at);
        $duration = $createdAt->diffInDays($saleDate); // sale duration in days

        $vehicle->sale_date = $saleDate ?? Carbon::now();
        $vehicle->price = $actualPrice;
        $vehicle->profit = $vehicle->price - $vehicle->cost;
        $vehicle->sale_duration = $duration;
        $vehicle->save();
        return $vehicle;
    }

    /**
     * Calculate invest income for every investor
     * @param Vehicle $vehicle
     * @return int it's count investors (actually this return not necessary)
     */
    public function investIncome(Vehicle $vehicle): int
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
