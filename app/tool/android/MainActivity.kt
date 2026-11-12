package ir.boshrooyeh.boshrouyeh_man

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.content.ContextCompat
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger, "bmn.app/update-notifications"
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "setEnabled" -> {
                    val enabled = call.argument<Boolean>("enabled") ?: true
                    UpdateBackgroundScheduler.configure(applicationContext, enabled)
                    if (enabled) requestNotificationPermissionOnce()
                    result.success(true)
                }
                else -> result.notImplemented()
            }
        }
    }

    private fun requestNotificationPermissionOnce() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) return
        if (ContextCompat.checkSelfPermission(
                this, Manifest.permission.POST_NOTIFICATIONS
            ) == PackageManager.PERMISSION_GRANTED) return
        val prefs = getSharedPreferences(UpdateBackgroundScheduler.PREFS, Context.MODE_PRIVATE)
        if (prefs.getBoolean("permission_asked", false)) return
        prefs.edit().putBoolean("permission_asked", true).apply()
        requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), 2026)
    }
}
