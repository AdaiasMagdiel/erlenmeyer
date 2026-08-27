# Deploying to Production

!!! note "Tradução em andamento"
    Esta página ainda não foi traduzida para pt-BR e está exibindo o conteúdo original em inglês. [Ajude a traduzir](https://github.com/AdaiasMagdiel/Erlenmeyer).


Erlenmeyer has no framework-specific deployment step — it's plain PHP behind a front
controller. What matters is getting your web server to route every request to a single
entry point (`index.php` or `public/index.php`) and, if that server sits behind a reverse
proxy or load balancer, telling Erlenmeyer which peer to trust.

This guide covers the front-controller setup for the servers you'll most likely deploy
behind: **Apache**, **Nginx**, **Caddy**, and **Traefik**.

---

## The Front Controller Pattern

Every request — whatever the URL — should reach the same PHP file, which boots `App` and
lets Erlenmeyer's router decide what runs:

```php
<?php
// public/index.php
require __DIR__ . '/../vendor/autoload.php';

$app = new App();
// ... routes ...
$app->run();
```

Static assets (CSS, JS, images) should be served directly by the web server from a public
directory — never routed through PHP. See [`Response::withFile()`](../reference/Response.md)
for gated downloads that *do* need to go through PHP.

---

## Apache

Use `mod_rewrite` to send everything except real files to `index.php`. This is the same
`.htaccess` from [Getting Started](../getting-started.md#recommended-htaccess):

```apache
RewriteEngine On

Options -Indexes
Options +FollowSymLinks

Header always unset X-Powered-By

# Allow access to static files
RewriteRule ^(assets|public)/.* - [L]

# Block direct access to PHP files except index.php
RewriteCond %{REQUEST_URI} !/index\.php$ [NC]
RewriteCond %{REQUEST_URI} \.php$ [NC]
RewriteRule ^ - [R=404,L]

# Redirect everything else to index.php
RewriteRule ^ index.php [L]
```

`.htaccess` files are convenient but read on every request. For a production VirtualHost,
move the same rules into `<Directory>` and set `AllowOverride None` for a small speed win.

---

## Nginx

Nginx doesn't execute PHP itself — it proxies to PHP-FPM over FastCGI. A typical block:

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/example.com/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Deny access to dotfiles (.env, .git, etc.)
    location ~ /\. {
        deny all;
    }
}
```

If Nginx terminates TLS in front of PHP-FPM, add `fastcgi_param HTTPS on;` inside the PHP
block — otherwise `Request::isSecure()` sees plain HTTP and returns `false`.

---

## Caddy

Caddy's automatic HTTPS and one-line PHP integration make it the least config for a small
deployment:

```caddyfile
example.com {
    root * /var/www/example.com/public
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}
```

`php_fastcgi` already routes non-file requests to `index.php` and sets `HTTPS` correctly —
no extra rewrite rules needed.

---

## Traefik

Traefik is a reverse proxy and router, not a PHP runtime — it sits in front of Nginx, Caddy,
or a PHP-FPM-backed container and forwards matching requests to it. It's most common in
Docker/Kubernetes setups. A Docker Compose example routing a domain to a PHP app container
(itself running Caddy or Nginx internally):

```yaml
services:
  app:
    build: .
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.app.rule=Host(`example.com`)"
      - "traefik.http.routers.app.tls.certresolver=letsencrypt"
      - "traefik.http.services.app.loadbalancer.server.port=80"

  traefik:
    image: traefik:v3
    command:
      - "--providers.docker=true"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge=true"
    ports:
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
```

Because Traefik terminates TLS and forwards plain HTTP to your app container, treat it the
same as any reverse proxy for the two points below.

---

## Behind a Reverse Proxy: Trusted IPs and HTTPS Detection

Whenever Erlenmeyer runs behind Nginx-as-proxy, Traefik, a load balancer, or any setup where
something else sits between the client and PHP, two things need explicit configuration:

**Client IP.** By default `Request::getIp()` returns `REMOTE_ADDR`, which behind a proxy is
the *proxy's* IP, not the visitor's. Tell Erlenmeyer to trust that proxy so it reads the real
client IP from `X-Forwarded-For` instead:

```php
$app->setTrustedProxies(['172.18.0.1']); // your proxy/load balancer's IP
```

Never trust a proxy IP you don't control — see [Concepts → Requests](../concepts/requests.md#getting-client-info)
for why.

**HTTPS detection.** `Request::isSecure()` checks `$_SERVER['HTTPS']` and
`$_SERVER['SERVER_PORT']` — neither of which reflects reality when TLS is terminated by the
proxy and forwarded to PHP over plain HTTP. Caddy's `php_fastcgi` sets these correctly for
you; with Nginx or a custom FastCGI setup behind a TLS-terminating proxy, set
`fastcgi_param HTTPS on;` (or the equivalent for your setup) so `isSecure()` reports
correctly.

---

## Production Checklist

- Install dependencies without dev packages and with an optimized autoloader:
  ```bash
  composer install --no-dev --optimize-autoloader
  ```
- Enable OPcache in `php.ini` (`opcache.enable=1`) — Erlenmeyer has no build step, so OPcache
  is what keeps repeated `require`/autoload parsing cheap.
- Set `display_errors = Off` in `php.ini`. Erlenmeyer's default exception handler already
  returns a generic 500 page instead of leaking `getMessage()` to the client, but PHP-level
  fatal errors before that handler runs still respect `display_errors`.
- Make sure the session save path (`session.save_path`) is writable by the PHP process user.
- Point your web server's document root at `public/`, not the project root, so `vendor/`,
  `.env`, and `composer.json` are never served directly.

---

## See Also

- [Getting Started](../getting-started.md) — local development setup
- [Concepts → Requests](../concepts/requests.md) — client IP and trusted proxies in depth
- [Reference → App](../reference/App.md) — `setTrustedProxies()`
