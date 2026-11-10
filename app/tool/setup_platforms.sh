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
