# Android release signing

`upload.jks.enc` is the release keystore encrypted with AES-256 (PBKDF2, 200k iterations).
CI decrypts it with the repository secret **`ANDROID_KEYSTORE_PASSWORD`** (same value is the store/key password, alias `boshrouyeh`).

Without that secret CI still builds a debug-signed APK artifact but does **not** publish a GitHub Release
(updates must always be signed with the same key, otherwise Android refuses to install them over the old app).

Never commit the decrypted `upload.jks` or the password. Keep an offline backup of both: losing them means users must uninstall to update.
