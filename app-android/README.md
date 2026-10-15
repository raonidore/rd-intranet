# RD.Intranet para Android

App da equipe de TI. Primeiro módulo: **Chamados** (fila, "comigo", equipe,
encerrados, abrir, assumir, responder, nota interna, status, transferir, fotos).

- **Multi-cliente:** cada cliente tem o próprio RD.Intranet. Na tela de entrada,
  "Avançado" guarda vários endereços, com um login por endereço. O endereço
  padrão é `https://sidore.rd.inf.br/rd.intranet`.
- **Servidor:** fala só com `/api/app/*` (`app/Controllers/AppApiController.php`).
  O token vai no cabeçalho `X-RD-Token`. Permissões, SLA e fila por setor são os
  mesmos do painel. O servidor precisa estar com a migração
  `2026_10_15_app_android.sql` aplicada ("Aplicar atualização" + migrate).
- **Stack:** Capacitor 8 com HTML/JS puro (`www/`). O HTTP é nativo (CapacitorHttp),
  então não há CORS, e servidores `http://` também funcionam.

## Compilar

O toolchain fica em `C:\android-build` (JDK, SDK, Gradle e emulador) e a chave de
assinatura em `C:\android-build\chaves\rdintranet.*`.

**Guarde cópia de segurança da chave.** Sem ela, não dá para publicar atualizações
na Play Store.

```bash
cd app-android
npm install                 # só na primeira vez
export JAVA_HOME='C:\android-build\jdk' ANDROID_HOME='C:\android-build\sdk' GRADLE_USER_HOME='C:\android-build\gradle'
npx cap sync android
cd android
./gradlew assembleDebug                    # teste: app/build/outputs/apk/debug
./gradlew assembleRelease bundleRelease    # final: APK (instalação direta) + AAB (Play Store)
```

Para lançar uma nova versão, suba `versionCode`/`versionName` em
`android/app/build.gradle` e `VERSAO` em `www/app.js`.
