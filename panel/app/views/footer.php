    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/layui@2.9.16/dist/layui.js"></script>
<script>
/* Shared helpers for every panel page */
window.WP = (function () {
    var csrf = document.querySelector('meta[name="csrf"]').getAttribute('content');

    function post(url, data) {
        var body;
        if (data instanceof FormData) {
            data.append('_csrf', csrf);
            body = data;
        } else {
            body = new URLSearchParams(data || {});
            body.append('_csrf', csrf);
        }
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { j.__status = r.status; return j; }); });
    }

    function copy(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        var ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta);
        return Promise.resolve();
    }

    return { post: post, copy: copy, csrf: csrf };
})();

layui.use(['element', 'layer'], function () {
    var layer = layui.layer, $ = layui.$;
    $('#btnLogout').on('click', function () {
        layer.confirm('确定退出登录？', { title: '退出' }, function (idx) {
            fetch('/logout', { method: 'POST', body: new URLSearchParams({ _csrf: WP.csrf }) })
                .then(function () { location.href = '/login'; });
            layer.close(idx);
        });
    });
});
</script>
<?php if (PANEL_DRY): ?>
<script>
layui.use('layer', function () {
    layui.layer.msg('DRY-RUN 演示模式：不会真正修改系统', { icon: 0, time: 4000, offset: 't' });
});
</script>
<?php endif; ?>
</body>
</html>
