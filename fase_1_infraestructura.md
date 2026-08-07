```markdown
# Especificación de Ejecución Autónoma - Fase 1: Infraestructura Local, Base de Datos y Auth

## 1. Objetivo de la Fase
Configurar el entorno aislado de desarrollo multiplataforma utilizando Docker, inicializar el backend con Laravel 11, estructurar la base de datos PostgreSQL/MySQL con restricciones estrictas de integridad y habilitar el sistema de autenticación API global mediante Laravel Sanctum.

---

## 2. Instrucciones de Contexto para el Agente LLM
Eres un ingeniero de software backend automatizado. Tu tarea es generar la estructura de archivos, el código fuente y los scripts de prueba descritos en este documento. 
- Debes escribir código limpio, tipado y documentado.
- No dejes marcadores de posición (placeholders) como `// todo: implementar después`. Todo el código debe ser funcional.
- Ejecuta los comandos en el orden secuencial estricto del plan de acción.

---

## 3. Estructura de Archivos Objetivo
El agente LLM debe asegurar o crear la siguiente estructura en el espacio de trabajo:
```text
mi-proyecto-rapidito/
├── docker-compose.yml
├── docker/
│   └── backend.Dockerfile
├── backend/
│   ├── .env
│   ├── app/
│   │   └── Models/
│   │       └── User.php
│   ├── database/
│   │   └── migrations/
│   │       ├── 0001_01_01_000000_create_users_table.php
│   │       ├── 2026_01_01_000001_create_subscriptions_table.php
│   │       ├── 2026_01_01_000002_create_restaurants_table.php
│   │       ├── 2026_01_01_000003_create_tenant_designs_table.php
│   │       ├── 2026_01_01_000004_create_categories_table.php
│   │       ├── 2026_01_01_000005_create_products_table.php
│   │       ├── 2026_01_01_000006_create_tables_table.php
│   │       ├── 2026_01_01_000007_create_orders_table.php
│   │       └── 2026_01_01_000008_create_order_items_table.php
│   └── routes/
│       └── api.php
└── frontend/

```

---

## 4. Plan de Acción y Código a Generar

### Paso 1: Configuración del Entorno Docker

El agente debe escribir los archivos de configuración para el entorno de contenedores.

#### `docker-compose.yml`

```yaml
services:
  db:
    image: mysql:8.0
    container_name: rapidito_db
    restart: unless-stopped
    ports:
      - "43306:3306"
    environment:
      MYSQL_DATABASE: rapidito_matrix
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_USER: rapidito_user
      MYSQL_PASSWORD: rapidito_password
    volumes:
      - rapidito_db_data:/var/lib/mysql
    networks:
      - rapidito_network

  redis:
    image: redis:alpine
    container_name: rapidito_redis
    restart: unless-stopped
    ports:
      - "46379:6379"
    networks:
      - rapidito_network

  backend:
    build:
      context: .
      dockerfile: ./docker/backend.Dockerfile
    container_name: rapidito_backend
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./backend:/var/www/html
    ports:
      - "4005:8000"
    command: php artisan serve --host=0.0.0.0 --port=8000
    depends_on:
      - db
      - redis
    networks:
      - rapidito_network

networks:
  rapidito_network:
    driver: bridge

volumes:
  rapidito_db_data:
    driver: local

```

#### `docker/backend.Dockerfile`

```dockerfile
FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    bash \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    linux-headers \
    $PHPIZE_DEPS

RUN docker-php-ext-install pdo_mysql bcmath pcntl posix
RUN pecl install redis && docker-php-ext-enable redis
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
EXPOSE 8000

```

### Paso 2: Inicialización de Laravel 11 y Configuración del Entorno

1. Ejecutar de forma interna: `composer create-project laravel/laravel backend`.
2. Modificar el archivo `backend/.env` con los accesos exactos de Docker:

```env
APP_NAME=rapidito_Restaurante
APP_ENV=local
APP_KEY=base64:GENERATE_AN_APP_KEY_HERE
APP_DEBUG=true
APP_URL=http://localhost:8008

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=rapidito_matrix
DB_USERNAME=rapidito_user
DB_PASSWORD=rapidito_password

QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

3. Instalar Laravel Sanctum para la gestión de tokens API: `php artisan install:api`.

### Paso 3: Generación del Esquema de Datos Blindado (Migraciones)

El agente debe sobrescribir/crear los archivos de migración garantizando el orden cronológico para evitar colisiones de llaves foráneas.

#### Migración 1: `0001_01_01_000000_create_users_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->nullable()->index();
            $table->string('name', 155);
            $table->string('email', 155)->unique();
            $table->string('password', 255);
            $table->enum('role', ['superadmin', 'owner', 'waiter', 'kitchen', 'bar']);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('users'); }
};

```

#### Migración 2: `2026_01_01_000001_create_subscriptions_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('plan_name', 50);
            $table->enum('status', ['trialing', 'active', 'past_due', 'canceled'])->default('trialing');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('subscriptions'); }
};

```

#### Migración 3: `2026_01_01_000002_create_restaurants_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('restrict');
            $table->string('name', 155);
            $table->string('slug', 155)->unique();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->boolean('weekend_mode')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) { $table->dropForeign(['restaurant_id']); });
        Schema::dropIfExists('restaurants');
    }
};

