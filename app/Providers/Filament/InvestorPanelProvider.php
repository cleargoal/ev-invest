<?php

namespace App\Providers\Filament;

use App\Filament\Investor\Pages\InvestorBoard;
use App\Filament\Investor\Widgets\PayConfirmWidget;
use App\Filament\Investor\Widgets\StatsOverviewGeneral;
use App\Filament\Resources\InvestorResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Support\Facades\FilamentColor;

class InvestorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('investor')
            ->path('investor')
            ->collapsibleNavigationGroups(false)
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Investor/Resources'), for: 'App\\Filament\\Investor\\Resources')
            ->resources([])
            ->discoverPages(in: app_path('Filament/Investor/Pages'), for: 'App\\Filament\\Investor\\Pages')
            ->pages([
                InvestorBoard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Investor/Widgets'), for: 'App\\Filament\\Investor\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
                StatsOverviewGeneral::class,
                PayConfirmWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->favicon(asset('images/ev-logo.webp'))
            ->viteTheme('resources/css/filament/investor/theme.css')
            ->profile(isSimple: false)
            ->brandLogo(asset('images/ev-logo.webp'))
            ->brandLogoHeight('4rem')
            ->colors([
                'indigo' => Color::Indigo,
                'fuchsia' => Color::Fuchsia,
                'purple' => Color::Purple,
                'sky' => Color::Sky,
                'emerald' => Color::Emerald,
            ])
//            ->navigationItems([
//                NavigationItem::make('Analytics')->hidden(fn(): bool => ! auth()->user()->can('view-analytics'))])
            ;
    }
}
