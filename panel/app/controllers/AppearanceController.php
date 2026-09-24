<?php
declare(strict_types=1);

class AppearanceController extends Controller
{
    private const MAX_BYTES = 8 * 1024 * 1024;
    private const MAX_LOGO_BYTES = 2 * 1024 * 1024;
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    private const LOGO_MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    public function info(): void
    {
        $this->requireLogin();
        $this->ok($this->payload());
    }

    public function wallpaper(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();

        if (!empty($_FILES['file']) && (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->saveUpload();
        }

        $url = trim((string) $this->input('url', ''));
        if ($url !== '') {
            $this->saveUrl($url);
        }

        $this->fail('请上传 jpg / png / webp 图片，或填写图片 URL');
    }

    public function clear(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        panel_appearance_clear_files();
        panel_appearance_save(['kind' => '', 'file' => '', 'url' => '', 'updated' => time()]);
        $this->ok($this->payload());
    }

    public function logo(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        if (!empty($_FILES['file']) && (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->saveLogoUpload();
        }
        $this->fail('请上传 png / jpg / webp / svg 标志');
    }

    public function logoClear(): void
    {
        $this->requireLogin();
        $this->verifyCsrf();
        panel_logo_clear_files();
        panel_appearance_save(['logo_file' => '', 'logo_updated' => time()]);
        $this->ok($this->payload());
    }

    /** @return array{wallpaper: string, kind: string, logo: string, logo_custom: string} */
    private function payload(): array
    {
        $a = panel_appearance();
        return [
            'wallpaper' => panel_appearance_src(),
            'kind' => (string) ($a['kind'] ?? ''),
            'logo' => panel_logo_src(),
            'logo_custom' => panel_logo_custom_src(),
        ];
    }

    private function saveUpload(): never
    {
        $f = $_FILES['file'];
        $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $this->fail('上传失败（错误码 ' . $err . '）');
        }
        if ((int) ($f['size'] ?? 0) > self::MAX_BYTES) {
            $this->fail('壁纸不能超过 8MB');
        }

        $tmp = (string) ($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $this->fail('保存临时文件失败');
        }

        $mime = '';
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $fi->file($tmp);
        } elseif (function_exists('mime_content_type')) {
            $mime = (string) mime_content_type($tmp);
        }
        $ext = self::MIME_EXT[$mime] ?? '';
        if ($ext === '') {
            $this->fail('仅支持 jpg / png / webp 图片');
        }

        $dir = panel_uploads_dir();
        if (!is_dir($dir) || !is_writable($dir)) {
            $this->fail('无法写入 /static/uploads，请检查目录权限');
        }

        panel_appearance_clear_files();
        $name = 'wallpaper.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            $this->fail('保存壁纸失败');
        }
        @chmod($dest, 0644);

        panel_appearance_save([
            'kind' => 'file',
            'file' => $name,
            'url' => '',
            'updated' => time(),
        ]);
        $this->ok($this->payload());
    }

    private function saveUrl(string $url): never
    {
        $url = trim($url);
        if (strlen($url) > 2048 || !preg_match('#^https?://#i', $url)) {
            $this->fail('请填写以 http:// 或 https:// 开头的图片地址');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->fail('图片 URL 无效');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || !empty($parts['user']) || !empty($parts['pass'])) {
            $this->fail('图片 URL 无效');
        }

        panel_appearance_clear_files();
        panel_appearance_save([
            'kind' => 'url',
            'file' => '',
            'url' => $url,
            'updated' => time(),
        ]);
        $this->ok($this->payload());
    }

    private function saveLogoUpload(): never
    {
        $f = $_FILES['file'];
        $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $this->fail('上传失败（错误码 ' . $err . '）');
        }
        if ((int) ($f['size'] ?? 0) > self::MAX_LOGO_BYTES) {
            $this->fail('标志不能超过 2MB');
        }

        $tmp = (string) ($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $this->fail('保存临时文件失败');
        }

        $ext = $this->logoExt($tmp);
        if ($ext === '') {
            $this->fail('仅支持 png / jpg / webp / svg 标志');
        }

        $dir = panel_uploads_dir();
        if (!is_dir($dir) || !is_writable($dir)) {
            $this->fail('无法写入 /static/uploads，请检查目录权限');
        }

        panel_logo_clear_files();
        $name = 'logo.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            $this->fail('保存标志失败');
        }
        @chmod($dest, 0644);

        panel_appearance_save([
            'logo_file' => $name,
            'logo_updated' => time(),
        ]);
        $this->ok($this->payload());
    }

    private function logoExt(string $tmp): string
    {
        $mime = '';
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $fi->file($tmp);
        } elseif (function_exists('mime_content_type')) {
            $mime = (string) mime_content_type($tmp);
        }
        if (isset(self::LOGO_MIME_EXT[$mime]) && self::LOGO_MIME_EXT[$mime] !== 'svg') {
            return self::LOGO_MIME_EXT[$mime];
        }

        $head = (string) @file_get_contents($tmp, false, null, 0, 4096);
        if ($head !== '' && preg_match('/<svg\b/i', $head) && !preg_match('/<script\b|javascript:/i', $head)) {
            return 'svg';
        }
        return '';
    }
}
