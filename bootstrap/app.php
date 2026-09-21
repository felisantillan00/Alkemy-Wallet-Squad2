<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
              $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // En la API nunca se redirige a un login web: el 401 lo resuelve el handler de excepciones
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/login'
        );
    })
       ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // El 401 de la API mantiene el mismo formato que el resto de las respuestas
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado. Falta el token o no es válido.',
                ], 401);
            }
        });

        // El 404 de la API mantiene el mismo formato que el resto de respuestas
        $exceptions->render(function(NotFoundHttpException $e, Request $request){
            if($request->is('api/*')){
                return response()->json([
                    'success' => false,
                    'message' => 'Recurso no encontrado.',
                ], 404);
            }
        });
    
        // El 403 de la API mantiene el mismo formato que el resto de respuestas
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tenés permisos para realizar esta acción.',
                ], 403);
            }
        });

        // El 422 de la API mantiene el mismo formato que el resto de respuestas
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Los datos enviados no son válidos.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // Atrapa-todo de la API: nunca expone SQL, trazas ni detalles internos.
        // Tiene que quedar SIEMPRE como último render del archivo.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*') || $e instanceof HttpResponseException) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                $mensajes = [
                    405 => 'Método HTTP no permitido para esta ruta.',
                    429 => 'Demasiadas solicitudes. Intentá de nuevo más tarde.',
                ];

                return response()->json([
                    'success' => false,
                    'message' => $mensajes[$status] ?? 'No se pudo procesar la solicitud.',
                ], $status, $e->getHeaders());
            }

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor.',
            ], 500);
        });
    })->create();
