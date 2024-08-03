<?php

namespace App\Services;

use App\Events\BoughtAutoEvent;
use App\Events\TotalChangedEvent;
use App\Mail\BoughtAutoMail;
use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class CalculationService
{

    protected Total $total;
    protected Vehicle $vehicle;

    /**
     * Create New vehicle bought
     * @param array $vehData - data of new vehicle
     * @return Vehicle
     */
    public function buyVehicle(array $vehData): Vehicle
    {
        $vehData['created_at'] = isset($vehData['created_at']) && $vehData['created_at'] !== null ? $vehData['created_at'] : Carbon::now();
        $vehicle = $this->createVehicle($vehData);
        BoughtAutoEvent::dispatch('Придбано авто:', $vehicle);

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
        TotalChangedEvent::dispatch($this->total->amount, 'Продано авто. Прибуток:', $vehicle->profit);

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
        $soldDate = $saleDate ? Carbon::parse($saleDate) : Carbon::now(); // check if 'sale_date' is sent from caller, for example from seeder
        $createdAt = Carbon::parse($vehicle->created_at);
        $duration = $createdAt->diffInDays($soldDate); // sale duration in days

        $vehicle->sale_date ??= $soldDate;
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
        $commissions = $vehicle->profit / 2; // 1/2 of profit is company's commissions
        $payData = [
            'user_id' => $companyId,
            'operation_id' => 7, // company commissions
            'amount' => $commissions,
            'confirmed' => true,
            'created_at' => $vehicle->sale_date,
        ];
        $payment = $this->createPayment((array)$payData, true); // true prevents to change the Total until all data have been stored
        $this->createTotal($payment);

        return $commissions;
    }

    /**
     * Calculate invest income for every investor
     * @param Vehicle $vehicle
     * @return int it's count investors (actually this return not necessary)
     */
    public function investIncome(Vehicle $vehicle): int
    {
        $profitForShare = $vehicle->profit / 2; // 1/2 of profit is company's commissions
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
        }

        if (!$addIncome && $payment->confirmed) { // IF not add investors income when car sold. Just for operations of investors add or withdrawal
            $this->contributions($payment->id, $payment->created_at);
        }

        return true;
    }

    /**
     * Payment confirmation. Payment has been created by Livewire from Investor panel, but rest operations have to be run here
     * @param Payment $payment
     * @return void
     */
    public function paymentConfirmation(Payment $payment): void
    {
        $this->processing($payment);
        $this->createTotal($payment);
        TotalChangedEvent::dispatch($this->total->amount, 'Внесок інвестора', $payment->amount);
    }

    /**
     * Create contribution
     * @param Payment $payment
     * @return Contribution
     */
    protected function createContribution(Payment $payment): Contribution
    {
        $lastContrib = Contribution::where('user_id', $payment->user_id)->orderBy('id', 'desc')->first();

        $newContribution = new Contribution();
        $newContribution->payment_id = $payment->id;
        $newContribution->user_id = $payment->user_id;
        $newContribution->percents = $lastContrib ? $lastContrib->percents : 0;
        $newContribution->amount = $lastContrib ? $lastContrib->amount + $payment->amount : $payment->amount;
        $newContribution->save();
        User::where('id', $payment->user_id)->update(['actual_contribution' => $newContribution->amount]);
        return $newContribution;
    }

    /**
     * Get any change of total pool from 'payments' and recalculate it
     * @param Payment $payment
     * @return int
     */
    public function createTotal(Payment $payment): int
    {
        $lastRecord = Total::orderBy('id', 'desc')->first();
        $this->total = new Total();
        $this->total->amount = $lastRecord ? $lastRecord->amount + $payment->amount : $payment->amount;
        $this->total->payment_id = $payment->id;
        $this->total->created_at = $payment->created_at;
        $this->total->save();

        return $this->total->amount;
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

    /**
     * Actual Total
     * @return float|int
     */
    public function actualTotal(): float|int
    {
        return Total::orderBy('id', 'desc')->first()->amount/100;
    }

}
