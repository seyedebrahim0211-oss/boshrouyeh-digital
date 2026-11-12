package ir.boshrooyeh.boshrouyeh_man

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequest
import androidx.work.Worker
import androidx.work.WorkerParameters
import androidx.work.WorkManager
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.TimeUnit

/**
 * Background update notices without a third-party push service.
 *
 * WorkManager timing is controlled by Android: this is NOT instant push, and is
 * not guaranteed to execute when the app is force-stopped or battery-restricted.
 */
internal object UpdateBackgroundScheduler {
    const val PREFS = "bmn_update_notifications"
    const val CHANNEL = "bmn_app_updates"
    private const val JOB = "bmn_periodic_update_check"
    const val NOTIFICATION_ID = 1024

    fun configure(context: Context, enabled: Boolean) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit()
            .putBoolean("enabled", enabled).apply()
        val wm = WorkManager.getInstance(context.applicationContext)
        if (!enabled) {
            wm.cancelUniqueWork(JOB)
            NotificationManagerCompat.from(context).cancel(NOTIFICATION_ID)
            return
        }
        createChannel(context)
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build()
        val work = PeriodicWorkRequest.Builder(
            UpdateCheckWorker::class.java, 30, TimeUnit.MINUTES
        ).setConstraints(constraints).build()
        wm.enqueueUniquePeriodicWork(JOB, ExistingPeriodicWorkPolicy.KEEP, work)
    }

    fun isEnabled(context: Context): Boolean =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getBoolean("enabled", true)

    fun createChannel(context: Context) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(
                NotificationChannel(CHANNEL, "بروزرسانی اپ بشرویهٔ من", NotificationManager.IMPORTANCE_DEFAULT)
                    .apply { description = "اطلاع‌رسانی انتشار نسخه جدید اپ شهرداری" }
            )
        }
    }
}

class UpdateCheckWorker(context: Context, parameters: WorkerParameters) : Worker(context, parameters) {
    override fun doWork(): Result {
        val ctx = applicationContext
        if (!UpdateBackgroundScheduler.isEnabled(ctx)) return Result.success()
        try {
            // Public, unauthenticated endpoint; never transmit resident data or access tokens.
            val conn = URL("https://varsin-bsh.ir/wp-json/bmn/v1/app/version?platform=android").openConnection() as HttpURLConnection
            val payload = try {
                conn.requestMethod = "GET"
                conn.connectTimeout = 10000
                conn.readTimeout = 10000
                conn.useCaches = false
                conn.setRequestProperty("Cache-Control", "no-cache")
                conn.setRequestProperty("Accept", "application/json")
                conn.setRequestProperty("User-Agent", "BoshrouyehApp/Android-background-updater")
                if (conn.responseCode != HttpURLConnection.HTTP_OK) return Result.retry()
                conn.inputStream.bufferedReader(Charsets.UTF_8).use { it.readText() }
            } finally {
                conn.disconnect()
            }
            val data = JSONObject(payload)
            val latestCode = data.optLong("versionCode", 0L)
            val versionName = data.optString("versionName", data.optString("latest", ""))
            val apk = data.optString("apk", data.optString("url", ""))
            val checksum = data.optString("sha256", "")
            val installed = ctx.packageManager.getPackageInfo(ctx.packageName, 0)
            @Suppress("DEPRECATION")
            val installedCode = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                installed.longVersionCode
            } else {
                installed.versionCode.toLong()
            }
            // Only notify on independently verifiable, newer Android build releases.
            if (latestCode <= installedCode || !apk.startsWith("https://") ||
                !Regex("^[a-fA-F0-9]{64}$").matches(checksum)) return Result.success()
            if (Build.VERSION.SDK_INT >= 33 && ContextCompat.checkSelfPermission(
                    ctx, Manifest.permission.POST_NOTIFICATIONS
                ) != PackageManager.PERMISSION_GRANTED) return Result.success()
            if (!NotificationManagerCompat.from(ctx).areNotificationsEnabled()) return Result.success()

            val prefs = ctx.getSharedPreferences(UpdateBackgroundScheduler.PREFS, Context.MODE_PRIVATE)
            val releaseKey = "${installedCode}:${latestCode}"
            if (releaseKey == prefs.getString("last_notified", "")) return Result.success()
            UpdateBackgroundScheduler.createChannel(ctx)
            val launch = Intent(ctx, MainActivity::class.java)
                .addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
                .putExtra("bmn_open_update", true)
            val intent = PendingIntent.getActivity(ctx, 0, launch,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
            val displayVersion = versionName.ifBlank { latestCode.toString() }
            val notification = NotificationCompat.Builder(ctx, UpdateBackgroundScheduler.CHANNEL)
                .setSmallIcon(android.R.drawable.stat_sys_download_done)
                .setContentTitle("نسخه جدید بشرویهٔ من منتشر شد")
                .setContentText("نسخه $displayVersion آماده دریافت است. برای بروزرسانی ضربه بزنید.")
                .setStyle(NotificationCompat.BigTextStyle().bigText(
                    "نسخه $displayVersion منتشر شده است. برای دانلود و نصب روی همین برنامه ضربه بزنید؛ اطلاعات شما حفظ می‌شود."
                ))
                .setContentIntent(intent)
                .setAutoCancel(true)
                .setPriority(NotificationCompat.PRIORITY_DEFAULT)
                .build()
            NotificationManagerCompat.from(ctx).notify(UpdateBackgroundScheduler.NOTIFICATION_ID, notification)
            prefs.edit().putString("last_notified", releaseKey).apply()
            return Result.success()
        } catch (_: Exception) {
            return Result.retry()
        }
    }
}
