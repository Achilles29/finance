<?php
declare(strict_types=1);

/** Private Linux profile renderer. No service restarts, database access or public listeners. */
final class LinuxWebProfile
{
    public static function render(array $p): array
    {
        foreach (['app_root','state_root','deployment_file','tls_certificate','tls_key','mime_types'] as $key) {
            $path = $p[$key] ?? null;
            if (!is_string($path) || preg_match('~\A/[A-Za-z0-9_./-]+\z~D', $path) !== 1
                || strpos($path, '..') !== false || realpath($path) !== $path || is_link($path)) throw new RuntimeException('PROFILE_PATH_INVALID');
        }
        foreach (['user','group'] as $key) if (!is_string($p[$key] ?? null) || preg_match('/\A[a-z_][a-z0-9_-]{0,30}\z/D', $p[$key]) !== 1 || $p[$key] === 'root') throw new RuntimeException('PROFILE_ACCOUNT_INVALID');
        if (!is_int($p['port'] ?? null) || $p['port'] < 1024 || $p['port'] > 65535
            || !is_dir($p['app_root']) || !is_dir($p['state_root'])
            || strpos($p['state_root'] . '/', $p['app_root'] . '/') === 0) throw new RuntimeException('PROFILE_BOUNDARY_INVALID');
        $app = $p['app_root']; $s = $p['state_root']; $user = $p['user']; $group = $p['group']; $port = $p['port'];
        $fpm = "[global]\npid = {$s}/fpm.pid\nerror_log = {$s}/fpm.log\ndaemonize = no\n[finance]\nuser = {$user}\ngroup = {$group}\nlisten = {$s}/php.sock\nlisten.owner = {$user}\nlisten.group = {$group}\nlisten.mode = 0600\npm = ondemand\npm.max_children = 2\npm.process_idle_timeout = 10s\nclear_env = yes\nchdir = {$app}\nsecurity.limit_extensions = .php\ncatch_workers_output = yes\nenv[CI_ENV] = production\nenv[FINANCE_DEPLOYMENT_FILE] = {$p['deployment_file']}\nphp_admin_flag[display_errors] = off\nphp_admin_flag[log_errors] = on\nphp_admin_value[error_log] = {$s}/logs/php-error.log\nphp_admin_value[upload_tmp_dir] = {$s}/tmp\nphp_admin_value[sys_temp_dir] = {$s}/tmp\n";
        $nginx = <<<'NGINX'
user @USER@ @GROUP@;
worker_processes 1;
pid @STATE@/nginx.pid;
error_log @STATE@/nginx.log warn;
daemon off;
events { worker_connections 128; }
http {
 include @MIME@;
 default_type application/octet-stream;
 access_log off;
 server_tokens off;
 client_body_temp_path @STATE@/tmp/body;
 fastcgi_temp_path @STATE@/tmp/fastcgi;
 server {
  listen 127.0.0.1:@PORT@ ssl;
  server_name localhost 127.0.0.1;
  ssl_certificate @CERT@;
  ssl_certificate_key @KEY@;
  ssl_protocols TLSv1.2 TLSv1.3;
  root @APP@;
  client_max_body_size 16m;
  add_header X-Content-Type-Options nosniff always;
  location = /index.php {
   fastcgi_pass unix:@STATE@/php.sock;
   fastcgi_param QUERY_STRING $query_string;
   fastcgi_param REQUEST_METHOD $request_method;
   fastcgi_param CONTENT_TYPE $content_type;
   fastcgi_param CONTENT_LENGTH $content_length;
   fastcgi_param SCRIPT_FILENAME @APP@/index.php;
   fastcgi_param SCRIPT_NAME /index.php;
   fastcgi_param REQUEST_URI $request_uri;
   fastcgi_param DOCUMENT_URI $document_uri;
   fastcgi_param DOCUMENT_ROOT @APP@;
   fastcgi_param SERVER_PROTOCOL $server_protocol;
   fastcgi_param REQUEST_SCHEME https;
   fastcgi_param HTTPS on;
   fastcgi_param GATEWAY_INTERFACE CGI/1.1;
   fastcgi_param SERVER_SOFTWARE nginx;
   fastcgi_param REMOTE_ADDR $remote_addr;
   fastcgi_param REMOTE_PORT $remote_port;
   fastcgi_param SERVER_ADDR $server_addr;
   fastcgi_param SERVER_PORT $server_port;
   fastcgi_param SERVER_NAME $server_name;
   fastcgi_param REDIRECT_STATUS 200;
  }
  location ~ (^|/)[.] { return 404; }
  location ~* ^/(application|tools|sql|docs|vendor|wa-engine|backup)(/|$) { return 404; }
  location ~* ^/system/(core|database|fonts|helpers|language|libraries)(/|$) { return 404; }
  location ~* [.]php(?:/|$) { return 404; }
  location ~* ^/(assets|uploads)/.*[.](css|js|png|jpe?g|gif|webp|ico|svg|woff2?|ttf)$ { try_files $uri =404; }
  location / { rewrite ^ /index.php last; }
 }
}
NGINX;
        $nginx = strtr($nginx, ['@USER@'=>$user,'@GROUP@'=>$group,'@STATE@'=>$s,'@MIME@'=>$p['mime_types'],
            '@PORT@'=>(string)$port,'@CERT@'=>$p['tls_certificate'],'@KEY@'=>$p['tls_key'],'@APP@'=>$app]);
        return ['php-fpm.conf' => $fpm, 'nginx.conf' => $nginx . "\n"];
    }
}
