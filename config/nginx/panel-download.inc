# ============================================================================
# Streaming downloads for the panel vhost (file manager + backups).
#
# Included from the panel server {} block. Existing CVMs: add the same
# `include` line to /etc/nginx/conf.d/00-webpanel.conf (ops follow-up).
#
# Why not X-Accel-Redirect for vdb (/mnt/backup)?
#   Files there are typically root-owned and the panel php-fpm pool is
#   jailed by open_basedir. Giving the nginx worker read access to the
#   backup disk would weaken the jail. Downloads stay on sudo wp-fs.sh
#   cat / wp-backup.sh download; this snippet only keeps nginx from
#   buffering or timing out the already-flushed response body.
# ----------------------------------------------------------------------------

location ^~ /files/download {
    include fastcgi_params;
    fastcgi_pass unix:/run/php-fpm/panel.sock;
    fastcgi_param SCRIPT_FILENAME /usr/local/webpanel/panel/public/index.php;
    fastcgi_param SCRIPT_NAME /index.php;
    fastcgi_param DOCUMENT_ROOT /usr/local/webpanel/panel/public;
    fastcgi_param HTTPS on;
    fastcgi_read_timeout 3600;
    fastcgi_send_timeout 3600;
    fastcgi_buffering off;
    gzip off;
}

location ^~ /backup/download {
    include fastcgi_params;
    fastcgi_pass unix:/run/php-fpm/panel.sock;
    fastcgi_param SCRIPT_FILENAME /usr/local/webpanel/panel/public/index.php;
    fastcgi_param SCRIPT_NAME /index.php;
    fastcgi_param DOCUMENT_ROOT /usr/local/webpanel/panel/public;
    fastcgi_param HTTPS on;
    fastcgi_read_timeout 3600;
    fastcgi_send_timeout 3600;
    fastcgi_buffering off;
    gzip off;
}
