<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Acao;
use App\Observers\AcaoObserver;
use Illuminate\Support\Facades\Date;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Date::setLocale('pt_BR');
        Acao::observe(AcaoObserver::class); // ⬅️ ADICIONA AQUI
    }

    public function register(): void
    {
        //
    }
}