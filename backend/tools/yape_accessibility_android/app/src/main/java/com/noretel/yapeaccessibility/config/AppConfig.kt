package com.noretel.yapeaccessibility.config

data class AppConfig(
    val backendUrl: String,
    val monitorToken: String,
    val yapePackage: String,
    val enabled: Boolean,
    val pollIntervalMs: Long,
    val pendingLimit: Int
) {
    val isReady: Boolean
        get() = backendUrl.isNotBlank() && monitorToken.isNotBlank() && yapePackage.isNotBlank()
}
