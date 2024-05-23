<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class CalculationService
{

    /**
     * Create New vehicle bought
     * @param array $vehData - data of new vehicle
     * @return Vehicle
     */
    public function buyVehicle(array $vehData): Vehicle
    {
        $vehicle = $this->createVehicle($vehData);
        $operatorId = User::role('operator')->first()->id;

        $payData = [
            'user_id' => $operatorId,
            'operation_id' => 2,
            'amount' => $vehData['cost'],
            'confirmed' => true,
        ];

        if (isset($vehData['created_at'])) { // if data created in seeder
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
     * @param Carbon|null $saleDate - is for seeding only
     * @return Vehicle
     */
    public function sellVehicle(Vehicle $vehicle, int $actualPrice, Carbon $saleDate = null): Vehicle
    {
        $vehicle = $this->updateVehicleWhenSold($vehicle, $actualPrice, $saleDate);
        $this->companyCommissions($vehicle);
        $this->investIncome($vehicle);
        $operatorId = User::role('operator')->first()->id;

        $paymentData = [
            'user_id' => $operatorId,
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
        $soldDate = $saleDate ? Carbon::parse($saleDate) : Carbon::now();
        $createdAt = Carbon::parse($vehicle->created_at);
        $duration = $createdAt->diffInDays($soldDate); // sale duration in days

        $vehicle->sale_date = $soldDate;
        $vehicle->price = $actualPrice;
        $vehicle->profit = $vehicle->price - $vehicle->cost;
        $vehicle->sale_duration = $duration;
        $vehicle->save();
        return $vehicle;
    }

    /**
     * Company commissions add to Payment
     * @param Vehicle $vehicle
     * @return int
     */
    public function companyCommissions(Vehicle $vehicle): int
    {
        $companyId = User::role('company')->first()->id;
        $commissions = $vehicle->profit/2; // 1/2 of profit is company's commissions
        $payData = [
            'user_id' => $companyId,
            'operation_id' => 7,
            'amount' => $commissions,
            'confirmed' => true,
            'created_at' => $vehicle->sale_date,
        ];
        $this->createPayment((array)$payData, true); // true prevents to change the Total until all data have been stored
        return $commissions;
    }

    /**
     * Calculate invest income for every investor
     * @param Vehicle $vehicle
     * @return int it's count investors (actually this return not necessary)
     */
    public function investIncome(Vehicle $vehicle): int
    {
        $profitForShare = $vehicle->profit/2; // 1/2 of profit is company's commissions
        $investors = User::with('lastContribution')->get();
        foreach ($investors as $investor) {
            if (isset($investor->lastContribution)) {
                $payData = [
                    'user_id' => $investor->lastContribution->user_id,
                    'operation_id' => 6,
                    'amount' => $profitForShare * $investor->lastContribution->percents / 1000000,
                    'confirmed' => true,
                    'created_at' => $vehicle->sale_date,
                ];
                $this->createPayment((array)$payData, true); // true prevents to change the Total until all data have been stored
            }
        }
        return $investors->count();
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

        if ($payData['operation_id'] !== 7) {
            $this->processing($newPay, $addIncome);
        }
        return $newPay;
    }

    /**
     * Process calculation of Total and contributions
     * @param Payment $payment
     * @param bool $addIncome
     * @return true
     */
    public function processing(Payment $payment, bool $addIncome = false): true
    {
        if ($payment->confirmed) {
            $this->createContribution($payment);
            $this->calculateTotal($payment);
        }

        if (!$addIncome && $payment->confirmed) { // IF not add investors income when car sold. Just for operations of investors add or withdrawal
            $this->contributions($payment->id, $payment->created_at);
        }
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
     * Get any change of total pool from 'payments' and recalculate it
     * @param Payment $payment
     * @return int
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
     * @param Carbon $createdAt
     * @return int
     */
    public function contributions(int $paymentId, Carbon $createdAt): int
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

}
