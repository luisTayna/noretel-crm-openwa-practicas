package com.noretel.yapeaccessibility

import android.app.Activity
import android.content.Intent
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.provider.Settings
import android.text.InputType
import android.view.Gravity
import android.widget.Button
import android.widget.CheckBox
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import android.widget.Toast
import com.noretel.yapeaccessibility.api.YapeApiClient
import com.noretel.yapeaccessibility.config.SecureConfigStore
import com.noretel.yapeaccessibility.debug.YapeLogger
import com.noretel.yapeaccessibility.service.YapeNotificationListenerService
import java.util.concurrent.Executors

class MainActivity : Activity() {
    private val backgroundColor = Color.rgb(245, 247, 251)
    private val primaryColor = Color.rgb(25, 118, 210)
    private val successColor = Color.rgb(18, 136, 88)
    private val warningColor = Color.rgb(245, 124, 0)
    private val textColor = Color.rgb(31, 41, 55)
    private val mutedTextColor = Color.rgb(107, 114, 128)

    private lateinit var store: SecureConfigStore
    private lateinit var backendUrl: EditText
    private lateinit var monitorToken: EditText
    private lateinit var yapePackage: EditText
    private lateinit var enabled: CheckBox
    private lateinit var status: TextView
    private val worker = Executors.newSingleThreadExecutor()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        store = SecureConfigStore(this)
        val config = store.getConfig()

        backendUrl = editText("Backend URL", config.backendUrl)
        monitorToken = visibleTokenEditText("Monitor token interno Yape", config.monitorToken)
        yapePackage = editText("Yape package", config.yapePackage)
        enabled = CheckBox(this).apply {
            text = "Automatización activa"
            textSize = 15f
            setTextColor(textColor)
            setPadding(0, dp(8), 0, dp(8))
            isChecked = config.enabled
        }

