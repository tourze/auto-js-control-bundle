# Auto.js 设备控制 API 文档

[English](API.md) | [中文](API.zh-CN.md)

## 概述

本文档描述了 Auto.js 设备控制系统的 HTTP 轮询 API 接口和 Redis 消息队列设计。

## 目录

1. [API 基础](#api-基础)
2. [认证机制](#认证机制)
3. [API 接口](#api-接口)
   - [设备注册](#1-设备注册)
   - [设备心跳](#2-设备心跳长轮询)
   - [上报执行结果](#3-上报执行结果)
   - [批量上传日志](#4-批量上传日志)
   - [获取脚本内容](#5-获取脚本内容)
4. [Redis 队列设计](#redis-队列设计)
5. [错误处理](#错误处理)
6. [速率限制](#速率限制)
7. [最佳实践](#最佳实践)

## API 基础

- **基础 URL**: `https://your-server.com/api/autojs/v1`
- **协议**: 仅支持 HTTPS
- **认证方式**: 基于证书的 HMAC-SHA256 签名
- **内容类型**: `application/json`
- **编码**: UTF-8

### 请求头

所有请求应包含：

```http
Content-Type: application/json
User-Agent: AutoJS-Client/1.0
Accept: application/json
Accept-Encoding: gzip, deflate
```

### 响应格式

所有响应遵循以下格式：

```json
{
    "status": "ok|error",
    "message": "人类可读的消息",
    "data": { },
    "serverTime": "2024-01-01T12:00:00+08:00"
}
```

## 认证机制

所有 API 请求都需要签名验证：

1. **算法**: HMAC-SHA256
2. **密钥**: 设备证书（注册时获得）
3. **时间戳验证**: 请求时间戳必须在服务器时间 ±5 分钟内

### 签名生成

```javascript
// JavaScript 示例
function generateSignature(data, certificate) {
    return CryptoJS.HmacSHA256(data, certificate).toString();
}

// PHP 示例
function generateSignature($data, $certificate) {
    return hash_hmac('sha256', $data, $certificate);
}
```

## API 接口

### 1. 设备注册

**端点**: `POST /api/autojs/v1/device/register`

**描述**: 注册新设备并获取认证证书。

**请求体**:
```json
{
    "deviceCode": "DEVICE-001",
    "deviceName": "测试设备",
    "certificateRequest": "证书请求字符串",
    "model": "Xiaomi 12",
    "brand": "Xiaomi",
    "osVersion": "Android 13",
    "autoJsVersion": "4.1.1",
    "fingerprint": "设备指纹",
    "hardwareInfo": {
        "cpuCores": 8,
        "memorySize": 8192,
        "storageSize": 128000,
        "screenResolution": "1080x2400"
    }
}
```

**响应**:
```json
{
    "status": "ok",
    "deviceId": "123456",
    "certificate": "生成的设备证书",
    "message": "设备注册成功",
    "serverTime": "2024-01-01T12:00:00+08:00",
    "config": {
        "heartbeatInterval": 30,
        "logUploadInterval": 300
    }
}
```

### 2. 设备心跳（长轮询）

**端点**: `POST /api/autojs/v1/device/heartbeat`

**描述**: 发送设备状态并使用长轮询接收待执行指令。

**请求体**:
```json
{
    "deviceCode": "DEVICE-001",
    "signature": "签名字符串",
    "timestamp": 1704067200,
    "autoJsVersion": "4.1.1",
    "pollTimeout": 30,
    "deviceInfo": {
        "batteryLevel": 85,
        "networkType": "wifi"
    },
    "monitorData": {
        "cpuUsage": 45.5,
        "memoryUsage": 2048,
        "availableStorage": 64000
    }
}
```

**签名数据格式**: `deviceCode:timestamp:certificate`

**响应**:
```json
{
    "status": "ok",
    "serverTime": "2024-01-01T12:00:00+08:00",
    "instructionCount": 2,
    "instructions": [
        {
            "instructionId": "INS-001",
            "type": "execute_script",
            "data": {
                "scriptId": 123,
                "parameters": {"key": "value"}
            },
            "createTime": "2024-01-01T11:59:00+08:00",
            "timeout": 300,
            "priority": 5,
            "taskId": 456,
            "scriptId": 123,
            "correlationId": "CORR-001"
        }
    ],
    "config": {
        "heartbeatInterval": 30
    }
}
```

### 3. 上报执行结果

**端点**: `POST /api/autojs/v1/device/report-result`

**描述**: 上报指令执行结果。

**请求体**:
```json
{
    "deviceCode": "DEVICE-001",
    "signature": "签名字符串",
    "timestamp": 1704067200,
    "instructionId": "INS-001",
    "status": "SUCCESS",
    "startTime": "2024-01-01T12:00:00+08:00",
    "endTime": "2024-01-01T12:05:00+08:00",
    "output": "执行输出内容",
    "errorMessage": null,
    "executionMetrics": {
        "memoryUsed": 512,
        "cpuTime": 45.2
    },
    "screenshots": ["base64_encoded_image1", "base64_encoded_image2"]
}
```

**签名数据格式**: `deviceCode:instructionId:timestamp:certificate`

**响应**:
```json
{
    "status": "ok",
    "message": "结果上报成功",
    "serverTime": "2024-01-01T12:00:00+08:00"
}
```

### 4. 批量上传日志

**端点**: `POST /api/autojs/v1/device/logs`

**描述**: 批量上传设备日志。

**请求体**:
```json
{
    "deviceCode": "DEVICE-001",
    "signature": "签名字符串",
    "timestamp": 1704067200,
    "logs": [
        {
            "level": "INFO",
            "type": "SYSTEM",
            "message": "脚本执行开始",
            "logTime": "2024-01-01T12:00:00+08:00",
            "context": "Script.execute",
            "stackTrace": null
        }
    ]
}
```

**签名数据格式**: `deviceCode:timestamp:logCount:certificate`

**响应**:
```json
{
    "status": "ok",
    "message": "日志上传成功",
    "processedCount": 5,
    "serverTime": "2024-01-01T12:00:00+08:00"
}
```

### 5. 获取脚本内容

**端点**: `GET /api/autojs/v1/script/{scriptId}`

**描述**: 下载要执行的脚本内容。

**请求头**:
- `X-Device-Code`: 设备代码
- `X-Signature`: 请求签名
- `X-Timestamp`: Unix 时间戳

**签名数据格式**: `deviceCode:scriptId:timestamp:certificate`

**响应**:
```json
{
    "status": "ok",
    "scriptId": 123,
    "scriptCode": "SCRIPT-001",
    "scriptName": "测试脚本",
    "scriptType": "javascript",
    "content": "脚本内容",
    "contentSize": 1024,
    "version": "1.0.0",
    "parameters": {
        "param1": {"type": "string", "required": true}
    },
    "timeout": 3600,
    "checksum": "sha256校验和",
    "serverTime": "2024-01-01T12:00:00+08:00"
}
```

## Redis 队列设计

### 键命名规范

1. **设备指令队列**: `device_instruction_queue:{deviceCode}`
   - 类型: List
   - 存储: JSON 序列化的 DeviceInstruction 对象
   - 操作: LPUSH（入队）, BRPOP（出队）

2. **设备轮询通知**: `device_poll_notify:{deviceCode}`
   - 类型: Pub/Sub Channel
   - 用途: 通知长轮询立即返回

3. **设备在线状态**: `device_online:{deviceCode}`
   - 类型: String
   - TTL: 120 秒
   - 存储: 最后心跳时间戳

4. **指令执行状态**: `instruction_status:{instructionId}`
   - 类型: Hash
   - TTL: 3600 秒
   - 字段: status, startTime, endTime, retryCount

5. **设备锁**: `device_lock:{deviceCode}`
   - 类型: String
   - TTL: 30 秒
   - 用途: 防止并发操作

### 长轮询实现机制

1. 设备发起心跳请求，设置 `pollTimeout` 参数（最大 60 秒）
2. 服务端检查设备指令队列：
   - 如有待执行指令，立即返回
   - 如无指令，订阅 Redis Pub/Sub 通道，等待新指令
3. 新指令到达时：
   - 将指令加入设备队列
   - 发布通知到设备的 Pub/Sub 通道
4. 长轮询立即返回新指令或在超时后返回空响应

### 指令优先级处理

- 使用 Redis Sorted Set 存储不同优先级的指令
- 优先级范围: 0-10（数值越大优先级越高）
- 获取指令时按优先级排序

### 队列键汇总

| 键模式 | 类型 | 描述 | TTL |
|--------|------|------|-----|
| `device_instruction_queue:{deviceCode}` | List | 设备指令队列 | 无 |
| `device_poll_notify:{deviceCode}` | Pub/Sub | 轮询通知通道 | 无 |
| `device_online:{deviceCode}` | String | 在线状态标识 | 120秒 |
| `instruction_status:{instructionId}` | Hash | 执行状态跟踪 | 3600秒 |
| `device_lock:{deviceCode}` | String | 并发控制 | 30秒 |
| `instruction_priority:{deviceCode}` | Sorted Set | 优先级队列 | 无 |

## 错误处理

### 状态码

| HTTP 状态 | 状态字段 | 描述 | 示例场景 |
|-----------|----------|------|----------|
| 200 | `ok` | 成功 | 正常操作 |
| 400 | `invalid` | 无效请求 | 缺少必需字段 |
| 401 | `unauthorized` | 认证失败 | 无效签名 |
| 403 | `forbidden` | 访问被拒绝 | 设备未批准 |
| 404 | `not_found` | 资源不存在 | 脚本不存在 |
| 409 | `duplicate` | 重复请求 | 设备已注册 |
| 429 | `rate_limited` | 请求过多 | 超过速率限制 |
| 500 | `error` | 服务器错误 | 内部错误 |
| 503 | `unavailable` | 服务不可用 | 维护模式 |

### 错误响应格式

```json
{
    "status": "error",
    "message": "人类可读的错误消息",
    "error": {
        "code": "ERROR_CODE",
        "details": {
            "field": "额外的错误上下文"
        }
    },
    "serverTime": "2024-01-01T12:00:00+08:00"
}
```

### 常见错误代码

- `INVALID_SIGNATURE` - 签名验证失败
- `EXPIRED_TIMESTAMP` - 请求时间戳过期
- `DEVICE_NOT_FOUND` - 设备未注册
- `CERTIFICATE_EXPIRED` - 设备证书过期
- `QUOTA_EXCEEDED` - API 配额超限
- `INVALID_PARAMETER` - 无效参数值

## 速率限制

### 默认限制

| 端点 | 速率限制 | 突发 | 窗口 |
|------|----------|------|------|
| 设备注册 | 5 请求 | 2 | 1 小时 |
| 设备心跳 | 120 请求 | 10 | 1 小时 |
| 结果上报 | 60 请求 | 10 | 1 分钟 |
| 日志上传 | 10 请求 | 5 | 1 分钟 |
| 脚本下载 | 30 请求 | 5 | 1 分钟 |

### 速率限制响应头

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1704067200
Retry-After: 60
```

## 最佳实践

### 客户端实现

1. **重试逻辑**
   - 为失败请求实现指数退避
   - 最多重试 3 次瞬时错误
   - 不要重试 4xx 错误（429 除外）

2. **连接管理**
   - 重用 HTTP 连接（keep-alive）
   - 实现连接池
   - 优雅处理网络变化

3. **安全性**
   - 安全存储证书
   - 验证服务器证书
   - 不记录敏感数据

4. **性能**
   - 压缩请求/响应体
   - 尽可能批量操作
   - 本地缓存脚本内容

### 服务器集成

1. **Webhook 支持**
   ```json
   {
       "event": "device.registered",
       "data": {
           "deviceId": "123456",
           "deviceCode": "DEVICE-001",
           "timestamp": "2024-01-01T12:00:00+08:00"
       }
   }
   ```

2. **批量操作**
   - 使用 `/api/autojs/v1/devices/bulk` 端点
   - 每次请求最多 100 个设备
   - 大批量异步处理

3. **监控**
   - 跟踪 API 使用指标
   - 监控错误率
   - 设置异常告警

### 安全注意事项

1. **防重放保护**
   - 时间戳验证（±5 分钟）
   - 关键操作的请求随机数
   - 重试的幂等键

2. **设备认证**
   - 基于证书的签名
   - 定期证书轮换
   - 设备审批工作流

3. **传输安全**
   - 仅限 HTTPS（TLS 1.2+）
   - 推荐证书固定
   - 完美前向保密

4. **访问控制**
   - 设备隔离
   - 基于资源的权限
   - API 密钥范围限定

5. **速率限制**
   - 设备级别限制
   - 渐进式响应（阻止前警告）
   - 可信设备白名单

## API 版本控制

API 使用 URL 版本控制：
- 当前版本: `v1`
- URL 中的版本: `/api/autojs/v1/...`
- 弃用通知: 6 个月
- 停止服务: 12 个月

### 版本兼容性

| API 版本 | 最低客户端版本 | 最高客户端版本 | 状态 |
|----------|---------------|---------------|------|
| v1 | 1.0.0 | 当前 | 活跃 |

## 其他资源

- [客户端 SDK 文档](https://github.com/tourze/autojs-sdk)
- [API 测试平台](https://api.example.com/playground)
- [状态页面](https://status.example.com)
- [技术支持](mailto:api-support@example.com)