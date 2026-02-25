# S3媒体上传插件

适用于 WordPress 的 S3 插件，通过 S3 协议将上传的媒体文件保存到云端存储中。支持自动转换 WebP 格式及缩略图同步。

##  功能特性

- **多种存储模式**：支持本地模式、云端模式和同步模式
- **自动 WebP 转换**：可选将上传的图片自动转换为 WebP 格式
- **智能连接检测**：实时检测 S3 连接状态并在后台显示
- **完整的媒体管理**：在媒体库中直观显示文件存储位置

##  系统要求

- WordPress 5.0 或更高版本
- PHP 7.4 或更高版本
- 一个可访问的 S3 存储服务

##  安装方法

### 方法一：手动安装

1. 下载插件文件到本地
2. 将整个插件文件夹上传到 `/wp-content/plugins/` 目录
3. 在 WordPress 后台插件页面激活插件

### 方法二：Git 克隆

```bash
cd /path/to/your/wordpress/wp-content/plugins/
git clone https://github.com/ayfl269/s3-plugin.git s3
```

然后在 WordPress 后台激活插件。

##  配置说明

激活插件后，在 WordPress 后台左侧菜单找到 **"S3"** 选项进行配置。

### S3 配置参数

| 参数 | 说明 | 示例 |
|------|------|------|
| **存储模式** | 选择文件存储策略 | 本地模式 / 云端模式 / 同步模式 |
| **Access Key** | S3 访问密钥 | YOUR_ACCESS_KEY |
| **Secret Key** | S3 私密密钥 | YOUR_SECRET_KEY |
| **Region** | S3 区域 | us-east-1 |
| **Bucket** | 存储桶名称 | your-bucket-name |
| **API端点URL** | S3 API 端点 | https://s3.amazonaws.com |
| **公共访问URL** | 文件访问地址 | https://your-bucket.s3.amazonaws.com/ |
| **目标文件夹** | 存储路径 | /{year}/{month}(支持日期变量) |

### 存储模式详解

- **本地模式**：文件保存在服务器本地
- **云端模式**：文件保存在 S3 云端（本地不留存）
- **同步模式**：文件同时保存在本地和 S3 云端

### 其它设置

- **转换为WebP格式**：启用后会自动将上传的 JPG/PNG 图片转换为 WebP 格式


##  技术架构

### 核心组件

```
s3/
├── S3MediaUploader.php          # 插件主文件
├── includes/
│   ├── class-s3-service.php     # S3 服务核心类
│   ├── class-admin-settings.php # 后台设置界面
│   └── class-media-handler.php  # 媒体处理钩子
├── admin.js                     # 后台 JavaScript
├── admin.css                    # 后台样式
└── vendor/                      # AWS SDK for PHP 依赖
```

### 主要类说明

- **S3_Service**：负责与 S3 服务的通信，包括上传、删除等操作
- **Admin_Settings**：处理后台设置页面和用户界面
- **Media_Handler**：拦截 WordPress 媒体上传流程并进行相应处理

##  支持的 S3 服务

本插件兼容以下 S3 服务：

- 目前仅测试过rustfs

如遇到问题或有功能建议，请提交 Issue

##  许可证

本插件遵循 GNU General Public License v3.0 许可证发布。