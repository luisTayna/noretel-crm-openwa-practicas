package com.noretel.yapeaccessibility.api

import org.json.JSONObject
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

class YapeApiClient(
    private val backendUrl: String,
    private val monitorToken: String
) {
    fun pendingReceiverIntents(limit: Int = 20): List<PendingIntentDto> {
        val url = URL("${backendUrl.trimEnd('/')}/api/billing/yape/pending-receiver-intents?limit=$limit")
        val connection = (url.openConnection() as HttpURLConnection).apply {
            requestMethod = "GET"
            connectTimeout = 15000
            readTimeout = 20000
            setRequestProperty("Authorization", "Bearer $monitorToken")
            setRequestProperty("Accept", "application/json")
        }

        return connection.useJsonResponse { body ->
            val json = JSONObject(body)
            val intents = json.optJSONArray("intents") ?: return@useJsonResponse emptyList()
            buildList {
                for (index in 0 until intents.length()) {
                    val item = intents.getJSONObject(index)
                    add(
                        PendingIntentDto(
                            intentId = item.getLong("intent_id"),
                            intentNumber = item.optStringOrNull("intent_number"),
                            expectedAmount = item.optDouble("expected_amount", 0.0),
                            customerName = item.optStringOrNull("customer_name"),
                            whatsappPhone = item.optStringOrNull("whatsapp_phone"),
                            operationCode = item.optStringOrNull("operation_code"),
                            securityCode = item.optStringOrNull("security_code"),
                            clientEvidenceId = item.optLongOrNull("client_evidence_id"),
                            clientOccurredAt = item.optStringOrNull("client_occurred_at"),
                            createdAt = item.optStringOrNull("created_at"),
                            expiresAt = item.optStringOrNull("expires_at")
                        )
                    )
                }
            }
        }
    }

    fun postReceiverEvent(payload: ReceiverEventPayload): PostResult {
        val params = linkedMapOf(
            "source" to payload.source,
            "device_serial" to payload.deviceSerial,
            "package_name" to payload.packageName,
            "raw_text" to payload.rawText,
            "amount" to (payload.amount ?: ""),
            "operation_code" to (payload.operationCode ?: ""),
            "security_code" to (payload.securityCode ?: ""),
            "occurred_at" to (payload.occurredAt ?: ""),
            "sender_name" to (payload.senderName ?: ""),
            "confidence" to payload.confidence.toString(),
            "target_intent_id" to (payload.targetIntent?.intentId?.toString() ?: ""),
            "target_amount" to (payload.targetIntent?.expectedAmount?.toString() ?: ""),
            "target_customer_name" to (payload.targetIntent?.customerName ?: ""),
            "movement_list_context" to payload.movementListContext,
            "movement_match_score" to payload.movementMatchScore.toString(),
            "metadata" to (payload.metadataJson ?: "")
        )

        val body = params.entries.joinToString("&") { (key, value) ->
            "${key.urlEncode()}=${value.urlEncode()}"
        }
        val url = URL("${backendUrl.trimEnd('/')}/api/billing/yape/receiver-events")
        val connection = (url.openConnection() as HttpURLConnection).apply {
            requestMethod = "POST"
            connectTimeout = 15000
            readTimeout = 20000
            doOutput = true
            setRequestProperty("Authorization", "Bearer $monitorToken")
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8")
        }

        OutputStreamWriter(connection.outputStream, Charsets.UTF_8).use { it.write(body) }

        return connection.useJsonResponse { responseBody ->
            val json = JSONObject(responseBody)
            PostResult(
                ok = json.optBoolean("ok", false),
                intentStatus = json.optStringOrNull("intent_status"),
                rawBody = responseBody
            )
        }
    }

    private fun <T> HttpURLConnection.useJsonResponse(block: (String) -> T): T {
        val code = responseCode
        val stream = if (code in 200..299) inputStream else errorStream
        val body = stream?.bufferedReader()?.use { it.readText() }.orEmpty()
        disconnect()
        if (code !in 200..299) {
            throw IllegalStateException("HTTP $code: $body")
        }
        return block(body)
    }

    private fun String.urlEncode(): String = URLEncoder.encode(this, "UTF-8")

    private fun JSONObject.optStringOrNull(name: String): String? =
        if (isNull(name)) null else optString(name).takeIf { it.isNotBlank() }

    private fun JSONObject.optLongOrNull(name: String): Long? =
        if (isNull(name)) null else optLong(name)
}
