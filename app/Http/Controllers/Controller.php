<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Wallet API',
    description: "API de billetera virtual: usuarios, cuentas, depósitos, transferencias, CBU de terceros, movimientos, plazo fijo y administración.\n\n**Autenticación (Bearer JWT)**\n\n1. Ejecutá `POST /api/v1/auth/login` con email y contraseña.\n2. Copiá el valor de `data.access_token` de la respuesta.\n3. Presioná **Authorize**, pegá solo el token (sin escribir `Bearer`) y confirmá.\n4. Swagger envía `Authorization: Bearer <token>` en todas las rutas con candado.\n\nTodas las respuestas de error tienen el formato `{ \"success\": false, \"message\": \"...\" }`."
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Token JWT obtenido en POST /api/v1/auth/login'
)]
#[OA\Schema(
    schema: 'UnauthorizedError',
    required: ['success', 'message'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'No autenticado. Falta el token o no es válido.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ForbiddenError',
    required: ['success', 'message'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'No tenés permisos de administrador para acceder a este recurso.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'NotFoundError',
    required: ['success', 'message'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Recurso no encontrado.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ValidationError',
    required: ['success', 'message', 'errors'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Los datos enviados no son válidos.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['email' => ['El campo email es obligatorio.']]
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'TooManyAttemptsError',
    required: ['success', 'message', 'retry_after'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Demasiados intentos fallidos. Podés reintentar en 48 segundos.'),
        new OA\Property(property: 'retry_after', type: 'integer', example: 48),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Profile',
    required: ['id', 'name', 'email', 'age', 'image'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'TestUser'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'testuser@example.test'),
        new OA\Property(property: 'age', type: 'integer', nullable: true, example: 25),
        new OA\Property(property: 'image', type: 'string', nullable: true, description: 'URL pública de la imagen de perfil', example: 'http://localhost:8000/storage/profile-images/foto.jpg'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ProfileUpdateInput',
    description: 'Todos los campos son opcionales: se actualizan solo los enviados.',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Nuevo Nombre'),
        new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Único en el sistema (se permite reenviar el propio)', example: 'nuevo@example.test'),
        new OA\Property(property: 'age', type: 'integer', minimum: 1, maximum: 120, nullable: true, example: 30),
        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true, description: 'Solo por multipart/form-data. JPG, JPEG, PNG o WebP, máximo 2 MB'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Account',
    required: ['cbu', 'type', 'currency', 'balance'],
    properties: [
        new OA\Property(property: 'cbu', type: 'string', description: 'CBU de 22 dígitos, único', example: '0000003100000000000001'),
        new OA\Property(property: 'type', type: 'string', enum: ['savings', 'checking'], example: 'savings'),
        new OA\Property(property: 'currency', type: 'string', enum: ['ARS', 'USD'], example: 'ARS'),
        new OA\Property(property: 'balance', type: 'number', format: 'float', example: 1550.5),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Movement',
    required: ['type', 'amount', 'date', 'counterpart_cbu'],
    properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['deposit', 'transfer_in', 'transfer_out'], example: 'deposit'),
        new OA\Property(property: 'amount', type: 'string', description: 'Monto con dos decimales', example: '150.00'),
        new OA\Property(property: 'date', type: 'string', format: 'date-time', example: '2026-09-22T17:09:35.000000Z'),
        new OA\Property(property: 'counterpart_cbu', type: 'string', nullable: true, description: 'CBU de la contraparte; null en depósitos', example: '0000003100000000000002'),
    ],
    type: 'object'
)]
abstract class Controller
{
    //
}
