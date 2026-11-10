#!/usr/bin/env python3
"""Wire android/key.properties into the generated Gradle file (Kotlin DSL or Groovy).

Run after `flutter create` (tool/setup_platforms.sh). Idempotent. Does nothing when
android/key.properties is absent, so local/debug builds keep the default debug signing.
"""
import pathlib
import sys

root = pathlib.Path(__file__).resolve().parent.parent / "android"
if not (root / "key.properties").exists():
    print("apply_signing: no android/key.properties — skipping (debug signing).")
    sys.exit(0)

kts = root / "app" / "build.gradle.kts"
groovy = root / "app" / "build.gradle"
MARK = "// bmn-signing"

if kts.exists():
    s = kts.read_text(encoding="utf-8")
    if MARK not in s:
        loader = (
            f"{MARK}\n"
            "val bmnKeyProps = java.util.Properties().apply {\n"
            "    val f = rootProject.file(\"key.properties\")\n"
            "    if (f.exists()) f.inputStream().use { load(it) }\n"
            "}\n\n"
        )
        s = s.replace("android {", loader + "android {", 1)
        cfg = (
            "    signingConfigs {\n"
            "        create(\"release\") {\n"
            "            keyAlias = bmnKeyProps.getProperty(\"keyAlias\")\n"
            "            keyPassword = bmnKeyProps.getProperty(\"keyPassword\")\n"
            "            storeFile = file(bmnKeyProps.getProperty(\"storeFile\"))\n"
            "            storePassword = bmnKeyProps.getProperty(\"storePassword\")\n"
            "        }\n"
            "    }\n\n"
            "    buildTypes {"
        )
        s = s.replace("    buildTypes {", cfg, 1)
        s = s.replace('signingConfig = signingConfigs.getByName("debug")', 'signingConfig = signingConfigs.getByName("release")')
        if 'signingConfigs.getByName("release")' not in s:
            sys.exit("apply_signing: could not find release signingConfig line in build.gradle.kts")
        kts.write_text(s, encoding="utf-8")
elif groovy.exists():
    s = groovy.read_text(encoding="utf-8")
    if MARK not in s:
        loader = (
            f"{MARK}\n"
            "def bmnKeyProps = new Properties()\n"
            "def bmnKeyFile = rootProject.file('key.properties')\n"
            "if (bmnKeyFile.exists()) { bmnKeyFile.withInputStream { bmnKeyProps.load(it) } }\n\n"
        )
        s = s.replace("android {", loader + "android {", 1)
        cfg = (
            "    signingConfigs {\n"
            "        release {\n"
            "            keyAlias bmnKeyProps['keyAlias']\n"
            "            keyPassword bmnKeyProps['keyPassword']\n"
            "            storeFile file(bmnKeyProps['storeFile'])\n"
            "            storePassword bmnKeyProps['storePassword']\n"
            "        }\n"
            "    }\n\n"
            "    buildTypes {"
        )
        s = s.replace("    buildTypes {", cfg, 1)
        s = s.replace("signingConfig signingConfigs.debug", "signingConfig signingConfigs.release")
        groovy.write_text(s, encoding="utf-8")
else:
    sys.exit("apply_signing: no app/build.gradle(.kts) found — run tool/setup_platforms.sh first")

print("apply_signing: release signing configured.")
