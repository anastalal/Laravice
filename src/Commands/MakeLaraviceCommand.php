<?php

namespace AnasTalal\Laravice\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Relations\Relation; 

class MakeLaraviceCommand extends Command
{
    protected $signature = 'make:laravice {model} {--force : Overwrite existing service file}';

    protected $description = 'Create a new smart service class for a given model';


    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }
    public function handle(): int
    {
        
        // ----- 1. PREPARATION (إعداد المتغيرات) -----
        $modelName = Str::studly(class_basename($this->argument('model'))); // "User" from "App/Models/User"
        $modelFQN = $this->qualifyModel($this->argument('model')); // "App\Models\User"
        $serviceName = $modelName . 'Service'; // "UserService"
        $modelVariable = Str::camel($modelName); // "user"
        $servicePath = config('laravice.services_path', app_path('Services'));
        $path = "{$servicePath}/{$serviceName}.php";
        $this->ensureDirectoryExists($servicePath);

        $this->info("\nCreating a service for the {$modelName} model...");

        // ----- 2. PATH & EXISTENCE CHECK (فحص المسار) -----
        // $path = app_path("Services/{$serviceName}.php");
        $this->ensureDirectoryExists(app_path('Services'));

        if ($this->files->exists($path) && !$this->option('force')) {
            if (!$this->confirm("Service {$serviceName} already exists. Do you want to overwrite it?")) {
                $this->info("Operation cancelled.");
                return self::SUCCESS;
            }
        }

        // ----- 3. BUILD METHODS (بناء كود الدوال) -----
        $methods = $this->buildCrudMethods($modelName, $modelVariable);
        $this->info("Analyzing relationships for {$modelName}...");
        $relationshipMethods = $this->buildRelationshipMethods($modelFQN, $modelName, $modelVariable);
        $allMethods = implode("\n\n", array_filter([$methods, $relationshipMethods]));

        // ----- 4. POPULATE STUB (تعبئة القالب) -----
        $stub = $this->files->get(__DIR__ . '/../../stubs/service.stub');

        $replacements = [
           '{{ namespace }}' => config('laravice.services_namespace', 'App\\Services'),
            '{{ modelFQN }}'      => $modelFQN,
            '{{ modelName }}'     => $modelName,
            '{{ class }}'         => $serviceName,
            '{{ modelVariable }}' => $modelVariable,
            '{{ methods }}'       => $allMethods,
        ];

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );

        // ----- 5. WRITE FILE (كتابة الملف) -----
        $this->files->put($path, $content);

        $this->info("Service {$serviceName} created successfully with CRUD methods at {$path}");

        return self::SUCCESS;
    }

    
    /**
     * Build the CRUD methods string.
     */
    protected function buildCrudMethods(string $modelName, string $modelVariable): string
    {
        $allMethod = <<<EOD
    /**
     * Get all instances of the model.
     */
    public function all(): Collection
    {
        return \$this->{$modelVariable}->all();
    }
EOD;

        $findMethod = <<<EOD

    /**
     * Find a model instance by ID.
     */
    public function find(int \$id): ?{$modelName}
    {
        return \$this->{$modelVariable}->find(\$id);
    }
EOD;

        $createMethod = <<<EOD

    /**
     * Create a new model instance.
     */
    public function create(array \$data): {$modelName}
    {
        return \$this->{$modelVariable}->create(\$data);
    }
EOD;

        $updateMethod = <<<EOD

    /**
     * Update a model instance.
     */
    public function update({$modelName} \${$modelVariable}, array \$data): bool
    {
        return \${$modelVariable}->update(\$data);
    }
EOD;

        $deleteMethod = <<<EOD

    /**
     * Delete a model instance.
     */
    public function delete({$modelName} \${$modelVariable}): bool
    {
        return \${$modelVariable}->delete();
    }
EOD;

        return implode("\n", [$allMethod, $findMethod, $createMethod, $updateMethod, $deleteMethod]);
    }


    protected function buildRelationshipMethods(string $modelFQN, string $modelName, string $modelVariable): string
    {
        if (!class_exists($modelFQN)) {
            return '';
        }

        $methodsCode = [];
        $modelInstance = new $modelFQN; // نحتاج كائن من المودل
        $reflection = new ReflectionClass($modelFQN);

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // تجاهل الدوال التي تأخذ معاملات (العلاقات لا تأخذ معاملات)
            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            // تجاهل الدوال الموروثة من الكلاسات الأب (مثل Eloquent\Model)
            if ($method->getDeclaringClass()->getName() !== $modelFQN) {
                continue;
            }

            try {
                // هذا هو السحر: نحاول استدعاء الدالة لنرى ما تعيده
                $relation = $method->invoke($modelInstance);

                // هل القيمة المعادة هي من نوع علاقة Eloquent؟
                if ($relation instanceof Relation) {
                    $relationType = class_basename($relation); // e.g., "HasMany", "BelongsTo"
                    $relationName = $method->getName(); // e.g., "posts"

                    $this->line(" -> Found '{$relationType}' relationship: {$relationName}()");

                    // بناء الدوال المساعدة بناءً على نوع العلاقة
                    switch ($relationType) {
                        case 'HasMany':
                            $methodsCode[] = $this->createHasManyMethod($modelName, $modelVariable, $relationName);
                            break;
                        case 'BelongsTo':
                                $methodsCode[] = $this->createBelongsToMethods($modelName, $modelVariable, $relationName);
                            break;
                        case 'BelongsToMany':
                                $methodsCode[] = $this->createBelongsToManyMethods($modelName, $modelVariable, $relationName);
                            break;
                        case 'HasOne':
                                $methodsCode[] = $this->createHasOneMethod($modelName, $modelVariable, $relationName);
                                break;
                         case 'MorphMany':
                                // إعادة استخدام نفس منطق HasMany لأنه متطابق تقريبًا
                                $methodsCode[] = $this->createHasManyMethod($modelName, $modelVariable, $relationName);
                                break;
                        case 'MorphTo':
                                $methodsCode[] = $this->createMorphToMethods($modelName, $modelVariable, $relationName);
                                break;
                            
                    }
                }
            } catch (\Throwable $e) {
                // تجاهل أي أخطاء، فليست كل الدوال قابلة للاستدعاء بهذه الطريقة
                continue;
            }
        }

        return implode("\n", $methodsCode);
    }

  

    protected function createBelongsToMethods(string $modelName, string $modelVariable, string $relationName): string
    {
        // "user" -> "User"
        $relatedModelName = Str::studly($relationName);
        $relatedModelName2 = '\\'. $this->qualifyModel($relatedModelName);
        // $relatedModelName = Str::studly($relationName);
        // "user" -> "user"
        $relatedModelVariable = Str::camel($relationName);

        $associateMethod = <<<EOD

        /**
         * Associate the {$modelName} with a {$relatedModelName}.
         */
        public function associate{$relatedModelName}({$modelName} \${$modelVariable}, {$relatedModelName2} \${$relatedModelVariable}): {$modelName}
        {
            \${$modelVariable}->{$relationName}()->associate(\${$relatedModelVariable});
            \${$modelVariable}->save();

            return \${$modelVariable};
        }
    EOD;

        $dissociateMethod = <<<EOD

        /**
         * Dissociate the {$relatedModelName} from the {$modelName}.
         */
        public function dissociate{$relatedModelName}({$modelName} \${$modelVariable}): {$modelName}
        {
            \${$modelVariable}->{$relationName}()->dissociate();
            \${$modelVariable}->save();

            return \${$modelVariable};
        }
    EOD;

        return $associateMethod . "\n" . $dissociateMethod;
    }


    protected function createBelongsToManyMethods(string $modelName, string $modelVariable, string $relationName): string
    {
        // "tags" -> "Tag"
        $relatedModelNamePlural = Str::studly($relationName);
        // "tags" -> "tagIds"
        $relatedIdsVariable = Str::camel(Str::singular($relationName)) . 'Ids';

        $attachMethod = <<<EOD

        /**
         * Attach {$relatedModelNamePlural} to a {$modelName}.
         *
         * @param array<int> \${$relatedIdsVariable}
         */
        public function attach{$relatedModelNamePlural}({$modelName} \${$modelVariable}, array \${$relatedIdsVariable}): void
        {
            \${$modelVariable}->{$relationName}()->attach(\${$relatedIdsVariable});
        }
    EOD;

        $syncMethod = <<<EOD

        /**
         * Sync {$relatedModelNamePlural} for a {$modelName}.
         * Pass an empty array to detach all.
         *
         * @param array<int> \${$relatedIdsVariable}
         */
        public function sync{$relatedModelNamePlural}({$modelName} \${$modelVariable}, array \${$relatedIdsVariable}): array
        {
            return \${$modelVariable}->{$relationName}()->sync(\${$relatedIdsVariable});
        }
    EOD;

        $detachMethod = <<<EOD

        /**
         * Detach {$relatedModelNamePlural} from a {$modelName}.
         *
         * @param array<int> \${$relatedIdsVariable}
         */
        public function detach{$relatedModelNamePlural}({$modelName} \${$modelVariable}, array \${$relatedIdsVariable}): int
        {
            return \${$modelVariable}->{$relationName}()->detach(\${$relatedIdsVariable});
        }
    EOD;

        return implode("\n", [$attachMethod, $syncMethod, $detachMethod]);
    }

    // أضف هذه الدالة الجديدة في الكلاس MakeLaraviceCommand
    protected function createHasOneMethod(string $modelName, string $modelVariable, string $relationName): string
    {
        // "profile" -> "Profile"
        $relatedModelName = Str::studly($relationName);
        // "profile" -> "profileData"
        $relatedVariableData = Str::camel($relationName) . 'Data';

        // تحسين اسم الدالة: createRelatedProfile -> createOrUpdateProfile
        $methodName = 'createOrUpdate' . $relatedModelName;

        return <<<EOD

        /**
         * Create or update the related {$relatedModelName} for a {$modelName}.
         */
        public function {$methodName}({$modelName} \${$modelVariable}, array \${$relatedVariableData}): \Illuminate\Database\Eloquent\Model
        {
            return \${$modelVariable}->{$relationName}()->updateOrCreate([], \${$relatedVariableData});
        }
    EOD;
    }

    protected function createHasManyMethod(string $modelName, string $modelVariable, string $relationName): string
    {
        // "comments" -> "Comment"
        $relatedModelName = Str::studly(Str::singular($relationName));
        // "comments" -> "commentData"
        $relatedVariableData = Str::camel(Str::singular($relationName)) . 'Data';

        // تحسين الاسم: createRelatedComment -> createCommentForUser
        $methodName = 'create' . $relatedModelName . 'For' . $modelName;

        return <<<EOD

        /**
         * Create a new {$relatedModelName} for this {$modelName}.
         */
        public function {$methodName}({$modelName} \${$modelVariable}, array \${$relatedVariableData}): \Illuminate\Database\Eloquent\Model
        {
            return \${$modelVariable}->{$relationName}()->create(\${$relatedVariableData});
        }
    EOD;
    }

    // أضف هذه الدالة الجديدة في الكلاس MakeLaraviceCommand
    protected function createMorphToMethods(string $modelName, string $modelVariable, string $relationName): string
    {
        // "commentable" -> "Commentable"
        $relatedModelName = Str::studly($relationName);
        // "commentable" -> "parent"
        $parentVariable = 'parent';

        // تحسين اسم الدالة: associateCommentable -> associateParent
        $methodName = 'associate' . Str::studly($parentVariable);

        return <<<EOD

        /**
         * Associate the {$modelName} with its parent model (e.g., Post, Video).
         */
        public function {$methodName}({$modelName} \${$modelVariable}, \Illuminate\Database\Eloquent\Model \${$parentVariable}): {$modelName}
        {
            \${$modelVariable}->{$relationName}()->associate(\${$parentVariable});
            \${$modelVariable}->save();

            return \${$modelVariable};
        }
    EOD;
    }
    /**
     * Get the fully-qualified model class name.
     */
    protected function qualifyModel(string $model): string
    {
        $model = ltrim($model, '\\/');
        $model = str_replace('/', '\\', $model);

        $rootNamespace = $this->laravel->getNamespace();

        if (Str::startsWith($model, $rootNamespace)) {
            return $model;
        }

        return is_dir(app_path('Models'))
            ? $rootNamespace . 'Models\\' . $model
            : $rootNamespace . $model;
    }

    /**
     * Ensures the directory for the file exists.
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

}