# xhsNoteGet

开源小红书笔记解析网站：支持图文、视频、Live Photo 的媒体地址提取、作品信息展示、复制地址和下载操作。

- **前端**：原生 HTML / CSS / JavaScript，无需 Node.js 构建
- **API**：PHP 8.4，统一返回 JSON
- **解析引擎**：[JoeanAmier/XHS-Downloader](https://github.com/JoeanAmier/XHS-Downloader)（独立 Docker 服务）
- **部署**：Docker Compose + 一键安装脚本；可配合宝塔 Nginx 反代
- **持久化目录**：`./data/Volume`，已被 Git 忽略

> 本仓库不是 XHS-Downloader 的源码镜像，而是一个独立的 Web/API 封装项目。上游解析器遵守其自己的 GPL-3.0 许可证；本仓库的前端、PHP 和部署代码使用 MIT 许可证。

## 1. 一键安装

服务器需要 Docker Engine 和 Docker Compose v2（`docker compose`），适用于已安装 Docker 的宝塔 Linux 服务器。

```bash
git clone https://github.com/likeyun/xhsNoteGet.git /www/wwwroot/xhsNoteGet
cd /www/wwwroot/xhsNoteGet
bash install.sh
```

安装脚本会自动拉取 XHS-Downloader 镜像、构建 PHP 网站容器、启动服务并启用容器的自动重启。

默认 **只允许服务器本机访问**：`http://127.0.0.1:8080`。如果服务器的 8080 端口已占用，编辑 `.env` 的 `WEB_PORT` 后执行 `docker compose up -d`。

> 不要直接将 `WEB_BIND` 改成 `0.0.0.0` 后无保护地开放公网 API。建议使用 HTTPS 反向代理并配置访问限制/限流。

## 2. 宝塔反向代理

1. 把你的域名解析到服务器。
2. 宝塔 → 网站 → 创建网站，签发 SSL 证书。
3. 网站设置 → 反向代理 → 添加：目标地址 `http://127.0.0.1:8080`；代理目录 `/`；关闭代理缓存。
4. 打开 `https://你的域名/`，即可使用前端。API 地址为 `https://你的域名/api/parse.php`。

网站目录与 Docker 挂载的 `/www/wwwroot/xhsNoteGet` 只是项目源代码所在位置，不需要让宝塔 PHP 直接运行其中的 PHP 文件。**网站全部请求经 Nginx 代理到 Docker 8080 端口。**

## 3. API 文档

`POST /api/parse.php`，请求内容类型 `application/json`：

```bash
curl -X POST http://127.0.0.1:8080/api/parse.php \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://xhslink.cn/o/NrkR89uxDt"}'
```

响应示意（演示结构，并非真实解析结果）：

```json
{
  "code": 0,
  "message": "获取小红书作品数据成功",
  "data": {
    "id": "作品ID",
    "title": "作品标题",
    "description": "作品描述",
    "type": "图文",
    "author": "作者昵称",
    "author_url": "https://www.xiaohongshu.com/user/profile/...",
    "published_at": "2026-09-12_10:03:01",
    "likes": "479",
    "favorites": "96",
    "comments": "53",
    "shares": "15",
    "images": ["https://ci.xiaohongshu.com/..."],
    "live_videos": ["http://sns-bak-v1.xhscdn.com/..."],
    "cover": null,
    "original_url": "https://www.xiaohongshu.com/discovery/item/..."
  }
}
```

出错时 HTTP 返回 400/401/405/413/422/502 等，并包含 `code`、`message` 字段。

可选 API Key：编辑 `.env` 中的 `API_KEY`，服务端需传 `X-API-Key: ...` 请求头。**设置 API_KEY 后，默认的公开浏览器页面不能自动携带密钥**，应在后端网关或反代层部署安全的用户鉴权方式，而不是把密钥硬编码进公开 JS。

`XHS_COOKIE` 也是可选参数，仅保存在部署服务器本地的 `.env`，不要提交到 GitHub。

## 4. 常用运维命令

```bash
cd /www/wwwroot/xhsNoteGet
docker compose ps
docker compose logs -f
docker compose restart
docker compose pull xhs && docker compose up -d --build
docker compose down
```

`docker compose down` 默认不会删除挂载目录 `data/Volume`，请勿随意删除这个目录。

## 5. 已知限制和安全建议

- 小红书链接解析依赖非官方上游项目，链接有效性、画质和成功率受小红书接口及风控限制，无法保证所有作品都能解析。
- Live Photo 图片和 MP4 分别输出；未生成苹果原生 Live Photo 格式。
- 直接在浏览器下载第三方 CDN 可能受到 CORS、Referer、防盗链、混合内容以及多文件下载权限限制；遇到下载失败将尝试打开媒体链接，不保证强制下载成功。
- 本项目没有提供服务端任意 URL 代理，避免 SSRF 风险；解析端点只接受小红书指定域名。
- 如开放公网请在宝塔/Nginx 层添加限流、WAF、HTTPS 与访问控制，避免匿名滥用。
- 尊重创作者权益和平台规则，只下载你有权使用的素材。

## License

本仓库 MIT，见 [LICENSE](LICENSE)。上游 [XHS-Downloader](https://github.com/JoeanAmier/XHS-Downloader) 受其 GPL-3.0 许可证约束。
