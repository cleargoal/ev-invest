<?php

namespace App\Services;

use App\Events\TotalChangedEvent;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class CalculationService
{

    protected Total $total;

    public function __construct(
        protected VehicleService $vehicleService,
        protected PaymentService $paymentService,
        protected TotalService $totalService,
        protected Vehicle $vehicle
    ) {}

    /**
     * Sell Vehicle
     * @param Vehicle $vehicle
     * @param int $actualPrice
     * @param Carbon|null $saleDate - is for seeding only
     * @return Vehicle
     */
    public function sellVehicle(Vehicle $vehicle, int $actualPrice, Carbon $saleDate = null): Vehicle
    {
        // TODO - difference
        $vehicle = $this->vehicleService->sellVehicle($vehicle, $actualPrice, $saleDate);
    }


}
