<?php /** dry-run console stand-in page */ ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installatron 控制台（DRY-RUN 演示）</title>
<link rel="stylesheet" href="/static/layui/css/layui.css">
<style>
    body { background: linear-gradient(135deg,#0f2027,#203a43,#2c5364); height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; }
    .demo-box { width: 560px; background: #fff; border-radius: 10px; box-shadow: 0 18px 60px rgba(0,0,0,.35); overflow: hidden; }
    .demo-head { padding: 30px 30px 10px; text-align: center; }
    .demo-head .layui-icon { font-size: 56px; color: #1e9fff; }
    .demo-head h2 { margin: 10px 0 0; font-size: 20px; }
    .demo-body { padding: 8px 34px 34px; color: #555; font-size: 14px; line-height: 2; }
    .demo-body .mono { font-family: ui-monospace, Menlo, Consolas, monospace; background: #f4f4f4; padding: 2px 6px; border-radius: 4px; font-size: 12.5px; }
</style>
</head>
<body>
<div class="demo-box">
    <div class="demo-head">
        <span class="layui-icon layui-icon-app"></span>
        <h2>Installatron 控制台占位页</h2>
    </div>
    <div class="demo-body">
        <p><b>DRY-RUN 演示模式</b>下不会真正安装 Installatron，此页仅替代官方控制台入口。</p>
        <p>真实环境中点击「打开 Installatron 控制台」后：</p>
        <ul style="padding-left:18px;margin:8px 0">
            <li>面板通过 <span class="mono">installatron --POST /users/root/login</span> 创建一次性会话</li>
            <li>新窗口直达官方 GUI（320+ 应用一键安装/升级/克隆），无需二次登录</li>
            <li>会话 URL 一次性有效，退出后自动失效</li>
        </ul>
        <p style="text-align:center;margin-top:18px">
            <a class="layui-btn" href="javascript:window.close()">关闭此窗口</a>
            <a class="layui-btn layui-btn-primary" href="/installatron">返回面板</a>
        </p>
    </div>
</div>
</body>
</html>
