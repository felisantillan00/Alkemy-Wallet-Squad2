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
abstract class Controller
{
    //
}
