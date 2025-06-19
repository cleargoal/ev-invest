<?php

namespace App\Providers\Filament;

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Color\Rgb;
use Filament\AvatarProviders\Contracts;


class UiAvatarsProvider implements Contracts\AvatarProvider
{
    public function get(Model | Authenticatable $record): string
    {
        $name = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => filled($segment) ? mb_substr($segment, 0, 1) : '')
            ->join(' ');

        $color = collect(['gray', 'green', 'blue', 'red', 'violet', 'purple', 'pink', 'amber', 'orange', 'indigo', ])->random();
        $tone = collect([700, 800, 900, 950])->random();
        $backgroundColor = Rgb::fromString('rgb(' . FilamentColor::getColors()[$color][$tone] . ')')->toHex();

        return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&color=FFFFFF&background=' . str($backgroundColor)->after('#');
    }
}
