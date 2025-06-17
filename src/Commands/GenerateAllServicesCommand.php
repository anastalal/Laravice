<?php

namespace AnasTalal\Laravice\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class GenerateAllServicesCommand extends Command
{
    protected $signature = 'laravice:generate-all {--force : Overwrite existing service files}';

    protected $description = 'Generate services for all models in the application';

    public function handle(Filesystem $filesystem): int
    {
        $modelsPath = config('laravice.models_path', app_path('Models'));
        $excludeModels = config('laravice.exclude_models', []);
        $baseModel = config('laravice.base_model', \Illuminate\Database\Eloquent\Model::class);
        $rootNamespace = $this->laravel->getNamespace(); // e.g. "App\"

        if (! $filesystem->isDirectory($modelsPath)) {
            $this->error("Models directory not found at: {$modelsPath}");

            return self::FAILURE;
        }

        $this->info("Scanning for models in [{$modelsPath}]...");

        $models = collect(File::allFiles($modelsPath))
            ->map(function (\SplFileInfo $file) use ($rootNamespace) {
                // تحويل مسار الملف إلى اسم كلاس
                // 1. احصل على المسار النسبي: "Models/User.php"
                $path = Str::after($file->getRealPath(), app_path().DIRECTORY_SEPARATOR);

                // 2. حوله إلى namespace: "App\Models\User"
                return $rootNamespace.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    $path
                );
            })
            ->filter(function (string $class) use ($baseModel, $excludeModels) {
                if (! class_exists($class)) {
                    return false;
                }

                // استخدم Reflection API للتأكد أنه ليس interface أو trait
                $reflection = new ReflectionClass($class);
                if (! $reflection->isInstantiable()) {
                    return false; // يفلتر الـ abstract classes, interfaces, traits
                }

                // تأكد أنه يرث من المودل الأساسي المحدد في الـ config
                if (! is_subclass_of($class, $baseModel)) {
                    return false;
                }

                // تأكد أنه ليس في قائمة الاستثناءات
                if (in_array($reflection->getShortName(), $excludeModels)) {
                    return false;
                }

                return true;
            });

        if ($models->isEmpty()) {
            $this->warn('No valid models found to generate services for.');
            $this->line('Check your `config/laravice.php` settings for `models_path`, `base_model`, and `exclude_models`.');

            return self::SUCCESS;
        }

        $this->info("Found {$models->count()} models. Starting generation...");
        $bar = $this->output->createProgressBar($models->count());
        $bar->start();

        foreach ($models as $modelClass) {
            $this->call('make:laravice', [
                'model' => $modelClass,
                '--force' => $this->option('force'),
            ]);
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nAll services generated successfully!");

        return self::SUCCESS;
    }
}
