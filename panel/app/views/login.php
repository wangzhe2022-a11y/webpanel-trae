<?php /** @var string $csrf @var string $error */ ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WebPanel 登录</title>
<link rel="stylesheet" href="/static/layui/css/layui.css">
<style>
    body { background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); height: 100vh; margin: 0; }
    .login-box {
        width: 380px; margin: 14vh auto 0; background: #fff; border-radius: 10px;
        box-shadow: 0 18px 60px rgba(0,0,0,.35); overflow: hidden;
    }
    .login-head { padding: 34px 30px 18px; text-align: center; }
    .login-head .logo { font-size: 30px; color: #1e9fff; }
    .login-head h1 { font-size: 20px; margin: 10px 0 4px; }
    .login-head p { color: #999; font-size: 13px; margin: 0; }
    .login-body { padding: 10px 30px 34px; }
    .login-tip { color: #ff5722; font-size: 12px; min-height: 18px; }
</style>
</head>
<body>
<div class="login-box">
    <div class="login-head">
        <div class="logo layui-icon layui-icon-template-1"></div>
        <h1>WebPanel 服务器管理面板</h1>
        <p>Nginx · 多版本 PHP · MySQL 8 · SSL</p>
    </div>
    <div class="login-body">
        <form class="layui-form" id="loginForm">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="layui-form-item">
                <label class="layui-form-label">账号</label>
                <div class="layui-input-block">
                    <input type="text" name="username" required lay-verify="required"
                           placeholder="管理员账号" autocomplete="username"
                           class="layui-input" autofocus>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">密码</label>
                <div class="layui-input-block">
                    <input type="password" name="password" required lay-verify="required"
                           placeholder="登录密码" autocomplete="current-password"
                           class="layui-input">
                </div>
            </div>
            <div class="login-tip" id="tip"><?= e($error) ?></div>
            <button type="submit" class="layui-btn layui-btn-fluid" lay-submit lay-filter="doLogin">登 录</button>
        </form>
    </div>
</div>
<script src="/static/layui/layui.js"></script>
<script>
layui.use(['form', 'layer'], function () {
    var form = layui.form, layer = layui.layer, $ = layui.$;
    form.on('submit(doLogin)', function (data) {
        var btn = $(data.elem).attr('disabled', true).addClass('layui-btn-disabled');
        fetch('/login', { method: 'POST', body: new URLSearchParams(data.field) })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) { location.href = res.redirect || '/'; }
                else {
                    $('#tip').text(res.error || '登录失败');
                    btn.removeAttr('disabled').removeClass('layui-btn-disabled');
                }
            })
            .catch(function () {
                $('#tip').text('网络错误，请重试');
                btn.removeAttr('disabled').removeClass('layui-btn-disabled');
            });
        return false;
    });
});
</script>
</body>
</html>
