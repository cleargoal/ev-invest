<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationType;
use App\Events\BoughtAutoEvent;
use App\Events\TotalChangedEvent;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VehicleService
{
    private const PERCENTAGE_PRECISION = 1000000; // Percents have precision 99.9999
    private const COMPANY_COMMISSION_RATE = 0.5; // 50% commission rate

    public function __construct(
        protected PaymentService $paymentService,
        protected TotalService $totalService,
        protected Vehicle $vehicle
    ) {}

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
        return DB::transaction(function () use ($vehicle, $actualPrice, $saleDate) {
            $vehicle = $this->updateVehicleWhenSold($vehicle, $actualPrice, $saleDate);

            $payment = $this->companyCommissions($vehicle);
            $totalAmount = $this->totalService->createTotal($payment);
            $this->investIncome($vehicle);
            
            // Events are dispatched after successful transaction
            TotalChangedEvent::dispatch($totalAmount, 'Продано авто. Прибуток:', $vehicle->profit);

            return $vehicle;
        });
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
     * @return Payment
     */

    public function companyCommissions(Vehicle $vehicle): Payment
    {
        $companyUser = User::role('company')->first();
        if (!$companyUser) {
            throw new \InvalidArgumentException('Company user not found. Please ensure a user with "company" role exists.');
        }

        if (!$vehicle->profit || $vehicle->profit <= 0) {
            throw new \InvalidArgumentException('Vehicle must have a positive profit to calculate commissions.');
        }

        $commissions = $vehicle->profit * self::COMPANY_COMMISSION_RATE;

        $payData = [
            'user_id' => $companyUser->id,
            'operation_id' => OperationType::REVENUE,
            'amount' => $commissions,
            'confirmed' => true,
            'created_at' => $vehicle->sale_date,
        ];

        return $this->paymentService->createPayment((array)$payData, true);
    }

    /**
     * Calculate invest income for every investor
     * @param Vehicle $vehicle
     * @return int it's count investors (actually this return not necessary)
     */
    public function investIncome(Vehicle $vehicle): int
    {
        if (!$vehicle->profit || $vehicle->profit <= 0) {
            // No profit to distribute, return 0 investors processed
            return 0;
        }

        $profitForShare = $vehicle->profit * self::COMPANY_COMMISSION_RATE; // Remaining profit for investor distribution
        $investors = User::with('lastContribution')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'investor');
            })
            ->get();
            
        $processedInvestors = 0;
        foreach ($investors as $investor) {
            if (isset($investor->lastContribution) && $investor->lastContribution->percents > 0) {
                $incomeAmount = $profitForShare * $investor->lastContribution->percents / self::PERCENTAGE_PRECISION;
                
                // Only create payment if income amount is meaningful (> 0.01)
                if ($incomeAmount >= 0.01) {
                    $payData = [
                        'user_id' => $investor->lastContribution->user_id,
                        'operation_id' => OperationType::INCOME,
                        'amount' => $incomeAmount,
                        'confirmed' => true,
                        'created_at' => $vehicle->sale_date,
                    ];
                    $this->paymentService->createPayment((array)$payData, true);
                    $processedInvestors++;
                }
            }
        }
        return $processedInvestors;
    }

}
