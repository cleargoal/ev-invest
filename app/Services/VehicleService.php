<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\BoughtAutoEvent;
use App\Events\TotalChangedEvent;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class VehicleService
{

    public function __construct(
        protected VehicleService $vehicleService,
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
        $vehicle = $this->updateVehicleWhenSold($vehicle, $actualPrice, $saleDate);

        $payment = $this->companyCommissions($vehicle);
        $totalAmount = $this->totalService->createTotal($payment);
        $this->investIncome($vehicle);
        TotalChangedEvent::dispatch($totalAmount, 'Продано авто. Прибуток:', $vehicle->profit);

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
     * @return Payment
     */
    public function companyCommissions(Vehicle $vehicle): Payment
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

        return $this->paymentService->createPayment((array)$payData, true); // true prevents to change the Total until all data have been stored
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
                $this->paymentService->createPayment((array)$payData, true); // true prevents to change the Total until all data have been stored
            }
        }
        return $investors->count();
    }

}
