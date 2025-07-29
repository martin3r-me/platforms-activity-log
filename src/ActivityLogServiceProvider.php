<?php

namespace Platform\ActivityLog;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Illuminate\Support\Str;

class ActivityLogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Config publishen
        $this->publishes([
            __DIR__ . '/../config/activity-log.php' => config_path('activity-log.php'),
        ], 'config');

        // Migrationen laden & publishen
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'migrations');

        // Views laden & publishen
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'activity-log');
        $this->registerLivewireComponents();

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/activity-log'),
        ], 'views');

        // Livewire-Komponente registrieren
        
    }

    public function register(): void
    {
        // Config-Merge
        $this->mergeConfigFrom(
            __DIR__ . '/../config/activity-log.php',
            'activity-log'
        );
    }

    protected function registerLivewireComponents(): void
    {
        $componentPath = __DIR__ . '/Http/Livewire/Activities';
        $namespace = 'Platform\\ActivityLog\\Http\\Livewire\\Activities';
        $prefix = 'activity-log';

        if (!is_dir($componentPath)) {
            return;
        }

        foreach (scandir($componentPath) as $file) {
            if (!str_ends_with($file, '.php')) {
                continue;
            }

            $class = $namespace . '\\' . pathinfo($file, PATHINFO_FILENAME);

            if (class_exists($class)) {
                $alias = $prefix . '.' . Str::kebab(pathinfo($file, PATHINFO_FILENAME));
                Livewire::component($alias, $class);
            }
        }
    }
}
