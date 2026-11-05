package com.noretel.yapeaccessibility.config

import android.content.Context
import android.content.SharedPreferences
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec
import org.json.JSONObject

class SecureConfigStore(context: Context) {
    private val prefs: SharedPreferences = context.getSharedPreferences("yape_accessibility_config", Context.MODE_PRIVATE)

    fun getConfig(): AppConfig = AppConfig(
        backendUrl = prefs.getString(KEY_BACKEND_URL, "http://127.0.0.1:8000") ?: "http://127.0.0.1:8000",
        monitorToken = getSecret(KEY_MONITOR_TOKEN).orEmpty().ifBlank { DEFAULT_MONITOR_TOKEN },
        yapePackage = prefs.getString(KEY_YAPE_PACKAGE, "com.bcp.innovacxion.yapeapp") ?: "com.bcp.innovacxion.yapeapp",
        enabled = prefs.getBoolean(KEY_ENABLED, false),
        pollIntervalMs = prefs.getLong(KEY_POLL_INTERVAL_MS, 5000L),
        pendingLimit = prefs.getInt(KEY_PENDING_LIMIT, 20)
    )

    fun saveConfig(
        backendUrl: String,
        monitorToken: String,
        yapePackage: String,
        enabled: Boolean,
        pollIntervalMs: Long = 5000L,
        pendingLimit: Int = 20
    ) {
        prefs.edit()
            .putString(KEY_BACKEND_URL, backendUrl.trim().trimEnd('/'))
            .putString(KEY_YAPE_PACKAGE, yapePackage.trim())
            .putBoolean(KEY_ENABLED, enabled)
            .putLong(KEY_POLL_INTERVAL_MS, pollIntervalMs.coerceAtLeast(2000L))
            .putInt(KEY_PENDING_LIMIT, pendingLimit.coerceIn(1, 100))
            .apply()

        if (monitorToken.isNotBlank()) putSecret(KEY_MONITOR_TOKEN, monitorToken.trim())
    }

    fun setEnabled(enabled: Boolean) {
        prefs.edit().putBoolean(KEY_ENABLED, enabled).apply()
    }

    fun saveLastNotification(rawJson: String) {
        prefs.edit().putString(KEY_LAST_NOTIFICATION, rawJson).apply()
    }

    fun getLastNotification(): String? = prefs.getString(KEY_LAST_NOTIFICATION, null)

    fun saveLastNotification(
        packageName: String,
        rawText: String,
        amount: Double?,
        amountText: String?,
        operationCode: String?,
        securityCode: String?,
        senderName: String?,
        occurredAt: String?,
        postTime: Long
    ) {
        val json = JSONObject()
            .put("package_name", packageName)
            .put("raw_text", rawText)
            .put("amount", amount)
            .put("amount_text", amountText)
            .put("operation_code", operationCode)
            .put("security_code", securityCode)
            .put("sender_name", senderName)
            .put("occurred_at", occurredAt)
            .put("post_time", postTime)
        saveLastNotification(json.toString())
    }

    private fun putSecret(name: String, value: String) {
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.ENCRYPT_MODE, getOrCreateSecretKey())
        val encrypted = cipher.doFinal(value.toByteArray(Charsets.UTF_8))
        prefs.edit()
            .putString("$name.iv", Base64.encodeToString(cipher.iv, Base64.NO_WRAP))
            .putString("$name.value", Base64.encodeToString(encrypted, Base64.NO_WRAP))
            .apply()
    }

    private fun getSecret(name: String): String? {
        val iv = prefs.getString("$name.iv", null) ?: return null
        val value = prefs.getString("$name.value", null) ?: return null
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(
            Cipher.DECRYPT_MODE,
            getOrCreateSecretKey(),
            GCMParameterSpec(128, Base64.decode(iv, Base64.NO_WRAP))
        )
        return String(cipher.doFinal(Base64.decode(value, Base64.NO_WRAP)), Charsets.UTF_8)
    }

    private fun getOrCreateSecretKey(): SecretKey {
        val keyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        (keyStore.getEntry(KEY_ALIAS, null) as? KeyStore.SecretKeyEntry)?.let { return it.secretKey }

        val keyGenerator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore")
        keyGenerator.init(
            KeyGenParameterSpec.Builder(KEY_ALIAS, KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .setRandomizedEncryptionRequired(true)
                .build()
        )
        return keyGenerator.generateKey()
    }

    companion object {
        private const val KEY_ALIAS = "noretel_yape_accessibility_key"
        private const val TRANSFORMATION = "AES/GCM/NoPadding"
        private const val DEFAULT_MONITOR_TOKEN = "change-this-yape-monitor-token"
        private const val KEY_BACKEND_URL = "backend_url"
        private const val KEY_YAPE_PACKAGE = "yape_package"
        private const val KEY_MONITOR_TOKEN = "monitor_token"
        private const val KEY_ENABLED = "enabled"
        private const val KEY_POLL_INTERVAL_MS = "poll_interval_ms"
        private const val KEY_PENDING_LIMIT = "pending_limit"
        private const val KEY_LAST_NOTIFICATION = "last_notification"
    }
}
