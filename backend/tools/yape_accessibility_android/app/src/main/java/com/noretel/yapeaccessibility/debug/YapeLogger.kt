package com.noretel.yapeaccessibility.debug

import android.util.Log

object YapeLogger {
    const val TAG = "NoreTelYape"

    fun d(message: String) {
        Log.d(TAG, message)
    }

    fun w(message: String) {
        Log.w(TAG, message)
    }

    fun e(message: String, throwable: Throwable? = null) {
        if (throwable == null) {
            Log.e(TAG, message)
        } else {
            Log.e(TAG, message, throwable)
        }
    }

    fun truncate(value: String?, maxLength: Int = 500): String {
        if (value.isNullOrBlank()) return ""
        return if (value.length <= maxLength) value else value.take(maxLength) + "...[truncated]"
    }
}