        val save = primaryButton("Guardar configuración segura", successColor) { saveConfig() }
        val notificationAccess = primaryButton("Abrir acceso a notificaciones", primaryColor) {
            startActivity(Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS))
        }
        val showLastNotification = secondaryButton("Ver última notificación capturada") { showLastNotification() }
        val testConnection = secondaryButton("Probar conexión") { testConnection() }
        val sendLastNotification = primaryButton("Enviar última notificación ahora", warningColor) {
            sendLastNotification()
        }
        status = TextView(this).apply {
            text = "Estado: esperando acción."
            textSize = 14f
            setTextColor(textColor)
            setPadding(dp(14), dp(14), dp(14), dp(14))
            background = roundedBackground(Color.rgb(239, 246, 255), dp(14), Color.rgb(191, 219, 254))
        }

        val title = TextView(this).apply {
            text = "NoreTel Yape Receiver"
            textSize = 24f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(textColor)
        }

        val subtitle = TextView(this).apply {
            text = "Escucha notificaciones reales de Yape y las envía de forma segura a Laravel."
            textSize = 15f
            setTextColor(mutedTextColor)
            setPadding(0, dp(6), 0, 0)
        }

        val info = TextView(this).apply {
            text = "Antes de usarlo:\n" +
                "• Guarda backend, token y package.\n" +
                "• Activa el acceso a notificaciones.\n" +
                "• No guarda PIN, no abre Yape y no captura pantalla."
            textSize = 14f
            setTextColor(textColor)
            setLineSpacing(0f, 1.15f)
        }

        val content = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(20), dp(24), dp(20), dp(24))
            setBackgroundColor(backgroundColor)
            addView(card {
                addView(title)
                addView(subtitle)
            })
            addView(card {
                addView(sectionTitle("Configuración"))
                addView(label("Backend Laravel"))
                addView(backendUrl)
                addView(label("Token interno Yape"))
                addView(monitorToken)
                addView(label("Package Yape"))
                addView(yapePackage)
                addView(enabled)
                addView(save)
            })
            addView(card {
                addView(sectionTitle("Operación"))
                addView(testConnection)
                addView(notificationAccess)
                addView(showLastNotification)
                addView(sendLastNotification)
            })
            addView(card {
                addView(sectionTitle("Último estado"))
                addView(status)
            })
            addView(card { addView(info) })
        }

        setContentView(ScrollView(this).apply {
            setBackgroundColor(backgroundColor)
            addView(content)
        })
    }

    private fun saveConfig() {
        val tokenValue = monitorToken.text.toString()
        if (backendUrl.text.isBlank() || yapePackage.text.isBlank()) {
            Toast.makeText(this, "Backend y package son obligatorios.", Toast.LENGTH_LONG).show()
            return
        }

        store.saveConfig(
            backendUrl = backendUrl.text.toString(),
            monitorToken = tokenValue,
            yapePackage = yapePackage.text.toString(),
            enabled = enabled.isChecked
        )
        Toast.makeText(this, "Configuración guardada.", Toast.LENGTH_LONG).show()
        updateStatus("Configuración guardada. Token no se muestra en logs.")
    }

    private fun testConnection() {
        saveEditableConfigIfNeeded()
        updateStatus("Probando conexión con Laravel...")
        worker.execute {
            val message = try {
                val config = store.getConfig()
                if (config.backendUrl.isBlank() || config.monitorToken.isBlank()) {
                    "Error: backend URL y token son obligatorios."
                } else {
                    YapeLogger.d("MainActivity Probar conexión: consultando pending-receiver-intents.")
                    val intents = YapeApiClient(config.backendUrl, config.monitorToken)
                        .pendingReceiverIntents(config.pendingLimit)
                    YapeLogger.d("MainActivity Probar conexión: count=${intents.size}")
                    "Conexión OK. Intents pendientes: ${intents.size}"
                }
            } catch (throwable: Throwable) {
                YapeLogger.e("MainActivity Probar conexión falló: ${throwable.message}", throwable)
                "Error conexión: ${throwable.message}"
            }
            runOnUiThread { updateStatus(message) }
        }
    }

    private fun sendLastNotification() {
        saveEditableConfigIfNeeded()
        val service = YapeNotificationListenerService.instance
        if (service == null) {
            updateStatus("Listener de notificaciones no está activo. Activa el acceso a notificaciones primero.")
            return
        }
        updateStatus("Enviando última notificación capturada...")
        service.sendLastNotificationFromUi { message ->
            runOnUiThread { updateStatus(message) }
        }
    }

    private fun showLastNotification() {
        val service = YapeNotificationListenerService.instance
        val message = service?.getLastNotificationSummary()
            ?: store.getLastNotification()?.let { "Última notificación guardada: ${YapeLogger.truncate(it, 500)}" }
            ?: "Todavía no hay notificación Yape capturada."
        updateStatus(message)
    }

    private fun saveEditableConfigIfNeeded() {
        store.saveConfig(
            backendUrl = backendUrl.text.toString(),
            monitorToken = monitorToken.text.toString(),
            yapePackage = yapePackage.text.toString(),
            enabled = enabled.isChecked
        )
    }

    private fun updateStatus(message: String) {
        status.text = "Estado: $message"
        YapeLogger.d("UI estado: $message")
    }

    private fun label(value: String): TextView = TextView(this).apply {
        text = value
        textSize = 13f
        setTextColor(mutedTextColor)
        gravity = Gravity.START
        setPadding(0, dp(16), 0, dp(6))
    }

    private fun sectionTitle(value: String): TextView = TextView(this).apply {
        text = value
        textSize = 18f
        typeface = Typeface.DEFAULT_BOLD
        setTextColor(textColor)
        setPadding(0, 0, 0, dp(6))
    }

    private fun editText(hint: String, value: String, password: Boolean = false): EditText = EditText(this).apply {
        this.hint = hint
        setText(value)
        maxLines = 1
        isSingleLine = true
        textSize = 15f
        setTextColor(textColor)
        setHintTextColor(Color.rgb(156, 163, 175))
        setPadding(dp(12), dp(10), dp(12), dp(10))
        background = roundedBackground(Color.WHITE, dp(12), Color.rgb(209, 213, 219))
        inputType = if (password) {
            InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_PASSWORD
        } else {
            InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI
        }
    }

    private fun visibleTokenEditText(hint: String, value: String): EditText = EditText(this).apply {
        this.hint = hint
        maxLines = 1
        isSingleLine = true
        textSize = 15f
        setTextColor(textColor)
        setHintTextColor(Color.rgb(156, 163, 175))
        setPadding(dp(12), dp(10), dp(12), dp(10))
        background = roundedBackground(Color.WHITE, dp(12), Color.rgb(209, 213, 219))
        typeface = Typeface.MONOSPACE
        transformationMethod = null
        inputType = InputType.TYPE_CLASS_TEXT or
            InputType.TYPE_TEXT_VARIATION_NORMAL or
            InputType.TYPE_TEXT_FLAG_NO_SUGGESTIONS
        setRawInputType(
            InputType.TYPE_CLASS_TEXT or
                InputType.TYPE_TEXT_VARIATION_NORMAL or
                InputType.TYPE_TEXT_FLAG_NO_SUGGESTIONS
        )
        setText(value)
    }

    private fun primaryButton(text: String, color: Int, action: () -> Unit): Button = Button(this).apply {
        this.text = text
        textSize = 14f
        setTextColor(Color.WHITE)
        isAllCaps = false
        setPadding(dp(12), dp(10), dp(12), dp(10))
        background = roundedBackground(color, dp(14))
        setOnClickListener { action() }
        layoutParams = LinearLayout.LayoutParams(
            LinearLayout.LayoutParams.MATCH_PARENT,
            LinearLayout.LayoutParams.WRAP_CONTENT
        ).apply {
            topMargin = dp(10)
        }
    }

    private fun secondaryButton(text: String, action: () -> Unit): Button = Button(this).apply {
        this.text = text
        textSize = 14f
        setTextColor(primaryColor)
        isAllCaps = false
        setPadding(dp(12), dp(10), dp(12), dp(10))
        background = roundedBackground(Color.WHITE, dp(14), Color.rgb(147, 197, 253))
        setOnClickListener { action() }
        layoutParams = LinearLayout.LayoutParams(
            LinearLayout.LayoutParams.MATCH_PARENT,
            LinearLayout.LayoutParams.WRAP_CONTENT
        ).apply {
            topMargin = dp(10)
        }
    }

    private fun card(content: LinearLayout.() -> Unit): LinearLayout = LinearLayout(this).apply {
        orientation = LinearLayout.VERTICAL
        setPadding(dp(18), dp(18), dp(18), dp(18))
        background = roundedBackground(Color.WHITE, dp(20), Color.rgb(229, 231, 235))
        layoutParams = LinearLayout.LayoutParams(
            LinearLayout.LayoutParams.MATCH_PARENT,
            LinearLayout.LayoutParams.WRAP_CONTENT
        ).apply {
            bottomMargin = dp(14)
        }
        content()
    }

    private fun roundedBackground(color: Int, radius: Int, strokeColor: Int? = null): GradientDrawable {
        return GradientDrawable().apply {
            setColor(color)
            cornerRadius = radius.toFloat()
            strokeColor?.let { setStroke(dp(1), it) }
        }
    }

    private fun dp(value: Int): Int = (value * resources.displayMetrics.density).toInt()

    override fun onDestroy() {
        worker.shutdownNow()
        super.onDestroy()
    }
}
