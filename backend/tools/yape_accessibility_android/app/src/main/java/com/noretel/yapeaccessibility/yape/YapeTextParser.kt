package com.noretel.yapeaccessibility.yape

import com.noretel.yapeaccessibility.api.PendingIntentDto
import java.text.Normalizer
import java.util.Locale

object YapeTextParser {
    private val amountPatterns = listOf(
        Regex("(?:S/|S\\.|soles?)\\s*([0-9]+(?:[.,][0-9]{1,2})?)", RegexOption.IGNORE_CASE),
        Regex("(?:monto|importe|total)\\D{0,12}([0-9]+(?:[.,][0-9]{1,2})?)", RegexOption.IGNORE_CASE),
        Regex("\\b([0-9]{1,5}[.,][0-9]{2})\\b")
    )
    private val operationPatterns = listOf(
        Regex("(?:operaci[oó]n|op\\.?|n[°ºro.]*\\s*(?:de\\s*)?operaci[oó]n|transacci[oó]n)\\D{0,24}([0-9]{5,20})", RegexOption.IGNORE_CASE),
        Regex("\\b([0-9]{8,20})\\b")
    )
    private val securityCodePatterns = listOf(
        Regex("c[oó]d\\.?\\s*de\\s*seguridad\\s*es\\s*:?\\s*([0-9]{2,8})", RegexOption.IGNORE_CASE),
        Regex("c[oó]digo\\s*de\\s*seguridad\\s*es\\s*:?\\s*([0-9]{2,8})", RegexOption.IGNORE_CASE)
    )

    fun normalize(value: String?): String {
        if (value.isNullOrBlank()) return ""
        val withoutAccents = Normalizer.normalize(value, Normalizer.Form.NFD).replace("\\p{Mn}+".toRegex(), "")
        return withoutAccents.replace("[^a-zA-Z0-9.,:/\\s-]+".toRegex(), " ")
            .replace("\\s+".toRegex(), " ")
            .trim()
            .lowercase()
    }

    fun parseAmount(text: String): Double? {
        for (pattern in amountPatterns) {
            val match = pattern.find(text) ?: continue
            return match.groupValues[1].replace(',', '.').toDoubleOrNull()
        }
        return null
    }

    fun parseAmountText(text: String): String? {
        val amount = parseAmount(text) ?: return null
        return String.format(Locale.US, "%.2f", amount)
    }

    fun parseOperationCode(text: String): String? {
        val textWithoutSecurityCode = stripSecurityCodeSentences(text)
        for (pattern in operationPatterns) {
            val match = pattern.find(textWithoutSecurityCode) ?: continue
            val context = normalize(match.value)
            if ("seguridad" in context) continue
            return match.groupValues[1].filter(Char::isDigit).takeIf { it.isNotBlank() }
        }
        return null
    }

    fun parseSecurityCode(text: String): String? {
        val compactText = text.replace("\\s+".toRegex(), " ")
        for (pattern in securityCodePatterns) {
            val match = pattern.find(compactText) ?: continue
            return match.groupValues[1].filter(Char::isDigit).takeIf { it.isNotBlank() }
        }
        return null
    }

    fun parseSenderName(text: String): String? {
        text.lineSequence()
            .map { it.trim() }
            .filter { it.isNotBlank() && normalize(it) !in genericTitles }
            .forEach { line ->
                Regex("^(.+?)\\s+te\\s+env[ií][oó]\\s+un\\s+pago\\s+por\\b", RegexOption.IGNORE_CASE)
                    .find(line)
                    ?.groupValues
                    ?.getOrNull(1)
                    ?.let { return cleanSenderName(it) }
            }

        Regex("^(.+?)\\s+te\\s+env[ií][oó]\\s+un\\s+pago\\s+por\\b", setOf(RegexOption.IGNORE_CASE, RegexOption.MULTILINE))
            .find(text)
            ?.groupValues
            ?.getOrNull(1)
            ?.let { return cleanSenderName(it) }

        Regex("^(.+?)\\s+te\\s+env[ií][oó]\\b", setOf(RegexOption.IGNORE_CASE, RegexOption.MULTILINE))
            .find(text)
            ?.groupValues
            ?.getOrNull(1)
            ?.let { return cleanSenderName(it) }

        val match = Regex("(?:de|remitente|te yape[oó])\\s*:?\\s*([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\\s.]{2,60})", RegexOption.IGNORE_CASE).find(text)
        return match?.groupValues?.getOrNull(1)?.let { cleanSenderName(it) }
    }

    private fun cleanSenderName(value: String): String? {
        val cleaned = value
            .substringAfterLast("Confirmación de Pago", value)
            .substringAfterLast("Confirmacion de Pago", value)
            .replace("*", "")
            .lineSequence()
            .map { it.trim() }
            .filter { it.isNotBlank() && normalize(it) !in genericTitles }
            .joinToString(" ")
            .replace("\\s+".toRegex(), " ")
            .trim()

        return cleaned.takeIf { it.isNotBlank() }
    }

    private fun stripSecurityCodeSentences(text: String): String {
        var result = text.replace("\\s+".toRegex(), " ")
        for (pattern in securityCodePatterns) {
            result = result.replace(pattern, " ")
        }
        return result
    }

    private val genericTitles = setOf(
        "confirmacion de pago",
        "confirmacion pago",
        "pago"
    )
}

object YapeCandidateScorer {
    fun score(intent: PendingIntentDto, text: String): Int {
        val normalized = YapeTextParser.normalize(text)
        var score = 0
        val parsedAmount = YapeTextParser.parseAmount(text)
        if (parsedAmount != null && kotlin.math.abs(parsedAmount - intent.expectedAmount) < 0.001) score += 65

        val tokens = YapeTextParser.normalize(intent.customerName)
            .split(" ")
            .filter { it.length >= 3 && it !in setOf("del", "de", "las", "los", "sra", "sr") }
            .take(3)
        val matched = tokens.count { it in normalized }
        score += (matched * 15).coerceAtMost(30)

        intent.securityCode?.let { if (it.isNotBlank() && it in normalized) score += 25 }
        intent.operationCode?.let { if (it.isNotBlank() && it in normalized) score += 20 }
        return score.coerceAtMost(100)
    }
}
