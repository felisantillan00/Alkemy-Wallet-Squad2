<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Setup

Pasos para levantar el proyecto localmente:

```sh
composer install

cp .env.example .env
php artisan key:generate
php artisan jwt:secret

php artisan migrate --seed
php artisan test
```

- `key:generate` crea el `APP_KEY` de Laravel.
- `jwt:secret` crea el `JWT_SECRET` que usa `php-open-source-saver/jwt-auth`; sin este paso, cualquier ruta protegida con `auth:api` responde `500` en vez de `401`/`200`.
- Ambos comandos escriben directamente en tu `.env` local, que no se versiona — cada desarrollador debe correrlos una vez después de clonar.

## Eliminación de perfil (`DELETE /api/v1/profile`)

Eliminar el propio perfil hace un **soft delete** del usuario (`deleted_at`), no un borrado físico:

- El usuario deja de poder autenticarse (login) y de aparecer en cualquier consulta normal (el scope de `SoftDeletes` lo excluye automáticamente), pero la fila sigue en `users`.
- La **cuenta** (`accounts`) no se toca: `accounts.user_id` sigue apuntando al mismo usuario, con su `balance` y `cbu` intactos. Como la fila de `users` no se borra de verdad, la FK `accounts.user_id → users.id` (`onDelete('set null')`) nunca se dispara.
- Los **movimientos** (`movements`) tampoco se tocan: siguen asociados a la misma cuenta, sin pérdida de historial.
- Los **CBUs de terceros guardados** (`saved_accounts`, WAL-010/011) no se tocan por la misma razón: al no borrarse la fila de `users`, ninguna FK sobre `user_id` se dispara. *(Nota: `saved_accounts` todavía no existe en esta rama — se agregó en WAL-010/011, pendientes de merge a `dev`. Cuando se integren, conviene revisar este comportamiento con la tabla ya presente.)*

Se eligió soft delete en vez de borrado físico porque es una wallet: borrar en duro al usuario arrastraría (vía `SET NULL`) una cuenta con saldo y movimientos a un estado huérfano e irreversible. Con soft delete, el dato queda íntegro y trazable, y es reversible si se decide restaurar al usuario.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
