package com.noretel.yapeaccessibility.service

import android.app.Notification
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import com.noretel.yapeaccessibility.api.ParsedYapeNotification
import com.noretel.yapeaccessibility.api.PendingIntentDto
import com.noretel.yapeaccessibility.api.ReceiverEventPayload
import com.noretel.yapeaccessibility.api.YapeApiClient
import com.noretel.yapeaccessibility.config.SecureConfigStore
import com.noretel.yapeaccessibility.debug.YapeLogger
import com.noretel.yapeaccessibility.yape.YapeCandidateScorer
import com.noretel.yapeaccessibility.yape.YapeTextParser
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.concurrent.Executors

class YapeNotificationListenerService : NotificationListenerService() {
    companion object {
        @Volatile
        var instance: YapeNotificationListenerService? = null
            private set
    }

    private val worker = Executors.newSingleThreadExecutor()
    private lateinit var configStore: SecureConfigStore
    private val processedKeys = linkedSetOf<String>()

    override fun onCreate() {
        super.onCreate()
        instance = this
        configStore = SecureConfigStore(this)
        YapeLogger.d("NotificationListener creado.")
    }

    override fun onListenerConnected() {
        super.onListenerConnected()
        val config = configStore.getConfig()
        YapeLogger.d(
            "NotificationListener conectado: enabled=${config.enabled}, " +
                "package=${config.yapePackage}, backend=${config.backendUrl}"
        )
        processActiveYapeNotifications("listener_connected")
    }

    override fun onDestroy() {
        instance = null
        worker.shutdownNow()
        YapeLogger.w("NotificationListener destruido.")
        super.onDestroy()
    }

    override fun onNotificationPosted(sbn: StatusBarNotification?) {
        if (sbn == null) return
        worker.execute {
            processStatusBarNotification(sbn, manual = false)
        }
    }

    private fun processActiveYapeNotifications(reason: String) {
        worker.execute {
            try {
                val config = configStore.getConfig()
                val active = activeNotifications?.toList().orEmpty()
                val yapeActive = active.filter { it.packageName == config.yapePackage }

                YapeLogger.d(
                    "notificaciones activas revisadas: reason=$reason, total=${active.size}, " +
                        "yape=${yapeActive.size}"
                )

                yapeActive.forEach { sbn ->
                    processStatusBarNotification(sbn, manual = false)
                }
            } catch (throwable: Throwable) {
                YapeLogger.e("No se pudo revisar notificaciones activas: ${throwable.message}", throwable)
            }
        }
    }

    fun getLastNotificationSummary(): String {
        val raw = configStore.getLastNotification()
            ?: return "Todavía no hay notificación Yape capturada."
        val json = JSONObject(raw)
        return "package=${json.optString("package_name")}, amount=${json.optString("amount_text", json.optString("amount"))}, " +
            "operation=${json.optString("operation_code")}, security=${json.optString("security_code")}, sender=${json.optString("sender_name")}, " +
            "occurred_at=${json.optString("occurred_at")}, raw=${YapeLogger.truncate(json.optString("raw_text"), 300)}"
    }

    fun sendLastNotificationFromUi(callback: (String) -> Unit) {
        worker.execute {
            val message = try {
                val raw = configStore.getLastNotification()
                    ?: return@execute callback("No hay última notificación capturada.")
                val json = JSONObject(raw)
                val parsed = ParsedYapeNotification(
                    packageName = json.optString("package_name"),
                    rawText = json.optString("raw_text"),
                    amount = json.optDoubleOrNull("amount"),
                    amountText = json.optStringOrNull("amount_text"),
                    operationCode = json.optStringOrNull("operation_code"),
                    securityCode = json.optStringOrNull("security_code"),
                    senderName = json.optStringOrNull("sender_name"),
                    occurredAt = json.optStringOrNull("occurred_at"),
                    postTime = json.optLong("post_time", 0L)
                )
                processParsedNotification(parsed, manual = true)
            } catch (throwable: Throwable) {
                YapeLogger.e("Enviar última notificación falló: ${throwable.message}", throwable)
                "Error enviando última notificación: ${throwable.message}"
            }
            callback(message)
        }
    }

