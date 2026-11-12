#!/usr/bin/env bash
# Generates Android/iOS/Web host projects and applies the permissions & settings the app needs.
# Safe to re-run. Requires the Flutter SDK on PATH.
set -euo pipefail
cd "$(dirname "$0")/.."

flutter create . --platforms=android,ios --project-name boshrouyeh_man --org ir.boshrooyeh --no-overwrite >/dev/null

MANIFEST=android/app/src/main/AndroidManifest.xml
add_perm() { grep -q "$1" "$MANIFEST" || sed -i "s#<application#<uses-permission android:name=\"$1\"/>\n    <application#" "$MANIFEST"; }
add_perm android.permission.INTERNET
add_perm android.permission.ACCESS_FINE_LOCATION
add_perm android.permission.ACCESS_COARSE_LOCATION
add_perm android.permission.CAMERA
# In-app update: download the APK from the website and open the Android installer (ota_update).
add_perm android.permission.REQUEST_INSTALL_PACKAGES
grep -q OtaUpdateFileProvider "$MANIFEST" || sed -i 's#</application>#    <provider android:name="sk.fourq.otaupdate.OtaUpdateFileProvider" android:authorities="${applicationId}.ota_update_provider" android:exported="false" android:grantUriPermissions="true">\n            <meta-data android:name="android.support.FILE_PROVIDER_PATHS" android:resource="@xml/filepaths"/>\n        </provider>\n    </application>#' "$MANIFEST"
mkdir -p android/app/src/main/res/xml
cat > android/app/src/main/res/xml/filepaths.xml <<'XML'
<?xml version="1.0" encoding="utf-8"?>
<paths xmlns:android="http://schemas.android.com/apk/res/android">
    <files-path name="internal_apk_storage" path="ota_update/"/>
</paths>
XML
# ota_update 7.x needs: minSdk >= 23, compileSdk >= 36 and core library desugaring in the app module.
GK=android/app/build.gradle.kts
GG=android/app/build.gradle
if [ -f "$GK" ] && ! grep -q 'bmn-ota' "$GK"; then
  sed -i 's#compileSdk = flutter.compileSdkVersion#compileSdk = maxOf(36, flutter.compileSdkVersion)#' "$GK"
  sed -i 's#minSdk = flutter.minSdkVersion#minSdk = maxOf(23, flutter.minSdkVersion)#' "$GK"
  sed -i 's#compileOptions {#compileOptions {\n        isCoreLibraryDesugaringEnabled = true#' "$GK"
  printf '\n// bmn-ota\ndependencies {\n    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")\n}\n' >> "$GK"
  # Release-time lint ("lintVital") crashes inside plugins on compileSdk 36 sources; it is only a check.
  printf '\nandroid {\n    lint {\n        checkReleaseBuilds = false\n        abortOnError = false\n    }\n}\n' >> "$GK"
elif [ -f "$GG" ] && ! grep -q 'bmn-ota' "$GG"; then
  sed -i 's#compileSdk = flutter.compileSdkVersion#compileSdk = Math.max(36, flutter.compileSdkVersion)#; s#compileSdkVersion flutter.compileSdkVersion#compileSdkVersion Math.max(36, flutter.compileSdkVersion)#' "$GG"
  sed -i 's#minSdk = flutter.minSdkVersion#minSdk = Math.max(23, flutter.minSdkVersion)#; s#minSdkVersion flutter.minSdkVersion#minSdkVersion Math.max(23, flutter.minSdkVersion)#' "$GG"
  sed -i 's#compileOptions {#compileOptions {\n        coreLibraryDesugaringEnabled true#' "$GG"
  printf '\n// bmn-ota\ndependencies {\n    coreLibraryDesugaring "com.android.tools:desugar_jdk_libs:2.1.4"\n}\n' >> "$GG"
  printf '\nandroid {\n    lintOptions {\n        checkReleaseBuilds false\n        abortOnError false\n    }\n}\n' >> "$GG"
fi
# App label (Persian) and cleartext off.
sed -i 's#android:label="[^"]*"#android:label="بشرویهٔ من"#' "$MANIFEST"
grep -q usesCleartextTraffic "$MANIFEST" || sed -i 's#<application#<application android:usesCleartextTraffic="false"#' "$MANIFEST"
# url_launcher visibility (Android 11+).
grep -q '<queries>' "$MANIFEST" || sed -i 's#</manifest>#    <queries>\n        <intent><action android:name="android.intent.action.VIEW"/><data android:scheme="https"/></intent>\n        <intent><action android:name="android.intent.action.DIAL"/><data android:scheme="tel"/></intent>\n        <intent><action android:name="android.intent.action.VIEW"/><data android:scheme="geo"/></intent>\n    </queries>\n</manifest>#' "$MANIFEST"

PLIST=ios/Runner/Info.plist
add_plist() {
  grep -q "$1" "$PLIST" || sed -i "s#<dict>#<dict>\n\t<key>$1</key>\n\t<string>$2</string>#" "$PLIST"
}
add_plist NSCameraUsageDescription "برای ثبت عکس در گزارش ۱۳۷"
add_plist NSPhotoLibraryUsageDescription "برای انتخاب عکس گزارش ۱۳۷"
add_plist NSLocationWhenInUseUsageDescription "برای ثبت موقعیت گزارش ۱۳۷ و مسیریابی جاذبه‌ها"
sed -i 's#<string>Boshrouyeh Man</string>#<string>بشرویهٔ من</string>#' "$PLIST" || true

# Launcher icons from the municipality logo.
for d in mdpi hdpi xhdpi xxhdpi xxxhdpi; do
  cp "assets/launcher/mipmap-$d/ic_launcher.png" "android/app/src/main/res/mipmap-$d/ic_launcher.png"
done
if [ -d ios/Runner/Assets.xcassets/AppIcon.appiconset ]; then
  cp assets/launcher/ios/Icon-App-1024x1024@1x.png ios/Runner/Assets.xcassets/AppIcon.appiconset/Icon-App-1024x1024@1x.png
fi
echo "Platforms ready."
