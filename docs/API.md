# Auto.js Control API Documentation

[English](API.md) | [中文](API.zh-CN.md)

## Overview

This document describes the HTTP polling API and Redis queue design for the Auto.js device control system.

## Table of Contents

1. [API Basics](#api-basics)
2. [Authentication](#authentication)
3. [API Endpoints](#api-endpoints)
   - [Device Registration](#1-device-registration)
   - [Device Heartbeat](#2-device-heartbeat-long-polling)
   - [Report Execution Result](#3-report-execution-result)
   - [Upload Logs](#4-batch-log-upload)
   - [Download Script](#5-get-script-content)
4. [Redis Queue Design](#redis-queue-design)
5. [Error Handling](#error-handling)
6. [Rate Limiting](#rate-limiting)
7. [Best Practices](#best-practices)

## API Basics

- **Base URL**: `https://your-server.com/api/autojs/v1`
- **Protocol**: HTTPS only
- **Authentication**: Certificate-based HMAC-SHA256 signatures
- **Content-Type**: `application/json`
- **Encoding**: UTF-8

### Request Headers

All requests should include:

```http
Content-Type: application/json
User-Agent: AutoJS-Client/1.0
Accept: application/json
Accept-Encoding: gzip, deflate
```

### Response Format

All responses follow this format:

```json
{
    "status": "ok|error",
    "message": "Human-readable message",
    "data": { },
    "serverTime": "2024-01-01T12:00:00+08:00"
}
```

## Authentication

All API requests require signature verification:

1. **Algorithm**: HMAC-SHA256
2. **Key**: Device certificate (obtained during registration)
3. **Timestamp validation**: Request timestamp must be within ±5 minutes of server time

### Signature Generation

```javascript
// JavaScript example
function generateSignature(data, certificate) {
    return CryptoJS.HmacSHA256(data, certificate).toString();
}

// PHP example
function generateSignature($data, $certificate) {
    return hash_hmac('sha256', $data, $certificate);
}
```

## API 接口列表

### 1. 设备注册

**端点**: `POST /api/autojs/v1/device/register`

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

**Response**:
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

### 4. 批量上报日志

**端点**: `POST /api/autojs/v1/device/logs`

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

### 5. 获取脚本内容

**端点**: `GET /api/autojs/v1/script/{scriptId}`

**请求参数**:
- `deviceCode`: 设备代码
- `signature`: 签名
- `timestamp`: 时间戳

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

## 错误码

- `ok`: 成功
- `error`: 一般错误
- `unauthorized`: 认证失败
- `not_found`: 资源不存在
- `forbidden`: 无权限
- `invalid`: 无效请求
- `duplicate`: 重复请求
- `partial`: 部分成功

## 安全考虑

1. **防重放攻击**: 时间戳验证 + 请求唯一标识
2. **设备认证**: 基于证书的签名验证
3. **传输安全**: 强制使用 HTTPS
4. **访问控制**: 设备只能访问自己的资源
5. **速率限制**: 防止恶意请求