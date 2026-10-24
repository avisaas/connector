<?php

namespace AviSaaS\Connector;

use AviSaaS\Connector\Classes\InstallReport;
use Igniter\Flame\Support\Facades\Igniter;
use Igniter\System\Classes\BaseExtension;
use Illuminate\Support\Facades\Route;

class Extension extends BaseExtension
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/avisaas-connector.php', 'avisaas-connector');
    }

    public function boot(): void
    {
        
        
        
        
        Route::middleware(config('igniter-routes.adminMiddleware', ['web', 'igniter', 'igniter:admin']))
            ->prefix(Igniter::adminUri())
            ->group(__DIR__.'/../routes/admin.php');

        
        Route::get('avisaas-install-check/connector', [InstallReport::class, 'answer']);

        $this->app->make(InstallReport::class)->sendIfDue();
    }

    public function registerPermissions(): array
    {
        return [
            'Avisaas.Connector.Manage' => [
                'label' => 'Manage AviSaaS Connector',
                'group' => 'AviSaaS',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'system' => [
                'child' => [
                    'avisaas_connector' => [
                        'priority' => 30,
                        'class' => 'connector',
                        'href' => admin_url('avisaas/connector'),
                        'title' => 'Marketplace',
                        'permission' => 'Avisaas.Connector.Manage',
                    ],
                ],
            ],
        ];
    }
}
