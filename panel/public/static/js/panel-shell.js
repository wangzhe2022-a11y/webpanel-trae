/* Theme toggle, wallpaper (dark only), CasaOS-style clock, and side nav collapse. */
(function () {
    var THEME_KEY = 'wp.theme';
    var SIDE_KEY = 'wp.sideCollapsed';
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

    function isSideCollapsed() {
        return document.documentElement.classList.contains('wp-side-collapsed');
    }

    function applySideCollapsed(collapsed) {
        collapsed = !!collapsed;
        document.documentElement.classList.toggle('wp-side-collapsed', collapsed);
        try { localStorage.setItem(SIDE_KEY, collapsed ? '1' : '0'); } catch (e) {}
        var icon = document.getElementById('btnSideToggleIcon');
        if (icon) {
            icon.className = 'layui-icon ' + (collapsed ? 'layui-icon-spread-left' : 'layui-icon-shrink-right');
        }
        var btn = document.getElementById('btnSideToggle');
        if (btn) {
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            btn.title = collapsed ? '展开侧栏' : '折叠侧栏';
        }
    }

    function tickClock() {
        var d = new Date();
        var hh = ('0' + d.getHours()).slice(-2);
        var mm = ('0' + d.getMinutes()).slice(-2);
        var ss = ('0' + d.getSeconds()).slice(-2);
        var time = hh + ':' + mm + ':' + ss;
        var date = d.getFullYear() + '年' + (d.getMonth() + 1) + '月' + d.getDate() + '日 ' + WEEK[d.getDay()];
        var times = document.querySelectorAll('.wp-clock-time');
        var dates = document.querySelectorAll('.wp-clock-date');
        var i;
        for (i = 0; i < times.length; i++) times[i].textContent = time;
        for (i = 0; i < dates.length; i++) dates[i].textContent = date;
    }

    applyTheme(currentTheme());
    applySideCollapsed(isSideCollapsed());
    tickClock();
    setInterval(tickClock, 1000);

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

    document.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('#btnSideToggle') : null;
        if (!btn) return;
        ev.preventDefault();
        applySideCollapsed(!isSideCollapsed());
    });

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
        applyLogo(res.logo || '', res.logo_custom || '');
        say(true, res.wallpaper ? '壁纸已更新（深色主题生效）' : '已清除壁纸');
    }

    function applyLogo(logo, custom) {
        var box = document.getElementById('wpLogoPreview');
        if (box) {
            var img = box.querySelector('img');
            if (!img) {
                img = document.createElement('img');
                img.alt = '当前标志';
                box.appendChild(img);
            }
            img.src = custom || logo || '';
        }
        var brand = document.querySelector('.layui-logo');
        if (!brand) return;
        var existing = brand.querySelector('.wp-logo-img');
        var icon = brand.querySelector('.wp-logo-icon');
        if (custom) {
            if (!existing) {
                existing = document.createElement('img');
                existing.className = 'wp-logo-img';
                existing.alt = '';
                brand.insertBefore(existing, brand.firstChild);
            }
            existing.src = custom;
            if (icon) icon.style.display = 'none';
        } else {
            if (existing) existing.remove();
            if (icon) icon.style.display = '';
            else {
                icon = document.createElement('span');
                icon.className = 'layui-icon layui-icon-template-1 wp-logo-icon';
                brand.insertBefore(icon, brand.firstChild);
            }
        }
    }

    function afterLogo(res) {
        if (!res || !res.ok) {
            say(false, (res && res.error) || '操作失败');
            return;
        }
        applyLogo(res.logo || '', res.logo_custom || '');
        say(true, res.logo_custom ? '登录标志已更新' : '已恢复默认标志');
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

    var logoInput = document.getElementById('wpLogoFile');
    var logoUploadBtn = document.getElementById('btnLogoUpload');
    if (logoUploadBtn && logoInput) {
        logoUploadBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            logoInput.click();
        });
        logoInput.addEventListener('change', function () {
            var file = logoInput.files && logoInput.files[0];
            logoInput.value = '';
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                say(false, '标志不能超过 2MB');
                return;
            }
            var fd = new FormData();
            fd.append('file', file);
            WP.post('/appearance/logo', fd).then(afterLogo);
        });
    }

    var logoClearBtn = document.getElementById('btnLogoClear');
    if (logoClearBtn) {
        logoClearBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            WP.post('/appearance/logo/clear', {}).then(afterLogo);
        });
    }
})();
