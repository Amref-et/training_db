<?php

namespace App\Services;

use App\Models\GeneratedCrud;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class CrudBuilderService
{
    public function create(array $payload): GeneratedCrud
    {
        $fields = collect($payload['fields'])
            ->values()
            ->map(fn (array $field) => [
                'name' => Str::snake($field['name']),
                'label' => $field['label'] ?: Str::headline($field['name']),
                'type' => $field['type'],
                'nullable' => (bool) ($field['nullable'] ?? false),
                'unique' => (bool) ($field['unique'] ?? false),
                'show_in_index' => (bool) ($field['show_in_index'] ?? false),
                'in_form' => (bool) ($field['in_form'] ?? false),
            ]);

        $tableName = Str::snake($payload['table_name']);
        $slug = Str::kebab($payload['slug'] ?: $tableName);
        $resourceName = Str::pluralStudly($payload['name']);
        $modelName = Str::studly(Str::singular($tableName));
        $modelClass = 'App\\Models\\Generated\\'.$modelName;

        if (File::exists(app_path('Models/Generated/'.$modelName.'.php'))) {
            throw new RuntimeException('A generated model with that name already exists.');
        }

        $migrationFileName = now()->format('Y_m_d_His_u').'_'.$tableName.'_crud.php';
        $migrationPath = database_path('migrations/'.$migrationFileName);

        DB::beginTransaction();

        try {
            File::ensureDirectoryExists(app_path('Models/Generated'));
            File::put($migrationPath, $this->migrationStub($tableName, $fields->all()));
            File::put(app_path('Models/Generated/'.$modelName.'.php'), $this->modelStub($modelName, $tableName, $fields->pluck('name')->all()));

            $crud = GeneratedCrud::create([
                'name' => $resourceName,
                'slug' => $slug,
                'table_name' => $tableName,
                'singular_label' => $payload['singular_label'] ?: Str::headline(Str::singular($tableName)),
                'plural_label' => $payload['plural_label'] ?: Str::headline(Str::plural($tableName)),
                'model_class' => $modelClass,
                'schema' => ['fields' => $fields->all()],
            ]);

            $this->ensurePermissions($slug, $crud->plural_label);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            File::delete($migrationPath);
            File::delete(app_path('Models/Generated/'.$modelName.'.php'));
            throw $e;
        }

        Artisan::call('migrate', [
            '--path' => 'database/migrations/'.$migrationFileName,
            '--force' => true,
        ]);

        if (str_contains(strtolower(Artisan::output()), 'fail')) {
            throw new RuntimeException(Artisan::output());
        }

        return $crud;
    }

    public function delete(GeneratedCrud $crud): void
    {
        $tableName = $crud->table_name;
        $slug = $crud->slug;
        $modelPath = app_path('Models/Generated/'.class_basename($crud->model_class).'.php');
        $migrationFiles = glob(database_path('migrations/*_'.$tableName.'_crud.php')) ?: [];

        if (Schema::hasTable($tableName)) {
            Schema::drop($tableName);
        }

        DB::transaction(function () use ($crud, $slug): void {
            Permission::query()
                ->where('slug', 'like', $slug.'.%')
                ->delete();

            $crud->delete();
        });

        File::delete($modelPath);
        File::delete($migrationFiles);
    }

    private function ensurePermissions(string $slug, string $label): void
    {
        $permissions = [
            'view' => 'View '.$label,
            'create' => 'Create '.$label,
            'update' => 'Update '.$label,
            'delete' => 'Delete '.$label,
        ];

        foreach ($permissions as $action => $name) {
            Permission::updateOrCreate(['slug' => $slug.'.'.$action], ['name' => $name]);
        }

        $permissionIds = Permission::query()->where('slug', 'like', $slug.'.%')->pluck('id')->all();
        $viewPermissionId = Permission::query()->where('slug', $slug.'.view')->value('id');
        $editorPermissionIds = Permission::query()->whereIn('slug', [$slug.'.view', $slug.'.create', $slug.'.update'])->pluck('id')->all();

        if ($admin = Role::query()->where('name', 'Admin')->first()) {
            $admin->permissions()->syncWithoutDetaching($permissionIds);
        }

        if ($editor = Role::query()->where('name', 'Editor')->first()) {
            $editor->permissions()->syncWithoutDetaching($editorPermissionIds);
        }

        $viewer = Role::query()->where('name', 'Viewer')->first();

        if ($viewer && $viewPermissionId) {
            $viewer->permissions()->syncWithoutDetaching([$viewPermissionId]);
        }
    }

    private function migrationStub(string $tableName, array $fields): string
    {
        $fieldLines = collect($fields)->map(function (array $field) {
            $line = match ($field['type']) {
                'string' => "\$table->string('{$field['name']}')",
                'text' => "\$table->text('{$field['name']}')",
                'integer' => "\$table->integer('{$field['name']}')",
                'bigInteger' => "\$table->bigInteger('{$field['name']}')",
                'decimal' => "\$table->decimal('{$field['name']}', 10, 2)",
                'boolean' => "\$table->boolean('{$field['name']}')",
                'date' => "\$table->date('{$field['name']}')",
                'dateTime' => "\$table->dateTime('{$field['name']}')",
                default => "\$table->string('{$field['name']}')",
            };

            if ($field['nullable']) {
                $line .= '->nullable()';
            }

            if ($field['unique']) {
                $line .= '->unique()';
            }

            if ($field['type'] === 'boolean' && ! $field['nullable']) {
                $line .= '->default(false)';
            }

            return '            '.$line.';';
        })->implode(PHP_EOL);

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('{$tableName}')) {
            return;
        }

        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
{$fieldLines}
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;
    }

    private function modelStub(string $modelName, string $tableName, array $fillable): string
    {
        $fillableExport = implode(",\n        ", array_map(fn (string $field) => "'{$field}'", $fillable));

        return <<<PHP
<?php

namespace App\Models\Generated;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class {$modelName} extends Model
{
    use HasFactory;

    protected \$table = '{$tableName}';

    protected \$fillable = [
        {$fillableExport}
    ];
}
PHP;
    }
}

