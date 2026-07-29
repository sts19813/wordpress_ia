# Despliegue de colas en Hostinger

La generación de artículos e imágenes se procesa fuera de la petición web. Así nginx responde de inmediato y no llega al límite que provocaba `504 Gateway Time-out`.

## 1. Variables de entorno

En el `.env` del servidor:

```dotenv
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=360
OPENAI_CONNECT_TIMEOUT=15
OPENAI_TIMEOUT=180
```

`DB_QUEUE_RETRY_AFTER` debe ser mayor que el timeout del worker para evitar que dos procesos tomen la misma tarea.

## 2. Actualizar la aplicación

Desde la raíz del proyecto en el servidor:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

Comprueba también que `storage/` y `bootstrap/cache/` tengan permisos de escritura y que el document root del dominio apunte a `public/`.

## 3. Crear el cron

En **hPanel → Sitios web → Administrar → Tareas cron**, crea una tarea de tipo **Custom** cada minuto. Usa la ruta real de tu cuenta:

```bash
/usr/bin/php /home/USUARIO/domains/DOMINIO/public_html/artisan schedule:run
```

El programador de Laravel ejecutará el worker de las colas `ai-text` y `ai-image`, sin solapar procesos. Puedes comprobar el registro con:

```bash
php artisan schedule:list
```

Durante la primera puesta en marcha conviene dejar la salida sin redirigir: hPanel permite usar **View Output** para confirmar que el cron sí se ejecuta. Cuando todo funcione puedes silenciarla si lo prefieres.

Si hPanel no acepta una frecuencia menor a 5 minutos, el sistema seguirá funcionando, pero un trabajo nuevo puede esperar hasta 5 minutos antes de empezar.

## 4. Diagnóstico

```bash
php artisan queue:work database --queue=social-capture,ai-text,ai-image --stop-when-empty --tries=3 --timeout=300
php artisan queue:failed
php artisan queue:retry all
```

El primer comando permite procesar manualmente la cola para comprobar la configuración. El módulo **Programador** muestra el avance, los intentos, la etapa actual y la bitácora de cada solicitud.

## 5. Navegador para Post rápido

El módulo **Post rápido** abre publicaciones públicas de Facebook, X e Instagram con un navegador sin sesión. El servidor necesita Node.js y un ejecutable de Chromium, Google Chrome o Microsoft Edge.

Instala las dependencias de Node con `npm ci` y configura la ruta real en `.env`:

```dotenv
SOCIAL_BROWSER_EXECUTABLE=/usr/bin/chromium
SOCIAL_BROWSER_WS_ENDPOINT=
SOCIAL_NODE_BINARY=node
SOCIAL_BROWSER_TIMEOUT=60
SOCIAL_CAPTURE_MODEL=gpt-4.1-mini
```

Busca la ruta disponible con:

```bash
which chromium
which chromium-browser
which google-chrome
```

En un plan compartido que no permita ejecutar Chromium, configura un proveedor de navegador remoto compatible con CDP:

```dotenv
SOCIAL_BROWSER_EXECUTABLE=
SOCIAL_BROWSER_WS_ENDPOINT=wss://PROVEEDOR_CDP?token=TOKEN_PRIVADO
```

No publiques ese endpoint ni su token. La descarga HTTP y la búsqueda web por sí solas no acceden de forma fiable a los posts de Facebook.
