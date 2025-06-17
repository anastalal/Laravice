<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Services Path
    |--------------------------------------------------------------------------
    |
    | This is the default path where the generated services will be placed.
    | You can change this to any path inside your application.
    |
    */
    'services_path' => app_path('Services'),

    /*
    |--------------------------------------------------------------------------
    | Services Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace that will be used for the generated services. This
    | usually corresponds to the path above.
    |
    */
    'services_namespace' => 'App\\Services',

    /*
    |--------------------------------------------------------------------------
    | Models Path
    |--------------------------------------------------------------------------
    |
    | The path where your application's models are located. Laravice will
    | scan this directory to find models when running the "all" command.
    |
    */
    'models_path' => app_path('Models'),

    /*
    |--------------------------------------------------------------------------
    | Base Model
    |--------------------------------------------------------------------------
    |
    | If you want to only generate services for models that extend a
    | specific base model, you can specify it here. Leave it as null
    | to consider any class in the models_path as a potential model.
    | Example: App\Models\BaseModel::class
    |
    */
    'base_model' => \Illuminate\Database\Eloquent\Model::class,

    /*
    |--------------------------------------------------------------------------
    | Models to Exclude
    |--------------------------------------------------------------------------
    |
    | An array of model names (without namespace) to be excluded from
    | the generation process. This is useful for ignoring certain
    | models like pivot models or internal models.
    |
    */
    'exclude_models' => [
        // 'User',
        // 'Pivot',
    ],

    /*
    |--------------------------------------------------------------------------
    | Overwrite Existing Files
    |--------------------------------------------------------------------------
    |
    | When generating a service, if the file already exists, this option
    | determines the default behavior. If set to false, the command will
    | ask for confirmation. Use the --force flag to override this.
    |
    */
    'overwrite_existing' => false,

];