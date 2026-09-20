# ============================================================================
# phpMyAdmin on the panel vhost (/phpmyadmin/)
# Gated by the panel session cookie via auth_request → /phpmyadmin-auth.
# Included from the panel server {} block. Do not use as a standalone vhost.
# ----------------------------------------------------------------------------

location = /phpmyadmin-auth {
    internal;
    fastcgi_pass unix:/run/php-fpm/panel.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /usr/local/webpanel/panel/public/index.php;
    fastcgi_param SCRIPT_NAME /index.php;
    fastcgi_param DOCUMENT_URI /phpmyadmin-auth;
    fastcgi_param DOCUMENT_ROOT /usr/local/webpanel/panel/public;
    fastcgi_param REQUEST_URI /phpmyadmin-auth;
    fastcgi_param QUERY_STRING "";
    fastcgi_param REQUEST_METHOD GET;
    fastcgi_param HTTPS on;
    fastcgi_pass_request_body off;
    fastcgi_param CONTENT_LENGTH "";
}

location @pma_unauth {
    return 302 /login;
}

location ^~ /phpmyadmin/ {
    auth_request /phpmyadmin-auth;
    error_page 401 = @pma_unauth;

    alias /www/server/phpmyadmin/;
    index index.php;

    location ~ ^/phpmyadmin/(?:setup|libraries|templates|sql|vendor|tmp|locale|examples)(/|$) {
        deny all;
    }

    location ~* ^/phpmyadmin/(?:config\.inc\.php|config\.secret\.php|composer\.(json|lock)|package\.json|\.wp-secret|\.webpanel-version) {
        deny all;
    }

    location ~ ^/phpmyadmin/(.+\.php)$ {
        auth_request /phpmyadmin-auth;
        error_page 401 = @pma_unauth;
        fastcgi_pass unix:/run/php-fpm/phpmyadmin.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /www/server/phpmyadmin/$1;
        fastcgi_param DOCUMENT_ROOT /www/server/phpmyadmin;
        fastcgi_param HTTPS on;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }
}
