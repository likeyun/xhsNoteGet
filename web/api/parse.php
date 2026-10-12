<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['code' => 405, 'message' => '请使用 POST 请求']);
}
$body = file_get_contents('php://input', false, null, 0, 16385);
if ($body === false || strlen($body) > 16384) respond(413, ['code' => 413, 'message' => '请求数据过大']);
$payload = json_decode($body, true);
if (!is_array($payload)) respond(400, ['code' => 400, 'message' => 'JSON 格式不正确']);
$key = getenv('API_KEY') ?: '';
if ($key !== '' && !hash_equals($key, (string)($_SERVER['HTTP_X_API_KEY'] ?? ''))) {
    respond(401, ['code' => 401, 'message' => 'API Key 不正确']);
}
$raw = trim((string)($payload['url'] ?? ''));
if ($raw === '' || strlen($raw) > 8192) respond(400, ['code' => 400, 'message' => '请输入有效的作品链接']);
if (!preg_match('~https?://[^\s<>"\x{3000}]+~u', $raw, $match)) {
    respond(400, ['code' => 400, 'message' => '未发现链接']);
}
$url = rtrim($match[0], "，。,.;!?)]}");
$parts = parse_url($url);
$host = strtolower($parts['host'] ?? '');
$allowed = ['xhslink.com', 'xhslink.cn', 'xiaohongshu.com'];
$valid = false;
foreach ($allowed as $domain) {
    if ($host === $domain || str_ends_with($host, '.'.$domain)) { $valid = true; break; }
}
if (!$valid || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
    || isset($parts['user']) || isset($parts['pass'])
    || (isset($parts['port']) && !in_array($parts['port'], [80, 443], true))) {
    respond(400, ['code' => 400, 'message' => '不支持此链接域名']);
}
$api = getenv('XHS_API_URL') ?: 'http://xhs:5556/xhs/detail';
$request = ['url' => $url, 'download' => false];
$cookie = getenv('XHS_COOKIE') ?: '';
if ($cookie !== '') $request['cookie'] = $cookie;
$ch = curl_init($api);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_MAXREDIRS => 0
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);
if ($response === false) respond(502, ['code' => 502, 'message' => '解析引擎连接失败：'.$error]);
$result = json_decode($response, true);
if (!is_array($result)) respond(502, ['code' => 502, 'message' => '解析引擎返回异常']);
if ($status < 200 || $status >= 300) {
    respond(502, ['code' => 502, 'message' => '解析引擎返回 HTTP '.$status, 'detail' => $result]);
}
if (empty($result['data']) || !is_array($result['data'])) {
    respond(422, ['code' => 422, 'message' => $result['message'] ?? '未解析到作品数据', 'detail' => $result]);
}
$data = $result['data'];
respond(200, [
    'code' => 0,
    'message' => $result['message'] ?? '解析成功',
    'data' => [
        'id' => $data['作品ID'] ?? '',
        'title' => $data['作品标题'] ?? '',
        'description' => $data['作品描述'] ?? '',
        'type' => $data['作品类型'] ?? '',
        'author' => $data['作者昵称'] ?? '',
        'author_url' => $data['作者链接'] ?? '',
        'published_at' => $data['发布时间'] ?? '',
        'likes' => $data['点赞数量'] ?? '0',
        'favorites' => $data['收藏数量'] ?? '0',
        'comments' => $data['评论数量'] ?? '0',
        'shares' => $data['分享数量'] ?? '0',
        'images' => is_array($data['下载地址'] ?? null) ? $data['下载地址'] : [],
        'live_videos' => is_array($data['动图地址'] ?? null) ? $data['动图地址'] : [],
        'cover' => $data['封面地址'] ?? null,
        'original_url' => $data['作品链接'] ?? $url
    ]
]);
