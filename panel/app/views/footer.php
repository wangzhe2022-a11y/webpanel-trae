    </div>
</div>
<script src="/static/js/panel-shell.js"></script>
<script>
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
