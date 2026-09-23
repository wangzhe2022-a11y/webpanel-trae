/* Theme toggle, wallpaper (dark only), and CasaOS-style header clock. */
(function () {
    var THEME_KEY = 'wp.theme';
    var WEEK = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];

    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function wallpaperCss(src) {
        if (!src) return 'none';
        return 'url("' + String(src).replace(/"/g, '') + '")';
    }

    function applyWallpaper(src) {
        var root = document.documentElement;
        if (src) root.setAttribute('data-wallpaper', src);
        else root.removeAttribute('data-wallpaper');
        if (currentTheme() === 'dark' && src) {
            root.style.setProperty('--wp-wallpaper-image', wallpaperCss(src));
        } else {
            root.style.removeProperty('--wp-wallpaper-image');
        }
        var box = document.getElementById('wpPreview');
        if (!box) return;
        if (src) {
            box.hidden = false;
            var img = box.querySelector('img');
            if (!img) {
                img = document.createElement('img');
                img.alt = '当前壁纸';
                box.appendChild(img);
            }
            img.src = src;
        } else {
            box.hidden = true;
            box.innerHTML = '';
        }
    }

    function applyTheme(theme) {
        theme = theme === 'dark' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
        document.cookie = 'wp_theme=' + theme + ';path=/;max-age=31536000;samesite=lax';
        var icon = document.getElementById('btnThemeIcon');
        if (icon) {
            icon.className = 'layui-icon ' + (theme === 'dark' ? 'layui-icon-light' : 'layui-icon-moon');
        }
        var btn = document.getElementById('btnTheme');
        if (btn) {
            btn.title = theme === 'dark' ? '切换到浅色主题' : '切换到深色玻璃主题';
        }
        applyWallpaper(document.documentElement.getAttribute('data-wallpaper') || '');
    }

    function tickClock() {
        var timeEl = document.getElementById('wpClockTime');
        var dateEl = document.getElementById('wpClockDate');
        if (!timeEl || !dateEl) return;
        var d = new Date();
        var hh = ('0' + d.getHours()).slice(-2);
        var mm = ('0' + d.getMinutes()).slice(-2);
        timeEl.textContent = hh + ':' + mm;
        dateEl.textContent = (d.getMonth() + 1) + '月' + d.getDate() + '日 ' + WEEK[d.getDay()];
    }

    applyTheme(currentTheme());
    tickClock();
    setInterval(tickClock, 15000);

    document.addEventListener('click', function (ev) {
        var appear = document.getElementById('wpAppear');
        var wrap = document.querySelector('.wp-appear-wrap');
        if (!appear || appear.hidden) return;
        if (wrap && wrap.contains(ev.target)) return;
        appear.hidden = true;
    });

    var themeBtn = document.getElementById('btnTheme');
    if (themeBtn) {
        themeBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
        });
    }

    var appearBtn = document.getElementById('btnAppearance');
    var appear = document.getElementById('wpAppear');
    if (appearBtn && appear) {
        appearBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            appear.hidden = !appear.hidden;
        });
    }

    function say(ok, msg) {
        if (window.layui && layui.layer) {
            layui.layer.msg(msg, { icon: ok ? 1 : 2 });
        }
    }

    function afterWallpaper(res) {
        if (!res || !res.ok) {
            say(false, (res && res.error) || '操作失败');
            return;
        }
        applyWallpaper(res.wallpaper || '');
        say(true, res.wallpaper ? '壁纸已更新（深色主题生效）' : '已清除壁纸');
    }

    var fileInput = document.getElementById('wpFile');
    var uploadBtn = document.getElementById('btnWpUpload');
    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            fileInput.click();
        });
        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0];
            fileInput.value = '';
            if (!file) return;
            if (file.size > 8 * 1024 * 1024) {
                say(false, '壁纸不能超过 8MB');
                return;
            }
            var fd = new FormData();
            fd.append('file', file);
            WP.post('/appearance/wallpaper', fd).then(afterWallpaper);
        });
    }

    var urlBtn = document.getElementById('btnWpUrl');
    var urlInput = document.getElementById('wpUrl');
    if (urlBtn && urlInput) {
        urlBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            var url = (urlInput.value || '').trim();
            if (!url) {
                say(false, '请填写图片 URL');
                return;
            }
            WP.post('/appearance/wallpaper', { url: url }).then(afterWallpaper);
        });
    }

    var clearBtn = document.getElementById('btnWpClear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            WP.post('/appearance/clear', {}).then(afterWallpaper);
        });
    }
})();
