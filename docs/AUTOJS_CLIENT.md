# Auto.js Client Implementation Guide

This guide provides complete implementation examples for Auto.js client devices to connect and communicate with the control server.

## Table of Contents

1. [Basic Client Implementation](#basic-client-implementation)
2. [Advanced Features](#advanced-features)
3. [Error Handling](#error-handling)
4. [Security Implementation](#security-implementation)
5. [Performance Optimization](#performance-optimization)
6. [Testing and Debugging](#testing-and-debugging)

## Basic Client Implementation

### Complete Client Example

```javascript
/**
 * Auto.js Cloud Control Client
 * Version: 1.0.0
 * Compatible with: Auto.js 4.1.1+, AutoX
 */

// Configuration
const CONFIG = {
    // Server settings
    serverUrl: "https://control.example.com",
    apiVersion: "v1",
    
    // Device identification
    deviceCode: device.getAndroidId(),
    deviceName: device.brand + " " + device.model,
    
    // Timing settings (milliseconds)
    heartbeatInterval: 30000,      // 30 seconds
    pollTimeout: 25000,            // 25 seconds (must be less than heartbeatInterval)
    retryDelay: 5000,              // 5 seconds
    maxRetries: 3,
    
    // Storage keys
    storageKeys: {
        certificate: "device_certificate",
        lastHeartbeat: "last_heartbeat",
        deviceId: "device_id",
        config: "server_config"
    }
};

// Global variables
let isRunning = true;
let certificate = null;
let deviceId = null;
let heartbeatThread = null;
let executionThreads = {};

/**
 * Main entry point
 */
function main() {
    console.log("Auto.js Cloud Control Client starting...");
    
    // Load stored credentials
    loadCredentials();
    
    // Register device if needed
    if (!certificate) {
        if (!registerDevice()) {
            console.error("Failed to register device");
            return;
        }
    }
    
    // Start heartbeat loop
    startHeartbeat();
    
    // Keep script running
    setInterval(() => {
        if (!isRunning) {
            console.log("Client stopping...");
            stopClient();
        }
    }, 1000);
}

/**
 * Load stored credentials
 */
function loadCredentials() {
    certificate = storage.get(CONFIG.storageKeys.certificate);
    deviceId = storage.get(CONFIG.storageKeys.deviceId);
    
    if (certificate) {
        console.log("Loaded stored certificate");
    }
}

/**
 * Register device with server
 */
function registerDevice() {
    console.log("Registering device...");
    
    const deviceInfo = {
        deviceCode: CONFIG.deviceCode,
        deviceName: CONFIG.deviceName,
        model: device.model,
        brand: device.brand,
        osVersion: device.release,
        sdkInt: device.sdkInt,
        autoJsVersion: getAutoJsVersion(),
        fingerprint: device.fingerprint,
        hardwareInfo: {
            cpuCores: device.cpuCores || 4,
            memorySize: device.totalMem,
            storageSize: device.totalStorage,
            screenResolution: device.width + "x" + device.height,
            screenDensity: device.densityDpi
        }
    };
    
    try {
        const response = http.postJson(
            CONFIG.serverUrl + "/api/autojs/" + CONFIG.apiVersion + "/device/register",
            deviceInfo,
            {
                headers: {
                    "Content-Type": "application/json",
                    "User-Agent": "AutoJS-Client/1.0"
                }
            }
        );
        
        if (response.statusCode === 200) {
            const data = response.body.json();
            certificate = data.certificate;
            deviceId = data.deviceId;
            
            // Store credentials
            storage.put(CONFIG.storageKeys.certificate, certificate);
            storage.put(CONFIG.storageKeys.deviceId, deviceId);
            
            // Store server config
            if (data.config) {
                storage.put(CONFIG.storageKeys.config, JSON.stringify(data.config));
                applyServerConfig(data.config);
            }
            
            console.log("Device registered successfully. ID: " + deviceId);
            toast("设备注册成功");
            return true;
        } else {
            console.error("Registration failed: " + response.statusCode);
            console.error(response.body.string());
            return false;
        }
    } catch (e) {
        console.error("Registration error: " + e);
        return false;
    }
}

/**
 * Start heartbeat loop
 */
function startHeartbeat() {
    heartbeatThread = threads.start(function() {
        while (isRunning) {
            try {
                heartbeat();
            } catch (e) {
                console.error("Heartbeat error: " + e);
            }
            
            sleep(CONFIG.heartbeatInterval);
        }
    });
}

/**
 * Send heartbeat and receive instructions
 */
function heartbeat() {
    const timestamp = Date.now();
    const signatureData = CONFIG.deviceCode + ":" + timestamp + ":" + certificate;
    const signature = generateHMAC(signatureData, certificate);
    
    const heartbeatData = {
        deviceCode: CONFIG.deviceCode,
        signature: signature,
        timestamp: Math.floor(timestamp / 1000),
        autoJsVersion: getAutoJsVersion(),
        pollTimeout: Math.floor(CONFIG.pollTimeout / 1000),
        deviceInfo: {
            batteryLevel: device.getBattery(),
            isCharging: device.isCharging(),
            networkType: getNetworkType(),
            wifiSignalLevel: getWifiSignalLevel()
        },
        monitorData: {
            cpuUsage: getCpuUsage(),
            memoryUsage: getMemoryUsage(),
            availableStorage: device.getAvailableStorage(),
            runningScripts: Object.keys(executionThreads).length
        }
    };
    
    try {
        const response = http.postJson(
            CONFIG.serverUrl + "/api/autojs/" + CONFIG.apiVersion + "/device/heartbeat",
            heartbeatData,
            {
                headers: {
                    "Content-Type": "application/json",
                    "User-Agent": "AutoJS-Client/1.0"
                },
                timeout: CONFIG.pollTimeout + 5000  // Add buffer to timeout
            }
        );
        
        if (response.statusCode === 200) {
            const data = response.body.json();
            storage.put(CONFIG.storageKeys.lastHeartbeat, timestamp);
            
            // Process instructions
            if (data.instructions && data.instructions.length > 0) {
                console.log("Received " + data.instructions.length + " instructions");
                data.instructions.forEach(instruction => {
                    processInstruction(instruction);
                });
            }
            
            // Update config if provided
            if (data.config) {
                applyServerConfig(data.config);
            }
        } else if (response.statusCode === 401) {
            console.error("Authentication failed, re-registering...");
            certificate = null;
            storage.remove(CONFIG.storageKeys.certificate);
            registerDevice();
        } else {
            console.error("Heartbeat failed: " + response.statusCode);
        }
    } catch (e) {
        console.error("Heartbeat request error: " + e);
    }
}

/**
 * Process received instruction
 */
function processInstruction(instruction) {
    console.log("Processing instruction: " + instruction.type + " (ID: " + instruction.instructionId + ")");
    
    // Check if already executing
    if (executionThreads[instruction.instructionId]) {
        console.log("Instruction already executing");
        return;
    }
    
    // Start execution in separate thread
    executionThreads[instruction.instructionId] = threads.start(function() {
        const startTime = new Date().toISOString();
        let status = "FAILED";
        let output = "";
        let errorMessage = null;
        let screenshots = [];
        
        try {
            switch (instruction.type) {
                case "execute_script":
                    const result = executeScript(instruction);
                    status = result.success ? "SUCCESS" : "FAILED";
                    output = result.output;
                    errorMessage = result.error;
                    screenshots = result.screenshots;
                    break;
                    
                case "update_config":
                    updateConfig(instruction.data.config);
                    status = "SUCCESS";
                    output = "Configuration updated";
                    break;
                    
                case "take_screenshot":
                    const screenshot = captureScreenshot();
                    screenshots = [screenshot];
                    status = "SUCCESS";
                    output = "Screenshot captured";
                    break;
                    
                case "get_device_info":
                    output = JSON.stringify(getDetailedDeviceInfo());
                    status = "SUCCESS";
                    break;
                    
                case "restart_client":
                    status = "SUCCESS";
                    output = "Client will restart";
                    setTimeout(() => {
                        engines.execScriptFile(engines.myEngine().source);
                    }, 2000);
                    break;
                    
                default:
                    errorMessage = "Unknown instruction type: " + instruction.type;
            }
        } catch (e) {
            errorMessage = e.toString();
            console.error("Instruction execution error: " + e);
        }
        
        const endTime = new Date().toISOString();
        
        // Report result
        reportExecutionResult({
            instructionId: instruction.instructionId,
            status: status,
            startTime: startTime,
            endTime: endTime,
            output: output,
            errorMessage: errorMessage,
            screenshots: screenshots,
            executionMetrics: {
                memoryUsed: getMemoryUsage(),
                cpuTime: Date.parse(endTime) - Date.parse(startTime)
            }
        });
        
        // Clean up
        delete executionThreads[instruction.instructionId];
    });
}

/**
 * Execute script instruction
 */
function executeScript(instruction) {
    const result = {
        success: false,
        output: "",
        error: null,
        screenshots: []
    };
    
    try {
        // Get script content
        const script = downloadScript(instruction.scriptId);
        if (!script) {
            result.error = "Failed to download script";
            return result;
        }
        
        // Prepare parameters
        const params = instruction.data.parameters || {};
        
        // Take before screenshot if requested
        if (params.screenshotBefore) {
            result.screenshots.push(captureScreenshot());
        }
        
        // Execute script
        console.log("Executing script: " + script.scriptName);
        const scriptResult = engines.execScript(
            script.scriptName,
            wrapScript(script.content, params)
        );
        
        // Wait for completion or timeout
        const timeout = Math.min(instruction.timeout || 300, 3600) * 1000;
        const startTime = Date.now();
        
        while (scriptResult.getEngine().isRunning() && 
               Date.now() - startTime < timeout) {
            sleep(100);
        }
        
        if (scriptResult.getEngine().isRunning()) {
            scriptResult.getEngine().forceStop();
            result.error = "Script execution timeout";
        } else {
            result.success = true;
            result.output = "Script completed successfully";
        }
        
        // Take after screenshot if requested
        if (params.screenshotAfter) {
            result.screenshots.push(captureScreenshot());
        }
        
    } catch (e) {
        result.error = e.toString();
    }
    
    return result;
}

/**
 * Download script from server
 */
function downloadScript(scriptId) {
    const timestamp = Date.now();
    const signatureData = CONFIG.deviceCode + ":" + scriptId + ":" + timestamp + ":" + certificate;
    const signature = generateHMAC(signatureData, certificate);
    
    try {
        const response = http.get(
            CONFIG.serverUrl + "/api/autojs/" + CONFIG.apiVersion + "/script/" + scriptId,
            {
                headers: {
                    "X-Device-Code": CONFIG.deviceCode,
                    "X-Timestamp": Math.floor(timestamp / 1000),
                    "X-Signature": signature
                }
            }
        );
        
        if (response.statusCode === 200) {
            return response.body.json();
        } else {
            console.error("Failed to download script: " + response.statusCode);
            return null;
        }
    } catch (e) {
        console.error("Script download error: " + e);
        return null;
    }
}

/**
 * Report execution result to server
 */
function reportExecutionResult(result) {
    const timestamp = Date.now();
    const signatureData = CONFIG.deviceCode + ":" + result.instructionId + ":" + timestamp + ":" + certificate;
    const signature = generateHMAC(signatureData, certificate);
    
    const reportData = Object.assign({
        deviceCode: CONFIG.deviceCode,
        signature: signature,
        timestamp: Math.floor(timestamp / 1000)
    }, result);
    
    try {
        const response = http.postJson(
            CONFIG.serverUrl + "/api/autojs/" + CONFIG.apiVersion + "/device/report-result",
            reportData,
            {
                headers: {
                    "Content-Type": "application/json",
                    "User-Agent": "AutoJS-Client/1.0"
                }
            }
        );
        
        if (response.statusCode === 200) {
            console.log("Result reported successfully");
        } else {
            console.error("Failed to report result: " + response.statusCode);
        }
    } catch (e) {
        console.error("Result report error: " + e);
    }
}

/**
 * Utility Functions
 */

// Generate HMAC signature
function generateHMAC(data, key) {
    return java.lang.String(
        javax.crypto.Mac.getInstance("HmacSHA256")
            .apply(function(mac) {
                mac.init(new javax.crypto.spec.SecretKeySpec(
                    java.lang.String(key).getBytes("UTF-8"),
                    "HmacSHA256"
                ));
                return mac.doFinal(java.lang.String(data).getBytes("UTF-8"));
            })
    ).encodeToHex();
}

// Wrap script with parameter injection
function wrapScript(scriptContent, params) {
    return "(function() {\n" +
           "  const params = " + JSON.stringify(params) + ";\n" +
           "  " + scriptContent + "\n" +
           "})();";
}

// Capture screenshot
function captureScreenshot() {
    const img = captureScreen();
    const base64 = images.toBase64(img, "png", 50);
    img.recycle();
    return base64;
}

// Get Auto.js version
function getAutoJsVersion() {
    try {
        return app.autojs.versionName;
    } catch (e) {
        return "Unknown";
    }
}

// Get network type
function getNetworkType() {
    // Implementation depends on Auto.js version
    return "wifi"; // or "mobile", "none"
}

// Get WiFi signal level
function getWifiSignalLevel() {
    // Implementation depends on device
    return -50; // dBm
}

// Get CPU usage
function getCpuUsage() {
    // Simple implementation
    return Math.random() * 100;
}

// Get memory usage
function getMemoryUsage() {
    return Math.floor((device.totalMem - device.availMem) / 1024 / 1024); // MB
}

// Get detailed device info
function getDetailedDeviceInfo() {
    return {
        device: {
            model: device.model,
            brand: device.brand,
            device: device.device,
            product: device.product,
            board: device.board,
            hardware: device.hardware,
            fingerprint: device.fingerprint,
            bootloader: device.bootloader,
            serial: device.serial,
            sdkInt: device.sdkInt,
            release: device.release,
            baseOS: device.baseOS,
            securityPatch: device.securityPatch
        },
        display: {
            width: device.width,
            height: device.height,
            density: device.density,
            densityDpi: device.densityDpi,
            scaledDensity: device.scaledDensity,
            rotation: device.rotation
        },
        memory: {
            total: device.totalMem,
            available: device.availMem,
            threshold: device.threshold
        },
        storage: {
            total: device.totalStorage,
            available: device.getAvailableStorage()
        },
        battery: {
            level: device.getBattery(),
            isCharging: device.isCharging()
        }
    };
}

// Apply server configuration
function applyServerConfig(config) {
    if (config.heartbeatInterval) {
        CONFIG.heartbeatInterval = config.heartbeatInterval * 1000;
    }
    
    storage.put(CONFIG.storageKeys.config, JSON.stringify(config));
    console.log("Server configuration applied");
}

// Update local configuration
function updateConfig(newConfig) {
    Object.assign(CONFIG, newConfig);
    storage.put("client_config", JSON.stringify(CONFIG));
}

// Stop client gracefully
function stopClient() {
    isRunning = false;
    
    // Stop all execution threads
    Object.values(executionThreads).forEach(thread => {
        thread.interrupt();
    });
    
    // Stop heartbeat
    if (heartbeatThread) {
        heartbeatThread.interrupt();
    }
    
    engines.myEngine().forceStop();
}

// Start the client
main();
```

## Advanced Features

### Batch Log Upload

```javascript
// Log buffer
const logBuffer = [];
const LOG_BUFFER_SIZE = 100;
const LOG_UPLOAD_INTERVAL = 300000; // 5 minutes

// Log levels
const LogLevel = {
    DEBUG: "DEBUG",
    INFO: "INFO",
    WARNING: "WARNING",
    ERROR: "ERROR",
    CRITICAL: "CRITICAL"
};

// Log types
const LogType = {
    SYSTEM: "SYSTEM",
    SCRIPT: "SCRIPT",
    NETWORK: "NETWORK",
    DEVICE: "DEVICE"
};

/**
 * Add log entry
 */
function log(level, type, message, context) {
    const entry = {
        level: level,
        type: type,
        message: message,
        logTime: new Date().toISOString(),
        context: context || null,
        stackTrace: level === LogLevel.ERROR ? getStackTrace() : null
    };
    
    logBuffer.push(entry);
    
    // Upload if buffer is full
    if (logBuffer.length >= LOG_BUFFER_SIZE) {
        uploadLogs();
    }
}

/**
 * Upload buffered logs
 */
function uploadLogs() {
    if (logBuffer.length === 0) return;
    
    const timestamp = Date.now();
    const signatureData = CONFIG.deviceCode + ":" + timestamp + ":" + logBuffer.length + ":" + certificate;
    const signature = generateHMAC(signatureData, certificate);
    
    const logsToUpload = logBuffer.splice(0, LOG_BUFFER_SIZE);
    
    threads.start(function() {
        try {
            const response = http.postJson(
                CONFIG.serverUrl + "/api/autojs/" + CONFIG.apiVersion + "/device/logs",
                {
                    deviceCode: CONFIG.deviceCode,
                    signature: signature,
                    timestamp: Math.floor(timestamp / 1000),
                    logs: logsToUpload
                }
            );
            
            if (response.statusCode !== 200) {
                // Re-add logs to buffer on failure
                logBuffer.unshift(...logsToUpload);
            }
        } catch (e) {
            console.error("Log upload failed: " + e);
            logBuffer.unshift(...logsToUpload);
        }
    });
}

// Start periodic log upload
setInterval(uploadLogs, LOG_UPLOAD_INTERVAL);
```

### Reconnection Logic

```javascript
let connectionFailures = 0;
const MAX_FAILURES = 5;
const BACKOFF_MULTIPLIER = 2;

/**
 * Enhanced heartbeat with reconnection
 */
function heartbeatWithReconnect() {
    try {
        heartbeat();
        connectionFailures = 0; // Reset on success
    } catch (e) {
        connectionFailures++;
        console.error("Heartbeat failed (" + connectionFailures + "/" + MAX_FAILURES + "): " + e);
        
        if (connectionFailures >= MAX_FAILURES) {
            console.log("Max failures reached, attempting re-registration...");
            certificate = null;
            storage.remove(CONFIG.storageKeys.certificate);
            
            if (registerDevice()) {
                connectionFailures = 0;
            } else {
                // Exponential backoff
                const delay = CONFIG.retryDelay * Math.pow(BACKOFF_MULTIPLIER, connectionFailures - MAX_FAILURES);
                console.log("Re-registration failed, retrying in " + delay + "ms");
                sleep(delay);
            }
        }
    }
}
```

### Script Caching

```javascript
const scriptCache = {};
const CACHE_EXPIRY = 3600000; // 1 hour

/**
 * Download script with caching
 */
function downloadScriptWithCache(scriptId) {
    const cached = scriptCache[scriptId];
    
    if (cached && Date.now() - cached.timestamp < CACHE_EXPIRY) {
        console.log("Using cached script: " + scriptId);
        return cached.script;
    }
    
    const script = downloadScript(scriptId);
    
    if (script) {
        scriptCache[scriptId] = {
            script: script,
            timestamp: Date.now()
        };
    }
    
    return script;
}
```

## Error Handling

### Comprehensive Error Handling

```javascript
/**
 * Global error handler
 */
threads.currentThread().setUncaughtExceptionHandler(
    new java.lang.Thread.UncaughtExceptionHandler({
        uncaughtException: function(thread, error) {
            console.error("Uncaught error in thread " + thread.getName() + ": " + error);
            
            log(LogLevel.CRITICAL, LogType.SYSTEM, 
                "Uncaught exception: " + error.toString(),
                "Thread: " + thread.getName()
            );
            
            // Try to recover
            if (thread === heartbeatThread) {
                console.log("Restarting heartbeat thread...");
                setTimeout(() => startHeartbeat(), 5000);
            }
        }
    })
);

/**
 * Network error handler
 */
function handleNetworkError(error, operation) {
    const errorInfo = {
        operation: operation,
        error: error.toString(),
        timestamp: new Date().toISOString(),
        networkType: getNetworkType()
    };
    
    log(LogLevel.ERROR, LogType.NETWORK, "Network error", errorInfo);
    
    // Check if we need to switch to offline mode
    if (isNetworkError(error)) {
        enterOfflineMode();
    }
}

/**
 * Offline mode operations
 */
let offlineMode = false;
const offlineQueue = [];

function enterOfflineMode() {
    if (!offlineMode) {
        offlineMode = true;
        console.log("Entering offline mode");
        toast("离线模式");
        
        // Start checking for connectivity
        threads.start(function() {
            while (offlineMode) {
                sleep(30000); // Check every 30 seconds
                
                if (checkConnectivity()) {
                    exitOfflineMode();
                }
            }
        });
    }
}

function exitOfflineMode() {
    offlineMode = false;
    console.log("Exiting offline mode");
    toast("已恢复在线");
    
    // Process offline queue
    processOfflineQueue();
}
```

## Security Implementation

### Enhanced Security Features

```javascript
/**
 * Certificate validation
 */
function validateCertificate(cert) {
    try {
        // Check certificate format
        if (!cert || cert.length < 64) {
            return false;
        }
        
        // Verify certificate signature (simplified)
        const parts = cert.split(".");
        if (parts.length !== 3) {
            return false;
        }
        
        // Check expiration
        const payload = JSON.parse(
            java.lang.String(
                android.util.Base64.decode(parts[1], android.util.Base64.DEFAULT)
            )
        );
        
        if (payload.exp && payload.exp < Date.now() / 1000) {
            console.log("Certificate expired");
            return false;
        }
        
        return true;
    } catch (e) {
        console.error("Certificate validation error: " + e);
        return false;
    }
}

/**
 * Encrypt sensitive data
 */
function encryptData(data, key) {
    // Simple XOR encryption for demonstration
    // In production, use proper encryption library
    const keyBytes = java.lang.String(key).getBytes();
    const dataBytes = java.lang.String(JSON.stringify(data)).getBytes();
    const result = new Array(dataBytes.length);
    
    for (let i = 0; i < dataBytes.length; i++) {
        result[i] = dataBytes[i] ^ keyBytes[i % keyBytes.length];
    }
    
    return android.util.Base64.encodeToString(result, android.util.Base64.NO_WRAP);
}

/**
 * Secure storage
 */
const SecureStorage = {
    put: function(key, value) {
        const encrypted = encryptData(value, certificate);
        storage.put("secure_" + key, encrypted);
    },
    
    get: function(key) {
        const encrypted = storage.get("secure_" + key);
        if (!encrypted) return null;
        
        return decryptData(encrypted, certificate);
    },
    
    remove: function(key) {
        storage.remove("secure_" + key);
    }
};
```

## Performance Optimization

### Memory Management

```javascript
/**
 * Memory monitor
 */
const MemoryMonitor = {
    threshold: 0.8, // 80% memory usage threshold
    
    check: function() {
        const usage = (device.totalMem - device.availMem) / device.totalMem;
        
        if (usage > this.threshold) {
            console.warn("High memory usage: " + (usage * 100).toFixed(1) + "%");
            this.cleanup();
        }
        
        return usage;
    },
    
    cleanup: function() {
        // Clear script cache
        Object.keys(scriptCache).forEach(key => {
            if (Date.now() - scriptCache[key].timestamp > 600000) { // 10 minutes
                delete scriptCache[key];
            }
        });
        
        // Force garbage collection
        java.lang.System.gc();
        
        // Clear old logs
        if (logBuffer.length > LOG_BUFFER_SIZE / 2) {
            uploadLogs();
        }
    }
};

// Monitor memory periodically
setInterval(() => MemoryMonitor.check(), 60000);
```

### Battery Optimization

```javascript
/**
 * Battery-aware operations
 */
const BatteryManager = {
    lowBatteryThreshold: 20,
    criticalBatteryThreshold: 10,
    
    shouldReduceActivity: function() {
        const level = device.getBattery();
        const isCharging = device.isCharging();
        
        if (isCharging) return false;
        
        if (level < this.criticalBatteryThreshold) {
            return "critical";
        } else if (level < this.lowBatteryThreshold) {
            return "low";
        }
        
        return false;
    },
    
    adjustBehavior: function() {
        const status = this.shouldReduceActivity();
        
        if (status === "critical") {
            // Critical battery - minimize activity
            CONFIG.heartbeatInterval = 120000; // 2 minutes
            CONFIG.pollTimeout = 10000; // 10 seconds
            console.log("Critical battery mode activated");
        } else if (status === "low") {
            // Low battery - reduce activity
            CONFIG.heartbeatInterval = 60000; // 1 minute
            CONFIG.pollTimeout = 20000; // 20 seconds
            console.log("Low battery mode activated");
        } else {
            // Normal mode
            CONFIG.heartbeatInterval = 30000; // 30 seconds
            CONFIG.pollTimeout = 25000; // 25 seconds
        }
    }
};

// Check battery status periodically
setInterval(() => BatteryManager.adjustBehavior(), 300000); // Every 5 minutes
```

## Testing and Debugging

### Debug Mode

```javascript
const DEBUG = true; // Set to false in production

/**
 * Debug logger
 */
const Debug = {
    log: function(message) {
        if (DEBUG) {
            console.log("[DEBUG] " + message);
            log(LogLevel.DEBUG, LogType.SYSTEM, message);
        }
    },
    
    logObject: function(label, obj) {
        if (DEBUG) {
            console.log("[DEBUG] " + label + ":");
            console.log(JSON.stringify(obj, null, 2));
        }
    },
    
    time: function(label) {
        if (DEBUG) {
            console.time(label);
        }
    },
    
    timeEnd: function(label) {
        if (DEBUG) {
            console.timeEnd(label);
        }
    }
};
```

### Test Commands

```javascript
/**
 * Test command implementations
 */
const TestCommands = {
    // Test connectivity
    testConnection: function() {
        Debug.log("Testing connection to server...");
        
        try {
            const response = http.get(CONFIG.serverUrl + "/api/health");
            Debug.log("Connection test result: " + response.statusCode);
            return response.statusCode === 200;
        } catch (e) {
            Debug.log("Connection test failed: " + e);
            return false;
        }
    },
    
    // Test script execution
    testScriptExecution: function() {
        Debug.log("Testing script execution...");
        
        const testScript = 'toast("Test script executed successfully");';
        
        try {
            engines.execScript("test", testScript);
            return true;
        } catch (e) {
            Debug.log("Script execution test failed: " + e);
            return false;
        }
    },
    
    // Simulate high load
    simulateHighLoad: function(duration) {
        Debug.log("Simulating high load for " + duration + "ms");
        
        const startTime = Date.now();
        const threads = [];
        
        // Start multiple threads
        for (let i = 0; i < 5; i++) {
            threads.push(threads.start(function() {
                while (Date.now() - startTime < duration) {
                    // Busy work
                    Math.sqrt(Math.random() * 1000000);
                }
            }));
        }
        
        // Wait for completion
        threads.forEach(t => t.join());
        Debug.log("High load simulation completed");
    }
};

// Run tests if in debug mode
if (DEBUG) {
    setTimeout(() => {
        console.log("Running diagnostic tests...");
        TestCommands.testConnection();
        TestCommands.testScriptExecution();
    }, 5000);
}
```

### Performance Profiling

```javascript
/**
 * Performance profiler
 */
const Profiler = {
    metrics: {},
    
    start: function(operation) {
        this.metrics[operation] = {
            startTime: Date.now(),
            startMemory: device.availMem
        };
    },
    
    end: function(operation) {
        const metric = this.metrics[operation];
        if (!metric) return;
        
        const duration = Date.now() - metric.startTime;
        const memoryDelta = metric.startMemory - device.availMem;
        
        Debug.log("Performance: " + operation + 
                 " - Duration: " + duration + "ms" +
                 " - Memory: " + (memoryDelta / 1024 / 1024).toFixed(2) + "MB");
        
        // Log to server if significant
        if (duration > 1000 || memoryDelta > 10 * 1024 * 1024) {
            log(LogLevel.WARNING, LogType.SYSTEM, 
                "Performance issue detected",
                {
                    operation: operation,
                    duration: duration,
                    memoryDelta: memoryDelta
                }
            );
        }
        
        delete this.metrics[operation];
    }
};

// Example usage
Profiler.start("heartbeat");
heartbeat();
Profiler.end("heartbeat");
```

## Best Practices

1. **Always handle errors gracefully** - Network issues are common on mobile devices
2. **Implement retry logic** - Use exponential backoff for failed requests
3. **Monitor resource usage** - Be mindful of battery and data consumption
4. **Use secure communication** - Always validate certificates and use HTTPS
5. **Log important events** - But avoid logging sensitive information
6. **Test on various devices** - Different Auto.js versions may behave differently
7. **Implement offline mode** - Queue operations when network is unavailable
8. **Clean up resources** - Properly stop threads and release resources
9. **Update regularly** - Check for client updates from the server
10. **Profile performance** - Monitor and optimize slow operations