# Alkemy Wallet API — Squad 2

API REST desarrollada en **Laravel** para la simulación de una billetera virtual: autenticación JWT, roles (usuario/administrador), cuentas, depósitos, transferencias, CBUs de terceros, movimientos paginados, simulación de plazo fijo y documentación interactiva con Swagger/OpenAPI.

## Índice

- [Requisitos](#requisitos)
- [Instalación y configuración](#instalación-y-configuración)
- [Base de datos: migraciones y seeders](#base-de-datos-migraciones-y-seeders)
- [Correr los tests](#correr-los-tests)
- [Levantar el servidor](#levantar-el-servidor)
- [Documentación interactiva (Swagger)](#documentación-interactiva-swagger)
- [Credenciales de prueba](#credenciales-de-prueba)
- [Endpoints principales](#endpoints-principales)
- [Reglas de negocio destacadas](#reglas-de-negocio-destacadas)
- [Manejo de errores y seguridad](#manejo-de-errores-y-seguridad)
- [Checklist de demo (WAL-024)](#checklist-de-demo-wal-024)
- [Frontend demostrativo](#frontend-demostrativo)

---

## Requisitos

- PHP compatible con Laravel (ver `composer.json`)
- Composer
- SQLite habilitado en PHP (`pdo_sqlite`), que es la base por defecto del proyecto. Si preferís MySQL/PostgreSQL, cambiá las variables `DB_*` en tu `.env`.

## Instalación y configuración

```sh
# 1. Instalar dependencias de Composer
composer install

# 2. Copiar y configurar el archivo de entorno local
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# 3. Solo si usás la base por defecto (SQLite): crear el archivo de base de datos
touch database/database.sqlite
```

- `key:generate` crea el `APP_KEY` de Laravel.
- `jwt:secret` crea el `JWT_SECRET` que usa `php-open-source-saver/jwt-auth`. **Sin este paso, cualquier ruta protegida con `auth:api` responde `500` en vez de `401`/`200`.**
- Ambos comandos escriben directamente en tu `.env` local, que **no se versiona**: cada desarrollador debe correrlos una vez después de clonar.
- Opcionalmente, definí `ADMIN_SEED_PASSWORD` en tu `.env` (ver [Credenciales de prueba](#credenciales-de-prueba)).

## Base de datos: migraciones y seeders

```sh
php artisan migrate --seed
```

Esto corre todas las migraciones desde una base vacía y ejecuta, en orden, los seeders declarados en `DatabaseSeeder`:

1. **`RoleSeeder`** — crea los roles `admin` (id 1) y `user` (id 2).
2. **`UserSeeder`** — crea `TestUser` (`testuser@example.test`, rol `user`) y `Admin` (`admin@example.test`, rol `admin`).
3. **`AccountSeeder`** — crea la cuenta de cada uno de los dos usuarios anteriores.

Si necesitás reiniciar la base desde cero durante el desarrollo:

```sh
php artisan migrate:fresh --seed
```

## Correr los tests

```sh
php artisan test
```

La suite usa una base de datos de pruebas independiente y datos deterministas; puede ejecutarse en cualquier orden sin depender de corridas anteriores. Para correr solo un archivo puntual:

```sh
php artisan test --filter=NombreDelTest
```

## Levantar el servidor

```sh
php artisan serve
```

Por defecto queda disponible en `http://localhost:8000`. Todos los endpoints de la API usan el prefijo `/api/v1`.

## Documentación interactiva (Swagger)

Con el servidor corriendo:

- **Swagger UI:** `http://localhost:8000/api/documentation`

Ahí se puede ver método, parámetros, cuerpo y respuesta de cada endpoint (incluyendo errores `401`/`403`/`404`/`422`), y probar rutas protegidas indicando el Bearer Token (botón "Authorize"). Las rutas de movimientos propios y de administración de usuarios/movimientos ya tienen anotaciones OpenAPI completas (`OA\Get`, `OA\Post`, etc.) en `MovementController`, `Admin\UserController` y `Admin\MovementController`.

## Credenciales de prueba

| Rol | Email | Contraseña |
|---|---|---|
| Usuario (`user`, `role_id` 2) | `testuser@example.test` | `password123` |
| Administrador (`admin`, `role_id` 1) | `admin@example.test` | Definida en `ADMIN_SEED_PASSWORD` del `.env`. Si no está configurada, el seeder genera una al azar y la imprime **una sola vez** por consola al correr `php artisan db:seed` — nunca queda escrita en el repo. |

Para consultar CBUs de las cuentas generadas por el seeding (útil para probar transferencias):

```sh
php artisan tinker --execute="App\Models\Account::all(['user_id', 'cbu'])->each(function(\$a){ echo \$a->user_id . ' -> ' . \$a->cbu . PHP_EOL; });"
```

## Endpoints principales

Todas las rutas usan el prefijo `/api/v1`. Las marcadas 🔒 requieren `Authorization: Bearer <token>`; las marcadas 🔒👑 además requieren rol `admin`.

### Autenticación

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/auth/register` | Registro público. Crea el usuario (rol `user`) y su cuenta con CBU único y saldo `0.00`. |
| POST | `/auth/login` | Devuelve el JWT a usar como Bearer Token. |
| POST | `/auth/logout` 🔒 | Invalida la sesión/token actual. |
| GET | `/auth/check` 🔒 | Verifica si el token enviado es válido. |

### Perfil y cuenta propios 🔒

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/profile` | Datos del usuario autenticado (sin contraseña). |
| PUT/PATCH | `/profile` | Actualiza `name`, `email`, `age`, `image`. |
| DELETE | `/profile` | Elimina (soft delete) el usuario autenticado — ver [Reglas de negocio](#reglas-de-negocio-destacadas). |
| GET | `/account` | CBU y saldo de la cuenta propia. |

### Operaciones de la wallet 🔒

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/deposits` | Deposita en la cuenta propia. Crea un movimiento `deposit`. |
| POST | `/transfers` | Transfiere a un `destination_cbu`. Rechaza saldo insuficiente y CBU propio con `422`. Crea `transfer_out`/`transfer_in` dentro de una transacción atómica. |
| GET | `/movements` | Historial paginado de la cuenta propia. Parámetros: `page`, `per_page` (1–100, default 15), `order` (`asc`\|`desc`, default `desc`). Siempre ordena por fecha (`created_at`); no admite elegir otro campo. |

### CBUs de terceros guardados 🔒

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/cbu/{cbu}/users/{idUser}` | Guarda un CBU de tercero para el usuario autenticado. |
| GET | `/cbu/users/{idUser}` | Lista los CBUs guardados por el usuario autenticado. |
| DELETE | `/cbu/{cbu}/users/{idUser}` | Quita un CBU guardado (no borra la cuenta ni al tercero). |

### Inversiones 🔒

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/investments/fixed-term/simulate` | Simula un plazo fijo (interés simple, TNA 30%, 30–365 días). No mueve saldo ni crea movimientos. |

### Administración 🔒👑

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/admin/check` | Endpoint mínimo para verificar acceso admin (`{"success": true}`). |
| GET | `/admin/users` | Listado paginado de usuarios. Filtros: `page`, `per_page` (1–100, default 15), `sort` (`id`\|`name`\|`email`\|`age`\|`created_at`, default `id`), `order` (`asc`\|`desc`, default `asc`), `role_id`. |
| POST | `/admin/users` | Crea un usuario y su cuenta asociada. Requiere `name`, `email`, `password`, `password_confirmation`, `age`, `image`, `role_id`; opcionales `account_type`/`account_currency`. |
| GET | `/admin/users/{id}` | Detalle de un usuario. |
| PUT | `/admin/users/{id}` | Actualización parcial (solo los campos enviados). |
| DELETE | `/admin/users/{id}` | Baja lógica (soft delete). La cuenta y los movimientos del usuario se conservan intactos, igual que en `DELETE /profile`. |
| GET | `/admin/accounts` | Listado paginado de cuentas, ordenable por `created_at`/`balance`. |
| POST | `/admin/accounts` | Alta de cuenta para un usuario que todavía no tiene una. |
| GET | `/admin/accounts/{account}` | Detalle de una cuenta. |
| PUT/PATCH | `/admin/accounts/{account}` | Edita `type`/`currency`/`balance`. Nunca reasigna `user_id`. |
| DELETE | `/admin/accounts/{account}` | Elimina una cuenta. |
| GET | `/admin/movements` | Listado paginado. Filtros: `page`, `per_page` (1–100, default 15), `sort` (`id`\|`created_at`\|`amount`\|`type`, default `id`), `order` (`asc`\|`desc`, default `desc`), `account_id`, `user_id` (resuelto vía la cuenta), `type`. |
| POST | `/admin/movements` | Crea un movimiento manual (`account_id`, `type`, `amount`, `counterpart_cbu` opcional). **No modifica el saldo de la cuenta.** |
| GET | `/admin/movements/{id}` | Detalle de un movimiento, con su cuenta, moneda y usuario asociado. |
| PUT | `/admin/movements/{id}` | Actualización parcial de un movimiento. **No recalcula el saldo de la cuenta.** |
| DELETE | `/admin/movements/{id}` | Elimina un movimiento. **No devuelve el importe al saldo.** |

Un usuario con rol `user` que intenta acceder a cualquier ruta `/admin/*` recibe `403 Forbidden` en JSON.

## Reglas de negocio destacadas

### Eliminación de usuarios: siempre soft delete

Tanto `DELETE /api/v1/profile` (autoeliminación) como `DELETE /api/v1/admin/users/{id}` (baja por un administrador) hacen un **soft delete** (`deleted_at`), no un borrado físico:

- El usuario deja de poder loguearse y de aparecer en consultas normales, pero la fila sigue en `users`.
- La cuenta, sus movimientos y los CBUs guardados **no se tocan**: como `users` no se borra de verdad, ninguna FK con `onDelete('set null')` se dispara.
- Se eligió soft delete porque un borrado físico arrastraría, vía `SET NULL`, una cuenta con saldo real a un estado huérfano e irreversible.

### El CRUD administrativo de movimientos nunca toca el saldo

`Admin\MovementController` (`/api/v1/admin/movements`) es una herramienta de **corrección del historial**, no un atajo para acreditar o debitar dinero: crear, editar o eliminar un movimiento desde ahí nunca modifica `accounts.balance`. Los saldos solo cambian a través de las operaciones reales de la wallet (`POST /deposits` y `POST /transfers`), que aplican sus propias validaciones dentro de una transacción. Cada respuesta de este CRUD lo aclara explícitamente en el mensaje (`"...El saldo de la cuenta no fue modificado/recalculado."`).

### Roles y rutas administrativas

- Los roles `admin` (id 1) / `user` (id 2) se crean con `RoleSeeder`. El registro público siempre asigna `user` desde el servidor — el `RegisterRequest` ni acepta un campo de rol, así que cualquier `role`/`role_id` enviado por el cliente se ignora.
- El middleware `admin` (alias registrado en `bootstrap/app.php`, implementado en `App\Http\Middleware\EnsureUserIsAdmin`) exige `auth:api` primero (`401` sin token) y luego `role.role_name === 'admin'` (`403` en caso contrario).

### Tipo de cuenta y moneda

Cada cuenta tiene `type` (`savings`/`checking`, default `savings`) y `currency` (`ARS`/`USD`, default `ARS`). Los montos de depósitos, transferencias y movimientos pertenecen siempre a la moneda de la cuenta: **no hay conversión entre monedas** en esta entrega.

### Simulación de plazo fijo

Puramente informativa: no descuenta saldo, no acredita nada, no crea `Movement`. Parámetros en `config/investments.php`:

- TNA fija del **30%**, no configurable por el cliente.
- Interés simple: `interés = monto × TNA × (días / 365)`.
- Plazo permitido: 30 a 365 días (`term_days` o `end_date`, nunca ambos).

## Manejo de errores y seguridad

Toda excepción bajo `/api/v1` (centralizado en `bootstrap/app.php`) responde en JSON, nunca redirige a una ruta web de login. Las respuestas de **error** siempre siguen el formato:

```json
{ "success": false, "message": "..." }
```

Con `"errors"` adicional en el caso de `422`. Nota: las respuestas de **éxito** no siempre incluyen `"success": true` — varían según el controlador (por ejemplo, `POST /admin/users` y todo `Admin\MovementController` responden `{"message": "...", "data": {...}}` sin esa clave).

Códigos usados:

| Código | Cuándo |
|---|---|
| `401` | Falta el token o no es válido. |
| `403` | Autenticado, pero sin permiso (por ejemplo, `user` intentando entrar a `/admin/*`). |
| `404` | Recurso no encontrado. |
| `422` | Datos de entrada inválidos (incluye el detalle por campo en `errors`). |
| `429` | Límite de intentos de login superado. |
| `500` | Error interno — nunca expone SQL, trazas ni detalles internos. |

> Nota: `/movements` (endpoint propio) solo requiere `auth:api` — a diferencia de los listados `/admin/*`, no depende de un rol, así que nunca devuelve `403`.

Además:

- El login admite máximo 5 intentos fallidos por minuto por combinación de email normalizado + IP; al superar el límite responde `429` JSON.
- `.env`, `JWT_SECRET`, tokens y credenciales reales **no están versionados**. `.env.example` no contiene secretos.

## Checklist de demo (WAL-024)

Para la demo final, usar al menos **dos usuarios** y **un administrador** (`testuser@example.test` / `admin@example.test`, o crear otro usuario con `/auth/register`) y mostrar:

- [ ] Login con JWT
- [ ] Consulta de perfil propio
- [ ] Consulta de cuenta y saldo
- [ ] Un depósito
- [ ] Una transferencia entre los dos usuarios
- [ ] Guardar y listar un CBU de tercero
- [ ] Historial de movimientos paginado
- [ ] Simulación de plazo fijo
- [ ] Un error de validación (por ejemplo, `amount` negativo en `/deposits` → `422`)
- [ ] Un intento de transferencia con saldo insuficiente → `422`
- [ ] Un usuario común intentando entrar a una ruta `/admin/*` → `403`

## Frontend demostrativo

Se integró el frontend base provisto por la cátedra, que consume la API por AJAX/JSON y demuestra login, perfil, cuenta, depósito, transferencia e historial. El token se guarda solo en el cliente (nunca se versiona) y las secciones privadas redirigen al login si no hay sesión. La API sigue siendo utilizable sin el frontend, directamente desde Swagger o cualquier cliente HTTP.
