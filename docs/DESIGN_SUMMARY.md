# Auto.js 控制系统 HTTP 轮询 API 设计总结

## 设计概述

本设计实现了一个完整的 Auto.js 设备控制系统的 HTTP 轮询 API 和 Redis 消息队列架构。系统采用长轮询机制，支持设备注册、心跳保持、指令下发、执行结果上报等功能。

## 核心特性

### 1. 安全机制
- **HMAC-SHA256 签名验证**：每个请求都需要使用设备证书进行签名
- **时间戳防重放**：请求时间戳必须在服务器时间前后 5 分钟内
- **设备证书管理**：设备注册时颁发唯一证书，用于后续认证

### 2. 长轮询实现
- **最大等待时间**：支持 1-60 秒的轮询超时配置
- **即时推送**：新指令到达时通过 Redis Pub/Sub 立即通知
- **队列优先级**：支持 0-10 级优先级，高优先级指令优先执行

### 3. Redis 队列设计
- **设备指令队列**：`device_instruction_queue:{deviceCode}`
- **轮询通知通道**：`device_poll_notify:{deviceCode}` 
- **在线状态跟踪**：`device_online:{deviceCode}`
- **分布式锁**：`device_lock:{deviceCode}` 防止并发操作

## 文件结构

```
packages/auto-js-control-bundle/
├── src/
│   ├── Dto/
│   │   ├── Request/
│   │   │   ├── DeviceHeartbeatRequest.php      # 心跳请求
│   │   │   ├── DeviceRegisterRequest.php       # 设备注册
│   │   │   ├── ReportExecutionResultRequest.php # 结果上报
│   │   │   └── DeviceLogRequest.php            # 日志上报
│   │   └── Response/
│   │       ├── DeviceHeartbeatResponse.php     # 心跳响应
│   │       ├── DeviceRegisterResponse.php      # 注册响应
│   │       ├── ExecutionResultResponse.php     # 结果确认
│   │       ├── DeviceLogResponse.php           # 日志确认
│   │       └── ScriptDownloadResponse.php      # 脚本下载
│   ├── ValueObject/
│   │   ├── DeviceInstruction.php               # 设备指令
│   │   ├── RedisQueueKeys.php                  # Redis键定义
│   │   ├── DeviceConnectionInfo.php            # 连接信息
│   │   └── InstructionExecutionContext.php     # 执行上下文
│   └── Resources/
│       └── config/
│           └── routes.yaml                      # 路由配置
└── docs/
    ├── API.md                                   # API文档
    ├── IMPLEMENTATION_GUIDE.md                  # 实现指南
    └── DESIGN_SUMMARY.md                        # 设计总结
```

## API 端点

| 方法 | 路径 | 描述 |
|------|------|------|
| POST | `/api/autojs/v1/device/register` | 设备注册 |
| POST | `/api/autojs/v1/device/heartbeat` | 设备心跳（长轮询） |
| POST | `/api/autojs/v1/device/report-result` | 上报执行结果 |
| POST | `/api/autojs/v1/device/logs` | 批量上报日志 |
| GET | `/api/autojs/v1/script/{scriptId}` | 获取脚本内容 |
| POST | `/api/autojs/v1/device/screenshot` | 上传截图 |

## 指令类型

系统支持以下指令类型：
- `execute_script` - 执行脚本
- `stop_script` - 停止脚本
- `update_status` - 更新状态
- `collect_log` - 收集日志
- `restart_app` - 重启应用
- `update_app` - 更新应用
- `ping` - 心跳检测

## 下一步实现

1. **控制器实现**：创建 `DeviceApiController` 实现所有 API 端点
2. **服务层开发**：
   - `DeviceAuthenticationService` - 设备认证服务
   - `InstructionQueueService` - 指令队列服务
   - `DeviceLongPollingService` - 长轮询服务
3. **Redis 集成**：配置 Redis 连接和实现队列操作
4. **事件系统**：创建设备注册、指令接收等事件
5. **测试编写**：为所有组件编写单元测试和集成测试

## 性能考虑

- 使用 Redis 连接池减少连接开销
- 批量处理日志上报减少请求次数
- 缓存脚本内容避免重复查询
- 合理设置 TTL 避免 Redis 内存溢出

## 安全建议

- 定期轮换设备证书
- 实施请求速率限制
- 记录所有认证失败日志
- 使用 HTTPS 强制加密传输