<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Auction;

echo 'Current time: ' . now()->format('Y-m-d H:i:s') . PHP_EOL;

$auctions = Auction::where('status', 'opening')->where('end_time', '<=', now())->get();
echo 'Expired opening auctions: ' . $auctions->count() . PHP_EOL;

foreach($auctions as $a) {
    echo 'ID: ' . $a->id . ', End: ' . $a->end_time->format('Y-m-d H:i:s') . ', Status: ' . $a->status . PHP_EOL;
}

$pending = Auction::where('status', 'pending')->get();
echo 'Pending auctions: ' . $pending->count() . PHP_EOL;

foreach($pending as $a) {
    echo 'ID: ' . $a->id . ', End: ' . ($a->end_time ? $a->end_time->format('Y-m-d H:i:s') : 'null') . ', Status: ' . $a->status . PHP_EOL;
}