    private fun processStatusBarNotification(sbn: StatusBarNotification, manual: Boolean): String {
        val config = configStore.getConfig()
        YapeLogger.d("notificación recibida: package=${sbn.packageName}, key=${sbn.key}")

        if (sbn.packageName != config.yapePackage) {
            YapeLogger.d("notificación ignorada: package ${sbn.packageName} no es Yape ${config.yapePackage}.")
            return "Ignorada: no es Yape."
        }

        val parsed = parseNotification(sbn)
        configStore.saveLastNotification(
            packageName = parsed.packageName,
            rawText = parsed.rawText,
            amount = parsed.amount,
            amountText = parsed.amountText,
            operationCode = parsed.operationCode,
            securityCode = parsed.securityCode,
            senderName = parsed.senderName,
            occurredAt = parsed.occurredAt,
            postTime = parsed.postTime
        )

        return processParsedNotification(parsed, manual)
    }

    private fun processParsedNotification(parsed: ParsedYapeNotification, manual: Boolean): String {
        val config = configStore.getConfig()
        if (!config.enabled || !config.isReady) {
            val message = "motivo no envía: enabled=${config.enabled}, isReady=${config.isReady}."
            YapeLogger.w(message)
            return message
        }

        if (parsed.packageName != config.yapePackage) {
            val message = "motivo no envía: última notificación no pertenece a Yape."
            YapeLogger.w(message)
            return message
        }

        val key = dedupeKey(parsed)
        if (!manual && key in processedKeys) {
            val message = "motivo no envía: notificación duplicada key=$key."
            YapeLogger.w(message)
            return message
        }

        YapeLogger.d("raw_text notificación=${YapeLogger.truncate(parsed.rawText)}")
        YapeLogger.d(
            "parser notificación: amount=${parsed.amount ?: "null"}, amount_normalizado=${parsed.amountText ?: "null"}, " +
                "operation_code=${parsed.operationCode ?: "null"}, security_code=${parsed.securityCode ?: "null"}, " +
                "sender_name=${parsed.senderName ?: "null"}, " +
                "occurred_at=${parsed.occurredAt ?: "null"}"
        )

        if (parsed.amount == null) {
            val message = "motivo no envía: notificación incompleta; requiere monto."
            YapeLogger.w(message)
            return message
        }

        if (parsed.operationCode.isNullOrBlank()) {
            YapeLogger.d(
                "operation_code=null: correcto para notificación Yape; el matching automático usa amount+security_code."
            )
        }

        if (parsed.securityCode.isNullOrBlank()) {
            val message = "motivo no envía: notificación sin security_code; no se guarda automático."
            YapeLogger.w(message)
            return message
        }

        val api = YapeApiClient(config.backendUrl, config.monitorToken)
        val intents = api.pendingReceiverIntents(config.pendingLimit)
        YapeLogger.d("pending-receiver-intents desde notificación: count=${intents.size}")

        val candidate = selectCandidate(parsed, intents)
        val score = if (candidate != null) YapeCandidateScorer.score(candidate, parsed.rawText).coerceAtLeast(95) else 90

        if (candidate != null) {
            YapeLogger.d(
                "candidato elegido: intent_id=${candidate.intentId}, amount=${candidate.expectedAmount}, " +
                    "operation=${candidate.operationCode}, security=${candidate.securityCode}, matching=${matchingMode(parsed)}, score=$score"
            )
        } else {
            YapeLogger.w(
                "sin candidato en pending-receiver-intents: se enviará evento receptor sin target_intent_id " +
                    "para que Laravel lo guarde y lo asocie cuando llegue WhatsApp/OCR."
            )
        }

        val payload = ReceiverEventPayload(
            source = "android_notification",
            deviceSerial = "android-notification-listener",
            packageName = parsed.packageName,
            rawText = parsed.rawText,
            amount = parsed.amountText,
            operationCode = parsed.operationCode,
            securityCode = parsed.securityCode,
            occurredAt = parsed.occurredAt,
            senderName = parsed.senderName,
            confidence = 0.98,
            targetIntent = candidate,
            movementListContext = parsed.rawText,
            movementMatchScore = score,
            metadataJson = notificationMetadataJson(parsed, matchingMode(parsed), candidate != null)
        )

        YapeLogger.d(
            "payload receiver-events: source=${payload.source}, target_intent_id=${payload.targetIntent?.intentId ?: "null"}, " +
                "amount=${payload.amount}, operation_code=${payload.operationCode}, security_code=${payload.securityCode}, sender_name=${payload.senderName ?: "null"}, " +
                "package_name=${payload.packageName}, " +
                "raw_text=${YapeLogger.truncate(payload.rawText)}"
        )

        val result = api.postReceiverEvent(payload)
        YapeLogger.d("respuesta Laravel receiver-events: ok=${result.ok}, intent_status=${result.intentStatus}, body=${YapeLogger.truncate(result.rawBody)}")
        if (result.ok) rememberProcessedKey(key)
        return if (result.ok) "Evento enviado. intent_status=${result.intentStatus}" else "Laravel respondió ok=false."
    }

