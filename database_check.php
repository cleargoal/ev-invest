<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VEHICLE UNSELLING DATABASE CHECK ===\n\n";

// Check vehicles
$vehicles = \App\Models\Vehicle::all();
echo "🚗 VEHICLES:\n";
foreach ($vehicles as $vehicle) {
    $status = 'Unknown';
    if ($vehicle->isSold()) {
        $status = '✅ SOLD';
    } elseif ($vehicle->isCancelled()) {
        $status = '❌ CANCELLED (with sale data)';
    } elseif ($vehicle->isUnsold()) {
        $status = '🔄 UNSOLD (was cancelled, now for sale)';
    } else {
        $status = '🟡 FOR SALE';
    }
    
    echo sprintf(
        "  ID:%d %s - Price:%s Profit:%s SaleDate:%s CancelledAt:%s\n",
        $vehicle->id,
        $status,
        $vehicle->price ?: 'null',
        $vehicle->profit ?: 'null',
        $vehicle->sale_date ? $vehicle->sale_date->format('Y-m-d') : 'null',
        $vehicle->cancelled_at ? $vehicle->cancelled_at->format('Y-m-d H:i') : 'null'
    );
}

// Check payments
echo "\n💰 PAYMENTS:\n";
$payments = \App\Models\Payment::with(['user', 'operation'])->get();
foreach ($payments as $payment) {
    $status = $payment->is_cancelled ? '❌ CANCELLED' : '✅ ACTIVE';
    $vehicleInfo = $payment->vehicle_id ? " (Vehicle: {$payment->vehicle_id})" : '';
    
    echo sprintf(
        "  ID:%d %s - User:%s Amount:%s Op:%s%s\n",
        $payment->id,
        $status,
        $payment->user->name ?? 'Unknown',
        $payment->amount ?: '0',
        $payment->operation->title ?? 'Unknown',
        $vehicleInfo
    );
}

// Check totals
echo "\n📊 TOTALS:\n";
$totalSum = \App\Models\Total::sum('amount');
echo "  Total Amount: $" . number_format($totalSum / 100, 2) . "\n";

// VehicleResource query check
echo "\n🔍 VEHICLERESOURCE QUERY CHECK:\n";
$forSaleQuery = \App\Models\Vehicle::where('profit', null)->orWhere('sale_date', null);
$forSaleCount = $forSaleQuery->count();
echo "  Vehicles showing in VehicleResource: {$forSaleCount}\n";

if ($forSaleCount > 0) {
    echo "  Details:\n";
    foreach ($forSaleQuery->get() as $vehicle) {
        echo sprintf(
            "    ID:%d %s (profit:%s sale_date:%s cancelled_at:%s)\n",
            $vehicle->id,
            $vehicle->title ?: 'No title',
            $vehicle->getOriginal('profit') ?: 'null',
            $vehicle->sale_date ? $vehicle->sale_date->format('Y-m-d') : 'null',
            $vehicle->cancelled_at ? $vehicle->cancelled_at->format('Y-m-d') : 'null'
        );
    }
}

echo "\n=== END CHECK ===\n";