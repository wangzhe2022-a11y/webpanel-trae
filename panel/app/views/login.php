<?php /** @var string $csrf @var string $error */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login</title>
<link rel="stylesheet" href="/static/layui/css/layui.css">
<style>
    body { background: linear-gradient(135deg, #0a0b0d, #111318, #16181d); height: 100vh; margin: 0; }
    .login-box {
        width: 380px; margin: 14vh auto 0; background: #fff; border-radius: 10px;
        box-shadow: 0 18px 60px rgba(0,0,0,.35); overflow: hidden;
    }
    .login-head { padding: 34px 30px 18px; text-align: center; }
    .login-head .logo { font-size: 30px; color: #90BA1E; }
    .login-head h1 { font-size: 20px; margin: 10px 0 0; }
    .login-body { padding: 10px 30px 34px; }
    .login-tip { color: #ff5722; font-size: 12px; min-height: 18px; }
    .layui-btn { background-color: #90BA1E; color: #1a1f0a; }
    .layui-btn:hover { background-color: #7a9e16; color: #1a1f0a; }
</style>
</head>
<body>
<div class="login-box">
    <div class="login-head">
        <div class="logo layui-icon layui-icon-template-1"></div>
        <h1>Login</h1>
    </div>
    <div class="login-body">
        <form class="layui-form" id="loginForm">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="layui-form-item">
                <label class="layui-form-label">Account</label>
                <div class="layui-input-block">
                    <input type="text" name="username" required lay-verify="required"
                           placeholder="Account" autocomplete="username"
                           class="layui-input" autofocus>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">Password</label>
                <div class="layui-input-block">
                    <input type="password" name="password" required lay-verify="required"
                           placeholder="Password" autocomplete="current-password"
                           class="layui-input">
                </div>
            </div>
            <div class="login-tip" id="tip"><?= e($error) ?></div>
            <button type="submit" class="layui-btn layui-btn-fluid" lay-submit lay-filter="doLogin">Sign in</button>
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
                    $('#tip').text(res.error || 'Login failed');
                    btn.removeAttr('disabled').removeClass('layui-btn-disabled');
                }
            })
            .catch(function () {
                $('#tip').text('Network error, try again');
                btn.removeAttr('disabled').removeClass('layui-btn-disabled');
            });
        return false;
    });
});
</script>
</body>
</html>