    private fun parseNotification(sbn: StatusBarNotification): ParsedYapeNotification {
        val extras = sbn.notification.extras
        val fields = listOf(
            extras.getCharSequence(Notification.EXTRA_TITLE)?.toString(),
            extras.getCharSequence(Notification.EXTRA_TEXT)?.toString(),
            extras.getCharSequence(Notification.EXTRA_BIG_TEXT)?.toString(),
            extras.getCharSequence(Notification.EXTRA_SUB_TEXT)?.toString(),
            extras.getCharSequence(Notification.EXTRA_SUMMARY_TEXT)?.toString(),
            extras.getCharSequence(Notification.EXTRA_INFO_TEXT)?.toString()
        )
        val rawText = fields
            .filter { !it.isNullOrBlank() }
            .distinct()
            .joinToString("\n")

        return ParsedYapeNotification(
            packageName = sbn.packageName,
            rawText = rawText,
            amount = YapeTextParser.parseAmount(rawText),
            amountText = YapeTextParser.parseAmountText(rawText),
            operationCode = YapeTextParser.parseOperationCode(rawText),
            securityCode = YapeTextParser.parseSecurityCode(rawText),
            senderName = YapeTextParser.parseSenderName(rawText),
            occurredAt = formatPostTime(sbn.postTime),
            postTime = sbn.postTime
        )
    }

    private fun selectCandidate(parsed: ParsedYapeNotification, intents: List<PendingIntentDto>): PendingIntentDto? {
        if (parsed.securityCode.isNullOrBlank()) {
            YapeLogger.w("motivo no usa candidato: notificación sin security_code.")
            return null
        }

        YapeLogger.d("matching usado: amount+security_code")
        val exact = intents.filter { intent ->
            parsed.amount != null &&
                kotlin.math.abs(intent.expectedAmount - parsed.amount) < 0.001 &&
                intent.securityCode == parsed.securityCode
        }

        if (exact.isEmpty()) {
            YapeLogger.w("sin candidato: ningún pending intent coincide por monto exacto + código de seguridad exacto.")
            return null
        }

        if (exact.size == 1) return exact.first()

        YapeLogger.w(
            "sin candidato único: ambigüedad por monto+código de seguridad. exact=${exact.size}, " +
                "security_code=${parsed.securityCode}, sender=${parsed.senderName ?: "null"}"
        )
        return null
    }

    private fun matchingMode(parsed: ParsedYapeNotification): String =
        if (parsed.securityCode.isNullOrBlank()) "none_missing_security_code" else "amount+security_code"

    private fun notificationMetadataJson(parsed: ParsedYapeNotification, matchingMode: String, hasTargetIntent: Boolean): String =
        JSONObject()
            .put("source_package", parsed.packageName)
            .put("notification_security_code", parsed.securityCode)
            .put("matching_mode", matchingMode)
            .put("has_target_intent", hasTargetIntent)
            .put("has_real_operation_code", !parsed.operationCode.isNullOrBlank())
            .put("note", "notification is sent with amount+security_code even before WhatsApp/OCR; Laravel may attach it later")
            .toString()

    private fun nameLooksCompatible(senderName: String?, customerName: String?): Boolean {
        val sender = YapeTextParser.normalize(senderName)
        val customerTokens = YapeTextParser.normalize(customerName)
            .split(" ")
            .filter { it.length >= 3 && it !in setOf("del", "de", "las", "los", "sra", "sr") }

        if (sender.isBlank() || customerTokens.isEmpty()) return false

        val first = customerTokens.firstOrNull() ?: return false
        val second = customerTokens.getOrNull(1)

        return first in sender || (second != null && second.take(4) in sender)
    }

    private fun dedupeKey(parsed: ParsedYapeNotification): String =
        "${parsed.packageName}|${parsed.postTime}|${parsed.amount}|${parsed.securityCode ?: parsed.operationCode ?: parsed.rawText.hashCode()}"

    private fun rememberProcessedKey(key: String) {
        processedKeys.add(key)
        while (processedKeys.size > 100) {
            processedKeys.remove(processedKeys.first())
        }
    }

    private fun formatPostTime(postTime: Long): String =
        SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Date(postTime))

    private fun JSONObject.optStringOrNull(name: String): String? =
        if (isNull(name)) null else optString(name).takeIf { it.isNotBlank() && it != "null" }

    private fun JSONObject.optDoubleOrNull(name: String): Double? =
        if (isNull(name)) null else optDouble(name)
}
