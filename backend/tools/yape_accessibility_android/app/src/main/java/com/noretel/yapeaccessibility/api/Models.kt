package com.noretel.yapeaccessibility.api

data class PendingIntentDto(
    val intentId: Long,
    val intentNumber: String?,
    val expectedAmount: Double,
    val customerName: String?,
    val whatsappPhone: String?,
    val operationCode: String?,
    val securityCode: String?,
    val clientEvidenceId: Long?,
    val clientOccurredAt: String?,
    val createdAt: String?,
    val expiresAt: String?
)

data class ReceiverEventPayload(
    val source: String,
    val deviceSerial: String,
    val packageName: String,
    val rawText: String,
    val amount: String?,
    val operationCode: String?,
    val securityCode: String?,
    val occurredAt: String?,
    val senderName: String?,
    val confidence: Double,
    val targetIntent: PendingIntentDto?,
    val movementListContext: String,
    val movementMatchScore: Int,
    val metadataJson: String?
)

data class PostResult(
    val ok: Boolean,
    val intentStatus: String?,
    val rawBody: String
)

data class ParsedYapeNotification(
    val packageName: String,
    val rawText: String,
    val amount: Double?,
    val amountText: String?,
    val operationCode: String?,
    val securityCode: String?,
    val senderName: String?,
    val occurredAt: String?,
    val postTime: Long
)
