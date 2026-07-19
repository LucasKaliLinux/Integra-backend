<?php

namespace App\Providers;

use App\Listeners\RegistrarLogin;
use App\Listeners\RegistrarLoginFalha;
use App\Listeners\RegistrarLogout;
use App\Models\Acao;
use App\Observers\AcaoObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Date::setLocale('pt_BR');
        Acao::observe(AcaoObserver::class); // ⬅️ ADICIONA AQUI

        // Rate limiting do login: 5 tentativas/min por combinação e-mail + IP.
        // Evita força bruta sem que um atacante consiga bloquear um usuário
        // legítimo batendo só no IP dele (ou contornar o limite trocando de IP).
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        // Log de Atividades: eventos de auth do gabinete (fora do grupo client).
        Event::listen(Login::class, RegistrarLogin::class);
        Event::listen(Logout::class, RegistrarLogout::class);
        Event::listen(Failed::class, RegistrarLoginFalha::class);
    }

    public function register(): void
    {
        //
    }
}
