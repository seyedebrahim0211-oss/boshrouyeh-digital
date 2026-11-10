import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import 'app.dart';
import 'core/api.dart';
import 'core/auth_store.dart';
import 'core/settings.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  final auth = AuthStore();
  final settings = AppSettings();
  await Future.wait([auth.load(), settings.load()]);
  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: auth),
        ChangeNotifierProvider.value(value: settings),
        Provider<Api>(create: (_) => Api(auth)),
      ],
      child: const BoshrouyehApp(),
    ),
  );
}
