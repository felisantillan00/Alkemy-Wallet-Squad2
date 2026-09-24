# Frontend de Wallet

## ¿Qué es?

Este directorio contiene un cliente sencillo para la API de Wallet. Está construido únicamente con HTML, CSS, JavaScript Vanilla y `fetch()`.

Permite registrar usuarios, iniciar y cerrar la sesión local, consultar la cuenta y el saldo, depositar, transferir, consultar movimientos, administrar destinatarios, editar el perfil y simular un plazo fijo.

No incluye funciones administrativas ni elimina perfiles.

## 1. Iniciar la API Laravel

Desde la raíz del proyecto, prepará Laravel si todavía no lo hiciste:

```bash
composer install
php artisan migrate
```

Luego iniciá la API:

```bash
php artisan serve
```

Por defecto quedará disponible en `http://127.0.0.1:8000`.

Para mostrar las imágenes de perfil guardadas también puede ser necesario crear el enlace público una sola vez:

```bash
php artisan storage:link
```

## 2. Servir el frontend mediante HTTP

No abras `index.html` directamente con una URL `file://`. Los módulos JavaScript y las peticiones a la API deben ejecutarse desde un servidor HTTP.

Una opción simple, si tenés PHP instalado, es abrir otra terminal en la raíz del proyecto y ejecutar:

```bash
php -S 127.0.0.1:5500 -t frontend
```

Después abrí:

```text
http://127.0.0.1:5500
```

La API y el frontend deben mantenerse iniciados en sus respectivas terminales.

## 3. Configurar la URL de la API

La URL base se configura en `frontend/index.html`:

```html
<meta name="api-base-url" content="http://127.0.0.1:8000/api/v1">
```

Si Laravel utiliza otro host o puerto, cambiá únicamente el valor de `content`. La URL debe terminar en `/api/v1`.

## 4. Registrarse

1. Abrí el frontend.
2. Completá nombre, email, contraseña y confirmación.
3. La contraseña debe tener al menos 8 caracteres.
4. Presioná **Registrarme**.
5. El registro crea una cuenta Wallet con saldo inicial `0.00`, pero no inicia sesión automáticamente.

## 5. Iniciar y cerrar sesión

Ingresá el email y la contraseña en el formulario de acceso. El JWT recibido se guarda en `sessionStorage`, por lo que pertenece a la sesión actual de la pestaña.

El botón **Cerrar sesión** elimina el JWT local. La API no posee un endpoint de logout de servidor.

Si el JWT vence o la API responde `401`, el frontend elimina el token y vuelve al formulario de acceso.

## 6. Probar depósitos

1. Iniciá sesión.
2. Entrá en **Operaciones**.
3. Escribí un monto mayor que cero, con hasta dos decimales.
4. Presioná **Realizar depósito**.

Al completarse, el frontend actualiza el saldo y la primera página de movimientos.

## 7. Probar transferencias entre dos usuarios

Para probar una transferencia hacen falta dos cuentas:

1. Registrá el primer usuario e iniciá sesión.
2. Realizá un depósito para que tenga saldo.
3. Copiá su CBU si querés usarlo luego.
4. Cerrá sesión.
5. Registrá un segundo usuario e iniciá sesión.
6. Copiá el CBU del segundo usuario.
7. Volvé a iniciar sesión como el primer usuario.
8. Entrá en **Operaciones** y escribí el CBU del segundo usuario y el monto.
9. Presioná **Realizar transferencia**.

También podés guardar previamente el segundo CBU en **Destinatarios**. El botón **Transferir** de un destinatario copia automáticamente su CBU al formulario.

La API rechazará, entre otros casos, un CBU inexistente, el CBU propio o un monto superior al saldo disponible. El frontend muestra el mensaje exacto recibido.

## 8. Consultar movimientos

Entrá en **Movimientos**. La tabla muestra tipo, monto, fecha y CBU contraparte.