```

#### Migración 4: `2026_01_01_000003_create_tenant_designs_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tenant_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained('restaurants')->onDelete('cascade');
            $table->string('primary_color', 7)->default('#FF5733');
            $table->string('secondary_color', 7)->default('#333333');
            $table->string('background_color', 7)->default('#FAFAFA');
            $table->string('font_family', 50)->default('Roboto');
            $table->enum('menu_layout', ['grid', 'list'])->default('list');
            $table->text('logo_url')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('tenant_designs'); }
};

```

#### Migración 5: `2026_01_01_000004_create_categories_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->json('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['restaurant_id', 'sort_order']);
        });
    }
    public function down(): void { Schema::dropIfExists('categories'); }
};

```

#### Migración 6: `2026_01_01_000005_create_products_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('weekend_price', 10, 2)->nullable();
            $table->text('image_url')->nullable();
            $table->enum('stock_status', ['available', 'out_of_stock'])->default('available');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_vegan')->default(false);
            $table->boolean('is_vegetarian')->default(false);
            $table->json('allergens')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};

```

#### Migración 7: `2026_01_01_000006_create_tables_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->string('number', 20);
            $table->enum('status', ['free', 'occupied'])->default('free');
            $table->string('secret_token', 64);
            $table->string('current_session_token', 64)->nullable();
            $table->timestamps();
            $table->index(['id', 'secret_token']);
        });
    }
    public function down(): void { Schema::dropIfExists('tables'); }
};

```

#### Migración 8: `2026_01_01_000007_create_orders_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('table_id')->constrained('tables')->onDelete('restrict');
            $table->string('session_token', 64);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->decimal('total_price', 10, 2)->default(0.00);
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};

```

#### Migración 9: `2026_01_01_000008_create_order_items_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'cooking', 'ready', 'delivered', 'cancelled'])->default('pending');
            $table->enum('target_area', ['kitchen', 'bar']);
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();
            $table->index(['target_area', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('order_items'); }
};

```

Ejecutar de manera interna dentro del contenedor una vez generadas: `php artisan migrate:fresh`.

### Paso 4: Lógica del Modelo de Usuario y Autenticación API

Modificar el modelo `User.php` para incorporar la conversión de tipos (casts) y la seguridad de asignación masiva de Laravel junto con el rol.

#### `backend/app/Models/User.php`

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'restaurant_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];
}

```

#### Controlador de Autenticación: `backend/app/Http/Controllers/Api/AuthController.php`

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller {
    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email|max:155',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'restaurant_id' => $user->restaurant_id
            ]
        ]);
    }
}

```

#### Registro de Rutas: `backend/routes/api.php`

```php
<?php
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/v1/user', function (Request $request) {
    return $request->user();
});

```

---

## 5. Pruebas Automatizadas de Verificación (QA de la Fase 1)

El agente LLM debe crear este archivo de prueba automatizada para certificar que la base de datos y el flujo de login funcionan bajo estándares estrictos.

#### `backend/tests/Feature/PhaseOneArchitectureTest.php`

```php
<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseOneArchitectureTest extends TestCase {
    use RefreshDatabase;

    #[\ReturnTypeWillChange]
    public function test_database_has_correct_migrations_and_foreign_keys_constraints() {
        // Creación del usuario dueño
        $owner = User::create([
            'name' => 'Owner Test',
            'email' => 'owner@test.com',
            'password' => Hash::make('secret123'),
            'role' => 'owner'
        ]);

        $this->assertDatabaseHas('users', ['email' => 'owner@test.com']);
    }

    #[\ReturnTypeWillChange]
    public function test_user_can_login_via_api_and_receives_sanctum_token() {
        User::create([
            'name' => 'Staff Test',
            'email' => 'staff@test.com',
            'password' => Hash::make('password123'),
            'role' => 'waiter'
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@test.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'access_token',
                     'token_type',
                     'user' => ['id', 'name', 'email', 'role']
                 ]);
    }
}

```

Para verificar de forma autónoma el cumplimiento de la fase, el LLM ejecutará dentro del contenedor el comando:
`vendor/bin/phpunit tests/Feature/PhaseOneArchitectureTest.php`
Si el resultado devuelve **OK (2 tests, 4 assertions)**, la fase se considera completada con éxito.

```

***

<Callout type="tip" title="Próximo Paso">
Una vez que hayas cargado esta especificación en tu agente LLM o la hayas ejecutado tú mismo y las pruebas del validador den luz verde (en verde con código de salida 0), confírmamelo y te generaré de inmediato la documentación técnica idéntica para la **Fase 2: Núcleo Multi-tenant y Filtros de Suscripción**.
</Callout>

<Elicitations message="¿Cómo quieres proceder ahora?">
  <Elicitation label="Generar Fase 2" query="He completado con éxito la fase 1. Genera el documento completo en markdown para la Fase 2 y las instrucciones del agente LLM." />
  <Elicitation label="Ajustar Base de Datos" query="Necesito hacer un cambio en el esquema de la base de datos antes de seguir." />
</Elicitations>

```
