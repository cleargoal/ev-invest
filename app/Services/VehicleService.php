<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationType;
use App\Events\BoughtAutoEvent;
use App\Events\TotalChangedEvent;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Traits\HandlesInvestmentCalculations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VehicleService
{
    use HandlesInvestmentCalculations;

    public function __construct(
        protected PaymentService $paymentService,
        protected TotalService $totalService,
        protected VehicleCancellationService $cancellationService,
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
        if (!$vehicle->profit || $vehicle->profit <= 0) {
            throw new \InvalidArgumentException('Vehicle must have a positive profit to calculate commissions.');
        }

        return $this->createCompanyCommissionPayment(
            $vehicle->profit,
            OperationType::REVENUE,
            $vehicle->sale_date,
            $this->paymentService
        );
    }

    /**
     * Calculate invest income for every investor
     * @param Vehicle $vehicle
     * @return int it's count investors (actually this return not necessary)
     */
    public function investIncome(Vehicle $vehicle): int
    {
        return $this->distributeIncomeToInvestors(
            $vehicle->profit ?? 0,
            OperationType::INCOME,
            $vehicle->sale_date,
            $this->paymentService
        );
    }

    /**
     * Cancel a vehicle sale ("unsell" the vehicle)
     * This is a high-level wrapper around the cancellation service
     * 
     * @param Vehicle $vehicle
     * @param string|null $reason
     * @return bool
     * @throws \Throwable
     */
    public function unsellVehicle(Vehicle $vehicle, ?string $reason = null): bool
    {
        $cancelledBy = auth()->user();
        
        return $this->cancellationService->cancelVehicleSale($vehicle, $reason, $cancelledBy);
    }

    /**
     * Restore a cancelled vehicle sale
     * 
     * @param Vehicle $vehicle
     * @return bool
     * @throws \Throwable
     */
    public function restoreVehicleSale(Vehicle $vehicle): bool
    {
        return $this->cancellationService->restoreVehicleSale($vehicle);
    }

}