Los botones **Anterior** y **Siguiente** utilizan los datos reales del paginador de Laravel. Se muestran 10 movimientos por página, ordenados del más reciente al más antiguo.

## 9. Editar el perfil

En **Perfil** podés modificar nombre, email y edad, además de subir una imagen JPG, PNG o WebP de hasta 2 MB.

La imagen se envía mediante `FormData`. El cliente no establece manualmente `Content-Type`; el navegador agrega el encabezado multipart correspondiente.

## 10. Probar el simulador de plazo fijo

1. Entrá en **Plazo fijo**.
2. Ingresá un monto mayor que cero.
3. Indicá un plazo entre 30 y 365 días.
4. Presioná **Simular**.

Se muestran la fecha inicial, fecha final, capital, interés y total estimado. Es sólo una simulación: no comprueba ni descuenta el saldo y no crea movimientos.

## Panel administrativo

### Ingresar como administrador

Iniciá sesión con un usuario cuyo campo `role` sea `admin`. Un registro público siempre crea usuarios con rol `user`, por lo que el administrador debe existir previamente, por ejemplo mediante el seeder incluido en el proyecto.

Después del login y al restaurar una sesión, el frontend consulta:

```text
GET /api/v1/admin/check
```

Si la API responde `200`, aparece la opción **Administración**. Si responde `403`, la opción permanece oculta. Ocultarla es sólo una decisión de interfaz: todos los endpoints administrativos también están protegidos por la API.

### Administrar usuarios

La pestaña **Usuarios** permite listar, crear, editar y eliminar usuarios. Al crear un usuario, la API crea también su cuenta Wallet vacía.

Al editar, la contraseña nunca se recupera ni se muestra. El campo puede dejarse vacío para conservarla. Antes de eliminar se solicita confirmación y se advierte que también se eliminarán la cuenta, los movimientos y otros datos Wallet asociados.

### Administrar cuentas

La pestaña **Cuentas** permite listar, crear, editar y eliminar cuentas. Para crear una cuenta se debe indicar el ID de un usuario que todavía no tenga una. Al editar no se puede cambiar `user_id`, porque la API no permite reasignar cuentas.

Cambiar administrativamente el saldo de una cuenta **no crea un movimiento automático**. El frontend no intenta compensarlo creando movimientos adicionales.

### Administrar movimientos

La pestaña **Movimientos** permite listar, crear, editar y eliminar movimientos. También permite usar los filtros reales `account_id` y `user_id`.

Los movimientos `transfer_out` y `transfer_in` requieren un CBU contraparte de 22 dígitos. En un movimiento `deposit`, ese campo puede quedar vacío.

Crear, editar o eliminar un movimiento administrativo **no recalcula el saldo de la cuenta**. El frontend muestra esta advertencia y no modifica saldos automáticamente.

### Paginación y permisos

Los tres listados muestran página actual, cantidad de páginas, total de registros y botones anterior/siguiente. Utilizan `page`, `per_page`, `sort` y `order` con valores admitidos por cada endpoint.

Para comprobar la protección con un usuario común:

1. Iniciá sesión con un usuario de rol `user` y verificá que no aparezca **Administración**.
2. Copiá su JWT desde `sessionStorage` sólo en un entorno local de pruebas.
3. Enviá una petición a `GET /api/v1/admin/users` con `Authorization: Bearer TOKEN` desde una herramienta como Swagger o Postman.
4. La API debe responder `403` con el mensaje `Se requiere rol administrador.`.

Si un endpoint administrativo devuelve `403` mientras el panel está abierto, el frontend oculta la sección administrativa y muestra el mensaje de la API.

## Organización

```text
frontend/
├── index.html
├── css/
│   └── styles.css
└── js/
    ├── api.js
    └── app.js
```

- `api.js` contiene toda la comunicación con la API y el manejo del JWT.
- `app.js` contiene eventos, estado y actualización segura del DOM.
- `styles.css` contiene solamente los estilos necesarios para ordenar la interfaz.
